<?php
namespace App\API\Modules\inventory;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

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
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['asset_ids', 'disposal_reason', 'suggested_method'];
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

            $assetIds = array_values(array_unique(array_map('intval', (array) $data['asset_ids'])));
            if (empty($assetIds)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No valid assets provided');
            }

            // Map suggested disposal method to the live disposal_type enum
            $methodMap = [
                self::METHOD_SALE => 'sale',
                self::METHOD_TRADE_IN => 'sale',
                self::METHOD_DONATION => 'donation',
                self::METHOD_SCRAP => 'scrap',
                self::METHOD_RECYCLING => 'scrap',
                self::METHOD_WRITE_OFF => 'write_off'
            ];
            $disposalType = $methodMap[$data['suggested_method']] ?? 'sale';

            // Validate assets exist and are available for disposal
            $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
            $stmt = $this->db->prepare("
                SELECT id, name, status, current_book_value
                FROM fixed_assets 
                WHERE id IN ($placeholders) AND status NOT IN ('disposed', 'written_off')
            ");
            $stmt->execute($assetIds);
            $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($assets) !== count($assetIds)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Some assets are not available for disposal');
            }

            // Create one disposal record per asset
            $disposalIds = [];
            $totalBookValue = 0;
            $insert = $this->db->prepare("
                INSERT INTO asset_disposals (
                    asset_id, disposal_date, disposal_type,
                    book_value_at_disposal, reason, authorised_by, created_at
                ) VALUES (?, NOW(), ?, ?, ?, ?, NOW())
            ");
            foreach ($assets as $asset) {
                $bookValue = (float) $asset['current_book_value'];
                $insert->execute([
                    $asset['id'],
                    $disposalType,
                    $bookValue,
                    $data['disposal_reason'],
                    $userId
                ]);
                $disposalIds[] = (int) $this->db->lastInsertId();
                $totalBookValue += $bookValue;
            }

            $disposalId = $disposalIds[0];

            // Start workflow
            $workflowData = [
                'disposal_id' => $disposalId,
                'disposal_ids' => $disposalIds,
                'asset_ids' => $assetIds,
                'assets' => $assets,
                'total_book_value' => $totalBookValue,
                'disposal_reason' => $data['disposal_reason'],
                'suggested_method' => $data['suggested_method'],
                'supporting_documents' => $data['supporting_documents'] ?? []
            ];

            // Start workflow - returns instance_id (int)
            $instance_id = $this->startWorkflow('disposal', $disposalId, $workflowData);

            $this->db->commit();
            $this->logAction('create', $instance_id, "Initiated disposal workflow for {$disposalId}");

            return formatResponse(true, [
                'workflow_id' => $instance_id,
                'disposal_id' => $disposalId,
                'disposal_ids' => $disposalIds,
                'asset_count' => count($assetIds),
                'total_book_value' => $totalBookValue,
                'current_stage' => 'disposal_request'
            ], 'Disposal workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
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
            $stmt = $this->db->prepare("
                SELECT r.name AS role
                FROM " . ReadReplicaService::masterRef("user_role_grant") . " 
                WHERE ur.user_id = ? AND r.is_active = 1
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRole = $user['role'] ?? '';

            $approvalLevels = [
                'Director' => 100000,
                'System Administrator' => PHP_INT_MAX
            ];

            // Check if user has authority
            if (!isset($approvalLevels[$userRole]) || $totalBookValue > $approvalLevels[$userRole]) {
                return formatResponse(false, null, "You do not have authority to approve this disposal (Book value: KES " . number_format($totalBookValue, 2) . ")");
            }

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
            $disposalMethod = $workflowData['disposal_method_selection']['disposal_method'] ?? $workflowData['suggested_method'] ?? self::METHOD_WRITE_OFF;
            $assetIds = $workflowData['asset_ids'] ?? [];
            $disposalIds = $workflowData['disposal_ids'] ?? [];

            // Persist execution results on the disposal record(s)
            if (!empty($disposalIds)) {
                $sets = [];
                $vals = [];
                if (isset($data['proceeds'])) {
                    $sets[] = 'proceeds = ?';
                    $vals[] = $data['proceeds'];
                }
                if (isset($data['buyer_name'])) {
                    $sets[] = 'buyer_name = ?';
                    $vals[] = $data['buyer_name'];
                }
                if (!empty($sets)) {
                    $placeholders = implode(',', array_fill(0, count($disposalIds), '?'));
                    $stmt = $this->db->prepare("UPDATE asset_disposals SET " . implode(', ', $sets) . " WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge($vals, $disposalIds));
                }
            }

            // Mark assets as disposed or written off
            if (!empty($assetIds)) {
                $status = $disposalMethod === self::METHOD_WRITE_OFF ? 'written_off' : 'disposed';
                $placeholders = implode(',', array_fill(0, count($assetIds), '?'));
                $stmt = $this->db->prepare("UPDATE fixed_assets SET status = ?, updated_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute(array_merge([$status], $assetIds));
            }

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
            \App\API\Services\Logger::legacyError('[AssetDisposalWorkflow] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

}              
