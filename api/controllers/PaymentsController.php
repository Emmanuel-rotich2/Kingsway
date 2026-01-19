<?php
/**
 * PaymentsController - Exposes RESTful endpoints for all payment webhooks
 */
namespace App\API\Controllers;

use App\API\Modules\payments\PaymentsAPI;
use App\API\Modules\finance\PaymentReconciliationAPI;
use Exception;

class PaymentsController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new PaymentsAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Payments API is running']);
    }

    /**
     * GET /api/payments/trends - Alias for collection trends
     */
    public function getTrends($id = null, $data = [], $segments = [])
    {
        // Reuse getCollectionTrends logic
        return $this->getCollectionTrends($id, $data, $segments);
    }

    /**
     * GET /api/payments/revenue-sources - Returns revenue sources breakdown
     */
    public function getRevenueSources($id = null, $data = [], $segments = [])
    {
        try {
            $query = "
                SELECT payment_method AS source, SUM(amount_paid) as total
                FROM payment_transactions
                WHERE status = 'confirmed'
                GROUP BY payment_method
                ORDER BY total DESC
            ";
            $result = $this->db->query($query);
            $sources = $result->fetchAll();
            return $this->success([
                'sources' => $sources,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Revenue sources breakdown');
        } catch (\Exception $e) {
            return $this->error('Failed to fetch revenue sources: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/stats - Get fees collection statistics for dashboard
     * Returns: amount collected, percentage collected, outstanding amount
     */
    public function getStats($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db;

            // Get fees collected this month
            $monthlyQuery = "
                SELECT COALESCE(SUM(COALESCE(amount_paid, amount, 0)), 0) as monthly_collected 
                FROM payment_transactions 
                WHERE status = 'successful' 
                AND YEAR(transaction_date) = YEAR(NOW())
                AND MONTH(transaction_date) = MONTH(NOW())
            ";
            $monthlyResult = $db->query($monthlyQuery);
            $monthlyRow = $monthlyResult->fetch();
            $monthlyCollected = (float) ($monthlyRow['monthly_collected'] ?? 0);

            // Get overdue payment count
            $overdueQuery = "
                SELECT COUNT(DISTINCT student_id) as overdue_count
                FROM student_fee_obligations
                WHERE status = 'overdue'
            ";
            $overdueResult = $db->query($overdueQuery);
            $overdueRow = $overdueResult->fetch();
            $overdueCount = (int) ($overdueRow['overdue_count'] ?? 0);

            // Get total fees expected (sum of active fee obligations)
            $totalFeesQuery = "
                SELECT SUM(balance) as total_expected 
                FROM student_fee_obligations 
                WHERE status = 'active' OR status = 'pending'
            ";
            $totalResult = $db->query($totalFeesQuery);
            $totalRow = $totalResult->fetch();
            $totalExpected = (float) ($totalRow['total_expected'] ?? 0);

            // Get total fees collected (sum of confirmed payments in last 30 days)
            // Note: payment_transactions uses 'confirmed' status, not 'successful'
            $collectedQuery = "
                SELECT COALESCE(SUM(COALESCE(amount_paid, amount, 0)), 0) as amount_collected 
                FROM payment_transactions 
                WHERE status IN ('confirmed', 'successful') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ";
            $collectedResult = $db->query($collectedQuery);
            $collectedRow = $collectedResult->fetch();
            $amountCollected = (float) ($collectedRow['amount_collected'] ?? 0);

            // Get outstanding fees
            $outstandingQuery = "
                SELECT COALESCE(SUM(balance), 0) as outstanding 
                FROM student_fee_obligations
                WHERE balance > 0
            ";
            $outstandingResult = $db->query($outstandingQuery);
            $outstandingRow = $outstandingResult->fetch();
            $outstanding = (float) ($outstandingRow['outstanding'] ?? 0);

            $percentage = $totalExpected > 0 ? round(($amountCollected / $totalExpected) * 100, 2) : 0;

            return $this->success([
                'monthly_collected' => $monthlyCollected,
                'amount' => $amountCollected,
                'percentage' => (float) $percentage,
                'outstanding' => $outstanding,
                'total_expected' => $totalExpected,
                'overdue_count' => $overdueCount,
                'period_days' => 30,
                'timestamp' => date('Y-m-d H:i:s')
            ], 'Fees collection statistics');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch fees statistics: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/collection-trends - Get fee collection trends over time
     * Returns: monthly collection data and comparison to target
     * SECURITY: Director and Finance roles only
     */
    public function getCollectionTrends($id = null, $data = [], $segments = [])
    {
        try {
            // Get last 12 months of collection data
            $query = "
                SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    DATE_FORMAT(payment_date, '%b') as month_label,
                    SUM(amount_paid) as collected,
                    COUNT(DISTINCT student_id) as students_paid
                FROM payment_transactions
                WHERE status = 'confirmed'
                AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month ASC
            ";

            $result = $this->db->query($query);
            $monthlyData = $result ? $result->fetchAll() : [];

            // Calculate average monthly target (total annual expected / 12)
            $targetQuery = "
                SELECT SUM(balance) as total_expected 
                FROM student_fee_obligations 
                WHERE status = 'active' OR status = 'pending'
            ";
            $targetResult = $this->db->query($targetQuery);
            $targetRow = $targetResult ? $targetResult->fetch() : [];
            $totalExpected = (float) ($targetRow['total_expected'] ?? 0);
            $monthlyTarget = $totalExpected / 12;

            // Format chart data
            $chartData = [];
            if ($monthlyData && count($monthlyData) > 0) {
                foreach ($monthlyData as $month) {
                    $chartData[] = [
                        'month' => $month['month_label'] ?? substr($month['month'], 5),
                        'collected' => (float) ($month['collected'] ?? 0),
                        'target' => $monthlyTarget,
                        'students_paid' => (int) ($month['students_paid'] ?? 0)
                    ];
                }
            }

            // Calculate totals
            $totalCollected = count($chartData) > 0 ? array_sum(array_column($chartData, 'collected')) : 0;
            $totalTarget = $monthlyTarget * count($chartData);
            $collectionRate = $totalTarget > 0
                ? round(($totalCollected / $totalTarget) * 100, 2)
                : 0;

            return $this->success([
                'chart_data' => $chartData,
                'summary' => [
                    'collected' => (float) $totalCollected,
                    'target' => (float) $totalTarget,
                    'collection_rate' => (float) $collectionRate,
                    'period' => '12 months',
                    'month_target' => (float) $monthlyTarget
                ]
            ], 'Collection trends retrieved');

        } catch (\Exception $e) {
            return $this->error('Failed to fetch collection trends: ' . $e->getMessage());
        }
    }

    /**
     * Standard API response handler
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                return $result['success']
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            if (isset($result['status'])) {
                return $result['status'] === 'success'
                    ? $this->success($result['data'] ?? [], $result['message'] ?? 'Operation successful')
                    : $this->badRequest($result['message'] ?? 'Operation failed', $result['data'] ?? []);
            }

            return $this->success($result);
        }

        return $this->success(['result' => $result]);
    }

    /**
     * POST /api/payments/mpesa-b2c-callback
     */
    public function postMpesaB2cCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-b2c-timeout
     */
    public function postMpesaB2cTimeout($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaB2CTimeout($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/mpesa-c2b-confirmation
     */
    public function postMpesaC2bConfirmation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processMpesaC2BConfirmation($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-validation
     */
    public function postKcbValidation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbValidation($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-transfer-callback
     */
    public function postKcbTransferCallback($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbTransferCallback($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/kcb-notification
     */
    public function postKcbNotification($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processKcbNotification($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/payments/bank-webhook
     */
    public function postBankWebhook($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processBankWebhook($data, $data['headers'] ?? []);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/payments/unmatched-mpesa - List mpesa transactions not matched to payments
     */
    public function getUnmatchedMpesa($id = null, $data = [], $segments = [])
    {
        // Authentication & authorization
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        // Use BaseController helper methods for role/permission checking
        // Allows: finance.view, finance.reconcile permissions, OR role ID 10 (Accountant), 
        // OR role names: accountant, finance, admin
        $allowed = $this->userHasAny(
            ['finance.view', 'finance.reconcile'],      // permissions
            [10],                                        // role IDs (10 = Accountant)
            ['accountant', 'finance', 'admin', 'director']  // role names
        );

        if (!$allowed) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $query = "
                SELECT mt.*
                FROM mpesa_transactions mt
                LEFT JOIN payment_transactions pt ON mt.mpesa_code = pt.reference_no
                WHERE pt.reference_no IS NULL
                  AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'matched'))
                ORDER BY mt.transaction_date DESC
                LIMIT 200
            ";

            $stmt = $this->db->query($query);
            $rows = $stmt ? $stmt->fetchAll() : [];

            return $this->success(['transactions' => $rows]);
        } catch (\Exception $e) {
            return $this->error('Failed to fetch unmatched mpesa transactions: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/payments/import-mpesa - Import MPESA transactions (stub)
     * Accepts: { transactions: [ { mpesa_code, amount, msisdn, transaction_date, note } ] }
     */
    public function postImportMpesa($id = null, $data = [], $segments = [])
    {
        // Authentication: only finance/accountant roles allowed to import
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) {
            return $this->unauthorized('Authentication required');
        }
        $perms = $user['effective_permissions'] ?? [];
        $roles = $user['roles'] ?? [];
        $role = $user['role'] ?? '';
        $allowed = false;
        if (in_array('finance.import', $perms) || in_array(10, $roles) || $role === 'accountant' || $role === 'finance' || $role === 'admin') {
            $allowed = true;
        }
        if (!$allowed)
            return $this->forbidden('Insufficient permissions');

        $txns = $data['transactions'] ?? [];
        if (!is_array($txns) || count($txns) === 0) {
            return $this->badRequest('No transactions provided for import');
        }

        $inserted = 0;
        try {
            $this->db->beginTransaction();
            $insertSql = "INSERT INTO mpesa_transactions (mpesa_code, amount, msisdn, transaction_date, note, raw_data, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            foreach ($txns as $t) {
                $code = $t['mpesa_code'] ?? $t['trans_id'] ?? null;
                if (!$code)
                    continue;

                // skip if exists - use query() method
                $chkStmt = $this->db->query('SELECT id FROM mpesa_transactions WHERE mpesa_code = ? LIMIT 1', [$code]);
                if ($chkStmt && $chkStmt->fetch())
                    continue;

                // Use query() for insert
                $this->db->query($insertSql, [
                    $code,
                    $t['amount'] ?? 0,
                    $t['msisdn'] ?? $t['phone'] ?? null,
                    $t['transaction_date'] ?? ($t['date'] ?? date('Y-m-d H:i:s')),
                    $t['note'] ?? null,
                    json_encode($t),
                    'pending'
                ]);
                $inserted++;
            }
            $this->db->commit();
            return $this->success(['imported' => $inserted], 'Import completed');
        } catch (\Exception $e) {
            $this->db->rollBack();
            return $this->error('Failed to import mpesa transactions: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/payments/reconcile-mpesa
     * Reconcile an MPESA transaction by allocating to student fees (if student_id provided)
     * or creating a school_transaction record (for tracking)
     */
    public function postReconcileMpesa($id = null, $data = [], $segments = [])
    {
        // Auth
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        $perms = $user['effective_permissions'] ?? [];
        $roles = $user['roles'] ?? [];
        $role = $user['role'] ?? '';
        $allowed = false;

        // Check if user has required permission
        if (in_array('finance.reconcile', $perms)) {
            $allowed = true;
        }

        // Check role by ID (roles array contains objects with 'id' key)
        foreach ($roles as $r) {
            $roleId = is_array($r) ? ($r['id'] ?? null) : (is_object($r) ? ($r->id ?? null) : $r);
            $roleName = is_array($r) ? ($r['name'] ?? '') : (is_object($r) ? ($r->name ?? '') : '');
            if ($roleId == 10 || strtolower($roleName) === 'accountant' || strtolower($roleName) === 'finance' || strtolower($roleName) === 'admin') {
                $allowed = true;
                break;
            }
        }

        // Also check simple role string
        if ($role === 'accountant' || $role === 'finance' || $role === 'admin') {
            $allowed = true;
        }

        if (!$allowed)
            return $this->forbidden('Insufficient permissions');

        $mpesaId = $data['mpesa_id'] ?? $id ?? null;
        $studentId = $data['student_id'] ?? null;
        $bankRef = $data['bank_statement_ref'] ?? null;
        $notes = $data['notes'] ?? 'Quick reconcile from dashboard';

        if (!$mpesaId)
            return $this->badRequest('mpesa_id is required');

        try {
            // Fetch mpesa transaction
            $stmt = $this->db->query('SELECT * FROM mpesa_transactions WHERE id = ? LIMIT 1', [$mpesaId]);
            $mp = $stmt ? $stmt->fetch() : null;
            if (!$mp)
                return $this->notFound('MPESA transaction not found');

            // Use student_id from mpesa record if not explicitly provided
            if (!$studentId && !empty($mp['student_id'])) {
                $studentId = $mp['student_id'];
            }

            $this->db->beginTransaction();

            $amount = $mp['amount'] ?? $mp['amt'] ?? 0;
            $mpesaCode = $mp['mpesa_code'] ?? $mp['trans_id'] ?? $mp['code'] ?? null;
            $transactionDate = $mp['transaction_date'] ?? ($mp['created_at'] ?? date('Y-m-d H:i:s'));
            $phoneNumber = $mp['phone_number'] ?? $mp['msisdn'] ?? '';
            $payerName = trim(($mp['first_name'] ?? '') . ' ' . ($mp['middle_name'] ?? '') . ' ' . ($mp['last_name'] ?? ''));

            $paymentId = null;
            $feeAllocated = false;

            // If we have a student_id, use sp_process_student_payment to properly allocate to fees
            if ($studentId) {
                // Get parent_id for this student
                $parentStmt = $this->db->query(
                    "SELECT parent_id FROM student_parents WHERE student_id = ? LIMIT 1",
                    [$studentId]
                );
                $parentRow = $parentStmt ? $parentStmt->fetch() : null;
                $parentId = $parentRow ? $parentRow['parent_id'] : null;

                // Generate receipt number
                $receiptNo = 'MPESA-' . $mpesaCode;

                // Current user ID for received_by
                $receivedBy = $user['user_id'] ?? $user['id'] ?? 1;

                // Build notes with payer info
                $fullNotes = $notes;
                if ($payerName || $phoneNumber) {
                    $fullNotes .= " | Payer: {$payerName} (Phone: {$phoneNumber})";
                }

                // Call sp_process_student_payment to allocate to fees
                $spStmt = $this->db->query("CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                    $studentId,
                    $parentId,
                    $amount,
                    'mpesa',
                    $mpesaCode,
                    $receiptNo,
                    $receivedBy,
                    $transactionDate,
                    $fullNotes
                ]);
                if ($spStmt) {
                    $spStmt->closeCursor();
                }

                // Get the payment_transaction ID that was created
                $ptStmt = $this->db->query(
                    "SELECT id FROM payment_transactions WHERE reference_no = ? ORDER BY id DESC LIMIT 1",
                    [$mpesaCode]
                );
                $ptRow = $ptStmt ? $ptStmt->fetch() : null;
                $paymentId = $ptRow ? $ptRow['id'] : null;
                $feeAllocated = true;

            } else {
                // No student - create school_transactions record for tracking (legacy behavior)
                $details = json_encode($mp);
                $this->db->query(
                    "INSERT INTO school_transactions (student_id, financial_period_id, source, reference, amount, transaction_date, status, details, created_at) VALUES (?, NULL, 'mpesa', ?, ?, ?, 'confirmed', ?, NOW())",
                    [null, $mpesaCode, $amount, $transactionDate, $details]
                );
                $paymentId = $this->db->lastInsertId();
            }

            // Mark the mpesa_transactions record as reconciled
            $this->db->query(
                "UPDATE mpesa_transactions SET status = 'reconciled', reconciled_at = NOW(), student_id = ? WHERE id = ?",
                [$studentId, $mpesaId]
            );

            $this->db->commit();

            return $this->success([
                'payment_id' => $paymentId,
                'student_id' => $studentId,
                'amount' => $amount,
                'fee_allocated' => $feeAllocated
            ], $feeAllocated
                ? 'Payment reconciled and allocated to student fees'
                : 'Transaction recorded (no student linked - fees not updated)');

        } catch (\Exception $e) {
            if ($this->db->inTransaction())
                $this->db->rollBack();
            return $this->error('Reconcile failed: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * GET /api/payments/mpesa-reconcile-history?mpesa_id=ID
     * Returns reconciliation records for school_transactions created from a given MPESA code
     */
    public function getMpesaReconcileHistory($id = null, $data = [], $segments = [])
    {
        // Auth
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        $mpesaId = $_GET['mpesa_id'] ?? $data['mpesa_id'] ?? $id ?? null;
        if (!$mpesaId)
            return $this->badRequest('mpesa_id is required');

        try {
            // get mpesa code using query() method
            $stmt = $this->db->query('SELECT mpesa_code FROM mpesa_transactions WHERE id = ? LIMIT 1', [$mpesaId]);
            $mp = $stmt ? $stmt->fetch() : null;
            if (!$mp)
                return $this->notFound('MPESA transaction not found');
            $code = $mp['mpesa_code'];

            $sql = "
                SELECT pr.*, u.username as reconciled_by_name, st.reference as school_reference, st.transaction_date as school_transaction_date
                FROM payment_reconciliations pr
                JOIN school_transactions st ON pr.transaction_id = st.id
                LEFT JOIN users u ON pr.reconciled_by = u.id
                WHERE st.source = 'mpesa' AND st.reference = ?
                ORDER BY pr.reconciled_at DESC
            ";
            $s = $this->db->query($sql, [$code]);
            $rows = $s ? $s->fetchAll() : [];

            return $this->success(['history' => $rows]);
        } catch (\Exception $e) {
            return $this->error('Failed to fetch reconciliation history: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/payments/lookup-by-phone?phone=07XXXXXXXX
     * 
     * Lookup students by parent phone number for payment reconciliation
     * Useful when M-Pesa payment doesn't have admission number but has phone
     * 
     * Returns matching students linked to parent with that phone number
     */
    public function getLookupByPhone($id = null, $data = [], $segments = [])
    {
        // Auth check
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        $phone = $_GET['phone'] ?? $data['phone'] ?? $id ?? null;
        if (!$phone)
            return $this->badRequest('phone is required');

        try {
            // Normalize phone number (handle various formats)
            $normalizedPhone = $this->normalizePhoneNumber($phone);

            // Search in multiple places:
            // 1. Parents table (phone_number column)
            // 2. Students table (guardian_phone if exists)
            // 3. M-Pesa transaction history (to find student_id linked to this phone)

            $results = [];

            // 1. Search via parent phone -> student_parents -> students
            // Note: parents table uses phone_1 and phone_2, relationship is in student_parents
            // Students have stream_id -> class_streams -> classes
            $sql1 = "
                SELECT DISTINCT 
                    s.id as student_id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    s.stream_id,
                    cs.class_id,
                    c.name as class_name,
                    cs.stream_name,
                    p.id as parent_id,
                    p.first_name as parent_first_name,
                    p.last_name as parent_last_name,
                    COALESCE(p.phone_1, p.phone_2) as parent_phone,
                    sp.relationship,
                    'parent_record' as match_source
                FROM parents p
                JOIN student_parents sp ON p.id = sp.parent_id
                JOIN students s ON sp.student_id = s.id
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE (
                    REPLACE(REPLACE(REPLACE(p.phone_1, '+', ''), ' ', ''), '-', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(p.phone_1, '+', ''), ' ', ''), '-', '') LIKE ?
                    OR p.phone_1 LIKE ?
                    OR REPLACE(REPLACE(REPLACE(p.phone_2, '+', ''), ' ', ''), '-', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(p.phone_2, '+', ''), ' ', ''), '-', '') LIKE ?
                    OR p.phone_2 LIKE ?
                )
                AND s.status = 'active'
            ";
            $phone254 = '254' . substr($normalizedPhone, -9);
            $phone07 = '0' . substr($normalizedPhone, -9);

            $stmt1 = $this->db->query($sql1, [
                '%' . $phone254 . '%',
                '%' . $phone07 . '%',
                '%' . $phone . '%',
                '%' . $phone254 . '%',
                '%' . $phone07 . '%',
                '%' . $phone . '%'
            ]);
            $parentMatches = $stmt1 ? $stmt1->fetchAll() : [];

            foreach ($parentMatches as $match) {
                $results[] = $match;
            }

            // 2. Search in M-Pesa transaction history
            // If we've successfully processed payments from this phone before,
            // we can suggest those students
            $sql2 = "
                SELECT DISTINCT
                    s.id as student_id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    s.stream_id,
                    cs.class_id,
                    c.name as class_name,
                    cs.stream_name,
                    NULL as parent_id,
                    m.first_name as parent_first_name,
                    m.last_name as parent_last_name,
                    m.phone_number as parent_phone,
                    'M-Pesa payer' as relationship,
                    'mpesa_history' as match_source,
                    COUNT(*) as payment_count,
                    MAX(m.transaction_date) as last_payment_date,
                    SUM(m.amount) as total_paid
                FROM mpesa_transactions m
                JOIN students s ON m.student_id = s.id
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                WHERE (
                    REPLACE(REPLACE(REPLACE(m.phone_number, '+', ''), ' ', ''), '-', '') LIKE ?
                    OR REPLACE(REPLACE(REPLACE(m.phone_number, '+', ''), ' ', ''), '-', '') LIKE ?
                    OR m.phone_number LIKE ?
                )
                AND m.student_id IS NOT NULL
                AND s.status = 'active'
                GROUP BY s.id, s.admission_no, s.first_name, s.last_name, s.stream_id,
                         cs.class_id, c.name, cs.stream_name,
                         m.first_name, m.last_name, m.phone_number
                ORDER BY payment_count DESC
            ";

            $stmt2 = $this->db->query($sql2, [
                '%' . $phone254 . '%',
                '%' . $phone07 . '%',
                '%' . $phone . '%'
            ]);
            $mpesaMatches = $stmt2 ? $stmt2->fetchAll() : [];

            // Add M-Pesa matches (avoid duplicates)
            $existingStudentIds = array_column($results, 'student_id');
            foreach ($mpesaMatches as $match) {
                if (!in_array($match['student_id'], $existingStudentIds)) {
                    $results[] = $match;
                }
            }

            return $this->success([
                'phone_searched' => $phone,
                'normalized_phone' => $normalizedPhone,
                'students' => $results,
                'count' => count($results)
            ], count($results) . ' student(s) found for phone ' . $phone);

        } catch (\Exception $e) {
            return $this->error('Phone lookup failed: ' . $e->getMessage());
        }
    }

    /**
     * Normalize phone number to consistent format
     * Handles: +254..., 254..., 07..., 7...
     */
    private function normalizePhoneNumber($phone)
    {
        // Remove all non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        // Handle various formats
        if (strlen($digits) >= 9) {
            // If starts with 254, keep as is
            if (substr($digits, 0, 3) === '254') {
                return $digits;
            }
            // If starts with 0, convert to 254
            if (substr($digits, 0, 1) === '0') {
                return '254' . substr($digits, 1);
            }
            // If starts with 7, add 254
            if (substr($digits, 0, 1) === '7') {
                return '254' . $digits;
            }
        }

        return $digits;
    }

    /**
     * POST /api/payments/link-student
     * 
     * Link an M-Pesa transaction to a student (for manual reconciliation)
     * Used when parent didn't enter correct admission number
     */
    public function postLinkStudent($id = null, $data = [], $segments = [])
    {
        // Auth check
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');

        // Permission check
        $perms = $user['effective_permissions'] ?? [];
        $roles = $user['roles'] ?? [];
        $role = $user['role'] ?? '';
        $allowed = false;
        if (
            in_array('finance.reconcile', $perms) || in_array('payments.reconcile', $perms) ||
            in_array(10, $roles) || $role === 'accountant' || $role === 'finance' || $role === 'admin'
        ) {
            $allowed = true;
        }
        if (!$allowed)
            return $this->forbidden('Insufficient permissions');

        $mpesaId = $data['mpesa_id'] ?? null;
        $studentId = $data['student_id'] ?? null;

        if (!$mpesaId || !$studentId)
            return $this->badRequest('mpesa_id and student_id are required');

        try {
            // Verify mpesa transaction exists
            $checkMpesa = $this->db->query(
                'SELECT id, mpesa_code, student_id, amount, status FROM mpesa_transactions WHERE id = ? LIMIT 1',
                [$mpesaId]
            );
            $mpesa = $checkMpesa ? $checkMpesa->fetch() : null;
            if (!$mpesa)
                return $this->notFound('M-Pesa transaction not found');

            // Verify student exists
            $checkStudent = $this->db->query(
                "SELECT id, admission_no, first_name, last_name FROM students WHERE id = ? AND status = 'active' LIMIT 1",
                [$studentId]
            );
            $student = $checkStudent ? $checkStudent->fetch() : null;
            if (!$student)
                return $this->notFound('Student not found or not active');

            // Update the mpesa_transactions table
            $this->db->query(
                'UPDATE mpesa_transactions SET student_id = ?, bill_ref_number = ? WHERE id = ?',
                [$studentId, $student['admission_no'], $mpesaId]
            );

            // Log the action
            error_log("M-Pesa transaction {$mpesa['mpesa_code']} linked to student {$student['admission_no']} (ID: {$studentId}) by user {$user['id']}");

            return $this->success([
                'mpesa_id' => $mpesaId,
                'mpesa_code' => $mpesa['mpesa_code'],
                'student_id' => $studentId,
                'student_name' => $student['first_name'] . ' ' . $student['last_name'],
                'admission_no' => $student['admission_no']
            ], 'Student linked successfully');

        } catch (\Exception $e) {
            return $this->error('Failed to link student: ' . $e->getMessage());
        }
    }
}


