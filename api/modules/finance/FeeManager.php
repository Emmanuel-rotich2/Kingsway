<?php

namespace App\API\Modules\finance;

use App\Database\Database;
use App\API\Services\NotificationService;
use App\API\Services\ReadReplicaService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Fee Management Class
 * 
 * Handles all fee-related operations:
 * - Fee structure management (create, update, retrieve)
 * - Student fee obligations and calculations
 * - Fee balances and carryover
 * - Discounts and waivers
 * - Fee reminders and collection tracking
 * 
 * Integrates with stored procedures:
 * - sp_calculate_student_fees
 * - sp_apply_fee_discount
 * - sp_carryover_fee_balance
 * - sp_send_fee_reminder
 * - sp_get_fee_collection_rate
 * - sp_get_outstanding_fees_report
 * - sp_transition_to_new_term
 * - sp_transition_to_new_academic_year
 */
class FeeManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Resolve an academic_years.id from either its primary key or year code.
     * Frontend selectors use the primary key; older callers may still send
     * the four-digit starting year or the stored year_code.
     */
    private function resolveAcademicYearId($year)
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM academic_years
             WHERE id = ? OR year_code = ? OR YEAR(start_date) = ?
             LIMIT 1"
        );
        $stmt->execute([$year, $year, $year]);
        return $stmt->fetchColumn();
    }

    /**
     * Reconcile a changed fee schedule without rewriting paid history.
     * Unpaid/partial obligations are repriced; paid obligations remain
     * unchanged and any reduction becomes a student credit.
     */
    private function reconcileScheduleChange(int $oldScheduleId, int $newScheduleId, float $oldAmount, float $newAmount, ?int $userId): array
    {
        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
        $rows = $this->db->prepare(
            "SELECT sfo.id, sfo.amount_due, sfo.academic_year_id, sfo.academic_year_term_id,
                    sae.student_id, COALESCE(vfb.amount_paid, 0) AS amount_paid
             FROM student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id = sfo.student_academic_enrollment_id
             LEFT JOIN $feeBalView vfb
               ON vfb.student_academic_enrollment_id = sfo.student_academic_enrollment_id
              AND vfb.academic_year_term_id = sfo.academic_year_term_id
             WHERE sfo.academic_year_fee_schedule_id = ?"
        );
        $rows->execute([$oldScheduleId]);

        $repriced = 0;
        $credits = 0;
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $paid = (float) $row['amount_paid'];
            $delta = round($newAmount - $oldAmount, 2);
            $type = $delta < 0 ? 'credit' : ($delta > 0 ? 'debit' : 'unchanged');

            $this->db->prepare(
                "INSERT INTO fee_structure_adjustments
                    (student_fee_obligation_id, old_schedule_id, new_schedule_id,
                     old_amount, new_amount, amount_delta, adjustment_type,
                     payment_protected, created_by, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
            )->execute([
                (int) $row['id'], $oldScheduleId, $newScheduleId,
                $oldAmount, $newAmount, $delta, $type, $userId,
                $paid >= $oldAmount
                    ? 'Paid amount protected; schedule change did not rewrite payment history.'
                    : 'Existing unpaid/partial obligation repriced to approved schedule amount.'
            ]);

            // Payments are never edited. The obligation is adjusted around
            // them: an increase creates a remaining debit, while a reduction
            // above the paid amount creates a usable credit.
            if ($newAmount < $paid) {
                $yearStmt = $this->db->prepare("SELECT year_code FROM academic_years WHERE id = ? LIMIT 1");
                $yearStmt->execute([(int) $row['academic_year_id']]);
                $yearCode = (string) ($yearStmt->fetchColumn() ?: date('Y'));
                $this->db->prepare(
                    "INSERT INTO fee_credit_notes
                        (credit_number, student_id, academic_year, term_id,
                         credit_amount, credit_reason, status, applied_amount,
                         notes, created_by)
                     VALUES (?, ?, ?, ?, ?, 'fee_reduction', 'available', 0, ?, ?)"
                )->execute([
                    'CRD-' . date('YmdHis'),
                    (int) $row['student_id'],
                    substr($yearCode, 0, 4),
                    (int) $row['academic_year_term_id'],
                    round($paid - $newAmount, 2),
                    'Credit created from approved fee reduction; paid history preserved.',
                    $userId,
                ]);
                $creditId = (int) $this->db->lastInsertId();
                $credits++;
            }

            $status = $paid >= $newAmount ? 'paid' : ($paid > 0 ? 'partial' : 'pending');
            $this->db->prepare(
                "UPDATE student_fee_obligations
                 SET amount_due = ?, status = ?, updated_at = NOW()
                 WHERE id = ?"
            )->execute([$newAmount, $status, (int) $row['id']]);
            $repriced++;
        }

        return ['repriced' => $repriced, 'credits' => $credits];
    }

    /**
     * Build a map of term_number => academic_year_terms row for a given academic year
     */
    private function getYearTermMap($academicYearId)
    {
        $stmt = $this->db->prepare(
            "SELECT ayt.id, ayt.term_id, t.code AS term_code, t.name AS term_name
             FROM academic_year_terms ayt
             JOIN terms t ON ayt.term_id = t.id
             WHERE ayt.academic_year_id = ?
             ORDER BY t.code"
        );
        $stmt->execute([$academicYearId]);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $term) {
            $number = (int) ltrim((string) $term['term_code'], 'Tt');
            $map[$number] = $term;
        }
        return $map;
    }

    /** Resolve the single canonical school-fee identity used by the matrix. */
    private function resolveFeeCatalogId($feeTypeName, $studentTypeId = null)
    {
        $stmt = $this->db->prepare("SELECT id FROM fee_catalog WHERE code = 'SCHOOL_FEES' LIMIT 1");
        $stmt->execute();
        $catalogId = $stmt->fetchColumn();
        if ($catalogId) {
            return (int) $catalogId;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO fee_catalog (code, name, default_amount, status)
             VALUES ('SCHOOL_FEES', 'School Fees', 0, 'active')
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)"
        );
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Compute an aggregated "invoice" from live student_fee_obligations / balances
     */
    private function computeStudentInvoice($studentId, $academicYearId, $termId)
    {
                $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
                $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(v.amount_due), 0) AS total_amount,
                COALESCE(SUM(v.amount_paid), 0) AS amount_paid,
                COALESCE(SUM(v.balance), 0) AS balance,
                MAX(v.latest_due_date) AS due_date
            FROM $feeBalView v
            WHERE v.student_id = ? AND v.academic_year_id = ? AND v.academic_year_term_id = ?
        ");
        $stmt->execute([$studentId, $academicYearId, $termId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($totals) || floatval($totals['total_amount']) <= 0) {
            return null;
        }

        $totalAmount = floatval($totals['total_amount']);
        $amountPaid = floatval($totals['amount_paid']);
        $balance = floatval($totals['balance']);

        $status = 'pending';
        if ($balance <= 0 && $totalAmount > 0) {
            $status = 'paid';
        } elseif ($amountPaid > 0) {
            $status = 'partial';
        }

        return [
            'student_id' => (int) $studentId,
            'academic_year_id' => (int) $academicYearId,
            'term_id' => (int) $termId,
            'total_amount' => $totalAmount,
            'amount_paid' => $amountPaid,
            'balance' => $balance,
            'status' => $status,
            'due_date' => $totals['due_date'] ?? null,
        ];
    }

    /**
     * Create a new fee structure
     * @param array $data Fee structure data
     * @return array Response with fee_structure_id
     */
    public function createFeeStructure($data)
    {
        try {
            $required = ['name', 'academic_year', 'level_id', 'amount'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                return formatResponse(false, null, 'Academic year not found for year: ' . $data['academic_year']);
            }

            $this->db->beginTransaction();

            // Insert main fee structure into the fee catalog (master fee item)
            $code = 'FS-' . $data['academic_year'] . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $data['name']), 0, 8));
            $stmt = $this->db->prepare("
                INSERT INTO fee_catalog (
                    code, name, description, default_amount, status
                ) VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $code,
                $data['name'],
                $data['description'] ?? null,
                $data['amount'],
                $data['status'] ?? 'active'
            ]);
            $feeStructureId = (int) $this->db->lastInsertId();

            // Insert detailed fee types if provided
            if (!empty($data['fee_types'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO academic_year_fee_schedules (
                        academic_year_id, academic_year_term_id, academic_year_class_id,
                        student_type_id, fee_catalog_id, amount, status, created_by
                    ) VALUES (?, NULL, NULL, ?, ?, ?, ?, ?)
                ");

                foreach ($data['fee_types'] as $feeType) {
                    $stmt->execute([
                        $academicYearId,
                        $feeType['student_type_id'] ?? null,
                        $feeStructureId,
                        $feeType['amount'],
                        $feeType['status'] ?? 'active',
                        $data['created_by'] ?? null
                    ]);
                    $newStructureId = (int) $this->db->lastInsertId();
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'fee_structure_id' => $feeStructureId,
                'message' => 'Fee structure created successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Update existing fee structure
     * @param int $feeStructureId Fee structure ID
     * @param array $data Updated data
     * @return array Response
     */
    public function updateFeeStructure($feeStructureId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if fee structure exists
            $stmt = $this->db->prepare("SELECT id FROM fee_catalog WHERE id = ?");
            $stmt->execute([$feeStructureId]);

            if (!$stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Fee structure not found');
            }

            // Build update query dynamically
            $fieldMap = ['name' => 'name', 'description' => 'description', 'amount' => 'default_amount', 'status' => 'status'];
            $allowedFields = ['name', 'description', 'amount', 'status'];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$fieldMap[$field]} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No valid fields to update');
            }

            $params[] = $feeStructureId;
            $sql = "UPDATE fee_catalog SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();

            return formatResponse(true, ['message' => 'Fee structure updated successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get fee structure details
     * @param int $feeStructureId Fee structure ID
     * @return array Response with fee structure data
     */
    public function getFeeStructure($feeStructureId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT fc.*, NULL as level_id, NULL as level_name, NULL as level_code,
                       NULL as created_by_name
                FROM fee_catalog fc
                WHERE fc.id = ?
            ");

            $stmt->execute([$feeStructureId]);
            $feeStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feeStructure) {
                return formatResponse(false, null, 'Fee structure not found');
            }

            // Get the matrix rows represented by this canonical school-fee item.
            $stmt = $this->db->prepare("
                SELECT ayfs.*, 'School Fees' AS fee_type_name, 'SCHOOL_FEES' AS fee_type_code
                FROM academic_year_fee_schedules ayfs
                WHERE ayfs.fee_catalog_id = ?
            ");

            $stmt->execute([$feeStructureId]);
            $feeStructure['fee_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $feeStructure);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * List all fee structures with filters
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Response with fee structures list
     */
    public function listFeeStructures($filters = [], $page = 1, $limit = 20)
    {
        try {
            $offset = ($page - 1) * $limit;

            $sql = "SELECT ayfs.*, fc.name as name, fc.description,
                           ay.year_code as academic_year,
                           sl.name as level_name, sl.code as level_code,
                           t.name as term_name, t.code as term_code,
                           st.name as student_type_name, st.code as student_type_code,
                           'SCHOOL_FEES' as fee_type_code, 'School Fees' as fee_name, 'school_fees' as fee_category,
                           COUNT(DISTINCT sae.id) as student_count
                    FROM academic_year_fee_schedules ayfs
                    LEFT JOIN academic_years ay ON ayfs.academic_year_id = ay.id
                    LEFT JOIN academic_year_terms ayt ON ayfs.academic_year_term_id = ayt.id
                    LEFT JOIN terms t ON ayt.term_id = t.id
                    LEFT JOIN academic_year_classes ayc ON ayfs.academic_year_class_id = ayc.id
                    LEFT JOIN classes c ON ayc.class_id = c.id
                    LEFT JOIN school_levels sl ON c.level_id = sl.id
                    LEFT JOIN student_types st ON ayfs.student_type_id = st.id
                    LEFT JOIN fee_catalog fc ON ayfs.fee_catalog_id = fc.id
                    LEFT JOIN student_academic_enrollments sae ON sae.academic_year_id = ayfs.academic_year_id AND sae.enrollment_status = 'active'
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND (ay.year_code = ? OR YEAR(ay.start_date) = ?)";
                $params[] = $filters['academic_year'];
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['level_id'])) {
                $sql .= " AND ayfs.academic_year_class_id IN (SELECT ayc.id FROM academic_year_classes ayc JOIN classes c ON ayc.class_id = c.id WHERE c.level_id = ?)";
                $params[] = $filters['level_id'];
            }

            if (!empty($filters['student_type_id'])) {
                $sql .= " AND ayfs.student_type_id = ?";
                $params[] = $filters['student_type_id'];
            }

            $termId = $filters['term_id'] ?? $filters['term'] ?? null;
            if (!empty($termId)) {
                $sql .= " AND ayfs.academic_year_term_id = ?";
                $params[] = $termId;
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND ayfs.academic_year_class_id IN (SELECT ayc.id FROM academic_year_classes ayc WHERE ayc.class_id = ?)";
                $params[] = $filters['class_id'];
            }

            if (!empty($filters['class_ids']) && is_array($filters['class_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['class_ids']), '?'));
                $sql .= " AND ayfs.academic_year_class_id IN (SELECT ayc.id FROM academic_year_classes ayc WHERE ayc.class_id IN ($placeholders))";
                $params = array_merge($params, $filters['class_ids']);
            }

            if (!empty($filters['status'])) {
                $sql .= " AND ayfs.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (fc.name LIKE ? OR fc.description LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }

            $sql .= " GROUP BY ayfs.id ORDER BY ay.year_code DESC, ayfs.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $feeStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(DISTINCT ayfs.id) as total FROM academic_year_fee_schedules ayfs
                         LEFT JOIN academic_years ay ON ayfs.academic_year_id = ay.id
                         LEFT JOIN fee_catalog fc ON ayfs.fee_catalog_id = fc.id
                         WHERE 1=1";
            $countParams = array_slice($params, 0, -2); // Remove limit and offset

            if (!empty($filters['academic_year'])) {
                $countSql .= " AND (ay.year_code = ? OR YEAR(ay.start_date) = ?)";
            }
            if (!empty($filters['level_id'])) {
                $countSql .= " AND ayfs.academic_year_class_id IN (SELECT ayc.id FROM academic_year_classes ayc JOIN classes c ON ayc.class_id = c.id WHERE c.level_id = ?)";
            }
            if (!empty($filters['student_type_id'])) {
                $countSql .= " AND ayfs.student_type_id = ?";
            }
            $termId = $filters['term_id'] ?? $filters['term'] ?? null;
            if (!empty($termId)) {
                $countSql .= " AND ayfs.academic_year_term_id = ?";
            }
            if (!empty($filters['class_id'])) {
                $countSql .= " AND ayfs.academic_year_class_id IN (SELECT ayc.id FROM academic_year_classes ayc WHERE ayc.class_id = ?)";
            }
            if (!empty($filters['class_ids']) && is_array($filters['class_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['class_ids']), '?'));
                $countSql .= " AND ayfs.academic_year_class_id IN (SELECT ayc.id FROM academic_year_classes ayc WHERE ayc.class_id IN ($placeholders))";
            }
            if (!empty($filters['status'])) {
                $countSql .= " AND ayfs.status = ?";
            }
            if (!empty($filters['search'])) {
                $countSql .= " AND (fc.name LIKE ? OR fc.description LIKE ?)";
            }

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Revenue exposure must come from obligations actually billed to
            // enrolled learners, not fee amount multiplied by a duplicated
            // row-level student count.
            $billingSql = "
                SELECT COUNT(DISTINCT sfo.student_academic_enrollment_id) AS billed_students,
                       COALESCE(SUM(sfo.amount_due), 0) AS billed_amount
                FROM student_fee_obligations sfo
                JOIN academic_year_fee_schedules ayfs
                  ON ayfs.id = sfo.academic_year_fee_schedule_id
                JOIN academic_years ay ON ay.id = ayfs.academic_year_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                WHERE sfo.status NOT IN ('cancelled', 'void')";
            $billingParams = [];

            if (!empty($filters['academic_year'])) {
                $billingSql .= " AND (ay.year_code = ? OR YEAR(ay.start_date) = ? OR ay.id = ?)";
                $billingParams[] = $filters['academic_year'];
                $billingParams[] = $filters['academic_year'];
                $billingParams[] = $filters['academic_year'];
            }
            if (!empty($filters['level_id'])) {
                $billingSql .= " AND c.level_id = ?";
                $billingParams[] = $filters['level_id'];
            }
            if (!empty($filters['student_type_id'])) {
                $billingSql .= " AND ayfs.student_type_id = ?";
                $billingParams[] = $filters['student_type_id'];
            }
            if (!empty($termId)) {
                $billingSql .= " AND ayfs.academic_year_term_id = ?";
                $billingParams[] = $termId;
            }
            if (!empty($filters['class_id'])) {
                $billingSql .= " AND ayc.class_id = ?";
                $billingParams[] = $filters['class_id'];
            }
            if (!empty($filters['class_ids']) && is_array($filters['class_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['class_ids']), '?'));
                $billingSql .= " AND ayc.class_id IN ($placeholders)";
                $billingParams = array_merge($billingParams, $filters['class_ids']);
            }
            if (!empty($filters['status'])) {
                $billingSql .= " AND ayfs.status = ?";
                $billingParams[] = $filters['status'];
            }

            $billingStmt = $this->db->prepare($billingSql);
            $billingStmt->execute($billingParams);
            $billingSummary = $billingStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return formatResponse(true, [
                'fee_structures' => $feeStructures,
                'billing_summary' => [
                    'billed_students' => (int) ($billingSummary['billed_students'] ?? 0),
                    'billed_amount' => (float) ($billingSummary['billed_amount'] ?? 0),
                ],
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // ===============================================================
    // Fee Invoices
    // ===============================================================

    /**
     * Resolve current academic year and term IDs
     */
    private function resolveCurrentYearTerm($academicYearId = null, $termId = null)
    {
        if (empty($academicYearId)) {
            $stmt = $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1");
            $academicYearId = $stmt->fetchColumn();
        }

        if (empty($termId)) {
            $stmt = $this->db->query("SELECT ayt.id FROM academic_year_terms ayt JOIN academic_years ay ON ayt.academic_year_id = ay.id WHERE ay.is_current = 1 AND ayt.status = 'current' LIMIT 1");
            $termId = $stmt->fetchColumn();
        }

        return [$academicYearId, $termId];
    }

    /**
     * Generate or refresh a fee invoice for a student (current term/year by default)
     */
    public function generateStudentInvoice($studentId, $academicYearId = null, $termId = null, $generatedBy = null)
    {
        try {
            if (empty($studentId)) {
                return formatResponse(false, null, 'student_id is required');
            }

            [$academicYearId, $termId] = $this->resolveCurrentYearTerm($academicYearId, $termId);

            if (empty($academicYearId) || empty($termId)) {
                return formatResponse(false, null, 'Current academic year or term not configured');
            }

            $invoice = $this->computeStudentInvoice($studentId, $academicYearId, $termId);

            if ($invoice === null) {
                return formatResponse(false, null, 'No fee obligations found for student');
            }

            return formatResponse(true, $invoice, 'Invoice generated successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Generate invoices for all students with obligations in a term/year
     */
    public function generateInvoicesForTerm($academicYearId = null, $termId = null, $filters = [], $generatedBy = null)
    {
        try {
            [$academicYearId, $termId] = $this->resolveCurrentYearTerm($academicYearId, $termId);
            if (empty($academicYearId) || empty($termId)) {
                return formatResponse(false, null, 'Current academic year or term not configured');
            }

            $bindings = [$academicYearId, $termId];
            $where = "WHERE v.academic_year_id = ? AND v.academic_year_term_id = ?";

            if (!empty($filters['class_id'])) {
                $where .= " AND ayc.class_id = ?";
                $bindings[] = $filters['class_id'];
            }
            if (!empty($filters['stream_id'])) {
                $where .= " AND aycs.stream_id = ?";
                $bindings[] = $filters['stream_id'];
            }

                        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
                        $stmt = $this->db->prepare("
                SELECT DISTINCT v.student_id AS student_id
                FROM $feeBalView v
                JOIN students s ON v.student_id = s.id
                LEFT JOIN student_academic_enrollments sae ON sae.student_id = v.student_id AND sae.academic_year_id = v.academic_year_id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                LEFT JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                $where
            ");
            $stmt->execute($bindings);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $count = 0;
            $errors = [];
            foreach ($students as $studentId) {
                $res = $this->generateStudentInvoice($studentId, $academicYearId, $termId, $generatedBy);
                if (!empty($res['status']) && $res['status'] === 'success') {
                    $count++;
                } else {
                    $errors[] = [
                        'student_id' => $studentId,
                        'message' => $res['message'] ?? 'Failed to generate invoice'
                    ];
                }
            }

            return formatResponse(true, [
                'academic_year_id' => $academicYearId,
                'term_id' => $termId,
                'generated' => $count,
                'errors' => $errors
            ], 'Invoice batch generation completed');
        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get a student invoice for a term/year (current by default)
     */
    public function getStudentInvoice($studentId, $academicYearId = null, $termId = null)
    {
        try {
            if (empty($studentId)) {
                return formatResponse(false, null, 'student_id is required');
            }
            [$academicYearId, $termId] = $this->resolveCurrentYearTerm($academicYearId, $termId);

            $invoice = $this->computeStudentInvoice($studentId, $academicYearId, $termId);

            if ($invoice === null) {
                return formatResponse(false, null, 'Invoice not found');
            }
            return formatResponse(true, $invoice, 'Invoice retrieved successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * List student types
     * @return array Response with student types list
     */
    public function listStudentTypes()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, code, name, description, status
                FROM student_types
                WHERE status = 'active'
                ORDER BY name ASC
            ");
            $stmt->execute();
            $studentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $studentTypes);
        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Update annual fee structure with term breakdown
     * @param array $data Contains: academic_year, level_id, student_type_id, term_breakdown, updated_by
     * @return array Response with update counts
     */
    public function updateAnnualFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id', 'student_type_id', 'term_breakdown', 'updated_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            if (empty($data['term_breakdown']) || !is_array($data['term_breakdown'])) {
                return formatResponse(false, null, 'Term breakdown is required');
            }

            $this->db->beginTransaction();

            // Resolve academic year and get terms for this academic year
            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                throw new Exception("No academic year found for {$data['academic_year']}");
            }

            $terms = $this->getYearTermMap($academicYearId);

            if (empty($terms)) {
                throw new Exception("No terms found for academic year {$data['academic_year']}");
            }

            $termMap = [];
            foreach ($terms as $termNumber => $term) {
                $termMap[$termNumber] = $term['id'];
            }

            $updatedCount = 0;
            $createdCount = 0;

            foreach ($data['term_breakdown'] as $feeTypeName => $termAmounts) {
                // Resolve the fee catalog entry from name or code
                $catalogId = $this->resolveFeeCatalogId($feeTypeName, $data['student_type_id']);

                if (!$catalogId) {
                    throw new Exception("Fee type '{$feeTypeName}' not found");
                }

                foreach ($termAmounts as $termKey => $amount) {
                    $termNumber = (int) str_replace('term', '', $termKey);
                    if (!isset($termMap[$termNumber])) {
                        continue;
                    }

                    $termId = $termMap[$termNumber];

                    $updateStmt = $this->db->prepare("
                        UPDATE academic_year_fee_schedules
                        SET amount = ?, status = 'active', created_by = ?, updated_at = NOW()
                        WHERE academic_year_id = ?
                        AND academic_year_term_id = ?
                        AND student_type_id = ?
                        AND fee_catalog_id = ?
                    ");

                    $updateStmt->execute([
                        $amount,
                        $data['updated_by'],
                        $academicYearId,
                        $termId,
                        $data['student_type_id'],
                        $catalogId
                    ]);

                    if ($updateStmt->rowCount() > 0) {
                        $updatedCount++;
                    } else {
                        $insertStmt = $this->db->prepare("
                            INSERT INTO academic_year_fee_schedules (
                                academic_year_id,
                                academic_year_term_id,
                                student_type_id,
                                fee_catalog_id,
                                amount,
                                status,
                                created_by
                            ) VALUES (?, ?, ?, ?, ?, 'active', ?)
                        ");

                        $insertStmt->execute([
                            $academicYearId,
                            $termId,
                            $data['student_type_id'],
                            $catalogId,
                            $amount,
                            $data['updated_by']
                        ]);
                        $insertId = (int) $this->db->lastInsertId();

                        $createdCount++;
                    }
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'structures_updated' => $updatedCount,
                'structures_created' => $createdCount,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'student_type_id' => $data['student_type_id'],
                'message' => 'Annual fee structure updated successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Delete annual fee structure for a level/year/student type
     * @param array $data Contains: academic_year, level_id, student_type_id, optional term_id
     * @return array Response with delete count
     */
    public function deleteAnnualFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id', 'student_type_id'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                return formatResponse(false, null, 'Academic year not found');
            }

            $params = [
                $academicYearId,
                $data['student_type_id']
            ];

            $sql = "
                SELECT id
                FROM academic_year_fee_schedules
                WHERE academic_year_id = ?
                AND student_type_id = ?
            ";

            if (!empty($data['term_id'])) {
                $sql .= " AND academic_year_term_id = ?";
                $params[] = $data['term_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($ids)) {
                return formatResponse(true, [
                    'structures_deleted' => 0,
                    'message' => 'No fee structures found to delete'
                ]);
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $checkStmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM student_fee_obligations WHERE academic_year_fee_schedule_id IN ($placeholders)"
            );
            $checkStmt->execute($ids);
            $inUse = (int) $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($inUse > 0) {
                return formatResponse(false, null, 'Cannot delete: Fee structure is in use by ' . $inUse . ' student(s)');
            }

            $deleteStmt = $this->db->prepare(
                "DELETE FROM academic_year_fee_schedules WHERE id IN ($placeholders)"
            );
            $deleteStmt->execute($ids);

            return formatResponse(true, [
                'structures_deleted' => count($ids),
                'message' => 'Fee structures deleted successfully'
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Calculate student fees using stored procedure
     * @param int $studentId Student ID
     * @param int $academicYear Academic year
     * @param int $termId Term ID
     * @return array Response with calculated fees
     */
    public function calculateStudentFees($studentId, $academicYear, $termId)
    {
        try {
            $stmt = $this->db->prepare("CALL sp_calculate_student_fees(?, ?, ?)");
            $stmt->execute([$studentId, $academicYear, $termId]);

            // Get the result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, $result);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get student fee balance
     * @param int $studentId Student ID
     * @param int $academicYear Academic year (optional)
     * @return array Response with balance details
     */
    public function getStudentFeeBalance($studentId, $academicYear = null)
    {
        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
        try {
            $yearId = null;
            if (!empty($academicYear)) {
                $yearId = $this->resolveAcademicYearId($academicYear);
            }

            $summaryWhere = ["v.student_id = ?"];
            $summaryParams = [$studentId];

            if (!empty($academicYear)) {
                $summaryWhere[] = "v.academic_year_id = ?";
                $summaryParams[] = $yearId;
            }

            $summarySql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                    c.name AS class_name,
                    st.name AS stream_name,
                    COALESCE(SUM(v.amount_due), 0) AS total_due,
                    COALESCE(SUM(v.amount_paid), 0) AS total_paid,
                    COALESCE(SUM(v.amount_waived), 0) AS total_waived,
                    COALESCE(SUM(v.balance), 0) AS total_balance,
                    MAX(ay.updated_at) AS last_updated
                FROM students s
                LEFT JOIN persons p ON s.person_id = p.id
                LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                LEFT JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                LEFT JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN streams st ON aycs.stream_id = st.id
                LEFT JOIN $feeBalView v ON v.student_id = s.id
                LEFT JOIN academic_years ay ON v.academic_year_id = ay.id
                WHERE " . implode(' AND ', $summaryWhere) . "
                GROUP BY s.id, s.admission_no, p.first_name, p.last_name, c.name, st.name
            ";

            $summaryStmt = $this->db->prepare($summarySql);
            $summaryStmt->execute($summaryParams);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

            if (!$summary) {
                return formatResponse(false, null, 'Student not found');
            }

            $termsWhere = ["v.student_id = ?"];
            $termsParams = [$studentId];
            if (!empty($academicYear)) {
                $termsWhere[] = "v.academic_year_id = ?";
                $termsParams[] = $yearId;
            }

            $termsSql = "
                SELECT
                    v.academic_year_term_id AS term_id,
                    t.name AS term_name,
                    CAST(SUBSTRING(t.code, 2) AS UNSIGNED) AS term_number,
                    v.academic_year AS academic_year,
                    v.amount_due,
                    v.amount_paid,
                    v.amount_waived,
                    v.balance
                FROM $feeBalView v
                JOIN academic_year_terms ayt ON v.academic_year_term_id = ayt.id
                JOIN terms t ON ayt.term_id = t.id
                WHERE " . implode(' AND ', $termsWhere) . "
                ORDER BY v.academic_year DESC, t.code DESC, v.academic_year_term_id DESC
            ";

            $termsStmt = $this->db->prepare($termsSql);
            $termsStmt->execute($termsParams);
            $termBalances = $termsStmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'summary' => $summary,
                'term_balances' => $termBalances,
                'balances' => $termBalances // backward compatibility
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Apply discount or waiver to student fees
     * @param int $studentId Student ID
     * @param array $data Discount/waiver data
     * @return array Response
     */
    public function applyDiscount($studentId, $data)
    {
        try {
            $required = ['discount_type', 'amount', 'reason'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Call stored procedure
            $stmt = $this->db->prepare("
                CALL sp_apply_fee_discount(?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $studentId,
                $data['amount'],
                $data['reason'],
                $data['approved_by'] ?? null,
                $data['discount_type'], // 'percentage' or 'fixed'
                $data['term_id'] ?? null,
                $this->resolveAcademicYearId($data['academic_year'] ?? date('Y'))
            ]);

            return formatResponse(true, ['message' => 'Discount applied successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Carryover fee balance to new academic year
     * @param int $studentId Student ID
     * @param int $fromYear Source year
     * @param int $toYear Target year
     * @return array Response
     */
    public function carryoverBalance($studentId, $fromYear, $toYear)
    {
        try {
            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_carryover_fee_balance(?, ?, ?)");
            $stmt->execute([$studentId, $fromYear, $toYear]);

            return formatResponse(true, ['message' => 'Balance carried over successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Send fee reminder to parent/guardian
     * @param int $studentId Student ID
     * @return array Response
     */
    public function sendFeeReminder($studentId)
    {
        try {
                        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
                        $stmt = $this->db->prepare(
                "SELECT s.id, CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS student_name,
                        COALESCE(SUM(v.balance), 0) AS amount_due,
                        MAX(v.latest_due_date) AS due_date,
                        MAX(v.academic_year) AS academic_year,
                        MAX(v.term_id) AS term_id
                   FROM students s JOIN persons p ON p.id = s.person_id
                   LEFT JOIN $feeBalView v ON v.student_id = s.id
                  WHERE s.id = ? GROUP BY s.id, p.first_name, p.middle_name, p.last_name"
            );
            $stmt->execute([(int) $studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$student || (float) $student['amount_due'] <= 0) {
                return formatResponse(false, null, 'No outstanding fee balance found.');
            }

            $platform = new \App\API\Services\CommunicationPlatformService($this->db);
            $eventService = new \App\API\Services\CommunicationBusinessEventService($this->db);
            $eventId = $eventService->getOrCreate('fee_reminder', $studentId . ':manual:' . date('Y-m-d'), date('Y-m-d H:i:s'), null);
            $eventService->linkFeeStudent($eventId, (int) $studentId, 'manual');
            $queued = [];
            foreach (['sms', 'whatsapp', 'email'] as $channel) {
                $queued[$channel] = $platform->queueForStudentParents((int) $studentId, $channel, 'fees', [
                    'parent_name' => 'Parent/Guardian',
                    'amount_due' => number_format((float) $student['amount_due'], 2),
                    'student_name' => $student['student_name'],
                    'class_name' => '',
                    'due_date' => $student['due_date'] ?: '',
                ], [
                    'business_event_id' => $eventId,
                    'purpose' => 'fees',
                    'sender_id' => $this->user_id ?: 1,
                    'subject' => 'Fee Reminder: ' . $student['student_name'],
                ]);
            }
            $eventService->markProcessed($eventId);
            return formatResponse(true, ['message' => 'Fee reminders queued for SMS, WhatsApp and email.', 'channels' => $queued]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get fee collection rate for a period
     * @param int $academicYear Academic year
     * @param int $termId Term ID (optional)
     * @return array Response with collection statistics
     */
    public function getFeeCollectionRate($academicYear, $termId = null)
    {
        try {
            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_get_fee_collection_rate(?, ?)");
            $stmt->execute([$academicYear, $termId]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, $result);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get outstanding fees report
     * @param array $filters Filter criteria
     * @return array Response with outstanding fees data
     */
    public function getOutstandingFeesReport($filters = [])
    {
        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
        try {
            // Use the live balances view to derive per-student outstanding amounts
            $sql = "SELECT v.student_id,
                           v.academic_year,
                           v.academic_year_term_id AS term_id,
                           s.admission_no,
                           CONCAT(p.first_name, ' ', p.last_name) AS student_name,
                           c.id AS class_id,
                           sl.id AS level_id,
                           c.name AS class_name,
                           v.amount_due,
                           v.amount_paid,
                           v.balance AS outstanding_balance,
                           v.days_overdue
                    FROM $feeBalView v
                    JOIN students s ON v.student_id = s.id
                    LEFT JOIN persons p ON s.person_id = p.id
                    LEFT JOIN student_academic_enrollments sae ON sae.student_id = v.student_id
                        AND sae.academic_year_id = (
                            SELECT ay2.id FROM academic_years ay2
                            WHERE ay2.year_code = v.academic_year
                            LIMIT 1
                        )
                        AND sae.enrollment_status = 'active'
                    LEFT JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                    LEFT JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                    LEFT JOIN classes c ON ayc.class_id = c.id
                    LEFT JOIN school_levels sl ON c.level_id = sl.id
                    WHERE v.balance > 0";
            $params = [];

            if (!empty($filters['academic_year'])) {
                $yearId = $this->resolveAcademicYearId($filters['academic_year']);
                if (!$yearId) {
                    return formatResponse(true, [
                        'outstanding_fees' => [],
                        'summary' => ['total_outstanding' => 0, 'student_count' => 0, 'average_balance' => 0]
                    ]);
                }
                $sql .= " AND v.academic_year = (SELECT year_code FROM academic_years WHERE id = ?)";
                $params[] = $yearId;
            }

            if (!empty($filters['level_id'])) {
                $sql .= " AND sl.id = ?";
                $params[] = $filters['level_id'];
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND c.id = ?";
                $params[] = $filters['class_id'];
            }

            if (!empty($filters['min_balance'])) {
                $sql .= " AND v.balance >= ?";
                $params[] = $filters['min_balance'];
            }

            $sql .= " ORDER BY v.balance DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $outstandingFees = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary statistics
            $totalOutstanding = array_sum(array_column($outstandingFees, 'outstanding_balance'));
            $studentCount = count($outstandingFees);

            return formatResponse(true, [
                'outstanding_fees' => $outstandingFees,
                'summary' => [
                    'total_outstanding' => $totalOutstanding,
                    'student_count' => $studentCount,
                    'average_balance' => $studentCount > 0 ? $totalOutstanding / $studentCount : 0
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get student fee statement
     * @param int $studentId Student ID
     * @param int $academicYear Academic year
     * @return array Response with fee statement
     */
    public function getStudentFeeStatement($studentId, $academicYear)
    {
        try {
            if (empty($academicYear)) {
                $yearStmt = $this->db->prepare("SELECT year_code FROM academic_years WHERE is_current = 1 LIMIT 1");
                $yearStmt->execute();
                $currentYear = $yearStmt->fetchColumn();
                $academicYear = $currentYear ?: date('Y');
            }

            // Get student details
            $stmt = $this->db->prepare("
                SELECT
                    s.id,
                    s.admission_no,
                    CONCAT(p.first_name, ' ', p.last_name) as student_name,
                    c.name as class_name,
                    st.name as stream_name,
                    sl.name as level_name
                FROM students s
                LEFT JOIN persons p ON s.person_id = p.id
                LEFT JOIN student_academic_enrollments sae ON sae.student_id = s.id AND sae.enrollment_status = 'active'
                LEFT JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                LEFT JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                LEFT JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN streams st ON aycs.stream_id = st.id
                WHERE s.id = ?
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            $academicYearId = $this->resolveAcademicYearId($academicYear);

            // Get fee obligations
                        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
                        $stmt = $this->db->prepare("
                SELECT
                    sfo.id,
                    sfo.student_academic_enrollment_id,
                    sfo.academic_year_id,
                    sfo.academic_year_term_id AS term_id,
                    sfo.academic_year_fee_schedule_id,
                    sfo.amount_due,
                    sfo.status,
                    sfo.due_date,
                    sfo.is_sponsored,
                    sfo.sponsored_waiver_amount,
                    ay.year_code AS academic_year,
                    COALESCE(v.amount_paid, 0) AS amount_paid,
                    COALESCE(v.amount_waived, 0) AS amount_waived,
                    COALESCE(v.balance, sfo.amount_due) AS balance,
                    COALESCE(v.payment_status, 'pending') AS payment_status,
                    t.name AS term_name,
                    CAST(SUBSTRING(t.code, 2) AS UNSIGNED) AS term_number,
                    'School Fees' AS fee_type_name,
                    ayfs.amount AS configured_amount
                FROM student_fee_obligations sfo
                JOIN student_academic_enrollments sae ON sfo.student_academic_enrollment_id = sae.id
                JOIN academic_years ay ON sfo.academic_year_id = ay.id
                JOIN academic_year_terms ayt ON sfo.academic_year_term_id = ayt.id
                JOIN terms t ON ayt.term_id = t.id
                LEFT JOIN academic_year_fee_schedules ayfs ON sfo.academic_year_fee_schedule_id = ayfs.id
                LEFT JOIN $feeBalView v ON v.student_academic_enrollment_id = sfo.student_academic_enrollment_id AND v.academic_year_term_id = sfo.academic_year_term_id
                WHERE sae.student_id = ? AND sfo.academic_year_id = ?
                ORDER BY term_number ASC, sfo.id ASC
            ");
            $stmt->execute([$studentId, $academicYearId]);
            $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payments
            $stmt = $this->db->prepare("
                SELECT
                    p.id,
                    p.student_id,
                    p.receipt_no,
                    p.amount AS amount_paid,
                    p.payment_date,
                    p.method AS payment_method,
                    p.reference,
                    p.status,
                    p.status AS payment_status,
                    p.created_at,
                    ayt.id AS term_id,
                    t.name AS term_name,
                    CAST(SUBSTRING(t.code, 2) AS UNSIGNED) AS term_number,
                    ay.year_code AS academic_year
                FROM payments p
                LEFT JOIN academic_years ay ON p.payment_date BETWEEN ay.start_date AND ay.end_date
                LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND p.payment_date BETWEEN ayt.opening_date AND ayt.closing_date
                LEFT JOIN terms t ON ayt.term_id = t.id
                WHERE p.student_id = ? AND ay.id = ?
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$studentId, $academicYearId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $totalDue = array_sum(array_map(static fn($row) => (float) ($row['amount_due'] ?? 0), $obligations));
            $totalPaid = array_sum(array_map(static fn($row) => (float) ($row['amount_paid'] ?? 0), $obligations));
            $totalWaived = array_sum(array_map(static fn($row) => (float) ($row['amount_waived'] ?? 0), $obligations));
            $totalBalance = array_sum(array_map(static fn($row) => (float) ($row['balance'] ?? 0), $obligations));

            return formatResponse(true, [
                'student' => $student,
                'summary' => [
                    'total_due' => $totalDue,
                    'total_paid' => $totalPaid,
                    'total_waived' => $totalWaived,
                    'balance' => $totalBalance,
                ],
                'obligations' => $obligations,
                'payments' => $payments,
                'balance' => [
                    'balance' => $totalBalance
                ],
                'academic_year' => $academicYear
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Transition fees to new term
     * @param int $currentTermId Current term ID
     * @param int $newTermId New term ID
     * @return array Response
     */
    public function transitionToNewTerm($currentTermId, $newTermId)
    {
        try {
            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_transition_to_new_term(?, ?)");
            $stmt->execute([$currentTermId, $newTermId]);

            return formatResponse(true, ['message' => 'Fees transitioned to new term successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Transition fees to new academic year
     * @param int $currentYear Current academic year
     * @param int $newYear New academic year
     * @return array Response
     */
    public function transitionToNewAcademicYear($currentYear, $newYear)
    {
        try {
            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_transition_to_new_academic_year(?, ?)");
            $stmt->execute([$currentYear, $newYear]);

            return formatResponse(true, ['message' => 'Fees transitioned to new academic year successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get class fee schedule using view
     * @param int $classId Class ID
     * @param string $academicYear Academic year (optional)
     * @return array Response
     */
    public function getClassFeeSchedule($classId, $academicYear = null)
    {
        try {
            $sql = "SELECT sl.name AS level_name, sl.code AS level_code,
                           t.name AS academic_term,
                           st.name AS student_type, st.code AS student_type_code,
                           'School Fees' AS fee_name, 'school_fees' AS fee_category,
                           ayfs.amount AS amount_due, ayfs.amount AS amount, ayfs.due_date,
                           ay.year_code AS academic_year
                    FROM academic_year_fee_schedules ayfs
                    JOIN academic_year_classes ayc ON ayfs.academic_year_class_id = ayc.id
                    JOIN classes c ON ayc.class_id = c.id
                    JOIN school_levels sl ON c.level_id = sl.id
                    LEFT JOIN academic_year_terms ayt ON ayfs.academic_year_term_id = ayt.id
                    LEFT JOIN terms t ON ayt.term_id = t.id
                    LEFT JOIN student_types st ON ayfs.student_type_id = st.id
                    LEFT JOIN academic_years ay ON ayfs.academic_year_id = ay.id
                    WHERE ayc.class_id = ?";
            $params = [$classId];

            if ($academicYear) {
                $sql .= " AND (ay.year_code = ? OR YEAR(ay.start_date) = ?)";
                $params[] = $academicYear;
                $params[] = $academicYear;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'schedule' => $schedule,
                'total_fees' => array_sum(array_column($schedule, 'amount'))
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get fee carryover summary using view
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getFeeCarryoverSummary($filters = [])
    {
        try {
            $sql = "SELECT *, previous_balance AS carryover_amount FROM vw_fee_carryover_summary WHERE 1=1";
            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND student_id IN (SELECT sae.student_id FROM student_academic_enrollments sae JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id WHERE ayc.class_id = ?)";
                $params[] = $filters['class_id'];
            }

            $sql .= " ORDER BY previous_balance DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'summary' => $summary,
                'total_carryover' => array_sum(array_column($summary, 'carryover_amount')),
                'students_affected' => count($summary)
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get fee transition audit trail using view
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getFeeTransitionAudit($filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_fee_transition_audit WHERE 1=1";
            $params = [];

            if (!empty($filters['student_id'])) {
                $sql .= " AND student_id = ?";
                $params[] = $filters['student_id'];
            }

            if (!empty($filters['from_year'])) {
                $sql .= " AND from_academic_year = ?";
                $params[] = $filters['from_year'];
            }

            if (!empty($filters['to_year'])) {
                $sql .= " AND to_academic_year = ?";
                $params[] = $filters['to_year'];
            }

            $sql .= " ORDER BY created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'audit_trail' => $audit,
                'total_transitions' => count($audit)
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get fee type collection summary using view
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getFeeTypeCollection($filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_fee_type_collection WHERE 1=1";
            $params = [];

            if (!empty($filters['fee_type'])) {
                $sql .= " AND fee_type = ?";
                $params[] = $filters['fee_type'];
            }

            $sql .= " ORDER BY total_collected DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $collection = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'collection_by_type' => $collection,
                'total_collected' => array_sum(array_column($collection, 'total_collected')),
                'total_outstanding' => array_sum(array_column($collection, 'total_outstanding'))
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Calculate student fee due using database function
     * @param int $studentId Student ID
     * @param int $termId Term ID
     * @return array Response
     */
    public function calculateStudentFeeDue($studentId, $termId)
    {
        try {
            $stmt = $this->db->prepare("SELECT fn_student_fee_due(?, ?) as fee_due");
            $stmt->execute([$studentId, $termId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'student_id' => $studentId,
                'term_id' => $termId,
                'fee_due' => (float) $result['fee_due']
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Send batch fee reminders using stored procedure
     * @return array Response
     */
    public function sendBatchFeeReminders()
    {
        try {
                        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
                        $stmt = $this->db->query("SELECT DISTINCT student_id FROM $feeBalView WHERE balance > 0");
            $queued = 0;
            $failed = 0;
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $studentId) {
                $result = $this->sendFeeReminder((int) $studentId);
                if (($result['success'] ?? false) === true) $queued++; else $failed++;
            }
            return formatResponse(true, ['message' => 'Batch fee reminders queued.', 'queued_students' => $queued, 'failed_students' => $failed]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // =====================================================
    // ACADEMIC YEAR FEE STRUCTURE MANAGEMENT
    // =====================================================

    /**
     * Create annual fee structure with term breakdown
     * @param array $data Contains: academic_year, level_id, student_type_id, term_breakdown
     * @return array Response
     */
    public function createAnnualFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id', 'student_type_id', 'term_breakdown', 'created_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // term_breakdown format:
            // [
            //     'tuition' => ['term1' => 15000, 'term2' => 15000, 'term3' => 15000],
            //     'transport' => ['term1' => 3000, 'term2' => 3000, 'term3' => 3000],
            //     ...
            // ]

            $this->db->beginTransaction();

            $structuresCreated = 0;

            // Resolve academic year and get terms for this academic year
            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                throw new Exception("No academic year found for {$data['academic_year']}");
            }

            $terms = $this->getYearTermMap($academicYearId);

            if (empty($terms)) {
                throw new Exception("No terms found for academic year {$data['academic_year']}");
            }

            $termMap = [];
            foreach ($terms as $termNumber => $term) {
                $termMap[$termNumber] = $term['id'];
            }

            // Resolve academic_year_class_id for all classes in this level
            $fyClassAfIds = $data["class_ids"] ?? null;
            if (empty($fyClassAfIds)) {
                $stmt = $this->db->prepare("
                    SELECT ayc.id, c.id AS class_id
                    FROM academic_year_classes ayc
                    JOIN classes c ON c.id = ayc.class_id
                    WHERE c.level_id = ? AND ayc.academic_year_id = ?
                ");
                $stmt->execute([$data['level_id'], $academicYearId]);
                $aycIdMap = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $aycIdMap[(int) $r['class_id']] = (int) $r['id'];
                }
            } else {
                $phs = implode(',', array_fill(0, count($fyClassAfIds), '?'));
                $params = array_merge($fyClassAfIds, [$academicYearId]);
                $stmt = $this->db->prepare("
                    SELECT ayc.id, c.id AS class_id
                    FROM academic_year_classes ayc
                    JOIN classes c ON c.id = ayc.class_id
                    WHERE ayc.class_id IN ($phs) AND ayc.academic_year_id = ?
                ");
                $stmt->execute($params);
                $aycIdMap = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $aycIdMap[(int) $r['class_id']] = (int) $r['id'];
                }
            }

            if (empty($aycIAll)) {
                throw new Exception("No academic_year_classes found for level={$data['level_id']} year={$data['academic_year']}");
            }

            // Create fee structures for each fee type and term — one per class
            foreach ($data['term_breakdown'] as $feeTypeName => $termAmounts) {
                // Resolve the fee catalog entry from name/code
                $catalogId = $this->resolveFeeCatalogId($feeTypeName, $data['student_type_id']);

                if (!$catalogId) {
                    throw new Exception("Fee type '{$feeTypeName}' not found");
                }

                // Insert fee structure for each term × each class in level
                foreach ($termAmounts as $termKey => $amount) {
                    $termNumber = (int) str_replace('term', '', $termKey);

                    if (!isset($termMap[$termNumber])) {
                        continue;
                    }

                    $aytId = $termMap[$termNumber];

                    foreach ($aycIdMap as $classId => $aycId) {
                        // Archive previous active version for this exact (year, term, ayc, st, catalog) key
                        $this->db->prepare("
                            UPDATE academic_year_fee_schedules
                            SET status = 'archived', updated_at = NOW()
                            WHERE academic_year_id = ? AND academic_year_term_id = ?
                            AND academic_year_class_id = ? AND student_type_id = ?
                            AND fee_catalog_id = ? AND status = 'active'
                        ")->execute([$academicYearId, $aytId, $aycId, $data['student_type_id'], $catalogId]);

                        $this->db->prepare("
                            INSERT INTO academic_year_fee_schedules (
                                academic_year_id, academic_year_term_id, academic_year_class_id,
                                student_type_id, fee_catalog_id, amount, status, created_by, created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
                        ")->execute([
                            $academicYearId, $aytId, $aycId,
                            $data['student_type_id'], $catalogId, $amount, $data['created_by']
                        ]);
                        $insertId = (int) $this->db->lastInsertId();

                        \App\API\Includes\FileLogger::write('finance', [
                            'type' => 'audit',
                            'action' => 'fee_structure_drafted',
                            'entity' => 'academic_year_fee_schedule',
                            'entity_id' => $insertId,
                            'user_id' => $data['created_by'],
                            'details' => [
                                'stage' => 'drafted',
                                'old_amount' => null,
                                'new_amount' => $amount,
                                'notes' => 'Initial annual fee structure draft',
                            ],
                            'status' => 'success',
                        ]);

                        $structuresCreated++;
                    }
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'structures_created' => $structuresCreated,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'student_type_id' => $data['student_type_id'],
                'class_count' => count($aycIdMap),
                'message' => 'Annual fee structure created successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Create (or re-create) a fee structure bundle for a grade range.
     *
     * One bundle covers ALL fee items x ALL terms x ALL student types for the
     * classes within the grade range. Previous active rows for the same
     * (academic_year, term, class, student_type, fee_catalog) key are archived
     * (status='inactive', academic_year_class_id=NULL) so the UNIQUE key can
     * receive a fresh active row; amount changes are propagated to
     * pending/partial obligations via sp_propagate_fee_schedule_changes.
     *
     * @param array $data {
     *   academic_year: 2026,
     *   grade_range: {from_id: 4, to_id: 12},   // class id range
     *   student_type_ids: [1, 2, 3],
     *   items: {
     *     "TUITION": { "term1": {1: 15000, 2: 18000, 3: 18000}, ... },
     *     "BOARDING": { "term1": {2: 8000, 3: 8000}, ... }
     *   },
     *   created_by: 5,
     *   notes: "Fee structure for 2026/2027"
     * }
     * @return array Response with creation counts
     */
    public function createFeeStructureBundle($data)
    {
        try {
            $required = ['academic_year', 'grade_range', 'student_type_ids', 'items', 'created_by'];
            $missing = array_diff($required, array_keys($data));
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $gradeRange = $data['grade_range'];
            $fromId = (int) ($gradeRange['from_id'] ?? 0);
            $toId = (int) ($gradeRange['to_id'] ?? 0);
            if ($fromId <= 0 || $toId < $fromId) {
                return formatResponse(false, null, 'Invalid grade range');
            }

            $studentTypeIds = array_values(array_unique(array_filter(array_map('intval', (array) $data['student_type_ids']))));
            if (empty($studentTypeIds)) {
                return formatResponse(false, null, 'At least one student type is required');
            }

            $items = $data['items'];
            if (empty($items) || !is_array($items)) {
                return formatResponse(false, null, 'No fee items provided');
            }

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                return formatResponse(false, null, 'Academic year not found');
            }

            $terms = $this->getYearTermMap($academicYearId);
            if (empty($terms)) {
                return formatResponse(false, null, 'No terms found for the selected academic year');
            }

            $stmt = $this->db->prepare("
                SELECT ayc.id, c.id AS class_id
                FROM academic_year_classes ayc
                JOIN classes c ON c.id = ayc.class_id
                WHERE ayc.academic_year_id = ? AND c.id BETWEEN ? AND ?
                ORDER BY c.id
            ");
            $stmt->execute([$academicYearId, $fromId, $toId]);
            $classRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($classRows)) {
                return formatResponse(false, null, 'No classes found for the selected grade range in this academic year');
            }

            $this->db->beginTransaction();

            // Some legacy installations do not have the optional propagation
            // procedure. Drafting must still work there; propagation is an
            // enhancement, not a prerequisite for saving the schedule.
            $procedureCheck = $this->db->prepare(
                "SELECT COUNT(*) FROM information_schema.ROUTINES
                 WHERE ROUTINE_SCHEMA = DATABASE()
                   AND ROUTINE_TYPE = 'PROCEDURE'
                   AND ROUTINE_NAME = 'sp_propagate_fee_schedule_changes'"
            );
            $procedureCheck->execute();
            $propagationAvailable = (bool) $procedureCheck->fetchColumn();

            $rowsCreated = 0;
            $rowsArchived = 0;
            $obligationsUpdated = 0;
            $creditsIssued = 0;
            $feeAdjustments = 0;

            $catalogCache = [];
            foreach ($items as $feeCode => $termAmounts) {
                if (!is_array($termAmounts)) {
                    continue;
                }
                foreach ($termAmounts as $termKey => $typeAmounts) {
                    $termNumber = (int) str_replace('term', '', (string) $termKey);
                    if ($termNumber <= 0 || !isset($terms[$termNumber])) {
                        continue;
                    }
                    $aytId = (int) $terms[$termNumber]['id'];
                    $typeAmounts = (is_array($typeAmounts)) ? $typeAmounts : [];

                    foreach ($studentTypeIds as $stId) {
                        $raw = $typeAmounts[$stId] ?? null;
                        // $raw can be:
                        //   - a scalar number  → same amount for all classes in range (legacy format)
                        //   - an associative array { classId: amount } → per-class amounts (simple mode)
                        $isPerClass = is_array($raw) && !empty($raw);

                        $cacheKey = $feeCode . '|' . $stId;
                        if (!array_key_exists($cacheKey, $catalogCache)) {
                            $catalogCache[$cacheKey] = $this->resolveFeeCatalogId($feeCode, $stId);
                        }
                        $catalogId = $catalogCache[$cacheKey];
                        if (!$catalogId) {
                            throw new Exception("Fee type '{$feeCode}' not found");
                        }

                        foreach ($classRows as $classRow) {
                            $aycId = (int) $classRow['id'];
                            $classId = (int) $classRow['class_id'];

                            // Resolve amount: per-class map or scalar
                            if ($isPerClass) {
                                $classRaw = $raw[$classId] ?? $raw[$aycId] ?? null;
                                $hasAmount = ($classRaw !== null && $classRaw !== '');
                                $amount = $hasAmount ? (float) $classRaw : null;
                            } else {
                                $hasAmount = ($raw !== null && $raw !== '');
                                $amount = $hasAmount ? (float) $raw : null;
                            }

                            // Keep the old schedule ids before archiving them so
                            // existing obligations can be reconciled safely.
                            $oldRowsStmt = $this->db->prepare("
                                SELECT id, amount
                                FROM academic_year_fee_schedules
                                WHERE academic_year_id = ? AND academic_year_term_id = ?
                                  AND academic_year_class_id = ? AND student_type_id = ?
                                  AND fee_catalog_id = ? AND status = 'active'
                            ");
                            $oldRowsStmt->execute([$academicYearId, $aytId, $aycId, $stId, $catalogId]);
                            $oldRows = $oldRowsStmt->fetchAll(PDO::FETCH_ASSOC);

                            $archiveStmt = $this->db->prepare("
                                UPDATE academic_year_fee_schedules
                                SET status = 'inactive', academic_year_class_id = NULL, updated_at = NOW()
                                WHERE academic_year_id = ? AND academic_year_term_id = ?
                                  AND academic_year_class_id = ? AND student_type_id = ?
                                  AND fee_catalog_id = ? AND status = 'active'
                            ");
                            $archiveStmt->execute([$academicYearId, $aytId, $aycId, $stId, $catalogId]);
                            $archivedHere = (int) $archiveStmt->rowCount();
                            $rowsArchived += $archivedHere;

                            if (!$hasAmount) {
                                // Cell cleared means the charge is removed. Reconcile
                                // existing obligations to zero instead of leaving an
                                // old charge behind. A zero new_schedule_id records
                                // that no replacement schedule exists.
                                foreach ($oldRows as $oldRow) {
                                    if ((float) $oldRow['amount'] == 0.0) {
                                        continue;
                                    }
                                    $reconciliation = $this->reconcileScheduleChange(
                                        (int) $oldRow['id'],
                                        0,
                                        (float) $oldRow['amount'],
                                        0.0,
                                        isset($data['created_by']) ? (int) $data['created_by'] : null
                                    );
                                    $feeAdjustments += (int) ($reconciliation['repriced'] ?? 0);
                                    $creditsIssued += (int) ($reconciliation['credits'] ?? 0);
                                }
                                // Cell cleared -> nothing to (re)create for this class.
                                continue;
                            }

                            $this->db->prepare("
                                INSERT INTO academic_year_fee_schedules (
                                    academic_year_id, academic_year_term_id, academic_year_class_id,
                                    student_type_id, fee_catalog_id, amount, status, created_by, created_at, updated_at
                                ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())
                            ")->execute([
                                $academicYearId, $aytId, $aycId, $stId, $catalogId, $amount, $data['created_by']
                            ]);
                            $insertId = (int) $this->db->lastInsertId();
                            $rowsCreated++;

                            foreach ($oldRows as $oldRow) {
                                if ((float) $oldRow['amount'] === (float) $amount) {
                                    continue;
                                }
                                $reconciliation = $this->reconcileScheduleChange(
                                    (int) $oldRow['id'],
                                    $insertId,
                                    (float) $oldRow['amount'],
                                    (float) $amount,
                                    isset($data['created_by']) ? (int) $data['created_by'] : null
                                );
                                $feeAdjustments += (int) ($reconciliation['repriced'] ?? 0);
                                $creditsIssued += (int) ($reconciliation['credits'] ?? 0);
                            }

                            \App\API\Includes\FileLogger::write('finance', [
                                'type' => 'audit',
                                'action' => 'fee_structure_drafted',
                                'entity' => 'academic_year_fee_schedule',
                                'entity_id' => $insertId,
                                'user_id' => $data['created_by'],
                                'details' => [
                                    'stage' => 'drafted',
                                    'old_amount' => null,
                                    'new_amount' => $amount,
                                    'notes' => $data['notes'] ?? 'Grade-range fee structure bundle draft',
                                ],
                                'status' => 'success',
                            ]);

                            if ($archivedHere > 0 && $propagationAvailable) {
                                $propStmt = $this->db->prepare(
                                    "CALL sp_propagate_fee_schedule_changes(?, ?, @kwa_prop_updated, @kwa_prop_credits)"
                                );
                                $propStmt->execute([$insertId, $data['created_by']]);
                                $sum = $this->db->query("SELECT @kwa_prop_updated AS u, @kwa_prop_credits AS c")->fetch(PDO::FETCH_ASSOC);
                                $obligationsUpdated += (int) ($sum['u'] ?? 0);
                                $creditsIssued += (int) ($sum['c'] ?? 0);
                            }
                        }
                    }
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'total_rows_created' => $rowsCreated,
                'total_rows_archived' => $rowsArchived,
                'obligations_updated' => $obligationsUpdated,
                'credits_issued' => $creditsIssued,
                'fee_adjustments' => $feeAdjustments,
                'class_count' => count($classRows),
                'grade_range' => ['from_id' => $fromId, 'to_id' => $toId],
                'academic_year' => $data['academic_year'],
                'message' => 'Fee structure bundle created successfully',
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[FeeManager] createFeeStructureBundle: ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Read a fee structure bundle as an editable tabular grid.
     *
     * Returns active schedule rows for the given grade range, pivoted into the
     * same shape the create form submits (items keyed by fee-type code, each
     * holding per-term maps of student_type_id => amount).
     *
     * @param array $data Contains: academic_year, grade_range {from_id,to_id}, student_type_ids[]
     * @return array Response with the pivoted grid
     */
    public function getFeeStructureBundleGrid($data)
    {
        try {
            $academicYear = $data['academic_year'] ?? null;
            $gradeRange = $data['grade_range'] ?? null;
            if (!$academicYear || !$gradeRange) {
                return formatResponse(false, null, 'academic_year and grade_range are required');
            }

            $fromId = (int) ($gradeRange['from_id'] ?? 0);
            $toId = (int) ($gradeRange['to_id'] ?? 0);
            if ($fromId <= 0 || $toId < $fromId) {
                return formatResponse(false, null, 'Invalid grade range');
            }

            $academicYearId = $this->resolveAcademicYearId($academicYear);
            if (!$academicYearId) {
                return formatResponse(false, null, 'Academic year not found');
            }

            $sql = "
                SELECT ayfs.id AS schedule_id, c.id AS class_id, c.name AS class_name,
                       'SCHOOL_FEES' AS fee_code, 'School Fees' AS fee_name,
                       ayfs.student_type_id, ayfs.amount, t.code AS term_code
                FROM academic_year_fee_schedules ayfs
                JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
                JOIN terms t ON t.id = ayt.term_id
                WHERE ayfs.academic_year_id = ? AND ayfs.status = 'active'
                  AND c.id BETWEEN ? AND ?
            ";
            $params = [$academicYearId, $fromId, $toId];

            $rawStudentTypeIds = $data['student_type_ids'] ?? [];
            if (is_string($rawStudentTypeIds)) {
                $rawStudentTypeIds = preg_split('/\s*,\s*/', $rawStudentTypeIds, -1, PREG_SPLIT_NO_EMPTY);
            }
            $studentTypeIds = array_values(array_unique(array_filter(array_map('intval', (array) $rawStudentTypeIds))));
            if (!empty($studentTypeIds)) {
                $phs = implode(',', array_fill(0, count($studentTypeIds), '?'));
                $sql .= " AND ayfs.student_type_id IN ($phs)";
                $params = array_merge($params, $studentTypeIds);
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $items = [];
            $classGrid = [];
            $classIds = [];
            $typesFound = [];
            $terms = $this->getYearTermMap($academicYearId);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = $row['fee_code'];
                $termNumber = (int) ltrim((string) $row['term_code'], 'Tt');
                $stId = (int) $row['student_type_id'];
                $clsId = (int) $row['class_id'];
                $termKey = 'term' . $termNumber;

                // Legacy aggregated view (per-student-type)
                if (!isset($items[$code])) {
                    $items[$code] = ['name' => $row['fee_name'], 'terms' => []];
                }
                if (!isset($items[$code]['terms'][$termKey])) {
                    $items[$code]['terms'][$termKey] = [];
                }
                $items[$code]['terms'][$termKey][$stId] = (float) $row['amount'];

                // Per-class grid: classGrid[classId][code][termKey][studentTypeId] = amount
                if (!isset($classGrid[$clsId])) {
                    $classGrid[$clsId] = [];
                }
                if (!isset($classGrid[$clsId][$code])) {
                    $classGrid[$clsId][$code] = [];
                }
                if (!isset($classGrid[$clsId][$code][$termKey])) {
                    $classGrid[$clsId][$code][$termKey] = [];
                }
                $classGrid[$clsId][$code][$termKey][$stId] = (float) $row['amount'];

                $classIds[$clsId] = $row['class_name'];
                $typesFound[$stId] = true;
            }

            $termList = [];
            foreach ($terms as $number => $term) {
                $termList[] = ['number' => $number, 'name' => $term['term_name']];
            }

            $resolvedFrom = $fromId;
            $resolvedTo = $toId;
            if (!empty($classIds)) {
                $ids = array_map('intval', array_keys($classIds));
                $resolvedFrom = min($ids);
                $resolvedTo = max($ids);
            }

            return formatResponse(true, [
                'academic_year' => $academicYear,
                'grade_range' => ['from_id' => $resolvedFrom, 'to_id' => $resolvedTo],
                'classes' => $classIds,
                'student_type_ids' => array_keys($typesFound),
                'terms' => $termList,
                'items' => $items,
                'class_grid' => $classGrid,
            ]);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FeeManager] getFeeStructureBundleGrid: ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Review fee structure (Director action)
     * @param array $data Contains: academic_year, level_id, reviewed_by, notes
     * @return array Response
     */
    public function reviewFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id', 'reviewed_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Academic year not found');
            }

            $sql = "
                UPDATE academic_year_fee_schedules
                SET approved_by = ?,
                    approved_at = NOW()
                WHERE academic_year_id = ?
                AND status = 'active'
            ";

            $params = [
                $data['reviewed_by'],
                $academicYearId
            ];

            if (!empty($data['student_type_id'])) {
                $sql .= " AND student_type_id = ?";
                $params[] = $data['student_type_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $updatedCount = $stmt->rowCount();

            $this->db->commit();

            return formatResponse(true, [
                'structures_reviewed' => $updatedCount,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'message' => 'Fee structures reviewed successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Approve fee structure (Director action)
     * @param array $data Contains: academic_year, level_id, approved_by, notes
     * @return array Response
     */
    public function approveFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id', 'approved_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Academic year not found');
            }

            $sql = "
                UPDATE academic_year_fee_schedules
                SET approved_by = ?,
                    approved_at = NOW()
                WHERE academic_year_id = ?
                AND status = 'active'
            ";

            $params = [
                $data['approved_by'],
                $academicYearId
            ];

            if (!empty($data['student_type_id'])) {
                $sql .= " AND student_type_id = ?";
                $params[] = $data['student_type_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $updatedCount = $stmt->rowCount();

            $this->db->commit();

            if ($updatedCount > 0) {
                $this->notifyFeeStructurePublished((string) $data['academic_year'], (int) ($data['approved_by'] ?? 0));
            }

            return formatResponse(true, [
                'structures_approved' => $updatedCount,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'message' => 'Fee structures approved successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Push a staff-wide notification that a fee structure is out.
     * De-duplicated so repeated approvals don't spam.
     */
    private function notifyFeeStructurePublished(string $academicYear, int $actorUserId): void
    {
        try {
            $title = 'Fee structure released';
            $message = $academicYear !== ''
                ? 'The ' . $academicYear . ' fee structure is now out.'
                : 'The new fee structure is now out.';
            $service = new NotificationService($this->db);
            $recipients = $actorUserId > 0
                ? array_values(array_filter($service->allStaffUserIds(), fn ($uid) => $uid !== $actorUserId))
                : 'all_staff';
            $service->push($recipients, 'fee_structure', $title, $message, 'high', ['dedup_minutes' => 60]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FeeManager] Notification push failed: ' . $e->getMessage());
        }
    }

    /**
     * Activate fee structure (make it live)
     * @param array $data Contains: academic_year, level_id
     * @return array Response
     */
    public function activateFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Academic year not found');
            }

            $sql = "
                UPDATE academic_year_fee_schedules
                SET status = 'active',
                    approved_at = NOW()
                WHERE academic_year_id = ?
                AND status = 'active'
            ";

            $params = [
                $academicYearId
            ];

            if (!empty($data['student_type_id'])) {
                $sql .= " AND student_type_id = ?";
                $params[] = $data['student_type_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $updatedCount = $stmt->rowCount();

            $this->db->commit();

            return formatResponse(true, [
                'structures_activated' => $updatedCount,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'message' => 'Fee structures activated successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Deactivate fee structure (archive it)
     * @param array $data Contains: academic_year, level_id
     * @return array Response
     */
    public function deactivateFeeStructure($data)
    {
        try {
            $required = ['academic_year', 'level_id'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Academic year not found');
            }

            $sql = "
                UPDATE academic_year_fee_schedules
                SET status = 'archived'
                WHERE academic_year_id = ?
                AND status = 'active'
            ";

            $params = [
                $academicYearId
            ];

            if (!empty($data['student_type_id'])) {
                $sql .= " AND student_type_id = ?";
                $params[] = $data['student_type_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $updatedCount = $stmt->rowCount();

            $this->db->commit();

            return formatResponse(true, [
                'structures_deactivated' => $updatedCount,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'message' => 'Fee structures deactivated successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Rollover fee structures from one academic year to another
     * @param array $data Contains: source_year, target_year, executed_by
     * @return array Response
     */
    public function rolloverFeeStructure($data)
    {
        try {
            $required = ['source_year', 'target_year'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_auto_rollover_fee_structures(?, ?, ?, @copied, @log_id)");
            $stmt->execute([
                $data['source_year'],
                $data['target_year'],
                isset($data['apply_increase']) ? (int) $data['apply_increase'] : 0
            ]);

            // Get output parameters
            $stmt = $this->db->query("SELECT @copied as structures_copied, @log_id as rollover_log_id");
            $output = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$output) {
                $output = ['structures_copied' => 0, 'rollover_log_id' => null];
            }

            return formatResponse(true, [
                'structures_copied' => (int) $output['structures_copied'],
                'rollover_log_id' => (int) $output['rollover_log_id'],
                'source_year' => $data['source_year'],
                'target_year' => $data['target_year'],
                'message' => 'Fee structures rolled over successfully'
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get term breakdown for a specific academic year and level
     * @param int $academicYear Academic year
     * @param int $levelId Level ID
     * @return array Response with term breakdown
     */
    public function getTermBreakdown($academicYear, $levelId)
    {
        try {
            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_get_fee_breakdown_for_review(?, ?)");
            $stmt->execute([$academicYear, $levelId]);

            $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Organize by fee type
            $organized = [];
            foreach ($breakdown as $row) {
                $feeType = $row['fee_type'];

                if (!isset($organized[$feeType])) {
                    $organized[$feeType] = [
                        'fee_type' => $feeType,
                        'category' => $row['category'],
                        'status' => $row['status'],
                        'is_auto_rollover' => (bool) $row['is_auto_rollover'],
                        'reviewed_by' => $row['reviewer_name'],
                        'reviewed_at' => $row['reviewed_at'],
                        'approved_by' => $row['approver_name'],
                        'approved_at' => $row['approved_at'],
                        'terms' => [],
                        'annual_total' => 0
                    ];
                }

                $organized[$feeType]['terms'][] = [
                    'term_number' => $row['term_number'],
                    'term_name' => $row['term_name'],
                    'amount' => (float) $row['amount']
                ];

                $organized[$feeType]['annual_total'] += (float) $row['amount'];

                // Add year-over-year comparison
                if ($row['previous_year_amount']) {
                    $organized[$feeType]['previous_year_amount'] = (float) $row['previous_year_amount'];
                    $organized[$feeType]['amount_change'] = (float) $row['amount_change'];
                    $organized[$feeType]['percent_change'] = (float) $row['percent_change'];
                }
            }

            return formatResponse(true, [
                'academic_year' => $academicYear,
                'level_id' => $levelId,
                'fee_breakdown' => array_values($organized)
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get student payment history across multiple academic years
     * @param int $studentId Student ID
     * @param int|null $academicYear Optional: filter by specific year
     * @return array Response with payment history
     */
    public function getStudentPaymentHistory($studentId, $academicYear = null)
    {
        try {
            $sql = "SELECT * FROM vw_student_payment_history_multi_year WHERE student_id = ?";
            $params = [$studentId];

            if ($academicYear) {
                $sql .= " AND academic_year = ?";
                $params[] = $academicYear;
            }

            $sql .= " ORDER BY academic_year DESC, term_number";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $totalPaid = 0;
            $totalDue = 0;
            $totalBalance = 0;

            foreach ($history as $record) {
                $totalPaid += (float) $record['total_paid'];
                $totalDue += (float) $record['amount_due'];
                $totalBalance += (float) $record['balance'];
            }

            return formatResponse(true, [
                'student_id' => $studentId,
                'academic_year_filter' => $academicYear,
                'summary' => [
                    'total_paid' => $totalPaid,
                    'total_due' => $totalDue,
                    'total_balance' => $totalBalance
                ],
                'history' => $history
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Compare yearly fee collections between two academic years
     * @param int $year1 First academic year
     * @param int $year2 Second academic year
     * @return array Response with comparison data
     */
    public function compareYearlyCollections($year1, $year2)
    {
        try {
            $sql = "SELECT * FROM vw_fee_collection_by_year WHERE academic_year IN (?, ?) ORDER BY academic_year";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$year1, $year2]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($results) < 2) {
                return formatResponse(false, null, 'Insufficient data for comparison');
            }

            $data1 = $results[0];
            $data2 = $results[1];

            $comparison = [
                'year1' => [
                    'academic_year' => $data1['academic_year'],
                    'total_students' => (int) $data1['total_students'],
                    'total_fees_due' => (float) $data1['total_fees_due'],
                    'total_collected' => (float) $data1['total_collected'],
                    'collection_rate' => (float) $data1['collection_rate_percent']
                ],
                'year2' => [
                    'academic_year' => $data2['academic_year'],
                    'total_students' => (int) $data2['total_students'],
                    'total_fees_due' => (float) $data2['total_fees_due'],
                    'total_collected' => (float) $data2['total_collected'],
                    'collection_rate' => (float) $data2['collection_rate_percent']
                ],
                'differences' => [
                    'students_change' => (int) $data2['total_students'] - (int) $data1['total_students'],
                    'fees_due_change' => (float) $data2['total_fees_due'] - (float) $data1['total_fees_due'],
                    'collected_change' => (float) $data2['total_collected'] - (float) $data1['total_collected'],
                    'collection_rate_change' => (float) $data2['collection_rate_percent'] - (float) $data1['collection_rate_percent']
                ]
            ];

            return formatResponse(true, $comparison);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get pending fee structure reviews (for Director dashboard)
     * @return array Response with pending reviews
     */
    public function getPendingReviews()
    {
        try {
            $stmt = $this->db->query("SELECT * FROM vw_pending_fee_structure_reviews ORDER BY priority DESC, days_until_start");
            $pendingReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'pending_count' => count($pendingReviews),
                'reviews' => $pendingReviews
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get annual fee structure summary (for Director dashboard)
     * @param int $academicYear Academic year
     * @param int|null $levelId Optional: filter by level
     * @return array Response with summary
     */
    public function getAnnualFeeSummary($academicYear, $levelId = null)
    {
        try {
            $yearId = $this->resolveAcademicYearId($academicYear);
            if (!$yearId) {
                return formatResponse(true, [
                    'academic_year' => $academicYear,
                    'level_filter' => $levelId,
                    'summary' => []
                ]);
            }

            $yearStmt = $this->db->prepare('SELECT year_code FROM academic_years WHERE id = ? LIMIT 1');
            $yearStmt->execute([$yearId]);
            $yearCode = $yearStmt->fetchColumn();
            $sql = "SELECT * FROM vw_fee_structure_annual_summary WHERE academic_year = ?";
            $params = [$yearCode];

            if ($levelId) {
                $sql .= " AND level_id = ?";
                $params[] = $levelId;
            }

            $sql .= " ORDER BY level_name, fee_category, fee_type";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'academic_year' => $academicYear,
                'level_filter' => $levelId,
                'summary' => $summary
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Delete a fee structure
     * @param int $structureId Fee structure ID
     * @return array Response
     */
    public function deleteFeeStructure($structureId)
    {
        try {
            // Check if structure is in use
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM student_fee_obligations
                WHERE academic_year_fee_schedule_id = ?
            ");
            $stmt->execute([$structureId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                return formatResponse(false, null, 'Cannot delete: Fee structure is in use by ' . $result['count'] . ' student(s)');
            }

            // Delete the structure
            $stmt = $this->db->prepare("DELETE FROM academic_year_fee_schedules WHERE id = ?");
            $stmt->execute([$structureId]);

            return formatResponse(true, null, 'Fee structure deleted successfully');

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Duplicate a fee structure for a new academic year
     * @param int $sourceStructureId Source structure ID
     * @param array $data Target year and optional price adjustment
     * @return array Response with new structure ID
     */
    public function duplicateFeeStructure($sourceStructureId, $data)
    {
        try {
            $targetYear = $data['target_academic_year'] ?? null;
            $priceAdjustment = floatval($data['price_adjustment'] ?? 0);

            if ($targetYear === null) {
                return formatResponse(false, null, 'Target academic year is required');
            }

            // Get source structure
            $stmt = $this->db->prepare("SELECT * FROM academic_year_fee_schedules WHERE id = ?");
            $stmt->execute([$sourceStructureId]);
            $sourceStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sourceStructure) {
                return formatResponse(false, null, 'Source fee structure not found');
            }

            $targetYearId = $this->resolveAcademicYearId($targetYear);
            if (!$targetYearId) {
                return formatResponse(false, null, 'Target academic year not found');
            }

            // Create new structure record with price adjustment
            $multiplier = (100 + $priceAdjustment) / 100;
            $stmt = $this->db->prepare("
                INSERT INTO academic_year_fee_schedules
                (academic_year_id, academic_year_term_id, academic_year_class_id, student_type_id,
                 fee_catalog_id, amount, due_date, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                $targetYearId,
                $sourceStructure['academic_year_term_id'],
                $sourceStructure['academic_year_class_id'],
                $sourceStructure['student_type_id'],
                $sourceStructure['fee_catalog_id'],
                $sourceStructure['amount'] * $multiplier,
                $sourceStructure['due_date'],
                $data['created_by'] ?? null
            ]);

            return formatResponse(true, [
                'new_structure_id' => $newStructureId,
                'source_structure_id' => $sourceStructureId,
                'target_academic_year' => $targetYear,
                'price_adjustment' => $priceAdjustment,
                'items_copied' => 1
            ], 'Fee structure duplicated successfully');

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // =====================================================
    // FEE BUNDLE WORKFLOW
    // =====================================================

    /**
     * Submit a fee structure bundle for review
     * @param array $data Contains: level_id, academic_year, term_id, student_type_id, submitted_by, notes (optional)
     * @return array Response with approval record and line item count
     */
    public function submitFeeStructureBundle($data)
    {
        try {
            $required = ['level_id', 'academic_year', 'term_id', 'student_type_id', 'submitted_by'];
            $missing = array_diff($required, array_keys($data));
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $academicYearId = $this->resolveAcademicYearId($data['academic_year']);
            if (!$academicYearId) {
                return formatResponse(false, null, 'Academic year not found');
            }

            // Validate that schedule rows exist for this bundle
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS cnt
                FROM academic_year_fee_schedules
                WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
            ");
            $stmt->execute([
                $academicYearId,
                $data['term_id'],
                $data['student_type_id'],
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lineItemCount = (int) $row['cnt'];

            if ($lineItemCount === 0) {
                return formatResponse(false, null, 'No draft fee structure rows found for this bundle');
            }

            $this->db->beginTransaction();

            // Mark the schedule rows as submitted (records submitter, clears prior approval)
            $stmt = $this->db->prepare("
                UPDATE academic_year_fee_schedules
                SET approved_by = ?, approved_at = NULL, updated_at = NOW()
                WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
            ");
            $stmt->execute([
                $data['submitted_by'],
                $academicYearId,
                $data['term_id'],
                $data['student_type_id'],
            ]);

            // A bundle is identified by the earliest schedule row in its group
            $bundleStmt = $this->db->prepare("
                SELECT MIN(id) FROM academic_year_fee_schedules
                WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
            ");
            $bundleStmt->execute([
                $academicYearId,
                $data['term_id'],
                $data['student_type_id'],
            ]);
            $approvalId = (int) $bundleStmt->fetchColumn();

            $approval = [
                'id' => $approvalId,
                'level_id' => (int) $data['level_id'],
                'academic_year' => $data['academic_year'],
                'term_id' => (int) $data['term_id'],
                'student_type_id' => (int) $data['student_type_id'],
                'status' => 'submitted',
                'submitted_by' => $data['submitted_by'],
                'submitted_at' => date('Y-m-d H:i:s'),
                'review_notes' => $data['notes'] ?? null,
            ];

            $this->db->commit();

            return formatResponse(true, [
                'approval' => $approval,
                'line_item_count' => $lineItemCount,
                'message' => 'Fee structure bundle submitted for review'
            ]);

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Submit all terms and student types in one fee-structure revision.
     * A revision is idempotent: once every active row is submitted, a second
     * submission is rejected until a changed draft creates new active rows.
     */
    public function submitFeeStructureBundleBatch($data)
    {
        try {
            $yearId = $this->resolveAcademicYearId($data['academic_year'] ?? null);
            $typeIds = $data['student_type_ids'] ?? [];
            if (is_string($typeIds)) {
                $typeIds = preg_split('/\s*,\s*/', $typeIds, -1, PREG_SPLIT_NO_EMPTY);
            }
            $typeIds = array_values(array_unique(array_filter(array_map('intval', (array) $typeIds))));
            if (!$yearId || empty($typeIds)) {
                return formatResponse(false, null, 'Academic year and student types are required');
            }

            $ph = implode(',', array_fill(0, count($typeIds), '?'));
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_rows,
                        SUM(CASE WHEN approved_by IS NOT NULL THEN 1 ELSE 0 END) AS submitted_rows
                 FROM academic_year_fee_schedules
                 WHERE academic_year_id = ? AND student_type_id IN ($ph) AND status = 'active'"
            );
            $stmt->execute(array_merge([(int) $yearId], $typeIds));
            $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $totalRows = (int) ($counts['total_rows'] ?? 0);
            $submittedRows = (int) ($counts['submitted_rows'] ?? 0);

            if ($totalRows === 0) {
                return formatResponse(false, null, 'No active fee structure draft exists for this academic year');
            }
            if ($submittedRows === $totalRows) {
                return formatResponse(false, null, 'This fee structure has already been submitted. Change at least one amount before submitting again.');
            }

            $userId = (int) ($data['submitted_by'] ?? 0);
            $this->db->beginTransaction();
            $update = $this->db->prepare(
                "UPDATE academic_year_fee_schedules
                 SET approved_by = ?, approved_at = NULL, updated_at = NOW()
                 WHERE academic_year_id = ? AND student_type_id IN ($ph)
                   AND status = 'active' AND approved_by IS NULL"
            );
            $update->execute(array_merge([$userId, (int) $yearId], $typeIds));
            $submittedNow = (int) $update->rowCount();
            $this->db->commit();

            return formatResponse(true, [
                'submitted_rows' => $submittedNow,
                'academic_year_id' => (int) $yearId,
                'student_type_ids' => $typeIds,
                'status' => 'submitted',
                'message' => 'Fee structure submitted for review',
            ]);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[FeeManager] submitFeeStructureBundleBatch: ' . $e->getMessage());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Review a fee structure bundle (approve or reject at review stage)
     * @param array $data Contains: approval_id, reviewed_by, action ('approve'|'reject'), notes
     * @return array Response with updated approval record
     */
    public function reviewFeeStructureBundle($data)
    {
        try {
            $required = ['approval_id', 'reviewed_by', 'action', 'notes'];
            $missing = array_diff($required, array_keys($data));
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            if (!in_array($data['action'], ['approve', 'reject'])) {
                return formatResponse(false, null, "action must be 'approve' or 'reject'");
            }

            // Resolve the bundle group from its identifying schedule row
            $group = $this->getBundleContext($data['approval_id']);
            if (!$group) {
                return formatResponse(false, null, 'Approval record not found');
            }

            $this->db->beginTransaction();

            if ($data['action'] === 'approve') {
                // Fetch schedule IDs first for audit logging
                $ids = $this->db->prepare("
                    SELECT id, amount FROM academic_year_fee_schedules
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $ids->execute([$group['academic_year_id'], $group['academic_year_term_id'], $group['student_type_id']]);
                $rows = $ids->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $r) {
                    \App\API\Includes\FileLogger::write('finance', [
                        'type' => 'audit',
                        'action' => 'fee_structure_reviewed',
                        'entity' => 'academic_year_fee_schedule',
                        'entity_id' => (int) $r['id'],
                        'user_id' => $data['reviewed_by'],
                        'details' => [
                            'stage' => 'reviewed',
                            'notes' => $data['notes'] ?? null,
                        ],
                        'status' => 'success',
                    ]);
                }

                $stmt = $this->db->prepare("
                    UPDATE academic_year_fee_schedules
                    SET status = 'inactive'
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $stmt->execute([
                    $group['academic_year_id'],
                    $group['academic_year_term_id'],
                    $group['student_type_id'],
                ]);
                $newStatus = 'reviewed';
            } else {
                $rejectIds = $this->db->prepare("
                    SELECT id FROM academic_year_fee_schedules
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $rejectIds->execute([$group['academic_year_id'], $group['academic_year_term_id'], $group['student_type_id']]);
                foreach ($rejectIds->fetchAll(PDO::FETCH_COLUMN) as $rid) {
                    \App\API\Includes\FileLogger::write('finance', [
                        'type' => 'audit',
                        'action' => 'fee_structure_rejected',
                        'entity' => 'academic_year_fee_schedule',
                        'entity_id' => $rid,
                        'user_id' => $data['reviewed_by'],
                        'details' => [
                            'stage' => 'rejected',
                            'notes' => $data['notes'] ?? null,
                        ],
                        'status' => 'success',
                    ]);
                }

                $stmt = $this->db->prepare("
                    UPDATE academic_year_fee_schedules
                    SET status = 'cancelled'
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $stmt->execute([
                    $group['academic_year_id'],
                    $group['academic_year_term_id'],
                    $group['student_type_id'],
                ]);
                $newStatus = 'rejected';
            }

            $this->db->commit();

            $updatedApproval = [
                'id' => (int) $data['approval_id'],
                'level_id' => $group['level_id'],
                'academic_year' => $group['academic_year'],
                'term_id' => $group['academic_year_term_id'],
                'student_type_id' => $group['student_type_id'],
                'status' => $newStatus,
                'reviewed_by' => $data['reviewed_by'],
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => $data['notes'],
            ];

            return formatResponse(true, [
                'approval' => $updatedApproval,
                'message' => 'Fee structure bundle ' . ($data['action'] === 'approve' ? 'reviewed' : 'rejected') . ' successfully'
            ]);

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Approve a fee structure bundle (final approval stage)
     * @param array $data Contains: approval_id, approved_by, action ('approve'|'reject'), notes
     * @return array Response with updated approval record and obligations count
     */
    public function approveFeeStructureBundle($data)
    {
        try {
            $required = ['approval_id', 'approved_by', 'action', 'notes'];
            $missing = array_diff($required, array_keys($data));
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            if (!in_array($data['action'], ['approve', 'reject'])) {
                return formatResponse(false, null, "action must be 'approve' or 'reject'");
            }

            // Resolve the bundle group from its identifying schedule row
            $group = $this->getBundleContext($data['approval_id']);
            if (!$group) {
                return formatResponse(false, null, 'Approval record not found');
            }

            $this->db->beginTransaction();

            $obligationsCount = 0;
            $studentsProcessed = 0;
            $newStatus = null;

            if ($data['action'] === 'approve') {
                $apvIds = $this->db->prepare("
                    SELECT id, amount FROM academic_year_fee_schedules
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $apvIds->execute([$group['academic_year_id'], $group['academic_year_term_id'], $group['student_type_id']]);
                foreach ($apvIds->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    \App\API\Includes\FileLogger::write('finance', [
                        'type' => 'audit',
                        'action' => 'fee_structure_approved',
                        'entity' => 'academic_year_fee_schedule',
                        'entity_id' => (int) $r['id'],
                        'user_id' => $data['approved_by'],
                        'details' => [
                            'stage' => 'approved',
                            'notes' => $data['notes'] ?? null,
                        ],
                        'status' => 'success',
                    ]);
                }

                $stmt = $this->db->prepare("
                    UPDATE academic_year_fee_schedules
                    SET status = 'active'
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $stmt->execute([
                    $group['academic_year_id'],
                    $group['academic_year_term_id'],
                    $group['student_type_id'],
                ]);

                $this->db->commit();

                // Activate and generate obligations (outside transaction to avoid nesting issues)
                $result = $this->activateAndGenerateObligations(
                    $group['level_id'],
                    $group['academic_year'],
                    $group['academic_year_term_id'],
                    $group['student_type_id'],
                    $data['approved_by']
                );

                if (!empty($result['data'])) {
                    $obligationsCount = (int) ($result['data']['obligations_created'] ?? 0);
                    $studentsProcessed = (int) ($result['data']['students_processed'] ?? 0);
                }

                $newStatus = 'approved';
            } else {
                // Reject
                $stmt = $this->db->prepare("
                    UPDATE academic_year_fee_schedules
                    SET status = 'cancelled'
                    WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                      AND status <> 'cancelled'
                ");
                $stmt->execute([
                    $group['academic_year_id'],
                    $group['academic_year_term_id'],
                    $group['student_type_id'],
                ]);

                $this->db->commit();

                $newStatus = 'rejected';
            }

            $updatedApproval = [
                'id' => (int) $data['approval_id'],
                'level_id' => $group['level_id'],
                'academic_year' => $group['academic_year'],
                'term_id' => $group['academic_year_term_id'],
                'student_type_id' => $group['student_type_id'],
                'status' => $newStatus,
                'approved_by' => $data['approved_by'],
                'approved_at' => date('Y-m-d H:i:s'),
                'obligations_generated' => $newStatus === 'approved' ? 1 : 0,
                'obligations_count' => $obligationsCount,
            ];

            return formatResponse(true, [
                'approval' => $updatedApproval,
                'obligations_count' => $obligationsCount,
                'students_processed' => $studentsProcessed,
                'obligations_created' => $obligationsCount,
                'message' => 'Fee structure bundle ' . ($data['action'] === 'approve' ? 'approved and activated' : 'rejected') . ' successfully'
            ]);

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Activate fee structures and generate student fee obligations for a bundle
     * @param int $levelId
     * @param int $academicYear  4-digit year integer (NOT a foreign key)
     * @param int $termId
     * @param int $studentTypeId
     * @param int $userId
     * @return array Response with students_processed and obligations_created counts
     */
    public function activateAndGenerateObligations($levelId, $academicYear, $termId, $studentTypeId, $userId)
    {
        try {
            // 2. Resolve academic_year_id from the 4-digit year
            $stmt = $this->db->prepare("
                SELECT id FROM academic_years
                WHERE YEAR(start_date) = ? OR year_code = ?
                LIMIT 1
            ");
            $stmt->execute([$academicYear, $academicYear]);
            $academicYearId = $stmt->fetchColumn();

            if (!$academicYearId) {
                return formatResponse(false, null, "Academic year record not found for year: $academicYear");
            }

            // 1. Mark schedules active (record approval audit)
            $stmt = $this->db->prepare("
                UPDATE academic_year_fee_schedules
                SET status = 'active', approved_at = NOW()
                WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
            ");
            $stmt->execute([$academicYearId, $termId, $studentTypeId]);

            // 3. Get active students enrolled in this level + student_type
            $levelFilter = $levelId ? " AND c.level_id = ?" : "";
            $stmt = $this->db->prepare("
                SELECT DISTINCT s.id AS student_id
                FROM students s
                JOIN student_academic_enrollments sae ON sae.student_id = s.id
                JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                JOIN classes c ON ayc.class_id = c.id
                WHERE 1=1
                  AND s.student_type_id = ?
                  AND s.status = 'active'
                  AND sae.academic_year_id = ?
                  AND sae.enrollment_status = 'active'
                  $levelFilter
            ");
            $levelParams = [$studentTypeId, $academicYearId];
            if ($levelId) {
                $levelParams[] = $levelId;
            }
            $stmt->execute($levelParams);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 4. Get all fee schedule rows for this bundle
            $fsdStmt = $this->db->prepare("
                SELECT id, amount, due_date
                FROM academic_year_fee_schedules
                WHERE academic_year_id = ? AND academic_year_term_id = ? AND student_type_id = ?
                  AND status = 'active'
            ");
            $fsdStmt->execute([$academicYearId, $termId, $studentTypeId]);
            $feeRows = $fsdStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($feeRows)) {
                return formatResponse(true, [
                    'students_processed' => 0,
                    'obligations_created' => 0,
                    'message' => 'No active fee structure rows found after activation'
                ]);
            }

            // 5. Insert obligations for each student × each fee row
            $enrollStmt = $this->db->prepare("
                SELECT id FROM student_academic_enrollments
                WHERE student_id = ? AND academic_year_id = ? AND enrollment_status = 'active'
                LIMIT 1
            ");

            $insertStmt = $this->db->prepare("
                INSERT INTO student_fee_obligations
                    (student_academic_enrollment_id, academic_year_id, academic_year_term_id, academic_year_fee_schedule_id,
                     amount_due, status, due_date)
                VALUES (?, ?, ?, ?, ?, 'pending', ?)
                ON DUPLICATE KEY UPDATE
                    due_date   = VALUES(due_date)
            ");

            $totalObligations = 0;
            foreach ($students as $studentId) {
                $enrollStmt->execute([$studentId, $academicYearId]);
                $enrollmentId = $enrollStmt->fetchColumn();
                if (!$enrollmentId) {
                    continue;
                }
                foreach ($feeRows as $feeRow) {
                    $insertStmt->execute([
                        $enrollmentId,
                        $academicYearId,
                        $termId,
                        $feeRow['id'],
                        $feeRow['amount'],
                        $feeRow['due_date'] ?? null,
                    ]);
                    $totalObligations++;
                }
            }

            return formatResponse(true, [
                'students_processed' => count($students),
                'obligations_created' => $totalObligations,
                'message' => 'Obligations generated successfully'
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Resolve the bundle group (academic year / term / student type) for a schedule row id.
     * A bundle is identified by the earliest schedule row in its group.
     * @param int $bundleId A schedule row id
     * @return array|null Group context or null if not found
     */
    private function getBundleContext($bundleId)
    {
        $stmt = $this->db->prepare("
            SELECT ayfs.academic_year_id,
                   ayfs.academic_year_term_id,
                   ayfs.student_type_id,
                   ay.year_code AS academic_year,
                   c.level_id
            FROM academic_year_fee_schedules ayfs
            JOIN academic_years ay ON ay.id = ayfs.academic_year_id
            LEFT JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
            LEFT JOIN classes c ON c.id = ayc.class_id
            WHERE ayfs.id = ?
            LIMIT 1
        ");
        $stmt->execute([$bundleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !$row['academic_year_id'] || !$row['academic_year_term_id'] || !$row['student_type_id']) {
            return null;
        }

        return [
            'academic_year_id' => (int) $row['academic_year_id'],
            'academic_year_term_id' => (int) $row['academic_year_term_id'],
            'student_type_id' => (int) $row['student_type_id'],
            'academic_year' => $row['academic_year'],
            'level_id' => $row['level_id'] !== null ? (int) $row['level_id'] : null,
        ];
    }

    /**
     * Get a paginated list of fee structure bundles (derived from academic_year_fee_schedules)
     * @param array $filters Optional: status, academic_year, term_id, level_id
     * @param int $page
     * @param int $limit
     * @return array Response with paginated list
     */
    public function getFeeStructureBundles($filters = [], $page = 1, $limit = 20)
    {
        try {
            $offset = ($page - 1) * $limit;

            $where = "WHERE ayfs.status = 'active' AND ayfs.academic_year_class_id IS NOT NULL";
            $params = [];

            if (!empty($filters['academic_year'])) {
                $yearId = $this->resolveAcademicYearId($filters['academic_year']);
                if (!$yearId) {
                    return formatResponse(true, ['bundles' => [], 'pagination' => ['total' => 0, 'page' => $page, 'limit' => $limit, 'pages' => 0]]);
                }
                $where .= " AND ay.id = ?";
                $params[] = $yearId;
            }
            if (!empty($filters['term_id'])) {
                $where .= " AND ayfs.academic_year_term_id = ?";
                $params[] = $filters['term_id'];
            }
            if (!empty($filters['level_id'])) {
                $where .= " AND c.level_id = ?";
                $params[] = $filters['level_id'];
            }
            if (!empty($filters['student_type_id'])) {
                $where .= " AND ayfs.student_type_id = ?";
                $params[] = $filters['student_type_id'];
            }

            $innerSql = "
                SELECT MIN(ayfs.id) AS id,
                       sl.id AS level_id,
                       COALESCE(c.name, sl.name) AS level_name,
                       ay.year_code AS academic_year,
                       ayfs.academic_year_id AS academic_year_id,
                       ayfs.student_type_id AS student_type_id,
                       st.name AS student_type_name,
                       COUNT(DISTINCT ayfs.academic_year_class_id) AS class_count,
                       MAX(CASE WHEN ayt.term_id = 1 THEN ayfs.amount END) AS term_1_amount,
                       MAX(CASE WHEN ayt.term_id = 2 THEN ayfs.amount END) AS term_2_amount,
                       MAX(CASE WHEN ayt.term_id = 3 THEN ayfs.amount END) AS term_3_amount,
                       SUM(ayfs.amount) AS total_amount,
                       MAX(u_sub.username) AS submitted_by_name,
                       MIN(ayfs.created_at) AS submitted_at,
                       CASE
                           WHEN SUM(CASE WHEN ayfs.status = 'cancelled' THEN 1 ELSE 0 END) > 0 THEN 'rejected'
                           WHEN MAX(ayfs.approved_at) IS NOT NULL
                                AND SUM(CASE WHEN ayfs.status = 'active' THEN 1 ELSE 0 END) = COUNT(ayfs.id) THEN 'approved'
                           WHEN MAX(ayfs.approved_at) IS NOT NULL THEN 'reviewed'
                           WHEN MAX(ayfs.approved_by) IS NOT NULL THEN 'submitted'
                           ELSE 'draft'
                       END AS status
                FROM academic_year_fee_schedules ayfs
                JOIN academic_years ay ON ay.id = ayfs.academic_year_id
                LEFT JOIN academic_year_terms ayt ON ayt.id = ayfs.academic_year_term_id
                LEFT JOIN terms t ON t.id = ayt.term_id
                JOIN student_types st ON st.id = ayfs.student_type_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = ayfs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN school_levels sl ON sl.id = c.level_id
                LEFT JOIN users u_sub ON u_sub.id = ayfs.approved_by
                $where
                GROUP BY ayfs.academic_year_id, c.id, c.name, sl.id, sl.name, ayfs.student_type_id, st.name
            ";

            $statusFilter = "";
            $statusParam = [];
            if (!empty($filters['status'])) {
                $statusFilter = " WHERE t.status = ?";
                $statusParam[] = $filters['status'];
            }

            $sql = "
                SELECT t.*
                FROM ($innerSql) t
                $statusFilter
                ORDER BY t.submitted_at DESC
                LIMIT ? OFFSET ?
            ";

            $listParams = array_merge($params, $statusParam, [$limit, $offset]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($listParams);
            $bundles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total
            $countSql = "
                SELECT COUNT(*)
                FROM ($innerSql) t
                $statusFilter
            ";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute(array_merge($params, $statusParam));
            $total = (int) $countStmt->fetchColumn();

            return formatResponse(true, [
                'bundles' => $bundles,
                'pagination' => [
                    'total' => $total,
                    'page'  => $page,
                    'limit' => $limit,
                    'pages' => $total > 0 ? (int) ceil($total / $limit) : 0
                ]
            ]);

        } catch (\PDOException $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // =====================================================
    // BILLING HISTORY
    // =====================================================

    /**
     * Get a student's full billing history grouped by academic year and term
     * @param int $studentId
     * @return array Response with academic_years array
     */
    public function getStudentBillingHistory($studentId)
    {
        try {
            if (empty($studentId)) {
                return formatResponse(false, null, 'student_id is required');
            }

            // Fetch obligations with enriched joins
                        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
                        $stmt = $this->db->prepare("
                SELECT sfo.id,
                       sfo.student_academic_enrollment_id,
                       sfo.academic_year_id,
                       sfo.academic_year_term_id AS term_id,
                       sfo.academic_year_fee_schedule_id,
                       sfo.amount_due,
                       sfo.status,
                       sfo.due_date,
                       sfo.is_sponsored,
                       sfo.sponsored_waiver_amount,
                       ay.year_code AS academic_year,
                       COALESCE(v.amount_paid, 0) AS amount_paid,
                       COALESCE(v.amount_waived, 0) AS amount_waived,
                       COALESCE(v.balance, sfo.amount_due) AS balance,
                       COALESCE(v.payment_status, 'pending') AS payment_status,
                       t.name        AS term_name,
                       CAST(SUBSTRING(t.code, 2) AS UNSIGNED) AS term_number,
                       'School Fees' AS fee_type_name,
                       'SCHOOL_FEES' AS fee_type_code,
                       sl.name        AS level_name,
                       c.name         AS class_name
                FROM student_fee_obligations sfo
                JOIN student_academic_enrollments sae ON sfo.student_academic_enrollment_id = sae.id
                JOIN academic_years ay ON sfo.academic_year_id = ay.id
                JOIN academic_year_terms ayt ON sfo.academic_year_term_id = ayt.id
                JOIN terms t ON ayt.term_id = t.id
                LEFT JOIN academic_year_fee_schedules ayfs ON sfo.academic_year_fee_schedule_id = ayfs.id
                LEFT JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                LEFT JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                LEFT JOIN classes c ON ayc.class_id = c.id
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                LEFT JOIN $feeBalView v ON v.student_academic_enrollment_id = sfo.student_academic_enrollment_id AND v.academic_year_term_id = sfo.academic_year_term_id
                WHERE sae.student_id = ?
                ORDER BY ay.year_code DESC, t.code ASC
            ");
            $stmt->execute([$studentId]);
            $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch confirmed payments
            $stmt = $this->db->prepare("
                SELECT p.id,
                       p.student_id,
                       p.receipt_no,
                       p.amount AS amount,
                       p.payment_date,
                       p.method AS payment_method,
                       p.reference,
                       p.status,
                       p.created_at,
                       ay.year_code AS academic_year,
                       ayt.id AS term_id,
                       t.name AS term_name,
                       CAST(SUBSTRING(t.code, 2) AS UNSIGNED) AS term_number
                FROM payments p
                LEFT JOIN academic_years ay ON p.payment_date BETWEEN ay.start_date AND ay.end_date
                LEFT JOIN academic_year_terms ayt ON ayt.academic_year_id = ay.id AND p.payment_date BETWEEN ayt.opening_date AND ayt.closing_date
                LEFT JOIN terms t ON ayt.term_id = t.id
                WHERE p.student_id = ? AND p.status IN ('confirmed', 'completed', 'success')
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([$studentId]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group obligations by (academic_year, term_id)
            $grouped = [];
            foreach ($obligations as $ob) {
                $year   = (int) $ob['academic_year'];
                $termId = (int) $ob['term_id'];
                if (!isset($grouped[$year])) {
                    $grouped[$year] = [];
                }
                if (!isset($grouped[$year][$termId])) {
                    $grouped[$year][$termId] = [
                        'term_id'      => $termId,
                        'term_name'    => $ob['term_name'],
                        'term_number'  => (int) $ob['term_number'],
                        'obligations'  => [],
                        'payments'     => [],
                        'total_due'    => 0.0,
                        'total_paid'   => 0.0,
                        'total_waived' => 0.0,
                        'balance'      => 0.0,
                    ];
                }
                $grouped[$year][$termId]['obligations'][] = $ob;
                $grouped[$year][$termId]['total_due']    += (float) $ob['amount_due'];
                $grouped[$year][$termId]['total_paid']   += (float) $ob['amount_paid'];
                $grouped[$year][$termId]['total_waived'] += (float) $ob['amount_waived'];
                $grouped[$year][$termId]['balance']       = $grouped[$year][$termId]['total_due']
                                                            - $grouped[$year][$termId]['total_paid']
                                                            - $grouped[$year][$termId]['total_waived'];
            }

            // Attach payments to their (academic_year, term_id) bucket
            foreach ($payments as $pmt) {
                $year   = (int) ($pmt['academic_year'] ?? 0);
                $termId = (int) ($pmt['term_id'] ?? 0);
                if (isset($grouped[$year][$termId])) {
                    $grouped[$year][$termId]['payments'][] = $pmt;
                }
            }

            // Build final structure
            $academicYears = [];
            foreach ($grouped as $year => $terms) {
                $termsArr = array_values($terms);
                // Sort terms by term_number ascending
                usort($termsArr, fn($a, $b) => $a['term_number'] <=> $b['term_number']);
                $academicYears[] = [
                    'year'  => $year,
                    'terms' => $termsArr,
                ];
            }

            return formatResponse(true, ['academic_years' => $academicYears]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get a billing report for all active students in a class for a given academic year/term
     * @param int $classId
     * @param int $academicYearId
     * @param int|null $termId Optional: filter by term
     * @return array Response with per-student rows and class aggregate
     */
    public function getClassBillingReport($classId, $academicYearId, $termId = null)
    {
        $feeBalView = ReadReplicaService::qualifiedRef('student_fee_balances');
        try {
            if (empty($classId) || empty($academicYearId)) {
                return formatResponse(false, null, 'class_id and academic_year_id are required');
            }

            $termFilter        = $termId ? " AND v.academic_year_term_id = ?" : "";
            $pmtTermFilter     = $termId ? " AND p.payment_date BETWEEN (SELECT opening_date FROM academic_year_terms WHERE id = ?) AND (SELECT closing_date FROM academic_year_terms WHERE id = ?)" : "";

            $sql = "
                SELECT s.id,
                       prs.first_name,
                       prs.last_name,
                       s.admission_no,
                       st.name                        AS student_type,
                       COALESCE(SUM(v.amount_due),    0) AS total_billed,
                       COALESCE(SUM(v.amount_paid),   0) AS total_paid,
                       COALESCE(SUM(v.amount_waived), 0) AS total_waived,
                       COALESCE(SUM(v.balance),       0) AS balance,
                       MAX(v.payment_status)             AS payment_status,
                       MAX(p.payment_date)                AS last_payment_date,
                       COUNT(DISTINCT p.id)               AS payment_count
                FROM student_academic_enrollments sae
                JOIN students s       ON s.id = sae.student_id
                JOIN persons prs      ON s.person_id = prs.id
                JOIN student_types st ON s.student_type_id = st.id
                JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                LEFT JOIN $feeBalView v
                       ON v.student_academic_enrollment_id = sae.id
                      AND v.academic_year_id = sae.academic_year_id
                      $termFilter
                LEFT JOIN payments p
                       ON p.student_id = s.id
                      AND p.status IN ('confirmed', 'completed', 'success')
                      $pmtTermFilter
                WHERE ayc.class_id = ?
                  AND sae.academic_year_id = ?
                  AND sae.enrollment_status = 'active'
                  AND s.status = 'active'
                GROUP BY s.id, prs.first_name, prs.last_name, s.admission_no, st.name
                ORDER BY prs.last_name, prs.first_name
            ";

            $params = [];
            if ($termId) {
                $params[] = $termId;
                $params[] = $termId;
                $params[] = $termId;
            }
            $params[] = $classId;
            $params[] = $academicYearId;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Compute class aggregates
            $totalStudents      = count($rows);
            $totalBilledClass   = array_sum(array_column($rows, 'total_billed'));
            $totalCollectedClass = array_sum(array_column($rows, 'total_paid'));
            $collectionRate     = $totalBilledClass > 0
                                    ? round(($totalCollectedClass / $totalBilledClass) * 100, 2)
                                    : 0.0;

            return formatResponse(true, [
                'students' => $rows,
                'aggregate' => [
                    'total_students'       => $totalStudents,
                    'total_billed_class'   => $totalBilledClass,
                    'total_collected_class' => $totalCollectedClass,
                    'collection_rate'      => $collectionRate,
                ]
            ]);

        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'getStudentFeeAccounts',
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    // =========================================================================
    // EXTRA CHARGES — flexible, database-driven charges on the fee structure
    // =========================================================================

    /** Persist the normalized extra-charge relations. Legacy columns remain
     * populated only for compatibility with older reports. */
    private function syncExtraChargeRelations(int $chargeId, array $data): void
    {
        $db = $this->db;
        $target = (string) ($data['target_scope'] ?? 'all_students');
        $db->prepare('DELETE FROM extra_charge_contexts WHERE extra_charge_id=?')->execute([$chargeId]);
        $context = $target === 'new_admissions' ? 'admission' : 'enrollment';
        $db->prepare('INSERT INTO extra_charge_contexts(extra_charge_id,context_code) VALUES(?,?)')->execute([$chargeId, $context]);

        $db->prepare('DELETE FROM extra_charge_parent_scopes WHERE extra_charge_id=?')->execute([$chargeId]);
        if ($target === 'new_admissions') {
            $db->prepare("INSERT INTO extra_charge_parent_scopes(extra_charge_id,parent_scope) VALUES(?, 'any_parent')")->execute([$chargeId]);
        }

        $db->prepare('DELETE FROM extra_charge_student_types WHERE extra_charge_id=?')->execute([$chargeId]);
        if (!empty($data['student_type_id'])) {
            $db->prepare('INSERT INTO extra_charge_student_types(extra_charge_id,student_type_id) VALUES(?,?)')->execute([$chargeId, (int) $data['student_type_id']]);
        }
        $db->prepare('DELETE FROM extra_charge_classes WHERE extra_charge_id=?')->execute([$chargeId]);
        if ($target === 'specific_class' && !empty($data['class_id'])) {
            $db->prepare('INSERT INTO extra_charge_classes(extra_charge_id,class_id) VALUES(?,?)')->execute([$chargeId, (int) $data['class_id']]);
        }

        $db->prepare('DELETE FROM extra_charge_pricing_tiers WHERE extra_charge_id=?')->execute([$chargeId]);
        if (!empty($data['pricing_tiers']) && is_array($data['pricing_tiers'])) {
            $insert = $db->prepare('INSERT INTO extra_charge_pricing_tiers(extra_charge_id,condition_code,label,amount,sort_order) VALUES(?,?,?,?,?)');
            $order = 0;
            foreach ($data['pricing_tiers'] as $tier) {
                $condition = trim((string) ($tier['condition'] ?? ''));
                $label = trim((string) ($tier['label'] ?? ''));
                $amount = (float) ($tier['amount'] ?? 0);
                if ($condition === '' || $label === '' || $amount <= 0) continue;
                $insert->execute([$chargeId, $condition, $label, $amount, $order++]);
            }
        }

        $db->prepare('UPDATE extra_charge_schedules SET status=\'inactive\' WHERE extra_charge_id=?')->execute([$chargeId]);
        $yearStmt = $db->prepare('SELECT start_date FROM academic_years WHERE id=(SELECT academic_year_id FROM extra_charges WHERE id=?)');
        $yearStmt->execute([$chargeId]);
        $startsOn = $data['starts_on'] ?? ($yearStmt->fetchColumn() ?: date('Y-m-d'));
        $frequency = (string) ($data['billing_frequency'] ?? 'one_time');
        $termId = !empty($data['academic_year_term_id']) ? (int) $data['academic_year_term_id'] : null;
        $db->prepare('INSERT INTO extra_charge_schedules(extra_charge_id,frequency,starts_on,ends_on,academic_year_term_id,due_day,status) VALUES(?,?,?,?,?,?,\'active\')')
            ->execute([$chargeId, $frequency, $startsOn, $data['ends_on'] ?? null, $termId, $data['due_day'] ?? null]);
    }

    private function normalizedPricingTiers(int $chargeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, condition_code AS `condition`, label, amount, sort_order FROM extra_charge_pricing_tiers WHERE extra_charge_id=? ORDER BY sort_order,id'
        );
        $stmt->execute([$chargeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * List extra charges for an academic year with all enhanced fields.
     */
    public function getExtraCharges(array $filters = []): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $yearId = (int) ($filters['academic_year'] ?? 0);
            if ($yearId <= 0) {
                $row = $db->query("SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                $yearId = (int) ($row['id'] ?? 0);
            }
            if ($yearId <= 0) return formatResponse(true, ['charges' => []]);

            $where = ['ec.academic_year_id = ?'];
            $params = [$yearId];

            if (!empty($filters['status'])) {
                $where[] = 'ec.status = ?';
                $params[] = $filters['status'];
            }
            if (!empty($filters['target_scope'])) {
                $where[] = 'ec.target_scope = ?';
                $params[] = $filters['target_scope'];
            }
            if (!empty($filters['billing_model'])) {
                $where[] = 'ec.billing_model = ?';
                $params[] = $filters['billing_model'];
            }

            $sql = "SELECT ec.*,
                           cl.name AS class_name,
                           coa.account_name AS gl_account_name,
                           coa.account_code AS gl_account_code,
                           CONCAT(p1.first_name, ' ', p1.last_name) AS created_by_name,
                           CONCAT(p2.first_name, ' ', p2.last_name) AS approved_by_name
                    FROM extra_charges ec
                    LEFT JOIN classes cl ON cl.id = ec.class_id
                    LEFT JOIN chart_of_accounts coa ON coa.id = ec.gl_account_id
                    LEFT JOIN users u ON u.id = ec.created_by
                    LEFT JOIN persons p1 ON p1.id = u.person_id
                    LEFT JOIN users u2 ON u2.id = ec.approved_by
                    LEFT JOIN persons p2 ON p2.id = u2.person_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY ec.display_order, ec.name";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $charges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($charges as &$c) {
                $c['pricing_tiers'] = $this->normalizedPricingTiers((int) $c['id']);
            }

            return formatResponse(true, ['charges' => $charges], 'Extra charges loaded');
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'getExtraCharges',
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get a single extra charge with review history.
     */
    public function getExtraCharge(int $id): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT ec.*, cl.name AS class_name,
                        coa.account_name AS gl_account_name, coa.account_code AS gl_account_code
                 FROM extra_charges ec
                 LEFT JOIN classes cl ON cl.id = ec.class_id
                 LEFT JOIN chart_of_accounts coa ON coa.id = ec.gl_account_id
                 WHERE ec.id = ?"
            );
            $stmt->execute([$id]);
            $charge = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$charge) return formatResponse(false, null, 'Extra charge not found.');

            $charge['pricing_tiers'] = $this->normalizedPricingTiers($id);

            $logStmt = $db->prepare(
                "SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) AS reviewer_name
                 FROM extra_charge_review_log r
                 JOIN users u ON u.id = r.reviewer_id
                 JOIN persons p ON p.id = u.person_id
                 WHERE r.extra_charge_id = ? ORDER BY r.created_at DESC"
            );
            $logStmt->execute([$id]);
            $charge['review_log'] = $logStmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['charge' => $charge]);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'getExtraCharge',
                'charge_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Create a new extra charge (draft status).
     */
    public function createExtraCharge(array $data, int $userId): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $yearId = (int) ($data['academic_year_id'] ?? 0);
            if ($yearId <= 0) return formatResponse(false, null, 'Academic year is required.');
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') return formatResponse(false, null, 'Charge name is required.');
            $amount = (float) ($data['amount'] ?? 0);
            // A flat amount is optional when per-segment pricing tiers are supplied.
            $hasTiers = !empty($data['pricing_tiers']) && is_array($data['pricing_tiers']);
            if ($amount <= 0 && !$hasTiers) {
                return formatResponse(false, null, 'Amount must be greater than zero (or provide pricing tiers).');
            }

            $calcMode = in_array($data['calculation_mode'] ?? '', ['fixed', 'per_unit'], true)
                ? $data['calculation_mode'] : 'fixed';
            $unitLabel = trim((string) ($data['unit_label'] ?? ''));
            $unitPrice = ($calcMode === 'per_unit' && isset($data['unit_price']))
                ? (float) $data['unit_price'] : null;
            if ($calcMode === 'per_unit' && (!$unitPrice || $unitPrice <= 0 || $unitLabel === '')) {
                return formatResponse(false, null, 'Per-unit charges require a unit label and a positive unit price.');
            }

            $billingModel = in_array($data['billing_model'] ?? '', ['added_to_fees', 'paid_separately', 'optional'], true)
                ? $data['billing_model'] : 'paid_separately';
            $billingFreq = in_array($data['billing_frequency'] ?? '', ['one_time', 'daily', 'weekly', 'monthly', 'per_term', 'per_year'], true)
                ? $data['billing_frequency'] : 'one_time';

            $targetScope = in_array($data['target_scope'] ?? '', ['new_admissions', 'existing_students', 'all_students', 'boarders', 'day_students', 'specific_class'], true)
                ? $data['target_scope'] : 'all_students';
            $classId = ($targetScope === 'specific_class' && !empty($data['class_id']))
                ? (int) $data['class_id'] : null;
            if ($targetScope === 'specific_class' && !$classId) {
                return formatResponse(false, null, 'A class is required for a specific-class charge.');
            }

            $visibleOnFee = !empty($data['visible_on_fee_structure']) ? 1 : 0;
            $glAccountId = !empty($data['gl_account_id']) ? (int) $data['gl_account_id'] : null;
            if ($glAccountId) {
                $gl = $db->prepare("SELECT id FROM chart_of_accounts WHERE id=? AND status='active' AND is_postable=1");
                $gl->execute([$glAccountId]);
                if (!$gl->fetchColumn()) return formatResponse(false, null, 'The selected GL account is not an active postable account.');
            }
            $desc = trim((string) ($data['description'] ?? ''));
            $order = (int) ($data['display_order'] ?? 0);

            $stmt = $db->prepare(
                "INSERT INTO extra_charges
                    (academic_year_id, name, description, amount, calculation_mode, unit_label, unit_price,
                     charge_frequency, billing_model, billing_frequency, visible_on_fee_structure,
                     gl_account_id, target_scope, class_id,
                     display_order, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)"
            );
            $stmt->execute([
                $yearId, $name, $desc ?: null, $amount,
                $calcMode, $unitLabel ?: null, $unitPrice,
                $billingFreq, $billingModel, $billingFreq, $visibleOnFee,
                $glAccountId, $targetScope, $classId,
                $order, $userId,
            ]);
            $newId = (int) $db->lastInsertId();
            $this->syncExtraChargeRelations($newId, $data);

            \App\API\Includes\FileLogger::write('finance', [
                'action' => 'extra_charge_created',
                'charge_id' => $newId,
                'name' => $name,
                'amount' => $amount,
                'created_by' => $userId,
            ]);

            return formatResponse(true, ['id' => $newId, 'message' => 'Extra charge created.']);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'createExtraCharge',
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Update an extra charge (only drafts can be edited).
     */
    public function updateExtraCharge(int $id, array $data, int $userId): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("SELECT * FROM extra_charges WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) return formatResponse(false, null, 'Extra charge not found.');
            if ($existing['status'] !== 'draft') return formatResponse(false, null, 'Only draft charges can be edited.');

            $sets = [];
            $params = [];
            $fields = [
                'name', 'description', 'amount', 'calculation_mode', 'unit_label', 'unit_price',
                'billing_model', 'billing_frequency', 'visible_on_fee_structure', 'gl_account_id',
                'target_scope', 'class_id', 'display_order',
            ];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $sets[] = "$f = ?";
                    $params[] = $data[$f];
                }
            }

            $merged = array_merge($existing, $data);
            if (!empty($merged['gl_account_id'])) {
                $gl = $db->prepare("SELECT id FROM chart_of_accounts WHERE id=? AND status='active' AND is_postable=1");
                $gl->execute([(int) $merged['gl_account_id']]);
                if (!$gl->fetchColumn()) return formatResponse(false, null, 'The selected GL account is not an active postable account.');
            }
            if (empty($sets)) return formatResponse(false, null, 'Nothing to update.');

            $params[] = $id;
            $db->prepare("UPDATE extra_charges SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);
            $this->syncExtraChargeRelations($id, $merged);

            \App\API\Includes\FileLogger::write('finance', [
                'action' => 'extra_charge_updated',
                'charge_id' => $id,
                'updated_by' => $userId,
            ]);

            return formatResponse(true, ['message' => 'Extra charge updated.']);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'updateExtraCharge',
                'charge_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Soft-delete an extra charge (set inactive).
     */
    public function deleteExtraCharge(int $id, int $userId): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare("UPDATE extra_charges SET status = 'inactive' WHERE id = ?")->execute([$id]);

            \App\API\Includes\FileLogger::write('finance', [
                'action' => 'extra_charge_deleted',
                'charge_id' => $id,
                'deleted_by' => $userId,
            ]);

            return formatResponse(true, ['message' => 'Extra charge removed.']);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'deleteExtraCharge',
                'charge_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Submit an extra charge for review (draft → submitted).
     */
    public function submitExtraCharge(int $id, int $userId): array
    {
        return $this->transitionExtraCharge($id, $userId, 'draft', 'submitted', 'submitted');
    }

    /**
     * Approve an extra charge (draft/submitted → active).
     */
    public function approveExtraCharge(int $id, int $userId, string $notes = ''): array
    {
        $db = Database::getInstance()->getConnection();
        $result = $this->transitionExtraCharge($id, $userId, null, 'active', 'approved', $notes);
        // formatResponse() exposes code/status, not a "success" key.
        $code = (int) ($result['code'] ?? 0);
        if ($code >= 200 && $code < 300) {
            try {
                $db->prepare("UPDATE extra_charges SET approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'active'")->execute([$userId, $id]);
            } catch (Exception $e) {
                \App\API\Includes\FileLogger::error('finance', [
                    'action' => 'approveExtraCharge_stamp',
                    'charge_id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        return $result;
    }

    /**
     * Reject an extra charge (back to draft).
     */
    public function rejectExtraCharge(int $id, int $userId, string $notes = ''): array
    {
        return $this->transitionExtraCharge($id, $userId, null, 'draft', 'rejected', $notes);
    }

    /**
     * Internal: transition an extra charge status and log it.
     */
    private function transitionExtraCharge(int $id, int $userId, ?string $fromStatus, string $toStatus, string $action, string $notes = ''): array
    {
        try {
            $db = Database::getInstance()->getConnection();

            $where = 'id = ?';
            $params = [$id];
            if ($fromStatus !== null) {
                $where .= ' AND status = ?';
                $params[] = $fromStatus;
            }

            $stmt = $db->prepare("SELECT id, status FROM extra_charges WHERE $where");
            $stmt->execute($params);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing) return formatResponse(false, null, 'Extra charge not found or invalid status.');

            $db->prepare("UPDATE extra_charges SET status = ? WHERE id = ?")->execute([$toStatus, $id]);
            $db->prepare(
                "INSERT INTO extra_charge_review_log (extra_charge_id, action, reviewer_id, notes) VALUES (?, ?, ?, ?)"
            )->execute([$id, $action, $userId, $notes ?: null]);

            \App\API\Includes\FileLogger::write('finance', [
                'action' => "extra_charge_{$action}",
                'charge_id' => $id,
                'from_status' => $existing['status'],
                'to_status' => $toStatus,
                'reviewer_id' => $userId,
            ]);

            return formatResponse(true, ['message' => 'Status updated.']);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => "transitionExtraCharge_{$action}",
                'charge_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get active extra charges formatted for the fee structure printout.
     * Only returns charges where visible_on_fee_structure = 1.
     */
    public function getExtraChargesForPrint(int $yearId): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT ec.id, ec.name, ec.amount, ec.calculation_mode, ec.unit_label, ec.unit_price,
                        ec.billing_model, ec.billing_frequency, ec.target_scope,
                        ec.class_id, coa.account_name AS gl_account_name
                 FROM extra_charges ec
                 LEFT JOIN chart_of_accounts coa ON coa.id = ec.gl_account_id
                 WHERE ec.academic_year_id = ? AND ec.status = 'active' AND ec.visible_on_fee_structure = 1
                 ORDER BY ec.display_order, ec.name"
            );
            $stmt->execute([$yearId]);
            $charges = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $row['amount'] = (float) $row['amount'];
                $row['pricing_tiers'] = $this->normalizedPricingTiers((int) ($row['id'] ?? 0));
                $charges[] = $row;
            }
            return formatResponse(true, ['charges' => $charges]);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'getExtraChargesForPrint',
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get active extra charges applicable to a student for auto-billing.
     * Used by enrollment triggers to generate student_fee_obligations.
     */
    public function getExtraChargesForEnrollment(int $yearId, int $studentTypeId, ?int $classId, bool $isNewStudent): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT ec.*
                    FROM extra_charges ec
                    JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='enrollment'
                    LEFT JOIN student_types st ON st.id=?
                    WHERE ec.academic_year_id = ?
                      AND ec.status = 'active'
                      AND ec.billing_model = 'added_to_fees'
                      AND (
                          ec.target_scope = 'all_students'
                          OR (ec.target_scope = 'existing_students' AND ? = 0)
                          OR (ec.target_scope = 'boarders' AND st.code IN ('BOARD','WEEKLY'))
                          OR (ec.target_scope = 'day_students' AND st.code = 'DAY')
                          OR (ec.target_scope = 'specific_class' AND EXISTS (SELECT 1 FROM extra_charge_classes xcc WHERE xcc.extra_charge_id=ec.id AND xcc.class_id=? ) )
                          OR EXISTS (SELECT 1 FROM extra_charge_student_types xst WHERE xst.extra_charge_id=ec.id AND xst.student_type_id=? )
                      )
                    ORDER BY ec.display_order, ec.name";
            $stmt = $db->prepare($sql);
            $stmt->execute([$studentTypeId, $yearId, $isNewStudent ? 1 : 0, $classId, $studentTypeId]);
            $charges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($charges as &$c) {
                $c['pricing_tiers'] = $this->normalizedPricingTiers((int) $c['id']);
            }

            return formatResponse(true, ['charges' => $charges]);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', [
                'action' => 'getExtraChargesForEnrollment',
                'error' => $e->getMessage(),
            ]);
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * List academic years for the extra charges filter dropdown.
     */
    public function getAcademicYearsList(): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query(
                "SELECT id, year_code, year_name, start_date, end_date, is_current
                 FROM academic_years
                 ORDER BY year_code DESC"
            );
            return formatResponse(true, ['years' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', ['action' => 'getExtraChargesAcademicYears', 'error' => $e->getMessage()]);
            return formatResponse(false, null, 'Academic years could not be loaded.');
        }
    }

    /**
     * List GL accounts for the extra charges account dropdown.
     */
    public function getGLAccounts(): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query(
                "SELECT coa.id, coa.account_code, coa.account_name, cat.code AS account_type
                 FROM chart_of_accounts coa
                 JOIN accounting_account_types cat ON cat.id = coa.account_type_id
                 WHERE coa.is_postable = 1 AND coa.status = 'active'
                 ORDER BY coa.account_code"
            );
            return formatResponse(true, ['accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (Exception $e) {
            \App\API\Includes\FileLogger::error('finance', ['action' => 'getExtraChargesGLAccounts', 'error' => $e->getMessage()]);
            return formatResponse(false, null, 'GL accounts could not be loaded.');
        }
    }

}
