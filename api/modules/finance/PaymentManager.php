<?php

namespace App\API\Modules\finance;

use App\Database\Database;

use App\API\Services\FinancialPostingCoordinator;
use App\API\Services\payments\FeeLedgerFilter;
use App\API\Services\payments\FinancialAccountService;
use App\API\Services\payments\ReferenceNormalizer;
use App\API\Services\ReadReplicaService;
use App\API\Includes\FileLogger;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Payment Management Class
 * 
 * Handles all payment-related operations:
 * - Payment processing (cash, bank, M-Pesa)
 * - Payment allocation to fee types
 * - Payment reconciliation
 * - Refunds and reversals
 * - Payment tracking and reporting
 * 
 * Integrates with stored procedures:
 * - sp_process_student_payment
 * - sp_allocate_payment
 * - sp_record_cash_payment
 * 
 * Integrates with tables:
 * - payments
 * - mpesa_transactions
 * - bank_transactions
 * - payment_reconciliations
 * - student_fee_obligations
 * - academic_year_fee_schedules
 */
class PaymentManager
{
    private $db;

    /** Whether listStudentPaymentStatus is reading the materialized summary. */
    private bool $feeStatusUsingSummary = false;

    /**
     * Per-request schema/column metadata cache for the fee-status source.
     * Keyed by qualified name so the information_schema lookups happen once.
     */
    private static array $feeSourceMeta = [];

