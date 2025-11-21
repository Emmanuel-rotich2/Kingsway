<?php
/**
 * M-Pesa B2C Timeout URL Endpoint
 * 
 * This endpoint is called when M-Pesa B2C request times out
 * Purpose: Mark disbursement as timed out for retry
 * 
 * URL: /api/payments/mpesa-b2c-timeout.php
 * 
 * M-Pesa Timeout Format:
 * {
 *   "Result": {
 *     "ResultType": 0,
 *     "ResultCode": 1,
 *     "ResultDesc": "The balance is insufficient for the transaction",
 *     "OriginatorConversationID": "29115-34620561-1",
 *     "ConversationID": "AG_20191219_00005797af5d7d75f652",
 *     "TransactionID": "",
 *     "ReferenceData": {
 *       "ReferenceItem": {
 *         "Key": "QueueTimeoutURL"
 *       }
 *     }
 *   }
 * }
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';

use App\Config\Database;
use PDO;

// Set headers for JSON response
header('Content-Type: application/json');

// Log all incoming requests
$logFile = __DIR__ . '/../../logs/mpesa_b2c_timeouts.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$rawInput = file_get_contents('php://input');
$logEntry = "[$timestamp] RAW B2C TIMEOUT:\n$rawInput\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Get M-Pesa B2C timeout data
    $timeoutData = json_decode($rawInput, true);

    if (!$timeoutData || !isset($timeoutData['Result'])) {
        http_response_code(400);
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Invalid timeout data'
        ]);
        exit;
    }

    $result = $timeoutData['Result'];
    $resultCode = $result['ResultCode'] ?? 1;
    $resultDesc = $result['ResultDesc'] ?? 'Request timed out';
    $conversationID = $result['ConversationID'] ?? null;
    $originatorConversationID = $result['OriginatorConversationID'] ?? null;

    // Get database connection
    $db = Database::getInstance()->getConnection();

    // Find the disbursement request
    $stmt = $db->prepare("
        SELECT id, disbursement_type, recipient_id, amount, phone_number, recipient_name, retry_count
        FROM disbursement_transactions
        WHERE conversation_id = ? OR originator_conversation_id = ?
        LIMIT 1
    ");
    $stmt->execute([$conversationID, $originatorConversationID]);
    $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$disbursement) {
        // Log unknown transaction
        $logEntry = "[$timestamp] UNKNOWN TIMEOUT: ConversationID=$conversationID\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        http_response_code(200);
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Received but transaction not found'
        ]);
        exit;
    }

    // Update disbursement status
    $db->beginTransaction();

    $retryCount = ($disbursement['retry_count'] ?? 0) + 1;
    $maxRetries = 3; // Maximum retry attempts

    if ($retryCount < $maxRetries) {
        // Mark for retry
        $stmt = $db->prepare("
            UPDATE disbursement_transactions
            SET status = 'timeout',
                retry_count = ?,
                result_code = ?,
                result_description = ?,
                callback_data = ?,
                last_retry_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $retryCount,
            $resultCode,
            $resultDesc,
            json_encode($timeoutData),
            $disbursement['id']
        ]);

        $logEntry = "[$timestamp] B2C TIMEOUT (Retry $retryCount/$maxRetries): {$disbursement['recipient_name']} - KES {$disbursement['amount']}\n";
        $logEntry .= "Reason: $resultDesc\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

    } else {
        // Max retries reached - mark as failed
        $stmt = $db->prepare("
            UPDATE disbursement_transactions
            SET status = 'failed',
                retry_count = ?,
                result_code = ?,
                result_description = ?,
                callback_data = ?,
                failed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $retryCount,
            $resultCode,
            "Max retries exceeded: $resultDesc",
            json_encode($timeoutData),
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
            $stmt->execute(["Max retries exceeded: $resultDesc", $disbursement['id']]);

        } elseif ($disbursement['disbursement_type'] === 'supplier') {
            $stmt = $db->prepare("
                UPDATE supplier_payments
                SET payment_status = 'failed',
                    payment_notes = ?
                WHERE disbursement_id = ?
            ");
            $stmt->execute(["Max retries exceeded: $resultDesc", $disbursement['id']]);
        }

        $logEntry = "[$timestamp] B2C FAILED (Max retries): {$disbursement['recipient_name']} - KES {$disbursement['amount']}\n";
        $logEntry .= "Reason: $resultDesc\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        // Notify admin
        notifyAdminOfFailedDisbursement($disbursement, $resultDesc);
    }

    $db->commit();

    // Return success response to M-Pesa
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Timeout processed successfully'
    ]);

} catch (Exception $e) {
    error_log("M-Pesa B2C Timeout Error: " . $e->getMessage());

    // Log error
    $errorEntry = "[$timestamp] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $errorEntry, FILE_APPEND);

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Still return success to M-Pesa
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Received'
    ]);
}

/**
 * Notify admin of failed disbursement
 */
function notifyAdminOfFailedDisbursement($disbursement, $reason)
{
    try {
        $message = "DISBURSEMENT FAILED AFTER MAX RETRIES\n\n";
        $message .= "Recipient: {$disbursement['recipient_name']}\n";
        $message .= "Phone: {$disbursement['phone_number']}\n";
        $message .= "Amount: KES " . number_format($disbursement['amount'], 2) . "\n";
        $message .= "Type: " . strtoupper($disbursement['disbursement_type']) . "\n";
        $message .= "Reason: $reason\n";
        $message .= "Action Required: Manual disbursement needed.\n";

        // TODO: Send email/SMS to admin
        // sendAdminEmail('Disbursement Failed', $message);

        // Log to admin notification file
        $adminLogFile = __DIR__ . '/../../logs/admin_notifications.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($adminLogFile, "[$timestamp] $message\n\n", FILE_APPEND);

    } catch (Exception $e) {
        error_log("Failed to notify admin: " . $e->getMessage());
    }
}
