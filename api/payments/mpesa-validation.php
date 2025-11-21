<?php
/**
 * M-Pesa C2B Validation URL Endpoint
 * 
 * This endpoint is called by M-Pesa BEFORE completing a payment
 * to validate that the account number (admission number) exists.
 * 
 * MUST respond within 30 seconds with:
 * - ResultCode: '0' = Accept, anything else = Reject
 * - ResultDesc: Description message
 * 
 * Flow:
 * 1. Customer initiates payment from M-Pesa app
 * 2. M-Pesa calls this endpoint to validate account number
 * 3. System checks if admission number exists
 * 4. Returns Accept (0) or Reject (C2B00011)
 * 5. If accepted, payment proceeds; if rejected, payment cancelled
 */

namespace App\API;
use Exception;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use App\API\Services\Payments\MpesaPaymentService;

// Set header - M-Pesa expects JSON
header('Content-Type: application/json');

// Read raw callback data
$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

// Log raw callback for debugging
$logFile = __DIR__ . '/../../logs/mpesa_c2b_validations_raw.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = date('Y-m-d H:i:s') . " - C2B VALIDATION REQUEST\n";
$logEntry .= "Raw Data: " . $callbackJSON . "\n";
$logEntry .= "Parsed Data: " . print_r($callbackData, true) . "\n";
$logEntry .= str_repeat('-', 80) . "\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Initialize M-Pesa service
    $mpesaService = new MpesaPaymentService();
    
    // Validate the payment
    $response = $mpesaService->validateC2BPayment($callbackData);
    
    // Log validation response
    $logEntry = date('Y-m-d H:i:s') . " - C2B VALIDATION RESPONSE\n";
    $logEntry .= "Response: " . json_encode($response) . "\n";
    $logEntry .= str_repeat('=', 80) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Return response to M-Pesa
    echo json_encode($response);
    
} catch (Exception $e) {
    // On error, reject the payment
    $errorResponse = [
        'ResultCode' => 'C2B00016',
        'ResultDesc' => 'System error during validation'
    ];
    
    // Log error
    $logEntry = date('Y-m-d H:i:s') . " - C2B VALIDATION ERROR\n";
    $logEntry .= "Error: " . $e->getMessage() . "\n";
    $logEntry .= "Response: " . json_encode($errorResponse) . "\n";
    $logEntry .= str_repeat('!', 80) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    echo json_encode($errorResponse);
}
