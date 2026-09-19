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

            // Lock source rows before checking and decrementing stock. The
            // generated quantity_on_hand column mirrors current_quantity.
            $lockedItems = [];
            $requestedItems = [];
            foreach ($data['items'] as $index => $itemId) {
                $requestedItems[] = [
                    'item_id' => (int) $itemId,
                    'quantity' => (int) ($data['quantities'][$index] ?? 0)
                ];
            }
            usort($requestedItems, static function (array $left, array $right): int {
                return $left['item_id'] <=> $right['item_id'];
            });

            foreach ($requestedItems as $requestedItem) {
                $itemId = $requestedItem['item_id'];
                $quantity = $requestedItem['quantity'];
                if ($quantity <= 0) {
                    $this->db->rollBack();
                    return formatResponse(false, null, "Quantity for item #{$itemId} must be positive");
                }

                $stmt = $this->db->prepare("
                    SELECT id, current_quantity
                    FROM inventory_items 
                    WHERE id = ? AND location_id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->execute([$itemId, $data['source_location_id']]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item || (int) $item['current_quantity'] < $quantity) {
                    $this->db->rollBack();
                    return formatResponse(false, null, "Insufficient stock for item #{$itemId} at source location");
                }
                $lockedItems[] = ['item_id' => (int) $itemId, 'quantity' => $quantity];
            }

            // Transfer ledger: insert the first row without a reference, use its
            // database-generated ID as the transfer identity, then attach it to
            // the first row and all subsequent rows.
            $outStmt = $this->db->prepare("
                INSERT INTO inventory_transactions (
                    item_id, transaction_type, quantity, transaction_date,
                    reference_type, reference_id, notes
                ) VALUES (?, 'out', ?, CURDATE(), 'transfer', ?, ?)
            ");
            $decStmt = $this->db->prepare(
                "UPDATE inventory_items
                 SET current_quantity = current_quantity - ?
                 WHERE id = ? AND current_quantity >= ?"
            );
            $transferId = null;
            foreach ($lockedItems as $lockedItem) {
                $itemId = $lockedItem['item_id'];
                $quantity = $lockedItem['quantity'];
                $decStmt->execute([$quantity, $itemId, $quantity]);
                if ($decStmt->rowCount() !== 1) {
                    throw new Exception("Stock changed while transferring item #{$itemId}");
                }
                $outStmt->execute([
                    $itemId,
                    $quantity,
                    null,
                    'Transfer from ' . $data['source_location_id'] . ' to ' . $data['destination_location_id']
                ]);
                if ($transferId === null) {
                    $transferId = (int) $this->db->lastInsertId();
                    $this->db->prepare(
                        "UPDATE inventory_transactions
                         SET reference_id = ?, notes = ?
                         WHERE id = ?"
                    )->execute([
                        $transferId,
                        'Transfer ' . $transferId . ' from ' . $data['source_location_id'] .
                            ' to ' . $data['destination_location_id'],
                        $transferId
                    ]);
                } else {
                    $this->db->prepare(
                        "UPDATE inventory_transactions SET reference_id = ? WHERE id = ?"
                    )->execute([$transferId, (int) $this->db->lastInsertId()]);
                }
            }
            $transferNumber = 'TR-' . date('Y') . '-' . str_pad((string) $transferId, 6, '0', STR_PAD_LEFT);

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

            // Record approval in the workflow data; ledger rows were posted at initiation.
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

            // Post 'in' transactions at the destination and move the item location
            $inStmt = $this->db->prepare("
                INSERT INTO inventory_transactions (
                    item_id, transaction_type, quantity, transaction_date,
                    reference_type, reference_id, notes
                ) VALUES (?, 'in', ?, CURDATE(), 'transfer', ?, ?)
            ");
            $incStmt = $this->db->prepare(
                "UPDATE inventory_items
                 SET current_quantity = current_quantity + ?,
                     location_id = ?
                 WHERE id = ?"
            );
            foreach ($data['received_quantities'] as $itemId => $receivedQty) {
                $inStmt->execute([
                    $itemId,
                    $receivedQty,
                    $transferId,
                    'Transfer received at ' . $workflowData['destination_location_id']
                ]);
                $incStmt->execute([$receivedQty, $workflowData['destination_location_id'], $itemId]);
            }

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
            \App\API\Services\Logger::legacyError('[StockTransferWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }
}
