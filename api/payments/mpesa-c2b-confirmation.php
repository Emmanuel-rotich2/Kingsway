<?php
/**
 * M-Pesa C2B Confirmation URL Endpoint
 * 
 * Called by Safaricom AFTER successful payment via Paybill.
 * Purpose: Record the payment and update student account balance.
 * 
 * CRITICAL: This is the FINAL confirmation. Payment has been deducted from customer.
 * Must process and respond within 3 seconds.
 * 
 * Expected Input from M-Pesa:
 * - TransactionType: "Pay Bill"
 * - TransID: M-Pesa confirmation code (unique)
 * - TransTime: yyyyMMddHHmmss
 * - TransAmount: Amount paid
 * - BusinessShortCode: School's paybill number
 * - BillRefNumber: Admission number (student's account reference)
 * - InvoiceNumber: Optional
 * - MSISDN: Customer's phone number (254...)
 * - FirstName, MiddleName, LastName: Customer names
 * - OrgAccountBalance: Business account balance after transaction
 * 
 * Expected Response:
 * - ResultCode: 0 (Success) or 1 (Error)
 * - ResultDesc: Message
 */

require_once __DIR__ . '/../../config/db_connection.php';

use App\Config\Database;

// Set JSON response header
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/mpesa_c2b_confirmation_errors.log');

// Log raw incoming request
$rawInput = file_get_contents('php://input');
file_put_contents(
    __DIR__ . '/../../logs/mpesa_c2b_confirmation_raw.log',
    date('Y-m-d H:i:s') . " - RAW REQUEST:\n" . $rawInput . "\n\n",
    FILE_APPEND
);

