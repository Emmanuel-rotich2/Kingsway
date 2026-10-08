<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;
/**
 * Clearance Manager
 *
 * Manages multi-department clearance tracking for student transfers.
 * Built on the normalized `student_clearances` table (clearance_type enum:
 * finance/library/uniform/property/academic) linked to transfer requests in
 * `student_transitions` via `transfer_request_id`. The retired
 * `clearance_departments` / `student_transfers` tables no longer exist.
 */
class ClearanceManager extends BaseAPI
{
    /**
     * The five clearance types (live `student_clearances.clearance_type` enum).
     */
    private function departmentTypes(): array
    {
        return [
            ['code' => 'FINANCE',  'name' => 'Finance',   'is_mandatory' => true,  'sort_order' => 1, 'description' => 'Fee settlement verification'],
            ['code' => 'LIBRARY',  'name' => 'Library',   'is_mandatory' => true,  'sort_order' => 2, 'description' => 'Library books return'],
            ['code' => 'UNIFORM',  'name' => 'Uniform',   'is_mandatory' => true,  'sort_order' => 3, 'description' => 'Uniform items return'],
            ['code' => 'PROPERTY', 'name' => 'Property',  'is_mandatory' => true,  'sort_order' => 4, 'description' => 'School property return'],
            ['code' => 'ACADEMIC', 'name' => 'Academic',  'is_mandatory' => false, 'sort_order' => 5, 'description' => 'Academic records completion'],
        ];
    }

    /**
     * Map a department code to its clearance_type value.
     */
    private function typeForCode(string $code): ?string
    {
        $code = strtoupper(trim($code));
        foreach ($this->departmentTypes() as $dept) {
            if ($dept['code'] === $code) {
                return strtolower($dept['code']);
            }
        }
        return null;
    }

    /**
     * Get all active clearance departments
     * @return array Response with departments list
     */
    public function getDepartments()
    {
        try {
            $departments = array_map(function (array $dept) {
                $dept['is_active'] = true;
                $dept['check_procedure'] = null;
                return $dept;
            }, $this->departmentTypes());

            return formatResponse(true, $departments, 'Departments retrieved successfully');

        } catch (Exception $e) {
            $this->logError('getDepartments', $e->getMessage());
            return formatResponse(false, null, 'Failed to get departments: ' . $e->getMessage());
        }
    }

    /**
     * Check clearance for a specific student across all departments
     * Useful for pre-transfer checks
     * @param int $studentId Student ID
     * @return array Response with clearance status per department
     */
    public function checkStudentClearance($studentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT clearance_type, status, amount_outstanding, notes
                FROM student_clearances
                WHERE student_id = ? AND transfer_request_id IS NULL
                ORDER BY id ASC
            ");
            $stmt->execute([$studentId]);
            $existing = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $existing[$row['clearance_type']] = $row;
            }

            $clearanceStatus = [];

            foreach ($this->departmentTypes() as $dept) {
                $type = strtolower($dept['code']);
                $row = $existing[$type] ?? null;

                $status = [
                    'department_code' => $dept['code'],
                    'department_name' => $dept['name'],
                    'is_mandatory' => (bool) $dept['is_mandatory'],
                    'is_cleared' => false,
                    'has_issues' => true,
                    'issue_description' => null,
                    'outstanding_amount' => 0.00,
                ];

                if ($row) {
                    $status['is_cleared'] = $row['status'] === 'cleared';
                    $status['has_issues'] = $row['status'] !== 'cleared';
                    $status['issue_description'] = $row['status'] === 'blocked' ? ($row['notes'] ?? 'Blocked') : ($row['status'] === 'pending' ? 'Pending verification' : null);
                    $status['outstanding_amount'] = (float) ($row['amount_outstanding'] ?? 0);
                } else {
                    $status['manual_check_required'] = true;
                    $status['issue_description'] = 'Manual verification required';
                }

                $clearanceStatus[] = $status;
            }

            // Calculate summary
            $mandatoryClear = true;
            $allClear = true;
            $totalIssues = 0;

            foreach ($clearanceStatus as $status) {
                if ($status['has_issues']) {
                    $totalIssues++;
                    $allClear = false;
                    if ($status['is_mandatory']) {
                        $mandatoryClear = false;
                    }
                }
            }

