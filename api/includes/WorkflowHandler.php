<?php
namespace App\API\Includes;

require_once __DIR__ . '/BaseAPI.php';

use App\Config\Database;
use PDO;
use Exception;

/**
 * Base Workflow Handler
 * Provides common workflow management functionality for all workflow types
 */
class WorkflowHandler extends BaseAPI
{
   
    protected $workflow_code;
    protected $workflow_id;
    protected $workflow_config;

    public function __construct($workflow_code)
    {
        parent::__construct('workflow');
        $this->workflow_code = $workflow_code;
        $this->loadWorkflowDefinition();
    }

     /**
     * List all workflow instances for this workflow
     * @param array $filters
     * @return array
     */

    public function listWorkflows($filters = [])
    {
        $sql = "SELECT wi.*, wd.code as workflow_code, wd.name as workflow_name, ws.name as current_stage_name, ws.description as stage_description, u.username as started_by_username
                FROM workflow_instances wi
                JOIN workflow_definitions wd ON wi.workflow_id = wd.id
                LEFT JOIN workflow_stages ws ON wi.workflow_id = ws.workflow_id AND wi.current_stage = ws.code
                LEFT JOIN users u ON wi.started_by = u.id
                WHERE wi.workflow_id = :workflow_id";

        $params = ['workflow_id' => $this->workflow_id];
        if (!empty($filters['reference_type'])) {
            $sql .= " AND wi.reference_type = :reference_type";
            $params['reference_type'] = $filters['reference_type'];
        }
        if (!empty($filters['reference_id'])) {
            $sql .= " AND wi.reference_id = :reference_id";
            $params['reference_id'] = $filters['reference_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND wi.status = :status";
            $params['status'] = $filters['status'];
        }
        $sql .= " ORDER BY wi.started_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $instances = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($instances as &$instance) {
            if ($instance['data_json']) {
                $instance['data'] = json_decode($instance['data_json'], true);
            }
        }
        return $instances;
    }

    /**
     * Load workflow definition from database
     */
    protected function loadWorkflowDefinition()
    {
        try {
            $sql = "SELECT id, name, description, category, handler_class, config_json, is_active 
                    FROM workflow_definitions 
                    WHERE code = :code AND is_active = 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['code' => $this->workflow_code]);
            $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workflow) {
                throw new Exception("Workflow '{$this->workflow_code}' not found or inactive");
            }

            $this->workflow_id = $workflow['id'];
            $this->workflow_config = json_decode($workflow['config_json'], true) ?? [];

