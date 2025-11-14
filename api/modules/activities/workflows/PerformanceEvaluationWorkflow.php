<?php
namespace App\API\Modules\Activities\Workflows;

require_once __DIR__ . '/../../../includes/WorkflowHandler.php';
use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;

/**
 * PerformanceEvaluationWorkflow - Assesses student performance in activities
 * 
 * Stages: assess → verify → approve → publish
 */
class PerformanceEvaluationWorkflow extends WorkflowHandler
{
    private $workflowType = 'performance_evaluation';

    public function __construct()
    {
        parent::__construct('performance_evaluation_workflow');
    }

    public function initiateEvaluation($data, $userId)
    {
        try {
            $required = ['activity_id', 'participant_id', 'evaluation_period'];
            foreach ($required as $field) {
                if (empty($data[$field]))
                    throw new Exception("Field '$field' is required");
            }

            $this->beginTransaction();

            $workflowData = [
                'participant_id' => $data['participant_id'],
                'evaluation_period' => $data['evaluation_period'],
                'criteria' => $data['criteria'] ?? []
            ];

            $stmt = $this->db->prepare("
                INSERT INTO workflow_instances (
                    workflow_type, entity_type, entity_id, current_stage,
                    status, initiated_by, metadata, created_at
                ) VALUES (?, 'activity_participant', ?, 'assess', 'pending', ?, ?, NOW())
            ");
            $stmt->execute([$this->workflowType, $data['activity_id'], $userId, json_encode($workflowData)]);

            $workflowId = $this->db->lastInsertId();
            $this->recordHistory($workflowId, 'assess', 'Evaluation initiated', $userId);
            $this->commit();

            return [
                'success' => true,
                'data' => ['workflow_id' => $workflowId],
                'message' => 'Evaluation initiated'
            ];
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function submitAssessment($workflowId, $data, $userId)
    {
        try {
            $this->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET metadata = JSON_SET(
                    metadata,
                    '$.scores', ?,
                    '$.attendance_rate', ?,
                    '$.participation_level', ?,
                    '$.skills_acquired', ?,
                    '$.comments', ?,
                    '$.assessed_by', ?,
                    '$.assessed_at', NOW()
                )
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($data['scores'] ?? []),
                $data['attendance_rate'] ?? null,
                $data['participation_level'] ?? null,
                $data['skills_acquired'] ?? null,
                $data['comments'] ?? null,
                $userId,
                $workflowId
            ]);

            $this->recordHistory($workflowId, 'assess', 'Assessment submitted', $userId);
            $this->commit();

            return ['success' => true, 'message' => 'Assessment submitted for verification'];
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function verifyAssessment($workflowId, $data, $userId)
    {
        try {
            $this->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'verify',
                    metadata = JSON_SET(metadata, '$.verified_by', ?, '$.verification_notes', ?, '$.verified_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $data['notes'] ?? null, $workflowId]);

            $this->recordHistory($workflowId, 'verify', 'Assessment verified', $userId);
            $this->commit();

            return ['success' => true, 'message' => 'Assessment verified'];
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function approveEvaluation($workflowId, $userId)
    {
        try {
            $this->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'approve',
                    status = 'approved',
                    metadata = JSON_SET(metadata, '$.approved_by', ?, '$.approved_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'approve', 'Evaluation approved', $userId);
            $this->commit();

            return ['success' => true, 'message' => 'Evaluation approved'];
        } catch (Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function publishResults($workflowId, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            $metadata = json_decode($workflow['metadata'], true);

            $this->beginTransaction();

            // TODO: Store evaluation results in student_activities or separate evaluations table

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'publish',
                    status = 'completed',
                    metadata = JSON_SET(metadata, '$.published_by', ?, '$.published_at', NOW()),
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'publish', 'Results published', $userId);
            $this->commit();

            return [
                'success' => true,
                'message' => 'Evaluation results published',
                'data' => ['status' => 'completed']
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
