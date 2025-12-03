<?php
namespace App\API\Modules\students;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
/**
 * Clearance Manager
 * 
 * Manages multi-department clearance tracking for student transfers
 * Provides utilities for checking clearance status across all departments
 */
class ClearanceManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('clearance');
    }

    /**
     * Get all active clearance departments
     * @return array Response with departments list
     */
    public function getDepartments()
    {
        try {
            $stmt = $this->db->query("
                SELECT * FROM clearance_departments 
                WHERE is_active = TRUE 
                ORDER BY sort_order
            ");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            // Get all active departments
            $stmt = $this->db->query("
                SELECT * FROM clearance_departments 
                WHERE is_active = TRUE 
                ORDER BY sort_order
            ");
            $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $clearanceStatus = [];

            foreach ($departments as $dept) {
                $status = [
                    'department_code' => $dept['code'],
                    'department_name' => $dept['name'],
                    'is_mandatory' => (bool) $dept['is_mandatory'],
                    'is_cleared' => false,
                    'has_issues' => false,
                    'issue_description' => null,
                    'outstanding_amount' => 0.00
                ];

                // Run automated check if procedure exists
                if ($dept['check_procedure']) {
                    try {
                        $stmt = $this->db->prepare("CALL {$dept['check_procedure']}(?, @is_cleared, @outstanding, @description)");
                        $stmt->execute([$studentId]);

                        $result = $this->db->query("SELECT @is_cleared as is_cleared, @outstanding as outstanding, @description as description")->fetch(PDO::FETCH_ASSOC);

                        $status['is_cleared'] = (bool) $result['is_cleared'];
                        $status['has_issues'] = !$result['is_cleared'];
                        $status['issue_description'] = $result['description'];
                        $status['outstanding_amount'] = $result['outstanding'] ?? 0.00;
                    } catch (Exception $e) {
                        $status['error'] = 'Check procedure failed: ' . $e->getMessage();
                    }
                } else {
                    // Manual clearance required
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

                $sql = "UPDATE student_clearances SET
                    status = 'cleared',
                    waiver_granted = TRUE,
                    waiver_granted_by = ?,
                    waiver_reason = ?,
                    resolution_notes = ?,
                    cleared_by = ?,
                    cleared_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $currentUserId,
                    $data['waiver_reason'],
                    $data['resolution_notes'] ?? 'Waiver granted',
                    $currentUserId,
                    $clearanceId
                ]);

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
            // ensure any open transaction is rolled back
            $this->db->rollBack();
            $this->logError('grantWaiver', $e->getMessage());
            $response = formatResponse(false, null, 'Failed to grant waiver: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Bulk clear student for multiple departments
     * Useful for students with no issues
     * @param int $transferId Transfer ID
     * @param array $departmentCodes Array of department codes to clear
     * @return array Response
     */
    public function bulkClear($transferId, $departmentCodes)
    {
        try {
            $this->db->beginTransaction();

            $currentUserId = $this->getCurrentUserId();
            $clearedCount = 0;

            foreach ($departmentCodes as $code) {
                // Get department
                $stmt = $this->db->prepare("SELECT id FROM clearance_departments WHERE code = ? AND is_active = TRUE");
                $stmt->execute([$code]);
                $dept = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$dept) {
                    continue; // Skip invalid departments
                }

                // Update or insert clearance
                $stmt = $this->db->prepare("
                    INSERT INTO student_clearances (transfer_id, department_id, status, cleared_by, cleared_at, has_issues)
                    VALUES (?, ?, 'cleared', ?, NOW(), FALSE)
                    ON DUPLICATE KEY UPDATE 
                        status = 'cleared',
                        cleared_by = VALUES(cleared_by),
                        cleared_at = VALUES(cleared_at),
                        has_issues = FALSE,
                        updated_at = NOW()
                ");
                $stmt->execute([$transferId, $dept['id'], $currentUserId]);
                $clearedCount++;
            }

            $this->db->commit();
            $this->logAction('update', $transferId, "Bulk clearance: {$clearedCount} departments cleared");

            return formatResponse(true, [
                'transfer_id' => $transferId,
                'cleared_count' => $clearedCount
            ], "{$clearedCount} departments cleared successfully");

        } catch (Exception $e) {
            $this->db->rollBack();
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
                $where[] = 'st.request_date >= ?';
                $params[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $where[] = 'st.request_date <= ?';
                $params[] = $filters['to_date'];
            }

            if (!empty($filters['status'])) {
                $where[] = 'st.status = ?';
                $params[] = $filters['status'];
            }

            $whereClause = implode(' AND ', $where);

            $stmt = $this->db->prepare("
                SELECT 
                    cd.name as department,
                    COUNT(sc.id) as total_clearances,
                    SUM(CASE WHEN sc.status = 'cleared' THEN 1 ELSE 0 END) as cleared,
                    SUM(CASE WHEN sc.status = 'blocked' THEN 1 ELSE 0 END) as blocked,
                    SUM(CASE WHEN sc.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN sc.waiver_granted THEN 1 ELSE 0 END) as waivers_granted,
                    SUM(sc.outstanding_amount) as total_outstanding
                FROM student_clearances sc
                JOIN clearance_departments cd ON sc.department_id = cd.id
                JOIN student_transfers st ON sc.transfer_id = st.id
                WHERE {$whereClause}
                GROUP BY cd.id, cd.name
                ORDER BY cd.sort_order
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