            $this->logAction('workflow_loaded', $this->workflow_id, "Loaded workflow: {$workflow['name']}");
        } catch (Exception $e) {
            $this->logError('workflow_load_failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Start a new workflow instance
     * 
     * @param string $reference_type Entity type (e.g., 'student', 'staff', 'application')
     * @param int $reference_id Entity ID
     * @param array $initial_data Optional initial workflow data
     * @return int Workflow instance ID
     */
    public function startWorkflow($reference_type, $reference_id, $initial_data = [])
    {
        try {
            $this->db->beginTransaction();

            // Get first stage
            $first_stage = $this->getFirstStage();
            if (!$first_stage) {
                throw new Exception("No starting stage found for workflow '{$this->workflow_code}'");
            }

            // Create workflow instance
            $sql = "INSERT INTO workflow_instances 
                    (workflow_id, reference_type, reference_id, current_stage, status, 
                     started_by, started_at, data_json) 
                    VALUES 
                    (:workflow_id, :reference_type, :reference_id, :current_stage, 'in_progress', 
                     :started_by, NOW(), :data_json)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'workflow_id' => $this->workflow_id,
                'reference_type' => $reference_type,
                'reference_id' => $reference_id,
                'current_stage' => $first_stage['code'],
                'started_by' => $this->user_id,
                'data_json' => json_encode($initial_data)
            ]);

            $instance_id = $this->db->lastInsertId();

            // Log stage entry
            $this->logStageEntry($instance_id, $first_stage['code'], 'Workflow started');

            // Execute stage entry actions
            $this->executeStageActions($instance_id, $first_stage);

            // Send notifications
            $this->sendStageNotifications($instance_id, $first_stage, 'stage_entry');

            $this->db->commit();

            $this->logAction('workflow_started', $instance_id, 
                "Started workflow instance for {$reference_type} ID {$reference_id}");

            return $instance_id;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('workflow_start_failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Advance workflow to next stage
     * 
     * @param int $instance_id Workflow instance ID
     * @param string $to_stage Target stage code
     * @param string $action Action being performed
     * @param array $action_data Additional action data
     * @return bool Success status
     */
    public function advanceStage($instance_id, $to_stage, $action, $action_data = [])
    {
        try {
            // Use stored procedure for atomic stage advancement
            $sql = "CALL sp_advance_workflow_stage(:instance_id, :to_stage, :action, :user_id, :notes)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'instance_id' => $instance_id,
                'to_stage' => $to_stage,
                'action' => $action,
                'user_id' => $this->user_id,
                'notes' => json_encode($action_data)
            ]);

            // Get new stage details
            $stage = $this->getStageDetails($to_stage);
            if ($stage) {
                // Execute stage actions
                $this->executeStageActions($instance_id, $stage);
                
                // Send notifications
                $this->sendStageNotifications($instance_id, $stage, 'stage_entry');
            }

            $this->logAction('workflow_advanced', $instance_id, 
                "Advanced to stage: {$to_stage} via action: {$action}");

            return true;
        } catch (Exception $e) {
            $this->logError('workflow_advance_failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Complete workflow
     * 
     * @param int $instance_id Workflow instance ID
     * @param array $completion_data Final workflow data
     * @return bool Success status
     */
    public function completeWorkflow($instance_id, $completion_data = [])
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE workflow_instances 
                    SET status = 'completed', 
                        completed_at = NOW(),
                        data_json = JSON_SET(COALESCE(data_json, '{}'), '$.completion', :completion_data)
                    WHERE id = :instance_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'instance_id' => $instance_id,
                'completion_data' => json_encode($completion_data)
            ]);

            // Log completion
            $this->logStageEntry($instance_id, 'completed', 'Workflow completed successfully');

            // Send completion notifications
            $this->sendWorkflowNotification($instance_id, 'Workflow Completed', 
                'The workflow has been completed successfully', 'stage_complete');

            $this->db->commit();

            $this->logAction('workflow_completed', $instance_id, 'Workflow completed');

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('workflow_complete_failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Cancel workflow
     * 
     * @param int $instance_id Workflow instance ID
     * @param string $reason Cancellation reason
     * @return bool Success status
     */
    public function cancelWorkflow($instance_id, $reason)
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE workflow_instances 
                    SET status = 'cancelled',
                        data_json = JSON_SET(COALESCE(data_json, '{}'), '$.cancellation_reason', :reason)
                    WHERE id = :instance_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'instance_id' => $instance_id,
                'reason' => $reason
            ]);

            $this->logStageEntry($instance_id, 'cancelled', "Cancelled: {$reason}");

            $this->db->commit();

            $this->logAction('workflow_cancelled', $instance_id, "Workflow cancelled: {$reason}");

            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logError('workflow_cancel_failed', $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get workflow instance details
     * 
     * @param int $instance_id Workflow instance ID
     * @return array|null Workflow instance data
     */
    public function getWorkflowInstance($instance_id)
    {
        $sql = "SELECT wi.*, wd.code as workflow_code, wd.name as workflow_name,
                       ws.name as current_stage_name, ws.description as stage_description,
                       u.username as started_by_username
                FROM workflow_instances wi
                JOIN workflow_definitions wd ON wi.workflow_id = wd.id
                LEFT JOIN workflow_stages ws ON wi.workflow_id = ws.workflow_id AND wi.current_stage = ws.code
                LEFT JOIN users u ON wi.started_by = u.id
                WHERE wi.id = :instance_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['instance_id' => $instance_id]);
        
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($instance && $instance['data_json']) {
            $instance['data'] = json_decode($instance['data_json'], true);
        }
        
        return $instance;
    }

    /**
     * Get workflow history
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Stage history
     */
    public function getWorkflowHistory($instance_id)
    {
        $sql = "SELECT wsh.*, u.username as processed_by_username
                FROM workflow_stage_history wsh
                LEFT JOIN users u ON wsh.processed_by = u.id
                WHERE wsh.instance_id = :instance_id
                ORDER BY wsh.entered_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['instance_id' => $instance_id]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available actions for current stage
     * 
     * @param int $instance_id Workflow instance ID
     * @return array Available actions
     */
    public function getAvailableActions($instance_id)
    {
        $instance = $this->getWorkflowInstance($instance_id);
        if (!$instance) {
            return [];
        }

        $stage = $this->getStageDetails($instance['current_stage']);
        if (!$stage || !$stage['allowed_transitions']) {
            return [];
        }

        $transitions = json_decode($stage['allowed_transitions'], true) ?? [];
        $actions = [];

        foreach ($transitions as $transition) {
            if (is_array($transition)) {
                $actions[] = [
                    'action' => $transition['action'] ?? 'unknown',
                    'target_stage' => $transition['target'] ?? null,
                    'label' => $transition['label'] ?? $transition['action'],
                    'requires_data' => $transition['requires_data'] ?? false
                ];
            }
        }

        return $actions;
    }

    // Protected helper methods

    protected function getFirstStage()
    {
        $sql = "SELECT * FROM workflow_stages 
                WHERE workflow_id = :workflow_id 
                AND is_active = 1 
                ORDER BY sequence ASC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['workflow_id' => $this->workflow_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function getStageDetails($stage_code)
    {
        $sql = "SELECT * FROM workflow_stages 
                WHERE workflow_id = :workflow_id 
                AND code = :code 
                AND is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'workflow_id' => $this->workflow_id,
            'code' => $stage_code
        ]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    protected function logStageEntry($instance_id, $stage_code, $notes = '')
    {
        $sql = "INSERT INTO workflow_stage_history 
                (instance_id, stage_code, action_taken, processed_by, entered_at, notes) 
                VALUES 
                (:instance_id, :stage_code, 'entered', :processed_by, NOW(), :notes)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'instance_id' => $instance_id,
            'stage_code' => $stage_code,
            'processed_by' => $this->user_id,
            'notes' => $notes
        ]);
    }

    protected function executeStageActions($instance_id, $stage)
    {
        if (empty($stage['action_config'])) {
            return;
        }

        $actions = json_decode($stage['action_config'], true) ?? [];
        
        // Execute procedures
        if (!empty($actions['procedures'])) {
            foreach ($actions['procedures'] as $procedure) {
                $this->executeProcedure($procedure, $instance_id);
            }
        }

        // Log trigger execution (triggers fire automatically)
        if (!empty($actions['triggers'])) {
            $this->logAction('triggers_fired', $instance_id, 
                'Triggers: ' . implode(', ', $actions['triggers']));
        }

        // Log events (events run on schedule)
        if (!empty($actions['events'])) {
            $this->logAction('events_scheduled', $instance_id, 
                'Events: ' . implode(', ', $actions['events']));
        }
    }

    protected function executeProcedure($procedure_name, $instance_id)
    {
        try {
            // This is a placeholder - actual procedure calls will be implemented
            // in specific workflow handlers based on their needs
            $this->logAction('procedure_executed', $instance_id, 
                "Executed procedure: {$procedure_name}");
        } catch (Exception $e) {
            $this->logError('procedure_execution_failed', 
                "Failed to execute {$procedure_name}: " . $e->getMessage());
        }
    }

    protected function sendStageNotifications($instance_id, $stage, $notification_type)
    {
        try {
            $required_role = $stage['required_role'] ?? null;
            if (!$required_role) {
                return;
            }

            // Get users with the required role
            $sql = "SELECT u.id, u.username, u.email 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.id 
                    WHERE r.name = :role AND u.status = 'active'";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['role' => $required_role]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $instance = $this->getWorkflowInstance($instance_id);
            $title = "Action Required: {$instance['workflow_name']}";
            $message = "Stage '{$stage['name']}' requires your attention.";

            foreach ($users as $user) {
                $this->createNotification($instance_id, $user['id'], $title, $message, $notification_type);
            }
        } catch (Exception $e) {
            $this->logError('notification_failed', $e->getMessage());
        }
    }

    protected function sendWorkflowNotification($instance_id, $title, $message, $type)
    {
        try {
            $instance = $this->getWorkflowInstance($instance_id);
            
            // Notify workflow starter
            $this->createNotification($instance_id, $instance['started_by'], $title, $message, $type);
        } catch (Exception $e) {
            $this->logError('notification_failed', $e->getMessage());
        }
    }

    protected function createNotification($instance_id, $user_id, $title, $message, $type)
    {
        $sql = "INSERT INTO workflow_notifications 
                (instance_id, notification_type, user_id, title, message, created_at) 
                VALUES 
                (:instance_id, :type, :user_id, :title, :message, NOW())";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'instance_id' => $instance_id,
            'type' => $type,
            'user_id' => $user_id,
            'title' => $title,
            'message' => $message
        ]);
    }
}
