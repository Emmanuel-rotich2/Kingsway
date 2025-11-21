<?php
/**
 * M-Pesa B2C Result Callback Endpoint
 * 
 * This endpoint receives M-Pesa B2C (Business to Customer) transaction results
 * Used for: Salary disbursements, Supplier payments
 * 
 * URL: /api/payments/mpesa-b2c-callback.php
 * 
 * M-Pesa B2C Result Format:
 * {
 *   "Result": {
 *     "ResultType": 0,
 *     "ResultCode": 0,
 *     "ResultDesc": "The service request is processed successfully.",
 *     "OriginatorConversationID": "29115-34620561-1",
 *     "ConversationID": "AG_20191219_00005797af5d7d75f652",
 *     "TransactionID": "NLJ7RT61SV",
 *     "ResultParameters": {
 *       "ResultParameter": [
 *         {"Key": "TransactionAmount", "Value": 5000},
 *         {"Key": "TransactionReceipt", "Value": "NLJ7RT61SV"},
 *         {"Key": "B2CRecipientIsRegisteredCustomer", "Value": "Y"},
 *         {"Key": "B2CChargesPaidAccountAvailableFunds", "Value": -4985.00},
 *         {"Key": "ReceiverPartyPublicName", "Value": "254708374149 - John Doe"},
 *         {"Key": "TransactionCompletedDateTime", "Value": "19.12.2019 11:45:50"},
 *         {"Key": "B2CUtilityAccountAvailableFunds", "Value": 10116.00},
 *         {"Key": "B2CWorkingAccountAvailableFunds", "Value": 900000.00}
 *       ]
 *     },
 *     "ReferenceData": {
 *       "ReferenceItem": {
 *         "Key": "QueueTimeoutURL",
 *         "Value": "https://internalsandbox.safaricom.co.ke/mpesa/b2cresults/v1/submit"
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
$logFile = __DIR__ . '/../../logs/mpesa_b2c_callbacks.log';
$logDir = dirname($logFile);

if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$timestamp = date('Y-m-d H:i:s');
$rawInput = file_get_contents('php://input');
$logEntry = "[$timestamp] RAW B2C CALLBACK:\n$rawInput\n\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Get M-Pesa B2C result data
    $callbackData = json_decode($rawInput, true);

    if (!$callbackData || !isset($callbackData['Result'])) {
        http_response_code(400);
        echo json_encode([
            'ResultCode' => 1,
            'ResultDesc' => 'Invalid B2C result data'
        ]);
        exit;
    }

    $result = $callbackData['Result'];
    $resultCode = $result['ResultCode'] ?? 1;
    $resultDesc = $result['ResultDesc'] ?? 'Unknown error';
    $conversationID = $result['ConversationID'] ?? null;
    $originatorConversationID = $result['OriginatorConversationID'] ?? null;
    $transactionID = $result['TransactionID'] ?? null;

    // Extract result parameters
    $resultParams = [];
    if (isset($result['ResultParameters']['ResultParameter'])) {
        foreach ($result['ResultParameters']['ResultParameter'] as $param) {
            $resultParams[$param['Key']] = $param['Value'] ?? null;
        }
    }

    $transactionAmount = $resultParams['TransactionAmount'] ?? 0;
    $transactionReceipt = $resultParams['TransactionReceipt'] ?? $transactionID;
    $recipientName = $resultParams['ReceiverPartyPublicName'] ?? null;
    $completedDateTime = $resultParams['TransactionCompletedDateTime'] ?? null;
    $recipientRegistered = $resultParams['B2CRecipientIsRegisteredCustomer'] ?? 'N';

    // Get database connection
    $db = Database::getInstance()->getConnection();

    // Find the disbursement request by ConversationID
    $stmt = $db->prepare("
        SELECT id, disbursement_type, recipient_id, amount, phone_number, recipient_name, status
        FROM disbursement_transactions
        WHERE conversation_id = ? OR originator_conversation_id = ?
        LIMIT 1
    ");
    $stmt->execute([$conversationID, $originatorConversationID]);
    $disbursement = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$disbursement) {
        // Log unknown transaction
        $logEntry = "[$timestamp] UNKNOWN B2C TRANSACTION: ConversationID=$conversationID, TransactionID=$transactionID\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        http_response_code(200);
        echo json_encode([
            'ResultCode' => 0,
            'ResultDesc' => 'Received but transaction not found'
        ]);
        exit;
    }

    // Update disbursement status based on result code
    $db->beginTransaction();

    if ($resultCode == 0) {
        // Success
        $stmt = $db->prepare("
            UPDATE disbursement_transactions
            SET status = 'completed',
                mpesa_receipt_number = ?,
                transaction_id = ?,
                completed_at = NOW(),
                result_code = ?,
                result_description = ?,
                callback_data = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $transactionReceipt,
            $transactionID,
            $resultCode,
            $resultDesc,
            json_encode($callbackData),
            $disbursement['id']
        ]);

        // Update staff_payments or supplier_payments table
        if ($disbursement['disbursement_type'] === 'salary') {
            $stmt = $db->prepare("
                UPDATE staff_payments
                SET disbursement_status = 'completed',
                    mpesa_receipt = ?,
                    disbursement_date = NOW()
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$transactionReceipt, $disbursement['id']]);

        } elseif ($disbursement['disbursement_type'] === 'supplier') {
            $stmt = $db->prepare("
                UPDATE supplier_payments
                SET payment_status = 'completed',
                    mpesa_receipt = ?,
                    payment_date = NOW()
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$transactionReceipt, $disbursement['id']]);
        }

        $logEntry = "[$timestamp] B2C SUCCESS: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Receipt: $transactionReceipt\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

    } else {
        // Failed
        $stmt = $db->prepare("
            UPDATE disbursement_transactions
            SET status = 'failed',
                result_code = ?,
                result_description = ?,
                callback_data = ?,
                failed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $resultCode,
            $resultDesc,
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
            $stmt->execute([$resultDesc, $disbursement['id']]);

        } elseif ($disbursement['disbursement_type'] === 'supplier') {
            $stmt = $db->prepare("
                UPDATE supplier_payments
                SET payment_status = 'failed',
                    payment_notes = ?
                WHERE disbursement_id = ?
            ");
            $stmt->execute([$resultDesc, $disbursement['id']]);
        }

        $logEntry = "[$timestamp] B2C FAILED: {$disbursement['recipient_name']} - KES {$disbursement['amount']} - Error: $resultDesc\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    $db->commit();

    // Send notification to recipient
    if ($resultCode == 0) {
        sendDisbursementNotification($disbursement, $transactionReceipt, 'completed');
    } else {
        sendDisbursementNotification($disbursement, null, 'failed', $resultDesc);
    }

    // Return success response to M-Pesa
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'B2C result processed successfully'
    ]);

} catch (Exception $e) {
    error_log("M-Pesa B2C Callback Error: " . $e->getMessage());

    // Log error
    $errorEntry = "[$timestamp] ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $errorEntry, FILE_APPEND);

    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }

    // Still return success to M-Pesa to prevent retries
    http_response_code(200);
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Received'
    ]);
}

/**
 * Send disbursement notification
 */
function sendDisbursementNotification($disbursement, $receipt, $status, $error = null)
{
    try {
        $message = '';

        if ($status === 'completed') {
            $message = "Payment Received!\n";
            $message .= "Amount: KES " . number_format($disbursement['amount'], 2) . "\n";
            $message .= "Receipt: $receipt\n";
            $message .= "Thank you.";
        } else {
            $message = "Payment Failed\n";
            $message .= "Amount: KES " . number_format($disbursement['amount'], 2) . "\n";
            $message .= "Reason: $error\n";
            $message .= "Please contact admin.";
        }

        // TODO: Send SMS to recipient
        // sendSMS($disbursement['phone_number'], $message);

    } catch (Exception $e) {
        error_log("Failed to send disbursement notification: " . $e->getMessage());
    }
}
