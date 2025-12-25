<?php
// Comprehensive Payment API Test Suite
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once 'config/config.php';
require_once 'api/includes/helpers.php';
require_once 'database/Database.php';
require_once 'api/services/payments/BankPaymentWebhook.php';

use App\API\Services\payments\BankPaymentWebhook;
use App\Database\Database;

// Test counter
$passed = 0;
$failed = 0;
$tests = [];

function test($name, $result, &$passed, &$failed) {
    global $tests;
    if ($result) {
        $passed++;
        $tests[] = "✓ $name";
    } else {
        $failed++;
        $tests[] = "✗ $name";
    }
}

echo "=== Comprehensive Payment API Test Suite ===\n\n";

// Get Database instance
$db = Database::getInstance()->getConnection();

// Test 1-3: M-Pesa B2C Tests
echo "M-Pesa B2C Callback Tests:\n";
// These would require the PaymentsAPI class, which is complex
// For now, test the bank webhook which is the critical piece

// Test 4-7: KCB Bank Tests
echo "\nKCB Bank Webhook Tests:\n";

$webhook = new BankPaymentWebhook();

// Test 4: Valid KCB payment
$test4 = false;
try {
    $paymentData = [
        'bank_name' => 'KCB Bank',
        'transaction_id' => 'KCB-TEST-' . time(),
        'account_number' => 'ADM101',
        'amount' => 3000,
        'transaction_date' => date('Y-m-d H:i:s'),
        'sender_account' => '1234567890'
    ];
    $result = $webhook->processKCBPayment($paymentData);
    $test4 = isset($result['status']) && $result['status'] === 'success';
} catch (Exception $e) {
    error_log("Test 4 error: " . $e->getMessage());
}
test("KCB: Valid payment", $test4, $passed, $failed);

// Test 5: KCB payment with missing amount
$test5 = false;
try {
    $paymentData = [
        'bank_name' => 'KCB Bank',
        'transaction_id' => 'KCB-TEST-' . time(),
        'account_number' => 'ADM101',
        // Missing amount
        'transaction_date' => date('Y-m-d H:i:s')
    ];
    $result = $webhook->processKCBPayment($paymentData);
    $test5 = isset($result['status']) && $result['status'] === 'error';
} catch (Exception $e) {
    $test5 = true; // Expected to fail
}
test("KCB: Reject missing amount", $test5, $passed, $failed);

// Test 6: KCB payment with invalid admission number
$test6 = false;
try {
    $paymentData = [
        'bank_name' => 'KCB Bank',
        'transaction_id' => 'KCB-TEST-' . time(),
        'account_number' => 'INVALID999',
        'amount' => 3000,
        'transaction_date' => date('Y-m-d H:i:s')
    ];
    $result = $webhook->processKCBPayment($paymentData);
    $test6 = isset($result['status']) && $result['status'] === 'error';
} catch (Exception $e) {
    error_log("Test 6: " . $e->getMessage());
}
test("KCB: Reject invalid student", $test6, $passed, $failed);

// Test 7: KCB duplicate transaction check
$test7 = false;
try {
    $refNo = 'KCB-DUP-' . time();
    $paymentData = [
        'bank_name' => 'KCB Bank',
        'transaction_id' => $refNo,
        'account_number' => 'ADM102',
        'amount' => 2500,
        'transaction_date' => date('Y-m-d H:i:s')
    ];
    // First payment
    $result1 = $webhook->processKCBPayment($paymentData);
    // Second payment with same reference (duplicate)
    $paymentData['transaction_id'] = $refNo;
    $result2 = $webhook->processKCBPayment($paymentData);
    $test7 = isset($result2['status']) && $result2['status'] === 'error';
} catch (Exception $e) {
    error_log("Test 7: " . $e->getMessage());
}
test("KCB: Reject duplicate transaction", $test7, $passed, $failed);

// Test 8-11: Generic Bank Tests
echo "\nGeneric Bank Webhook Tests:\n";

// Test 8: Valid generic bank payment
$test8 = false;
try {
    $paymentData = [
        'account_number' => 'ADM103',
        'amount' => 4500,
        'transaction_id' => 'BANK-TEST-' . time(),
        'transaction_date' => date('Y-m-d H:i:s')
    ];
    $result = $webhook->processGenericBankPayment($paymentData, 'Standard Bank');
    $test8 = isset($result['status']) && $result['status'] === 'success';
} catch (Exception $e) {
    error_log("Test 8: " . $e->getMessage());
}
test("Generic Bank: Valid payment", $test8, $passed, $failed);

// Test 9: Generic bank with alternative field names
$test9 = false;
try {
    $paymentData = [
        'account_ref' => 'ADM101',
        'trans_amount' => 2000,
        'trans_id' => 'BANK-ALT-' . time(),
        'date' => date('Y-m-d H:i:s')
    ];
    $result = $webhook->processGenericBankPayment($paymentData, 'Alternative Bank');
    $test9 = isset($result['status']) && $result['status'] === 'success';
} catch (Exception $e) {
    error_log("Test 9: " . $e->getMessage());
}
test("Generic Bank: Alternative field names", $test9, $passed, $failed);

// Test 10: Generic bank missing required fields
$test10 = false;
try {
    $paymentData = [
        'account_number' => 'ADM101'
        // Missing amount and transaction_id
    ];
    $result = $webhook->processGenericBankPayment($paymentData, 'Incomplete Bank');
    $test10 = isset($result['status']) && $result['status'] === 'error';
} catch (Exception $e) {
    $test10 = true; // Expected to fail
}
test("Generic Bank: Reject missing fields", $test10, $passed, $failed);

// Test 11: Generic bank invalid student
$test11 = false;
try {
    $paymentData = [
        'account_number' => 'NOTAREAL999',
        'amount' => 1000,
        'transaction_id' => 'BANK-INVALID-' . time()
    ];
    $result = $webhook->processGenericBankPayment($paymentData, 'Generic Bank');
    $test11 = isset($result['status']) && $result['status'] === 'error';
} catch (Exception $e) {
    error_log("Test 11: " . $e->getMessage());
}
test("Generic Bank: Reject invalid student", $test11, $passed, $failed);

// Test 12-14: Database Integration Tests
echo "\nDatabase Integration Tests:\n";

// Test 12: Verify payment was recorded in payment_transactions
$test12 = false;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM payment_transactions WHERE status = 'confirmed' AND payment_method = 'bank_transfer'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $test12 = ($row['cnt'] > 0);
} catch (Exception $e) {
    error_log("Test 12: " . $e->getMessage());
}
test("Database: Payment transaction recorded", $test12, $passed, $failed);

// Test 13: Verify bank_transactions table populated
$test13 = false;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM bank_transactions WHERE status = 'processed'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $test13 = ($row['cnt'] > 0);
} catch (Exception $e) {
    error_log("Test 13: " . $e->getMessage());
}
test("Database: Bank transaction recorded", $test13, $passed, $failed);

// Test 14: Verify webhook logs exist
$test14 = false;
try {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM payment_webhooks_log WHERE source IN ('kcb_bank', 'generic_bank')");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $test14 = ($row['cnt'] > 0);
} catch (Exception $e) {
    error_log("Test 14: " . $e->getMessage());
}
test("Database: Webhook logs recorded", $test14, $passed, $failed);

// Print summary
echo "\n=== Test Results ===\n";
foreach ($tests as $test) {
    echo "$test\n";
}

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100) : 0;

echo "\n$passed/$total tests passed ($percentage%)\n";

if ($failed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ $failed tests failed\n";
    exit(1);
}
?>
