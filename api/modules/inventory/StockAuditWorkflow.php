<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\WorkflowHandler;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Stock Audit Workflow
 * 
 * Manages physical inventory audits and reconciliation
 * 
 * Workflow Stages:
 * 1. audit_planning - Plan audit scope and team
 * 2. audit_scheduling - Schedule audit date/time
 * 3. count_preparation - Prepare for physical count
 * 4. physical_count - Perform physical count
 * 5. count_verification - Verify count accuracy
 * 6. variance_analysis - Analyze variances
 * 7. variance_investigation - Investigate discrepancies
 * 8. recount - Recount if necessary
 * 9. adjustment_proposal - Propose adjustments
 * 10. reconciliation_approval - Approve adjustments
 * 11. adjustment_posting - Post inventory adjustments
 * 12. audit_report_generation - Generate final report
 */
class StockAuditWorkflow extends WorkflowHandler
{
    protected $workflowType = 'stock_audit';

    /**
     * Audit types
     */
    const TYPE_FULL_AUDIT = 'full_audit';
    const TYPE_CYCLE_COUNT = 'cycle_count';
    const TYPE_SPOT_CHECK = 'spot_check';
    const TYPE_CATEGORY_AUDIT = 'category_audit';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('stock_audit');
    }

    /**
     * Initiate stock audit workflow
     * @param array $data Audit planning data
     * @param int $userId User initiating audit
     * @return array Response
     */
    public function initiateAudit($data, $userId)
    {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['audit_type', 'audit_scope', 'planned_date'];
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

            // Validate audit type
            $validTypes = [
                self::TYPE_FULL_AUDIT,
                self::TYPE_CYCLE_COUNT,
                self::TYPE_SPOT_CHECK,
                self::TYPE_CATEGORY_AUDIT
            ];

            if (!in_array($data['audit_type'], $validTypes)) {
                $this->rollback();
                return formatResponse(false, null, 'Invalid audit type');
            }

            // Generate audit number
            $auditNumber = 'AUD-' . date('Y') . '-' . str_pad($this->db->query("SELECT COUNT(*) + 1 FROM stock_audits")->fetchColumn(), 6, '0', STR_PAD_LEFT);

            // Create audit record
            $stmt = $this->db->prepare("
                INSERT INTO stock_audits (
                    audit_number, audit_type, audit_scope, planned_by,
                    planned_date, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'planning', NOW())
            ");
            $stmt->execute([
                $auditNumber,
                $data['audit_type'],
                json_encode($data['audit_scope']),
                $userId,
                $data['planned_date']
            ]);

            $auditId = $this->db->lastInsertId();

            // Start workflow
            $workflowData = [
                'audit_id' => $auditId,
                'audit_number' => $auditNumber,
                'audit_type' => $data['audit_type'],
                'audit_scope' => $data['audit_scope'],
                'planned_date' => $data['planned_date'],
                'team_members' => $data['team_members'] ?? [],
                'locations' => $data['locations'] ?? [],
                'categories' => $data['categories'] ?? [],
                'items' => $data['items'] ?? []
            ];

            $result = $this->startWorkflow('stock_audit', $auditId, $userId, $workflowData);

            if (!$result['success']) {
                $this->rollback();
                return $result;
            }

            // Update audit with workflow ID
            $stmt = $this->db->prepare("
                UPDATE stock_audits 
                SET workflow_instance_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$result['data']['workflow_id'], $auditId]);

            $this->commit();
            $this->logAction('create', $result['data']['workflow_id'], "Initiated audit workflow {$auditNumber}");

            return formatResponse(true, [
                'workflow_id' => $result['data']['workflow_id'],
                'audit_id' => $auditId,
                'audit_number' => $auditNumber,
                'audit_type' => $data['audit_type'],
                'current_stage' => 'audit_planning'
            ], 'Audit workflow initiated successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Schedule audit (Stage 2)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Scheduling data
     * @return array Response
     */
    public function scheduleAudit($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'audit_scheduling') {
                return formatResponse(false, null, "Cannot schedule audit. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update audit record
            $stmt = $this->db->prepare("
                UPDATE stock_audits 
                SET scheduled_date = ?, scheduled_by = ?, 
                    team_lead = ?, status = 'scheduled'
                WHERE id = ?
            ");
            $stmt->execute([
                $data['scheduled_date'],
                $userId,
                $data['team_lead'] ?? $userId,
                $workflowData['audit_id']
            ]);

            // Update workflow data
            $workflowData['audit_scheduling'] = [
                'scheduled_by' => $userId,
                'scheduled_at' => date('Y-m-d H:i:s'),
                'scheduled_date' => $data['scheduled_date'],
                'team_lead' => $data['team_lead'] ?? $userId,
                'team_members' => $data['team_members'] ?? [],
                'scheduling_notes' => $data['scheduling_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'count_preparation',
                'audit_scheduled',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Audit scheduled for ' . $data['scheduled_date']
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Prepare for physical count (Stage 3)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Preparation data
     * @return array Response
     */
    public function prepareCount($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'count_preparation') {
                return formatResponse(false, null, "Cannot prepare count. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $auditId = $workflowData['audit_id'];

            // Generate count sheets based on audit scope
            $items = $this->getAuditItems($workflowData);

            // Create count records
            foreach ($items as $item) {
                $stmt = $this->db->prepare("
                    INSERT INTO audit_count_sheets (
                        audit_id, item_id, location_id, system_quantity,
                        created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $auditId,
                    $item['id'],
                    $item['location_id'],
                    $item['quantity_on_hand']
                ]);
            }

            // Update audit status
            $stmt = $this->db->prepare("
                UPDATE stock_audits 
                SET status = 'ready', preparation_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$auditId]);

            // Update workflow data
            $workflowData['count_preparation'] = [
                'prepared_by' => $userId,
                'prepared_at' => date('Y-m-d H:i:s'),
                'total_items' => count($items),
                'count_sheets_generated' => true,
                'preparation_notes' => $data['preparation_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'physical_count',
                'count_prepared',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                count($items) . ' items ready for counting'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Perform physical count (Stage 4)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Count data
     * @return array Response
     */
    public function performPhysicalCount($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'physical_count') {
                return formatResponse(false, null, "Cannot perform count. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $auditId = $workflowData['audit_id'];

            // Update count records with physical counts
            foreach ($data['counts'] as $itemId => $physicalQty) {
                $stmt = $this->db->prepare("
                    UPDATE audit_count_sheets 
                    SET physical_quantity = ?, counted_by = ?, count_date = NOW()
                    WHERE audit_id = ? AND item_id = ?
                ");
                $stmt->execute([
                    $physicalQty,
                    $userId,
                    $auditId,
                    $itemId
                ]);
            }

            // Update audit status
            $stmt = $this->db->prepare("
                UPDATE stock_audits 
                SET status = 'counting', count_start_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$auditId]);

            // Update workflow data
            $workflowData['physical_count'] = [
                'counted_by' => $userId,
                'count_date' => date('Y-m-d H:i:s'),
                'items_counted' => count($data['counts']),
                'count_notes' => $data['count_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'count_verification',
                'count_completed',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Physical count completed for ' . count($data['counts']) . ' items'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Verify count accuracy (Stage 5)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Verification data
     * @return array Response
     */
    public function verifyCount($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'count_verification') {
                return formatResponse(false, null, "Cannot verify count. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update workflow data
            $workflowData['count_verification'] = [
                'verified_by' => $userId,
                'verified_at' => date('Y-m-d H:i:s'),
                'verification_status' => $data['verification_status'] ?? 'verified',
                'verification_notes' => $data['verification_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'variance_analysis',
                'count_verified',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                'Count verification completed'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Analyze variances (Stage 6)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Analysis data
     * @return array Response
     */
    public function analyzeVariances($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'variance_analysis') {
                return formatResponse(false, null, "Cannot analyze variances. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $auditId = $workflowData['audit_id'];

            // Get variances from count sheets
            $stmt = $this->db->prepare("
                SELECT item_id, system_quantity, physical_quantity,
                       (physical_quantity - system_quantity) as variance,
                       ((physical_quantity - system_quantity) / NULLIF(system_quantity, 0) * 100) as variance_percentage
                FROM audit_count_sheets
                WHERE audit_id = ? AND physical_quantity != system_quantity
            ");
            $stmt->execute([$auditId]);
            $variances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Categorize variances
            $significantVariances = [];
            $minorVariances = [];
            $varianceThreshold = $data['variance_threshold'] ?? 5; // 5% threshold

            foreach ($variances as $variance) {
                if (abs($variance['variance_percentage']) > $varianceThreshold) {
                    $significantVariances[] = $variance;
                } else {
                    $minorVariances[] = $variance;
                }
            }

            // Update workflow data
            $workflowData['variance_analysis'] = [
                'analyzed_by' => $userId,
                'analyzed_at' => date('Y-m-d H:i:s'),
                'total_variances' => count($variances),
                'significant_variances' => count($significantVariances),
                'minor_variances' => count($minorVariances),
                'variance_threshold' => $varianceThreshold,
                'variances' => $variances
            ];

            // Determine next stage
            $nextStage = count($significantVariances) > 0 ? 'variance_investigation' : 'adjustment_proposal';

            $this->advanceStage(
                $workflowId,
                $nextStage,
                'variances_analyzed',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                count($variances) . ' variances found (' . count($significantVariances) . ' significant)'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Approve adjustments (Stage 10)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Approval data
     * @return array Response
     */
    public function approveAdjustments($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'reconciliation_approval') {
                return formatResponse(false, null, "Cannot approve adjustments. Current stage is: {$currentStage}");
            }

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];

            // Update workflow data
            $workflowData['reconciliation_approval'] = [
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
                'approval_notes' => $data['approval_notes'] ?? null
            ];

            $this->advanceStage(
                $workflowId,
                'adjustment_posting',
                'adjustments_approved',
                $workflowData
            );
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $data['approval_notes'] ?? 'Adjustments approved'
            );

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Post inventory adjustments (Stage 11)
     * @param int $workflowId Workflow instance ID
     * @param int $userId User performing action
     * @param array $data Posting data
     * @return array Response
     */
    public function postAdjustments($workflowId, $userId, $data)
    {
        try {
            $workflow = $this->getWorkflowInstance($workflowId);
            if (!$workflow['success']) {
                return $workflow;
            }

            $currentStage = $workflow['data']['current_stage'];
            if ($currentStage !== 'adjustment_posting') {
                return formatResponse(false, null, "Cannot post adjustments. Current stage is: {$currentStage}");
            }

            $this->beginTransaction();

            $workflowData = json_decode($workflow['data']['workflow_data'], true) ?? [];
            $auditId = $workflowData['audit_id'];

            // Get approved variances
            $stmt = $this->db->prepare("
                SELECT item_id, system_quantity, physical_quantity,
                       (physical_quantity - system_quantity) as variance
                FROM audit_count_sheets
                WHERE audit_id = ? AND physical_quantity != system_quantity
            ");
            $stmt->execute([$auditId]);
            $adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $adjustmentCount = 0;

            // Post adjustments to inventory
            foreach ($adjustments as $adjustment) {
                if ($adjustment['variance'] != 0) {
                    $stmt = $this->db->prepare("
                        UPDATE inventory_items 
                        SET quantity_on_hand = quantity_on_hand + ?,
                            last_audit_date = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $adjustment['variance'],
                        $adjustment['item_id']
                    ]);
                    $adjustmentCount++;
                }
            }

            // Update audit status
            $stmt = $this->db->prepare("
                UPDATE stock_audits 
                SET status = 'completed', completion_date = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$auditId]);

            // Update workflow data
            $workflowData['adjustment_posting'] = [
                'posted_by' => $userId,
                'posted_at' => date('Y-m-d H:i:s'),
                'adjustments_posted' => $adjustmentCount
            ];

            $this->advanceStage(
                $workflowId,
                'audit_report_generation',
                'adjustments_posted',
                $workflowData
            );

            $this->commit();
            return formatResponse(
                true,
                ['workflow_id' => $workflowId],
                $adjustmentCount . ' inventory adjustments posted'
            );

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Get items to audit based on scope
     * @param array $workflowData Workflow data
     * @return array Items
     */
    private function getAuditItems($workflowData)
    {
        $auditType = $workflowData['audit_type'];
        $scope = $workflowData['audit_scope'];

        $query = "SELECT id, item_name, location_id, quantity_on_hand FROM inventory_items WHERE 1=1";
        $params = [];

        if ($auditType === self::TYPE_FULL_AUDIT) {
            // All items
        } elseif ($auditType === self::TYPE_CATEGORY_AUDIT && !empty($workflowData['categories'])) {
            $placeholders = implode(',', array_fill(0, count($workflowData['categories']), '?'));
            $query .= " AND category_id IN ($placeholders)";
            $params = $workflowData['categories'];
        } elseif ($auditType === self::TYPE_CYCLE_COUNT && !empty($workflowData['locations'])) {
            $placeholders = implode(',', array_fill(0, count($workflowData['locations']), '?'));
            $query .= " AND location_id IN ($placeholders)";
            $params = $workflowData['locations'];
        } elseif ($auditType === self::TYPE_SPOT_CHECK && !empty($workflowData['items'])) {
            $placeholders = implode(',', array_fill(0, count($workflowData['items']), '?'));
            $query .= " AND id IN ($placeholders)";
            $params = $workflowData['items'];
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Validate workflow stage transition
     */
    protected function validateTransition($fromStage, $toStage, $data)
    {
        $validTransitions = [
            'audit_planning' => ['audit_scheduling', 'cancelled'],
            'audit_scheduling' => ['count_preparation', 'audit_planning'],
            'count_preparation' => ['physical_count', 'audit_scheduling'],
            'physical_count' => ['count_verification', 'count_preparation'],
            'count_verification' => ['variance_analysis', 'recount'],
            'variance_analysis' => ['variance_investigation', 'adjustment_proposal'],
            'variance_investigation' => ['recount', 'adjustment_proposal'],
            'recount' => ['count_verification'],
            'adjustment_proposal' => ['reconciliation_approval', 'variance_investigation'],
            'reconciliation_approval' => ['adjustment_posting', 'adjustment_proposal'],
            'adjustment_posting' => ['audit_report_generation'],
            'audit_report_generation' => ['completed']
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
                case 'audit_planning':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Audit planned");
                    }
                    return ['success' => true, 'message' => 'Audit planned', 'next_stage' => 'audit_scheduling'];

                case 'audit_scheduling':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Audit scheduled");
                    }
                    return ['success' => true, 'message' => 'Audit scheduled', 'next_stage' => 'count_preparation'];

                case 'count_preparation':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Count prepared");
                    }
                    return ['success' => true, 'message' => 'Count sheets ready', 'next_stage' => 'physical_count'];

                case 'physical_count':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Physical count completed");
                    }
                    return ['success' => true, 'message' => 'Count completed', 'next_stage' => 'count_verification'];

                case 'count_verification':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Count verified");
                    }
                    return ['success' => true, 'message' => 'Count verified', 'next_stage' => 'variance_analysis'];

                case 'variance_analysis':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Variances analyzed");
                    }
                    return ['success' => true, 'message' => 'Analysis complete', 'next_stage' => 'adjustment_proposal'];

                case 'variance_investigation':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Variances investigated");
                    }
                    return ['success' => true, 'message' => 'Investigation complete', 'next_stage' => 'adjustment_proposal'];

                case 'recount':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Recount completed");
                    }
                    return ['success' => true, 'message' => 'Recount completed', 'next_stage' => 'count_verification'];

                case 'adjustment_proposal':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Adjustments proposed");
                    }
                    return ['success' => true, 'message' => 'Adjustments proposed', 'next_stage' => 'reconciliation_approval'];

                case 'reconciliation_approval':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Adjustments approved");
                    }
                    return ['success' => true, 'message' => 'Adjustments approved', 'next_stage' => 'adjustment_posting'];

                case 'adjustment_posting':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Adjustments posted");
                    }
                    return ['success' => true, 'message' => 'Adjustments posted', 'next_stage' => 'audit_report_generation'];

                case 'audit_report_generation':
                    if ($instance_id) {
                        $this->logAction('update', $instance_id, "Audit report generated");
                    }
                    return ['success' => true, 'message' => 'Audit complete', 'next_stage' => null];

                default:
                    return ['success' => false, 'message' => "Unknown stage: {$stage}"];
            }
        } catch (Exception $e) {
            $this->logError('processStage', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
