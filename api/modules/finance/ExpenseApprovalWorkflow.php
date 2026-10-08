<?php

namespace App\API\Modules\finance;

use App\API\Includes\WorkflowHandler;
use App\API\Modules\finance\ExpenseManager;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\payments\SupplierDisbursementService;
use App\API\Services\ReadReplicaService;

/**
 * Expense Approval Workflow
 * 
 * Multi-stage workflow for expense approval
 * Extends WorkflowHandler for workflow management
 * 
 * Workflow Stages:
 * 1. submission - Expense submitted
 * 2. validation - Finance validates against budget
 * 3. approval - Manager/Director approves
 * 4. payment - Payment processed
 */
class ExpenseApprovalWorkflow extends WorkflowHandler
{
    protected $workflowType = 'expense_approval';
    private $expenseManager;

    public function __construct()
    {
        parent::__construct('EXPENSE_APPROVAL');
        $this->expenseManager = new ExpenseManager();
    }

    /**
     * Initiate expense approval workflow
     * @param int $expenseId Expense ID
     * @param int $userId User initiating workflow
     * @param array $data Additional data
     * @return array Response
     */
    public function initiateExpenseApproval($expenseId, $userId, $data = [])
    {
        try {
            $this->db->beginTransaction();

            // Verify expense exists
            $stmt = $this->db->prepare("
                SELECT e.*, 
                       ec.name AS category_name,
                       s.name AS vendor_name,
                       (bli.allocated_amount - bli.spent_amount - bli.committed_amount) AS available_balance
                FROM expenses e
                LEFT JOIN expense_categories ec ON e.category_id = ec.id
                LEFT JOIN suppliers s ON e.vendor_id = s.id
                LEFT JOIN budget_line_items bli ON e.budget_line_item_id = bli.id
                WHERE e.id = ?
            ");
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$expense) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Expense not found');
            }

            // Check for existing active workflow
            $stmt = $this->db->prepare("
                SELECT wi.* FROM workflow_instances wi
                WHERE wi.workflow_id = ?
                AND wi.status IN ('in_progress', 'pending')
                AND JSON_EXTRACT(wi.data_json, '$.expense_id') = ?
            ");
            $stmt->execute([$this->workflow_id, $expenseId]);

            if ($stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Active approval workflow already exists for this expense');
            }

            // Validate against budget
            $budgetValidation = 'valid';
            $validationNotes = '';

            if ($expense['budget_line_item_id']) {
                if ($expense['amount'] > $expense['available_balance']) {
                    $budgetValidation = 'exceeds_budget';
                    $validationNotes = 'Expense exceeds available budget balance';
                }
            }

            // Create workflow instance
            $workflowData = [
                'expense_id' => $expenseId,
                'description' => $expense['description'],
                'amount' => $expense['amount'],
                'category' => $expense['category_name'] ?? 'Uncategorized',
                'vendor' => $expense['vendor_name'] ?? '',
                'budget_validation' => $budgetValidation,
                'validation_notes' => $validationNotes,
                'initiated_by' => $userId,
                'initiated_at' => date('Y-m-d H:i:s')
            ];

            $instanceId = $this->startWorkflow(
                'expense',
                $expenseId,
                $workflowData
            );

            if (!$instanceId) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Failed to create workflow instance');
            }

            // Advance to validation stage
            $this->advanceStage($instanceId, 'validation', 'submitted_for_validation', [
                'notes' => $data['notes'] ?? 'Expense submitted for validation',
                'budget_validation' => $budgetValidation
            ]);

            // Update expense status
            $stmt = $this->db->prepare("
                UPDATE expenses 
                SET status = 'pending_approval'
                WHERE id = ?
            ");
            $stmt->execute([$expenseId]);

            $this->db->commit();

            return formatResponse(true, [
                'workflow_instance_id' => $instanceId,
                'budget_validation' => $budgetValidation,
                'message' => 'Expense approval workflow initiated successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Finance validation
     * @param int $instanceId Workflow instance ID
     * @param int $userId Validating user ID
     * @param array $data Validation data
     * @return array Response
     */
    public function financeValidation($instanceId, $userId, $data)
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
            if ($instance['current_stage'] !== 'validation') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in validation stage');
            }

            $action = $data['action'] ?? ''; // 'approve' or 'reject'

            if ($action === 'approve') {
                // Advance to approval stage
                $this->advanceStage($instanceId, 'approval', 'finance_validated', [
                    'notes' => $data['notes'] ?? 'Validated by finance team',
                    'validated_by' => $userId
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Expense validated by finance team']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected during validation');

                // Update expense status
                $workflowData = json_decode($instance['data_json'], true);
                $stmt = $this->db->prepare("
                    UPDATE expenses 
                    SET status = 'rejected',
                        rejected_by = ?,
                        rejected_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $userId,
                    $data['notes'] ?? 'Rejected during validation',
                    $workflowData['expense_id']
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Expense rejected during validation']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Manager/Director approval
     * @param int $instanceId Workflow instance ID
     * @param int $userId Approving user ID
     * @param array $data Approval data
     * @return array Response
     */
    public function managerApproval($instanceId, $userId, $data)
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
                // Advance to payment stage
                $this->advanceStage($instanceId, 'payment', $userId, [
                    'action' => 'manager_approved',
                    'notes' => $data['notes'] ?? 'Approved by manager',
                    'approved_by' => $userId,
                    'approved_at' => date('Y-m-d H:i:s')
                ]);

                // Update expense status
                $workflowData = json_decode($instance['data_json'], true);
                $stmt = $this->db->prepare("
                    UPDATE expenses 
                    SET status = 'approved',
                        approved_by = ?,
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$userId, $workflowData['expense_id']]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Expense approved, ready for payment']);

            } elseif ($action === 'reject') {
                // Reject and close workflow
                $this->cancelWorkflow($instanceId, $data['notes'] ?? 'Rejected by manager');

                // Update expense status
                $workflowData = json_decode($instance['data_json'], true);
                $stmt = $this->db->prepare("
                    UPDATE expenses 
                    SET status = 'rejected',
                        rejected_by = ?,
                        rejected_at = NOW(),
                        rejection_reason = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $userId,
                    $data['notes'] ?? 'Rejected by manager',
                    $workflowData['expense_id']
                ]);

                $this->db->commit();
                return formatResponse(true, ['message' => 'Expense rejected']);

            } else {
                $this->db->rollBack();
                return formatResponse(false, null, 'Invalid action. Use "approve" or "reject"');
            }

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Record payment
     * @param int $instanceId Workflow instance ID
     * @param int $userId User recording payment
     * @param array $data Payment data
     * @return array Response
     */
    public function recordPayment($instanceId, $userId, $data)
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
            if ($instance['current_stage'] !== 'payment') {
                $this->db->rollBack();
                return formatResponse(false, null, 'Workflow not in payment stage');
            }

            // Update expense with payment details
            $workflowData = json_decode($instance['data_json'], true);
            $expenseId = (int) ($workflowData['expense_id'] ?? 0);
            $method = strtolower((string) ($data['payment_method'] ?? 'cash'));
            $supplierPayment = null;
            if (in_array($method, ['bank_transfer', 'kcb_bank'], true)) {
                $supplierCheck = $this->db->prepare("SELECT vendor_id FROM " . ReadReplicaService::qualifiedRef("workflow_instances") . " id = ? LIMIT 1");
                $supplierCheck->execute([$expenseId]);
                $vendorId = $supplierCheck->fetchColumn();
                if ($vendorId) {
                    $supplierPayment = (new SupplierDisbursementService($this->db))->initiateExpensePayment($expenseId, (int) $userId, $data);
                }
            }
            $stmt = $this->db->prepare("
                UPDATE expenses 
                SET status = ?,
                    payment_method = ?,
                    reference_number = ?,
                    paid_at = IF(? = 'paid', NOW(), paid_at),
                    paid_by = ?
                WHERE id = ?
            ");
            $expenseStatus = $supplierPayment ? ($supplierPayment['status'] === 'pending' ? 'payment_pending' : 'approved') : 'paid';
            $stmt->execute([
                $expenseStatus,
                $method,
                $supplierPayment['transaction_ref'] ?? ($data['payment_reference'] ?? ''),
                $expenseStatus,
                $userId,
                $expenseId
            ]);

            // Complete workflow
            $this->completeWorkflow($instanceId, [
                'completed_by' => $userId,
                'completed_at' => date('Y-m-d H:i:s'),
                'outcome' => $supplierPayment ? 'payment_submitted' : 'paid',
                'payment_method' => $method,
                'payment_reference' => $supplierPayment['transaction_ref'] ?? ($data['payment_reference'] ?? '')
            ]);

            $this->db->commit();
            return formatResponse(true, ['message' => 'Payment recorded successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Get workflow status
     * @param int $expenseId Expense ID
     * @return array Response with workflow status
     */
    public function getExpenseApprovalStatus($expenseId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT wi.*, 
                       e.description as expense_description,
                       e.status as expense_status
                FROM " . ReadReplicaService::qualifiedRef("workflow_instances") . "
                INNER JOIN " . ReadReplicaService::qualifiedRef("expenses") . " e ON JSON_EXTRACT(wi.data_json, '$.expense_id') = e.id
                WHERE wi.workflow_id = ?
                AND JSON_EXTRACT(wi.data_json, '$.expense_id') = ?
                ORDER BY wi.started_at DESC
                LIMIT 1
            ");

            $stmt->execute([$this->workflow_id, $expenseId]);
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
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * WRAPPER METHODS FOR FinanceAPI INTEGRATION
     * These methods provide a consistent interface expected by FinanceAPI
     */

    /**
     * Submit expense for approval (wrapper for initiateExpenseApproval)
     * 
     * @param int $id Expense ID
     * @param array $data Additional data
     * @param int $userId User initiating submission
     * @return array Response with status and data
     */
    public function submitForApproval($id, $data, $userId)
    {
        // Map to existing initiateExpenseApproval method
        return $this->initiateExpenseApproval($id, $userId, $data);
    }

    /**
     * Approve expense (wrapper for managerApproval)
     * 
     * @param int $id Expense ID
     * @param array $data Approval data
     * @param int $userId User approving
     * @return array Response with status and data
     */
    public function approve($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this expense');
            }

            return $this->managerApproval($instanceId, $userId, $data);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Reject expense at any stage
     * 
     * @param int $id Expense ID
     * @param array $data Rejection data (remarks required)
     * @param int $userId User rejecting
     * @return array Response with status and data
     */
    public function reject($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this expense');
            }

            // Get current workflow instance
            $stmt = $this->db->prepare("SELECT * FROM workflow_instances WHERE id = ?");
            $stmt->execute([$instanceId]);
            $instance = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instance) {
                return formatResponse(false, null, 'Workflow instance not found');
            }

            // Cancel workflow
            $this->cancelWorkflow($instanceId, $data['remarks'] ?? 'Expense rejected');

            // Update expense status
            $stmt = $this->db->prepare("UPDATE expenses SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('reject_expense', $id, "Expense ID $id rejected");

            return formatResponse(true, ['message' => 'Expense rejected']);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Process payment for approved expense (wrapper for recordPayment)
     * 
     * @param int $id Expense ID
     * @param array $data Payment data
     * @param int $userId User processing payment
     * @return array Response with status and data
     */
    public function processPayment($id, $data, $userId)
    {
        try {
            $instanceId = $this->getInstanceId($id);

            if (!$instanceId) {
                return formatResponse(false, null, 'No active workflow found for this expense');
            }

            return $this->recordPayment($instanceId, $userId, $data);

        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ExpenseApprovalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Helper method to get workflow instance ID from expense ID
     * 
     * @param int $expenseId Expense ID
     * @return int|null Workflow instance ID or null if not found
     */
    private function getInstanceId($expenseId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM workflow_instances
                WHERE workflow_id = ?
                AND JSON_EXTRACT(data_json, '$.expense_id') = ?
                AND status IN ('pending', 'in_progress')
                ORDER BY started_at DESC
                LIMIT 1
            ");
            $stmt->execute([$this->workflow_id, $expenseId]);
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
        // Define permitted transitions for expense approval workflow
        $allowed = [
            'submission' => ['validation'],
            'validation' => ['approval', 'rejected'],
            'approval' => ['payment', 'rejected'],
            'payment' => ['completed'],
        ];

        // Always allow explicit rejection from any stage
        if ($toStage === 'rejected') {
            return true;
        }

        // If no fromStage (new instance), allow move to submission or validation
        if (empty($fromStage)) {
            return in_array($toStage, ['submission', 'validation']);
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
            $this->logAction('process_stage', $data['expense_id'] ?? null, "Processing expense workflow stage: {$stage}", $data);

            // Stage-specific actions
            if ($stage === 'validation') {
                if (!empty($data['expense_id'])) {
                    $stmt = $this->db->prepare("UPDATE expenses SET status = 'pending_approval' WHERE id = ?");
                    $stmt->execute([$data['expense_id']]);
                }
            } elseif ($stage === 'approval') {
                if (!empty($data['expense_id'])) {
                    $stmt = $this->db->prepare("UPDATE expenses SET status = 'pending_approval' WHERE id = ?");
                    $stmt->execute([$data['expense_id']]);
                }
            } elseif ($stage === 'payment') {
                if (!empty($data['expense_id'])) {
                    $stmt = $this->db->prepare("UPDATE expenses SET status = 'approved' WHERE id = ?");
                    $stmt->execute([$data['expense_id']]);
                }
            } elseif ($stage === 'completed') {
                if (!empty($data['expense_id'])) {
                    $stmt = $this->db->prepare("UPDATE expenses SET status = 'paid', paid_at = NOW() WHERE id = ?");
                    $stmt->execute([$data['expense_id']]);
                }
            }

            return true;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("Failed to process expense stage {$stage}: " . $e->getMessage());
            return false;
        }
    }
}
