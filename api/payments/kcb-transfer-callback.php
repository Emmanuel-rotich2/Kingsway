<?php
/**
 * KCB Bank Transfer Callback Endpoint
 * 
 * This endpoint receives KCB bank-to-bank transfer confirmations
 * Used for: Salary disbursements via bank transfer, Supplier payments
 * 
 * URL: /api/payments/kcb-transfer-callback.php
 * 
 * KCB Transfer Callback Format:
 * {
 *   "transactionReference": "FT00026252",  // KCB transaction reference
 *   "requestId": "c7d702cb-6b5f-4fa6-8b57-436d0f789017",  // Our original request ID
 *   "channelCode": "202",
 *   "timestamp": "2021111103005",
 *   "transactionAmount": "5000.00",
 *   "currency": "KES",
 *   "debitAccountNumber": "1234567890",  // School account
 *   "creditAccountNumber": "0987654321",  // Recipient account
 *   "creditAccountName": "John Doe",
 *   "creditBankCode": "01",  // KCB = 01
 *   "status": "SUCCESS",  // or "FAILED"
 *   "statusDescription": "Transaction successful",
 *   "balance": "100000.00",  // School account balance after transfer
 *   "narration": "Salary payment for John Doe",
 *   "charges": "10.00"
 * }
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use App\Config\Database;
use PDO;

// Set headers for JSON response
header('Content-Type: application/json');

// Log all incoming requests
$logFile = __DIR__ . '/../../logs/kcb_transfer_callbacks.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$rawInput = file_get_contents('php://input');
$headers = getallheaders();

$logEntry = "[$timestamp] RAW KCB TRANSFER CALLBACK:\nHeaders: " . json_encode($headers) . "\nBody: $rawInput\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Get KCB callback data
    $callbackData = json_decode($rawInput, true);

    if (!$callbackData) {
        http_response_code(400);
        echo json_encode([
            'statusCode' => '1',
            'statusMessage' => 'Invalid JSON data'
        ]);
        exit;
    }

    // Extract transaction details
    $transactionRef = $callbackData['transactionReference'] ?? null;
    $requestId = $callbackData['requestId'] ?? null;
    $amount = $callbackData['transactionAmount'] ?? 0;
    $status = $callbackData['status'] ?? 'UNKNOWN';
    $statusDesc = $callbackData['statusDescription'] ?? '';
    $creditAccount = $callbackData['creditAccountNumber'] ?? null;
    $creditAccountName = $callbackData['creditAccountName'] ?? null;
    $debitAccount = $callbackData['debitAccountNumber'] ?? null;
    $charges = $callbackData['charges'] ?? 0;
    $narration = $callbackData['narration'] ?? '';
    $transactionTimestamp = $callbackData['timestamp'] ?? null;

    // Validate required fields
    if (!$requestId || !$amount) {
        http_response_code(400);
        echo json_encode([
            'statusCode' => '1',
            'statusMessage' => 'Missing required fields'
        ]);
        exit;
    }

    // Get database connection
    $db = Database::getInstance()->getConnection();

    // Find the disbursement request by requestId
    $stmt = $db->prepare("
        SELECT id, disbursement_type, recipient_id, amount, account_number, recipient_name, status
        FROM disbursement_transactions
        WHERE request_id = ? OR transaction_ref = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId, $transactionRef]);
    $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$disbursement) {
        // Log unknown transaction
        $logEntry = "[$timestamp] UNKNOWN KCB TRANSFER: RequestID=$requestId, TransactionRef=$transactionRef\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        http_response_code(200);
        echo json_encode([
            'statusCode' => '0',
            'statusMessage' => 'Received but transaction not found'
        ]);
        exit;
    }

    // Update disbursement status
    $db->beginTransaction();

    if (strtoupper($status) === 'SUCCESS') {
        // Success
        $stmt = $db->prepare("
            UPDATE disbursement_transactions
            SET status = 'completed',
                transaction_ref = ?,
                transaction_id = ?,
                completed_at = NOW(),
                result_description = ?,
                callback_data = ?,
                bank_charges = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $transactionRef,
            $transactionRef,
            $statusDesc,
            json_encode($callbackData),
            $charges,
            $disbursement['id']
        ]);

        // Update staff_payments or supplier_payments
        if ($disbursement['disbursement_type'] === 'salary') {
            $stmt = $db->prepare("
                UPDATE staff_payments
                SET disbursement_status = 'completed',
                    bank_reference = ?,
                    disbursement_date = NOW(),
                    bank_charges = ?
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$transactionRef, $charges, $disbursement['id']]);

        } elseif ($disbursement['disbursement_type'] === 'supplier') {
            $stmt = $db->prepare("
                UPDATE supplier_payments
                SET payment_status = 'completed',
                    bank_reference = ?,
                    payment_date = NOW(),
                    bank_charges = ?
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$transactionRef, $charges, $disbursement['id']]);
        }

        $logEntry = "[$timestamp] KCB TRANSFER SUCCESS: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Ref: $transactionRef\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Send success notification
        sendTransferNotification($disbursement, $transactionRef, 'completed', $charges);

    } else {
        // Failed
        $stmt = $db->prepare("
            UPDATE disbursement_transactions
            SET status = 'failed',
                transaction_ref = ?,
                result_description = ?,
                callback_data = ?,
                failed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $transactionRef,
            $statusDesc,
            json_encode($callbackData),
            $disbursement['id']
        ]);

        // Update staff_payments or supplier_payments
        if ($disbursement['disbursement_type'] === 'salary') {
            $stmt = $db->prepare("
                UPDATE staff_payments
                SET disbursement_status = 'failed',
                    disbursement_notes = ?
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$statusDesc, $disbursement['id']]);

        } elseif ($disbursement['disbursement_type'] === 'supplier') {
            $stmt = $db->prepare("
                UPDATE supplier_payments
                SET payment_status = 'failed',
                    payment_notes = ?
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$statusDesc, $disbursement['id']]);
        }

        $logEntry = "[$timestamp] KCB TRANSFER FAILED: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Error: $statusDesc\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Send failure notification
        sendTransferNotification($disbursement, $transactionRef, 'failed', 0, $statusDesc);
    }

    $db->commit();

    // Return success response to KCB
    http_response_code(200);
    echo json_encode([
        'transactionID' => $disbursement['id'],
        'statusCode' => '0',
        'statusMessage' => 'Transfer notification processed successfully'
    ]);

} catch (Exception $e) {
    error_log("KCB Transfer Callback Error: " . $e->getMessage());

    // Log error
    $errorEntry = "[$timestamp] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $errorEntry, FILE_APPEND);

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Return error response
    http_response_code(500);
    echo json_encode([
        'statusCode' => '1',
        'statusMessage' => 'Internal server error'
    ]);
}

/**
 * Send transfer notification to recipient
 */
