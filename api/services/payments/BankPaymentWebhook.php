<?php

namespace App\API\Services\payments;

use App\Database\Database;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Bank Payment Webhook Service
 * 
 * Handles bank payment notifications from:
 * - KCB Bank
 * - Other bank integrations
 * 
 * Uses admission number as account reference for student payments
 */
class BankPaymentWebhook
{
    private $db;
    private $apiKey;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        // Use the global constant explicitly (avoid namespaced constant lookup),
        // and fall back to an environment variable if the constant is not defined.
        if (defined('BANK_API_KEY')) {
            $this->apiKey = \BANK_API_KEY;
        } else {
            $envKey = getenv('BANK_API_KEY');
            $this->apiKey = $envKey !== false ? $envKey : '';
        }
    }

    /**
     * Validate webhook request
     * @param array $headers Request headers
     * @param string $payload Request payload
     * @return bool Valid or not
     */
    public function validateWebhookSignature($headers, $payload)
    {
        // Implement signature validation based on bank's specification
        // For KCB and other banks, they typically send:
        // - X-Signature header with HMAC signature
        // - X-API-Key header with API key

        if (isset($headers['X-API-Key']) && $headers['X-API-Key'] === $this->apiKey) {
            return true;
        }

        // Validate HMAC signature if provided
        if (isset($headers['X-Signature'])) {
            $signature = $headers['X-Signature'];
            $expectedSignature = hash_hmac('sha256', $payload, $this->apiKey);
            return hash_equals($expectedSignature, $signature);
        }

        return false;
    }

    /**
     * Process KCB Bank payment notification
     * @param array $paymentData Payment notification data
     * @return array Response
     */
    public function processKCBPayment($paymentData)
    {
        try {
            $this->db->beginTransaction();

            // Log raw webhook data
            $this->logWebhook('KCB', $paymentData);

            // Extract payment details (adjust based on KCB's actual webhook format)
            $accountNumber = $paymentData['account_number'] ?? $paymentData['reference'] ?? null; // Admission number
            $amount = $paymentData['amount'] ?? 0;
            $transactionRef = $paymentData['transaction_reference'] ?? $paymentData['transaction_id'] ?? null;
            $transactionDate = $paymentData['transaction_date'] ?? date('Y-m-d H:i:s');
            $bankName = $paymentData['bank_name'] ?? 'KCB Bank';
            $senderAccount = $paymentData['sender_account'] ?? null;
            $narration = $paymentData['narration'] ?? 'Bank Payment';

            // Validate required fields
            if (!$accountNumber || !$amount || !$transactionRef) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Missing required payment fields');
            }

            // Validate admission number and get student
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, admission_number, current_class_id
                FROM students 
                WHERE admission_number = ?
            ");
            $stmt->execute([$accountNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->db->rollBack();
                $this->logWebhookError('KCB', 'Invalid admission number: ' . $accountNumber, $paymentData);
                return formatResponse(false, null, 'Invalid admission number');
            }

            // Check for duplicate transaction
            $stmt = $this->db->prepare("
                SELECT id FROM bank_transactions 
                WHERE transaction_ref = ?
            ");
            $stmt->execute([$transactionRef]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Duplicate transaction');
            }

            // Process payment using stored procedure
            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $student['id'],
                $amount,
                'bank',
                $transactionRef,
                $transactionDate,
                null, // received_by (automatic)
                $narration . ' - ' . $student['admission_number']
            ]);

            // Get the payment ID
            $stmt = $this->db->prepare("
                SELECT id FROM payment_transactions 
                WHERE transaction_ref = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$transactionRef]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payment) {
                // Record bank transaction details
                $stmt = $this->db->prepare("
                    INSERT INTO bank_transactions (
                        payment_id, transaction_ref, amount, transaction_date,
                        bank_name, account_number, narration, status,
                        webhook_data, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
                ");

                $stmt->execute([
                    $payment['id'],
                    $transactionRef,
                    $amount,
                    $transactionDate,
                    $bankName,
                    $senderAccount,
                    $narration,
                    json_encode($paymentData)
                ]);
            }

            $this->db->commit();

            // Send confirmation notification
            $this->sendPaymentConfirmation($student, $amount, $transactionRef, 'bank');

            return formatResponse(true, [
                'message' => 'Bank payment processed successfully',
                'student' => $student['first_name'] . ' ' . $student['last_name'],
                'admission_number' => $student['admission_number'],
                'amount' => $amount,
                'transaction_ref' => $transactionRef
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Bank Payment Processing Error: " . $e->getMessage());
            $this->logWebhookError('KCB', $e->getMessage(), $paymentData);
            return formatResponse(false, null, 'Failed to process bank payment: ' . $e->getMessage());
        }
    }

    /**
     * Process generic bank payment (for other banks)
     * @param array $paymentData Payment notification data
     * @param string $bankName Bank name
     * @return array Response
     */
    public function processGenericBankPayment($paymentData, $bankName = 'Bank')
    {
        try {
            $this->db->beginTransaction();

            // Log webhook
            $this->logWebhook($bankName, $paymentData);

            // Extract common fields (banks may have different formats)
            $accountNumber = $this->extractAccountNumber($paymentData);
            $amount = $this->extractAmount($paymentData);
            $transactionRef = $this->extractTransactionRef($paymentData);
            $transactionDate = $this->extractTransactionDate($paymentData);

            if (!$accountNumber || !$amount || !$transactionRef) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid payment data format');
            }

            // Get student by admission number
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, admission_number 
                FROM students 
                WHERE admission_number = ?
            ");
            $stmt->execute([$accountNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->db->rollBack();
                $this->logWebhookError($bankName, 'Invalid admission number: ' . $accountNumber, $paymentData);
                return formatResponse(false, null, 'Invalid admission number');
            }

            // Process payment
            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $student['id'],
                $amount,
                'bank',
                $transactionRef,
                $transactionDate,
                null,
                $bankName . ' Payment - ' . $student['admission_number']
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'message' => 'Payment processed successfully',
                'student' => $student['first_name'] . ' ' . $student['last_name'],
                'amount' => $amount
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Bank Payment Error: " . $e->getMessage());
            return formatResponse(false, null, 'Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Extract account number from payment data
     */
    private function extractAccountNumber($data)
    {
        $fields = ['account_number', 'account_ref', 'reference', 'customer_ref', 'bill_ref'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        return null;
    }

    /**
     * Extract amount from payment data
     */
    private function extractAmount($data)
    {
        $fields = ['amount', 'trans_amount', 'transaction_amount', 'paid_amount'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && $data[$field] > 0) {
                return (float) $data[$field];
            }
        }
        return 0;
    }

    /**
     * Extract transaction reference from payment data
     */
    private function extractTransactionRef($data)
    {
        $fields = ['transaction_ref', 'transaction_id', 'trans_id', 'reference', 'receipt_number'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        return null;
    }

    /**
     * Extract transaction date from payment data
     */
    private function extractTransactionDate($data)
    {
        $fields = ['transaction_date', 'trans_date', 'date', 'payment_date'];
        foreach ($fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * Log webhook data
     */
    private function logWebhook($source, $data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_webhooks_log (
                    source, webhook_data, status, created_at
                ) VALUES (?, ?, 'received', NOW())
            ");

            $stmt->execute([
                $source,
                json_encode($data)
            ]);

            // Also log to file
            $logFile = __DIR__ . '/../../../../logs/bank_webhooks.log';
            $logDir = dirname($logFile);

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] [$source] " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

            file_put_contents($logFile, $logEntry, FILE_APPEND);

        } catch (Exception $e) {
            error_log("Failed to log webhook: " . $e->getMessage());
        }
    }

    /**
     * Log webhook error
     */
    private function logWebhookError($source, $error, $data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_webhooks_log (
                    source, webhook_data, status, error_message, created_at
                ) VALUES (?, ?, 'error', ?, NOW())
            ");

            $stmt->execute([
                $source,
                json_encode($data),
                $error
            ]);

        } catch (Exception $e) {
            error_log("Failed to log webhook error: " . $e->getMessage());
        }
    }

    /**
     * Send payment confirmation to student/parent
     */
    private function sendPaymentConfirmation($student, $amount, $transactionRef, $method)
    {
        try {
            // Get student contact info
            $stmt = $this->db->prepare("
                SELECT s.phone_number, s.email, 
                       p.phone_number as parent_phone, p.email as parent_email
                FROM students s
                LEFT JOIN parents p ON s.parent_id = p.id
                WHERE s.id = ?
            ");
            $stmt->execute([$student['id']]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            $message = "Payment Received!\n";
            $message .= "Student: " . $student['first_name'] . ' ' . $student['last_name'] . "\n";
            $message .= "Admission: " . $student['admission_number'] . "\n";
            $message .= "Amount: KES " . number_format($amount, 2) . "\n";
            $message .= "Ref: " . $transactionRef . "\n";
            $message .= "Method: " . strtoupper($method) . "\n";
            $message .= "Thank you for your payment.";

            // Send SMS if phone number available
            if (!empty($contact['parent_phone'])) {
                // TODO: Integrate with SMS service
                // sendSMS($contact['parent_phone'], $message);
            }

            // Send email if available
            if (!empty($contact['parent_email'])) {
                // TODO: Integrate with email service
                // sendEmail($contact['parent_email'], 'Payment Confirmation', $message);
            }

        } catch (Exception $e) {
            error_log("Failed to send payment confirmation: " . $e->getMessage());
        }
    }
}
