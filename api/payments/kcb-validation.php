<?php
/**
 * KCB Bank Payment Validation Endpoint
 * 
 * Called by KCB Bank BEFORE accepting payment to validate customer reference (admission number).
 * 
 * KCB Request Structure:
 * {
 *   "requestId": "d115245e-9604-49de-9436-9fdcb539871f",
 *   "customerReference": "ADM001",  // Admission number
 *   "organizationReference": "777777"  // School's organization code
 * }
 * 
 * Expected Response:
 * {
 *   "transactionID": "123456789",
 *   "statusCode": "0",  // 0 = Success, other = Failure
 *   "statusMessage": "Success",
 *   "CustomerName": "John Doe",
 *   "billAmount": "100.00",
 *   "currency": "KES",
 *   "billType": "PARTIAL",  // FIXED or PARTIAL
 *   "creditAccountIdentifier": "1234567890"  // School account number
 * }
 * 
 * Security: KCB signs request with SHA256withRSA - verify using KCB public key
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
ini_set('error_log', __DIR__ . '/../../logs/kcb_validation_errors.log');

// Log raw incoming request
$rawInput = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['Signature'] ?? $headers['signature'] ?? '';

file_put_contents(
    __DIR__ . '/../../logs/kcb_validation_raw.log',
    date('Y-m-d H:i:s') . " - RAW REQUEST:\n" . 
    "Signature: {$signature}\n" .
    "Body: {$rawInput}\n\n",
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
        __DIR__ . '/../../logs/kcb_validation.log',
        date('Y-m-d H:i:s') . " - PARSED DATA:\n" . print_r($validationData, true) . "\n\n",
        FILE_APPEND
    );
    
    // Extract fields
    $requestId = $validationData['requestId'] ?? '';
    $customerReference = $validationData['customerReference'] ?? ''; // Admission number
    $organizationReference = $validationData['organizationReference'] ?? '';
    
    // Validate required fields
    if (empty($customerReference)) {
        $response = [
            'transactionID' => $requestId,
            'statusCode' => '1',
            'statusMessage' => 'Customer reference (admission number) is required',
            'CustomerName' => '',
            'billAmount' => '0.00',
            'currency' => 'KES',
            'billType' => 'PARTIAL',
            'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/kcb_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Empty customer reference\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Connect to database
    $db = Database::getInstance();
    
    // Validate admission number and get student details
    $query = "
        SELECT 
            s.id,
            s.admission_no,
            CONCAT(s.first_name, ' ', s.last_name) as full_name,
            s.status,
            COALESCE(sfb.balance, 0) as current_balance
        FROM students s
        LEFT JOIN student_fee_balances sfb ON s.id = sfb.student_id
        WHERE s.admission_no = :admission_no
        LIMIT 1
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['admission_no' => $customerReference]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        // Reject: Admission number not found
        $response = [
            'transactionID' => $requestId,
            'statusCode' => '1',
            'statusMessage' => "Admission number {$customerReference} not found. Please verify and try again.",
            'CustomerName' => '',
            'billAmount' => '0.00',
            'currency' => 'KES',
            'billType' => 'PARTIAL',
            'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/kcb_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Admission number '{$customerReference}' not found\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Check if student is active
    if (!in_array($student['status'], ['active', 'enrolled'])) {
        $response = [
            'transactionID' => $requestId,
            'statusCode' => '1',
            'statusMessage' => "Student account {$customerReference} is {$student['status']}. Please contact school administration.",
            'CustomerName' => $student['full_name'],
            'billAmount' => '0.00',
            'currency' => 'KES',
            'billType' => 'PARTIAL',
            'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
        ];
        
        file_put_contents(
            __DIR__ . '/../../logs/kcb_validation.log',
            date('Y-m-d H:i:s') . " - REJECTED: Student '{$customerReference}' status is '{$student['status']}'\n\n",
            FILE_APPEND
        );
        
        echo json_encode($response);
        exit;
    }
    
    // Accept: All validations passed
    $response = [
        'transactionID' => $requestId,
        'statusCode' => '0', // Success
        'statusMessage' => 'Success',
        'CustomerName' => $student['full_name'],
        'billAmount' => number_format($student['current_balance'], 2, '.', ''), // Outstanding balance
        'currency' => 'KES',
        'billType' => 'PARTIAL', // Allow partial payments
        'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
    ];
    
    // Log validation request for audit
    $auditQuery = "
        INSERT INTO payment_webhooks_log (
            source,
            webhook_data,
            status,
            created_at
        ) VALUES (
            'kcb_validation',
            :webhook_data,
            'validated',
            NOW()
        )
    ";
    
    $auditStmt = $db->prepare($auditQuery);
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
        __DIR__ . '/../../logs/kcb_validation.log',
        date('Y-m-d H:i:s') . " - ACCEPTED: Student '{$customerReference}' - {$student['full_name']}, Balance: {$student['current_balance']}, RequestID: {$requestId}\n\n",
        FILE_APPEND
    );
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    file_put_contents(
        __DIR__ . '/../../logs/kcb_validation_errors.log',
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );
    
    // Return error response
    $response = [
        'transactionID' => $validationData['requestId'] ?? 'UNKNOWN',
        'statusCode' => '1',
        'statusMessage' => 'System error. Please try again later.',
        'CustomerName' => '',
        'billAmount' => '0.00',
        'currency' => 'KES',
        'billType' => 'PARTIAL',
        'creditAccountIdentifier' => defined('KCB_CREDIT_ACCOUNT') ? KCB_CREDIT_ACCOUNT : ''
    ];
    
    echo json_encode($response);
}
