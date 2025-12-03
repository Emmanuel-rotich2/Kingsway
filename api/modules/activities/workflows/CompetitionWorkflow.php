<?php
namespace App\API\Modules\activities\workflows;

require_once __DIR__ . '/../../../includes/WorkflowHandler.php';
use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;

/**
 * CompetitionWorkflow - Manages inter-school competitions
 * 
 * Stages: register → prepare → participate → report → recognize
 */
class CompetitionWorkflow extends WorkflowHandler
{
    private $workflowType = 'competition';

    public function __construct()
    {
        parent::__construct('competition_workflow');
    }

    public function registerForCompetition($data, $userId)
    {
        error_log("[CompetitionWorkflow] registerForCompetition called. userId=" . var_export($userId, true) . ", data=" . json_encode($data));
        try {
            $required = ['activity_id', 'competition_name', 'venue', 'competition_date'];
            foreach ($required as $field) {
                if (empty($data[$field]))
                    throw new Exception("Field '$field' is required");
            }

            // Compose workflow data
            $workflowData = [
                'competition_name' => $data['competition_name'],
                'venue' => $data['venue'],
                'competition_date' => $data['competition_date'],
                'category' => $data['category'] ?? null,
                'participants' => $data['participants'] ?? []
            ];

            // Use WorkflowHandler's startWorkflow method
            $workflowId = $this->startWorkflow('activity', $data['activity_id'], $workflowData, $userId);

            return [
                'success' => true,
                'data' => ['workflow_id' => $workflowId, 'current_stage' => $this->getFirstStage()['code']],
                'message' => 'Competition registration successful'
            ];
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function prepareTeam($workflowId, $data, $userId)
    {
        error_log("[CompetitionWorkflow] prepareTeam called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'prepare',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.team_members', ?, '$.coach', ?, '$.preparation_notes', ?)
                WHERE id = ?
            ");
            $stmt->execute([
                json_encode($data['team_members'] ?? []),
                $data['coach'] ?? null,
                $data['notes'] ?? null,
                $workflowId
            ]);

            $this->recordHistory($workflowId, 'prepare', 'Team preparation completed', $userId);
            $this->db->commit();

            return ['success' => true, 'message' => 'Team prepared for competition'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function recordParticipation($workflowId, $data, $userId)
    {
        error_log("[CompetitionWorkflow] recordParticipation called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'participate',
                    data_json = JSON_SET(COALESCE(data_json, '{}'), '$.participation_date', NOW(), '$.attendance', ?)
                WHERE id = ?
            ");
            $stmt->execute([json_encode($data['attendance'] ?? []), $workflowId]);

            $this->recordHistory($workflowId, 'participate', 'Competition participation recorded', $userId);
            $this->db->commit();

            return ['success' => true, 'message' => 'Participation recorded'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reportResults($workflowId, $data, $userId)
    {
        error_log("[CompetitionWorkflow] reportResults called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'report',
                    data_json = JSON_SET(
                        COALESCE(data_json, '{}'), 
                        '$.position', ?,
                        '$.score', ?,
                        '$.awards', ?,
                        '$.achievements', ?,
                        '$.report_notes', ?
                    )
                WHERE id = ?
            ");
            $stmt->execute([
                $data['position'] ?? null,
                $data['score'] ?? null,
                json_encode($data['awards'] ?? []),
                $data['achievements'] ?? null,
                $data['notes'] ?? null,
                $workflowId
            ]);

            $this->recordHistory($workflowId, 'report', 'Competition results reported', $userId);
            $this->db->commit();

            return ['success' => true, 'message' => 'Results reported successfully'];
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function recognizeAchievements($workflowId, $data, $userId)
    {
        error_log("[CompetitionWorkflow] recognizeAchievements called. userId=" . var_export($userId, true) . ", data=" . json_encode($data) . ", workflowId=" . var_export($workflowId, true));
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE workflow_instances 
                SET current_stage = 'recognize',
                    status = 'completed',
                    data_json = JSON_SET(
                        COALESCE(data_json, '{}'),
                        '$.recognition_type', ?,
                        '$.certificates', ?,
                        '$.awards_ceremony_date', ?
                    ),
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['recognition_type'] ?? null,
                json_encode($data['certificates'] ?? []),
                $data['ceremony_date'] ?? null,
                $workflowId
            ]);

            $this->recordHistory($workflowId, 'recognize', 'Achievements recognized', $userId);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Competition workflow completed',
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
