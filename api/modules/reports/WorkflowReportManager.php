<?php
namespace App\API\Modules\Reports;
use App\API\Includes\BaseAPI;

class WorkflowReportManager extends BaseAPI
{
    public function getWorkflowInstanceStats($filters = [])
    {
        // Workflow instance statistics: total, completed, running, failed
        $sql = "SELECT status, COUNT(*) as count
                FROM workflow_instances
                GROUP BY status";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getWorkflowStageTimes($filters = [])
    {
        // Workflow stage times: average time per stage
        $sql = "SELECT stage_id, AVG(TIMESTAMPDIFF(SECOND, entered_at, exited_at)) as avg_seconds
                FROM workflow_stage_logs
                WHERE exited_at IS NOT NULL
                GROUP BY stage_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getWorkflowTransitionFrequencies($filters = [])
    {
        // Workflow transition frequencies: count per transition
        $sql = "SELECT from_stage_id, to_stage_id, COUNT(*) as transition_count
                FROM workflow_transitions
                GROUP BY from_stage_id, to_stage_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
