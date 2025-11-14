<?php
/**
 * M-Pesa C2B Confirmation URL Endpoint
 * 
 * This endpoint is called by M-Pesa AFTER a successful payment
 * to confirm and record the transaction.
 * 
 * Expected Response:
 * - ResultCode: '0' = Success
 * - ResultDesc: Description message
 * 
 * Flow:
 * 1. Customer completes payment from M-Pesa app
 * 2. M-Pesa calls this endpoint with transaction details
 * 3. System records the payment
 * 4. System updates student account
 * 5. Returns Success (0)
 */

namespace App\API;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
use Exception;

use App\API\Services\Payments\MpesaPaymentService;

// Set header - M-Pesa expects JSON
header('Content-Type: application/json');

// Read raw callback data
$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

// Log raw callback for debugging
$logFile = __DIR__ . '/../../logs/mpesa_c2b_confirmations_raw.log';
$logDir = dirname($logFile);
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

$logEntry = date('Y-m-d H:i:s') . " - C2B CONFIRMATION REQUEST\n";
$logEntry .= "Raw Data: " . $callbackJSON . "\n";
$logEntry .= "Parsed Data: " . print_r($callbackData, true) . "\n";
$logEntry .= str_repeat('-', 80) . "\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Initialize M-Pesa service
    $mpesaService = new MpesaPaymentService();
    
    // Process the payment
    $response = $mpesaService->processC2BConfirmation($callbackData);
    
    // Log confirmation response
    $logEntry = date('Y-m-d H:i:s') . " - C2B CONFIRMATION RESPONSE\n";
    $logEntry .= "Response: " . json_encode($response) . "\n";
    $logEntry .= str_repeat('=', 80) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Return response to M-Pesa
    echo json_encode($response);
    
} catch (Exception $e) {
    // On error, still return success to M-Pesa (we don't want to fail the customer's payment)
    // But log the error for manual investigation
    $errorResponse = [
        'ResultCode' => '0', // Still return success to avoid customer issues
        'ResultDesc' => 'Payment received, pending processing'
    ];
    
    // Log error for investigation
    $logEntry = date('Y-m-d H:i:s') . " - C2B CONFIRMATION ERROR (CRITICAL - MANUAL ACTION REQUIRED)\n";
    $logEntry .= "Error: " . $e->getMessage() . "\n";
    $logEntry .= "Callback Data: " . json_encode($callbackData) . "\n";
    $logEntry .= "Response: " . json_encode($errorResponse) . "\n";
    $logEntry .= str_repeat('!', 80) . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    // Also log to system error log
    error_log("CRITICAL - C2B Confirmation Failed: " . $e->getMessage() . " | Data: " . json_encode($callbackData));
    
    echo json_encode($errorResponse);
}
