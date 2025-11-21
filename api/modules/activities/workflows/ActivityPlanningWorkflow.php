<?php
namespace App\API\Modules\Activities\Workflows;

require_once __DIR__ . '/../../../includes/WorkflowHandler.php';
use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;

/**
 * ActivityPlanningWorkflow - Manages activity setup and approval process
 * 
 * Workflow Stages:
 * 1. propose - Activity proposal submitted
 * 2. budget_review - Budget review and approval
 * 3. schedule - Schedule and venue booking
 * 4. prepare - Resource allocation and preparation
 * 5. execute - Activity execution
 * 6. review - Post-activity review and reporting
 */
class ActivityPlanningWorkflow extends WorkflowHandler
{
    private $workflowType = 'activity_planning';

    public function __construct()
    {
        parent::__construct('activity_planning_workflow');
    }

    /**
     * Initiate activity planning workflow
     */
    public function proposeActivity($data, $userId)
    {
        try {
            // Validate required fields
            $required = ['title', 'category_id', 'start_date', 'end_date', 'estimated_budget'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            $this->beginTransaction();

            // Create draft activity
            $stmt = $this->db->prepare("
                INSERT INTO activities (
                    title, description, category_id, start_date, end_date,
                    location, max_participants, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'planned', ?, NOW())
            ");
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['category_id'],
                $data['start_date'],
                $data['end_date'],
                $data['location'] ?? null,
                $data['max_participants'] ?? null,
                $userId
            ]);

            $activityId = $this->db->lastInsertId();

            // Create workflow instance
            $workflowData = [
                'title' => $data['title'],
                'category_id' => $data['category_id'],
                'estimated_budget' => $data['estimated_budget'],
                'objectives' => $data['objectives'] ?? null,
                'target_participants' => $data['max_participants'] ?? null
            ];

            $stmt = $this->db->prepare("
                INSERT INTO workflow_instances (
                    workflow_type, entity_type, entity_id, current_stage,
                    status, initiated_by, metadata, created_at
                ) VALUES (?, 'activity', ?, 'propose', 'pending', ?, ?, NOW())
            ");
            $stmt->execute([
                $this->workflowType,
                $activityId,
                $userId,
                json_encode($workflowData)
            ]);

            $workflowId = $this->db->lastInsertId();

            $this->recordHistory($workflowId, 'propose', 'Activity proposed', $userId);

            $this->commit();

            $this->logAction('create', $workflowId, "Activity planning initiated: {$data['title']}");

            return [
                'success' => true,
                'data' => [
                    'workflow_id' => $workflowId,
                    'activity_id' => $activityId,
                    'current_stage' => 'propose'
                ],
                'message' => 'Activity proposal submitted successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, 'Failed to propose activity');
            throw $e;
        }
    }

    /**
     * Review and approve budget
     */
    public function approveBudget($workflowId, $data, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            if ($workflow['current_stage'] !== 'propose') {
                throw new Exception('Workflow must be in propose stage');
            }

            $this->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'budget_review',
                    metadata = JSON_SET(metadata, '$.approved_budget', ?, '$.budget_approved_by', ?, '$.budget_notes', ?)
                WHERE id = ?
            ");
            $stmt->execute([
                $data['approved_budget'],
                $userId,
                $data['notes'] ?? null,
                $workflowId
            ]);

            $this->recordHistory($workflowId, 'budget_review', 'Budget approved', $userId);

            $this->commit();

            return [
                'success' => true,
                'message' => 'Budget approved, proceeding to scheduling',
                'data' => ['current_stage' => 'budget_review']
            ];

        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Schedule activity and book venues
     */
    public function scheduleActivity($workflowId, $schedules, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            $metadata = json_decode($workflow['metadata'], true);

            $this->beginTransaction();

            // Add schedules to activity
            foreach ($schedules as $schedule) {
                $stmt = $this->db->prepare("
                    INSERT INTO activity_schedule (
                        activity_id, day_of_week, start_time, end_time, venue, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $workflow['entity_id'],
                    $schedule['day_of_week'],
                    $schedule['start_time'],
                    $schedule['end_time'],
                    $schedule['venue'] ?? null
                ]);
            }

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'schedule',
                    metadata = JSON_SET(metadata, '$.scheduled_by', ?, '$.scheduled_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'schedule', 'Activity scheduled', $userId);

            $this->commit();

            return [
                'success' => true,
                'message' => 'Activity scheduled successfully',
                'data' => ['current_stage' => 'schedule']
            ];

        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Prepare resources and materials
     */
    public function prepareResources($workflowId, $resources, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            $this->beginTransaction();

            // Add resources
            foreach ($resources as $resource) {
                $stmt = $this->db->prepare("
                    INSERT INTO activity_resources (
                        activity_id, name, type, quantity, cost, notes, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $workflow['entity_id'],
                    $resource['name'],
                    $resource['type'],
                    $resource['quantity'] ?? 1,
                    $resource['cost'] ?? null,
                    $resource['notes'] ?? null
                ]);
            }

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'prepare',
                    metadata = JSON_SET(metadata, '$.prepared_by', ?, '$.prepared_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'prepare', 'Resources prepared', $userId);

            $this->commit();

            return [
                'success' => true,
                'message' => 'Resources prepared, ready to execute',
                'data' => ['current_stage' => 'prepare']
            ];

        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Execute activity
     */
    public function executeActivity($workflowId, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            $this->beginTransaction();

            // Update activity status to ongoing
            $stmt = $this->db->prepare("UPDATE activities SET status = 'ongoing' WHERE id = ?");
            $stmt->execute([$workflow['entity_id']]);

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'execute',
                    status = 'active',
                    metadata = JSON_SET(metadata, '$.executed_by', ?, '$.executed_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'execute', 'Activity execution started', $userId);

            $this->commit();

            return [
                'success' => true,
                'message' => 'Activity execution started',
                'data' => ['current_stage' => 'execute']
            ];

        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    /**
     * Review and complete activity
     */
    public function reviewActivity($workflowId, $data, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);

            $this->beginTransaction();

            // Update activity status to completed
            $stmt = $this->db->prepare("UPDATE activities SET status = 'completed' WHERE id = ?");
            $stmt->execute([$workflow['entity_id']]);

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'review',
                    status = 'completed',
                    metadata = JSON_SET(
                        metadata, 
                        '$.actual_budget', ?, 
                        '$.actual_participants', ?,
                        '$.outcomes', ?,
                        '$.lessons_learned', ?,
                        '$.reviewed_by', ?,
                        '$.reviewed_at', NOW()
                    ),
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['actual_budget'] ?? null,
                $data['actual_participants'] ?? null,
                $data['outcomes'] ?? null,
                $data['lessons_learned'] ?? null,
                $userId,
                $workflowId
            ]);

            // Mark all participants as completed
            $stmt = $this->db->prepare("
                UPDATE activity_participants 
                SET status = 'completed' 
                WHERE activity_id = ? AND status = 'active'
            ");
            $stmt->execute([$workflow['entity_id']]);

            $this->recordHistory($workflowId, 'review', 'Activity reviewed and completed', $userId);

            $this->commit();

            return [
                'success' => true,
                'message' => 'Activity completed and reviewed',
                'data' => ['current_stage' => 'review', 'status' => 'completed']
            ];

        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    private function recordHistory($workflowId, $stage, $action, $userId)
    {
        $stmt = $this->db->prepare("
            INSERT INTO workflow_history (workflow_id, stage, action, performed_by, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$workflowId, $stage, $action, $userId]);
    }
}
