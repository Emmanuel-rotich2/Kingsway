<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\BaseAPI;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Inventory API - Central Coordinator
 * 
 * Coordinates all inventory operations through specialized managers and workflows
 * Provides unified interface for inventory management system
 * 
 * Architecture:
 * - CRUD Managers: Handle basic database operations
 * - Workflow Classes: Handle complex business processes
 * - This API: Coordinates everything and enforces business rules
 */
class InventoryAPI extends BaseAPI
{
    // CRUD Managers
    private $itemsManager;
    private $categoriesManager;
    private $locationsManager;
    private $suppliersManager;
    private $purchaseOrdersManager;
    private $requisitionsManager;
    private $transactionsManager;
    private $movementsManager;

    // Workflow Handlers
    private $procurementWorkflow;
    private $disposalWorkflow;
    private $transferWorkflow;
    private $auditWorkflow;

    public function __construct()
    {
        parent::__construct('inventory');
        $this->initializeManagers();
        $this->initializeWorkflows();
    }

    /**
     * Initialize CRUD managers
     */
    private function initializeManagers()
    {
        $this->itemsManager = new InventoryItemsManager();
        $this->categoriesManager = new CategoriesManager();
        $this->locationsManager = new LocationsManager();
        $this->suppliersManager = new SuppliersManager();
        $this->purchaseOrdersManager = new PurchaseOrdersManager();
        $this->requisitionsManager = new RequisitionsManager();
        $this->transactionsManager = new TransactionsManager();
        $this->movementsManager = new StockMovementsManager();
    }

    /**
     * Initialize workflow handlers
     */
    private function initializeWorkflows()
    {
        $this->procurementWorkflow = new StockProcurementWorkflow();
        $this->disposalWorkflow = new AssetDisposalWorkflow();
        $this->transferWorkflow = new StockTransferWorkflow();
        $this->auditWorkflow = new StockAuditWorkflow();
    }

    // ==================== INVENTORY ITEMS ====================

    public function listItems($params = [])
    {
        return $this->itemsManager->listItems($params);
    }

    public function getItem($id)
    {
        return $this->itemsManager->getItem($id);
    }

    public function createItem($data, $userId)
    {
        return $this->itemsManager->createItem($data, $userId);
    }

    public function updateItem($id, $data, $userId)
    {
        return $this->itemsManager->updateItem($id, $data, $userId);
    }

    public function deleteItem($id, $userId)
    {
        return $this->itemsManager->deleteItem($id, $userId);
    }

    public function getItemWithStock($id)
    {
        return $this->itemsManager->getItemWithStock($id);
    }

    public function getLowStockItems($threshold = null)
    {
        return $this->itemsManager->getLowStockItems($threshold);
    }

    public function getStockValuation()
    {
        return $this->itemsManager->getStockValuation();
    }

    // ==================== CATEGORIES ====================

    public function listCategories($params = [])
    {
        return $this->categoriesManager->listCategories($params);
    }

    public function getCategory($id)
    {
        return $this->categoriesManager->getCategory($id);
    }

    public function createCategory($data, $userId)
    {
        return $this->categoriesManager->createCategory($data, $userId);
    }

    public function updateCategory($id, $data, $userId)
    {
        return $this->categoriesManager->updateCategory($id, $data, $userId);
    }

    public function deleteCategory($id, $userId)
    {
        return $this->categoriesManager->deleteCategory($id, $userId);
    }

    // ==================== LOCATIONS ====================

    public function listLocations($params = [])
    {
        return $this->locationsManager->listLocations($params);
    }

    public function getLocation($id)
    {
        return $this->locationsManager->getLocation($id);
    }

    public function createLocation($data, $userId)
    {
        return $this->locationsManager->createLocation($data, $userId);
    }

    public function updateLocation($id, $data, $userId)
    {
        return $this->locationsManager->updateLocation($id, $data, $userId);
    }

    public function deleteLocation($id, $userId)
    {
        return $this->locationsManager->deleteLocation($id, $userId);
    }

    // ==================== SUPPLIERS ====================

    public function listSuppliers($params = [])
    {
        return $this->suppliersManager->listSuppliers($params);
    }

    public function getSupplier($id)
    {
        return $this->suppliersManager->getSupplier($id);
    }

    public function createSupplier($data, $userId)
    {
        return $this->suppliersManager->createSupplier($data, $userId);
    }

    public function updateSupplier($id, $data, $userId)
    {
        return $this->suppliersManager->updateSupplier($id, $data, $userId);
    }

    public function deleteSupplier($id, $userId)
    {
        return $this->suppliersManager->deleteSupplier($id, $userId);
    }

    // ==================== PURCHASE ORDERS ====================

    public function listPurchaseOrders($params = [])
    {
        return $this->purchaseOrdersManager->listPurchaseOrders($params);
    }

    public function getPurchaseOrder($id)
    {
        return $this->purchaseOrdersManager->getPurchaseOrder($id);
    }

