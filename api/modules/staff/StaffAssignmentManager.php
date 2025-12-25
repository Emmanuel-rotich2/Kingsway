<?php
namespace App\API\Modules\staff;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Assignment Manager
 * 
 * Handles CRUD operations for staff-class assignments
 * - Assigns staff to classes per academic year
 * - Tracks teaching workload
 * - Manages subject assignments
 * - Handles staff transfers between classes
 * - Respects staff types, categories, and departments
 */
class StaffAssignmentManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Assign staff to a class for an academic year
     * @param array $data Assignment data
     * @return array Response
     */
    public function assignStaffToClass($data)
    {
        try {
            $required = ['staff_id', 'academic_year_id', 'class_stream_id', 'role'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Validate role
            $validRoles = ['class_teacher', 'subject_teacher', 'assistant_teacher', 'head_of_department'];
            if (!in_array($data['role'], $validRoles)) {
                return formatResponse(false, null, 'Invalid role. Must be: ' . implode(', ', $validRoles));
            }

            $this->db->beginTransaction();

            // Verify staff exists and get details
            $stmt = $this->db->prepare("
                SELECT s.*, st.name as staff_type, sc.category_name, d.name as department_name
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.id = ? AND s.status = 'active'
            ");
            $stmt->execute([$data['staff_id']]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active staff member not found');
            }

            // Verify academic year exists and is active
            $stmt = $this->db->prepare("
                SELECT * FROM academic_years WHERE id = ? AND status IN ('upcoming', 'active')
            ");
            $stmt->execute([$data['academic_year_id']]);
            $academicYear = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$academicYear) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Academic year not found or not active');
            }

            // Verify class stream exists
            $stmt = $this->db->prepare("
                SELECT cs.*, c.name as class_name
                FROM class_streams cs
                JOIN classes c ON cs.class_id = c.id
                WHERE cs.id = ?
            ");
            $stmt->execute([$data['class_stream_id']]);
            $classStream = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$classStream) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Class stream not found');
            }

            // Validate assignment using stored procedure
            $stmt = $this->db->prepare("CALL sp_validate_staff_assignment(?, ?, ?, ?, @is_valid, @error_message)");
            $stmt->execute([
                $data['staff_id'],
                $data['class_stream_id'],
                $data['academic_year_id'],
                $data['role']
            ]);
            $stmt->closeCursor();

            $result = $this->db->query("SELECT @is_valid AS is_valid, @error_message AS error_message")->fetch(PDO::FETCH_ASSOC);

            if (!$result['is_valid']) {
                $this->db->rollBack();
                return formatResponse(false, null, $result['error_message'] ?? 'Assignment validation failed');
            }

            // Create assignment
            $sql = "INSERT INTO staff_class_assignments (
                staff_id, academic_year_id, class_stream_id, subject_id, role,
                assigned_by, assignment_date, status, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";

            $currentUserId = $this->getCurrentUserId();
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['academic_year_id'],
                $data['class_stream_id'],
                $data['subject_id'] ?? null,
                $data['role'],
                $currentUserId,
                $data['assignment_date'] ?? date('Y-m-d'),
                $data['remarks'] ?? null
            ]);

            $assignmentId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction(
                'create',
                $assignmentId,
                "Assigned {$staff['first_name']} {$staff['last_name']} ({$staff['staff_type']}) as {$data['role']} to {$classStream['class_name']} - {$classStream['stream_name']}"
            );

            return formatResponse(true, [
                'assignment_id' => $assignmentId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'staff_type' => $staff['staff_type'],
                'category' => $staff['category_name'],
                'department' => $staff['department_name'],
                'class' => $classStream['class_name'] . ' - ' . $classStream['stream_name'],
                'role' => $data['role'],
                'academic_year' => $academicYear['year_name']
            ], 'Staff assigned to class successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Remove staff assignment
     * @param int $assignmentId Assignment ID
     * @param array $data Removal data (reason, removed_by)
     * @return array Response
     */
    public function removeAssignment($assignmentId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            // Get assignment details
            $stmt = $this->db->prepare("
                SELECT sca.*, s.first_name, s.last_name, s.staff_no,
                       cs.stream_name, c.name as class_name, ay.year_name
                FROM staff_class_assignments sca
                JOIN staff s ON sca.staff_id = s.id
                JOIN class_streams cs ON sca.class_stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN academic_years ay ON sca.academic_year_id = ay.id
                WHERE sca.id = ?
            ");
            $stmt->execute([$assignmentId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Assignment not found');
            }

            if ($assignment['status'] === 'completed') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Cannot remove completed assignment');
            }

            // Update assignment status
            $sql = "UPDATE staff_class_assignments 
                   SET status = 'cancelled', 
                       removed_date = ?,
                       removal_reason = ?
                   WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                date('Y-m-d'),
                $data['removal_reason'] ?? 'Assignment cancelled',
                $assignmentId
            ]);

            $this->db->commit();
            $this->logAction(
                'update',
                $assignmentId,
                "Removed assignment: {$assignment['first_name']} {$assignment['last_name']} from {$assignment['class_name']} - {$assignment['stream_name']}"
            );

            return formatResponse(true, [
                'assignment_id' => $assignmentId,
                'staff_name' => $assignment['first_name'] . ' ' . $assignment['last_name'],
                'class' => $assignment['class_name'] . ' - ' . $assignment['stream_name']
            ], 'Staff assignment removed successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get staff assignments with filters
     * Uses vw_staff_assignments_detailed view for detailed assignment data
     * @param array $filters Filter criteria
     * @return array Response
     */
    public function getStaffAssignments($filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_staff_assignments_detailed WHERE 1=1";
            $params = [];

            if (!empty($filters['staff_id'])) {
                $sql .= " AND staff_id = ?";
                $params[] = $filters['staff_id'];
            }

            if (!empty($filters['academic_year_id'])) {
                $sql .= " AND academic_year_id = ?";
                $params[] = $filters['academic_year_id'];
            }

            if (!empty($filters['class_stream_id'])) {
                $sql .= " AND class_stream_id = ?";
                $params[] = $filters['class_stream_id'];
            }

            if (!empty($filters['department_id'])) {
                $sql .= " AND department_id = ?";
                $params[] = $filters['department_id'];
            }

            if (!empty($filters['staff_type_id'])) {
                $sql .= " AND staff_type_id = ?";
                $params[] = $filters['staff_type_id'];
            }

            if (!empty($filters['role'])) {
                $sql .= " AND role = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            } else {
                $sql .= " AND status = 'active'";
            }

            $sql .= " ORDER BY academic_year DESC, class_name, stream_name, staff_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'assignments' => $assignments,
                'count' => count($assignments)
            ], 'Staff assignments retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get staffing for a specific class
     * @param int $classStreamId Class stream ID
     * @param int $academicYearId Academic year ID
     * @return array Response
     */
    public function getClassStaffing($classStreamId, $academicYearId)
    {
        try {
            $sql = "SELECT sca.*, 
                       s.staff_no, s.first_name, s.last_name, s.position, s.phone,
                       st.name as staff_type, sc.category_name,
                       d.name as department_name,
                       sub.name as subject_name
                FROM staff_class_assignments sca
                JOIN staff s ON sca.staff_id = s.id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN subjects sub ON sca.subject_id = sub.id
                WHERE sca.class_stream_id = ? AND sca.academic_year_id = ? 
                AND sca.status = 'active'
                ORDER BY 
                    CASE sca.role
                        WHEN 'class_teacher' THEN 1
                        WHEN 'head_of_department' THEN 2
                        WHEN 'subject_teacher' THEN 3
                        WHEN 'assistant_teacher' THEN 4
                    END,
                    s.last_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$classStreamId, $academicYearId]);
            $staffing = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get class details
            $stmt = $this->db->prepare("
                SELECT cs.*, c.name as class_name, ay.year_name
                FROM class_streams cs
                JOIN classes c ON cs.class_id = c.id
                JOIN academic_years ay ON ay.id = ?
                WHERE cs.id = ?
            ");
            $stmt->execute([$academicYearId, $classStreamId]);
            $classInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'class_info' => $classInfo,
                'staffing' => $staffing,
                'staff_count' => count($staffing)
            ], 'Class staffing retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Transfer staff assignment to another class
     * @param int $assignmentId Current assignment ID
     * @param array $data New assignment data
     * @return array Response
     */
    public function transferAssignment($assignmentId, $data)
    {
        try {
            $required = ['new_class_stream_id', 'transfer_reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Get current assignment
            $stmt = $this->db->prepare("
                SELECT sca.*, s.first_name, s.last_name
                FROM staff_class_assignments sca
                JOIN staff s ON sca.staff_id = s.id
                WHERE sca.id = ? AND sca.status = 'active'
            ");
            $stmt->execute([$assignmentId]);
            $currentAssignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$currentAssignment) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active assignment not found');
            }

            // Complete old assignment
            $sql = "UPDATE staff_class_assignments 
                   SET status = 'transferred', 
                       removed_date = ?,
                       removal_reason = ?
                   WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                date('Y-m-d'),
                $data['transfer_reason'],
                $assignmentId
            ]);

            // Create new assignment
            $newAssignmentData = [
                'staff_id' => $currentAssignment['staff_id'],
                'academic_year_id' => $currentAssignment['academic_year_id'],
                'class_stream_id' => $data['new_class_stream_id'],
                'subject_id' => $currentAssignment['subject_id'],
                'role' => $currentAssignment['role'],
                'assignment_date' => date('Y-m-d'),
                'remarks' => "Transferred from previous assignment. Reason: {$data['transfer_reason']}"
            ];

            $sql = "INSERT INTO staff_class_assignments (
                staff_id, academic_year_id, class_stream_id, subject_id, role,
                assigned_by, assignment_date, status, remarks
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?)";

            $currentUserId = $this->getCurrentUserId();
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $newAssignmentData['staff_id'],
                $newAssignmentData['academic_year_id'],
                $newAssignmentData['class_stream_id'],
                $newAssignmentData['subject_id'],
                $newAssignmentData['role'],
                $currentUserId,
                $newAssignmentData['assignment_date'],
                $newAssignmentData['remarks']
            ]);

            $newAssignmentId = $this->db->lastInsertId();

            $this->db->commit();
            $this->logAction(
                'update',
                $assignmentId,
                "Transferred {$currentAssignment['first_name']} {$currentAssignment['last_name']} to new class"
            );

            return formatResponse(true, [
                'old_assignment_id' => $assignmentId,
                'new_assignment_id' => $newAssignmentId,
                'staff_name' => $currentAssignment['first_name'] . ' ' . $currentAssignment['last_name']
            ], 'Staff assignment transferred successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get staff workload summary
     * Uses vw_staff_workload view for automated workload calculation
     * @param int $staffId Staff ID
     * @param int $academicYearId Academic year ID
     * @return array Response
     */
    public function getStaffWorkload($staffId, $academicYearId = null)
    {
        try {
            // Note: vw_staff_workload uses academic_year (text) not academic_year_id
            // Get workload for staff - view already filters to active academic year
            $stmt = $this->db->prepare("
                SELECT * FROM vw_staff_workload 
                WHERE staff_id = ?
            ");
            $stmt->execute([$staffId]);
            $workload = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workload) {
                return formatResponse(false, null, 'Staff workload not found');
            }

            return formatResponse(true, $workload, 'Staff workload retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get current assignments for active staff
     * Uses vw_current_staff_assignments view for current academic year assignments
     * @param array $filters Optional filters (staff_id, department_id, class_stream_id, role)
     * @return array Response
     */
    public function getCurrentAssignments($filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_current_staff_assignments WHERE 1=1";
            $params = [];

            if (!empty($filters['staff_id'])) {
                $sql .= " AND staff_id = ?";
                $params[] = $filters['staff_id'];
            }

            if (!empty($filters['department_id'])) {
                $sql .= " AND department_id = ?";
                $params[] = $filters['department_id'];
            }

            if (!empty($filters['class_stream_id'])) {
                $sql .= " AND class_stream_id = ?";
                $params[] = $filters['class_stream_id'];
            }

            if (!empty($filters['role'])) {
                $sql .= " AND role = ?";
                $params[] = $filters['role'];
            }

            $sql .= " ORDER BY class_name, stream_name, staff_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'assignments' => $assignments,
                'count' => count($assignments)
            ], 'Current assignments retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update workload allocation
     * @param int $staffId Staff ID
     * @param array $data Workload data
     * @return array Response
     */
    public function updateWorkload($staffId, $data)
    {
        try {
            // This is a placeholder for workload calculation logic
            // In a full implementation, this would calculate teaching hours,
            // student count, and other workload metrics

            $workloadData = $this->getStaffWorkload($staffId, $data['academic_year_id']);

            if (!$workloadData['status']) {
                return $workloadData;
            }

            return formatResponse(true, $workloadData['data'], 'Workload calculated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