function sendTransferNotification($disbursement, $transactionRef, $status, $charges = 0, $error = null)
{
    try {
        $db = Database::getInstance()->getConnection();

        // Get recipient contact info
        $phoneNumber = null;
        $email = null;

        if ($disbursement['disbursement_type'] === 'salary') {
            $stmt = $db->prepare("SELECT phone_number, email FROM staff WHERE id = ?");
            $stmt->execute([$disbursement['recipient_id']]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($contact) {
                $phoneNumber = $contact['phone_number'];
                $email = $contact['email'];
            }
        }

        if (!$phoneNumber) {
            return; // No contact info
        }

        $message = '';

        if ($status === 'completed') {
            $netAmount = $disbursement['amount'] - $charges;
            $message = "SALARY PAYMENT COMPLETED\n";
            $message .= "Gross Amount: KES " . number_format($disbursement['amount'], 2) . "\n";
            if ($charges > 0) {
                $message .= "Charges: KES " . number_format($charges, 2) . "\n";
                $message .= "Net Amount: KES " . number_format($netAmount, 2) . "\n";
            }
            $message .= "Reference: $transactionRef\n";
            $message .= "Account: {$disbursement['account_number']}\n";
            $message .= "Thank you.";
        } else {
            $message = "SALARY PAYMENT FAILED\n";
            $message .= "Amount: KES " . number_format($disbursement['amount'], 2) . "\n";
            $message .= "Reason: $error\n";
            $message .= "Please contact admin.";
        }

        // TODO: Send SMS to recipient
        // sendSMS($phoneNumber, $message);

        // TODO: Send email if available
        // if ($email) sendEmail($email, 'Salary Payment Notification', $message);

    } catch (Exception $e) {
        error_log("Failed to send transfer notification: " . $e->getMessage());
    }
}
