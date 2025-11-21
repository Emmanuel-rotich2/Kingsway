<?php
/**
 * KCB Bank Payment Notification Endpoint
 * 
 * Called by KCB Bank AFTER successful payment to notify us of the transaction.
 * This is the FINAL confirmation - payment has been credited to school account.
 * 
 * KCB Request Structure:
 * {
 *   "transactionReference": "FT00026252",  // KCB transaction reference
 *   "requestId": "c7d702cb-6b5f-4fa6-8b57-436d0f789017",
 *   "channelCode": "202",
 *   "timestamp": "2021111103005",
 *   "transactionAmount": "100.00",
 *   "currency": "KES",
 *   "customerReference": "ADM001",  // Admission number
 *   "customerName": "John Doe",
 *   "customerMobileNumber": "25471111111",
 *   "balance": "100000.00",  // School account balance after transaction
 *   "narration": "Payment for goods",
 *   "creditAccountIdentifier": "JD001",  // School account
 *   "organizationShortCode": "777777",
 *   "tillNumber": "150150"
 * }
 * 
 * Expected Response:
 * {
 *   "transactionID": "123456789",
 *   "statusCode": "0",
 *   "statusMessage": "Notification received successfully"
 * }
 * 
 * Security: KCB signs request with SHA256withRSA
 */

require_once __DIR__ . '/../../config/db_connection.php';
require_once __DIR__ . '/../../config/config.php';

use App\Config\Database;

// Set JSON response header
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/kcb_notification_errors.log');

// Log raw incoming request
$rawInput = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['Signature'] ?? $headers['signature'] ?? '';

file_put_contents(
    __DIR__ . '/../../logs/kcb_notification_raw.log',
    date('Y-m-d H:i:s') . " - RAW REQUEST:\n" .
    "Signature: {$signature}\n" .
    "Body: {$rawInput}\n\n",
    FILE_APPEND
);

