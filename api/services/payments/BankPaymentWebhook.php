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
    private $admissionColumn;

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
        $this->admissionColumn = null;
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
                return formatResponse(false, null, 'Missing required payment fields');
            }

            // Validate admission number and get student
            $student = $this->getStudentByAdmission($accountNumber);
            if (!$student) {
                $this->logWebhookError('KCB', 'Invalid admission number: ' . $accountNumber, $paymentData);
                return formatResponse(false, null, 'Student not found for admission: ' . $accountNumber);
            }

            // Check for duplicate transaction
            $stmt = $this->db->prepare("
                SELECT id FROM payment_transactions 
                WHERE reference_no = ?
            ");
            $stmt->execute([$transactionRef]);

            if ($stmt->fetch()) {
                return formatResponse(false, null, 'Duplicate transaction');
            }

            // Process payment using stored procedure
            // NOTE: Procedure handles its own transaction, do not use explicit transaction management
            // sp_process_student_payment(p_student_id, p_parent_id, p_amount_paid, p_payment_method, 
            //                            p_reference_no, p_receipt_no, p_received_by, p_payment_date, p_notes)
            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $student['id'],                           // p_student_id
                $student['parent_id'] ?? 0,               // p_parent_id
                $amount,                                  // p_amount_paid
                'bank_transfer',                          // p_payment_method
                $transactionRef,                          // p_reference_no
                null,                                     // p_receipt_no
                null,                                     // p_received_by
                $transactionDate,                         // p_payment_date
                $narration . ' - ' . $student['admission_number']  // p_notes
            ]);

            // Get the payment ID
            $stmt = $this->db->prepare("
                SELECT id FROM payment_transactions 
                WHERE reference_no = ? 
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$transactionRef]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($payment) {
                // Record bank transaction details
                $stmt = $this->db->prepare("
                    INSERT INTO bank_transactions (
                        student_id, transaction_ref, amount, transaction_date,
                        bank_name, account_number, narration, status,
                        webhook_data
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'processed', ?)
                ");

                $stmt->execute([
                    $student['id'],
                    $transactionRef,
                    $amount,
                    $transactionDate,
                    $bankName,
                    $senderAccount,
                    $narration,
                    json_encode($paymentData)
                ]);
            }

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
            // Log webhook
            $this->logWebhook($bankName, $paymentData);

            // Extract common fields (banks may have different formats)
            $accountNumber = $this->extractAccountNumber($paymentData);
            $amount = $this->extractAmount($paymentData);
            $transactionRef = $this->extractTransactionRef($paymentData);
            $transactionDate = $this->extractTransactionDate($paymentData);

            if (!$accountNumber || !$amount || !$transactionRef) {
                return formatResponse(false, null, 'Invalid payment data format');
            }

            // Get student by admission number
            $student = $this->getStudentByAdmission($accountNumber);
            if (!$student) {
                $this->logWebhookError($bankName, 'Invalid admission number: ' . $accountNumber, $paymentData);
                return formatResponse(false, null, 'Student not found for admission: ' . $accountNumber);
            }

            // Process payment using stored procedure
            // NOTE: Procedure handles its own transaction, do not use explicit transaction management
            // sp_process_student_payment(p_student_id, p_parent_id, p_amount_paid, p_payment_method, 
            //                            p_reference_no, p_receipt_no, p_received_by, p_payment_date, p_notes)
            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $student['id'],                           // p_student_id
                $student['parent_id'] ?? 0,               // p_parent_id
                $amount,                                  // p_amount_paid
                'bank_transfer',                          // p_payment_method
                $transactionRef,                          // p_reference_no
                null,                                     // p_receipt_no
                null,                                     // p_received_by
                $transactionDate,                         // p_payment_date
                $bankName . ' Payment - ' . $student['admission_number']  // p_notes
            ]);

            return formatResponse(true, [
                'message' => 'Payment processed successfully',
                'student' => $student['first_name'] . ' ' . $student['last_name'],
                'amount' => $amount
            ]);

        } catch (Exception $e) {
            error_log("Bank Payment Error: " . $e->getMessage());
            return formatResponse(false, null, 'Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Get student by admission number (handles both admission_number and admission_no columns)
     * @param string $accountNumber Admission number
     * @return array|null Student data or null if not found
     */
    private function getStudentByAdmission($accountNumber)
    {
        try {
            $admissionCol = $this->resolveAdmissionColumn();
            $sql = "SELECT s.id, s.first_name, s.last_name, s." . $admissionCol . " AS admission_number, 
                           COALESCE(sp.parent_id, 0) AS parent_id
                    FROM students s
                    LEFT JOIN student_parents sp ON s.id = sp.student_id
                    WHERE s." . $admissionCol . " = ? 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$accountNumber]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error fetching student: " . $e->getMessage());
            return null;
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
            // Map source names to valid enum values
            $sourceMap = [
                'KCB' => 'kcb_bank',
                'Bank' => 'generic_bank',
                'kcb_bank' => 'kcb_bank',
                'generic_bank' => 'generic_bank'
            ];
            $mappedSource = $sourceMap[$source] ?? 'generic_bank';

            $stmt = $this->db->prepare("
                INSERT INTO payment_webhooks_log (
                    source, webhook_data, status, created_at
                ) VALUES (?, ?, 'received', NOW())
            ");

            $stmt->execute([
                $mappedSource,
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
     * Resolve admission column in students table (admission_number vs admission_no)
     */
    private function resolveAdmissionColumn()
    {
        if ($this->admissionColumn) {
            return $this->admissionColumn;
        }

        try {
            // Prefer admission_number; fallback to admission_no
            $checkSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'students' AND COLUMN_NAME IN ('admission_number','admission_no') ORDER BY FIELD(COLUMN_NAME,'admission_number','admission_no') LIMIT 1";
            $stmt = $this->db->prepare($checkSql);
            $stmt->execute([DB_NAME]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->admissionColumn = $row && isset($row['COLUMN_NAME']) ? $row['COLUMN_NAME'] : 'admission_number';
        } catch (Exception $e) {
            // Default to admission_number
            $this->admissionColumn = 'admission_number';
        }
        return $this->admissionColumn;
    }

    /**
     * Log webhook error
     */
    private function logWebhookError($source, $error, $data)
    {
        try {
            // Map source names to valid enum values
            $sourceMap = [
                'KCB' => 'kcb_bank',
                'Bank' => 'generic_bank',
                'kcb_bank' => 'kcb_bank',
                'generic_bank' => 'generic_bank'
            ];
            $mappedSource = $sourceMap[$source] ?? 'generic_bank';

            // Truncate error message to fit in database column
            $truncatedError = substr($error, 0, 500);

            $stmt = $this->db->prepare("
                INSERT INTO payment_webhooks_log (
                    source, webhook_data, status, error_message, created_at
                ) VALUES (?, ?, 'failed', ?, NOW())
            ");

            $stmt->execute([
                $mappedSource,
                json_encode($data),
                $truncatedError
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
            // Get student contact info - note: students may not have direct phone/email
            // Contact info is typically stored in parent or student_contacts tables
            $stmt = $this->db->prepare("
                SELECT COALESCE(p.phone_1, '') as parent_phone, 
                       COALESCE(p.email, '') as parent_email
                FROM students s
                LEFT JOIN student_parents sp ON s.id = sp.student_id
                LEFT JOIN parents p ON sp.parent_id = p.id
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->execute([$student['id']]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$contact) {
                $contact = ['parent_phone' => '', 'parent_email' => ''];
            }

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
