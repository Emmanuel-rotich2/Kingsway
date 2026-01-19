<?php
/**
 * TEST SCRIPT: Simulate Safaricom Daraja C2B Callback
 * 
 * This script simulates what Safaricom sends when a parent pays school fees
 * Run this via: php scripts/test_c2b_callback.php
 */

// Bootstrap the application
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

echo "=== Simulating Safaricom Daraja C2B Payment ===\n\n";

// Get database connection
$db = Database::getInstance()->getConnection();

// Test payload - simulating a parent paying Ksh 15,000 for student ADM101
$c2bPayload = [
    'TransactionType' => 'Pay Bill',
    'TransID' => 'TRX' . date('YmdHis') . rand(100, 999),  // Unique transaction ID
    'TransTime' => date('YmdHis'),
    'TransAmount' => '15000.00',
    'BusinessShortCode' => '174379',
    'BillRefNumber' => 'ADM101',  // Student admission number
    'InvoiceNumber' => '',
    'OrgAccountBalance' => '850000.00',
    'ThirdPartyTransID' => '',
    'MSISDN' => '254712345678',  // Parent's phone number
    'FirstName' => 'James',
    'MiddleName' => 'Kamau',
    'LastName' => 'Mwangi'
];

echo "C2B Payload:\n";
echo json_encode($c2bPayload, JSON_PRETTY_PRINT) . "\n\n";

// Extract data
$transID = $c2bPayload['TransID'];
$transTime = $c2bPayload['TransTime'];
$transAmount = floatval($c2bPayload['TransAmount']);
$billRefNumber = $c2bPayload['BillRefNumber'];
$msisdn = $c2bPayload['MSISDN'];
$firstName = $c2bPayload['FirstName'];
$middleName = $c2bPayload['MiddleName'];
$lastName = $c2bPayload['LastName'];
$orgAccountBalance = $c2bPayload['OrgAccountBalance'];
$thirdPartyTransID = $c2bPayload['ThirdPartyTransID'];

echo "Processing C2B Confirmation...\n";
echo "- Transaction ID: $transID\n";
echo "- Amount: Ksh " . number_format($transAmount, 2) . "\n";
echo "- Admission No: $billRefNumber\n";
echo "- Phone: $msisdn\n";
echo "- Payer: $firstName $middleName $lastName\n\n";

