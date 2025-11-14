<?php

namespace App\API\Services\Payments;

use App\Config\Database;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * M-Pesa Payment Service
 * 
 * Handles M-Pesa Daraja API integration for:
 * - STK Push (Lipa na M-Pesa)
 * - Payment callbacks
 * - Transaction status queries
 * 
 * Uses admission number as account reference for student payments
 */
class MpesaPaymentService
{
    private $db;
    private $consumerKey;
    private $consumerSecret;
    private $businessShortCode;
    private $passkey;
    private $environment; // 'sandbox' or 'production'

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();

        // Load M-Pesa credentials from config
        $this->consumerKey = MPESA_CONSUMER_KEY ?? '';
        $this->consumerSecret = MPESA_CONSUMER_SECRET ?? '';
        $this->businessShortCode = MPESA_SHORTCODE ?? '174379';
        $this->passkey = MPESA_PASSKEY ?? '';
        $this->environment = MPESA_ENVIRONMENT ?? 'sandbox';
    }

    /**
     * Get M-Pesa access token
     * @return string|null Access token
     */
    private function getAccessToken()
    {
        try {
            $url = $this->environment === 'production'
                ? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
                : 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, false);
            curl_setopt($curl, CURLOPT_USERPWD, $this->consumerKey . ':' . $this->consumerSecret);

            $result = curl_exec($curl);
            $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($status === 200) {
                $result = json_decode($result);
                return $result->access_token ?? null;
            }

            return null;

        } catch (Exception $e) {
            error_log("M-Pesa Access Token Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Initiate STK Push for student fee payment
     * @param string $admissionNumber Student admission number
     * @param string $phoneNumber Student/parent phone number (format: 254XXXXXXXXX)
     * @param float $amount Amount to pay
     * @param string $description Payment description
     * @return array Response
     */
    public function initiateSTKPush($admissionNumber, $phoneNumber, $amount, $description = 'School Fees Payment')
    {
        try {
            // Validate admission number and get student
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, current_class_id, academic_year
                FROM students 
                WHERE admission_number = ?
            ");
            $stmt->execute([$admissionNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Invalid admission number');
            }

            // Validate phone number format
            if (!preg_match('/^254[0-9]{9}$/', $phoneNumber)) {
                return formatResponse(false, null, 'Invalid phone number format. Use 254XXXXXXXXX');
            }

            // Get access token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return formatResponse(false, null, 'Failed to get M-Pesa access token');
            }

            // Generate timestamp and password
            date_default_timezone_set('Africa/Nairobi');
            $timestamp = date('YmdHis');
            $password = base64_encode($this->businessShortCode . $this->passkey . $timestamp);

            // Callback URL
            // Use defined()/constant() to ensure we read global constants from the global namespace
            // and fall back to a sensible default if not set.
            if (defined('MPESA_CALLBACK_URL')) {
                $callbackUrl = constant('MPESA_CALLBACK_URL');
            } elseif (defined('BASE_URL')) {
                $callbackUrl = constant('BASE_URL') . '/api/payments/mpesa-callback.php';
            } else {
                $callbackUrl = '/api/payments/mpesa-callback.php';
            }

            // Prepare STK Push request
            $url = $this->environment === 'production'
                ? 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
                : 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ]);

            $requestData = [
                'BusinessShortCode' => $this->businessShortCode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => (int) $amount,
                'PartyA' => $phoneNumber,
                'PartyB' => $this->businessShortCode,
                'PhoneNumber' => $phoneNumber,
                'CallBackURL' => $callbackUrl,
                'AccountReference' => $admissionNumber, // Use admission number as account reference
                'TransactionDesc' => $description
            ];

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $responseData = json_decode($response, true);

            // Log STK Push request
            $this->logSTKPushRequest($student['id'], $admissionNumber, $phoneNumber, $amount, $requestData, $responseData);

            if ($httpCode === 200 && isset($responseData['ResponseCode']) && $responseData['ResponseCode'] === '0') {
                return formatResponse(true, [
                    'checkout_request_id' => $responseData['CheckoutRequestID'],
                    'merchant_request_id' => $responseData['MerchantRequestID'],
                    'message' => 'STK Push sent successfully. Please enter M-Pesa PIN on your phone.'
                ]);
            }

            return formatResponse(false, $responseData, $responseData['ResponseDescription'] ?? 'Failed to initiate M-Pesa payment');

        } catch (Exception $e) {
            error_log("M-Pesa STK Push Error: " . $e->getMessage());
            return formatResponse(false, null, 'M-Pesa payment initiation failed: ' . $e->getMessage());
        }
    }

    /**
     * Log STK Push request
     * @param int $student_id Student ID
     * @param string $admissionNumber Admission number
     * @param string $phoneNumber Phone number
     * @param float $amount Amount
     * @param array $request Request data
     * @param array $response Response data
     */
    private function logSTKPushRequest($student_id, $admissionNumber, $phoneNumber, $amount, $request, $response)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_stk_requests (
                    student_id, admission_number, phone_number, amount,
                    checkout_request_id, merchant_request_id,
                    request_data, response_data, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $student_id,
                $admissionNumber,
                $phoneNumber,
                $amount,
                $response['CheckoutRequestID'] ?? null,
                $response['MerchantRequestID'] ?? null,
                json_encode($request),
                json_encode($response),
                isset($response['ResponseCode']) && $response['ResponseCode'] === '0' ? 'pending' : 'failed'
            ]);

        } catch (Exception $e) {
            error_log("Failed to log STK request: " . $e->getMessage());
        }
    }

    /**
     * Process M-Pesa callback
     * @param array $callbackData Callback data from M-Pesa
     * @return array Response
     */
    public function processCallback($callbackData)
    {
        try {
            $this->db->beginTransaction();

            // Log raw callback
            $this->logCallback($callbackData);

            // Extract callback data
            $stkCallback = $callbackData['Body']['stkCallback'] ?? null;
            if (!$stkCallback) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid callback data');
            }

            $checkoutRequestId = $stkCallback['CheckoutRequestID'];
            $resultCode = $stkCallback['ResultCode'];
            $resultDesc = $stkCallback['ResultDesc'];

            // Update STK request status
            $stmt = $this->db->prepare("
                UPDATE mpesa_stk_requests 
                SET status = ?, result_desc = ?, callback_data = ?, updated_at = NOW()
                WHERE checkout_request_id = ?
            ");
            $stmt->execute([
                $resultCode === 0 ? 'completed' : 'failed',
                $resultDesc,
                json_encode($callbackData),
                $checkoutRequestId
            ]);

            // If payment successful, process it
            if ($resultCode === 0) {
                $callbackMetadata = $stkCallback['CallbackMetadata']['Item'] ?? [];

                $amount = null;
                $mpesaReceiptNumber = null;
                $phoneNumber = null;
                $transactionDate = null;

                foreach ($callbackMetadata as $item) {
                    if ($item['Name'] === 'Amount') {
                        $amount = $item['Value'];
                    }
                    if ($item['Name'] === 'MpesaReceiptNumber') {
                        $mpesaReceiptNumber = $item['Value'];
                    }
                    if ($item['Name'] === 'PhoneNumber') {
                        $phoneNumber = $item['Value'];
                    }
                    if ($item['Name'] === 'TransactionDate') {
                        $transactionDate = $item['Value'];
                    }
                }

                // Get student from STK request
                $stmt = $this->db->prepare("
                    SELECT student_id, admission_number 
                    FROM mpesa_stk_requests 
                    WHERE checkout_request_id = ?
                ");
                $stmt->execute([$checkoutRequestId]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($request) {
                    // Process payment using stored procedure
                    $stmt = $this->db->prepare("
                        CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $request['student_id'],
                        $amount,
                        'mpesa',
                        $mpesaReceiptNumber,
                        $this->formatTransactionDate($transactionDate),
                        null, // received_by (automatic)
                        'M-Pesa Payment - ' . $request['admission_number']
                    ]);

                    // Get the payment ID
                    $stmt = $this->db->prepare("
                        SELECT id FROM payment_transactions 
                        WHERE transaction_ref = ? 
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$mpesaReceiptNumber]);
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($payment) {
                        // Record M-Pesa transaction details
                        $stmt = $this->db->prepare("
                            INSERT INTO mpesa_transactions (
                                payment_id, transaction_id, phone_number, amount, 
                                transaction_date, status, checkout_request_id
                            ) VALUES (?, ?, ?, ?, ?, 'completed', ?)
                        ");

                        $stmt->execute([
                            $payment['id'],
                            $mpesaReceiptNumber,
                            $phoneNumber,
                            $amount,
                            $this->formatTransactionDate($transactionDate),
                            $checkoutRequestId
                        ]);
                    }
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'message' => 'Callback processed successfully',
                'result_code' => $resultCode,
                'result_desc' => $resultDesc
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("M-Pesa Callback Error: " . $e->getMessage());
            return formatResponse(false, null, 'Failed to process callback: ' . $e->getMessage());
        }
    }

    /**
     * Format M-Pesa transaction date
     */
    private function formatTransactionDate($transactionDate)
    {
        // Format: YYYYMMDDHHmmss to Y-m-d H:i:s
        if (strlen($transactionDate) === 14) {
            return substr($transactionDate, 0, 4) . '-' .
                substr($transactionDate, 4, 2) . '-' .
                substr($transactionDate, 6, 2) . ' ' .
                substr($transactionDate, 8, 2) . ':' .
                substr($transactionDate, 10, 2) . ':' .
                substr($transactionDate, 12, 2);
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * Log callback data
     */
    private function logCallback($callbackData)
    {
        try {
            $logFile = __DIR__ . '/../../../../logs/mpesa_callbacks.log';
            $logDir = dirname($logFile);

            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $timestamp = date('Y-m-d H:i:s');
            $logEntry = "[$timestamp] " . json_encode($callbackData, JSON_PRETTY_PRINT) . "\n\n";

            file_put_contents($logFile, $logEntry, FILE_APPEND);

        } catch (Exception $e) {
            error_log("Failed to log M-Pesa callback: " . $e->getMessage());
        }
    }

    /**
     * Query STK Push transaction status
     * @param string $checkoutRequestId Checkout Request ID
     * @return array Response
     */
    public function queryTransactionStatus($checkoutRequestId)
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return formatResponse(false, null, 'Failed to get access token');
            }

            $timestamp = date('YmdHis');
            $password = base64_encode($this->businessShortCode . $this->passkey . $timestamp);

            $url = $this->environment === 'production'
                ? 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'
                : 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ]);

            $requestData = [
                'BusinessShortCode' => $this->businessShortCode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId
            ];

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));

            $response = curl_exec($curl);
            curl_close($curl);

            $responseData = json_decode($response, true);

            return formatResponse(true, $responseData);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to query status: ' . $e->getMessage());
        }
    }

    /**
     * Register C2B URLs with M-Pesa
     * This must be called before customers can pay via paybill
     * 
     * @param string $validationURL URL for validating payments
     * @param string $confirmationURL URL for confirming successful payments
     * @param string $responseType 'Completed' or 'Cancelled' - determines if validation is required
     * @return array Registration response
     */
    public function registerC2BUrls($validationURL, $confirmationURL, $responseType = 'Completed')
    {
        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return formatResponse(false, null, 'Failed to get access token');
            }

            $url = $this->environment === 'production'
                ? 'https://api.safaricom.co.ke/mpesa/c2b/v1/registerurl'
                : 'https://sandbox.safaricom.co.ke/mpesa/c2b/v1/registerurl';

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type:application/json',
                'Authorization:Bearer ' . $accessToken
            ]);

            $requestData = [
                'ShortCode' => $this->businessShortCode,
                'ResponseType' => $responseType, // 'Completed' = no validation, 'Cancelled' = requires validation
                'ConfirmationURL' => $confirmationURL,
                'ValidationURL' => $validationURL
            ];

            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($requestData));

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            $result = json_decode($response, true);

            // Log registration attempt
            $this->logC2BRegistration($validationURL, $confirmationURL, $responseType, $result, $httpCode);

            return formatResponse(true, $result);
        } catch (Exception $e) {
            return formatResponse(false, null, 'C2B URL registration failed: ' . $e->getMessage());
        }
    }

    /**
     * Validate C2B payment before M-Pesa completes it
     * This is called by M-Pesa ValidationURL
     * 
     * @param array $callbackData Data from M-Pesa validation request
     * @return array Validation response (Accept or Reject)
     */
    public function validateC2BPayment($callbackData)
    {
        try {
            // Extract data from callback
            $transAmount = $callbackData['TransAmount'] ?? 0;
            $billRefNumber = $callbackData['BillRefNumber'] ?? null; // This is the admission number

            // Log validation request
            $this->logC2BValidation($callbackData);

            // Validate admission number exists
            if (empty($billRefNumber)) {
                return [
                    'ResultCode' => 'C2B00012',
                    'ResultDesc' => 'Account number is required'
                ];
            }

            $stmt = $this->db->prepare("SELECT id, first_name, last_name FROM students WHERE admission_no = ? AND status = 'active'");
            $stmt->execute([$billRefNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return [
                    'ResultCode' => 'C2B00011',
                    'ResultDesc' => 'Invalid account number: ' . $billRefNumber
                ];
            }

            // Validate amount (must be positive)
            if ($transAmount <= 0) {
                return [
                    'ResultCode' => 'C2B00013',
                    'ResultDesc' => 'Invalid amount'
                ];
            }

            // All validations passed
            return [
                'ResultCode' => '0',
                'ResultDesc' => 'Accepted'
            ];
        } catch (Exception $e) {
            error_log("C2B validation error: " . $e->getMessage());
            return [
                'ResultCode' => 'C2B00016',
                'ResultDesc' => 'System error during validation'
            ];
        }
    }

    /**
     * Process C2B confirmation callback from M-Pesa
     * This is called after a successful payment
     * 
     * @param array $callbackData Data from M-Pesa confirmation request
     * @return array Confirmation response
     */
    public function processC2BConfirmation($callbackData)
    {
        try {
            $this->db->beginTransaction();

            // Extract data from callback
            $transID = $callbackData['TransID'] ?? null;
            $transTime = $callbackData['TransTime'] ?? null;
            $transAmount = $callbackData['TransAmount'] ?? 0;
            $billRefNumber = $callbackData['BillRefNumber'] ?? null; // Admission number
            $msisdn = $callbackData['MSISDN'] ?? null;
            $firstName = $callbackData['FirstName'] ?? '';
            $middleName = $callbackData['MiddleName'] ?? '';
            $lastName = $callbackData['LastName'] ?? '';
            $orgAccountBalance = $callbackData['OrgAccountBalance'] ?? null;
            $thirdPartyTransID = $callbackData['ThirdPartyTransID'] ?? null;

            // Log confirmation request
            $this->logC2BConfirmation($callbackData);

            // Get student ID
            $stmt = $this->db->prepare("SELECT id FROM students WHERE admission_no = ?");
            $stmt->execute([$billRefNumber]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $this->db->rollBack();
                throw new Exception("Student not found: " . $billRefNumber);
            }

            $student_id = $student['id'];

            // Convert transaction time (format: YYYYMMDDHHmmss to Y-m-d H:i:s)
            $transactionDate = $this->formatTransactionDate($transTime);

            // Check for duplicate transaction
            $stmt = $this->db->prepare("SELECT id FROM mpesa_transactions WHERE mpesa_code = ?");
            $stmt->execute([$transID]);
            if ($stmt->fetch()) {
                $this->db->rollBack();
                error_log("Duplicate C2B transaction: " . $transID);
                return [
                    'ResultCode' => '0',
                    'ResultDesc' => 'Duplicate transaction already processed'
                ];
            }

            // Record in mpesa_transactions table
            $stmt = $this->db->prepare("
                INSERT INTO mpesa_transactions 
                (mpesa_code, student_id, amount, transaction_date, phone_number, status, raw_callback, transaction_type, first_name, middle_name, last_name, org_account_balance, third_party_trans_id)
                VALUES (?, ?, ?, ?, ?, 'processed', ?, 'C2B', ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transID,
                $student_id,
                $transAmount,
                $transactionDate,
                $msisdn,
                json_encode($callbackData),
                $firstName,
                $middleName,
                $lastName,
                $orgAccountBalance,
                $thirdPartyTransID
            ]);

            // Use stored procedure to process the payment
            $stmt = $this->db->prepare("CALL sp_process_student_payment(?, ?, ?, ?)");
            $stmt->execute([
                $student_id,
                $transAmount,
                $transID,
                'mpesa_c2b'
            ]);

            $this->db->commit();

            // Log success
            error_log("C2B payment processed successfully: TransID={$transID}, Student={$billRefNumber}, Amount={$transAmount}");

            return [
                'ResultCode' => '0',
                'ResultDesc' => 'Payment processed successfully'
            ];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("C2B confirmation error: " . $e->getMessage());
            return [
                'ResultCode' => '1',
                'ResultDesc' => 'Payment processing failed'
            ];
        }
    }

    /**
     * Log C2B URL registration attempt
     */
    private function logC2BRegistration($validationURL, $confirmationURL, $responseType, $result, $httpCode)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO c2b_url_registrations 
                (validation_url, confirmation_url, response_type, registration_response, http_code, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $validationURL,
                $confirmationURL,
                $responseType,
                json_encode($result),
                $httpCode
            ]);
        } catch (Exception $e) {
            error_log("Failed to log C2B registration: " . $e->getMessage());
        }
    }

    /**
     * Log C2B validation request
     */
    private function logC2BValidation($callbackData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO c2b_validation_log 
                (trans_id, trans_time, trans_amount, business_short_code, bill_ref_number, msisdn, validation_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $callbackData['TransID'] ?? null,
                $callbackData['TransTime'] ?? null,
                $callbackData['TransAmount'] ?? 0,
                $callbackData['BusinessShortCode'] ?? null,
                $callbackData['BillRefNumber'] ?? null,
                $callbackData['MSISDN'] ?? null,
                json_encode($callbackData)
            ]);
        } catch (Exception $e) {
            error_log("Failed to log C2B validation: " . $e->getMessage());
        }
    }

    /**
     * Log C2B confirmation request
     */
    private function logC2BConfirmation($callbackData)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO c2b_confirmation_log 
                (trans_id, trans_time, trans_amount, business_short_code, bill_ref_number, msisdn, confirmation_data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $callbackData['TransID'] ?? null,
                $callbackData['TransTime'] ?? null,
                $callbackData['TransAmount'] ?? 0,
                $callbackData['BusinessShortCode'] ?? null,
                $callbackData['BillRefNumber'] ?? null,
                $callbackData['MSISDN'] ?? null,
                json_encode($callbackData)
            ]);
        } catch (Exception $e) {
            error_log("Failed to log C2B confirmation: " . $e->getMessage());
        }
    }

    /**
     * Validate admission number
     * 
     * Called by C2B validation endpoint to check if admission number exists.
     * 
     * @param string $admissionNumber Student admission number
     * @return array ['valid' => bool, 'student' => array|null, 'message' => string]
     */
    public function validateAdmissionNumber($admissionNumber)
    {
        $query = "
            SELECT 
                s.id,
                s.admission_number,
                s.first_name,
                s.last_name,
                s.status,
                COALESCE(sfb.balance, 0) as current_balance
            FROM students s
            LEFT JOIN student_fee_balances sfb ON s.id = sfb.student_id
            WHERE s.admission_number = :admission_number
            LIMIT 1
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute(['admission_number' => $admissionNumber]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return [
                'valid' => false,
                'student' => null,
                'message' => "Admission number {$admissionNumber} not found"
            ];
        }

        if (!in_array($student['status'], ['active', 'enrolled'])) {
            return [
                'valid' => false,
                'student' => $student,
                'message' => "Student account is {$student['status']}"
            ];
        }

        return [
            'valid' => true,
            'student' => $student,
            'message' => "Valid admission number"
        ];
    }

    /**
     * Get recent payments by admission number
     * 
     * @param string $admissionNumber Student admission number
     * @param int $limit Number of records to retrieve
     * @return array List of recent payments
     */
    public function getPaymentsByAdmission($admissionNumber, $limit = 10)
    {
        $query = "
            SELECT 
                mt.id,
                mt.mpesa_code,
                mt.amount,
                mt.transaction_date,
                mt.phone_number,
                mt.status,
                s.admission_number,
                s.first_name,
                s.last_name
            FROM mpesa_transactions mt
            INNER JOIN students s ON mt.student_id = s.id
            WHERE s.admission_number = :admission_number
            ORDER BY mt.transaction_date DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':admission_number', $admissionNumber, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
