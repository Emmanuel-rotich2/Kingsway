<?php

namespace App\API\Modules\finance;

use App\API\Includes\WorkflowHandler;
use App\API\Modules\finance\BudgetManager;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Budget Approval Workflow
 * 
 * Multi-stage workflow for budget approval
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. draft - Budget created
 * 2. departmental_review - Department head reviews
 * 3. finance_review - Finance team reviews
 * 4. director_approval - Director approves
 * 5. approved - Budget approved and active
 */
class BudgetApprovalWorkflow extends WorkflowHandler
{
    protected $workflowType = 'budget_approval';
    private $budgetManager;

    public function __construct()
    {
        parent::__construct('BUDGET_APPROVAL');
        $this->budgetManager = new BudgetManager();
    }

    /**
     * Initiate budget approval workflow
     * @param int $budgetId Budget ID
     * @param int $userId User initiating workflow
     * @param array $data Additional data
     * @return array Response
     */
    public function initiateBudgetApproval($budgetId, $userId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            // Verify budget exists
            $stmt = $this->db->prepare("
                SELECT id, name, total_amount, fiscal_year, department 
                FROM budgets 
                WHERE id = ?
            ");
            $stmt->execute([$budgetId]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Budget not found');
            }

            // Check for existing active workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.workflow_type = 'budget_approval'
                AND wi.status IN ('in_progress', 'pending')
                AND JSON_EXTRACT(wi.workflow_data, '$.budget_id') = ?
            ");
            $stmt->execute([$budgetId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active approval workflow already exists for this budget');
            }

            // Get budget line items count
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as item_count, SUM(amount) as total_amount
                FROM budget_line_items
                WHERE budget_id = ?
            ");
            $stmt->execute([$budgetId]);
            $lineItems = $stmt->fetch(PDO::FETCH_ASSOC);

            // Create workflow instance
            $workflowData = [
                'budget_id' => $budgetId,
                'budget_name' => $budget['name'],
                'total_amount' => $budget['total_amount'],
                'fiscal_year' => $budget['fiscal_year'],
                'department' => $budget['department'],
                'line_items_count' => $lineItems['item_count'],
                'initiated_by' => $userId,
                'initiated_at' => date('Y-m-d H:i:s')
            ];

            $instanceId = $this->startWorkflow(
                'budget',
                $budgetId,
                $workflowData
            );

            if (!$instanceId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Failed to create workflow instance');
            }

            // Advance to departmental review if department specified, otherwise finance review
            $nextStage = $budget['department'] ? 'departmental_review' : 'finance_review';
            $this->advanceStage($instanceId, $nextStage, 'submitted_for_review', [
                'notes' => $data['notes'] ?? 'Budget submitted for review'
            ]);

            // Update budget status
            $stmt = $this->db->prepare("
                UPDATE budgets 
                SET status = 'pending_approval'
                WHERE id = ?
            ");
            $stmt->execute([$budgetId]);

            $this->db->commit();

            return formatResponse(true, [
                'workflow_instance_id' => $instanceId,
                'message' => 'Budget approval workflow initiated successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to initiate workflow: ' . $e->getMessage());
        }
    }

    /**
     * Department head review
     * @param int $instanceId Workflow instance ID
     * @param int $userId Reviewing user ID
     * @param array $data Review data
     * @return array Response
     */
    public function departmentalReview($instanceId, $userId, $data)
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
            if ($instance['current_stage'] !== 'departmental_review') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in departmental review stage');
            }

            $action = $data['action'] ?? ''; // 'approve' or 'reject'

            if ($action === 'approve') {
                // Advance to finance review stage
                $this->advanceStage($instanceId, 'finance_review', 'department_approved', [
                    'notes' => $data['notes'] ?? 'Approved by department head',
                    'reviewed_by' => $userId
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Budget approved by department head']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected by department head');

                // Update budget status
                $workflowData = json_decode($instance['workflow_data'], true);
                $stmt = $this->db->prepare("
                    UPDATE budgets 
                    SET status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$workflowData['budget_id']]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Budget rejected by department head']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to review budget: ' . $e->getMessage());
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
            if ($instance['current_stage'] !== 'finance_review') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in finance review stage');
            }

            $action = $data['action'] ?? ''; // 'approve' or 'reject'

            if ($action === 'approve') {
                // Advance to director approval stage
                $this->advanceStage($instanceId, 'director_approval', 'finance_approved', [
                    'notes' => $data['notes'] ?? 'Approved by finance team',
                    'reviewed_by' => $userId
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Budget approved by finance team']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected by finance team');

                // Update budget status
                $workflowData = json_decode($instance['workflow_data'], true);
                $stmt = $this->db->prepare("
                    UPDATE budgets 
                    SET status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$workflowData['budget_id']]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Budget rejected by finance team']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to review budget: ' . $e->getMessage());
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
            if ($instance['current_stage'] !== 'director_approval') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in director approval stage');
            }

            $action = $data['action'] ?? ''; // 'approve' or 'reject'

            if ($action === 'approve') {
                // Approve budget
                $workflowData = json_decode($instance['workflow_data'], true);
                $stmt = $this->db->prepare("
                    UPDATE budgets 
                    SET status = 'approved',
                        approved_by = ?,
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $workflowData['budget_id']]);

                // Complete workflow
                $this->completeWorkflow($instanceId, [
                    'completed_by' => $userId,
                    'completed_at' => date('Y-m-d H:i:s'),
                    'outcome' => 'approved',
                    'approval_notes' => $data['notes'] ?? 'Budget approved by director'
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Budget approved by director']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected by director');

                // Update budget status
                $workflowData = json_decode($instance['workflow_data'], true);
                $stmt = $this->db->prepare("
                    UPDATE budgets 
                    SET status = 'rejected'
                    WHERE id = ?
                ");
                $stmt->execute([$workflowData['budget_id']]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Budget rejected by director']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to approve budget: ' . $e->getMessage());
        }
    }

    /**
     * Get workflow status
     * @param int $budgetId Budget ID
     * @return array Response with workflow status
     */
    public function getBudgetApprovalStatus($budgetId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       b.name as budget_name,
                       b.status as budget_status
                FROM workflow_instances wi
                INNER JOIN budgets b ON JSON_EXTRACT(wi.workflow_data, '$.budget_id') = b.id
                WHERE wi.workflow_type = 'budget_approval'
                AND JSON_EXTRACT(wi.workflow_data, '$.budget_id') = ?
                ORDER BY wi.created_at DESC
                LIMIT 1
            ");

            $stmt->execute([$budgetId]);
            $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$workflow) {
                return formatResponse(false, null, 'No approval workflow found');
            }

            // Get stage history
            $stmt = $this->db->prepare("
                SELECT * FROM workflow_stage_history
                WHERE instance_id = ?
                ORDER BY created_at ASC
            ");
            $stmt->execute([$workflow['id']]);
            $workflow['stage_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $workflow);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get workflow status: ' . $e->getMessage());
        }
    }

    /**
     * WRAPPER METHODS FOR FinanceAPI INTEGRATION
     * These methods provide a consistent interface expected by FinanceAPI
     */

    /**
     * Submit budget for departmental review (wrapper for initiateBudgetApproval)
     * 
     * @param int $id Budget ID
     * @param array $data Additional data
     * @param int $userId User initiating submission
     * @return array Response with status and data
     */
    public function submitForDepartmentalReview($id, $data, $userId)
    {
        // Map to existing initiateBudgetApproval method
        return $this->initiateBudgetApproval($id, $userId, $data);
    }

    /**
     * Approve budget at departmental level (wrapper for departmentalReview)
     * 
     * @param int $id Budget ID
     * @param array $data Approval data
     * @param int $userId User approving
     * @return array Response with status and data
     */
    public function approveDepartmental($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this budget');
            }

            return $this->departmentalReview($instanceId, $userId, $data);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to approve at departmental level: ' . $e->getMessage());
        }
    }

    /**
     * Approve budget at finance level (wrapper for financeReview)
     * 
     * @param int $id Budget ID
     * @param array $data Approval data
     * @param int $userId User approving
     * @return array Response with status and data
     */
    public function approveFinance($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this budget');
            }

            return $this->financeReview($instanceId, $userId, $data);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to approve at finance level: ' . $e->getMessage());
        }
    }

    /**
     * Approve budget at director level (wrapper for directorApproval)
     * 
     * @param int $id Budget ID
     * @param array $data Approval data
     * @param int $userId User approving
     * @return array Response with status and data
     */
    public function approveDirector($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this budget');
            }

            return $this->directorApproval($instanceId, $userId, $data);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to approve at director level: ' . $e->getMessage());
        }
    }

    /**
     * Reject budget at any stage
     * 
     * @param int $id Budget ID
     * @param array $data Rejection data (remarks required)
     * @param int $userId User rejecting
     * @return array Response with status and data
     */
    public function reject($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this budget');
            }

            // Get current workflow instance
            $stmt = $this->db->prepare("SELECT * FROM workflow_instances WHERE id = ?");
            $stmt->execute([$instanceId]);
            $instance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            // Cancel workflow
            $this->cancelWorkflow($instanceId, $data['remarks'] ?? 'Budget rejected');

            // Update budget status
            $stmt = $this->db->prepare("UPDATE budgets SET status = 'draft' WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('reject_budget', $id, "Budget ID $id rejected");

            return formatResponse(true, ['message' => 'Budget rejected']);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to reject budget: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to get workflow instance ID from budget ID
     * 
     * @param int $budgetId Budget ID
     * @return int|null Workflow instance ID or null if not found
     */
    private function getInstanceId($budgetId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM workflow_instances
                WHERE workflow_type = 'budget_approval'
                AND JSON_EXTRACT(workflow_data, '$.budget_id') = ?
                AND current_stage != 'completed'
                AND current_stage != 'rejected'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$budgetId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['id'] : null;

        } catch (Exception $e) {
            error_log("Failed to get instance ID: " . $e->getMessage());
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
        // Define permitted transitions for budget approval workflow
        $allowed = [
            'draft' => ['departmental_review'],
            'departmental_review' => ['finance_review', 'rejected'],
            'finance_review' => ['director_approval', 'rejected'],
            'director_approval' => ['completed', 'rejected'],
        ];

        // Always allow explicit rejection from any stage
        if ($toStage === 'rejected') {
            return true;
        }

        // If no fromStage (new instance), allow move to draft or departmental_review
        if (empty($fromStage)) {
            return in_array($toStage, ['draft', 'departmental_review']);
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
            $this->logAction('process_stage', $data['budget_id'] ?? null, "Processing budget workflow stage: {$stage}", $data);

            // Stage-specific actions
            if ($stage === 'departmental_review') {
                if (!empty($data['budget_id'])) {
                    $stmt = $this->db->prepare("UPDATE budgets SET status = 'pending_departmental_review' WHERE id = ?");
                    $stmt->execute([$data['budget_id']]);
                }
            } elseif ($stage === 'finance_review') {
                if (!empty($data['budget_id'])) {
                    $stmt = $this->db->prepare("UPDATE budgets SET status = 'pending_finance_review' WHERE id = ?");
                    $stmt->execute([$data['budget_id']]);
                }
            } elseif ($stage === 'director_approval') {
                if (!empty($data['budget_id'])) {
                    $stmt = $this->db->prepare("UPDATE budgets SET status = 'pending_director_approval' WHERE id = ?");
                    $stmt->execute([$data['budget_id']]);
                }
            } elseif ($stage === 'completed') {
                if (!empty($data['budget_id'])) {
                    $stmt = $this->db->prepare("UPDATE budgets SET status = 'approved', approved_at = NOW() WHERE id = ?");
                    $stmt->execute([$data['budget_id']]);
                }
            }

            return true;
        } catch (Exception $e) {
            error_log("Failed to process budget stage {$stage}: " . $e->getMessage());
            return false;
        }
    }
}