try {
    // Parse JSON input
    $confirmationData = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }

    // Log parsed data
    file_put_contents(
        __DIR__ . '/../../logs/mpesa_c2b_confirmation.log',
        date('Y-m-d H:i:s') . " - PARSED DATA:\n" . print_r($confirmationData, true) . "\n\n",
        FILE_APPEND
    );

    // Extract payment details
    $mpesaCode = $confirmationData['TransID'] ?? '';
    $admissionNumber = $confirmationData['BillRefNumber'] ?? '';
    $amount = floatval($confirmationData['TransAmount'] ?? 0);
    $phoneNumber = $confirmationData['MSISDN'] ?? '';
    $transTime = $confirmationData['TransTime'] ?? '';
    $businessShortCode = $confirmationData['BusinessShortCode'] ?? '';
    $firstName = $confirmationData['FirstName'] ?? '';
    $middleName = $confirmationData['MiddleName'] ?? '';
    $lastName = $confirmationData['LastName'] ?? '';
    $orgBalance = $confirmationData['OrgAccountBalance'] ?? '';

    // Validate required fields
    if (empty($mpesaCode) || empty($admissionNumber) || $amount <= 0) {
        throw new Exception("Missing required fields: TransID={$mpesaCode}, BillRefNumber={$admissionNumber}, Amount={$amount}");
    }

    // Connect to database
    $db = Database::getInstance();
    $db->beginTransaction();

    try {
        // Get student ID from admission number
        $studentQuery = "
            SELECT id, first_name, last_name, status
            FROM students
            WHERE admission_number = :admission_number
            LIMIT 1
        ";

        $stmt = $db->prepare($studentQuery);
        $stmt->execute(['admission_number' => $admissionNumber]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception("Student not found: Admission number {$admissionNumber}");
        }

        $studentId = $student['id'];

        // Check for duplicate transaction (mpesa_code is UNIQUE)
        $duplicateCheck = "
            SELECT id, status
            FROM mpesa_transactions
            WHERE mpesa_code = :mpesa_code
            LIMIT 1
        ";

        $dupStmt = $db->prepare($duplicateCheck);
        $dupStmt->execute(['mpesa_code' => $mpesaCode]);
        $existing = $dupStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Transaction already processed
            $db->rollback();

            file_put_contents(
                __DIR__ . '/../../logs/mpesa_c2b_confirmation.log',
                date('Y-m-d H:i:s') . " - DUPLICATE: M-Pesa code {$mpesaCode} already processed (ID: {$existing['id']})\n\n",
                FILE_APPEND
            );

            // Return success (idempotency - already processed)
            $response = [
                'ResultCode' => '0',
                'ResultDesc' => 'Payment already processed'
            ];

            echo json_encode($response);
            exit;
        }

        // Parse transaction time (yyyyMMddHHmmss -> YYYY-MM-DD HH:MM:SS)
        $transDateTime = DateTime::createFromFormat('YmdHis', $transTime);
        if (!$transDateTime) {
            $transDateTime = new DateTime(); // Fallback to current time
        }
        $transDateFormatted = $transDateTime->format('Y-m-d H:i:s');

        // Insert into mpesa_transactions table with status='processed'
        // This will trigger trg_mpesa_payment_processed which:
        // 1. Updates student_fee_balances (reduces balance)
        // 2. Inserts into school_transactions (audit log)
        $insertQuery = "
            INSERT INTO mpesa_transactions (
                mpesa_code,
                student_id,
                amount,
                transaction_date,
                phone_number,
                status,
                raw_callback,
                created_at
            ) VALUES (
                :mpesa_code,
                :student_id,
                :amount,
                :transaction_date,
                :phone_number,
                'processed',
                :raw_callback,
                NOW()
            )
        ";

        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->execute([
            'mpesa_code' => $mpesaCode,
            'student_id' => $studentId,
            'amount' => $amount,
            'transaction_date' => $transDateFormatted,
            'phone_number' => $phoneNumber,
            'raw_callback' => json_encode($confirmationData)
        ]);

        $mpesaTransactionId = $db->lastInsertId();

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
                'mpesa',
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
            'amount_paid' => $amount,
            'payment_date' => $transDateFormatted,
            'reference_no' => $mpesaCode,
            'receipt_no' => 'MPESA-' . $mpesaCode,
            'notes' => "M-Pesa Paybill payment from {$firstName} {$middleName} {$lastName} (Phone: {$phoneNumber})"
        ]);

        // Log webhook for audit
        $webhookLogQuery = "
            INSERT INTO payment_webhooks_log (
                source,
                webhook_data,
                status,
                created_at
            ) VALUES (
                'mpesa_c2b_confirmation',
                :webhook_data,
                'processed',
                NOW()
            )
        ";

        $webhookStmt = $db->prepare($webhookLogQuery);
        $webhookStmt->execute([
            'webhook_data' => json_encode([
                'mpesa_code' => $mpesaCode,
                'admission_number' => $admissionNumber,
                'student_id' => $studentId,
                'amount' => $amount,
                'phone_number' => $phoneNumber,
                'transaction_time' => $transDateFormatted,
                'payer_name' => "{$firstName} {$middleName} {$lastName}",
                'business_short_code' => $businessShortCode,
                'org_balance' => $orgBalance,
                'mpesa_transaction_id' => $mpesaTransactionId
            ])
        ]);

        $db->commit();

        // Success response
        $response = [
            'ResultCode' => '0',
            'ResultDesc' => "Payment of KES {$amount} received for {$student['first_name']} {$student['last_name']} (Adm: {$admissionNumber})"
        ];

        file_put_contents(
            __DIR__ . '/../../logs/mpesa_c2b_confirmation.log',
            date('Y-m-d H:i:s') . " - SUCCESS: M-Pesa {$mpesaCode}, Student {$admissionNumber} ({$student['first_name']} {$student['last_name']}), Amount: KES {$amount}, Phone: {$phoneNumber}\n\n",
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
        __DIR__ . '/../../logs/mpesa_c2b_confirmation_errors.log',
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );

    // Return success to M-Pesa to avoid retries, but log the error for manual processing
    $response = [
        'ResultCode' => '0',
        'ResultDesc' => 'Received. Processing offline.'
    ];

    echo json_encode($response);
}
