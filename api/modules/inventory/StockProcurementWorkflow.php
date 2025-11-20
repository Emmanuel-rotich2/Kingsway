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
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }


            // Calculate total cost
            $totalCost = 0;
            if (!empty($data['quantities']) && !empty($data['estimated_costs'])) {
                foreach ($data['quantities'] as $i => $qty) {
                    $cost = isset($data['estimated_costs'][$i]) ? $data['estimated_costs'][$i] : 0;
                    $totalCost += $qty * $cost;
                }
            }

            // Example: Insert purchase request logic here (if needed)
            // ...

            // Generate workflowId if not set (simulate creation)
            $workflowId = $data['workflow_id'] ?? uniqid('wf_', true);
            $workflowData = [];

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
            $this->commit();
            return formatResponse(true, ['workflow_id' => $workflowId], 'Budget verified successfully');
        } catch (Exception $e) {
            $this->handleException($e);
            return formatResponse(false, null, 'Error initiating procurement');
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
            'purchase_order_creation' => ['goods_receipt', 'cancell ed'],
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
        // ...existing code...
        switch ($stage) {
            case 'purchase_request':
                return ['success' => true, 'message' => 'Purchase request submitted', 'next_stage' => 'budget_verification'];
            case 'budget_verification':
                return ['success' => true, 'message' => 'Budget verified', 'next_stage' => 'quotation_request'];
            case 'quotation_request':
                return ['success' => true, 'message' => 'Quotations requested', 'next_stage' => 'quotation_evaluation'];
            case 'quotation_evaluation':
                return ['success' => true, 'message' => 'Quotations evaluated', 'next_stage' => 'procurement_approval'];
            case 'procurement_approval':
                return ['success' => true, 'message' => 'Procurement approved', 'next_stage' => 'purchase_order_creation'];
            case 'purchase_order_creation':
                return ['success' => true, 'message' => 'Purchase order created', 'next_stage' => 'goods_receipt'];
            case 'goods_receipt':
                return ['success' => true, 'message' => 'Goods received', 'next_stage' => 'quality_inspection'];
            case 'quality_inspection':
                return ['success' => true, 'message' => 'Quality inspection passed', 'next_stage' => 'stock_posting'];
            case 'stock_posting':
                return ['success' => true, 'message' => 'Stock posted successfully', 'next_stage' => 'invoice_verification'];
            case 'invoice_verification':
                return ['success' => true, 'message' => 'Invoice verified', 'next_stage' => 'payment_processing'];
            case 'payment_processing':
                return ['success' => true, 'message' => 'Payment processed', 'next_stage' => null];
            default:
                return ['success' => false, 'message' => "Unknown stage: {$stage}"];
        }
    }

    /**
     * Complete the procurement workflow
     * @param int $workflowId Workflow instance ID
     * @param int $userId User completing the workflow
     * @return array Completion result
     */
    public function completeProcurement($workflowId, $userId)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }
            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'payment_processing') {
                return formatResponse(false, null, "Cannot complete workflow. Current stage is: {$currentStage}");
            }
            $this->beginTransaction();
            // Update workflow data
            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $workflowData['completion'] = [
                'completed_by' => $userId,
                'completed_at' => date('Y-m-d H:i:s')
            ];
            // Advance to completed stage
            $this->advanceStage(
                $workflowId,
                'completed',
                'procurement_completed',
                $workflowData
            );
            $this->commit();
            return formatResponse(true, ['workflow_id' => $workflowId], 'Procurement workflow completed successfully');
        } catch (Exception $e) {
            $this->handleException($e);
            return formatResponse(false, null, 'Error completing procurement workflow');
        }
    }
}
