<?php
namespace App\API\Modules\staff;

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
            $required = ['staff_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

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
                $this->db->rollBack();
                return formatResponse(false, null, 'Staff member not found');
            }

            // Check if onboarding already exists
            $stmt = $this->db->prepare("
                SELECT * FROM staff_onboarding 
                WHERE staff_id = ? AND status NOT IN ('completed', 'cancelled')
            ");
            $stmt->execute([$data['staff_id']]);
            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active onboarding already exists for this staff member');
            }

            $startDate = $data['start_date'] ?? $data['onboarding_start_date'] ?? date('Y-m-d');
            $probationMonths = (int)($data['probation_months'] ?? 3);
            $targetCompletion = $data['target_completion']
                ?? $data['expected_end_date']
                ?? date('Y-m-d', strtotime($startDate . " +{$probationMonths} months"));

            // Create onboarding record using the current staff_onboarding schema.
            $sql = "INSERT INTO staff_onboarding (
                staff_id, mentor_id, contract_type, probation_months, start_date,
                target_completion, expected_end_date, status, progress_percent,
                initiated_by, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, ?, ?)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['staff_id'],
                $data['mentor_id'] ?? null,
                $data['contract_type'] ?? 'probation',
                $probationMonths,
                $startDate,
                $targetCompletion,
                $data['expected_end_date'] ?? $targetCompletion,
                $data['initiated_by'] ?? $this->getCurrentUserId(),
                $data['notes'] ?? $data['remarks'] ?? "Onboarding for {$staff['first_name']} {$staff['last_name']}"
            ]);

            $onboardingId = $this->db->lastInsertId();

            // Call stored procedure to auto-generate onboarding tasks
            $stmt = $this->db->prepare("CALL sp_auto_generate_onboarding_tasks(?)");
            $stmt->execute([$onboardingId]);
            $stmt->closeCursor();

            $stmt = $this->db->prepare("UPDATE staff_onboarding SET status = 'in_progress' WHERE id = ?");
            $stmt->execute([$onboardingId]);

            $stmt = $this->db->prepare("
                INSERT INTO staff_contracts (staff_id, contract_type, start_date, end_date, salary, status, created_by)
                VALUES (?, ?, ?, ?, ?, 'active', ?)
            ");
            $stmt->execute([
                $data['staff_id'],
                $data['contract_type'] ?? 'probation',
                $startDate,
                $targetCompletion,
                $staff['salary'] ?? 0,
                $data['initiated_by'] ?? $this->getCurrentUserId(),
            ]);

            $this->db->commit();
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
                'status' => 'in_progress',
                'start_date' => $startDate,
                'target_completion' => $targetCompletion
            ], 'Onboarding created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
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
            $this->db->beginTransaction();

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
                $this->db->rollBack();
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
                $validStatuses = ['pending', 'in_progress', 'completed', 'extended', 'terminated'];
                if (!in_array($data['status'], $validStatuses)) {
                    $this->db->rollBack();
                    return formatResponse(false, null, 'Invalid status');
                }
                $updates[] = "status = ?";
                $params[] = $data['status'];

                if ($data['status'] === 'completed') {
                    $updates[] = "completion_date = NOW()";
                    $updates[] = "actual_completion = CURDATE()";
                    $updates[] = "progress_percent = 100";
                }
            }

            if (isset($data['remarks'])) {
                $updates[] = "notes = ?";
                $params[] = $data['remarks'];
            }
            if (isset($data['notes'])) {
                $updates[] = "notes = ?";
                $params[] = $data['notes'];
            }
            if (isset($data['target_completion'])) {
                $updates[] = "target_completion = ?";
                $params[] = $data['target_completion'];
            }
            if (isset($data['probation_outcome'])) {
                $updates[] = "probation_outcome = ?";
                $params[] = $data['probation_outcome'];
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $onboardingId;
            $sql = "UPDATE staff_onboarding SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();
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
                $this->db->rollBack();
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

    public function listOnboardings(array $filters = []): array
    {
        try {
            $where = ['1=1'];
            $params = [];

            if (!empty($filters['status'])) {
                $where[] = 'status = ?';
                $params[] = $filters['status'];
            }
            if (!empty($filters['staff_id'])) {
                $where[] = 'staff_id = ?';
                $params[] = (int)$filters['staff_id'];
            }
            if (!empty($filters['department_id'])) {
                $where[] = 'staff_id IN (SELECT id FROM staff WHERE department_id = ?)';
                $params[] = (int)$filters['department_id'];
            }

            $stmt = $this->db->prepare(
                "SELECT * FROM vw_onboarding_dashboard
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY start_date DESC
                 LIMIT 200"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'onboardings' => $rows,
                'stats' => [
                    'total' => count($rows),
                    'in_progress' => count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'in_progress')),
                    'completed' => count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'completed')),
                    'overdue' => count(array_filter($rows, fn($r) => (int)($r['overdue_tasks'] ?? 0) > 0)),
                    'pending' => count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'pending')),
                ],
            ], 'Onboardings retrieved successfully');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getOnboardingDetail(int $onboardingId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM vw_onboarding_dashboard WHERE onboarding_id = ?");
            $stmt->execute([$onboardingId]);
            $onboarding = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$onboarding) {
                return formatResponse(false, null, 'Onboarding record not found');
            }

            $tasks = $this->getTasks($onboardingId);
            $taskData = $tasks['data'] ?? [];

            $stmt = $this->db->prepare("SELECT * FROM onboarding_documents WHERE onboarding_id = ?");
            $stmt->execute([$onboardingId]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("
                SELECT pr.*, CONCAT(r.first_name, ' ', r.last_name) AS reviewer_name
                FROM staff_probation_reviews pr
                LEFT JOIN staff r ON r.id = pr.reviewer_id
                WHERE pr.onboarding_id = ?
                ORDER BY pr.review_month ASC
            ");
            $stmt->execute([$onboardingId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'onboarding' => $onboarding,
                'tasks' => $taskData['tasks'] ?? [],
                'documents' => $documents,
                'reviews' => $reviews,
            ], 'Onboarding retrieved successfully');
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

            $this->db->beginTransaction();

            $updates = ["status = ?"];
            $params = [$data['status']];

            if ($data['status'] === 'completed') {
                $updates[] = "completed_by = ?";
                $updates[] = "completed_date = NOW()";
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

            $this->db->commit();
            $this->logAction('update', $taskId, "Updated onboarding task status to: {$data['status']}");

            return formatResponse(true, [
                'task_id' => $taskId,
                'status' => $data['status']
            ], 'Task status updated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
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
            $this->db->beginTransaction();

            // Check all tasks are completed or skipped
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM onboarding_tasks
                WHERE onboarding_id = ? AND status NOT IN ('completed', 'skipped')
            ");
            $stmt->execute([$onboardingId]);
            $incompleteTasks = $stmt->fetchColumn();

            if ($incompleteTasks > 0 && empty($data['force_complete'])) {
                $this->db->rollBack();
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
                       actual_completion = CURDATE(),
                       progress_percent = 100,
                       notes = CONCAT(COALESCE(notes, ''), ' | Completed on ', NOW())
                   WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$onboardingId]);

            $this->db->commit();
            $this->logAction('update', $onboardingId, "Completed onboarding");

            return formatResponse(true, [
                'onboarding_id' => $onboardingId,
                'status' => 'completed'
            ], 'Onboarding completed successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function recordDocument(array $data): array
    {
        $missing = $this->validateRequired($data, ['onboarding_id', 'staff_id', 'document_type']);
        if (!empty($missing)) {
            return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                INSERT INTO onboarding_documents
                    (onboarding_id, staff_id, document_type, document_name,
                     is_original_seen, is_copy_filed, verified_by, verified_at, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                (int)$data['onboarding_id'],
                (int)$data['staff_id'],
                $data['document_type'],
                $data['document_name'] ?? null,
                (int)($data['is_original_seen'] ?? 0),
                (int)($data['is_copy_filed'] ?? 0),
                $data['verified_by'] ?? $this->getCurrentUserId(),
                $data['notes'] ?? null,
            ]);
            $documentId = (int)$this->db->lastInsertId();

            $stmt = $this->db->prepare("
                UPDATE onboarding_tasks
                   SET status = 'completed', completed_by = COALESCE(completed_by, ?), completed_date = NOW()
                 WHERE onboarding_id = ?
                   AND category = 'documentation'
                   AND LOWER(task_name) LIKE ?
                   AND status != 'completed'
                 LIMIT 1
            ");
            $stmt->execute([
                $data['verified_by'] ?? $this->getCurrentUserId(),
                (int)$data['onboarding_id'],
                '%' . strtolower(str_replace('_', ' ', $data['document_type'])) . '%',
            ]);

            $this->recalculateProgress((int)$data['onboarding_id']);
            $this->db->commit();

            return formatResponse(true, ['id' => $documentId], 'Onboarding document recorded');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function recordProbationReview(array $data): array
    {
        $missing = $this->validateRequired($data, ['onboarding_id', 'staff_id']);
        if (!empty($missing)) {
            return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare("
                INSERT INTO staff_probation_reviews
                    (onboarding_id, staff_id, review_month, review_date, reviewer_id,
                     overall_rating, attendance_score, performance_score, conduct_score,
                     strengths, areas_to_improve, outcome, outcome_notes, next_review_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                (int)$data['onboarding_id'],
                (int)$data['staff_id'],
                $data['review_month'] ?? 1,
                $data['review_date'] ?? date('Y-m-d'),
                $data['reviewer_id'] ?? $this->getCurrentUserId(),
                $data['overall_rating'] ?? 'satisfactory',
                $data['attendance_score'] ?? null,
                $data['performance_score'] ?? null,
                $data['conduct_score'] ?? null,
                $data['strengths'] ?? null,
                $data['areas_to_improve'] ?? null,
                $data['outcome'] ?? 'continue',
                $data['outcome_notes'] ?? null,
                $data['next_review_date'] ?? null,
            ]);
            $reviewId = (int)$this->db->lastInsertId();

            $outcome = $data['outcome'] ?? 'continue';
            if ($outcome === 'confirm_permanent') {
                $stmt = $this->db->prepare("
                    UPDATE staff_onboarding
                       SET probation_outcome = 'confirmed',
                           status = 'completed',
                           actual_completion = ?,
                           progress_percent = 100
                     WHERE id = ?
                ");
                $stmt->execute([date('Y-m-d'), (int)$data['onboarding_id']]);

                $stmt = $this->db->prepare("
                    UPDATE staff_contracts
                       SET contract_type = 'permanent', status = 'active', end_date = NULL
                     WHERE staff_id = ? AND status = 'active'
                ");
                $stmt->execute([(int)$data['staff_id']]);
            } elseif ($outcome === 'extend_probation') {
                $extendMonths = max(1, (int)($data['extend_months'] ?? 3));
                $newTarget = date('Y-m-d', strtotime(date('Y-m-d') . " +{$extendMonths} months"));
                $stmt = $this->db->prepare("
                    UPDATE staff_onboarding
                       SET probation_outcome = 'extended',
                           target_completion = ?,
                           expected_end_date = ?
                     WHERE id = ?
                ");
                $stmt->execute([$newTarget, $newTarget, (int)$data['onboarding_id']]);
            } elseif ($outcome === 'terminate') {
                $stmt = $this->db->prepare("
                    UPDATE staff_onboarding
                       SET probation_outcome = 'terminated', status = 'terminated'
                     WHERE id = ?
                ");
                $stmt->execute([(int)$data['onboarding_id']]);
                $stmt = $this->db->prepare("UPDATE staff SET status = 'inactive' WHERE id = ?");
                $stmt->execute([(int)$data['staff_id']]);
            }

            $this->db->commit();
            return formatResponse(true, ['id' => $reviewId], 'Probation review recorded');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    public function getActiveTemplates(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT *
                  FROM onboarding_task_templates
                 WHERE status = 'active'
                 ORDER BY display_order
            ");
            return formatResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Onboarding templates retrieved');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getPendingTasks(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT *
                  FROM vw_onboarding_pending_by_role
                 ORDER BY is_overdue DESC, due_date ASC
                 LIMIT 100
            ");
            return formatResponse(true, $stmt->fetchAll(PDO::FETCH_ASSOC), 'Pending onboarding tasks retrieved');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function recalculateProgress(int $onboardingId): void
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total,
                   SUM(status = 'completed') AS done,
                   SUM(status = 'skipped') AS skipped
              FROM onboarding_tasks
             WHERE onboarding_id = ?
        ");
        $stmt->execute([$onboardingId]);
        $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'done' => 0, 'skipped' => 0];

        $active = (int)$counts['total'] - (int)$counts['skipped'];
        $pct = $active > 0 ? round((int)$counts['done'] * 100 / $active) : 0;

        $stmt = $this->db->prepare("UPDATE staff_onboarding SET progress_percent = ? WHERE id = ?");
        $stmt->execute([$pct, $onboardingId]);
    }
}