    public function createPurchaseOrder($data, $userId)
    {
        return $this->purchaseOrdersManager->createPurchaseOrder($data, $userId);
    }

    public function updatePurchaseOrder($id, $data, $userId)
    {
        return $this->purchaseOrdersManager->updatePurchaseOrder($id, $data, $userId);
    }

    public function receivePurchaseOrder($id, $data, $userId)
    {
        return $this->purchaseOrdersManager->receivePurchaseOrder($id, $data, $userId);
    }

    // ==================== REQUISITIONS ====================

    public function listRequisitions($params = [])
    {
        return $this->requisitionsManager->listRequisitions($params);
    }

    public function getRequisition($id)
    {
        return $this->requisitionsManager->getRequisition($id);
    }

    public function createRequisition($data, $userId)
    {
        return $this->requisitionsManager->createRequisition($data, $userId);
    }

    public function updateRequisitionStatus($id, $status, $userId, $remarks = null)
    {
        return $this->requisitionsManager->updateStatus($id, $status, $userId, $remarks);
    }

    public function deleteRequisition($id, $userId)
    {
        return $this->requisitionsManager->deleteRequisition($id, $userId);
    }

    // ==================== STOCK MOVEMENTS ====================

    public function listMovements($params = [])
    {
        return $this->movementsManager->listMovements($params);
    }

    public function getMovementSummary($params = [])
    {
        return $this->movementsManager->getMovementSummary($params);
    }

    public function getItemHistory($itemId, $limit = 50)
    {
        return $this->movementsManager->getItemHistory($itemId, $limit);
    }

    public function adjustStock($data, $userId)
    {
        return $this->movementsManager->adjustStock($data, $userId);
    }

    public function recordMovement($data, $userId)
    {
        return $this->movementsManager->recordMovement($data, $userId);
    }

    // ==================== PROCUREMENT WORKFLOW ====================

