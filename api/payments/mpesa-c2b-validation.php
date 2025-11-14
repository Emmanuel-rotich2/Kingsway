<?php
/**
 * M-Pesa C2B Validation URL Endpoint
 * 
 * Called by Safaricom BEFORE accepting payment via Paybill.
 * Purpose: Validate that the admission number (account reference) exists.
 * 
 * CRITICAL: Must respond within 3 seconds or payment will be rejected.
 * 
 * Expected Input from M-Pesa:
 * - TransactionType: "PayBill" or "BuyGoods"
 * - TransID: M-Pesa transaction ID
 * - TransTime: yyyyMMddHHmmss
 * - TransAmount: Amount paid
 * - BusinessShortCode: School's paybill number
 * - BillRefNumber: Admission number (student's account reference)
 * - InvoiceNumber: Optional
 * - MSISDN: Customer's phone number (254...)
 * - FirstName, MiddleName, LastName: Customer names
 * - OrgAccountBalance: Business account balance
 * 
 * Expected Response:
 * - ResultCode: 0 (Accept) or 1 (Reject)
 * - ResultDesc: Message explaining acceptance/rejection
 */

require_once __DIR__ . '/../../config/db_connection.php';

use App\Config\Database;

// Set JSON response header
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/mpesa_c2b_validation_errors.log');

// Log raw incoming request for debugging
$rawInput = file_get_contents('php://input');
file_put_contents(
    __DIR__ . '/../../logs/mpesa_c2b_validation_raw.log',
    date('Y-m-d H:i:s') . " - RAW REQUEST:\n" . $rawInput . "\n\n",
    FILE_APPEND
);

try {
    // Parse JSON input
    $validationData = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    
    // Log parsed data
    file_put_contents(
        __DIR__ . '/../../logs/mpesa_c2b_validation.log',
        date('Y-m-d H:i:s') . " - PARSED DATA:\n" . print_r($validationData, true) . "\n\n",
        FILE_APPEND
    );
    
    // Extract admission number (account reference)
    $admissionNumber = $validationData['BillRefNumber'] ?? '';
    $transAmount = $validationData['TransAmount'] ?? 0;
    $msisdn = $validationData['MSISDN'] ?? '';
    $transID = $validationData['TransID'] ?? '';
    $transTime = $validationData['TransTime'] ?? '';
    $businessShortCode = $validationData['BusinessShortCode'] ?? '';
    
    // Validate required fields
    if (empty($admissionNumber)) {
        // Reject: No admission number provided
        $response = [
            'ResultCode' => 'C2B00011', // M-Pesa error code for invalid account
            'ResultDesc' => 'Invalid Account. Please enter a valid admission number as account number.'
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/mpesa_c2b_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Empty admission number\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Connect to database
    $db = Database::getInstance();
    
    // Check if admission number exists and get student details
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
    
    $stmt = $db->prepare($query);
    $stmt->execute(['admission_number' => $admissionNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        // Reject: Admission number not found
        $response = [
            'ResultCode' => 'C2B00011', // Invalid account
            'ResultDesc' => "Admission number {$admissionNumber} not found. Please verify and try again."
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/mpesa_c2b_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Admission number '{$admissionNumber}' not found\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Check if student is active
    if ($student['status'] !== 'active' && $student['status'] !== 'enrolled') {
        // Reject: Student not active
        $response = [
            'ResultCode' => 'C2B00012', // Account suspended/closed
            'ResultDesc' => "Student account {$admissionNumber} is {$student['status']}. Please contact school administration."
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/mpesa_c2b_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Student '{$admissionNumber}' status is '{$student['status']}'\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Validate amount (optional - can set minimum payment)
    $minPayment = 100; // Minimum 100 KES
    if ($transAmount < $minPayment) {
        $response = [
            'ResultCode' => 'C2B00013', // Amount below minimum
            'ResultDesc' => "Minimum payment amount is KES {$minPayment}. Please pay at least KES {$minPayment}."
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/mpesa_c2b_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Amount {$transAmount} below minimum {$minPayment}\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Accept: All validations passed
    $response = [
        'ResultCode' => '0', // Success - Accept payment
        'ResultDesc' => "Payment accepted for {$student['first_name']} {$student['last_name']} (Adm: {$admissionNumber}). Current balance: KES " . number_format($student['current_balance'], 2)
    ];
    
    // Log validation request for audit
    $auditQuery = "
        INSERT INTO payment_webhooks_log (
            source,
            webhook_data,
            status,
            created_at
        ) VALUES (
            'mpesa_c2b_validation',
            :webhook_data,
            'validated',
            NOW()
        )
    ";
    
    $auditStmt = $db->prepare($auditQuery);
    $auditStmt->execute([
        'webhook_data' => json_encode([
            'admission_number' => $admissionNumber,
            'student_id' => $student['id'],
            'trans_id' => $transID,
            'trans_amount' => $transAmount,
            'msisdn' => $msisdn,
            'trans_time' => $transTime,
            'business_short_code' => $businessShortCode,
            'validation_result' => 'accepted',
            'student_name' => $student['first_name'] . ' ' . $student['last_name'],
            'current_balance' => $student['current_balance']
        ])
    ]);
    
    file_put_contents(
        __DIR__ . '/../../logs/mpesa_c2b_validation.log',
        date('Y-m-d H:i:s') . " - ACCEPTED: Student '{$admissionNumber}' - {$student['first_name']} {$student['last_name']}, Amount: {$transAmount}, TransID: {$transID}\n\n",
        FILE_APPEND
    );
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    file_put_contents(
        __DIR__ . '/../../logs/mpesa_c2b_validation_errors.log',
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );
    
    // Default to ACCEPT on system error to avoid blocking legitimate payments
    // (You can change this to REJECT if you prefer fail-safe behavior)
    $response = [
        'ResultCode' => '0', // Accept
        'ResultDesc' => 'Payment accepted. Validation will be completed offline.'
    ];
    
    echo json_encode($response);
}
