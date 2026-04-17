<?php

namespace App\API\Modules\finance;

use App\Database\Database;
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

            $this->db->beginTransaction();

            // Insert main fee structure
            $stmt = $this->db->prepare("
                INSERT INTO fee_structures (
                    name, description, academic_year, level_id, 
                    amount, is_mandatory, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['academic_year'],
                $data['level_id'],
                $data['amount'],
                $data['is_mandatory'] ?? 1,
                $data['status'] ?? 'active',
                $data['created_by'] ?? null
            ]);

            $feeStructureId = $this->db->lastInsertId();

            // Insert detailed fee types if provided
            if (!empty($data['fee_types'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO fee_structures_detailed (
                        fee_structure_id, fee_type_id, amount, is_mandatory
                    ) VALUES (?, ?, ?, ?)
                ");

                foreach ($data['fee_types'] as $feeType) {
                    $stmt->execute([
                        $feeStructureId,
                        $feeType['fee_type_id'],
                        $feeType['amount'],
                        $feeType['is_mandatory'] ?? 1
                    ]);
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
            return formatResponse(false, null, 'Failed to create fee structure: ' . $e->getMessage());
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
            $stmt = $this->db->prepare("SELECT id FROM fee_structures WHERE id = ?");
            $stmt->execute([$feeStructureId]);

            if (!$stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Fee structure not found');
            }

            // Build update query dynamically
            $allowedFields = ['name', 'description', 'amount', 'is_mandatory', 'status'];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No valid fields to update');
            }

            $params[] = $feeStructureId;
            $sql = "UPDATE fee_structures SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();

            return formatResponse(true, ['message' => 'Fee structure updated successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to update fee structure: ' . $e->getMessage());
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
                SELECT fs.*, sl.name as level_name, sl.code as level_code,
                       u.username as created_by_name
                FROM fee_structures_detailed fs
                LEFT JOIN school_levels sl ON fs.level_id = sl.id
                LEFT JOIN users u ON fs.created_by = u.id
                WHERE fs.id = ?
            ");

            $stmt->execute([$feeStructureId]);
            $feeStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feeStructure) {
                return formatResponse(false, null, 'Fee structure not found');
            }

            // Get detailed fee types
            $stmt = $this->db->prepare("
                SELECT fsd.*, ft.name as fee_type_name, ft.code as fee_type_code
                FROM fee_structures_detailed fsd
                INNER JOIN fee_types ft ON fsd.fee_type_id = ft.id
                WHERE fsd.fee_structure_id = ?
            ");

            $stmt->execute([$feeStructureId]);
            $feeStructure['fee_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $feeStructure);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to retrieve fee structure: ' . $e->getMessage());
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

            $sql = "SELECT fs.*, sl.name as level_name, sl.code as level_code,
                           at.name as term_name,
                           st.name as student_type_name, st.code as student_type_code,
                           ft.id as fee_type_id, ft.name as fee_name, ft.code as fee_type_code, ft.category as fee_category,
                           COUNT(DISTINCT sfo.student_id) as student_count
                    FROM fee_structures_detailed fs
                    LEFT JOIN school_levels sl ON fs.level_id = sl.id
                    LEFT JOIN academic_terms at ON fs.term_id = at.id
                    LEFT JOIN student_types st ON fs.student_type_id = st.id
                    LEFT JOIN fee_types ft ON fs.fee_type_id = ft.id
                    LEFT JOIN student_fee_obligations sfo ON fs.id = sfo.fee_structure_detail_id
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND fs.academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['level_id'])) {
                $sql .= " AND fs.level_id = ?";
                $params[] = $filters['level_id'];
            }

            if (!empty($filters['student_type_id'])) {
                $sql .= " AND fs.student_type_id = ?";
                $params[] = $filters['student_type_id'];
            }

            $termId = $filters['term_id'] ?? $filters['term'] ?? null;
            if (!empty($termId)) {
                $sql .= " AND fs.term_id = ?";
                $params[] = $termId;
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND fs.level_id = (SELECT level_id FROM classes WHERE id = ?)";
                $params[] = $filters['class_id'];
            }

            if (!empty($filters['class_ids']) && is_array($filters['class_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['class_ids']), '?'));
                $sql .= " AND fs.level_id IN (SELECT level_id FROM classes WHERE id IN ($placeholders))";
                $params = array_merge($params, $filters['class_ids']);
            }

            if (!empty($filters['status'])) {
                $sql .= " AND fs.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (fs.name LIKE ? OR fs.description LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }

            $sql .= " GROUP BY fs.id ORDER BY fs.academic_year DESC, fs.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $feeStructures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(DISTINCT fs.id) as total FROM fee_structures_detailed fs WHERE 1=1";
            $countParams = array_slice($params, 0, -2); // Remove limit and offset

            if (!empty($filters['academic_year'])) {
                $countSql .= " AND fs.academic_year = ?";
            }
            if (!empty($filters['level_id'])) {
                $countSql .= " AND fs.level_id = ?";
            }
            if (!empty($filters['student_type_id'])) {
                $countSql .= " AND fs.student_type_id = ?";
            }
            $termId = $filters['term_id'] ?? $filters['term'] ?? null;
            if (!empty($termId)) {
                $countSql .= " AND fs.term_id = ?";
            }
            if (!empty($filters['class_id'])) {
                $countSql .= " AND fs.level_id = (SELECT level_id FROM classes WHERE id = ?)";
            }
            if (!empty($filters['class_ids']) && is_array($filters['class_ids'])) {
                $placeholders = implode(',', array_fill(0, count($filters['class_ids']), '?'));
                $countSql .= " AND fs.level_id IN (SELECT level_id FROM classes WHERE id IN ($placeholders))";
            }
            if (!empty($filters['status'])) {
                $countSql .= " AND fs.status = ?";
            }
            if (!empty($filters['search'])) {
                $countSql .= " AND (fs.name LIKE ? OR fs.description LIKE ?)";
            }

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return formatResponse(true, [
                'fee_structures' => $feeStructures,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to list fee structures: ' . $e->getMessage());
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
            $stmt = $this->db->query("SELECT id FROM academic_terms WHERE status = 'current' LIMIT 1");
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

            // Aggregate obligations
            $stmt = $this->db->prepare("
                SELECT 
                    COALESCE(SUM(amount_due), 0) AS total_amount,
                    COALESCE(SUM(amount_paid), 0) AS amount_paid,
                    COALESCE(SUM(balance), 0) AS balance,
                    MAX(due_date) AS due_date
                FROM student_fee_obligations
                WHERE student_id = ? AND academic_year_id = ? AND term_id = ?
            ");
            $stmt->execute([$studentId, $academicYearId, $termId]);
            $totals = $stmt->fetch(PDO::FETCH_ASSOC);

            if (empty($totals) || floatval($totals['total_amount']) <= 0) {
                return formatResponse(false, null, 'No fee obligations found for student');
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

            $dueDate = $totals['due_date'] ?? null;

            // Upsert invoice
            $stmt = $this->db->prepare("
                INSERT INTO fee_invoices
                    (student_id, academic_year_id, term_id, total_amount, amount_paid, balance, status, due_date, generated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    total_amount = VALUES(total_amount),
                    amount_paid = VALUES(amount_paid),
                    balance = VALUES(balance),
                    status = VALUES(status),
                    due_date = VALUES(due_date),
                    generated_by = VALUES(generated_by),
                    updated_at = NOW()
            ");

            $stmt->execute([
                $studentId,
                $academicYearId,
                $termId,
                $totalAmount,
                $amountPaid,
                $balance,
                $status,
                $dueDate,
                $generatedBy
            ]);

            // Return latest invoice
            $stmt = $this->db->prepare("
                SELECT * FROM fee_invoices
                WHERE student_id = ? AND academic_year_id = ? AND term_id = ?
                LIMIT 1
            ");
            $stmt->execute([$studentId, $academicYearId, $termId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, $invoice, 'Invoice generated successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate invoice: ' . $e->getMessage());
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
            $where = "WHERE sfo.academic_year_id = ? AND sfo.term_id = ?";

            if (!empty($filters['class_id'])) {
                $where .= " AND cs.class_id = ?";
                $bindings[] = $filters['class_id'];
            }
            if (!empty($filters['stream_id'])) {
                $where .= " AND s.stream_id = ?";
                $bindings[] = $filters['stream_id'];
            }

            $stmt = $this->db->prepare("
                SELECT DISTINCT s.id AS student_id
                FROM student_fee_obligations sfo
                JOIN students s ON sfo.student_id = s.id
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
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
            return formatResponse(false, null, 'Failed to generate invoices: ' . $e->getMessage());
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

            $stmt = $this->db->prepare("
                SELECT * FROM fee_invoices
                WHERE student_id = ? AND academic_year_id = ? AND term_id = ?
                LIMIT 1
            ");
            $stmt->execute([$studentId, $academicYearId, $termId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) {
                return formatResponse(false, null, 'Invoice not found');
            }
            return formatResponse(true, $invoice, 'Invoice retrieved successfully');
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to retrieve invoice: ' . $e->getMessage());
        }
    }

    /**
     * List fee types
     * @return array Response with fee types list
     */
    public function listFeeTypes()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, code, name, description, category, is_mandatory, status
                FROM fee_types
                WHERE status = 'active'
                ORDER BY name ASC
            ");
            $stmt->execute();
            $feeTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $feeTypes);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to list fee types: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to list student types: ' . $e->getMessage());
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

            // Get term IDs for this academic year
            $stmt = $this->db->prepare(
                "SELECT id, term_number FROM academic_terms WHERE year = ? ORDER BY term_number"
            );
            $stmt->execute([$data['academic_year']]);
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($terms)) {
                throw new Exception("No terms found for academic year {$data['academic_year']}");
            }

            $termMap = [];
            foreach ($terms as $term) {
                $termMap[$term['term_number']] = $term['id'];
            }

            $updatedCount = 0;
            $createdCount = 0;

            foreach ($data['term_breakdown'] as $feeTypeName => $termAmounts) {
                // Get fee_type_id from name or code
                $stmt = $this->db->prepare(
                    "SELECT id FROM fee_types WHERE name = ? OR code = ? LIMIT 1"
                );
                $stmt->execute([$feeTypeName, $feeTypeName]);
                $feeType = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$feeType) {
                    throw new Exception("Fee type '{$feeTypeName}' not found");
                }

                foreach ($termAmounts as $termKey => $amount) {
                    $termNumber = (int) str_replace('term', '', $termKey);
                    if (!isset($termMap[$termNumber])) {
                        continue;
                    }

                    $termId = $termMap[$termNumber];

                    $updateStmt = $this->db->prepare("
                        UPDATE fee_structures_detailed
                        SET amount = ?, status = 'draft', updated_by = ?, updated_at = NOW()
                        WHERE level_id = ?
                        AND academic_year = ?
                        AND term_id = ?
                        AND student_type_id = ?
                        AND fee_type_id = ?
                    ");

                    $updateStmt->execute([
                        $amount,
                        $data['updated_by'],
                        $data['level_id'],
                        $data['academic_year'],
                        $termId,
                        $data['student_type_id'],
                        $feeType['id']
                    ]);

                    if ($updateStmt->rowCount() > 0) {
                        $updatedCount++;
                    } else {
                        $insertStmt = $this->db->prepare("
                            INSERT INTO fee_structures_detailed (
                                level_id,
                                academic_year,
                                term_id,
                                student_type_id,
                                fee_type_id,
                                amount,
                                status,
                                created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
                        ");

                        $insertStmt->execute([
                            $data['level_id'],
                            $data['academic_year'],
                            $termId,
                            $data['student_type_id'],
                            $feeType['id'],
                            $amount,
                            $data['updated_by']
                        ]);

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
            return formatResponse(false, null, 'Failed to update annual fee structure: ' . $e->getMessage());
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

            $params = [
                $data['academic_year'],
                $data['level_id'],
                $data['student_type_id']
            ];

            $sql = "
                SELECT id
                FROM fee_structures_detailed
                WHERE academic_year = ?
                AND level_id = ?
                AND student_type_id = ?
            ";

            if (!empty($data['term_id'])) {
                $sql .= " AND term_id = ?";
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
                "SELECT COUNT(*) as count FROM student_fee_obligations WHERE fee_structure_detail_id IN ($placeholders)"
            );
            $checkStmt->execute($ids);
            $inUse = (int) $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($inUse > 0) {
                return formatResponse(false, null, 'Cannot delete: Fee structure is in use by ' . $inUse . ' student(s)');
            }

            $deleteStmt = $this->db->prepare(
                "DELETE FROM fee_structures_detailed WHERE id IN ($placeholders)"
            );
            $deleteStmt->execute($ids);

            return formatResponse(true, [
                'structures_deleted' => count($ids),
                'message' => 'Fee structures deleted successfully'
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to delete fee structures: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to calculate fees: ' . $e->getMessage());
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
        try {
            $summaryWhere = ["sfo.student_id = ?"];
            $summaryParams = [$studentId];

            if (!empty($academicYear)) {
                $summaryWhere[] = "sfo.academic_year = ?";
                $summaryParams[] = $academicYear;
            }

            $summarySql = "
                SELECT
                    s.id AS student_id,
                    s.admission_no,
                    CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                    c.name AS class_name,
                    cs.stream_name,
                    COALESCE(SUM(sfo.amount_due), 0) AS total_due,
                    COALESCE(SUM(sfo.amount_paid), 0) AS total_paid,
                    COALESCE(SUM(sfo.amount_waived), 0) AS total_waived,
                    COALESCE(SUM(sfo.balance), 0) AS total_balance,
                    MAX(sfo.updated_at) AS last_updated
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN student_fee_obligations sfo ON s.id = sfo.student_id
                WHERE " . implode(' AND ', $summaryWhere) . "
                GROUP BY s.id, s.admission_no, s.first_name, s.last_name, c.name, cs.stream_name
            ";

            $summaryStmt = $this->db->prepare($summarySql);
            $summaryStmt->execute($summaryParams);
            $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

            if (!$summary) {
                return formatResponse(false, null, 'Student not found');
            }

            $termsWhere = ["sfo.student_id = ?"];
            $termsParams = [$studentId];
            if (!empty($academicYear)) {
                $termsWhere[] = "sfo.academic_year = ?";
                $termsParams[] = $academicYear;
            }

            $termsSql = "
                SELECT
                    sfo.term_id,
                    at.name AS term_name,
                    at.term_number,
                    sfo.academic_year,
                    SUM(sfo.amount_due) AS amount_due,
                    SUM(sfo.amount_paid) AS amount_paid,
                    SUM(sfo.amount_waived) AS amount_waived,
                    SUM(sfo.balance) AS balance
                FROM student_fee_obligations sfo
                LEFT JOIN academic_terms at ON sfo.term_id = at.id
                WHERE " . implode(' AND ', $termsWhere) . "
                GROUP BY sfo.academic_year, sfo.term_id, at.name, at.term_number
                ORDER BY sfo.academic_year DESC, at.term_number DESC, sfo.term_id DESC
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
            return formatResponse(false, null, 'Failed to retrieve balance: ' . $e->getMessage());
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
                $data['fee_structure_id'] ?? null,
                $data['discount_type'], // 'percentage' or 'fixed'
                $data['amount'],
                $data['reason'],
                $data['approved_by'] ?? null,
                $data['academic_year'] ?? date('Y')
            ]);

            return formatResponse(true, ['message' => 'Discount applied successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to apply discount: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to carryover balance: ' . $e->getMessage());
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
            // Call stored procedure
            $stmt = $this->db->prepare("CALL sp_send_fee_reminder(?)");
            $stmt->execute([$studentId]);

            return formatResponse(true, ['message' => 'Fee reminder sent successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to send reminder: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to get collection rate: ' . $e->getMessage());
        }
    }

    /**
     * Get outstanding fees report
     * @param array $filters Filter criteria
     * @return array Response with outstanding fees data
     */
    public function getOutstandingFeesReport($filters = [])
    {
        try {
            // Use the view vw_outstanding_fees
            $sql = "SELECT * FROM vw_outstanding_fees WHERE 1=1";
            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['level_id'])) {
                $sql .= " AND level_id = ?";
                $params[] = $filters['level_id'];
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND class_id = ?";
                $params[] = $filters['class_id'];
            }

            if (!empty($filters['min_balance'])) {
                $sql .= " AND outstanding_balance >= ?";
                $params[] = $filters['min_balance'];
            }

            $sql .= " ORDER BY outstanding_balance DESC";

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
            return formatResponse(false, null, 'Failed to generate report: ' . $e->getMessage());
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
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    c.name as class_name,
                    cs.stream_name,
                    sl.name as level_name
                FROM students s
                LEFT JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON cs.class_id = c.id
                LEFT JOIN school_levels sl ON c.level_id = sl.id
                WHERE s.id = ?
            ");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                return formatResponse(false, null, 'Student not found');
            }

            // Get fee obligations
            $stmt = $this->db->prepare("
                SELECT
                    sfo.*,
                    at.name AS term_name,
                    at.term_number,
                    ft.name AS fee_type_name,
                    fsd.amount AS configured_amount
                FROM student_fee_obligations sfo
                LEFT JOIN academic_terms at ON sfo.term_id = at.id
                LEFT JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
                LEFT JOIN fee_types ft ON fsd.fee_type_id = ft.id
                WHERE sfo.student_id = ? AND sfo.academic_year = ?
                ORDER BY at.term_number ASC, ft.name ASC, sfo.id ASC
            ");
            $stmt->execute([$studentId, $academicYear]);
            $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payments
            $stmt = $this->db->prepare("
                SELECT
                    pt.*,
                    at.name AS term_name,
                    at.term_number
                FROM payment_transactions pt
                LEFT JOIN academic_terms at ON pt.term_id = at.id
                WHERE pt.student_id = ? AND pt.academic_year = ?
                ORDER BY payment_date DESC
            ");
            $stmt->execute([$studentId, $academicYear]);
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
            return formatResponse(false, null, 'Failed to generate statement: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to transition fees: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to transition fees: ' . $e->getMessage());
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
            $sql = "SELECT * FROM vw_fee_schedule_by_class WHERE class_id = ?";
            $params = [$classId];

            if ($academicYear) {
                $sql .= " AND academic_year = ?";
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
            return formatResponse(false, null, 'Failed to get class fee schedule: ' . $e->getMessage());
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
            $sql = "SELECT * FROM vw_fee_carryover_summary WHERE 1=1";
            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['class_id'])) {
                $sql .= " AND class_id = ?";
                $params[] = $filters['class_id'];
            }

            $sql .= " ORDER BY carryover_amount DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'summary' => $summary,
                'total_carryover' => array_sum(array_column($summary, 'carryover_amount')),
                'students_affected' => count($summary)
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get carryover summary: ' . $e->getMessage());
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
                $sql .= " AND from_year = ?";
                $params[] = $filters['from_year'];
            }

            if (!empty($filters['to_year'])) {
                $sql .= " AND to_year = ?";
                $params[] = $filters['to_year'];
            }

            $sql .= " ORDER BY transition_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $audit = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'audit_trail' => $audit,
                'total_transitions' => count($audit)
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get transition audit: ' . $e->getMessage());
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

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['term'])) {
                $sql .= " AND term = ?";
                $params[] = $filters['term'];
            }

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
            return formatResponse(false, null, 'Failed to get fee type collection: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to calculate fee due: ' . $e->getMessage());
        }
    }

    /**
     * Send batch fee reminders using stored procedure
     * @return array Response
     */
    public function sendBatchFeeReminders()
    {
        try {
            // Call stored procedure sp_send_fee_reminders (plural)
            $stmt = $this->db->prepare("CALL sp_send_fee_reminders()");
            $stmt->execute();

            return formatResponse(true, ['message' => 'Batch fee reminders sent successfully']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to send batch reminders: ' . $e->getMessage());
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

            // Get term IDs for this academic year
            $stmt = $this->db->query(
                "SELECT id, term_number FROM academic_terms WHERE year = ? ORDER BY term_number",
                [$data['academic_year']]
            );

            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($terms)) {
                throw new Exception("No terms found for academic year {$data['academic_year']}");
            }

            $termMap = [];
            foreach ($terms as $term) {
                $termMap[$term['term_number']] = $term['id'];
            }

            // Create fee structures for each fee type and term
            foreach ($data['term_breakdown'] as $feeTypeName => $termAmounts) {
                // Get fee_type_id from name
                $stmt = $this->db->query(
                    "SELECT id FROM fee_types WHERE name = ? OR code = ? LIMIT 1",
                    [$feeTypeName, $feeTypeName]
                );

                $feeType = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$feeType) {
                    throw new Exception("Fee type '{$feeTypeName}' not found");
                }

                // Insert fee structure for each term
                foreach ($termAmounts as $termKey => $amount) {
                    $termNumber = (int) str_replace('term', '', $termKey);

                    if (!isset($termMap[$termNumber])) {
                        continue; // Skip if term doesn't exist
                    }

                    $stmt = $this->db->prepare("
                        INSERT INTO fee_structures_detailed (
                            level_id,
                            academic_year,
                            term_id,
                            student_type_id,
                            fee_type_id,
                            amount,
                            status,
                            created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)
                    ");

                    $stmt->execute([
                        $data['level_id'],
                        $data['academic_year'],
                        $termMap[$termNumber],
                        $data['student_type_id'],
                        $feeType['id'],
                        $amount,
                        $data['created_by']
                    ]);

                    $structuresCreated++;
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'structures_created' => $structuresCreated,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'message' => 'Annual fee structure created successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to create annual fee structure: ' . $e->getMessage());
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

            $sql = "
                UPDATE fee_structures_detailed
                SET status = 'reviewed',
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    rollover_notes = CONCAT(COALESCE(rollover_notes, ''), '\n', 'Reviewed on ', NOW(), ': ', ?)
                WHERE academic_year = ?
                AND level_id = ?
                AND status IN ('draft', 'pending_review')
            ";

            $params = [
                $data['reviewed_by'],
                $data['notes'] ?? 'Reviewed and approved',
                $data['academic_year'],
                $data['level_id']
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
            return formatResponse(false, null, 'Failed to review fee structure: ' . $e->getMessage());
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

            $sql = "
                UPDATE fee_structures_detailed
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    rollover_notes = CONCAT(COALESCE(rollover_notes, ''), '\n', 'Approved on ', NOW(), ': ', ?)
                WHERE academic_year = ?
                AND level_id = ?
                AND status = 'reviewed'
            ";

            $params = [
                $data['approved_by'],
                $data['notes'] ?? 'Approved for activation',
                $data['academic_year'],
                $data['level_id']
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
                'structures_approved' => $updatedCount,
                'academic_year' => $data['academic_year'],
                'level_id' => $data['level_id'],
                'message' => 'Fee structures approved successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to approve fee structure: ' . $e->getMessage());
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

            $sql = "
                UPDATE fee_structures_detailed
                SET status = 'active',
                    activated_at = NOW()
                WHERE academic_year = ?
                AND level_id = ?
                AND status = 'approved'
            ";

            $params = [
                $data['academic_year'],
                $data['level_id']
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
            return formatResponse(false, null, 'Failed to activate fee structure: ' . $e->getMessage());
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
                $data['executed_by'] ?? null
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
            return formatResponse(false, null, 'Failed to rollover fee structures: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to get term breakdown: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to get payment history: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to compare collections: ' . $e->getMessage());
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
            return formatResponse(false, null, 'Failed to get pending reviews: ' . $e->getMessage());
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
            $sql = "SELECT * FROM vw_fee_structure_annual_summary WHERE academic_year = ?";
            $params = [$academicYear];

            if ($levelId) {
                $sql .= " AND level_id = ?";
                $params[] = $levelId;
            }

            $sql .= " ORDER BY level_name, fee_category, fee_type";

            $stmt = $this->db->query($sql, $params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'academic_year' => $academicYear,
                'level_filter' => $levelId,
                'summary' => $summary
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get annual summary: ' . $e->getMessage());
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
                WHERE fee_structure_detail_id = ?
            ");
            $stmt->execute([$structureId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                return formatResponse(false, null, 'Cannot delete: Fee structure is in use by ' . $result['count'] . ' student(s)');
            }

            // Delete fee items first
            $stmt = $this->db->prepare("DELETE FROM fee_structures WHERE fee_structure_detail_id = ?");
            $stmt->execute([$structureId]);

            // Delete the structure
            $stmt = $this->db->prepare("DELETE FROM fee_structures_detailed WHERE id = ?");
            $stmt->execute([$structureId]);

            return formatResponse(true, null, 'Fee structure deleted successfully');

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to delete fee structure: ' . $e->getMessage());
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
            $stmt = $this->db->prepare("SELECT * FROM fee_structures_detailed WHERE id = ?");
            $stmt->execute([$sourceStructureId]);
            $sourceStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sourceStructure) {
                return formatResponse(false, null, 'Source fee structure not found');
            }

            // Create new structure record
            $multiplier = (100 + $priceAdjustment) / 100;

            $stmt = $this->db->prepare("
                INSERT INTO fee_structures_detailed 
                (class_id, level_id, academic_year, status, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $sourceStructure['class_id'],
                $sourceStructure['level_id'],
                $targetYear,
                'draft',
                $data['created_by'] ?? null
            ]);

            $newStructureId = $this->db->lastInsertId();

            // Copy fee items with price adjustment
            $stmt = $this->db->prepare("
                SELECT * FROM fee_structures WHERE fee_structure_detail_id = ?
            ");
            $stmt->execute([$sourceStructureId]);
            $feeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $insertStmt = $this->db->prepare("
                INSERT INTO fee_structures 
                (fee_structure_detail_id, name, code, amount, description)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($feeItems as $item) {
                $newAmount = $item['amount'] * $multiplier;
                $insertStmt->execute([
                    $newStructureId,
                    $item['name'],
                    $item['code'],
                    $newAmount,
                    $item['description']
                ]);
            }

            return formatResponse(true, [
                'new_structure_id' => $newStructureId,
                'source_structure_id' => $sourceStructureId,
                'target_academic_year' => $targetYear,
                'price_adjustment' => $priceAdjustment,
                'items_copied' => count($feeItems)
            ], 'Fee structure duplicated successfully');

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to duplicate fee structure: ' . $e->getMessage());
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

            // Validate that draft rows exist for this bundle
            $stmt = $this->db->prepare("
                SELECT COUNT(*) AS cnt
                FROM fee_structures_detailed
                WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                  AND status IN ('draft', 'pending_review')
            ");
            $stmt->execute([
                $data['level_id'],
                $data['academic_year'],
                $data['term_id'],
                $data['student_type_id'],
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $lineItemCount = (int) $row['cnt'];

            if ($lineItemCount === 0) {
                return formatResponse(false, null, 'No draft fee structure rows found for this bundle');
            }

            $this->db->beginTransaction();

            // Update draft rows to pending_review
            $stmt = $this->db->prepare("
                UPDATE fee_structures_detailed
                SET status = 'pending_review', updated_by = ?, updated_at = NOW()
                WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                  AND status = 'draft'
            ");
            $stmt->execute([
                $data['submitted_by'],
                $data['level_id'],
                $data['academic_year'],
                $data['term_id'],
                $data['student_type_id'],
            ]);

            // Upsert fee_structure_approvals record
            $stmt = $this->db->prepare("
                INSERT INTO fee_structure_approvals
                    (level_id, academic_year, term_id, student_type_id, status, submitted_by, submitted_at, review_notes)
                VALUES (?, ?, ?, ?, 'submitted', ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE
                    status = 'submitted',
                    submitted_by = VALUES(submitted_by),
                    submitted_at = NOW(),
                    review_notes = VALUES(review_notes)
            ");
            $stmt->execute([
                $data['level_id'],
                $data['academic_year'],
                $data['term_id'],
                $data['student_type_id'],
                $data['submitted_by'],
                $data['notes'] ?? null,
            ]);

            // Fetch the approval record
            $approvalId = $this->db->lastInsertId();
            if (!$approvalId) {
                $lookupStmt = $this->db->prepare("
                    SELECT id FROM fee_structure_approvals
                    WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                    LIMIT 1
                ");
                $lookupStmt->execute([
                    $data['level_id'],
                    $data['academic_year'],
                    $data['term_id'],
                    $data['student_type_id'],
                ]);
                $approvalId = $lookupStmt->fetchColumn();
            }

            $approvalStmt = $this->db->prepare("SELECT * FROM fee_structure_approvals WHERE id = ?");
            $approvalStmt->execute([$approvalId]);
            $approval = $approvalStmt->fetch(PDO::FETCH_ASSOC);

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
            if (strpos($e->getMessage(), "fee_structure_approvals") !== false) {
                return formatResponse(false, null, 'fee_structure_approvals table does not exist. Please run the migration first.');
            }
            return formatResponse(false, null, 'Failed to submit fee structure bundle: ' . $e->getMessage());
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to submit fee structure bundle: ' . $e->getMessage());
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

            // Fetch approval record
            $stmt = $this->db->prepare("SELECT * FROM fee_structure_approvals WHERE id = ?");
            $stmt->execute([$data['approval_id']]);
            $approval = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$approval) {
                return formatResponse(false, null, 'Approval record not found');
            }

            $this->db->beginTransaction();

            if ($data['action'] === 'approve') {
                // Update approval record
                $stmt = $this->db->prepare("
                    UPDATE fee_structure_approvals
                    SET status = 'reviewed', reviewed_by = ?, reviewed_at = NOW(), review_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$data['reviewed_by'], $data['notes'], $data['approval_id']]);

                // Update fee_structures_detailed rows
                $stmt = $this->db->prepare("
                    UPDATE fee_structures_detailed
                    SET status = 'reviewed', reviewed_by = ?, reviewed_at = NOW()
                    WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                ");
                $stmt->execute([
                    $data['reviewed_by'],
                    $approval['level_id'],
                    $approval['academic_year'],
                    $approval['term_id'],
                    $approval['student_type_id'],
                ]);
            } else {
                // Reject: update approval record
                $stmt = $this->db->prepare("
                    UPDATE fee_structure_approvals
                    SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$data['reviewed_by'], $data['notes'], $data['approval_id']]);

                // Reset fee_structures_detailed back to draft
                $stmt = $this->db->prepare("
                    UPDATE fee_structures_detailed
                    SET status = 'draft'
                    WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                ");
                $stmt->execute([
                    $approval['level_id'],
                    $approval['academic_year'],
                    $approval['term_id'],
                    $approval['student_type_id'],
                ]);
            }

            // Fetch updated approval record
            $stmt = $this->db->prepare("SELECT * FROM fee_structure_approvals WHERE id = ?");
            $stmt->execute([$data['approval_id']]);
            $updatedApproval = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->db->commit();

            return formatResponse(true, [
                'approval' => $updatedApproval,
                'message' => 'Fee structure bundle ' . ($data['action'] === 'approve' ? 'reviewed' : 'rejected') . ' successfully'
            ]);

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (strpos($e->getMessage(), "fee_structure_approvals") !== false) {
                return formatResponse(false, null, 'fee_structure_approvals table does not exist. Please run the migration first.');
            }
            return formatResponse(false, null, 'Failed to review fee structure bundle: ' . $e->getMessage());
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to review fee structure bundle: ' . $e->getMessage());
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

            // Fetch approval record
            $stmt = $this->db->prepare("SELECT * FROM fee_structure_approvals WHERE id = ?");
            $stmt->execute([$data['approval_id']]);
            $approval = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$approval) {
                return formatResponse(false, null, 'Approval record not found');
            }

            $this->db->beginTransaction();

            $obligationsCount = 0;

            if ($data['action'] === 'approve') {
                // Update approval record to approved
                $stmt = $this->db->prepare("
                    UPDATE fee_structure_approvals
                    SET status = 'approved', approved_by = ?, approved_at = NOW(), approval_notes = ?
                    WHERE id = ?
                ");
                $stmt->execute([$data['approved_by'], $data['notes'], $data['approval_id']]);

                // Update fee_structures_detailed to approved
                $stmt = $this->db->prepare("
                    UPDATE fee_structures_detailed
                    SET status = 'approved', approved_by = ?, approved_at = NOW()
                    WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                ");
                $stmt->execute([
                    $data['approved_by'],
                    $approval['level_id'],
                    $approval['academic_year'],
                    $approval['term_id'],
                    $approval['student_type_id'],
                ]);

                $this->db->commit();

                // Activate and generate obligations (outside transaction to avoid nesting issues)
                $result = $this->activateAndGenerateObligations(
                    $approval['level_id'],
                    $approval['academic_year'],
                    $approval['term_id'],
                    $approval['student_type_id'],
                    $data['approved_by']
                );

                $obligationsCount = 0;
                if (!empty($result['data']['obligations_created'])) {
                    $obligationsCount = (int) $result['data']['obligations_created'];
                }

                // Update approval record with active status and obligations count
                $stmt = $this->db->prepare("
                    UPDATE fee_structure_approvals
                    SET status = 'active', obligations_generated = 1, obligations_count = ?
                    WHERE id = ?
                ");
                $stmt->execute([$obligationsCount, $data['approval_id']]);

            } else {
                // Reject
                $stmt = $this->db->prepare("
                    UPDATE fee_structure_approvals
                    SET status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([$data['approved_by'], $data['notes'], $data['approval_id']]);

                // Reset fee_structures_detailed back to draft
                $stmt = $this->db->prepare("
                    UPDATE fee_structures_detailed
                    SET status = 'draft'
                    WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                ");
                $stmt->execute([
                    $approval['level_id'],
                    $approval['academic_year'],
                    $approval['term_id'],
                    $approval['student_type_id'],
                ]);

                $this->db->commit();
            }

            // Fetch updated approval record
            $stmt = $this->db->prepare("SELECT * FROM fee_structure_approvals WHERE id = ?");
            $stmt->execute([$data['approval_id']]);
            $updatedApproval = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'approval' => $updatedApproval,
                'obligations_count' => $obligationsCount,
                'message' => 'Fee structure bundle ' . ($data['action'] === 'approve' ? 'approved and activated' : 'rejected') . ' successfully'
            ]);

        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (strpos($e->getMessage(), "fee_structure_approvals") !== false) {
                return formatResponse(false, null, 'fee_structure_approvals table does not exist. Please run the migration first.');
            }
            return formatResponse(false, null, 'Failed to approve fee structure bundle: ' . $e->getMessage());
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to approve fee structure bundle: ' . $e->getMessage());
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
            // 1. Mark fee_structures_detailed as active
            $stmt = $this->db->prepare("
                UPDATE fee_structures_detailed
                SET status = 'active', activated_at = NOW()
                WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
            ");
            $stmt->execute([$levelId, $academicYear, $termId, $studentTypeId]);

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

            // 3. Get active students enrolled in this level + student_type
            $stmt = $this->db->prepare("
                SELECT DISTINCT s.id AS student_id
                FROM students s
                JOIN class_enrollments ce ON ce.student_id = s.id
                JOIN classes c ON ce.class_id = c.id
                WHERE c.level_id = ?
                  AND s.student_type_id = ?
                  AND s.status = 'active'
                  AND ce.academic_year_id = ?
                  AND ce.enrollment_status = 'active'
            ");
            $stmt->execute([$levelId, $studentTypeId, $academicYearId]);
            $students = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // 4. Get all fee structure detail rows for this bundle
            $fsdStmt = $this->db->prepare("
                SELECT id, amount, due_date
                FROM fee_structures_detailed
                WHERE level_id = ? AND academic_year = ? AND term_id = ? AND student_type_id = ?
                  AND status = 'active'
            ");
            $fsdStmt->execute([$levelId, $academicYear, $termId, $studentTypeId]);
            $feeRows = $fsdStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($feeRows)) {
                return formatResponse(true, [
                    'students_processed' => 0,
                    'obligations_created' => 0,
                    'message' => 'No active fee structure rows found after activation'
                ]);
            }

            // 5. Insert obligations for each student × each fee row
            $insertStmt = $this->db->prepare("
                INSERT INTO student_fee_obligations
                    (student_id, academic_year, term_id, fee_structure_detail_id,
                     amount_due, amount_paid, amount_waived, status, payment_status, due_date, created_at)
                VALUES (?, ?, ?, ?, ?, 0, 0, 'pending', 'pending', ?, NOW())
                ON DUPLICATE KEY UPDATE
                    amount_due = VALUES(amount_due),
                    due_date   = VALUES(due_date)
            ");

            $totalObligations = 0;
            foreach ($students as $studentId) {
                foreach ($feeRows as $feeRow) {
                    $insertStmt->execute([
                        $studentId,
                        $academicYear,
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
            return formatResponse(false, null, 'Failed to activate and generate obligations: ' . $e->getMessage());
        }
    }

    /**
     * Get a paginated list of fee structure bundles (from fee_structure_approvals)
     * @param array $filters Optional: status, academic_year, term_id, level_id
     * @param int $page
     * @param int $limit
     * @return array Response with paginated list
     */
    public function getFeeStructureBundles($filters = [], $page = 1, $limit = 20)
    {
        try {
            $offset = ($page - 1) * $limit;

            $where = "WHERE 1=1";
            $params = [];

            if (!empty($filters['status'])) {
                $where .= " AND fsa.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['academic_year'])) {
                $where .= " AND fsa.academic_year = ?";
                $params[] = $filters['academic_year'];
            }
            if (!empty($filters['term_id'])) {
                $where .= " AND fsa.term_id = ?";
                $params[] = $filters['term_id'];
            }
            if (!empty($filters['level_id'])) {
                $where .= " AND fsa.level_id = ?";
                $params[] = $filters['level_id'];
            }

            $sql = "
                SELECT fsa.*,
                       sl.name  AS level_name,
                       at.name  AS term_name,
                       st.name  AS student_type_name,
                       COUNT(fsd.id) AS line_item_count,
                       SUM(fsd.amount) AS total_amount,
                       u_sub.display_name AS submitted_by_name,
                       u_apr.display_name AS approved_by_name
                FROM fee_structure_approvals fsa
                JOIN school_levels sl ON fsa.level_id = sl.id
                JOIN academic_terms at ON fsa.term_id = at.id
                JOIN student_types st ON fsa.student_type_id = st.id
                LEFT JOIN fee_structures_detailed fsd
                       ON fsd.level_id = fsa.level_id
                      AND fsd.academic_year = fsa.academic_year
                      AND fsd.term_id = fsa.term_id
                      AND fsd.student_type_id = fsa.student_type_id
                LEFT JOIN users u_sub ON fsa.submitted_by = u_sub.id
                LEFT JOIN users u_apr ON fsa.approved_by = u_apr.id
                $where
                GROUP BY fsa.id
                ORDER BY fsa.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $listParams = array_merge($params, [$limit, $offset]);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($listParams);
            $bundles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Count total
            $countSql = "
                SELECT COUNT(DISTINCT fsa.id) AS total
                FROM fee_structure_approvals fsa
                JOIN school_levels sl ON fsa.level_id = sl.id
                JOIN academic_terms at ON fsa.term_id = at.id
                JOIN student_types st ON fsa.student_type_id = st.id
                $where
            ";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
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
            if (strpos($e->getMessage(), "fee_structure_approvals") !== false) {
                return formatResponse(false, null, 'fee_structure_approvals table does not exist. Please run the migration first.');
            }
            return formatResponse(false, null, 'Failed to get fee structure bundles: ' . $e->getMessage());
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get fee structure bundles: ' . $e->getMessage());
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
            $stmt = $this->db->prepare("
                SELECT sfo.*,
                       at.name        AS term_name,
                       at.term_number,
                       ft.name        AS fee_type_name,
                       ft.code        AS fee_type_code,
                       sl.name        AS level_name,
                       c.name         AS class_name
                FROM student_fee_obligations sfo
                JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
                JOIN fee_types ft               ON fsd.fee_type_id = ft.id
                JOIN academic_terms at          ON sfo.term_id = at.id
                LEFT JOIN class_enrollments ce  ON ce.student_id = sfo.student_id
                                               AND YEAR(ce.created_at) = sfo.academic_year
                LEFT JOIN classes c             ON ce.class_id = c.id
                LEFT JOIN school_levels sl      ON c.level_id = sl.id
                WHERE sfo.student_id = ?
                ORDER BY sfo.academic_year DESC, at.term_number ASC
            ");
            $stmt->execute([$studentId]);
            $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch confirmed payments
            $stmt = $this->db->prepare("
                SELECT pt.*, at2.name AS term_name
                FROM payment_transactions pt
                JOIN academic_terms at2 ON pt.term_id = at2.id
                WHERE pt.student_id = ? AND pt.status = 'confirmed'
                ORDER BY pt.payment_date DESC
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
            return formatResponse(false, null, 'Failed to get student billing history: ' . $e->getMessage());
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
        try {
            if (empty($classId) || empty($academicYearId)) {
                return formatResponse(false, null, 'class_id and academic_year_id are required');
            }

            $termFilter        = $termId ? " AND sfo.term_id = $termId" : "";
            $pmtTermFilter     = $termId ? " AND pt.term_id = $termId" : "";

            $sql = "
                SELECT s.id,
                       s.first_name,
                       s.last_name,
                       s.admission_no,
                       st.name                        AS student_type,
                       COALESCE(SUM(sfo.amount_due),    0) AS total_billed,
                       COALESCE(SUM(sfo.amount_paid),   0) AS total_paid,
                       COALESCE(SUM(sfo.amount_waived), 0) AS total_waived,
                       COALESCE(SUM(sfo.balance),       0) AS balance,
                       MAX(sfo.payment_status)             AS payment_status,
                       MAX(pt.payment_date)                AS last_payment_date,
                       COUNT(DISTINCT pt.id)               AS payment_count
                FROM class_enrollments ce
                JOIN students s       ON s.id = ce.student_id
                JOIN student_types st ON s.student_type_id = st.id
                LEFT JOIN student_fee_obligations sfo
                       ON sfo.student_id = s.id
                      AND sfo.academic_year = (SELECT YEAR(start_date) FROM academic_years WHERE id = ?)
                      $termFilter
                LEFT JOIN payment_transactions pt
                       ON pt.student_id = s.id
                      AND pt.status = 'confirmed'
                      $pmtTermFilter
                WHERE ce.class_id = ?
                  AND ce.academic_year_id = ?
                  AND s.status = 'active'
                GROUP BY s.id, s.first_name, s.last_name, s.admission_no, st.name
                ORDER BY s.last_name, s.first_name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$academicYearId, $classId, $academicYearId]);
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
            return formatResponse(false, null, 'Failed to get class billing report: ' . $e->getMessage());
        }
    }
}
