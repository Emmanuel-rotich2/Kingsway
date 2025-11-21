<?php
/**
 * M-Pesa Payment Callback Endpoint
 * 
 * This endpoint receives M-Pesa STK Push callbacks
 * URL: /api/payments/mpesa-callback.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use App\API\Services\Payments\MpesaPaymentService;

// Set headers for JSON response
header('Content-Type: application/json');

// Log all incoming requests
$logFile = __DIR__ . '/../../logs/mpesa_callbacks_raw.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$rawInput = file_get_contents('php://input');
$logEntry = "[$timestamp] RAW CALLBACK:\n$rawInput\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Get M-Pesa callback data
    $callbackData = json_decode($rawInput, true);
    
    if (!$callbackData) {
        http_response_code(400);
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Invalid JSON data'
        ]);
        exit;
    }
    
    // Process callback
    $mpesaService = new MpesaPaymentService();
    $result = $mpesaService->processCallback($callbackData);
    
    // M-Pesa expects specific response format
    if ($result['status']) {
        http_response_code(200);
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Confirmation received successfully'
        ]);
    } else {
        http_response_code(200); // Still return 200 to acknowledge receipt
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => $result['message'] ?? 'Processing failed'
        ]);
    }
    
} catch (Exception $e) {
    error_log("M-Pesa Callback Error: " . $e->getMessage());
    
    // Log error
    $errorEntry = "[$timestamp] ERROR: " . $e->getMessage() . "\n\n";
    file_put_contents($logFile, $errorEntry, FILE_APPEND);
    
    // Still return success to M-Pesa to prevent retries
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Received'
    ]);
}
