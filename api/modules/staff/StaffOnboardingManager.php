<?php
namespace App\API\Modules\Staff;

use App\Config;
use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Staff Onboarding Manager
 * 
 * Handles CRUD operations for staff onboarding process
 * - Creates onboarding records for new staff
 * - Manages onboarding tasks and checklists
 * - Tracks onboarding progress
 * - Handles task assignments and completions
 * - Respects staff types, categories, and departments
 */
class StaffOnboardingManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Create onboarding record for new staff
     * @param array $data Onboarding data
     * @return array Response
     */
    public function createOnboarding($data)
    {
        try {
            $required = ['staff_id', 'onboarding_start_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->beginTransaction();

            // Verify staff exists
            $stmt = $this->db->prepare("
                SELECT s.*, st.name as staff_type, sc.category_name, d.name as department_name
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['staff_id']]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->rollback();
                return formatResponse(false, null, 'Staff member not found');
            }

            // Check if onboarding already exists
            $stmt = $this->db->prepare("
                SELECT * FROM staff_onboarding 
                WHERE staff_id = ? AND status NOT IN ('completed', 'cancelled')
            ");
            $stmt->execute([$data['staff_id']]);
            if ($stmt->fetch()) {
                $this->rollback();
                return formatResponse(false, null, 'Active onboarding already exists for this staff member');
            }

            // Create onboarding record
            $sql = "INSERT INTO staff_onboarding (
                staff_id, onboarding_start_date, expected_end_date, 
                mentor_id, status, remarks
            ) VALUES (?, ?, ?, ?, 'in_progress', ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['onboarding_start_date'],
                $data['expected_end_date'] ?? date('Y-m-d', strtotime('+30 days')),
                $data['mentor_id'] ?? null,
                $data['remarks'] ?? "Onboarding for {$staff['first_name']} {$staff['last_name']}"
            ]);

            $onboardingId = $this->db->lastInsertId();

            // Call stored procedure to auto-generate onboarding tasks
            $stmt = $this->db->prepare("CALL sp_auto_generate_onboarding_tasks(?)");
            $stmt->execute([$onboardingId]);

            $this->commit();
            $this->logAction(
                'create',
                $onboardingId,
                "Created onboarding for {$staff['first_name']} {$staff['last_name']} ({$staff['staff_type']})"
            );

            return formatResponse(true, [
                'onboarding_id' => $onboardingId,
                'staff_name' => $staff['first_name'] . ' ' . $staff['last_name'],
                'staff_type' => $staff['staff_type'],
                'department' => $staff['department_name'],
                'status' => 'in_progress'
            ], 'Onboarding created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Update onboarding record
     * @param int $onboardingId Onboarding ID
     * @param array $data Update data
     * @return array Response
     */
    public function updateOnboarding($onboardingId, $data)
    {
        try {
            $this->beginTransaction();

            // Verify onboarding exists
            $stmt = $this->db->prepare("
                SELECT so.*, s.first_name, s.last_name
                FROM staff_onboarding so
                JOIN staff s ON so.staff_id = s.id
                WHERE so.id = ?
            ");
            $stmt->execute([$onboardingId]);
            $onboarding = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$onboarding) {
                $this->rollback();
                return formatResponse(false, null, 'Onboarding record not found');
            }

            $updates = [];
            $params = [];

            if (isset($data['expected_end_date'])) {
                $updates[] = "expected_end_date = ?";
                $params[] = $data['expected_end_date'];
            }

            if (isset($data['mentor_id'])) {
                $updates[] = "mentor_id = ?";
                $params[] = $data['mentor_id'];
            }

            if (isset($data['status'])) {
                $validStatuses = ['in_progress', 'completed', 'on_hold', 'cancelled'];
                if (!in_array($data['status'], $validStatuses)) {
                    $this->rollback();
                    return formatResponse(false, null, 'Invalid status');
                }
                $updates[] = "status = ?";
                $params[] = $data['status'];

                if ($data['status'] === 'completed') {
                    $updates[] = "completion_date = NOW()";
                }
            }

            if (isset($data['remarks'])) {
                $updates[] = "remarks = ?";
                $params[] = $data['remarks'];
            }

            if (empty($updates)) {
                $this->rollback();
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $onboardingId;
            $sql = "UPDATE staff_onboarding SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->commit();
            $this->logAction(
                'update',
                $onboardingId,
                "Updated onboarding for {$onboarding['first_name']} {$onboarding['last_name']}"
            );

            return formatResponse(true, [
                'onboarding_id' => $onboardingId,
                'staff_name' => $onboarding['first_name'] . ' ' . $onboarding['last_name']
            ], 'Onboarding updated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get onboarding tasks
     * @param int $onboardingId Onboarding ID
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getTasks($onboardingId, $filters = [])
    {
        try {
            $sql = "SELECT ot.*,
                       CONCAT(assigned.first_name, ' ', assigned.last_name) as assigned_to_name,
                       CONCAT(completed.first_name, ' ', completed.last_name) as completed_by_name
                FROM onboarding_tasks ot
                LEFT JOIN users assigned ON ot.assigned_to = assigned.id
                LEFT JOIN users completed ON ot.completed_by = completed.id
                WHERE ot.onboarding_id = ?";

            $params = [$onboardingId];

            if (!empty($filters['status'])) {
                $sql .= " AND ot.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['category'])) {
                $sql .= " AND ot.category = ?";
                $params[] = $filters['category'];
            }

            if (!empty($filters['priority'])) {
                $sql .= " AND ot.priority = ?";
                $params[] = $filters['priority'];
            }

            $sql .= " ORDER BY ot.sequence, ot.due_date";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate progress
            $totalTasks = count($tasks);
            $completedTasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
            $progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

            return formatResponse(true, [
                'tasks' => $tasks,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'progress_percent' => $progressPercent
            ], 'Onboarding tasks retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update task status
     * @param int $taskId Task ID
     * @param array $data Update data
     * @return array Response
     */
    public function updateTaskStatus($taskId, $data)
    {
        try {
            $required = ['status'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $validStatuses = ['pending', 'in_progress', 'completed', 'skipped'];
            if (!in_array($data['status'], $validStatuses)) {
                return formatResponse(false, null, 'Invalid status. Must be: ' . implode(', ', $validStatuses));
            }

            $this->beginTransaction();

            $updates = ["status = ?"];
            $params = [$data['status']];

            if ($data['status'] === 'completed') {
                $updates[] = "completed_by = ?";
                $updates[] = "completion_date = NOW()";
                $params[] = $this->getCurrentUserId();
            }

            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
            }

            $params[] = $taskId;
            $sql = "UPDATE onboarding_tasks SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->commit();
            $this->logAction('update', $taskId, "Updated onboarding task status to: {$data['status']}");

            return formatResponse(true, [
                'task_id' => $taskId,
                'status' => $data['status']
            ], 'Task status updated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get onboarding progress
     * Uses vw_staff_onboarding_progress view (auto-calculated by trigger)
     * @param int $onboardingId Onboarding ID
     * @return array Response
     */
    public function getOnboardingProgress($onboardingId)
    {
        try {
            // Use view for optimized progress calculation
            $stmt = $this->db->prepare("
                SELECT * FROM vw_staff_onboarding_progress
                WHERE onboarding_id = ?
            ");
            $stmt->execute([$onboardingId]);
            $progress = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$progress) {
                return formatResponse(false, null, 'Onboarding record not found');
            }

            // Get tasks by category
            $stmt = $this->db->prepare("
                SELECT 
                    category,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                FROM onboarding_tasks
                WHERE onboarding_id = ?
                GROUP BY category
            ");
            $stmt->execute([$onboardingId]);
            $categoryProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'progress' => $progress,
                'category_progress' => $categoryProgress
            ], 'Onboarding progress retrieved successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Complete onboarding
     * @param int $onboardingId Onboarding ID
     * @param array $data Completion data
     * @return array Response
     */
    public function completeOnboarding($onboardingId, $data = [])
    {
        try {
            $this->beginTransaction();

            // Check all tasks are completed or skipped
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM onboarding_tasks
                WHERE onboarding_id = ? AND status NOT IN ('completed', 'skipped')
            ");
            $stmt->execute([$onboardingId]);
            $incompleteTasks = $stmt->fetchColumn();

            if ($incompleteTasks > 0 && empty($data['force_complete'])) {
                $this->rollback();
                return formatResponse(
                    false,
                    null,
                    "Cannot complete onboarding. {$incompleteTasks} task(s) still pending. Use 'force_complete' to override."
                );
            }

            // Update onboarding status
            $sql = "UPDATE staff_onboarding 
                   SET status = 'completed', 
                       completion_date = NOW(),
                       remarks = CONCAT(COALESCE(remarks, ''), ' | Completed on ', NOW())
                   WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$onboardingId]);

            $this->commit();
            $this->logAction('update', $onboardingId, "Completed onboarding");

            return formatResponse(true, [
                'onboarding_id' => $onboardingId,
                'status' => 'completed'
            ], 'Onboarding completed successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }
}