    /**
     * Initiate procurement workflow
     */
    public function initiateProcurement($data, $userId)
    {
        try {
            // Create requisition first (if not exists)
            if (empty($data['requisition_id'])) {
                $reqResult = $this->requisitionsManager->createRequisition([
                    'requisition_type' => 'procurement',
                    'items' => $data['items'],
                    'justification' => $data['justification'] ?? 'Procurement request',
                    'priority' => $data['urgency'] ?? 'normal'
                ], $userId);

                if (!$reqResult['success']) {
                    return $reqResult;
                }

                $data['requisition_id'] = $reqResult['data']['requisition_id'];
            }

            // Start workflow
            return $this->procurementWorkflow->initiateProcurement($data, $userId);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function verifyProcurementBudget($workflowId, $data, $userId)
    {
        return $this->procurementWorkflow->verifyBudget($workflowId, $userId, $data);
    }

    public function requestQuotations($workflowId, $data, $userId)
    {
        return $this->procurementWorkflow->requestQuotations($workflowId, $userId, $data);
    }

    public function evaluateQuotations($workflowId, $data, $userId)
    {
        return $this->procurementWorkflow->evaluateQuotations($workflowId, $userId, $data);
    }

    public function approveProcurement($workflowId, $data, $userId)
    {
        return $this->procurementWorkflow->approveProcurement($workflowId, $userId, $data);
    }

    public function createProcurementPO($workflowId, $data, $userId)
    {
        return $this->procurementWorkflow->createPurchaseOrder($workflowId, $userId, $data);
    }

    // ==================== DISPOSAL WORKFLOW ====================

    public function initiateDisposal($data, $userId)
    {
        return $this->disposalWorkflow->initiateDisposal($data, $userId);
    }

    public function assessAssetCondition($workflowId, $data, $userId)
    {
        return $this->disposalWorkflow->assessCondition($workflowId, $userId, $data);
    }

    public function performAssetValuation($workflowId, $data, $userId)
    {
        return $this->disposalWorkflow->performValuation($workflowId, $userId, $data);
    }

    public function selectDisposalMethod($workflowId, $data, $userId)
    {
        return $this->disposalWorkflow->selectDisposalMethod($workflowId, $userId, $data);
    }

    public function approveDisposal($workflowId, $data, $userId)
    {
        return $this->disposalWorkflow->approveDisposal($workflowId, $userId, $data);
    }

    public function executeDisposal($workflowId, $data, $userId)
    {
        return $this->disposalWorkflow->executeDisposal($workflowId, $userId, $data);
    }

    // ==================== TRANSFER WORKFLOW ====================

    public function initiateTransfer($data, $userId)
    {
        return $this->transferWorkflow->initiateTransfer($data, $userId);
    }

    public function approveTransfer($workflowId, $data, $userId)
    {
        return $this->transferWorkflow->approveTransfer($workflowId, $userId, $data);
    }

    public function pickStock($workflowId, $data, $userId)
    {
        return $this->transferWorkflow->pickStock($workflowId, $userId, $data);
    }

    public function performTransferQualityCheck($workflowId, $data, $userId)
    {
        return $this->transferWorkflow->performQualityCheck($workflowId, $userId, $data);
    }

    public function dispatchTransfer($workflowId, $data, $userId)
    {
        return $this->transferWorkflow->dispatchItems($workflowId, $userId, $data);
    }

    public function receiveTransfer($workflowId, $data, $userId)
    {
        return $this->transferWorkflow->receiveGoods($workflowId, $userId, $data);
    }

    public function inspectReceivedTransfer($workflowId, $data, $userId)
    {
        return $this->transferWorkflow->inspectReceivedGoods($workflowId, $userId, $data);
    }

    // ==================== AUDIT WORKFLOW ====================

    public function initiateAudit($data, $userId)
    {
        return $this->auditWorkflow->initiateAudit($data, $userId);
    }

    public function scheduleAudit($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->scheduleAudit($workflowId, $userId, $data);
    }

    public function prepareAuditCount($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->prepareCount($workflowId, $userId, $data);
    }

    public function performPhysicalCount($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->performPhysicalCount($workflowId, $userId, $data);
    }

    public function verifyAuditCount($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->verifyCount($workflowId, $userId, $data);
    }

    public function analyzeAuditVariances($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->analyzeVariances($workflowId, $userId, $data);
    }

    public function approveAuditAdjustments($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->approveAdjustments($workflowId, $userId, $data);
    }

    public function postAuditAdjustments($workflowId, $data, $userId)
    {
        return $this->auditWorkflow->postAdjustments($workflowId, $userId, $data);
    }

    // ==================== DASHBOARD & ANALYTICS ====================

    /**
     * Get inventory dashboard data
     */
    public function getDashboard()
    {
        try {
            // Total items and value
            $sql = "
                SELECT 
                    COUNT(*) as total_items,
                    SUM(quantity_on_hand) as total_quantity,
                    SUM(quantity_on_hand * unit_cost) as total_value,
                    COUNT(CASE WHEN quantity_on_hand <= reorder_level THEN 1 END) as low_stock_count,
                    COUNT(CASE WHEN quantity_on_hand = 0 THEN 1 END) as out_of_stock_count
                FROM inventory_items
                WHERE status = 'active'
            ";
            $stmt = $this->db->query($sql);
            $summary = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Recent movements (last 7 days)
            $sql = "
                SELECT 
                    DATE(transaction_date) as date,
                    transaction_type,
                    COUNT(*) as count,
                    SUM(total_cost) as value
                FROM inventory_transactions
                WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(transaction_date), transaction_type
                ORDER BY date DESC
            ";
            $stmt = $this->db->query($sql);
            $recentMovements = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Active workflows
            $sql = "
                SELECT 
                    wd.name as workflow_name,
                    wi.current_stage,
                    COUNT(*) as count
                FROM workflow_instances wi
                JOIN workflow_definitions wd ON wi.workflow_id = wd.id
                WHERE wi.status IN ('pending', 'in_progress')
                    AND wd.workflow_type IN ('stock_procurement', 'asset_disposal', 'stock_transfer', 'stock_audit')
                GROUP BY wd.name, wi.current_stage
            ";
            $stmt = $this->db->query($sql);
            $activeWorkflows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Pending requisitions
            $sql = "
                SELECT COUNT(*) as pending_requisitions
                FROM inventory_requisitions
                WHERE status = 'pending'
            ";
            $stmt = $this->db->query($sql);
            $pendingRequisitions = $stmt->fetchColumn();

            // Top categories by value
            $sql = "
                SELECT 
                    c.category_name,
                    COUNT(i.id) as item_count,
                    SUM(i.quantity_on_hand * i.unit_cost) as total_value
                FROM categories c
                LEFT JOIN inventory_items i ON c.id = i.category_id
                WHERE i.status = 'active'
                GROUP BY c.id
                ORDER BY total_value DESC
                LIMIT 5
            ";
            $stmt = $this->db->query($sql);
            $topCategories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'summary' => $summary,
                'recent_movements' => $recentMovements,
                'active_workflows' => $activeWorkflows,
                'pending_requisitions' => $pendingRequisitions,
                'top_categories' => $topCategories
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get workflow instance details
     */
    public function getWorkflowInstance($workflowId)
    {
        try {
            $sql = "
                SELECT 
                    wi.*,
                    wd.name as workflow_name,
                    wd.workflow_type,
                    u.username as initiated_by_name
                FROM workflow_instances wi
                JOIN workflow_definitions wd ON wi.workflow_id = wd.id
                LEFT JOIN users u ON wi.initiated_by = u.id
                WHERE wi.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$workflowId]);
            $workflow = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$workflow) {
                return formatResponse(false, null, 'Workflow not found');
            }

            // Get workflow history
            $sql = "
                SELECT 
                    wh.*,
                    u.username as performed_by_name
                FROM workflow_history wh
                LEFT JOIN users u ON wh.performed_by = u.id
                WHERE wh.workflow_instance_id = ?
                ORDER BY wh.created_at ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$workflowId]);
            $workflow['history'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return formatResponse(true, $workflow);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
