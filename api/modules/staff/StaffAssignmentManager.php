<?php
namespace App\API\Modules\staff;

use App\Config;
use App\API\Includes\BaseAPI;
use App\API\Services\StaffTeachingAssignmentService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Assignment Manager — 3NF/4NF schema.
 *
 * Teaching assignments now live in the normalized context tables:
 *   - class_teacher   -> academic_year_class_streams.class_teacher_id
 *   - subject_teacher -> academic_year_class_learning_area_teachers
 * The denormalized legacy `staff_class_assignments` table is gone, so all write paths
 * delegate to StaffTeachingAssignmentService (single normalized writer) and reads use the
 * shipped views (vw_staff_assignments_detailed / vw_current_staff_assignments / vw_staff_workload),
 * which already flatten the new tables back to the legacy column shape callers expect.
 */
class StaffAssignmentManager extends BaseAPI
{
    /** @var StaffTeachingAssignmentService */
    private $teaching;

    public function __construct()
    {
        parent::__construct();
        $this->teaching = new StaffTeachingAssignmentService();
    }

    /**
     * Assign staff to a class for an academic year.
     * Routes to the normalized writer based on role: class_teacher writes the stream column,
     * every other teaching role writes an academic_year_class_learning_area_teachers row.
     */
    public function assignStaffToClass($data)
    {
        try {
            $required = ['staff_id', 'academic_year_id', 'class_id', 'role'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $validRoles = ['class_teacher', 'subject_teacher', 'assistant', 'hod'];
            if (!in_array($data['role'], $validRoles, true)) {
                return formatResponse(false, null, 'Invalid role. Must be: ' . implode(', ', $validRoles));
            }

            $userId = (int)$this->getCurrentUserId();
            if ($data['role'] === 'class_teacher') {
                $id = $this->teaching->saveClassTeacher($data, null, $userId);
                $this->logAction('create', $id, "Assigned class teacher (staff #{$data['staff_id']})");
                return formatResponse(true, ['assignment_id' => $id, 'role' => 'class_teacher'], 'Class teacher assigned successfully');
            }

            $id = $this->teaching->saveSubjectAssignment($data, null, $userId);
            $this->logAction('create', $id, "Assigned {$data['role']} (staff #{$data['staff_id']})");
            return formatResponse(true, ['assignment_id' => $id, 'role' => $data['role']], 'Staff assigned to class successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Remove a teaching assignment. Class-teacher ids come from academic_year_class_streams,
     * subject ids from academic_year_class_learning_area_teachers, so the caller states which.
     */
    public function removeAssignment($assignmentId, $data = [])
    {
        try {
            $role = $data['role'] ?? 'subject_teacher';
            if ($role === 'class_teacher') {
                $this->teaching->removeClassTeacher((int)$assignmentId);
            } else {
                $this->teaching->removeSubjectAssignment((int)$assignmentId);
            }
            $this->logAction('update', $assignmentId, "Removed {$role} assignment #{$assignmentId}");
            return formatResponse(true, ['assignment_id' => $assignmentId], 'Staff assignment removed successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get staff assignments with filters — reads the detailed view (already flattens the new tables).
     */
    public function getStaffAssignments($filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_staff_assignments_detailed WHERE 1=1";
            $params = [];

            foreach (['staff_id', 'academic_year_id', 'class_stream_id', 'class_id', 'role'] as $col) {
                if (!empty($filters[$col])) { $sql .= " AND $col = ?"; $params[] = $filters[$col]; }
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
     * Get staffing for a specific class-in-year (academic_year_class) — every teaching role on it.
     * $academicYearClassId is the academic_year_classes id (the year-scoped class context).
     */
    public function getClassStaffing($academicYearClassId, $academicYearId)
    {
        try {
            $sql = "SELECT t.id, t.staff_id, t.role,
                       s.staff_no, p.first_name, p.last_name, s.position, p.phone,
                       stt.name AS staff_type, sc.category_name,
                       la.name AS subject_name,
                       c.name AS class_name, str.name AS stream_name
                FROM academic_year_class_learning_area_teachers t
                JOIN academic_year_class_learning_areas aycla ON aycla.id = t.academic_year_class_learning_area_id
                JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
                LEFT JOIN streams str ON str.id = aycs.stream_id
                JOIN staff s ON s.id = t.staff_id
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_types stt ON stt.id = s.staff_type_id
                LEFT JOIN staff_categories sc ON sc.id = s.staff_category_id
                LEFT JOIN learning_areas la ON la.id = aycla.learning_area_id
                WHERE ayc.id = ?
                ORDER BY
                    CASE t.role
                        WHEN 'hod' THEN 1
                        WHEN 'subject_teacher' THEN 2
                        WHEN 'assistant' THEN 3
                        ELSE 4
                    END,
                    p.last_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$academicYearClassId]);
            $staffing = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("
                SELECT ayc.id AS academic_year_class_id, c.name AS class_name, ay.year_name
                FROM academic_year_classes ayc
                JOIN classes c ON c.id = ayc.class_id
                JOIN academic_years ay ON ay.id = ayc.academic_year_id
                WHERE ayc.id = ?
            ");
            $stmt->execute([$academicYearClassId]);
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
     * Transfer a subject-teacher assignment to another class/subject.
     * History is preserved by appending a new teacher row and removing the old one
     * (the target table has no soft-delete column; the append-only fact is the new row).
     */
    public function transferAssignment($assignmentId, $data)
    {
        try {
            $required = ['class_id', 'subject_id', 'transfer_reason'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $current = $this->teaching->getSubjectAssignment((int)$assignmentId);
            if (!$current) {
                return formatResponse(false, null, 'Active assignment not found');
            }

            $userId = (int)$this->getCurrentUserId();
            $newId = $this->teaching->saveSubjectAssignment([
                'teacher_id'       => $current['teacher_id'],
                'subject_id'       => $data['subject_id'],
                'class_id'         => $data['class_id'],
                'academic_year_id' => $data['academic_year_id'] ?? $current['academic_year_id'],
                'role'             => $data['role'] ?? 'subject_teacher',
            ], null, $userId);

            $this->teaching->removeSubjectAssignment((int)$assignmentId);

            $this->logAction('update', $assignmentId, "Transferred assignment (staff #{$current['teacher_id']}). Reason: {$data['transfer_reason']}");

            return formatResponse(true, [
                'old_assignment_id' => $assignmentId,
                'new_assignment_id' => $newId,
                'staff_id' => $current['teacher_id']
            ], 'Staff assignment transferred successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get staff workload summary — reads vw_staff_workload (built on the normalized teacher tables).
     */
    public function getStaffWorkload($staffId, $academicYearId = null)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('staff_workload') . " WHERE staff_id = ?");
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
     * Get current-year assignments for active staff — reads vw_current_staff_assignments.
     */
    public function getCurrentAssignments($filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_current_staff_assignments WHERE 1=1";
            $params = [];

            foreach (['staff_id', 'class_stream_id', 'class_id', 'role'] as $col) {
                if (!empty($filters[$col])) { $sql .= " AND $col = ?"; $params[] = $filters[$col]; }
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
     * Update workload allocation (derived — recomputed from the normalized teacher tables via the view).
     */
    public function updateWorkload($staffId, $data)
    {
        try {
            $workloadData = $this->getStaffWorkload($staffId, $data['academic_year_id'] ?? null);
            if (!$workloadData['status']) {
                return $workloadData;
            }
            return formatResponse(true, $workloadData['data'], 'Workload calculated successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
