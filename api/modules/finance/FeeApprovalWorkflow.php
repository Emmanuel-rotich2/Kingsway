<?php

namespace App\API\Modules\finance;

use App\API\Includes\WorkflowHandler;
use App\API\Modules\finance\FeeManager;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

/**
 * Fee Approval Workflow
 * 
 * Multi-stage workflow for fee structure approval
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. draft - Fee structure created
 * 2. review - Under review by finance team
 * 3. approval - Pending director approval
 * 4. activation - Fee structure activated
 */
class FeeApprovalWorkflow extends WorkflowHandler
{
    protected $workflowType = 'fee_approval';
    private $feeManager;

    public function __construct()
    {
        parent::__construct('FEE_APPROVAL');
        $this->feeManager = new FeeManager();
    }

    /**
     * Initiate fee approval workflow
     * @param int $feeStructureId Fee structure ID
     * @param int $userId User initiating workflow
     * @param array $data Additional data
     * @return array Response
     */
    public function initiateFeeApproval($feeStructureId, $userId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            // Verify fee structure exists (3NF: fee_catalog)
            $stmt = $this->db->prepare("
                SELECT id, name, default_amount AS amount
                FROM fee_catalog 
                WHERE id = ?
            ");
            $stmt->execute([$feeStructureId]);
            $feeStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feeStructure) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Fee structure not found');
            }

