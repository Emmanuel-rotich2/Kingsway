<?php
namespace App\API\Modules\payments;
use App\API\Modules\communications\CommunicationsAPI;
/**
 * PaymentsAPI - Handles all payment webhook logic for bank, mpesa, etc.
 * All methods return associative arrays for controller use.
 */
namespace App\API\Modules\payments;

use App\API\Services\payments\BankPaymentWebhook;
use Exception;
use App\API\Includes\BaseAPI;
use \App\API\Modules\communications\CommunicationsAPI;

class PaymentsAPI extends BaseAPI
{


    private $commAPI;

    public function __construct()
    {
        parent::__construct('payments');
        $this->commAPI = new CommunicationsAPI();
    }

    /**
     * Process M-Pesa B2C Result Callback
     * @param array $callbackData
     * @param array $headers
     * @return array
     */
    public function processMpesaB2CCallback(array $callbackData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_b2c_callbacks.log';
        $logEntry = "[{$this->timestamp}] RAW B2C CALLBACK:\n" . json_encode($callbackData) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        try {
            if (!$callbackData || !isset($callbackData['Result'])) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid B2C result data'
                ];
            }
            $result = $callbackData['Result'];
            $resultCode = $result['ResultCode'] ?? 1;
            $resultDesc = $result['ResultDesc'] ?? 'Unknown error';
            $originatorConversationID = $result['OriginatorConversationID'] ?? null;
            $conversationID = $result['ConversationID'] ?? null;
            $transactionID = $result['TransactionID'] ?? null;
            $parameters = [];
            if (isset($result['ResultParameters']['ResultParameter'])) {
                foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                    $parameters[$param['Key']] = $param['Value'];
                }
            }
            $stmt = $this->db->prepare("SELECT id, disbursement_type, recipient_id, amount, phone_number, recipient_name FROM disbursement_transactions WHERE conversation_id = ? OR originator_conversation_id = ? LIMIT 1");
            $stmt->execute([$conversationID, $originatorConversationID]);
            $disbursement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$disbursement) {
                $logEntry = "[{$this->timestamp}] UNKNOWN B2C CALLBACK: ConversationID=$conversationID, OriginatorID=$originatorConversationID\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Received but transaction not found'
                ];
            }
            $this->db->beginTransaction();
            if ($resultCode == 0) {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'completed', transaction_ref = ?, transaction_id = ?, completed_at = NOW(), result_description = ?, callback_data = ? WHERE id = ?");
                $stmt->execute([
                    $transactionID,
                    $transactionID,
                    $resultDesc,
                    json_encode($callbackData),
                    $disbursement['id']
                ]);
                if ($disbursement['disbursement_type'] === 'salary') {
                    $stmt = $this->db->prepare("UPDATE staff_payments SET disbursement_status = 'completed', mpesa_reference = ?, disbursement_date = NOW() WHERE disbursement_id = ?");
                    $stmt->execute([$transactionID, $disbursement['id']]);
                } elseif ($disbursement['disbursement_type'] === 'supplier') {
                    $stmt = $this->db->prepare("UPDATE supplier_payments SET payment_status = 'completed', mpesa_reference = ?, payment_date = NOW() WHERE disbursement_id = ?");
                    $stmt->execute([$transactionID, $disbursement['id']]);
                }
                $logEntry = "[{$this->timestamp}] B2C SUCCESS: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Ref: $transactionID\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
            } else {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'failed', transaction_ref = ?, result_description = ?, callback_data = ?, failed_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $transactionID,
                    $resultDesc,
                    json_encode($callbackData),
                    $disbursement['id']
                ]);
                if ($disbursement['disbursement_type'] === 'salary') {
                    $stmt = $this->db->prepare("UPDATE staff_payments SET disbursement_status = 'failed', disbursement_notes = ? WHERE disbursement_id = ?");
                    $stmt->execute([$resultDesc, $disbursement['id']]);
                } elseif ($disbursement['disbursement_type'] === 'supplier') {
                    $stmt = $this->db->prepare("UPDATE supplier_payments SET payment_status = 'failed', payment_notes = ? WHERE disbursement_id = ?");
                    $stmt->execute([$resultDesc, $disbursement['id']]);
                }
                $logEntry = "[{$this->timestamp}] B2C FAILED: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Error: $resultDesc\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
            }
            $this->db->commit();
            return [
                'ResultCode' => 0,
                'ResultDesc' => 'B2C callback processed successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            file_put_contents($logFile, $errorEntry, FILE_APPEND);
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Internal server error'
            ];
        }
    }

    /**
     * Process M-Pesa B2C Timeout Callback
     * @param array $timeoutData
     * @param array $headers
     * @return array
     */
    public function processMpesaB2CTimeout(array $timeoutData, array $headers)
    {
        $logFile = $this->logDir . '/mpesa_b2c_timeouts.log';
        $logEntry = "[{$this->timestamp}] RAW B2C TIMEOUT:\n" . json_encode($timeoutData) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        try {
            if (!$timeoutData || !isset($timeoutData['Result'])) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Invalid timeout data'
                ];
            }
            $result = $timeoutData['Result'];
            // $resultCode = $result['ResultCode'] ?? 1; // Unused variable removed
            $resultDesc = $result['ResultDesc'] ?? 'Request timed out';
            $conversationID = $result['ConversationID'] ?? null;
            $originatorConversationID = $result['OriginatorConversationID'] ?? null;
            $stmt = $this->db->prepare("SELECT id, disbursement_type, recipient_id, amount, phone_number, recipient_name, retry_count FROM disbursement_transactions WHERE conversation_id = ? OR originator_conversation_id = ? LIMIT 1");
            $stmt->execute([$conversationID, $originatorConversationID]);
            $disbursement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$disbursement) {
                $logEntry = "[{$this->timestamp}] UNKNOWN B2C TIMEOUT: ConversationID=$conversationID, OriginatorID=$originatorConversationID\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Received but transaction not found'
                ];
            }
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'timeout', result_description = ?, callback_data = ?, failed_at = NOW(), retry_count = retry_count + 1 WHERE id = ?");
            $stmt->execute([
                $resultDesc,
                json_encode($timeoutData),
                $disbursement['id']
            ]);
            if ($disbursement['disbursement_type'] === 'salary') {
                $stmt = $this->db->prepare("UPDATE staff_payments SET disbursement_status = 'timeout', disbursement_notes = ? WHERE disbursement_id = ?");
                $stmt->execute([$resultDesc, $disbursement['id']]);
            } elseif ($disbursement['disbursement_type'] === 'supplier') {
                $stmt = $this->db->prepare("UPDATE supplier_payments SET payment_status = 'timeout', payment_notes = ? WHERE disbursement_id = ?");
                $stmt->execute([$resultDesc, $disbursement['id']]);
            }
            $this->db->commit();
            $logEntry = "[{$this->timestamp}] B2C TIMEOUT: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Marked as timeout\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
            return [
                'ResultCode' => 0,
                'ResultDesc' => 'B2C timeout processed successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            file_put_contents($logFile, $errorEntry, FILE_APPEND);
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Internal server error'
            ];
        }
    }

    /**
     * Process M-Pesa C2B Confirmation
     * @param array $confirmationData
     * @param array $headers
     * @return array
     */
    public function processMpesaC2BConfirmation(array $confirmationData, array $headers)
    {
        $logFileRaw = $this->logDir . '/mpesa_c2b_confirmation_raw.log';
        $logFile = $this->logDir . '/mpesa_c2b_confirmation.log';
        file_put_contents($logFileRaw, $this->timestamp . " - RAW REQUEST:\n" . json_encode($confirmationData) . "\n\n", FILE_APPEND);
        try {
            // Log parsed data
            file_put_contents($logFile, $this->timestamp . " - PARSED DATA:\n" . print_r($confirmationData, true) . "\n\n", FILE_APPEND);
            // Extract payment details
            $mpesaCode = $confirmationData['TransID'] ?? '';
            $admissionNumber = $confirmationData['BillRefNumber'] ?? '';
            $amount = floatval($confirmationData['TransAmount'] ?? 0);
            $phoneNumber = $confirmationData['MSISDN'] ?? '';
            $transTime = $confirmationData['TransTime'] ?? '';
            // $businessShortCode = $confirmationData['BusinessShortCode'] ?? ''; // Unused variable removed
            $firstName = $confirmationData['FirstName'] ?? '';
            $middleName = $confirmationData['MiddleName'] ?? '';
            $lastName = $confirmationData['LastName'] ?? '';
            $orgBalance = $confirmationData['OrgAccountBalance'] ?? '';
            // Validate required fields
            if (empty($mpesaCode) || empty($admissionNumber) || $amount <= 0) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Missing required fields'
                ];
            }
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, status FROM students WHERE admission_no = :admission_no LIMIT 1");
            $stmt->execute(['admission_no' => $admissionNumber]);
            $student = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Student not found'
                ];
            }
            if (!in_array($student['status'], ['active', 'enrolled'])) {
                return [
                    'ResultCode' => 1,
                    'ResultDesc' => 'Student account not active'
                ];
            }
            $this->db->beginTransaction();
            // Check for duplicate transaction
            $dupStmt = $this->db->prepare("SELECT id FROM payment_transactions WHERE reference_no = :reference_no AND payment_method = 'mpesa_c2b' LIMIT 1");
            $dupStmt->execute(['reference_no' => $mpesaCode]);
            $existing = $dupStmt->fetch(\PDO::FETCH_ASSOC);
            if ($existing) {
                $this->db->rollBack();
                file_put_contents($logFile, $this->timestamp . " - DUPLICATE: Transaction {$mpesaCode} already processed (ID: {$existing['id']})\n", FILE_APPEND);
                return [
                    'ResultCode' => 0,
                    'ResultDesc' => 'Confirmation received successfully (already processed)'
                ];
            }
            // Insert payment record
            $transDateTime = \DateTime::createFromFormat('YmdHis', $transTime);
            if (!$transDateTime) {
                $transDateTime = new \DateTime();
            }
            $transDateFormatted = $transDateTime->format('Y-m-d H:i:s');
            $insertQuery = "INSERT INTO payment_transactions (student_id, amount_paid, payment_date, payment_method, reference_no, receipt_no, status, notes, created_at) VALUES (:student_id, :amount_paid, :payment_date, 'mpesa_c2b', :reference_no, :receipt_no, 'confirmed', :notes, NOW())";
            $insertStmt = $this->db->prepare($insertQuery);
            $insertStmt->execute([
                'student_id' => $student['id'],
                'amount_paid' => $amount,
                'payment_date' => $transDateFormatted,
                'reference_no' => $mpesaCode,
                'receipt_no' => 'MPESA-' . $mpesaCode,
                'notes' => "M-Pesa payment from {$firstName} {$middleName} {$lastName} (Phone: {$phoneNumber}). OrgBalance: {$orgBalance}"
            ]);
            // Update student fee balance
            $balStmt = $this->db->prepare("UPDATE student_fee_balances SET balance = balance - :amount WHERE student_id = :student_id");
            $balStmt->execute([
                'amount' => $amount,
                'student_id' => $student['id']
            ]);
            // Log webhook
            $webhookLogQuery = "INSERT INTO payment_webhooks_log (source, webhook_data, status, created_at) VALUES ('mpesa_c2b', :webhook_data, 'processed', NOW())";
            $webhookStmt = $this->db->prepare($webhookLogQuery);
            $webhookStmt->execute([
                'webhook_data' => json_encode([
                    'mpesa_code' => $mpesaCode,
                    'admission_no' => $admissionNumber,
                    'student_id' => $student['id'],
                    'amount' => $amount,
                    'phone' => $phoneNumber,
                    'trans_time' => $transDateFormatted,
                    'org_balance' => $orgBalance
                ])
            ]);
            $this->db->commit();
            file_put_contents($logFile, $this->timestamp . " - CONFIRMATION SUCCESS: {$mpesaCode}, Student: {$admissionNumber}, Amount: {$amount}\n", FILE_APPEND);
            return [
                'ResultCode' => 0,
                'ResultDesc' => 'Confirmation received successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            file_put_contents($logFile, $this->timestamp . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
            return [
                'ResultCode' => 1,
                'ResultDesc' => 'Internal server error'
            ];
        }

    }
    /**
     * Process KCB Validation
     * @param array $validationData
     * @param array $headers
     * @return array
     */
    public function processKcbValidation(array $validationData, array $headers)
    {
        $logFileRaw = $this->logDir . '/kcb_validation_raw.log';
        $logFile = $this->logDir . '/kcb_validation.log';
        $logFileErr = $this->logDir . '/kcb_validation_errors.log';
        $signature = $headers['Signature'] ?? $headers['signature'] ?? '';
        file_put_contents(
            $logFileRaw,
            $this->timestamp . " - RAW REQUEST:\n" .
            "Signature: {$signature}\n" .
            "Body: " . json_encode($validationData) . "\n\n",
            FILE_APPEND
        );
        try {
            if (!$validationData || !is_array($validationData)) {
                throw new \Exception("Invalid or missing JSON data");
            }
            file_put_contents(
                $logFile,
                $this->timestamp . " - PARSED DATA:\n" . print_r($validationData, true) . "\n\n",
                FILE_APPEND
            );
            $requestId = $validationData['requestId'] ?? '';
            $customerReference = $validationData['customerReference'] ?? '';
            $organizationReference = $validationData['organizationReference'] ?? '';
            if (empty($customerReference)) {
                file_put_contents(
                    $logFile,
                    $this->timestamp . " - REJECTED: Empty customer reference\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '1',
                    'statusMessage' => 'Customer reference (admission number) is required',
                    'CustomerName' => '',
                    'billAmount' => '0.00',
                    'currency' => 'KES',
                    'billType' => 'PARTIAL',
                    'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
                ];
            }
            $stmt = $this->db->prepare("SELECT s.id, s.admission_no, CONCAT(s.first_name, ' ', s.last_name) as full_name, s.status, COALESCE(sfb.balance, 0) as current_balance FROM students s LEFT JOIN student_fee_balances sfb ON s.id = sfb.student_id WHERE s.admission_no = :admission_no LIMIT 1");
            $stmt->execute(['admission_no' => $customerReference]);
            $student = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$student) {
                file_put_contents(
                    $logFile,
                    $this->timestamp . " - REJECTED: Admission number '{$customerReference}' not found\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '1',
                    'statusMessage' => "Admission number {$customerReference} not found. Please verify and try again.",
                    'CustomerName' => '',
                    'billAmount' => '0.00',
                    'currency' => 'KES',
                    'billType' => 'PARTIAL',
                    'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
                ];
            }
            if (!in_array($student['status'], ['active', 'enrolled'])) {
                file_put_contents(
                    $logFile,
                    $this->timestamp . " - REJECTED: Student '{$customerReference}' status is '{$student['status']}'\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '1',
                    'statusMessage' => "Student account {$customerReference} is {$student['status']}. Please contact school administration.",
                    'CustomerName' => $student['full_name'],
                    'billAmount' => '0.00',
                    'currency' => 'KES',
                    'billType' => 'PARTIAL',
                    'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
                ];
            }
            $response = [
                'transactionID' => $requestId,
                'statusCode' => '0',
                'statusMessage' => 'Success',
                'CustomerName' => $student['full_name'],
                'billAmount' => number_format($student['current_balance'], 2, '.', ''),
                'currency' => 'KES',
                'billType' => 'PARTIAL',
                'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
            ];
            $auditQuery = "INSERT INTO payment_webhooks_log (source, webhook_data, status, created_at) VALUES ('kcb_validation', :webhook_data, 'validated', NOW())";
            $auditStmt = $this->db->prepare($auditQuery);
            $auditStmt->execute([
                'webhook_data' => json_encode([
                    'request_id' => $requestId,
                    'customer_reference' => $customerReference,
                    'organization_reference' => $organizationReference,
                    'student_id' => $student['id'],
                    'student_name' => $student['full_name'],
                    'current_balance' => $student['current_balance'],
                    'validation_result' => 'accepted',
                    'signature' => substr($signature, 0, 50) . '...'
                ])
            ]);
            file_put_contents(
                $logFile,
                $this->timestamp . " - ACCEPTED: Student '{$customerReference}' - {$student['full_name']}, Balance: {$student['current_balance']}, RequestID: {$requestId}\n\n",
                FILE_APPEND
            );
            return $response;
        } catch (\Exception $e) {
            file_put_contents(
                $logFileErr,
                $this->timestamp . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
            return [
                'transactionID' => $validationData['requestId'] ?? 'UNKNOWN',
                'statusCode' => '1',
                'statusMessage' => 'System error. Please try again later.',
                'CustomerName' => '',
                'billAmount' => '0.00',
                'currency' => 'KES',
                'billType' => 'PARTIAL',
                'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
            ];
        }

    }
    /**
     * Process KCB Bank Transfer Callback
     * @param array $callbackData
     * @param array $headers
     * @return array
     */
    public function processKcbTransferCallback(array $callbackData, array $headers)
    {
        $logFile = $this->logDir . '/kcb_transfer_callbacks.log';
        $logEntry = "[{$this->timestamp}] RAW KCB TRANSFER CALLBACK:\nHeaders: " . json_encode($headers) . "\nBody: " . json_encode($callbackData) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        try {
            if (!$callbackData || !is_array($callbackData)) {
                return [
                    'statusCode' => '1',
                    'statusMessage' => 'Invalid JSON data'
                ];
            }
            $transactionRef = $callbackData['transactionReference'] ?? null;
            $requestId = $callbackData['requestId'] ?? null;
            $amount = $callbackData['transactionAmount'] ?? 0;
            $status = $callbackData['status'] ?? 'UNKNOWN';
            $statusDesc = $callbackData['statusDescription'] ?? '';
            // $creditAccount = $callbackData['creditAccountNumber'] ?? null; // Unused variable removed
            // $creditAccountName = $callbackData['creditAccountName'] ?? null; // Unused variable removed
            // $debitAccount = $callbackData['debitAccountNumber'] ?? null; // Unused variable removed
            $charges = $callbackData['charges'] ?? 0;
            // $narration = $callbackData['narration'] ?? ''; // Unused variable removed
            // $transactionTimestamp = $callbackData['timestamp'] ?? null; // Unused variable removed
            if (!$requestId || !$amount) {
                return [
                    'statusCode' => '1',
                    'statusMessage' => 'Missing required fields'
                ];
            }
            $stmt = $this->db->prepare("SELECT id, disbursement_type, recipient_id, amount, account_number, recipient_name, status FROM disbursement_transactions WHERE request_id = ? OR transaction_ref = ? LIMIT 1");
            $stmt->execute([$requestId, $transactionRef]);
            $disbursement = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$disbursement) {
                $logEntry = "[{$this->timestamp}] UNKNOWN KCB TRANSFER: RequestID=$requestId, TransactionRef=$transactionRef\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                return [
                    'statusCode' => '0',
                    'statusMessage' => 'Received but transaction not found'
                ];
            }
            $this->db->beginTransaction();
            if (strtoupper($status) === 'SUCCESS') {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'completed', transaction_ref = ?, transaction_id = ?, completed_at = NOW(), result_description = ?, callback_data = ?, bank_charges = ? WHERE id = ?");
                $stmt->execute([
                    $transactionRef,
                    $transactionRef,
                    $statusDesc,
                    json_encode($callbackData),
                    $charges,
                    $disbursement['id']
                ]);
                if ($disbursement['disbursement_type'] === 'salary') {
                    $stmt = $this->db->prepare("UPDATE staff_payments SET disbursement_status = 'completed', bank_reference = ?, disbursement_date = NOW(), bank_charges = ? WHERE disbursement_id = ?");
                    $stmt->execute([$transactionRef, $charges, $disbursement['id']]);
                } elseif ($disbursement['disbursement_type'] === 'supplier') {
                    $stmt = $this->db->prepare("UPDATE supplier_payments SET payment_status = 'completed', bank_reference = ?, payment_date = NOW(), bank_charges = ? WHERE disbursement_id = ?");
                    $stmt->execute([$transactionRef, $charges, $disbursement['id']]);
                }
                $logEntry = "[{$this->timestamp}] KCB TRANSFER SUCCESS: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Ref: $transactionRef\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                $this->sendTransferNotification($disbursement, $transactionRef, 'completed', $charges);
            } else {
                $stmt = $this->db->prepare("UPDATE disbursement_transactions SET status = 'failed', transaction_ref = ?, result_description = ?, callback_data = ?, failed_at = NOW() WHERE id = ?");
                $stmt->execute([
                    $transactionRef,
                    $statusDesc,
                    json_encode($callbackData),
                    $disbursement['id']
                ]);
                if ($disbursement['disbursement_type'] === 'salary') {
                    $stmt = $this->db->prepare("UPDATE staff_payments SET disbursement_status = 'failed', disbursement_notes = ? WHERE disbursement_id = ?");
                    $stmt->execute([$statusDesc, $disbursement['id']]);
                } elseif ($disbursement['disbursement_type'] === 'supplier') {
                    $stmt = $this->db->prepare("UPDATE supplier_payments SET payment_status = 'failed', payment_notes = ? WHERE disbursement_id = ?");
                    $stmt->execute([$statusDesc, $disbursement['id']]);
                }
                $logEntry = "[{$this->timestamp}] KCB TRANSFER FAILED: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Error: $statusDesc\n";
                file_put_contents($logFile, $logEntry, FILE_APPEND);
                $this->sendTransferNotification($disbursement, $transactionRef, 'failed', 0, $statusDesc);
            }
            $this->db->commit();
            return [
                'transactionID' => $disbursement['id'],
                'statusCode' => '0',
                'statusMessage' => 'Transfer notification processed successfully'
            ];
        } catch (\Exception $e) {
            if ($this->db && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
            file_put_contents($logFile, $errorEntry, FILE_APPEND);
            return [
                'statusCode' => '1',
                'statusMessage' => 'Internal server error'
            ];
        }
    }

    /**
     * Send transfer notification to recipient (private helper)
     */
    private function sendTransferNotification($disbursement, $transactionRef, $status, $charges = 0, $error = null)
    {
        try {

            $phoneNumber = null;
            $email = null;
            $recipientName = null;
            if ($disbursement['disbursement_type'] === 'salary') {
                $stmt = $this->db->prepare("SELECT phone_number, email, first_name, last_name FROM staff WHERE id = ?");
                $stmt->execute([$disbursement['recipient_id']]);
                $contact = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($contact) {
                    $phoneNumber = $contact['phone_number'];
                    $email = $contact['email'];
                    $recipientName = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
                }
            }
            if (!$phoneNumber && !$email) {
                return;
            }
            // Prepare variables for SMS/email
            $variables = [
                'recipient_name' => $recipientName,
                'amount' => number_format($disbursement['amount'], 2),
                'net_amount' => number_format($disbursement['amount'] - $charges, 2),
                'charges' => number_format($charges, 2),
                'reference' => $transactionRef,
                'account' => $disbursement['account_number'],
                'status' => $status,
                'error' => $error,
            ];
            $category = ($status === 'completed') ? 'salary_payment_success' : 'salary_payment_failed';
            // Send SMS
            if ($phoneNumber) {
                $this->commAPI->sendTemplateSMS([$phoneNumber], $variables, $category, 'sms');
            }
            // Send Email
            if ($email) {
                $subject = ($status === 'completed') ? 'Salary Payment Completed' : 'Salary Payment Failed';
                $body = [
                    'recipient_name' => $recipientName,
                    'amount' => number_format($disbursement['amount'], 2),
                    'net_amount' => number_format($disbursement['amount'] - $charges, 2),
                    'charges' => number_format($charges, 2),
                    'reference' => $transactionRef,
                    'account' => $disbursement['account_number'],
                    'status' => $status,
                    'error' => $error,
                ];
                $this->commAPI->sendEmail([$email], $subject, $body);
            }
        } catch (\Exception $e) {
            error_log("Failed to send transfer notification: " . $e->getMessage());
        }
    }
    /**
     * Process KCB Bank Payment Notification
     * @param array $notificationData
     * @param array $headers
     * @return array
     */
    public function processKcbNotification(array $notificationData, array $headers)
    {
        $signature = $headers['Signature'] ?? $headers['signature'] ?? '';
        file_put_contents(
            $this->logDir . '/kcb_notification_raw.log',
            $this->timestamp . " - RAW REQUEST:\n" .
            "Signature: {$signature}\n" .
            "Body: " . json_encode($notificationData) . "\n\n",
            FILE_APPEND
        );
        try {
            if (!$notificationData || !is_array($notificationData)) {
                throw new \Exception("Invalid or missing JSON data");
            }
            file_put_contents(
                $this->logDir . '/kcb_notification.log',
                $this->timestamp . " - PARSED DATA:\n" . print_r($notificationData, true) . "\n\n",
                FILE_APPEND
            );
            $transactionReference = $notificationData['transactionReference'] ?? '';
            $requestId = $notificationData['requestId'] ?? '';
            $customerReference = $notificationData['customerReference'] ?? '';
            $transactionAmount = floatval($notificationData['transactionAmount'] ?? 0);
            $customerName = $notificationData['customerName'] ?? '';
            $customerMobile = $notificationData['customerMobileNumber'] ?? '';
            $narration = $notificationData['narration'] ?? '';
            $timestamp = $notificationData['timestamp'] ?? '';
            // $currency = $notificationData['currency'] ?? 'KES'; // Unused variable removed
            $channelCode = $notificationData['channelCode'] ?? '';
            $orgShortCode = $notificationData['organizationShortCode'] ?? '';
            $balance = $notificationData['balance'] ?? '';
            if (empty($transactionReference) || empty($customerReference) || $transactionAmount <= 0) {
                throw new \Exception("Missing required fields: TransRef={$transactionReference}, CustRef={$customerReference}, Amount={$transactionAmount}");
            }
            $this->db->beginTransaction();
            try {
                $studentQuery = "SELECT id, first_name, last_name, status FROM students WHERE admission_no = :admission_no LIMIT 1";
                $stmt = $this->db->prepare($studentQuery);
                $stmt->execute(['admission_no' => $customerReference]);
                $student = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$student) {
                    throw new \Exception("Student not found: Admission number {$customerReference}");
                }
                $studentId = $student['id'];
                $duplicateCheck = "SELECT id, status FROM bank_transactions WHERE transaction_ref = :transaction_ref LIMIT 1";
                $dupStmt = $this->db->prepare($duplicateCheck);
                $dupStmt->execute(['transaction_ref' => $transactionReference]);
                $existing = $dupStmt->fetch(\PDO::FETCH_ASSOC);
                if ($existing) {
                    $this->db->rollback();
                    file_put_contents(
                        $this->logDir . '/kcb_notification.log',
                        $this->timestamp . " - DUPLICATE: Transaction {$transactionReference} already processed (ID: {$existing['id']})\n\n",
                        FILE_APPEND
                    );
                    return [
                        'transactionID' => $requestId,
                        'statusCode' => '0',
                        'statusMessage' => 'Notification received successfully (already processed)'
                    ];
                }
                $transDateTime = \DateTime::createFromFormat('YmdHis', $timestamp);
                if (!$transDateTime) {
                    $transDateTime = new \DateTime();
                }
                $transDateFormatted = $transDateTime->format('Y-m-d H:i:s');
                $insertQuery = "INSERT INTO bank_transactions (transaction_ref, student_id, amount, transaction_date, bank_name, account_number, narration, status, webhook_data, created_at) VALUES (:transaction_ref, :student_id, :amount, :transaction_date, 'KCB Bank', :account_number, :narration, 'processed', :webhook_data, NOW())";
                $insertStmt = $this->db->prepare($insertQuery);
                $insertStmt->execute([
                    'transaction_ref' => $transactionReference,
                    'student_id' => $studentId,
                    'amount' => $transactionAmount,
                    'transaction_date' => $transDateFormatted,
                    'account_number' => $customerMobile,
                    'narration' => $narration,
                    'webhook_data' => json_encode($notificationData)
                ]);
                $bankTransactionId = $this->db->lastInsertId();
                $paymentQuery = "INSERT INTO payment_transactions (student_id, amount_paid, payment_date, payment_method, reference_no, receipt_no, status, notes, created_at) VALUES (:student_id, :amount_paid, :payment_date, 'bank_transfer', :reference_no, :receipt_no, 'confirmed', :notes, NOW())";
                $paymentStmt = $this->db->prepare($paymentQuery);
                $paymentStmt->execute([
                    'student_id' => $studentId,
                    'amount_paid' => $transactionAmount,
                    'payment_date' => $transDateFormatted,
                    'reference_no' => $transactionReference,
                    'receipt_no' => 'KCB-' . $transactionReference,
                    'notes' => "KCB Bank payment from {$customerName} (Mobile: {$customerMobile}). {$narration}"
                ]);
                $webhookLogQuery = "INSERT INTO payment_webhooks_log (source, webhook_data, status, created_at) VALUES ('kcb_bank', :webhook_data, 'processed', NOW())";
                $webhookStmt = $this->db->prepare($webhookLogQuery);
                $webhookStmt->execute([
                    'webhook_data' => json_encode([
                        'transaction_ref' => $transactionReference,
                        'request_id' => $requestId,
                        'customer_reference' => $customerReference,
                        'student_id' => $studentId,
                        'amount' => $transactionAmount,
                        'customer_mobile' => $customerMobile,
                        'customer_name' => $customerName,
                        'transaction_time' => $transDateFormatted,
                        'channel_code' => $channelCode,
                        'org_short_code' => $orgShortCode,
                        'balance' => $balance,
                        'bank_transaction_id' => $bankTransactionId,
                        'signature' => substr($signature, 0, 50) . '...'
                    ])
                ]);
                $this->db->commit();
                file_put_contents(
                    $this->logDir . '/kcb_notification.log',
                    $this->timestamp . " - SUCCESS: KCB {$transactionReference}, Student {$customerReference} ({$student['first_name']} {$student['last_name']}), Amount: KES {$transactionAmount}, Mobile: {$customerMobile}\n\n",
                    FILE_APPEND
                );
                return [
                    'transactionID' => $requestId,
                    'statusCode' => '0',
                    'statusMessage' => 'Notification received successfully'
                ];
            } catch (\Exception $e) {
                $this->db->rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            file_put_contents(
                $this->logDir . '/kcb_notification_errors.log',
                $this->timestamp . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
                FILE_APPEND
            );
            // Return success to KCB to avoid retries, but log for manual processing
            return [
                'transactionID' => $notificationData['requestId'] ?? 'UNKNOWN',
                'statusCode' => '0',
                'statusMessage' => 'Received. Processing offline.'
            ];
        }
    }
    /**
     * Process Bank Payment Webhook
     * @param array $webhookData
     * @param array $headers
     * @return array
     */
    public function processBankWebhook(array $webhookData, array $headers)
    {
        $logFile = $this->logDir . '/bank_webhooks_raw.log';
        $logEntry = "[{$this->timestamp}] RAW WEBHOOK:\nHeaders: " . json_encode($headers) . "\nBody: " . json_encode($webhookData) . "\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        try {
            if (!$webhookData) {
                return [
                    'status' => false,
                    'message' => 'Invalid JSON data'
                ];
            }
            $bankService = new BankPaymentWebhook();
            $bankName = $headers['X-Bank-Name'] ?? $webhookData['bank'] ?? $webhookData['bank_name'] ?? 'Generic Bank';

            // Use BankPaymentWebhook's flexible extractors to handle various field names
            $accountRef = $this->extractAccountNumber($webhookData);
            $narration = strtolower($webhookData['narration'] ?? '');
            $handled = false;
            $result = null;

            // If we got an account reference, try to process it as a bank payment
            if ($accountRef) {
                // Check account reference type and route accordingly
                if (preg_match('/^\d{5,}$/', $accountRef)) {
                    // Numeric account (5+ digits)
                    $result = (strtoupper($bankName) === 'KCB')
                        ? $bankService->processKCBPayment($webhookData)
                        : $bankService->processGenericBankPayment($webhookData, $bankName);
                    $handled = true;
                } elseif (preg_match('/^ADM\d+$/i', $accountRef)) {
                    // Admission number (ADM001, ADM102, etc.) - student school fees
                    $result = (strtoupper($bankName) === 'KCB')
                        ? $bankService->processKCBPayment($webhookData)
                        : $bankService->processGenericBankPayment($webhookData, $bankName);
                    $handled = true;
                } elseif (stripos($accountRef, 'TRP') === 0 || strpos($narration, 'transport') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Transport payment processing not yet implemented.'
                    ];
                    $handled = true;
                } elseif (stripos($accountRef, 'PAY') === 0 || strpos($narration, 'payroll') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Payroll payment processing not yet implemented.'
                    ];
                    $handled = true;
                } elseif (stripos($accountRef, 'DEPT') === 0 || strpos($narration, 'department') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Department payment processing not yet implemented.'
                    ];
                    $handled = true;
                } elseif (stripos($accountRef, 'CHQ') === 0 || strpos($narration, 'cheque') !== false) {
                    $result = [
                        'status' => false,
                        'message' => 'Cheque payment processing not yet implemented.'
                    ];
                    $handled = true;
                }
            }

            if (!$handled) {
                $result = [
                    'status' => false,
                    'message' => 'Unknown or unsupported payment type.'
                ];
            }
            if ($result['status']) {
                return [
                    'status' => true,
                    'message' => 'Payment processed successfully',
                    'data' => $result['data'] ?? null
                ];
            } else {
                return [
                    'status' => false,
                    'message' => $result['message']
                ];
            }
        } catch (\Exception $e) {
            $errorEntry = "[{$this->timestamp}] ERROR: " . $e->getMessage() . "\n\n";
            file_put_contents($logFile, $errorEntry, FILE_APPEND);
            return [
                'status' => false,
                'message' => 'Internal server error'
            ];
        }
    }

    /**
     * Extract account number from payment data (flexible field names)
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
}