            return formatResponse(true, [
                'clearances' => $clearanceStatus,
                'summary' => [
                    'all_clear' => $allClear,
                    'mandatory_clear' => $mandatoryClear,
                    'total_departments' => count($clearanceStatus),
                    'total_issues' => $totalIssues,
                    'can_transfer' => $mandatoryClear
                ]
            ], 'Clearance check completed');

        } catch (Exception $e) {
            $this->logError('checkStudentClearance', $e->getMessage());
            return formatResponse(false, null, 'Failed to check clearance: ' . $e->getMessage());
        }
    }

    /**
     * Grant waiver for a specific clearance
     * @param int $clearanceId Clearance record ID
     * @param array $data Waiver details
     * @return array Response
     */
    public function grantWaiver($clearanceId, $data)
    {
        $response = null;

        try {
            $required = ['waiver_reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                $response = formatResponse(false, null, 'Missing required field: waiver_reason');
            } else {
                $this->db->beginTransaction();

                $currentUserId = $this->getCurrentUserId();

                $notes = trim(
                    'Waiver granted: ' . $data['waiver_reason']
                    . ($data['resolution_notes'] ?? null ? ' | ' . $data['resolution_notes'] : '')
                );

                $sql = "UPDATE student_clearances SET
                    status = 'cleared',
                    checked_by = ?,
                    checked_at = NOW(),
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([$currentUserId, $notes, $clearanceId]);

                if ($stmt->rowCount() === 0) {
                    $this->db->rollBack();
                    $response = formatResponse(false, null, 'Clearance not found');
                } else {
                    $this->db->commit();
                    $this->logAction('update', $clearanceId, "Waiver granted: {$data['waiver_reason']}");
                    $response = formatResponse(true, ['clearance_id' => $clearanceId], 'Waiver granted successfully');
                }
            }
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('grantWaiver', $e->getMessage());
            $response = formatResponse(false, null, 'Failed to grant waiver: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Bulk clear student for multiple departments
     * Useful for students with no issues
     * @param int $transferId Transfer request ID (student_transitions.id)
     * @param array $departmentCodes Array of department codes to clear
     * @return array Response
     */
    public function bulkClear($transferId, $departmentCodes)
    {
        try {
            $this->db->beginTransaction();

            $currentUserId = $this->getCurrentUserId();
            $clearedCount = 0;

            $stmt = $this->db->prepare("SELECT student_id FROM " . ReadReplicaService::qualifiedRef("student_clearances") . " id = ?");
            $stmt->execute([$transferId]);
            $studentId = $stmt->fetchColumn();

            if (!$studentId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Transfer request not found');
            }

            foreach ($departmentCodes as $code) {
                $type = $this->typeForCode((string) $code);
                if (!$type) {
                    continue; // Skip invalid departments
                }

                $stmt = $this->db->prepare("
                    UPDATE student_clearances
                    SET status = 'cleared', checked_by = ?, checked_at = NOW(), updated_at = NOW()
                    WHERE transfer_request_id = ? AND clearance_type = ?
                ");
                $stmt->execute([$currentUserId, $transferId, $type]);

                if ($stmt->rowCount() === 0) {
                    $stmt = $this->db->prepare("
                        INSERT INTO student_clearances (student_id, transfer_request_id, clearance_type, status, checked_by, checked_at)
                        VALUES (?, ?, ?, 'cleared', ?, NOW())
                    ");
                    $stmt->execute([$studentId, $transferId, $type, $currentUserId]);
                }
                $clearedCount++;
            }

            $this->db->commit();
            $this->logAction('update', $transferId, "Bulk clearance: {$clearedCount} departments cleared");

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'cleared_count' => $clearedCount
            ], "{$clearedCount} departments cleared successfully");

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            $this->logError('bulkClear', $e->getMessage());
            return formatResponse(false, null, 'Failed to bulk clear: ' . $e->getMessage());
        }
    }

    /**
     * Get clearance summary for reporting
     * @param array $filters Optional filters (date range, status, etc.)
     * @return array Response with clearance statistics
     */
    public function getClearanceSummary($filters = [])
    {
        try {
            $where = ['1=1'];
            $params = [];

            if (!empty($filters['from_date'])) {
                $where[] = 'st.decided_at >= ?';
                $params[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $where[] = 'st.decided_at <= ?';
                $params[] = $filters['to_date'];
            }

            if (!empty($filters['status'])) {
                $where[] = 'st.executed_at IS NOT NULL';
                $params[] = $filters['status'];
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT
                    sc.clearance_type AS department,
                    COUNT(sc.id) as total_clearances,
                    SUM(CASE WHEN sc.status = 'cleared' THEN 1 ELSE 0 END) as cleared,
                    SUM(CASE WHEN sc.status = 'blocked' THEN 1 ELSE 0 END) as blocked,
                    SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    0 AS waivers_granted,
                    COALESCE(SUM(sc.amount_outstanding), 0) as total_outstanding
                FROM " . ReadReplicaService::qualifiedRef("student_clearances") . "
                JOIN " . ReadReplicaService::qualifiedRef("student_transitions") . " st ON sc.transfer_request_id = st.id
                WHERE {$whereClause}
                GROUP BY sc.clearance_type
                ORDER BY MIN(st.id)
            ");
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $summary, 'Clearance summary retrieved successfully');

        } catch (Exception $e) {
            $this->logError('getClearanceSummary', $e->getMessage());
            return formatResponse(false, null, 'Failed to get clearance summary: ' . $e->getMessage());
        }
    }
}
