<?php
namespace App\API\Modules\staff;

use App\API\Services\ReadReplicaService;

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

            // Verify staff exists.
            // Identity lives on persons (4NF); department membership is now a
            // time-boxed row in staff_department_assignments (s.department_id dropped).
            $stmt = $this->db->prepare("
                SELECT s.*, p.first_name, p.last_name,
                       st.name as staff_type, sc.category_name, d.name as department_name,
                       sda.department_id
                FROM staff s
                JOIN persons p ON p.id = s.person_id
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN staff_categories sc ON s.staff_category_id = sc.id
                LEFT JOIN staff_department_assignments sda ON sda.staff_id = s.id AND sda.effective_to IS NULL
                LEFT JOIN departments d ON d.id = sda.department_id
                WHERE s.id = ?
            ");
            $stmt->execute([$data['staff_id']]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$staff) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Staff member not found');
            }

            // Check if onboarding already exists.
            // The dropped staff_onboarding parent table is replaced by a
            // workflow_instances row (reference_type='staff_onboarding').
            $stmt = $this->db->prepare("
                SELECT id FROM workflow_instances
                WHERE reference_type = 'staff_onboarding'
                  AND reference_id = ?
                  AND status NOT IN ('completed', 'cancelled')
                LIMIT 1
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

            // Create the onboarding header as a workflow_instances row. Fields that
            // lost their dedicated column (mentor_id, probation_months, target dates,
            // notes) are preserved in data_json so no information is discarded.
            $workflowDefId = $this->resolveStaffOnboardingWorkflowId();
            $initiatedBy = (int)($data['initiated_by'] ?? $this->getCurrentUserId() ?? 0);
            $stmt = $this->db->prepare("
                INSERT INTO workflow_instances
                    (workflow_id, reference_type, reference_id, current_stage, status,
                     started_by, started_at, data_json)
                VALUES (?, 'staff_onboarding', ?, 'onboarding', 'in_progress', ?, NOW(), ?)
            ");
            $stmt->execute([
                $workflowDefId,
                (int)$data['staff_id'],
                $initiatedBy,
                json_encode([
                    'mentor_id'         => $data['mentor_id'] ?? null,
                    'contract_type'     => $data['contract_type'] ?? 'probation',
                    'probation_months'  => $probationMonths,
                    'start_date'        => $startDate,
                    'target_completion' => $targetCompletion,
                    'expected_end_date' => $data['expected_end_date'] ?? $targetCompletion,
                    'notes'             => $data['notes'] ?? $data['remarks']
                                            ?? "Onboarding for {$staff['first_name']} {$staff['last_name']}",
                ]),
            ]);

            $onboardingId = (int)$this->db->lastInsertId();

            // Seed onboarding_tasks directly from active templates. (The legacy
            // sp_auto_generate_onboarding_tasks procedure still targets the dropped
            // staff_onboarding table and would fail, so we do not CALL it.)
            $this->seedOnboardingTasks(
                $onboardingId,
                isset($staff['staff_type_id']) ? (int)$staff['staff_type_id'] : null,
                $startDate,
                isset($staff['department_id']) ? (int)$staff['department_id'] : null
            );

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
                $initiatedBy,
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

            // Verify the onboarding header (a workflow_instances row) exists, and pull
            // the staff identity from persons for logging/response.
            $stmt = $this->db->prepare("
                SELECT wi.id, wi.status, wi.data_json,
                       p.first_name, p.last_name
                FROM workflow_instances wi
                JOIN staff s ON s.id = wi.reference_id
                JOIN persons p ON p.id = s.person_id
                WHERE wi.id = ? AND wi.reference_type = 'staff_onboarding'
            ");
            $stmt->execute([$onboardingId]);
            $onboarding = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$onboarding) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Onboarding record not found');
            }

            // Fields that lost a dedicated column are merged back into data_json so
            // no history is lost; status/completion map to real workflow_instances columns.
            $meta = json_decode($onboarding['data_json'] ?? '', true) ?: [];
            $columnUpdates = [];
            $params = [];

            foreach (['expected_end_date', 'mentor_id', 'target_completion', 'probation_outcome'] as $jsonField) {
                if (isset($data[$jsonField])) {
                    $meta[$jsonField] = $data[$jsonField];
                }
            }
            if (isset($data['remarks'])) {
                $meta['notes'] = $data['remarks'];
            }
            if (isset($data['notes'])) {
                $meta['notes'] = $data['notes'];
            }

            if (isset($data['status'])) {
                $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
                if (!in_array($data['status'], $validStatuses)) {
                    $this->db->rollBack();
                    return formatResponse(false, null, 'Invalid status');
                }
                $columnUpdates[] = "status = ?";
                $params[] = $data['status'];

                if ($data['status'] === 'completed') {
                    $columnUpdates[] = "completed_at = NOW()";
                }
            }

            // Always persist the merged metadata.
            $columnUpdates[] = "data_json = ?";
            $params[] = json_encode($meta);

            $params[] = $onboardingId;
            $sql = "UPDATE workflow_instances SET " . implode(', ', $columnUpdates)
                 . " WHERE id = ? AND reference_type = 'staff_onboarding'";

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
            // Identity is on persons. assigned_to references staff (see
            // vw_onboarding_pending_by_role); completed_by is stamped with a user id.
            $sql = "SELECT ot.*,
                       CONCAT(ap.first_name, ' ', ap.last_name) as assigned_to_name,
                       CONCAT(cp.first_name, ' ', cp.last_name) as completed_by_name
                FROM onboarding_tasks ot
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " assigned_s ON ot.assigned_to = assigned_s.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " ap ON ap.id = assigned_s.person_id
                LEFT JOIN users completed_u ON ot.completed_by = completed_u.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " cp ON cp.id = completed_u.person_id
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
                // staff.department_id is dropped — resolve current membership via the
                // staff_department_assignments junction (effective_to IS NULL = current).
                $where[] = 'staff_id IN (
                    SELECT staff_id FROM staff_department_assignments
                    WHERE department_id = ? AND effective_to IS NULL
                )';
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
                SELECT pr.*, CONCAT(rp.first_name, ' ', rp.last_name) AS reviewer_name
                FROM staff_probation_reviews pr
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " r ON r.id = pr.reviewer_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " rp ON rp.id = r.person_id
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

            // Complete the onboarding header. The dropped staff_onboarding parent
            // table is the workflow_instances row (reference_type='staff_onboarding');
            // fields that lost a dedicated column (notes, actual_completion) are
            // merged back into data_json. progress_percent is DERIVED by the view.
            $meta = $this->fetchOnboardingMeta($onboardingId);
            $meta['actual_completion'] = date('Y-m-d');
            $meta['notes'] = ($meta['notes'] ?? '') . ' | Completed on ' . date('Y-m-d H:i:s');

            $stmt = $this->db->prepare("
                UPDATE workflow_instances
                   SET status = 'completed',
                       completed_at = NOW(),
                       data_json = ?
                 WHERE id = ? AND reference_type = 'staff_onboarding'
            ");
            $stmt->execute([json_encode($meta), $onboardingId]);

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
                // workflow_instances.status has no 'confirmed' value — 'completed'
                // is used, with the probation outcome carried in data_json.
                $meta = $this->fetchOnboardingMeta((int)$data['onboarding_id']);
                $meta['probation_outcome'] = 'confirmed';
                $meta['actual_completion'] = date('Y-m-d');
                $this->updateWorkflowInstanceStatus((int)$data['onboarding_id'], 'completed', $meta);

                $stmt = $this->db->prepare("
                    UPDATE staff_contracts
                       SET contract_type = 'permanent', status = 'active', end_date = NULL
                     WHERE staff_id = ? AND status = 'active'
                ");
                $stmt->execute([(int)$data['staff_id']]);
            } elseif ($outcome === 'extend_probation') {
                $extendMonths = max(1, (int)($data['extend_months'] ?? 3));
                $newTarget = date('Y-m-d', strtotime(date('Y-m-d') . " +{$extendMonths} months"));
                $meta = $this->fetchOnboardingMeta((int)$data['onboarding_id']);
                $meta['probation_outcome'] = 'extended';
                $meta['target_completion'] = $newTarget;
                $meta['expected_end_date'] = $newTarget;
                $this->updateWorkflowInstanceStatus((int)$data['onboarding_id'], null, $meta);
            } elseif ($outcome === 'terminate') {
                // workflow_instances.status enum has no 'terminated' — 'cancelled'
                // carries the semantics, with probation_outcome in data_json.
                $meta = $this->fetchOnboardingMeta((int)$data['onboarding_id']);
                $meta['probation_outcome'] = 'terminated';
                $this->updateWorkflowInstanceStatus((int)$data['onboarding_id'], 'cancelled', $meta);
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
        // No-op under the normalized schema. progress_percent is no longer a stored
        // column on a staff_onboarding row (that table is dropped) — it is DERIVED
        // live from onboarding_tasks status counts by vw_staff_onboarding_progress
        // and vw_onboarding_dashboard. Kept as a stable seam so callers that used to
        // trigger a recalculation continue to work without change.
    }

    /**
     * Read the data_json payload of the workflow_instances row that acts as the
     * onboarding header (reference_type='staff_onboarding').
     */
    private function fetchOnboardingMeta(int $onboardingId): array
    {
        $stmt = $this->db->prepare("
            SELECT data_json FROM workflow_instances
            WHERE id = ? AND reference_type = 'staff_onboarding'
        ");
        $stmt->execute([$onboardingId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Onboarding record not found');
        }
        return json_decode($row['data_json'] ?? '', true) ?: [];
    }

    /**
     * Update the workflow_instances onboarding header. A null $status leaves the
     * workflow status untouched and only rewrites data_json (used when extending
     * probation, where the workflow stays in_progress).
     */
    private function updateWorkflowInstanceStatus(int $onboardingId, ?string $status, array $meta): void
    {
        if ($status !== null) {
            $stmt = $this->db->prepare("
                UPDATE workflow_instances
                   SET status = ?, data_json = ?
                 WHERE id = ? AND reference_type = 'staff_onboarding'
            ");
            $stmt->execute([$status, json_encode($meta), $onboardingId]);
            return;
        }
        $stmt = $this->db->prepare("
            UPDATE workflow_instances
               SET data_json = ?
             WHERE id = ? AND reference_type = 'staff_onboarding'
        ");
        $stmt->execute([json_encode($meta), $onboardingId]);
    }

    /**
     * Resolve the workflow_definitions.id for the 'staff_onboarding' workflow,
     * creating a minimal definition if none is seeded. workflow_instances.workflow_id
     * is NOT NULL, so a definition must exist before an onboarding header can be written.
     */
    private function resolveStaffOnboardingWorkflowId(): int
    {
        $stmt = $this->db->prepare("SELECT id FROM workflow_definitions WHERE code = 'staff_onboarding' LIMIT 1");
        $stmt->execute();
        $id = (int)$stmt->fetchColumn();
        if ($id) {
            return $id;
        }
        $this->db->prepare("
            INSERT INTO workflow_definitions (code, name, description, category, handler_class, is_active, created_at, updated_at)
            VALUES ('staff_onboarding', 'Staff Onboarding', 'New staff onboarding task checklist', 'staff_affairs', 'App\\\\API\\\\Modules\\\\staff\\\\OnboardingWorkflow', 1, NOW(), NOW())
        ")->execute();
        return (int)$this->db->lastInsertId();
    }

    /**
     * Seed onboarding_tasks for a new onboarding instance from the active task templates.
     * Templates whose applies_to_type_ids is set are filtered to the staff's type; NULL/empty
     * applies-to means the template applies to everyone. Due dates derive from the start date
     * plus the template's days_from_start.
     */
    private function seedOnboardingTasks(int $onboardingId, ?int $staffTypeId, string $startDate, ?int $departmentId): void
    {
        $tpl = $this->db->prepare("
            SELECT task_name, description, category, days_from_start, priority
            FROM onboarding_task_templates
            WHERE status = 'active'
              AND (
                    applies_to_type_ids IS NULL
                 OR applies_to_type_ids = ''
                 OR JSON_CONTAINS(applies_to_type_ids, JSON_ARRAY(?))
              )
            ORDER BY display_order, id
        ");
        $tpl->execute([$staffTypeId ?? 0]);
        $templates = $tpl->fetchAll(PDO::FETCH_ASSOC);
        if (!$templates) {
            return;
        }

        $insert = $this->db->prepare("
            INSERT INTO onboarding_tasks
                (onboarding_id, task_name, description, category, department_id, due_date, priority, sequence, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL ? DAY), ?, ?, 'pending', NOW(), NOW())
        ");
        $seq = 0;
        foreach ($templates as $t) {
            $insert->execute([
                $onboardingId,
                $t['task_name'],
                $t['description'] ?? null,
                $t['category'] ?? null,
                $departmentId,
                $startDate,
                (int)($t['days_from_start'] ?? 0),
                $t['priority'] ?? 'medium',
                ++$seq,
            ]);
        }
    }
}