    /** Shared, unit-tested filter rules for the fee ledger. */
    private FeeLedgerFilter $feeFilter;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->feeFilter = new FeeLedgerFilter();
    }

    /**
     * Process a student payment
     * @param array $data Payment data
     * @return array Response with payment_id
     */
    public function processPayment($data)
    {
        $transactionStarted = false;
        try {
            $required = ['student_id', 'amount', 'payment_method'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // The procedure inserts the payment row but does not own a transaction.
            // Keep the payment row, financial-account assignment, ledger posting,
            // and provider detail in one atomic unit.
            $this->db->beginTransaction();
            $transactionStarted = true;

            // Verify student exists
            $stmt = $this->db->prepare("SELECT id FROM students WHERE id = ?");
            $stmt->execute([$data['student_id']]);
            $studentRow = $stmt->fetch();

            if (!$studentRow) {
                $this->db->rollBack();
                $transactionStarted = false;
                return formatResponse(false, null, 'Student not found');
            }

            // Get parent_id from the student_parents relationship table or use NULL if not found
            $parentId = null;
            $stmt = $this->db->prepare("
                SELECT parent_id FROM student_parents 
                WHERE student_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$data['student_id']]);
            $parentRow = $stmt->fetch();
            if ($parentRow) {
                $parentId = $parentRow['parent_id'];
            }

            // Generate receipt number if not provided
            $receiptNo = $data['receipt_no'] ?? 'RCP-' . date('Ymdhis') . '-' . $data['student_id'];

            // Call stored procedure to process payment (requires 9 arguments)
            // The stored procedure handles its own transaction (START TRANSACTION / COMMIT / ROLLBACK)
            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $normalizedReference = (new ReferenceNormalizer())->reference($data['reference_no'] ?? '');
            $stmt->execute([
                $data['student_id'],           // p_student_id
                $parentId,                      // p_parent_id (can be NULL)
                $data['amount'],                // p_amount_paid
                $data['payment_method'],        // p_payment_method ('cash', 'bank', 'mpesa', 'cheque')
                $normalizedReference !== '' ? $normalizedReference : null,  // p_reference_no (canonical account reference)
                $receiptNo,                     // p_receipt_no
                $data['received_by'] ?? 1,      // p_received_by (user_id)
                $data['payment_date'] ?? date('Y-m-d H:i:s'),  // p_payment_date
                $data['notes'] ?? null          // p_notes
            ]);

            // The stored procedure returns the generated payment id in its result set.
            $paymentResult = $stmt->fetch(PDO::FETCH_ASSOC);
            $paymentId = $paymentResult['transaction_id'] ?? null;

            if (!$paymentId) {
                $this->db->rollBack();
                $transactionStarted = false;
                return formatResponse(false, null, 'Payment was processed but ID could not be retrieved');
            }

            // Every operational fee payment must identify the receiving ledger
            // account before it is visible as confirmed financial activity.
            $purpose = strtolower((string)($data['payment_purpose'] ?? 'fees'));
            if (!in_array($purpose, ['fees', 'transport', 'uniforms'], true)) $purpose = 'fees';
            $method = strtolower((string)$data['payment_method']);
            $channel = in_array($method, ['mpesa', 'mpesa_daraja'], true) ? 'mpesa_c2b' : ($method === 'cash' ? 'cash' : 'bank_transfer');
            $source = (new FinancialAccountService($this->db))->requireFor((int)($data['financial_account_id'] ?? 0), $purpose, $channel, false, (int)($data['received_by'] ?? 0));
            $this->db->prepare('UPDATE payments SET financial_account_id=?, payment_purpose=? WHERE id=?')->execute([(int)$source['id'], $purpose, (int)$paymentId]);
            (new FinancialPostingCoordinator($this->db))->postIncoming('payment', (int)$paymentId, (int)$source['id'], $purpose, (string)$data['amount'], (int)($data['received_by'] ?? 0), $normalizedReference ?: null);

            // If M-Pesa payment, record M-Pesa transaction details
            if ($data['payment_method'] === 'mpesa' && !empty($data['mpesa_data'])) {
                $this->recordMpesaTransaction($paymentId, $data['mpesa_data']);
            }

            // If bank payment, record bank transaction details
            $bankMethod = strtolower((string)($data['payment_method'] ?? ''));
            if (in_array($bankMethod, ['bank', 'bank_transfer'], true) && !empty($data['bank_data'])) {
                $bankData = is_array($data['bank_data']) ? $data['bank_data'] : [];
                if (!isset($bankData['student_id'])) {
                    $bankData['student_id'] = $data['student_id'];
                }
                $this->recordBankTransaction($paymentId, $bankData);
            }

            $this->db->commit();
            $transactionStarted = false;

            return formatResponse(true, [
                'payment_id' => $paymentId,
                'message' => 'Payment processed successfully'
            ]);

        } catch (Exception $e) {
            if ($transactionStarted && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Record M-Pesa transaction details
     * @param int $paymentId Payment transaction ID
     * @param array $mpesaData M-Pesa data
     * @return bool Success status
     */
    private function recordMpesaTransaction($paymentId, $mpesaData)
    {
        // mpesa_transactions uses mpesa_code as the unique identifier (no payment_id column)
        // Store payment reference in third_party_trans_id for traceability
        $stmt = $this->db->prepare("
            INSERT INTO mpesa_transactions (
                mpesa_code, phone_number, amount,
                transaction_date, status, third_party_trans_id
            ) VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                third_party_trans_id = VALUES(third_party_trans_id)
        ");

        return $stmt->execute([
            $mpesaData['transaction_id'] ?? $mpesaData['mpesa_code'] ?? 'MPE-' . $paymentId,
            $mpesaData['phone_number'] ?? null,
            $mpesaData['amount'],
            $mpesaData['transaction_date'] ?? date('Y-m-d H:i:s'),
            $mpesaData['status'] ?? 'processed',
            (string) $paymentId  // link back to payments.id
        ]);
    }

    /**
     * Record bank transaction details
     * @param int $paymentId Payment transaction ID
     * @param array $bankData Bank data
     * @return bool Success status
     */
    private function recordBankTransaction($paymentId, $bankData)
    {
        // bank_transactions has no payment_id column; transaction_ref is the unique key
        // student_id is NOT NULL, so it must be set from the surrounding payment context.
        $studentId = $bankData['student_id'] ?? null;

        $stmt = $this->db->prepare("
            INSERT INTO bank_transactions (
                student_id, transaction_ref, amount,
                transaction_date, bank_name, account_number, narration, status,
                source_type
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'manual_entry')
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                narration = VALUES(narration)
        ");

        return $stmt->execute([
            $studentId,
            $bankData['transaction_ref'] ?? 'BNK-' . $paymentId . '-' . time(),
            $bankData['amount'],
            $bankData['transaction_date'] ?? date('Y-m-d H:i:s'),
            $bankData['bank_name'] ?? null,
            $bankData['account_number'] ?? null,
            $bankData['narration'] ?? null,
            $bankData['status'] ?? 'pending'
        ]);
    }

    /**
     * Allocate payment to specific fee types
     * @param int $paymentId Payment transaction ID
     * @param array $allocations Array of allocations
     * @return array Response
     */
    public function allocatePayment($paymentId, $allocations)
    {
        try {
            if (empty($allocations)) {
                return formatResponse(false, null, 'No allocations provided');
            }

            $this->db->beginTransaction();

            // Verify payment exists
            $stmt = $this->db->prepare("
                SELECT id, amount, student_id, status
                FROM payments
                WHERE id = ?
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Payment not found');
            }
            if ($payment['status'] === 'reversed') {
                $this->db->rollBack();
                return formatResponse(false, null, 'A reversed payment cannot be allocated');
            }

            // The live schema represents allocations as the payments rows themselves, so
            // allocation details are recorded against the payment row (notes column).
            $notesStmt = $this->db->prepare("
                UPDATE payments
                SET notes = CONCAT(COALESCE(notes, ''), ?)
                WHERE id = ?
            ");

            $allocated = 0.0;
            $allocationNotes = [];
            foreach ($allocations as $allocation) {
                $amount = (float) ($allocation['amount'] ?? $allocation['amount_allocated'] ?? 0);
                $obligationId = (int) ($allocation['student_fee_obligation_id'] ?? 0);
                if ($amount <= 0 || $obligationId <= 0) {
                    throw new Exception('Each allocation requires a positive amount and student_fee_obligation_id');
                }

                $obligationStmt = $this->db->prepare("
                    SELECT sae.student_id,
                           sfo.academic_year_id,
                           ayt.term_id
                    FROM student_fee_obligations sfo
                    INNER JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
                    LEFT JOIN academic_year_terms ayt ON ayt.id = sfo.academic_year_term_id
                    WHERE sfo.id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $obligationStmt->execute([$obligationId]);
                $obligation = $obligationStmt->fetch(PDO::FETCH_ASSOC);
                if (!$obligation) {
                    throw new Exception("Student fee obligation {$obligationId} not found");
                }

                \App\API\Includes\FileLogger::write('finance', [
                    'type' => 'audit',
                    'action' => 'ALLOCATE_PAYMENT',
                    'entity' => 'payments',
                    'entity_id' => $paymentId,
                    'user_id' => $this->user_id ?? null,
                    'details' => [
                        'student_id' => (int) $obligation['student_id'],
                        'allocated_amount' => $amount,
                        'term_id' => (int) $obligation['term_id'],
                        'academic_year_id' => (int) $obligation['academic_year_id'],
                    ],
                    'status' => 'success',
                ]);

                $allocationNotes[] = sprintf(
                    'Allocation: obligation %d amount %s (by %s)%s',
                    $obligationId,
                    number_format($amount, 2),
                    $this->user_id ?? 'system',
                    (isset($allocation['notes']) && $allocation['notes'] !== null && $allocation['notes'] !== '')
                        ? ' | ' . $allocation['notes']
                        : ''
                );
                $allocated += $amount;
            }

            if ($allocated > (float) $payment['amount']) {
                throw new Exception('Allocation total exceeds payment amount');
            }

            if (!empty($allocationNotes)) {
                $notesStmt->execute(["\n" . implode("\n", $allocationNotes), $paymentId]);
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Payment allocated successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get payment details
     * @param int $paymentId Payment ID
     * @return array Response with payment data
     */
    public function getPayment($paymentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT p.id,
                       p.student_id,
                       p.receipt_no,
                       p.amount,
                       p.method AS payment_method,
                       p.reference AS reference_no,
                       p.payment_date,
                       p.parent_id,
                       p.received_by,
                       p.status,
                       p.notes,
                       p.created_at,
                       p.updated_at,
                       s.admission_no,
                       CONCAT(ps.first_name, ' ', ps.last_name) as student_name,
                       u.username as received_by_name
                FROM " . ReadReplicaService::qualifiedRef("payments") . " p
                INNER JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON p.student_id = s.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " ps ON ps.id = s.person_id
                LEFT JOIN users u ON p.received_by = u.id
                WHERE p.id = ?
            ");

            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                return formatResponse(false, null, 'Payment not found');
            }

            // The live schema has no allocation table; a payment row is itself
            // the allocation, so derive a single allocation entry from it.
            $payment['allocations'] = [[
                'payment_transaction_id' => $payment['id'],
                'student_fee_obligation_id' => null,
                'amount_allocated' => $payment['amount'],
                'allocated_by' => $payment['received_by'],
                'notes' => $payment['notes'],
                'fee_structure_detail_id' => null,
                'fee_type_id' => null,
                'fee_type_name' => null,
                'fee_type_code' => null,
            ]];

            // Get M-Pesa details if applicable
            if ($payment['payment_method'] === 'mpesa') {
                $stmt = $this->db->prepare("
                    SELECT * FROM mpesa_transactions WHERE student_id = ? ORDER BY transaction_date DESC LIMIT 1
                ");
                $stmt->execute([$payment['student_id']]);
                $payment['mpesa_details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Get bank details if applicable
            if ($payment['payment_method'] === 'bank_transfer') {
                $stmt = $this->db->prepare("
                    SELECT * FROM bank_transactions WHERE student_id = ? ORDER BY transaction_date DESC LIMIT 1
                ");
                $stmt->execute([$payment['student_id']]);
                $payment['bank_details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return formatResponse(true, $payment);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * List payments with filters
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Response with payments list
     */
    public function listPayments($filters = [], $page = 1, $limit = 20)
    {
        try {
            $page = max(1, (int) ($filters['page'] ?? $page));
            $limit = max(1, min(500, (int) ($filters['limit'] ?? $limit)));
            $offset = ($page - 1) * $limit;

            $baseSql = "
                FROM payments pt
                INNER JOIN students s ON s.id = pt.student_id
                LEFT JOIN persons ps ON ps.id = s.person_id
                LEFT JOIN academic_years ay ON pt.payment_date BETWEEN ay.start_date AND ay.end_date
                LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id
                    AND pt.payment_date BETWEEN ayt.opening_date AND ayt.closing_date
                LEFT JOIN terms t ON t.id = ayt.term_id
                WHERE 1 = 1
            ";
            $params = [];

            if (!empty($filters['student_id'])) {
                $baseSql .= " AND pt.student_id = ?";
                $params[] = (int) $filters['student_id'];
            }

            if (!empty($filters['academic_year'])) {
                $baseSql .= " AND ay.id = ?";
                $params[] = (int) $filters['academic_year'];
            }

            if (!empty($filters['payment_method'])) {
                $baseSql .= " AND pt.method = ?";
                $params[] = $filters['payment_method'];
            }

            if (!empty($filters['status'])) {
                $baseSql .= " AND pt.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $baseSql .= " AND pt.payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $baseSql .= " AND pt.payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $baseSql .= " AND (
                    s.admission_no LIKE ?
                    OR CONCAT_WS(' ', ps.first_name, ps.middle_name, ps.last_name) LIKE ?
                    OR pt.reference LIKE ?
                    OR pt.receipt_no LIKE ?
                )";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }

            $listSql = "
                SELECT
                    pt.id,
                    pt.student_id,
                    s.admission_no AS student_no,
                    CONCAT_WS(' ', ps.first_name, ps.middle_name, ps.last_name) AS student_name,
                    pt.amount AS amount,
                    pt.payment_date AS transaction_date,
                    pt.method AS payment_method,
                    pt.reference AS transaction_ref,
                    pt.receipt_no,
                    pt.status,
                    pt.notes AS details,
                    ayt.term_id AS term_id,
                    t.name AS term_name,
                    t.id AS term_number,
                    ay.id AS academic_year,
                    pt.created_at,
                    pt.updated_at
                {$baseSql}
                ORDER BY pt.payment_date DESC, pt.id DESC
                LIMIT ? OFFSET ?
            ";
            $listParams = array_merge($params, [$limit, $offset]);
            $stmt = $this->db->prepare($listSql);
            $stmt->execute($listParams);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $countSql = "SELECT COUNT(*) AS total {$baseSql}";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

            $summarySql = "
                SELECT
                    COALESCE(SUM(pt.amount), 0) AS total_amount,
                    COALESCE(SUM(CASE WHEN pt.status = 'pending' THEN pt.amount ELSE 0 END), 0) AS pending_amount,
                    COALESCE(SUM(CASE WHEN pt.status = 'confirmed' THEN pt.amount ELSE 0 END), 0) AS confirmed_amount,
                    COALESCE(SUM(CASE WHEN DATE(pt.payment_date) = CURDATE() AND pt.status = 'confirmed' THEN pt.amount ELSE 0 END), 0) AS today_amount
                {$baseSql}
            ";
            $summaryStmt = $this->db->prepare($summarySql);
            $summaryStmt->execute($params);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [
                'total_amount' => 0,
                'pending_amount' => 0,
                'confirmed_amount' => 0,
                'today_amount' => 0
            ];

            return formatResponse(true, [
                'payments' => $payments,
                'summary' => [
                    'total_amount' => (float) ($summary['total_amount'] ?? 0),
                    'pending_amount' => (float) ($summary['pending_amount'] ?? 0),
                    'confirmed_amount' => (float) ($summary['confirmed_amount'] ?? 0),
                    'today_amount' => (float) ($summary['today_amount'] ?? 0)
                ],
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => $limit > 0 ? (int) ceil($total / $limit) : 0
                ]
            ]);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Reverse/refund a payment
     * @param int $paymentId Payment ID
     * @param array $data Reversal data
     * @return array Response
     */
    public function reversePayment($paymentId, $data)
    {
        try {
            $required = ['reason', 'reversed_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Verify payment exists and is not already reversed
            $stmt = $this->db->prepare("
                SELECT *
                FROM payments
                WHERE id = ?
                  AND status != 'reversed'
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Payment not found or already reversed');
            }

            // Update payment status to reversed.
            // payments has no reversal_reason/reversed_by/reversed_at columns.
            // Store reversal context in the notes column.
            $reversalNote = sprintf(
                '[REVERSED] Reason: %s | By: %s | At: %s',
                $data['reason'],
                $data['reversed_by'],
                date('Y-m-d H:i:s')
            );
            $stmt = $this->db->prepare("
                UPDATE payments
                SET status = 'reversed',
                    notes = CONCAT(COALESCE(notes,''), ?)
                WHERE id = ?
            ");

            $stmt->execute(["\n" . $reversalNote, $paymentId]);

            // Reversing the payment status is sufficient: the fee balance views
            // (vw_student_fee_balances) only count payments with a confirmed
            // status, so a reversed payment no longer contributes to amount_paid.
            $this->db->commit();

            return formatResponse(true, ['message' => 'Payment reversed successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Reconcile payments with bank statement
     * @param array $data Reconciliation data
     * @return array Response
     */
    public function reconcilePayments($data)
    {
        try {
            $required = ['reconciliation_date', 'bank_statement_file'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $reconciliationIds = [];

            // payment_reconciliations schema: (id, transaction_id FK school_transactions,
            //   reconciled_by, reconciled_at, bank_statement_ref, notes)
            // Record one entry per matched payment via school_transactions.
            if (!empty($data['matches'])) {
                $insertStmt = $this->db->prepare("
                    INSERT IGNORE INTO payment_reconciliations
                        (transaction_id, reconciled_by, bank_statement_ref, notes)
                    SELECT st.id, ?, ?, ?
                    FROM school_transactions st
                    WHERE st.reference = (
                        SELECT reference FROM payments WHERE id = ? LIMIT 1
                    )
                    LIMIT 1
                ");

                foreach ($data['matches'] as $match) {
                    $insertStmt->execute([
                        $data['reconciled_by'] ?? null,
                        $data['bank_statement_file'] ?? null,
                        $data['notes'] ?? null,
                        $match['payment_id']
                    ]);
                    if ($this->db->lastInsertId()) {
                        $reconciliationIds[] = $this->db->lastInsertId();
                    }

                    // Mark payment as confirmed once reconciled
                    $this->db->prepare(
                        "UPDATE payments SET status = 'confirmed' WHERE id = ? AND status = 'pending'"
                    )->execute([$match['payment_id']]);
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'reconciliation_ids' => $reconciliationIds,
                'message' => 'Payments reconciled successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get payment summary statistics
     * @param array $filters Filter criteria
     * @return array Response with summary data
     */
    public function getPaymentSummary($filters = [])
    {
        try {
            $amountExpr = 'COALESCE(p.amount, 0)';

            $sql = "SELECT 
                        COUNT(*) as total_transactions,
                        SUM($amountExpr) as total_amount,
                        AVG($amountExpr) as average_amount,
                        p.method AS payment_method,
                        COUNT(CASE WHEN p.status = 'confirmed' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN p.status = 'reversed' THEN 1 END) as reversed_count
                    FROM payments p
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM academic_years ay
                    WHERE ay.id = ? AND p.payment_date BETWEEN ay.start_date AND ay.end_date
                )";
                $params[] = (int) $filters['academic_year'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND p.payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND p.payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " GROUP BY p.method";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get overall totals
            $totalAmount = array_sum(array_column($summary, 'total_amount'));
            $totalTransactions = array_sum(array_column($summary, 'total_transactions'));

            return formatResponse(true, [
                'by_payment_method' => $summary,
                'overall' => [
                    'total_amount' => $totalAmount,
                    'total_transactions' => $totalTransactions,
                    'average_transaction' => $totalTransactions > 0 ? $totalAmount / $totalTransactions : 0
                ]
            ]);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Record cash payment using stored procedure
     * @param array $data Cash payment data
     * @return array Response
     */
    public function recordCashPayment($data)
    {
        try {
            throw new Exception('Cash payments are not supported for school fees. Use bank payment, M-Pesa C2B, Lipa Karo or STK Push.');
            $required = ['student_id', 'amount', 'received_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $source = (new FinancialAccountService($this->db))->requireFor((int)($data['financial_account_id'] ?? 0), 'fees', 'cash');
            $stmt = $this->db->prepare("CALL sp_record_cash_payment_v2(?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['student_id'],
                $data['amount'],
                $data['payment_method'] ?? 'cash',
                $data['payment_date'] ?? date('Y-m-d H:i:s'),
                (int)$source['id'],
                (int)$data['received_by'],
                $data['reference'] ?? ('CASH-' . date('YmdHis') . '-' . bin2hex(random_bytes(3))),
                'fees'
            ]);
            $created = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stmt->closeCursor();
            if (empty($created['payment_id'])) throw new Exception('Cash payment was not created.');
            (new FinancialPostingCoordinator($this->db))->postIncoming('payment',(int)$created['payment_id'],(int)$source['id'],'fees',(string)$data['amount'],(int)$data['received_by'],$data['reference'] ?? null);

            $this->db->commit();

            return formatResponse(true, [
                'message' => 'Cash payment recorded successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get parent payment activity
     * @param int $parentId Parent ID
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getParentPaymentActivity($parentId, $filters = [])
    {
        try {
            $sql = "SELECT
                        p.id,
                        p.student_id,
                        p.receipt_no,
                        p.amount,
                        p.payment_date,
                        p.method AS payment_method,
                        p.reference,
                        p.status,
                        p.notes,
                        s.admission_no,
                        CONCAT(ps.first_name, ' ', ps.last_name) AS student_name,
                        ay.id AS academic_year,
                        ayt.term_id AS term_id,
                        t.name AS term_name
                    FROM " . ReadReplicaService::qualifiedRef("payments") . " p
                    INNER JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON p.student_id = s.id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " ps ON ps.id = s.person_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON p.payment_date BETWEEN ay.start_date AND ay.end_date
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_terms") . " ayt ON ayt.academic_year_id = ay.id
                        AND p.payment_date BETWEEN ayt.opening_date AND ayt.closing_date
                    LEFT JOIN terms t ON t.id = ayt.term_id
                    WHERE p.parent_id = ?";
            $params = [$parentId];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND ay.id = ?";
                $params[] = (int) $filters['academic_year'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND p.payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND p.payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY p.payment_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'activity' => $activity,
                'total_payments' => count($activity),
                'total_amount' => array_sum(array_column($activity, 'amount'))
            ]);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Split "schema.table" into its two parts. A bare name keeps the current
     * schema, which is what unqualified view references rely on.
     */
    private static function splitQualified(string $qualified): array
    {
        $parts = explode('.', $qualified, 2);
        if (count($parts) === 2) {
            return [$parts[0], $parts[1]];
        }
        return ['', $parts[0]];
    }

    /**
     * Real column list for a table/view, cached per qualified name.
     */
    private function sourceColumns(string $qualified): array
    {
        if (isset(self::$feeSourceMeta[$qualified]['columns'])) {
            return self::$feeSourceMeta[$qualified]['columns'];
        }
        [$schema, $table] = self::splitQualified($qualified);
        $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                WHERE TABLE_NAME = ?";
        $args = [$table];
        if ($schema !== '') {
            $sql .= " AND TABLE_SCHEMA = ?";
            $args[] = $schema;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($args);
        $columns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'COLUMN_NAME');
        self::$feeSourceMeta[$qualified]['columns'] = $columns;
        return $columns;
    }

    /**
     * The collation a column actually uses, resolved from the catalog.
     *
     * The enhanced view inherits utf8mb4_general_ci from its base tables while
     * the connection speaks utf8mb4_unicode_ci, so a plain `column = ?`
     * comparison dies with SQLSTATE 1267 "Illegal mix of collations". Every
     * string predicate in this class therefore pins BOTH sides to the column's
     * own collation instead of guessing one.
     */
    private function columnCollation(string $qualified, string $column): ?string
    {
        $key = $qualified . '.' . $column . '.coll';
        if (array_key_exists($key, self::$feeSourceMeta)) {
            return self::$feeSourceMeta[$key];
        }
        [$schema, $table] = self::splitQualified($qualified);
        $sql = "SELECT COLLATION_NAME FROM information_schema.COLUMNS
                WHERE TABLE_NAME = ? AND COLUMN_NAME = ?";
        $args = [$table, $column];
        if ($schema !== '') {
            $sql .= " AND TABLE_SCHEMA = ?";
            $args[] = $schema;
        }
        $collation = null;
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($args);
            $found = $stmt->fetchColumn();
            // Only report a pin-worthy collation when the column has drifted
            // away from the schema standard. Pinning a conforming column with
            // an explicit COLLATE has coercibility 0 and disables index use —
            // the exact full-table-scan trap the 2026-10-04 collation
            // standardization migration removed.
            if (is_string($found)
                && preg_match('/^[A-Za-z0-9_]+$/', $found)
                && $found !== 'utf8mb4_unicode_ci') {
                $collation = $found;
            }
        } catch (\Throwable $e) {
            $collation = null;
        }
        self::$feeSourceMeta[$key] = $collation;
        return $collation;
    }

    /**
     * Build a placeholder-pinned equality/prefix match for a text column.
     *
     * COLLATE is applied to the COLUMN ONLY and never to a placeholder: MySQL
     * cannot resolve a parameter's character set before bind time, so `?
     * COLLATE x` fails at prepare with error 1253. A column carrying an
     * explicit COLLATE has coercibility 0 and therefore dominates the
     * comparison, which is exactly what removes the 1267 "illegal mix".
     */
    private function textMatch(
        string $qualified,
        string $column,
        string $placeholder,
        string $value
    ): array {
        $collation = $this->columnCollation($qualified, $column);
        $left = $column;
        if ($collation !== null) {
            $left .= " COLLATE " . $collation;
        }
        return [$left . " = " . $placeholder, [$value]];
    }

    /**
     * Same as textMatch but for case-insensitive equality: pins the column's
     * collation and lowercases both sides.
     */
    private function ciMatch(string $qualified, string $column, string $value): string
    {
        $collation = $this->columnCollation($qualified, $column);
        $col = $column . ($collation !== null ? " COLLATE " . $collation : '');
        return "LOWER({$col}) = LOWER(?)";
    }

    /**
     * A text column pinned to its own collation, for use inside LIKE.
     */
    private function pinned(string $qualified, string $column): string
    {
        $collation = $this->columnCollation($qualified, $column);
        return $column . ($collation !== null ? " COLLATE " . $collation : '');
    }

    /**
     * Resolve the fee-status source and normalise the difference between the
     * two shapes the workspace can read.
     *
     * vw_student_payment_status_enhanced  -> key column `id`
     * mmv_fee_status_summary (projection) -> key column `student_id`, plus
     *                                       class_id / level_id / stream_id
     *
     * The projection is preferred because it is millisecond-fast, but the two
     * do NOT expose identical columns, so every filter below is written
     * against the resolved source instead of assuming one of them.
     */
    private function feeStatusSource(): array
    {
        $candidates = [];

        // The materialized target lives in the reads namespace — an
        // unqualified reference resolves against the master schema, which has
        // no such table, and silently degraded every read to the slow view.
        if (ReadReplicaService::isOffloaded('fee_status_summary')) {
            $candidates[] = \App\Database\ConnectionManager::schemaFor(\App\Database\ConnectionManager::NS_READS)
                . '.' . ReadReplicaService::table('fee_status_summary');
        }
        $candidates[] = 'vw_student_payment_status_enhanced';

        foreach ($candidates as $qualified) {
            try {
                $columns = $this->sourceColumns($qualified);
                if ($columns === []) {
                    continue;
                }
                $idColumn = in_array('student_id', $columns, true) ? 'student_id' : 'id';
                if (!in_array($idColumn, $columns, true)) {
                    continue;
                }
                if ($qualified !== 'vw_student_payment_status_enhanced') {
                    // Only trust a fresh, published projection. An empty table
                    // degrades to the live view without a full COUNT(*) scan.
                    // A COUNT(*) probe scanned the entire read model for every
                    // fee-page request. Only emptiness is needed here.
                    $probe = $this->db->query("SELECT 1 FROM " . $qualified . " LIMIT 1");
                    if ($probe->fetchColumn() === false) {
                        continue;
                    }
                }
                $this->feeStatusUsingSummary = $qualified !== 'vw_student_payment_status_enhanced';
                return [
                    'qualified' => $qualified,
                    'columns' => $columns,
                    'id_column' => $idColumn,
                    'class_id_column' => in_array('class_id', $columns, true) ? 'class_id' : null,
                    'is_projection' => $qualified !== 'vw_student_payment_status_enhanced',
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        throw new Exception('No readable fee-status source is available.');
    }

    /**
     * The select list that gives both sources one stable response contract:
     * `id` is ALWAYS the learner id, whatever the underlying key is called.
     */
    private function feeStatusSelectList(array $source): string
    {
        $parts = [$source['id_column'] . ' AS id'];
        if ($source['id_column'] !== 'student_id') {
            $parts[] = $source['id_column'] . ' AS student_id';
        }
        foreach ($source['columns'] as $column) {
            if ($column === $source['id_column']) {
                continue;
            }
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
                continue;
            }
            $parts[] = $column;
        }
        return implode(', ', array_unique($parts));
    }

    /**
     * Resolve the caller's academic-year filter (id, "2026/2027" or "2026")
     * to a stored year code. Returns null when no year filter was requested.
     */
    private function resolveAcademicYearFilter($input): ?string
    {
        $year = $this->feeFilter->academicYear($input);
        return $year === '' ? null : $year;
    }

    /**
     * Term numbers are stored as the numeric part of terms.code ('T3' -> 3).
     * Returns null for an absent scope and throws for an unparseable value so
     * a broken filter is reported instead of quietly returning everything.
     */
    private function resolveTermFilter($input): ?int
    {
        return $this->feeFilter->term($input);
    }

    /**
     * Get student payment status using enhanced view
     * @param int $studentId Student ID
     * @return array Response
     */
    public function listStudentPaymentStatus($filters = [])
    {
        $startedAt = microtime(true);
        try {
            // The fee-workspace scalability fix: the materialized summary
            // (synced by the 5-minute projection worker) reads in ~2ms where
            // the enhanced view measured ~515ms at 454 learners and would
            // reach ~1.5s at 1000+. The view stays as the fallback so a
            // not-yet-synced projection degrades to correct live data.
            $source = $this->feeStatusSource();
            $qualified = $source['qualified'];
            $baseSql = "FROM " . $qualified . " WHERE 1=1";
            $params = [];
            $termParamIndex = null;
            $yearPrefixMode = false;

            if (!empty($filters['student_id'])) {
                $baseSql .= " AND " . $source['id_column'] . " = ?";
                $params[] = (int) $filters['student_id'];
            }

            if (!empty($filters['academic_year'])) {
                // The UI may submit an academic-year id, year code (2026/2027),
                // or a display year (2026). Resolve all forms to year_code.
                $yearInput = trim((string) $filters['academic_year']);
                $yearStmt = $this->db->prepare(
                    "SELECT year_code FROM academic_years
                     WHERE id = ? OR year_code = ? OR year_name = ?
                     ORDER BY id DESC LIMIT 1"
                );
                $yearStmt->execute([ctype_digit($yearInput) ? (int) $yearInput : 0, $yearInput, $yearInput]);
                $resolvedYear = $yearStmt->fetchColumn();
                if ($resolvedYear === false && preg_match('/^\\d{4}$/', $yearInput)) {
                    // "2026" means the academic year that starts in 2026.
                    $yearCol = $this->pinned($qualified, 'academic_year');
                    $baseSql .= " AND (" . $yearCol . " = ? OR " . $yearCol . " LIKE ?)";
                    $params[] = $yearInput;
                    $params[] = $yearInput . '/%';
                    $yearPrefixMode = true;
                } else {
                    $baseSql .= " AND " . $this->textMatch(
                        $qualified,
                        'academic_year',
                        '?',
                        $resolvedYear !== false ? (string) $resolvedYear : $yearInput
                    )[0];
                    $params[] = $resolvedYear !== false ? (string) $resolvedYear : $yearInput;
                }
            }

            if (!empty($filters['term_number'])) {
                // Track the index so the annual query can drop exactly this
                // placeholder's value — dropping only the SQL fragment left a
                // dangling param and PDO raised HY093.
                $termParamIndex = count($params);
                $params[] = (string) $this->resolveTermFilter($filters['term_number']);
                $baseSql .= " AND term_number = ?";
            }

            if (!empty($filters['status'])) {
                $status = $this->feeFilter->status($filters['status']);
                // Case-insensitive on BOTH sides, pinned to the column's own
                // collation, so this cannot die on 1267 the way a bare
                // `payment_status = ?` did.
                $baseSql .= " AND " . $this->ciMatch($qualified, 'payment_status', $status);
                $params[] = $status;
            }

            if (!empty($filters['class_id'])) {
                $classId = (int) $filters['class_id'];
                if ($source['class_id_column'] !== null) {
                    $baseSql .= " AND " . $source['class_id_column'] . " = ?";
                    $params[] = $classId;
                } else {
                    // The view has no class_id; resolve the name and match the
                    // stored "Class - Stream" label instead.
                    [$nameSql, $nameParams] = $this->classNamePredicate($qualified, $classId);
                    if ($nameSql === null) {
                        throw new Exception('Unknown class filter: ' . $classId);
                    }
                    $baseSql .= " AND " . $nameSql;
                    $params = array_merge($params, $nameParams);
                }
            } elseif (!empty($filters['class_name'])) {
                $className = trim((string) $filters['class_name']);
                [$nameSql, $nameParams] = $this->classLabelPredicate($qualified, $className);
                $baseSql .= " AND " . $nameSql;
                $params = array_merge($params, $nameParams);
            }

            if (!empty($filters['search'])) {
                $search = '%' . $filters['search'] . '%';
                $admCol = $this->pinned($qualified, 'admission_no');
                $nameCol = $this->pinned($qualified, 'student_name');
                $baseSql .= " AND (" . $admCol . " LIKE ? OR " . $nameCol . " LIKE ?)";
                $params[] = $search;
                $params[] = $search;
            }

            if (!empty($filters['balance_only'])) {
                $baseSql .= " AND current_balance > 0";
            }

            if (!empty($filters['amount_range'])) {
                if (preg_match('/^(\d+)\s*-\s*(\d+)$/', (string) $filters['amount_range'], $m)) {
                    $baseSql .= " AND current_balance BETWEEN ? AND ?";
                    $params[] = (float) $m[1];
                    $params[] = (float) $m[2];
                } elseif (preg_match('/^(\d+)\+$/', (string) $filters['amount_range'], $m)) {
                    $baseSql .= " AND current_balance >= ?";
                    $params[] = (float) $m[1];
                }
            }

            $page = max(1, (int) ($filters['page'] ?? 1));
            $limit = (int) ($filters['limit'] ?? 25);
            if ($limit < 1) {
                $limit = 25;
            }
            if ($limit > 500) {
                $limit = 500;
            }
            $offset = ($page - 1) * $limit;

            // Count and totals shared the same filtered view scan. Combine
            // them so the source is evaluated once for pagination and summary.
            $summarySql = "SELECT COUNT(*) AS total, COALESCE(SUM(total_due), 0) AS total_due, "
                . "COALESCE(SUM(total_paid), 0) AS total_paid, "
                . "COALESCE(SUM(current_balance), 0) AS total_balance "
                . $baseSql;
            $summaryStmt = $this->db->prepare($summarySql);
            $summaryStmt->execute($params);
            $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $total = (int) ($summaryRow['total'] ?? 0);

            $listSql = "SELECT " . $this->feeStatusSelectList($source) . " " . $baseSql
                . " ORDER BY admission_no ASC, academic_year DESC, term_number DESC LIMIT ? OFFSET ?";
            $listParams = array_merge($params, [$limit, $offset]);
            $stmt = $this->db->prepare($listSql);
            $stmt->execute($listParams);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // The annual view of the SAME filter set with the term scope
            // removed. The whole-year position and the per-term breakdown must
            // stay available even when the user has drilled into one term —
            // that is the annual balance and the term-on-term comparison the
            // workspace is for.
            $annualParams = $params;
            $annualBaseSql = preg_replace("/ AND term_number = \?/", '', $baseSql);
            if (isset($termParamIndex)) {
                unset($annualParams[$termParamIndex]);
                $annualParams = array_values($annualParams);
            }

            $annualStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(total_due), 0) AS total_due,"
                . " COALESCE(SUM(total_paid), 0) AS total_paid,"
                . " COALESCE(SUM(current_balance), 0) AS total_balance"
                . " " . $annualBaseSql
            );
            $annualStmt->execute($annualParams);
            $annualRow = $annualStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Per-term breakdown of the whole filtered year (term scope
            // removed) so the progress strip and the "Term N" lines always
            // describe the year, not just the selected term.
            $periodSql = "SELECT term_number,"
                . " COALESCE(SUM(total_due), 0) AS total_due,"
                . " COALESCE(SUM(total_paid), 0) AS total_paid,"
                . " COALESCE(SUM(current_balance), 0) AS total_balance"
                . " " . $annualBaseSql . " GROUP BY term_number ORDER BY term_number";
            $periodStmt = $this->db->prepare($periodSql);
            $periodStmt->execute($annualParams);
            $termRows = $periodStmt->fetchAll(PDO::FETCH_ASSOC);

            $terms = [];
            foreach ($termRows as $termRow) {
                $tDue = (float) $termRow['total_due'];
                $tPaid = (float) $termRow['total_paid'];
                $terms[] = [
                    'term_number' => (int) $termRow['term_number'],
                    'label' => 'Term ' . (int) $termRow['term_number'],
                    'total_due' => $tDue,
                    'total_paid' => $tPaid,
                    'total_balance' => (float) $termRow['total_balance'],
                    'collection_rate' => $tDue > 0 ? round(($tPaid / $tDue) * 100, 2) : 0,
                ];
            }

            $totalDue = (float) ($summaryRow['total_due'] ?? 0);
            $totalPaid = (float) ($summaryRow['total_paid'] ?? 0);
            $totalBalance = (float) ($summaryRow['total_balance'] ?? 0);
            $collectionRate = $totalDue > 0 ? round(($totalPaid / $totalDue) * 100, 2) : 0;

            // Everything from the academic-year context tables — the source of
            // truth. Every year has its own term rows, amounts and settings, so
            // many years with different calendars coexist on the same system
            // without overwriting or duplicating. Nothing is hardcoded.
            $contextYear = (isset($resolvedYear) && $resolvedYear !== false && $resolvedYear !== null && $resolvedYear !== '')
                ? (string) $resolvedYear
                : (string) ($this->db->query(
                    "SELECT year_code FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
                )->fetchColumn() ?: '');
            $currentTermNumber = 0;
            $hasCurrentTerm = false;
            if ($contextYear !== '') {
                $academicTerms = ReadReplicaService::qualifiedRef('academic_term');
                $ctxStmt = $this->db->prepare(
                    "SELECT CAST(SUBSTRING(term_code, 2) AS UNSIGNED)
                     FROM {$academicTerms}
                     WHERE year_code = ? AND term_period_status = 'current'
                     LIMIT 1"
                );
                $ctxStmt->execute([$contextYear]);
                $currentTermNumber = (int) ($ctxStmt->fetchColumn() ?: 0);
                $hasCurrentTerm = $currentTermNumber > 0;
                if (!$hasCurrentTerm) {
                    // No term is marked current for this year (a configured but
                    // not-yet-opened year) — fall back to the latest term that
                    // actually has fee rows, from the data, never assumed.
                    $latestStmt = $this->db->prepare(
                        "SELECT MAX(CAST(SUBSTRING(term_code, 2) AS UNSIGNED))
                         FROM {$academicTerms}
                         WHERE year_code = ?"
                    );
                    $latestStmt->execute([$contextYear]);
                    $currentTermNumber = (int) ($latestStmt->fetchColumn() ?: 0);
                }
            }

            $annualDue = (float) ($annualRow['total_due'] ?? 0);
            $annualPaid = (float) ($annualRow['total_paid'] ?? 0);
            $termScope = $this->resolveTermFilter($filters['term_number'] ?? '');

            $response = formatResponse(true, [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ],
                'summary' => [
                    'total_due' => $totalDue,
                    'total_paid' => $totalPaid,
                    'total_balance' => $totalBalance,
                    'collection_rate' => $collectionRate,
                    // Specific period figures: the annual position of the
                    // filtered year plus the per-term breakdown.
                    'annual' => [
                        'total_due' => $annualDue,
                        'total_paid' => $annualPaid,
                        'total_balance' => (float) ($annualRow['total_balance'] ?? 0),
                        'collection_rate' => $annualDue > 0 ? round(($annualPaid / $annualDue) * 100, 2) : 0,
                    ],
                    'terms' => $terms,
                    'term_scope' => $termScope,
                    'period_label' => $termScope === null
                        ? 'Whole Year'
                        : 'Term ' . $termScope,
                    // Everything from the academic-year context tables — the
                    // source of truth. Every year has its own term rows,
                    // amounts and settings, so many years with different
                    // calendars coexist on the same system without overwriting
                    // or duplicating. Nothing is hardcoded.
                    'academic_year' => $contextYear,
                    'current_term_number' => $currentTermNumber,
                    'has_current_term' => $hasCurrentTerm,
                ],
                // Which source answered, and how each filter was actually applied. The page
                // shows this instead of guessing, so an empty result is always
                // explainable ("you asked for Term 9" or "no rows matched
                // Grade 8") instead of silently looking like an empty ledger.
                'meta' => [
                    'source' => $this->feeStatusUsingSummary ? 'fee_status_summary_projection' : 'vw_student_payment_status_enhanced',
                    'filters_applied' => [
                        'student_id' => isset($filters['student_id']) ? (int) $filters['student_id'] : null,
                        'academic_year' => $yearPrefixMode
                            ? trim((string) ($filters['academic_year'] ?? '')) . '*'
                            : ($resolvedYear ?? null),
                        'term_number' => $termScope,
                        'status' => !empty($filters['status']) ? $this->feeFilter->status($filters['status']) : null,
                        'class_id' => isset($filters['class_id']) ? (int) $filters['class_id'] : null,
                        'class_name' => !empty($filters['class_name']) ? trim((string) $filters['class_name']) : null,
                        'search' => !empty($filters['search']) ? $filters['search'] : null,
                    ],
                ]
            ]);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            if ($durationMs >= max(50, (int) (getenv('DB_SLOW_QUERY_MS') ?: 250))) {
                FileLogger::write('database_performance', [
                    'event' => 'slow_read_workflow',
                    'module' => 'finance',
                    'workflow' => 'list_student_payment_status',
                    'source' => $this->feeStatusUsingSummary ? 'fee_status_summary_projection' : 'vw_student_payment_status_enhanced',
                    'duration_ms' => $durationMs,
                    'filter_names' => array_values(array_intersect(
                        ['student_id', 'academic_year', 'term_number', 'status', 'class_id', 'class_name', 'search', 'balance_only', 'amount_range'],
                        array_keys((array) $filters)
                    )),
                ], 'warning');
            }
            return $response;
        } catch (Exception $e) {
            FileLogger::write('database_performance', [
                'event' => 'read_workflow_failed',
                'module' => 'finance',
                'workflow' => 'list_student_payment_status',
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error_class' => get_class($e),
            ], 'error');
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            // Never let a bad filter or a broken source masquerade as an empty
            // ledger: mapMessageToCode() would turn this generic message into
            // 400, which reads as "you asked wrongly" and hides a real fault.
            return [
                'status' => 'error',
                'message' => 'The fee ledger could not be read: ' . $e->getMessage(),
                'type' => 'error',
                'code' => 500,
                'data' => null,
            ];
        }
    }

    /**
     * Match the stored "Class - Stream" label for a bare class name.
     * "Grade 8" must find "Grade 8 - A"; an exact label must still work.
     */
    private function classLabelPredicate(string $qualified, string $className): array
    {
        $col = $this->pinned($qualified, 'class_name');
        $sql = "({$col} = ? OR {$col} LIKE ?)";
        $params = [$className, $className . ' - %'];
        if (in_array('level_name', $this->sourceColumns($qualified), true)) {
            $lcol = $this->pinned($qualified, 'level_name');
            $sql = '(' . $lcol . ' = ? OR ' . $sql . ')';
            // The level_name placeholder is bound FIRST by the SQL above.
            array_unshift($params, $className);
        }
        return [$sql, $params];
    }

    /**
     * Same as classLabelPredicate but starting from a class id, for sources
     * that do not carry class_id themselves.
     */
    private function classNamePredicate(string $qualified, int $classId): array
    {
        $stmt = $this->db->prepare("SELECT name FROM classes WHERE id = ? LIMIT 1");
        $stmt->execute([$classId]);
        $name = $stmt->fetchColumn();
        if (!is_string($name) || $name === '') {
            return [null, []];
        }
        return $this->classLabelPredicate($qualified, $name);
    }

    public function getStudentPaymentStatus($studentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vw_student_payment_status_enhanced 
                WHERE id = ?
            ");
            $stmt->execute([$studentId]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$status) {
                return formatResponse(false, null, 'Student payment status not found');
            }

            return formatResponse(true, $status);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[PaymentManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }
}
