<?php
namespace App\API\Modules\inventory;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Stock Transfer Workflow
 * 
 * Manages transfer of stock between locations/departments
 * 
 * Workflow Stages:
 * 1. transfer_request - Submit transfer request
 * 2. transfer_approval - Approve transfer
 * 3. stock_picking - Pick items from source
 * 4. quality_check - Check quality before dispatch
 * 5. dispatch - Dispatch items
 * 6. in_transit - Items in transit
 * 7. goods_receipt_destination - Receive at destination
 * 8. receiving_inspection - Inspect received items
 * 9. discrepancy_resolution - Resolve any discrepancies
 * 10. stock_posting - Post stock movements
 */
class StockTransferWorkflow extends WorkflowHandler
{
    protected $workflowType = 'stock_transfer';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('stock_transfer');
    }

    /**
     * Initiate stock transfer workflow
     * @param array $data Transfer request data
     * @param int $userId User initiating request
     * @return array Response
     */
    public function initiateTransfer($data, $userId)
    {
        try {
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['source_location_id', 'destination_location_id', 'items', 'quantities', 'transfer_reason'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Validate source and destination are different
            if ($data['source_location_id'] == $data['destination_location_id']) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Source and destination locations must be different');
            }

            // Validate stock availability at source
            foreach ($data['items'] as $index => $itemId) {
                $quantity = $data['quantities'][$index];

                $stmt = $this->db->prepare("
                    SELECT quantity_on_hand 
                    FROM inventory_items 
                    WHERE id = ? AND location_id = ?
                ");
                $stmt->execute([$itemId, $data['source_location_id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item || $item['quantity_on_hand'] < $quantity) {
                    $this->db->rollBack();
                    return formatResponse(false, null, "Insufficient stock for item #{$itemId} at source location");
                }
            }

            // Generate transfer number
            $transferNumber = 'TR-' . date('Y') . '-' . str_pad($this->db->query("SELECT COUNT(*) + 1 FROM inventory_transfers")->fetchColumn(), 6, '0', STR_PAD_LEFT);

            // Create transfer record
            $stmt = $this->db->prepare("
                INSERT INTO inventory_transfers (
                    transfer_number, source_location_id, destination_location_id,
                    requested_by, transfer_date, transfer_reason, status, created_at
                ) VALUES (?, ?, ?, ?, NOW(), ?, 'pending', NOW())
            ");
            $stmt->execute([
                $transferNumber,
                $data['source_location_id'],
                $data['destination_location_id'],
                $userId,
                $data['transfer_reason']
            ]);

            $transferId = $this->db->lastInsertId();

            // Create transfer items
            foreach ($data['items'] as $index => $itemId) {
                $stmt = $this->db->prepare("
                    INSERT INTO inventory_transfer_items (
                        transfer_id, item_id, quantity_requested
                    ) VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $transferId,
                    $itemId,
                    $data['quantities'][$index]
                ]);
            }

            // Start workflow
            $workflowData = [
                'transfer_id' => $transferId,
                'transfer_number' => $transferNumber,
                'source_location_id' => $data['source_location_id'],
                'destination_location_id' => $data['destination_location_id'],
                'items' => $data['items'],
                'quantities' => $data['quantities'],
                'transfer_reason' => $data['transfer_reason'],
                'urgency' => $data['urgency'] ?? 'normal',
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null
            ];

            $result = $this->startWorkflow('stock_transfer', $transferId, $userId, $workflowData);

            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }

            // Update transfer with workflow ID
            $stmt = $this->db->prepare("
                UPDATE inventory_transfers 
                SET workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$result['data']['workflow_id'], $transferId]);

            $this->db->commit();
            $this->logAction('create', $result['data']['workflow_id'], "Initiated transfer workflow {$transferNumber}");

            return formatResponse(true, [
                'workflow_id' => $result['data']['workflow_id'],
                'transfer_id' => $transferId,
                'transfer_number' => $transferNumber,
                'current_stage' => 'transfer_request'
            ], 'Transfer workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Approve transfer (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Approval data
     * @return array Response
     */
    public function approveTransfer($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'transfer_approval') {
                return formatResponse(false, null, "Cannot approve transfer. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update transfer record
            $stmt = $this->db->prepare("
                UPDATE inventory_transfers 
                SET status = 'approved', approved_by = ?, approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $userId,
                $data['approval_notes'] ?? null,
                $workflowData['transfer_id']
            ]);

            // Update workflow data
            $workflowData['transfer_approval'] = [
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $data['approval_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'stock_picking',
                'transfer_approved',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['approval_notes'] ?? 'Transfer approved'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Pick stock from source (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Picking data
     * @return array Response
     */
    public function pickStock($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'stock_picking') {
                return formatResponse(false, null, "Cannot pick stock. Current stage is: {$currentStage}");
            }

            $this->db->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $transferId = $workflowData['transfer_id'];

            // Update picked quantities
            foreach ($data['picked_quantities'] as $itemId => $pickedQty) {
                $stmt = $this->db->prepare("
                    UPDATE inventory_transfer_items 
                    SET quantity_picked = ?, picked_by = ?, picked_at = NOW()
                    WHERE transfer_id = ? AND item_id = ?
                ");
                $stmt->execute([
                    $pickedQty,
                    $userId,
                    $transferId,
                    $itemId
                ]);
            }

            // Update transfer status
            $stmt = $this->db->prepare("
                UPDATE inventory_transfers 
                SET picking_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transferId]);

            // Update workflow data
            $workflowData['stock_picking'] = [
                'picked_by' => $userId,
                'picked_at' => date('Y-m-d H:i:s'),
                'picked_quantities' => $data['picked_quantities'],
                'picking_notes' => $data['picking_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'quality_check',
                'stock_picked',
                $workflowData
            );

            $this->db->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Stock picked from source location'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Perform quality check before dispatch (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Quality check data
     * @return array Response
     */
    public function performQualityCheck($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'quality_check') {
                return formatResponse(false, null, "Cannot perform quality check. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update workflow data
            $workflowData['quality_check'] = [
                'checked_by' => $userId,
                'checked_at' => date('Y-m-d H:i:s'),
                'quality_status' => $data['quality_status'] ?? 'passed',
                'check_notes' => $data['check_notes'] ?? null,
                'defects_found' => $data['defects_found'] ?? []
            ];

            $this->advanceStage(
                $workflowId,
                'dispatch',
                'quality_checked',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Quality check completed'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Dispatch items (Stage 5)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Dispatch data
     * @return array Response
     */
    public function dispatchItems($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'dispatch') {
                return formatResponse(false, null, "Cannot dispatch. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update transfer record
            $stmt = $this->db->prepare("
                UPDATE inventory_transfers 
                SET status = 'dispatched', dispatch_date = NOW(),
                    dispatched_by = ?, carrier_info = ?, tracking_number = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $userId,
                $data['carrier_info'] ?? null,
                $data['tracking_number'] ?? null,
                $workflowData['transfer_id']
            ]);

            // Update workflow data
            $workflowData['dispatch'] = [
                'dispatched_by' => $userId,
                'dispatch_date' => date('Y-m-d H:i:s'),
                'carrier_info' => $data['carrier_info'] ?? null,
                'tracking_number' => $data['tracking_number'] ?? null,
                'dispatch_notes' => $data['dispatch_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'in_transit',
                'items_dispatched',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Items dispatched to destination'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Receive goods at destination (Stage 7)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Receipt data
     * @return array Response
     */
    public function receiveGoods($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'goods_receipt_destination') {
                return formatResponse(false, null, "Cannot receive goods. Current stage is: {$currentStage}");
            }

            $this->db->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $transferId = $workflowData['transfer_id'];

            // Update received quantities
            foreach ($data['received_quantities'] as $itemId => $receivedQty) {
                $stmt = $this->db->prepare("
                    UPDATE inventory_transfer_items 
                    SET quantity_received = ?, received_by = ?, received_at = NOW()
                    WHERE transfer_id = ? AND item_id = ?
                ");
                $stmt->execute([
                    $receivedQty,
                    $userId,
                    $transferId,
                    $itemId
                ]);
            }

            // Update transfer status
            $stmt = $this->db->prepare("
                UPDATE inventory_transfers 
                SET status = 'received', received_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transferId]);

            // Update workflow data
            $workflowData['goods_receipt'] = [
                'received_by' => $userId,
                'received_at' => date('Y-m-d H:i:s'),
                'received_quantities' => $data['received_quantities'],
                'receipt_notes' => $data['receipt_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'receiving_inspection',
                'goods_received',
                $workflowData
            );

            $this->db->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Goods received at destination'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Inspect received goods (Stage 8)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Inspection data
     * @return array Response
     */
    public function inspectReceivedGoods($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'receiving_inspection') {
                return formatResponse(false, null, "Cannot inspect goods. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Check for discrepancies
            $hasDiscrepancies = false;
            $discrepancies = [];

            $pickedQty = $workflowData['stock_picking']['picked_quantities'] ?? [];
            $receivedQty = $workflowData['goods_receipt']['received_quantities'] ?? [];

            foreach ($pickedQty as $itemId => $picked) {
                $received = $receivedQty[$itemId] ?? 0;
                if ($picked != $received) {
                    $hasDiscrepancies = true;
                    $discrepancies[] = [
                        'item_id' => $itemId,
                        'picked' => $picked,
                        'received' => $received,
                        'variance' => $received - $picked
                    ];
                }
            }

            // Update workflow data
            $workflowData['receiving_inspection'] = [
                'inspected_by' => $userId,
                'inspected_at' => date('Y-m-d H:i:s'),
                'inspection_status' => $data['inspection_status'] ?? 'passed',
                'inspection_notes' => $data['inspection_notes'] ?? null,
                'discrepancies' => $discrepancies
            ];

            $nextStage = $hasDiscrepancies ? 'discrepancy_resolution' : 'stock_posting';

            $this->advanceStage(
                $workflowId,
                $nextStage,
                'inspection_completed',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $hasDiscrepancies ? 'Discrepancies found - resolution required' : 'Inspection passed'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Validate workflow stage transition
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        $validTransitions = [
            'transfer_request' => ['transfer_approval', 'rejected'],
            'transfer_approval' => ['stock_picking', 'transfer_request'],
            'stock_picking' => ['quality_check', 'cancelled'],
            'quality_check' => ['dispatch', 'stock_picking'],
            'dispatch' => ['in_transit'],
            'in_transit' => ['goods_receipt_destination'],
            'goods_receipt_destination' => ['receiving_inspection'],
            'receiving_inspection' => ['discrepancy_resolution', 'stock_posting'],
            'discrepancy_resolution' => ['stock_posting', 'receiving_inspection'],
            'stock_posting' => ['completed']
        ];

        if (!isset($validTransitions[$fromStage])) {
            return false;
        }

        return in_array($toStage, $validTransitions[$fromStage]);
    }

    /**
     * Process a workflow stage
     */
    protected function processStage($stage, $data, $instance_id = null)
    {
        try {
            switch ($stage) {
                case 'transfer_request':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Transfer request submitted");
                    }
                    return ['success' => true, 'message' => 'Transfer requested', 'next_stage' => 'transfer_approval'];

                case 'transfer_approval':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Transfer approved");
                    }
                    return ['success' => true, 'message' => 'Transfer approved', 'next_stage' => 'stock_picking'];

                case 'stock_picking':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Stock picked");
                    }
                    return ['success' => true, 'message' => 'Stock picked', 'next_stage' => 'quality_check'];

                case 'quality_check':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Quality checked");
                    }
                    return ['success' => true, 'message' => 'Quality verified', 'next_stage' => 'dispatch'];

                case 'dispatch':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Items dispatched");
                    }
                    return ['success' => true, 'message' => 'Items dispatched', 'next_stage' => 'in_transit'];

                case 'in_transit':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Items in transit");
                    }
                    return ['success' => true, 'message' => 'In transit', 'next_stage' => 'goods_receipt_destination'];

                case 'goods_receipt_destination':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Goods received");
                    }
                    return ['success' => true, 'message' => 'Goods received', 'next_stage' => 'receiving_inspection'];

                case 'receiving_inspection':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Inspection completed");
                    }
                    return ['success' => true, 'message' => 'Inspection complete', 'next_stage' => 'stock_posting'];

                case 'discrepancy_resolution':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Discrepancies resolved");
                    }
                    return ['success' => true, 'message' => 'Discrepancies resolved', 'next_stage' => 'stock_posting'];

                case 'stock_posting':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Stock movements posted");
                    }
                    return ['success' => true, 'message' => 'Stock posted', 'next_stage' => null];

                default:
                    return ['success' => false, 'message' => "Unknown stage: {$stage}"];
            }
        } catch (Exception $e) {
            $this->logError('processStage', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
