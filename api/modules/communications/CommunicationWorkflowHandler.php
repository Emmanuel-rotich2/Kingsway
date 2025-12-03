<?php

namespace App\API\Modules\communications;

use App\API\Includes\WorkflowHandler;


class CommunicationWorkflowHandler extends WorkflowHandler
{
    /**
     * CommunicationWorkflowHandler constructor.
     * @param string $workflow_code The workflow code as defined in workflow_definitions
     */
    public function __construct($workflow_code = 'communications')
    {
        parent::__construct($workflow_code);
    }

    /**
     * Start a new communication workflow instance (initiation stage)
     * @param string $reference_type
     * @param int $reference_id
     * @param array $data
     * @return int|false Workflow instance ID or false on failure
     */
    public function initiateCommunicationWorkflow($reference_type, $reference_id, $data = [])
    {
        // Starts the workflow at the 'initiation' stage
        return $this->startWorkflow($reference_type, $reference_id, $data);
    }

    /**
     * Approve a communication workflow instance (approval stage)
     * @param int $instance_id
     * @param array $action_data
     * @return bool
     */
    public function approveCommunication($instance_id, $action_data = [])
    {
        // Advances the workflow to the 'completed' stage from 'approval'
        return $this->advanceStage($instance_id, 'completed', 'approve', $action_data);
    }

    /**
     * Escalate a communication workflow instance (escalation stage)
     * @param int $instance_id
     * @param array $action_data
     * @return bool
     */
    public function escalateCommunication($instance_id, $action_data = [])
    {
        // Advances the workflow to the 'escalation' stage
        return $this->advanceStage($instance_id, 'escalation', 'escalate', $action_data);
    }

    /**
     * Complete a communication workflow instance (from escalation or approval)
     * @param int $instance_id
     * @param array $completion_data
     * @return bool
     */
    public function completeCommunication($instance_id, $completion_data = [])
    {
        // Completes the workflow instance
        return $this->completeWorkflow($instance_id, $completion_data);
    }

    /**
     * Get workflow instance details (overrides for communication-specific enrichment if needed)
     * @param int $instance_id
     * @return array|null
     */
    public function getCommunicationWorkflowInstance($instance_id)
    {
        // You can add extra enrichment here if needed
        return $this->getWorkflowInstance($instance_id);
    }

    /**
     * List all communication workflow instances with optional filters
     * @param array $filters
     * @return array
     */
    public function listCommunicationWorkflows($filters = [])
    {
        // You can add communication-specific filters here if needed
        return $this->listWorkflows($filters);
    }
}