            // Check for existing active workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM " . ReadReplicaService::qualifiedRef("workflow_instances") . "
                WHERE wi.workflow_id = ?
                AND wi.status IN ('in_progress', 'pending')
                AND JSON_EXTRACT(wi.data_json, '$.fee_structure_id') = ?
            ");
            $stmt->execute([$this->workflow_id, $feeStructureId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active approval workflow already exists for this fee structure');
            }

            // Create workflow instance
            $workflowData = [
                'fee_structure_id' => $feeStructureId,
                'fee_name' => $feeStructure['name'],
                'amount' => $feeStructure['amount'],
                'initiated_by' => $userId,
                'initiated_at' => date('Y-m-d H:i:s')
            ];

            $instanceId = $this->startWorkflow(
                'fee_structure',
                $feeStructureId,
                $workflowData
            );

            if (!$instanceId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Failed to create workflow instance');
            }

            // Advance to review stage
            $this->advanceStage($instanceId, 'review', 'submitted_for_review', [
                'notes' => $data['notes'] ?? 'Fee structure submitted for review'
            ]);

            // Update fee structure status (3NF: fee_catalog has active/inactive status)
            $stmt = $this->db->prepare("
                UPDATE fee_catalog 
                SET status = 'inactive'
                WHERE id = ?
            ");
            $stmt->execute([$feeStructureId]);

            $this->db->commit();

            return formatResponse(true, [
                'workflow_instance_id' => $instanceId,
                'message' => 'Fee approval workflow initiated successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Finance team review
     * @param int $instanceId Workflow instance ID
     * @param int $userId Reviewing user ID
     * @param array $data Review data
     * @return array Response
     */
    public function financeReview($instanceId, $userId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Get workflow instance
            $instance = $this->getWorkflowInstance($instanceId);
            if (!$instance) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow instance not found');
            }

            // Verify current stage
            if ($instance['current_stage'] !== 'review') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in review stage');
            }

            $action = $data['action'] ?? ''; // 'approve' or 'reject'

            if ($action === 'approve') {
                // Advance to approval stage
                $this->advanceStage($instanceId, 'approval', 'finance_approved', [
                    'notes' => $data['notes'] ?? 'Approved by finance team',
                    'reviewed_by' => $userId
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Fee structure approved by finance team']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected by finance team');

                // Update fee structure status (3NF: fee_catalog)
                $workflowData = json_decode($instance['data_json'], true);
                $stmt = $this->db->prepare("
                    UPDATE fee_catalog 
                    SET status = 'inactive'
                    WHERE id = ?
                ");
                $stmt->execute([$workflowData['fee_structure_id']]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Fee structure rejected']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Director approval
     * @param int $instanceId Workflow instance ID
     * @param int $userId Director user ID
     * @param array $data Approval data
     * @return array Response
     */
    public function directorApproval($instanceId, $userId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Get workflow instance
            $instance = $this->getWorkflowInstance($instanceId);
            if (!$instance) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow instance not found');
            }

            // Verify current stage
            if ($instance['current_stage'] !== 'approval') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in approval stage');
            }

            $action = $data['action'] ?? ''; // 'approve' or 'reject'

            if ($action === 'approve') {
                // Advance to activation stage
                $this->advanceStage($instanceId, 'activation', 'director_approved', [
                    'notes' => $data['notes'] ?? 'Approved by director',
                    'approved_by' => $userId,
                    'approved_at' => date('Y-m-d H:i:s')
                ]);

                // Activate fee structure (3NF: fee_catalog)
                $workflowData = json_decode($instance['data_json'], true);
                $stmt = $this->db->prepare("
                    UPDATE fee_catalog 
                    SET status = 'active'
                    WHERE id = ?
                ");
                $stmt->execute([$workflowData['fee_structure_id']]);

                // Complete workflow
                $this->completeWorkflow($instanceId, [
                    'completed_by' => $userId,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'outcome' => 'approved_and_activated'
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Fee structure approved and activated']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected by director');

                // Update fee structure status (3NF: fee_catalog)
                $workflowData = json_decode($instance['data_json'], true);
                $stmt = $this->db->prepare("
                    UPDATE fee_catalog 
                    SET status = 'inactive'
                    WHERE id = ?
                ");
                $stmt->execute([$workflowData['fee_structure_id']]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Fee structure rejected by director']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get workflow status
     * @param int $feeStructureId Fee structure ID
     * @return array Response with workflow status
     */
    public function getFeeApprovalStatus($feeStructureId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       fs.name as fee_name,
                       fs.status as fee_status
                FROM " . ReadReplicaService::qualifiedRef("workflow_instances") . "
                INNER JOIN fee_catalog fs ON JSON_EXTRACT(wi.data_json, '$.fee_structure_id') = fs.id
                WHERE wi.workflow_id = ?
                AND JSON_EXTRACT(wi.data_json, '$.fee_structure_id') = ?
                ORDER BY wi.started_at DESC
                LIMIT 1
            ");

            $stmt->execute([$this->workflow_id, $feeStructureId]);
            $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workflow) {
                return formatResponse(false, null, 'No approval workflow found');
            }

            // Get stage history
            $stmt = $this->db->prepare("
                SELECT * FROM workflow_stage_history
                WHERE instance_id = ?
                ORDER BY processed_at ASC
            ");
            $stmt->execute([$workflow['id']]);
            $workflow['stage_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $workflow);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * WRAPPER METHODS FOR FinanceAPI INTEGRATION
     * These methods provide a consistent interface expected by FinanceAPI
     */

    /**
     * Submit fee structure for approval (wrapper for initiateFeeApproval)
     * 
     * @param int $id Fee structure ID
     * @param array $data Additional data
     * @param int $userId User initiating submission
     * @return array Response with status and data
     */
    public function submitForApproval($id, $data, $userId)
    {
        // Map to existing initiateFeeApproval method
        return $this->initiateFeeApproval($id, $userId, $data);
    }

    /**
     * Approve fee structure (wrapper for directorApproval)
     * 
     * @param int $id Fee structure ID
     * @param array $data Approval data (remarks, etc.)
     * @param int $userId User approving
     * @return array Response with status and data
     */
    public function approve($id, $data, $userId)
    {
        try {
            // Get the workflow instance ID from fee structure ID
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this fee structure');
            }

            // Call existing directorApproval method
            return $this->directorApproval($instanceId, $userId, $data);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Reject fee structure at any stage
     * 
     * @param int $id Fee structure ID
     * @param array $data Rejection data (remarks required)
     * @param int $userId User rejecting
     * @return array Response with status and data
     */
    public function reject($id, $data, $userId)
    {
        try {
            // Get the workflow instance ID
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this fee structure');
            }

            // Get current workflow instance
            $stmt = $this->db->prepare("SELECT * FROM workflow_instances WHERE id = ?");
            $stmt->execute([$instanceId]);
            $instance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            // Cancel workflow
            $this->cancelWorkflow($instanceId, $data['remarks'] ?? 'Fee structure rejected');

            // Update fee structure status (3NF: fee_catalog)
            $stmt = $this->db->prepare("UPDATE fee_catalog SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('reject_fee_structure', $id, "Fee structure ID $id rejected");

            return formatResponse(true, ['message' => 'Fee structure rejected']);

            return $result;

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Activate fee structure after approval
     * 
     * @param int $id Fee structure ID
     * @param array $data Activation data
     * @param int $userId User activating
     * @return array Response with status and data
     */
    public function activate($id, $data, $userId)
    {
        try {
            // Verify fee structure is approved (3NF: fee_catalog has active/inactive status)
            $stmt = $this->db->prepare("SELECT status FROM fee_catalog WHERE id = ?");
            $stmt->execute([$id]);
            $feeStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feeStructure) {
                return formatResponse(false, null, 'Fee structure not found');
            }

            if ($feeStructure['status'] !== 'active') {
                return formatResponse(false, null, 'Fee structure must be approved before activation');
            }

            // Activate the fee structure
            $stmt = $this->db->prepare("UPDATE fee_catalog SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('activate_fee_structure', $id, "Fee structure ID $id activated");

            return formatResponse(true, ['id' => $id, 'status' => 'active'], 'Fee structure activated successfully');

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FeeApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Helper method to get workflow instance ID from fee structure ID
     * 
     * @param int $feeStructureId Fee structure ID
     * @return int|null Workflow instance ID or null if not found
     */
    private function getInstanceId($feeStructureId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM workflow_instances
                WHERE workflow_id = ?
                AND JSON_EXTRACT(data_json, '$.fee_structure_id') = ?
                AND status IN ('pending', 'in_progress')
                ORDER BY started_at DESC
                LIMIT 1
            ");
            $stmt->execute([$this->workflow_id, $feeStructureId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['id'] : null;

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("Failed to get instance ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate a transition between workflow stages.
     *
     * @param string|null $fromStage
     * @param string $toStage
     * @param array $data
     * @return bool
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        // Define permitted transitions
        $allowed = [
            'draft' => ['review'],
            'review' => ['approval', 'rejected'],
            'approval' => ['activation', 'rejected'],
            'activation' => ['completed'],
        ];

        // Always allow explicit rejection from any stage
        if ($toStage === 'rejected') {
            return true;
        }

        // If no fromStage (new instance), allow move to draft or review
        if (empty($fromStage)) {
            return in_array($toStage, ['draft', 'review']);
        }

        if (!isset($allowed[$fromStage])) {
            return false;
        }

        return in_array($toStage, $allowed[$fromStage], true);
    }

    /**
     * Execute any side-effects when entering a stage.
     *
     * @param string $stage
     * @param array $data
     * @return bool
     */
    protected function processStage($stage, $data)
    {
        try {
            // Log the stage processing for audit
            $this->logAction('process_stage', $data['fee_structure_id'] ?? null, "Processing workflow stage: {$stage}", $data);

            // Minimal stage-specific actions (safe defaults) — 3NF: fee_catalog
            if ($stage === 'activation') {
                if (!empty($data['fee_structure_id'])) {
                    $stmt = $this->db->prepare("UPDATE fee_catalog SET status = 'active' WHERE id = ?");
                    $stmt->execute([$data['fee_structure_id']]);
                }
            } elseif ($stage === 'review') {
                // mark pending approval status if fee_structure_id provided (3NF has no pending_approval)
                if (!empty($data['fee_structure_id'])) {
                    $stmt = $this->db->prepare("UPDATE fee_catalog SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$data['fee_structure_id']]);
                }
            }

            return true;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("Failed to process stage {$stage}: " . $e->getMessage());
            return false;
        }
    }
}
