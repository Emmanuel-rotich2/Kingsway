<?php
namespace App\API\Modules\activities\workflows;

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


    /**
     * Verifies an assessment in the workflow
     */
    public function verifyAssessment($workflowId, $data, $userId)
    {
        error_log("[PerformanceEvaluationWorkflow] verifyAssessment called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'verify',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.verified_by', ?, '$.verification_notes', ?, '$.verified_at', NOW())
                WHERE id = ?
            ");
            if (!$stmt->execute([$userId, $data['notes'] ?? null, $workflowId])) {
                throw new Exception('Failed to update workflow instance for verification');
            }

            $this->recordHistory($workflowId, 'verify', 'Assessment verified', $userId);
            $this->db->commit();

            return ['success' => true, 'message' => 'Assessment verified'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    private $workflowType = 'performance_evaluation';

    public function __construct()
    {
        parent::__construct('performance_evaluation_workflow');
    }

    public function initiateEvaluation($data, $userId)
    {
        error_log("[PerformanceEvaluationWorkflow] initiateEvaluation called. userId=" . var_export($userId, true) . ", data=" . json_encode($data));
        try {
            $required = ['activity_id', 'participant_id', 'evaluation_period'];
            foreach ($required as $field) {
                if (empty($data[$field]))
                    throw new Exception("Field '$field' is required");
            }

            $workflowData = [
                'participant_id' => $data['participant_id'],
                'evaluation_period' => $data['evaluation_period'],
                'criteria' => $data['criteria'] ?? []
            ];

            // Use WorkflowHandler's startWorkflow method
            $workflowId = $this->startWorkflow('activity_participant', $data['activity_id'], $workflowData, $userId);

            return [
                'success' => true,
                'data' => ['workflow_id' => $workflowId],
                'message' => 'Evaluation initiated'
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function submitAssessment($workflowId, $data, $userId)
    {
        error_log("[PerformanceEvaluationWorkflow] submitAssessment called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "UPDATE workflow_instances 
                SET data_json = JSON_SET(
                    COALESCE(data_json, '{}'),
                    '$.scores', ?,
                    '$.attendance_rate', ?,
                    '$.participation_level', ?,
                    '$.skills_acquired', ?,
                    '$.comments', ?,
                    '$.assessed_by', ?,
                    '$.assessed_at', NOW()
                )
                WHERE id = ?"
            );
            if (
                !$stmt->execute([
                json_encode($data['scores'] ?? []),
                $data['attendance_rate'] ?? null,
                $data['participation_level'] ?? null,
                $data['skills_acquired'] ?? null,
                $data['comments'] ?? null,
                $userId,
                $workflowId
                ])
            ) {
                throw new Exception('Failed to update workflow instance for assessment');
            }

            $this->recordHistory($workflowId, 'assess', 'Assessment submitted', $userId);
            $this->db->commit();
            return ['success' => true, 'message' => 'Assessment submitted'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function approveEvaluation($workflowId, $userId)
    {
        error_log("[PerformanceEvaluationWorkflow] approveEvaluation called. userId=" . var_export($userId, true) . ", workflowId=" . var_export($workflowId, true));
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'approve',
                    status = 'approved',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.approved_by', ?, '$.approved_at', NOW())
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'approve', 'Evaluation approved', $userId);
            $this->db->commit();

            return ['success' => true, 'message' => 'Evaluation approved'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function publishResults($workflowId, $userId)
    {
        error_log("[PerformanceEvaluationWorkflow] publishResults called. userId=" . var_export($userId, true) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            $metadata = null;
            if (isset($workflow['data_json']) && is_string($workflow['data_json'])) {
                $metadata = json_decode($workflow['data_json'], true);
                if ($metadata === null && json_last_error() !== JSON_ERROR_NONE) {
                    $metadata = [];
                }
            } else {
                $metadata = [];
            }

            $this->db->beginTransaction();

            // TODO: Store evaluation results in student_activities or separate evaluations table

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'publish',
                    status = 'completed',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.published_by', ?, '$.published_at', NOW()),
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$userId, $workflowId]);

            $this->recordHistory($workflowId, 'publish', 'Results published', $userId);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Evaluation results published',
                'data' => ['status' => 'completed']
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
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