try {
    // Parse JSON input
    $notificationData = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    
    // Log parsed data
    file_put_contents(
        __DIR__ . '/../../logs/kcb_notification.log',
        date('Y-m-d H:i:s') . " - PARSED DATA:\n" . print_r($notificationData, true) . "\n\n",
        FILE_APPEND
    );
    
    // Extract payment details
    $transactionReference = $notificationData['transactionReference'] ?? '';
    $requestId = $notificationData['requestId'] ?? '';
    $customerReference = $notificationData['customerReference'] ?? ''; // Admission number
    $transactionAmount = floatval($notificationData['transactionAmount'] ?? 0);
    $customerName = $notificationData['customerName'] ?? '';
    $customerMobile = $notificationData['customerMobileNumber'] ?? '';
    $narration = $notificationData['narration'] ?? '';
    $timestamp = $notificationData['timestamp'] ?? '';
    $currency = $notificationData['currency'] ?? 'KES';
    $channelCode = $notificationData['channelCode'] ?? '';
    $orgShortCode = $notificationData['organizationShortCode'] ?? '';
    $balance = $notificationData['balance'] ?? '';
    
    // Validate required fields
    if (empty($transactionReference) || empty($customerReference) || $transactionAmount <= 0) {
        throw new Exception("Missing required fields: TransRef={$transactionReference}, CustRef={$customerReference}, Amount={$transactionAmount}");
    }
    
    // Connect to database
    $db = Database::getInstance();
    $db->beginTransaction();
    
    try {
        // Get student ID from admission number
        $studentQuery = "
            SELECT id, first_name, last_name, status
            FROM students
            WHERE admission_no = :admission_no
            LIMIT 1
        ";
        
        $stmt = $db->prepare($studentQuery);
        $stmt->execute(['admission_no' => $customerReference]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception("Student not found: Admission number {$customerReference}");
        }
        
        $studentId = $student['id'];
        
        // Check for duplicate transaction (transaction_ref is UNIQUE)
        $duplicateCheck = "
            SELECT id, status
            FROM bank_transactions
            WHERE transaction_ref = :transaction_ref
            LIMIT 1
        ";
        
        $dupStmt = $db->prepare($duplicateCheck);
        $dupStmt->execute(['transaction_ref' => $transactionReference]);
        $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Transaction already processed
            $db->rollback();
            
            file_put_contents(
                __DIR__ . '/../../logs/kcb_notification.log',
                date('Y-m-d H:i:s') . " - DUPLICATE: Transaction {$transactionReference} already processed (ID: {$existing['id']})\n\n",
                FILE_APPEND
            );
            
            // Return success (idempotency)
            $response = [
                'transactionID' => $requestId,
                'statusCode' => '0',
                'statusMessage' => 'Notification received successfully (already processed)'
            ];
            
            echo json_encode($response);
            exit;
        }
        
        // Parse timestamp (format: 2021111103005 -> YYYY-MM-DD HH:MM:SS)
        $transDateTime = DateTime::createFromFormat('YmdHis', $timestamp);
        if (!$transDateTime) {
            $transDateTime = new DateTime(); // Fallback to current time
        }
        $transDateFormatted = $transDateTime->format('Y-m-d H:i:s');
        
        // Insert into bank_transactions table with status='processed'
        // This will trigger trg_bank_payment_processed which:
        // 1. Updates student_fee_balances (reduces balance)
        // 2. Inserts into school_transactions (audit log)
        $insertQuery = "
            INSERT INTO bank_transactions (
                transaction_ref,
                student_id,
                amount,
                transaction_date,
                bank_name,
                account_number,
                narration,
                status,
                webhook_data,
                created_at
            ) VALUES (
                :transaction_ref,
                :student_id,
                :amount,
                :transaction_date,
                'KCB Bank',
                :account_number,
                :narration,
                'processed',
                :webhook_data,
                NOW()
            )
        ";
        
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([
            'transaction_ref' => $transactionReference,
            'student_id' => $studentId,
            'amount' => $transactionAmount,
            'transaction_date' => $transDateFormatted,
            'account_number' => $customerMobile,
            'narration' => $narration,
            'webhook_data' => json_encode($notificationData)
        ]);
        
        $bankTransactionId = $db->lastInsertId();
        
        // Insert into payment_transactions for complete audit trail
        $paymentQuery = "
            INSERT INTO payment_transactions (
                student_id,
                amount_paid,
                payment_date,
                payment_method,
                reference_no,
                receipt_no,
                status,
                notes,
                created_at
            ) VALUES (
                :student_id,
                :amount_paid,
                :payment_date,
                'bank_transfer',
                :reference_no,
                :receipt_no,
                'confirmed',
                :notes,
                NOW()
            )
        ";
        
        $paymentStmt = $db->prepare($paymentQuery);
        $paymentStmt->execute([
            'student_id' => $studentId,
            'amount_paid' => $transactionAmount,
            'payment_date' => $transDateFormatted,
            'reference_no' => $transactionReference,
            'receipt_no' => 'KCB-' . $transactionReference,
            'notes' => "KCB Bank payment from {$customerName} (Mobile: {$customerMobile}). {$narration}"
        ]);
        
        // Log webhook for audit
        $webhookLogQuery = "
            INSERT INTO payment_webhooks_log (
                source,
                webhook_data,
                status,
                created_at
            ) VALUES (
                'kcb_bank',
                :webhook_data,
                'processed',
                NOW()
            )
        ";
        
        $webhookStmt = $db->prepare($webhookLogQuery);
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
        
        $db->commit();
        
        // Success response
        $response = [
            'transactionID' => $requestId,
            'statusCode' => '0',
            'statusMessage' => 'Notification received successfully'
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/kcb_notification.log',
            date('Y-m-d H:i:s') . " - SUCCESS: KCB {$transactionReference}, Student {$customerReference} ({$student['first_name']} {$student['last_name']}), Amount: KES {$transactionAmount}, Mobile: {$customerMobile}\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Log error
    file_put_contents(
        __DIR__ . '/../../logs/kcb_notification_errors.log',
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );
    
    // Return success to KCB to avoid retries, but log for manual processing
    $response = [
        'transactionID' => $notificationData['requestId'] ?? 'UNKNOWN',
        'statusCode' => '0',
        'statusMessage' => 'Received. Processing offline.'
    ];
    
    echo json_encode($response);
}
