<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Stock Procurement Workflow
 * 
 * Complete purchase-to-pay cycle for acquiring new inventory
 * 
 * Workflow Stages:
 * 1. purchase_request - Submit purchase request
 * 2. budget_verification - Verify budget availability
 * 3. quotation_request - Request supplier quotations
 * 4. quotation_evaluation - Evaluate and select supplier
 * 5. procurement_approval - Approve procurement decision
 * 6. purchase_order_creation - Create and send PO
 * 7. goods_receipt - Receive goods
 * 8. quality_inspection - Inspect received goods
 * 9. goods_return - Return rejected items (if needed)
 * 10. stock_posting - Post stock to inventory
 * 11. invoice_verification - Verify supplier invoice
 * 12. payment_processing - Process payment
 */
class StockProcurementWorkflow extends WorkflowHandler
{
    protected $workflowType = 'stock_procurement';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('stock_procurement');
    }

    /**
     * Initiate stock procurement workflow
     * @param array $data Purchase request data
     * @param int $userId User initiating request
     * @return array Response
     */
    public function initiateProcurement($data, $userId)
    {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['items', 'quantities', 'estimated_costs', 'justification'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                $this->rollback();
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Create requisition record
            $stmt = $this->db->prepare("
                INSERT INTO inventory_requisitions (
                    requested_by, requisition_date, status, justification, 
                    requisition_type, created_at
                ) VALUES (?, NOW(), 'pending', ?, 'procurement', NOW())
            ");
            $stmt->execute([
                $userId,
                $data['justification']
            ]);

            $requisitionId = $this->db->lastInsertId();

            // Start workflow
            $workflowData = [
                'requisition_id' => $requisitionId,
                'items' => $data['items'],
                'quantities' => $data['quantities'],
                'estimated_costs' => $data['estimated_costs'],
                'total_estimated_cost' => array_sum($data['estimated_costs']),
                'supplier_suggestions' => $data['supplier_suggestions'] ?? null,
                'justification' => $data['justification'],
                'urgency' => $data['urgency'] ?? 'normal'
            ];

            // Start workflow - returns instance_id (int)
            $instance_id = $this->startWorkflow('requisition', $requisitionId, $workflowData);

            // Update requisition with workflow instance ID
            $stmt = $this->db->prepare("
                UPDATE inventory_requisitions 
                SET workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$instance_id, $requisitionId]);

            $this->commit();
            $this->logAction('create', $instance_id, "Initiated procurement workflow for requisition #{$requisitionId}");

            return formatResponse(true, [
                'workflow_id' => $instance_id,
                'requisition_id' => $requisitionId,
                'current_stage' => 'purchase_request',
                'status' => 'pending'
            ], 'Procurement workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Verify budget availability (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Budget verification data
     * @return array Response
     */
    public function verifyBudget($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'budget_verification') {
                return formatResponse(false, null, "Cannot verify budget. Current stage is: {$currentStage}");
            }

            // Get workflow data
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $totalCost = $workflowData['total_estimated_cost'];

            // Check budget using stored procedure
            $stmt = $this->db->prepare("CALL sp_check_budget_utilization(?, ?, @available, @utilized, @remaining)");
            $stmt->execute([
                $data['budget_id'] ?? null,
                $totalCost
            ]);

            // Get results
            $stmt = $this->db->query("SELECT @available as available, @utilized as utilized, @remaining as remaining");
            $budgetCheck = $stmt->fetch(PDO::FETCH_ASSOC);

            $budgetAvailable = ($budgetCheck['remaining'] >= $totalCost);

            if (!$budgetAvailable && empty($data['override'])) {
                return formatResponse(false, [
                    'budget_available' => $budgetCheck['available'],
                    'budget_utilized' => $budgetCheck['utilized'],
                    'budget_remaining' => $budgetCheck['remaining'],
                    'required_amount' => $totalCost,
                    'deficit' => $totalCost - $budgetCheck['remaining']
                ], 'Insufficient budget available');
            }

            // Update workflow data
            $workflowData['budget_verification'] = [
                'verified_by' => $userId,
                'verified_at' => date('Y-m-d H:i:s'),
                'budget_id' => $data['budget_id'] ?? null,
                'budget_available' => $budgetCheck['available'],
                'budget_remaining' => $budgetCheck['remaining'],
                'approved' => true,
                'override_reason' => $data['override_reason'] ?? null
            ];

            // Prepare action data
            $actionData = array_merge($workflowData, [
                'remarks' => $data['remarks'] ?? 'Budget verified and approved'
            ]);

            // Advance to quotation_request stage
            $this->advanceStage(
                $workflowId,
                'quotation_request',
                'budget_verified',
                $actionData
            );

            return formatResponse(true, ['workflow_id' => $workflowId], 'Budget verified successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Request quotations from suppliers (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Quotation request data
     * @return array Response
     */
    public function requestQuotations($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'quotation_request') {
                return formatResponse(false, null, "Cannot request quotations. Current stage is: {$currentStage}");
            }

            // Validate minimum suppliers
            if (count($data['supplier_ids']) < 3) {
                return formatResponse(false, null, 'Minimum 3 suppliers required for quotation');
            }

            $this->beginTransaction();

            // Get workflow data
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $requisitionId = $workflowData['requisition_id'];

            // Create quotation requests for each supplier
            $quotationIds = [];
            foreach ($data['supplier_ids'] as $supplierId) {
                $stmt = $this->db->prepare("
                    INSERT INTO purchase_quotations (
                        requisition_id, supplier_id, quotation_date,
                        items, validity_days, status, created_at
                    ) VALUES (?, ?, NOW(), ?, ?, 'pending', NOW())
                ");
                $stmt->execute([
                    $requisitionId,
                    $supplierId,
                    json_encode($workflowData['items']),
                    $data['validity_days'] ?? 30
                ]);
                $quotationIds[] = $this->db->lastInsertId();
            }

            // Update workflow data
            $workflowData['quotation_request'] = [
                'requested_by' => $userId,
                'requested_at' => date('Y-m-d H:i:s'),
                'supplier_ids' => $data['supplier_ids'],
                'quotation_ids' => $quotationIds,
                'rfq_details' => $data['rfq_details'] ?? null,
                'deadline' => $data['deadline'] ?? null
            ];

            // Advance to quotation_evaluation stage
            $this->advanceStage(
                $workflowId,
                'quotation_evaluation',
                'quotations_requested',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Quotations requested from ' . count($data['supplier_ids']) . ' suppliers'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Evaluate quotations and select supplier (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Evaluation data
     * @return array Response
     */
    public function evaluateQuotations($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'quotation_evaluation') {
                return formatResponse(false, null, "Cannot evaluate quotations. Current stage is: {$currentStage}");
            }

            // Validate required fields
            $required = ['selected_quotation_id', 'evaluation_notes'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->beginTransaction();

            // Update selected quotation
            $stmt = $this->db->prepare("
                UPDATE purchase_quotations 
                SET status = 'selected', evaluation_notes = ?, evaluation_score = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['evaluation_notes'],
                $data['evaluation_score'] ?? null,
                $data['selected_quotation_id']
            ]);

            // Mark others as rejected
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $quotationIds = $workflowData['quotation_request']['quotation_ids'] ?? [];

            foreach ($quotationIds as $qId) {
                if ($qId != $data['selected_quotation_id']) {
                    $stmt = $this->db->prepare("UPDATE purchase_quotations SET status = 'rejected' WHERE id = ?");
                    $stmt->execute([$qId]);
                }
            }

            // Get selected quotation details
            $stmt = $this->db->prepare("SELECT * FROM purchase_quotations WHERE id = ?");
            $stmt->execute([$data['selected_quotation_id']]);
            $selectedQuotation = $stmt->fetch(PDO::FETCH_ASSOC);

            // Update workflow data
            $workflowData['quotation_evaluation'] = [
                'evaluated_by' => $userId,
                'evaluated_at' => date('Y-m-d H:i:s'),
                'selected_quotation_id' => $data['selected_quotation_id'],
                'selected_supplier_id' => $selectedQuotation['supplier_id'],
                'evaluation_criteria' => $data['evaluation_criteria'] ?? null,
                'evaluation_notes' => $data['evaluation_notes']
            ];

            // Advance to procurement_approval stage
            $this->advanceStage(
                $workflowId,
                'procurement_approval',
                'quotation_evaluated',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Supplier selected based on evaluation'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Approve procurement (Stage 5)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Approval data
     * @return array Response
     */
    public function approveProcurement($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'procurement_approval') {
                return formatResponse(false, null, "Cannot approve procurement. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $totalCost = $workflowData['total_estimated_cost'];

            // Check approval authority
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRole = $user['role'] ?? '';

            $approvalLevels = [
                'inventory_manager' => 50000,
                'director' => 200000,
                'board' => PHP_INT_MAX
            ];

                return formatResponse(false, null, "You do not have authority to approve this procurement amount (KES " . number_format($totalCost, 2) . ")");
            }

            // Update workflow data
            $workflowData['procurement_approval'] = [
                'approved_by' => $userId,
                'approved_by_role' => $userRole,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $data['approval_notes'] ?? null
            ];

            // Advance to purchase_order_creation stage
            $this->advanceStage(
                $workflowId,
                'purchase_order_creation',
                'procurement_approved',
                $workflowData
            );

            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['approval_notes'] ?? 'Procurement approved'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create purchase order (Stage 6)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data PO data
     * @return array Response
     */
    public function createPurchaseOrder($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'purchase_order_creation') {
                return formatResponse(false, null, "Cannot create PO. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $requisitionId = $workflowData['requisition_id'];
            $quotationId = $workflowData['quotation_evaluation']['selected_quotation_id'];
            $supplierId = $workflowData['quotation_evaluation']['selected_supplier_id'];

            // Generate PO number
            $poNumber = 'PO-' . date('Y') . '-' . str_pad($this->db->query("SELECT COUNT(*) + 1 FROM purchase_orders")->fetchColumn(), 6, '0', STR_PAD_LEFT);

            // Create purchase order
            $stmt = $this->db->prepare("
                INSERT INTO purchase_orders (
                    po_number, requisition_id, supplier_id, quotation_id,
                    order_date, expected_delivery_date, total_amount,
                    payment_terms, delivery_address, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, 'sent', ?, NOW())
            ");
            $stmt->execute([
                $poNumber,
                $requisitionId,
                $supplierId,
                $quotationId,
                $data['expected_delivery_date'],
                $workflowData['total_estimated_cost'],
                $data['payment_terms'] ?? 'Net 30',
                $data['delivery_address'] ?? 'School Main Store',
                $userId
            ]);

            $poId = $this->db->lastInsertId();

            // Create PO items
            foreach ($workflowData['items'] as $index => $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO purchase_order_items (
                        po_id, item_id, item_description, quantity, 
                        unit_price, total_price
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $poId,
                    $item['id'] ?? null,
                    $item['description'],
                    $workflowData['quantities'][$index],
                    $workflowData['estimated_costs'][$index],
                    $workflowData['quantities'][$index] * $workflowData['estimated_costs'][$index]
                ]);
            }

            // Update workflow data
            $workflowData['purchase_order'] = [
                'po_id' => $poId,
                'po_number' => $poNumber,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'expected_delivery_date' => $data['expected_delivery_date']
            ];

            // Advance to goods_receipt stage
            $this->advanceStage(
                $workflowId,
                'goods_receipt',
                'purchase_order_created',
                $workflowData
            );

            $this->commit();
            return formatResponse(true, [
                'workflow_id' => $workflowId,
                'po_id' => $poId,
                'po_number' => $poNumber
            ], "Purchase Order {$poNumber} created and sent to supplier");

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Validate workflow stage transition
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        $validTransitions = [
            'purchase_request' => ['budget_verification', 'rejected'],
            'budget_verification' => ['quotation_request', 'purchase_request', 'rejected'],
            'quotation_request' => ['quotation_evaluation', 'budget_verification'],
            'quotation_evaluation' => ['procurement_approval', 'quotation_request'],
            'procurement_approval' => ['purchase_order_creation', 'quotation_evaluation', 'rejected'],
            'purchase_order_creation' => ['goods_receipt', 'cancelled'],
            'goods_receipt' => ['quality_inspection', 'goods_receipt'],
            'quality_inspection' => ['stock_posting', 'goods_return'],
            'goods_return' => ['supplier_debit_note', 'quotation_request'],
            'stock_posting' => ['invoice_verification', 'cancelled'],
            'invoice_verification' => ['payment_processing', 'stock_posting'],
            'payment_processing' => ['completed']
        ];

        if (!isset($validTransitions[$fromStage])) {
            return false;
        }

        return in_array($toStage, $validTransitions[$fromStage]);
    }

    /**
     * Process a workflow stage
     * @param string $stage Stage code
     * @param array $data Stage data
     * @param int $instance_id Workflow instance ID
     * @return array Processing result
     */
    protected function processStage($stage, $data, $instance_id = null)
    {
        try {
            $response = ['success' => false, 'message' => "Unknown stage: {$stage}"];
            $logMessage = null;

            switch ($stage) {
                case 'purchase_request':
                    $logMessage = "Purchase request submitted";
                    $response = ['success' => true, 'message' => 'Purchase request submitted', 'next_stage' => 'budget_verification'];
                    break;

                case 'budget_verification':
                    $logMessage = "Budget verification completed";
                    $response = ['success' => true, 'message' => 'Budget verified', 'next_stage' => 'quotation_request'];
                    break;

                case 'quotation_request':
                    $logMessage = "Quotations requested";
                    $response = ['success' => true, 'message' => 'Quotations requested', 'next_stage' => 'quotation_evaluation'];
                    break;

                case 'quotation_evaluation':
                    $logMessage = "Quotations evaluated";
                    $response = ['success' => true, 'message' => 'Quotations evaluated', 'next_stage' => 'procurement_approval'];
                    break;

                case 'procurement_approval':
                    $logMessage = "Procurement approved";
                    $response = ['success' => true, 'message' => 'Procurement approved', 'next_stage' => 'purchase_order_creation'];
                    break;

                case 'purchase_order_creation':
                    $logMessage = "Purchase order created";
                    $response = ['success' => true, 'message' => 'Purchase order created', 'next_stage' => 'goods_receipt'];
                    break;

                case 'goods_receipt':
                    $logMessage = "Goods received";
                    $response = ['success' => true, 'message' => 'Goods received', 'next_stage' => 'quality_inspection'];
                    break;

                case 'quality_inspection':
                    $logMessage = "Quality inspection completed";
                    $response = ['success' => true, 'message' => 'Quality inspection passed', 'next_stage' => 'stock_posting'];
                    break;

                case 'stock_posting':
                    $logMessage = "Stock posted to inventory";
                    $response = ['success' => true, 'message' => 'Stock posted successfully', 'next_stage' => 'invoice_verification'];
                    break;

                case 'invoice_verification':
                    $logMessage = "Invoice verified";
                    $response = ['success' => true, 'message' => 'Invoice verified', 'next_stage' => 'payment_processing'];
                    break;

                case 'payment_processing':
                    $logMessage = "Payment processed";
                    $response = ['success' => true, 'message' => 'Payment processed', 'next_stage' => null];
                    break;

                default:
                    // response already indicates unknown stage
                    break;
            }

            if ($instance_id && $logMessage) {
                $this->logAction('update', $instance_id, $logMessage);
            }

            return $response;
        } catch (Exception $e) {
            $this->logError('processStage', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
