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
                           COUNT(DISTINCT sfo.student_id) as student_count
                    FROM fee_structures_detailed fs
                    LEFT JOIN school_levels sl ON fs.level_id = sl.id
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
            $sql = "SELECT sfb.*, fs.name as fee_structure_name, fs.amount as total_fee,
                           s.student_no, CONCAT(s.first_name, ' ', s.last_name) as student_name
                    FROM student_fee_balances sfb
                    INNER JOIN students s ON sfb.student_id = s.id
                    LEFT JOIN fee_structures fs ON sfb.fee_structure_id = fs.id
                    WHERE sfb.student_id = ?";

            $params = [$studentId];

            if ($academicYear !== null) {
                $sql .= " AND sfb.academic_year = ?";
                $params[] = $academicYear;
            }

            $sql .= " ORDER BY sfb.academic_year DESC, sfb.term_id DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $balances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['balances' => $balances]);

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
            // Get student details
            $stmt = $this->db->prepare("
                SELECT s.admission_no, CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       c.name as class_name, sl.name as level_name
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
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
                SELECT sfo.*, fs.name as fee_structure_name, fs.amount as total_fee
                FROM student_fee_obligations sfo
                INNER JOIN fee_structures_detailed fs ON sfo.fee_structure_detail_id = fs.id
                WHERE sfo.student_id = ? AND sfo.academic_year = ?
            ");
            $stmt->execute([$studentId, $academicYear]);
            $obligations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get payments
            $stmt = $this->db->prepare("
                SELECT * FROM vw_all_school_payments
                WHERE student_id = ? AND academic_year = ?
                ORDER BY payment_date DESC
            ");
            $stmt->execute([$studentId, $academicYear]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get current balance
            $stmt = $this->db->prepare("
                SELECT * FROM student_fee_balances
                WHERE student_id = ? AND academic_year = ?
            ");
            $stmt->execute([$studentId, $academicYear]);
            $balance = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'student' => $student,
                'obligations' => $obligations,
                'payments' => $payments,
                'balance' => $balance,
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

            $stmt = $this->db->prepare("
                UPDATE fee_structures_detailed
                SET status = 'reviewed',
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    rollover_notes = CONCAT(COALESCE(rollover_notes, ''), '\n', 'Reviewed on ', NOW(), ': ', ?)
                WHERE academic_year = ?
                AND level_id = ?
                AND status IN ('draft', 'pending_review')
            ");

            $stmt->execute([
                $data['reviewed_by'],
                $data['notes'] ?? 'Reviewed and approved',
                $data['academic_year'],
                $data['level_id']
            ]);

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

            $stmt = $this->db->prepare("
                UPDATE fee_structures_detailed
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    rollover_notes = CONCAT(COALESCE(rollover_notes, ''), '\n', 'Approved on ', NOW(), ': ', ?)
                WHERE academic_year = ?
                AND level_id = ?
                AND status = 'reviewed'
            ");

            $stmt->execute([
                $data['approved_by'],
                $data['notes'] ?? 'Approved for activation',
                $data['academic_year'],
                $data['level_id']
            ]);

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

            $stmt = $this->db->prepare("
                UPDATE fee_structures_detailed
                SET status = 'active',
                    activated_at = NOW()
                WHERE academic_year = ?
                AND level_id = ?
                AND status = 'approved'
            ");

            $stmt->execute([
                $data['academic_year'],
                $data['level_id']
            ]);

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

            $stmt = $this->db->query($sql, $params);
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
}

