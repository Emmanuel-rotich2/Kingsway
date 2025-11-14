<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Asset Disposal Workflow
 * 
 * Manages disposal of obsolete, damaged, or surplus assets
 * 
 * Workflow Stages:
 * 1. disposal_request - Submit disposal request
 * 2. condition_assessment - Assess asset condition
 * 3. valuation - Determine asset value
 * 4. disposal_method_selection - Select disposal method
 * 5. disposal_approval - Approve disposal
 * 6. disposal_execution - Execute disposal
 * 7. proceeds_recording/write_off_processing - Record proceeds or write off
 * 8. accounting_entry - Post accounting entries
 * 9. inventory_removal - Remove from inventory
 */
class AssetDisposalWorkflow extends WorkflowHandler
{
    protected $workflowType = 'asset_disposal';

    /**
     * Disposal methods
     */
    const METHOD_SALE = 'sale';
    const METHOD_DONATION = 'donation';
    const METHOD_SCRAP = 'scrap';
    const METHOD_RECYCLING = 'recycling';
    const METHOD_WRITE_OFF = 'write_off';
    const METHOD_TRADE_IN = 'trade_in';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('asset_disposal');
    }

    /**
     * Initiate asset disposal workflow
     * @param array $data Disposal request data
     * @param int $userId User initiating request
     * @return array Response
     */
    public function initiateDisposal($data, $userId)
    {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['asset_ids', 'disposal_reason', 'suggested_method'];
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

            // Validate assets exist and are available for disposal
            $assetIds = $data['asset_ids'];
            $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
            $stmt = $this->db->prepare("
                SELECT id, item_name, status, book_value 
                FROM inventory_items 
                WHERE id IN ($placeholders) AND status NOT IN ('disposed', 'in_disposal')
            ");
            $stmt->execute($assetIds);
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($assets) !== count($assetIds)) {
                $this->rollback();
                return formatResponse(false, null, 'Some assets are not available for disposal');
            }

            // Create disposal record
            $stmt = $this->db->prepare("
                INSERT INTO asset_disposals (
                    requested_by, disposal_date, disposal_reason,
                    suggested_method, status, total_book_value, created_at
                ) VALUES (?, NOW(), ?, ?, 'pending', ?, NOW())
            ");

            $totalBookValue = array_sum(array_column($assets, 'book_value'));

            $stmt->execute([
                $userId,
                $data['disposal_reason'],
                $data['suggested_method'],
                $totalBookValue
            ]);

            $disposalId = $this->db->lastInsertId();

            // Link assets to disposal
            foreach ($assetIds as $assetId) {
                $stmt = $this->db->prepare("
                    INSERT INTO disposal_assets (disposal_id, asset_id)
                    VALUES (?, ?)
                ");
                $stmt->execute([$disposalId, $assetId]);

                // Mark assets as in disposal
                $stmt = $this->db->prepare("UPDATE inventory_items SET status = 'in_disposal' WHERE id = ?");
                $stmt->execute([$assetId]);
            }

            // Start workflow
            $workflowData = [
                'disposal_id' => $disposalId,
                'asset_ids' => $assetIds,
                'assets' => $assets,
                'total_book_value' => $totalBookValue,
                'disposal_reason' => $data['disposal_reason'],
                'suggested_method' => $data['suggested_method'],
                'supporting_documents' => $data['supporting_documents'] ?? []
            ];

            // Start workflow - returns instance_id (int)
            $instance_id = $this->startWorkflow('disposal', $disposalId, $workflowData);

            // Update disposal with workflow instance ID
            $stmt = $this->db->prepare("
                UPDATE asset_disposals 
                SET workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$instance_id, $disposalId]);

            $this->commit();
            $this->logAction('create', $instance_id, "Initiated disposal workflow for {$disposalId}");

            return formatResponse(true, [
                'workflow_id' => $instance_id,
                'disposal_id' => $disposalId,
                'asset_count' => count($assetIds),
                'total_book_value' => $totalBookValue,
                'current_stage' => 'disposal_request'
            ], 'Disposal workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Assess asset condition (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Assessment data
     * @return array Response
     */
    public function assessCondition($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'condition_assessment') {
                return formatResponse(false, null, "Cannot assess condition. Current stage is: {$currentStage}");
            }

            // Validate assessment data
            $required = ['condition_rating', 'assessment_notes'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update disposal record
            $stmt = $this->db->prepare("
                UPDATE asset_disposals 
                SET condition_rating = ?, condition_assessment_notes = ?,
                    assessed_by = ?, assessed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['condition_rating'],
                $data['assessment_notes'],
                $userId,
                $workflowData['disposal_id']
            ]);

            // Update workflow data
            $workflowData['condition_assessment'] = [
                'assessed_by' => $userId,
                'assessed_at' => date('Y-m-d H:i:s'),
                'condition_rating' => $data['condition_rating'],
                'assessment_notes' => $data['assessment_notes'],
                'photos' => $data['photos'] ?? []
            ];

            $this->advanceStage(
                $workflowId,
                'valuation',
                'condition_assessed',
                $workflowData
            );

            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                "Asset condition assessed: {$data['condition_rating']}"
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Perform asset valuation (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Valuation data
     * @return array Response
     */
    public function performValuation($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'valuation') {
                return formatResponse(false, null, "Cannot perform valuation. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update disposal record
            $stmt = $this->db->prepare("
                UPDATE asset_disposals 
                SET estimated_value = ?, valuation_method = ?,
                    valuation_notes = ?, valuated_by = ?, valuated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['estimated_value'],
                $data['valuation_method'] ?? 'market_value',
                $data['valuation_notes'] ?? null,
                $userId,
                $workflowData['disposal_id']
            ]);

            // Update workflow data
            $workflowData['valuation'] = [
                'valuated_by' => $userId,
                'valuated_at' => date('Y-m-d H:i:s'),
                'estimated_value' => $data['estimated_value'],
                'valuation_method' => $data['valuation_method'] ?? 'market_value',
                'valuation_notes' => $data['valuation_notes'] ?? null,
                'depreciation_considered' => $data['depreciation_considered'] ?? true
            ];

            $this->advanceStage(
                $workflowId,
                'disposal_method_selection',
                'valuation_completed',
                $workflowData
            );

            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                "Asset valued at KES " . number_format($data['estimated_value'], 2)
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Select disposal method (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Method selection data
     * @return array Response
     */
    public function selectDisposalMethod($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'disposal_method_selection') {
                return formatResponse(false, null, "Cannot select method. Current stage is: {$currentStage}");
            }

            $validMethods = [
                self::METHOD_SALE,
                self::METHOD_DONATION,
                self::METHOD_SCRAP,
                self::METHOD_RECYCLING,
                self::METHOD_WRITE_OFF,
                self::METHOD_TRADE_IN
            ];

            if (!in_array($data['disposal_method'], $validMethods)) {
                return formatResponse(false, null, 'Invalid disposal method');
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update disposal record
            $stmt = $this->db->prepare("
                UPDATE asset_disposals 
                SET disposal_method = ?, method_selection_reason = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['disposal_method'],
                $data['selection_reason'] ?? null,
                $workflowData['disposal_id']
            ]);

            // Update workflow data
            $workflowData['disposal_method_selection'] = [
                'selected_by' => $userId,
                'selected_at' => date('Y-m-d H:i:s'),
                'disposal_method' => $data['disposal_method'],
                'selection_reason' => $data['selection_reason'] ?? null,
                'buyer_info' => $data['buyer_info'] ?? null,
                'expected_proceeds' => $data['expected_proceeds'] ?? 0
            ];

            $this->advanceStage(
                $workflowId,
                'disposal_approval',
                'method_selected',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                "Disposal method selected: {$data['disposal_method']}"
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Approve disposal (Stage 5)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Approval data
     * @return array Response
     */
    public function approveDisposal($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'disposal_approval') {
                return formatResponse(false, null, "Cannot approve disposal. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $totalBookValue = $workflowData['total_book_value'];

            // Check approval authority based on asset value
            $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRole = $user['role'] ?? '';

            $approvalLevels = [
                'inventory_manager' => 20000,
                'director' => 100000,
                'board' => PHP_INT_MAX
            ];

                return formatResponse(false, null, "You do not have authority to approve this disposal (Book value: KES " . number_format($totalBookValue, 2) . ")");
            }

            // Update disposal record
            $stmt = $this->db->prepare("
                UPDATE asset_disposals 
                SET status = 'approved', approved_by = ?, approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $userId,
                $data['approval_notes'] ?? null,
                $workflowData['disposal_id']
            ]);

            // Update workflow data
            $workflowData['disposal_approval'] = [
                'approved_by' => $userId,
                'approved_by_role' => $userRole,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $data['approval_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'disposal_execution',
                'disposal_approved',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['approval_notes'] ?? 'Disposal approved'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Execute disposal (Stage 6)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Execution data
     * @return array Response
     */
    public function executeDisposal($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'disposal_execution') {
                return formatResponse(false, null, "Cannot execute disposal. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $disposalMethod = $workflowData['disposal_method_selection']['disposal_method'];

            // Update disposal record
            $stmt = $this->db->prepare("
                UPDATE asset_disposals 
                SET execution_date = ?, executed_by = ?, execution_notes = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['execution_date'] ?? date('Y-m-d'),
                $userId,
                $data['execution_notes'] ?? null,
                $workflowData['disposal_id']
            ]);

            // Update workflow data
            $workflowData['disposal_execution'] = [
                'executed_by' => $userId,
                'execution_date' => $data['execution_date'] ?? date('Y-m-d'),
                'execution_notes' => $data['execution_notes'] ?? null,
                'receipts' => $data['receipts'] ?? []
            ];

            // Determine next stage based on disposal method
            $nextStage = in_array($disposalMethod, [self::METHOD_SALE, self::METHOD_TRADE_IN])
                ? 'proceeds_recording'
                : 'write_off_processing';

            $this->advanceStage(
                $workflowId,
                $nextStage,
                'disposal_executed',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                "Disposal executed via {$disposalMethod}"
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
            'disposal_request' => ['condition_assessment', 'rejected'],
            'condition_assessment' => ['valuation', 'disposal_request'],
            'valuation' => ['disposal_method_selection', 'condition_assessment'],
            'disposal_method_selection' => ['disposal_approval', 'valuation'],
            'disposal_approval' => ['disposal_execution', 'disposal_method_selection', 'rejected'],
            'disposal_execution' => ['proceeds_recording', 'write_off_processing'],
            'proceeds_recording' => ['accounting_entry'],
            'write_off_processing' => ['accounting_entry'],
            'accounting_entry' => ['inventory_removal'],
            'inventory_removal' => ['completed']
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
                case 'disposal_request':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Disposal request submitted");
                    }
                    return ['success' => true, 'message' => 'Disposal request submitted', 'next_stage' => 'condition_assessment'];

                case 'condition_assessment':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Asset condition assessed");
                    }
                    return ['success' => true, 'message' => 'Condition assessed', 'next_stage' => 'valuation'];

                case 'valuation':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Asset valuation completed");
                    }
                    return ['success' => true, 'message' => 'Asset valued', 'next_stage' => 'disposal_method_selection'];

                case 'disposal_method_selection':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Disposal method selected");
                    }
                    return ['success' => true, 'message' => 'Method selected', 'next_stage' => 'disposal_approval'];

                case 'disposal_approval':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Disposal approved");
                    }
                    return ['success' => true, 'message' => 'Disposal approved', 'next_stage' => 'disposal_execution'];

                case 'disposal_execution':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Disposal executed");
                    }
                    return ['success' => true, 'message' => 'Disposal completed', 'next_stage' => 'proceeds_recording'];

                case 'proceeds_recording':
                case 'write_off_processing':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Financial processing completed");
                    }
                    return ['success' => true, 'message' => 'Financial records updated', 'next_stage' => 'accounting_entry'];

                case 'accounting_entry':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Accounting entries posted");
                    }
                    return ['success' => true, 'message' => 'Accounting complete', 'next_stage' => 'inventory_removal'];

                case 'inventory_removal':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Assets removed from inventory");
                    }
                    return ['success' => true, 'message' => 'Inventory updated', 'next_stage' => null];

                default:
                    return ['success' => false, 'message' => "Unknown stage: {$stage}"];
            }
        } catch (Exception $e) {
            $this->logError('processStage', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