try {
    // 1. Check if student exists
    echo "Step 1: Looking up student...\n";
    $stmt = $db->prepare("SELECT id, first_name, last_name, status FROM students WHERE admission_no = ?");
    $stmt->execute([$billRefNumber]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        throw new Exception("Student not found: $billRefNumber");
    }

    echo "   ✓ Found student: {$student['first_name']} {$student['last_name']} (ID: {$student['id']})\n\n";
    $studentId = $student['id'];

    // 2. Check for duplicate transaction
    echo "Step 2: Checking for duplicates...\n";
    $stmt = $db->prepare("SELECT id FROM mpesa_transactions WHERE mpesa_code = ?");
    $stmt->execute([$transID]);
    if ($stmt->fetch()) {
        echo "   ⚠ Transaction already exists! Skipping.\n";
        exit(0);
    }
    echo "   ✓ No duplicate found\n\n";

    // 3. Format transaction date
    $transDateTime = DateTime::createFromFormat('YmdHis', $transTime);
    $formattedDate = $transDateTime ? $transDateTime->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

    // 4. Record M-Pesa transaction
    echo "Step 3: Recording M-Pesa transaction...\n";

    // Insert into mpesa_transactions (with new columns)
    $stmt = $db->prepare("
        INSERT INTO mpesa_transactions 
        (mpesa_code, student_id, amount, transaction_date, phone_number, 
         first_name, middle_name, last_name, org_account_balance, 
         bill_ref_number, status, transaction_type, raw_callback, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'processed', 'C2B', ?, NOW())
    ");
    $stmt->execute([
        $transID,
        $studentId,
        $transAmount,
        $formattedDate,
        $msisdn,
        $firstName,
        $middleName,
        $lastName,
        $orgAccountBalance,
        $billRefNumber,
        json_encode($c2bPayload)
    ]);

    $mpesaTxId = $db->lastInsertId();
    echo "   ✓ M-Pesa transaction recorded (ID: $mpesaTxId)\n\n";

    // 5. Process payment using stored procedure
    echo "Step 4: Processing payment via stored procedure...\n";

    // Get parent_id for this student (table is student_parents)
    $stmt = $db->prepare("SELECT parent_id FROM student_parents WHERE student_id = ? LIMIT 1");
    $stmt->execute([$studentId]);
    $parentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $parentId = $parentRow ? $parentRow['parent_id'] : null;
    echo "   - Parent ID: " . ($parentId ?? 'NULL') . "\n";

    // Get system user for automated payments (ID=1 or create one)
    $systemUserId = 1; // Using admin as system user

    // Generate receipt number
    $receiptNo = 'RCP-' . date('YmdHis');

    // sp_process_student_payment expects 9 parameters:
    // p_student_id, p_parent_id, p_amount_paid, p_payment_method, p_reference_no, 
    // p_receipt_no, p_received_by, p_payment_date, p_notes
    // Note: payment_method is ENUM('cash','bank_transfer','mpesa','cheque','other')
    $stmt = $db->prepare("CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $studentId,                              // p_student_id
        $parentId,                               // p_parent_id
        $transAmount,                            // p_amount_paid
        'mpesa',                                 // p_payment_method (must be valid ENUM)
        $transID,                                // p_reference_no
        $receiptNo,                              // p_receipt_no
        $systemUserId,                           // p_received_by
        $formattedDate,                          // p_payment_date
        "M-Pesa C2B payment from $firstName $lastName via $msisdn"  // p_notes
    ]);

    // Fetch the result from the stored procedure
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); // Important: close cursor before next query

    if ($result) {
        echo "   ✓ Payment processed\n";
        echo "   - Result: " . ($result['result'] ?? 'OK') . "\n";
        if (isset($result['payment_id'])) {
            echo "   - Payment ID: {$result['payment_id']}\n";
        }
    } else {
        echo "   ✓ Payment processed (no return data)\n";
    }

    echo "\n✅ C2B Payment completed successfully!\n\n";

    // 7. Verify the records
    echo "=== Verification ===\n";

    echo "\nM-Pesa Transaction Record:\n";
    $stmt = $db->prepare("SELECT * FROM mpesa_transactions WHERE id = ?");
    $stmt->execute([$mpesaTxId]);
    $mpesaRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r([
        'id' => $mpesaRecord['id'],
        'mpesa_code' => $mpesaRecord['mpesa_code'],
        'student_id' => $mpesaRecord['student_id'],
        'amount' => $mpesaRecord['amount'],
        'phone_number' => $mpesaRecord['phone_number'],
        'first_name' => $mpesaRecord['first_name'],
        'last_name' => $mpesaRecord['last_name'],
        'bill_ref_number' => $mpesaRecord['bill_ref_number'],
        'status' => $mpesaRecord['status'],
        'transaction_type' => $mpesaRecord['transaction_type']
    ]);

    echo "\nPayment Transaction Record:\n";
    $stmt = $db->prepare("SELECT id, student_id, amount_paid, payment_method, reference_no, status, payment_date FROM payment_transactions WHERE reference_no = ?");
    $stmt->execute([$transID]);
    $paymentRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($paymentRecord) {
        print_r($paymentRecord);
    } else {
        echo "  (No record found - check if stored procedure creates payment_transactions)\n";
    }

    echo "\nStudent Fee Obligations Updated:\n";
    $stmt = $db->prepare("
        SELECT sfo.id, ft.name as fee_component, sfo.amount_due, sfo.amount_paid, sfo.balance, sfo.status 
        FROM student_fee_obligations sfo
        LEFT JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
        LEFT JOIN fee_types ft ON fsd.fee_type_id = ft.id
        WHERE sfo.student_id = ? 
        ORDER BY sfo.id DESC LIMIT 3
    ");
    $stmt->execute([$studentId]);
    $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($obligations) {
        foreach ($obligations as $o) {
            echo "  - {$o['fee_component']}: Due={$o['amount_due']}, Paid={$o['amount_paid']}, Balance={$o['balance']}, Status={$o['status']}\n";
        }
    } else {
        echo "  (No fee obligations found for student)\n";
    }

    echo "\n=== Response to Safaricom ===\n";
    echo json_encode([
        'ResultCode' => '0',
        'ResultDesc' => 'Payment processed successfully'
    ], JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "\n=== Response to Safaricom ===\n";
    echo json_encode([
        'ResultCode' => '1',
        'ResultDesc' => 'Payment processing failed'
    ], JSON_PRETTY_PRINT) . "\n";
}
