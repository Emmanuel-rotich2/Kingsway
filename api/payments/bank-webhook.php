<?php
/**
 * Bank Payment Webhook Endpoint
 * 
 * This endpoint receives bank payment notifications (KCB and others)
 * URL: /api/payments/bank-webhook.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use App\API\Services\Payments\BankPaymentWebhook;

// Set headers for JSON response
header('Content-Type: application/json');

// Log all incoming requests
$logFile = __DIR__ . '/../../logs/bank_webhooks_raw.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$rawInput = file_get_contents('php://input');
$headers = getallheaders();

$logEntry = "[$timestamp] RAW WEBHOOK:\nHeaders: " . json_encode($headers) . "\nBody: $rawInput\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Get webhook data
    $webhookData = json_decode($rawInput, true);
    
    if (!$webhookData) {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => 'Invalid JSON data'
        ]);
        exit;
    }
    
    // Validate webhook signature/API key
    $bankService = new BankPaymentWebhook();
    
    // Determine bank source from header or data
    $bankName = $headers['X-Bank-Name'] ?? $webhookData['bank'] ?? 'KCB';
    
    // Process based on bank
    if (strtoupper($bankName) === 'KCB') {
        $result = $bankService->processKCBPayment($webhookData);
    } else {
        $result = $bankService->processGenericBankPayment($webhookData, $bankName);
    }
    
    // Return response
    if ($result['status']) {
        http_response_code(200);
        echo json_encode([
            'status' => true,
            'message' => 'Payment processed successfully',
            'data' => $result['data']
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'status' => false,
            'message' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    error_log("Bank Webhook Error: " . $e->getMessage());
    
    // Log error
    $errorEntry = "[$timestamp] ERROR: " . $e->getMessage() . "\n\n";
    file_put_contents($logFile, $errorEntry, FILE_APPEND);
    
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'message' => 'Internal server error'
    ]);
}
