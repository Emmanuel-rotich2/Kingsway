<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

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
        return $this->itemsManager->getLowStock($threshold);
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

    public function getOutstandingLiabilities()
    {
        return $this->suppliersManager->getOutstandingLiabilities();
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
            $summaryStmt = $this->db->query(
                "SELECT
                    COUNT(*) AS active_items,
                    COALESCE(SUM(current_quantity), 0) AS total_quantity,
                    COALESCE(SUM(inventory_value), 0) AS inventory_value,
                    SUM(stock_status IN ('REORDER', 'LOW STOCK')) AS low_stock,
                    SUM(stock_status = 'OUT OF STOCK') AS out_of_stock
                 FROM vw_inventory_health
                 WHERE status = 'active'"
            );
            $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

            $categoryStmt = $this->db->query(
                "SELECT COALESCE(category, 'Uncategorised') AS category,
                        COUNT(*) AS item_count,
                        COALESCE(SUM(inventory_value), 0) AS inventory_value
                 FROM vw_inventory_health
                 WHERE status = 'active'
                 GROUP BY category
                 ORDER BY inventory_value DESC, category"
            );

            $healthStmt = $this->db->query(
                "SELECT COALESCE(category, 'Uncategorised') AS category,
                        stock_status, COUNT(*) AS item_count
                 FROM vw_inventory_health
                 WHERE status = 'active'
                 GROUP BY COALESCE(category, 'Uncategorised'), stock_status
                 ORDER BY FIELD(
                    stock_status,
                    'OUT OF STOCK', 'REORDER', 'LOW STOCK', 'ADEQUATE'
                 ), category"
            );

            $lowStockStmt = $this->db->query(
                "SELECT id, name, code, category, current_quantity,
                        minimum_quantity, reorder_level, stock_status,
                        expiry_status, expiry_date, location, unit_cost,
                        inventory_value, updated_at
                 FROM vw_inventory_health
                 WHERE status = 'active'
                   AND stock_status IN ('OUT OF STOCK', 'REORDER', 'LOW STOCK')
                 ORDER BY FIELD(
                    stock_status,
                    'OUT OF STOCK', 'REORDER', 'LOW STOCK'
                 ), current_quantity, name
                 LIMIT 25"
            );

            $requisitionStmt = $this->db->query(
                "SELECT id, requisition_number, department, status, priority,
                        requisition_date, required_date, item_count,
                        total_quantity_requested, created_at
                 FROM vw_pending_requisitions
                 ORDER BY FIELD(priority, 'urgent', 'high', 'normal', 'low'),
                          required_date, created_at DESC
                 LIMIT 25"
            );

            return formatResponse(true, [
                'summary' => [
                    'active_items' => (int) ($summary['active_items'] ?? 0),
                    'total_quantity' => (int) ($summary['total_quantity'] ?? 0),
                    'inventory_value' => (float) ($summary['inventory_value'] ?? 0),
                    'low_stock' => (int) ($summary['low_stock'] ?? 0),
                    'out_of_stock' => (int) ($summary['out_of_stock'] ?? 0),
                ],
                'by_category' => $categoryStmt->fetchAll(\PDO::FETCH_ASSOC),
                'stock_health' => $healthStmt->fetchAll(\PDO::FETCH_ASSOC),
                'low_stock_items' => $lowStockStmt->fetchAll(\PDO::FETCH_ASSOC),
                'pending_requisitions' => $requisitionStmt->fetchAll(\PDO::FETCH_ASSOC),
            ], 'Inventory overview retrieved');
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
                    wd.code as workflow_type,
                    u.username as initiated_by_name
                FROM " . ReadReplicaService::qualifiedRef("workflow_stage_history") . "
                JOIN " . ReadReplicaService::qualifiedRef("workflow_definitions") . " wd ON wi.workflow_id = wd.id
                LEFT JOIN users u ON wi.started_by = u.id
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
                FROM " . ReadReplicaService::qualifiedRef("workflow_stage_history") . "
                LEFT JOIN users u ON wh.processed_by = u.id
                WHERE wh.instance_id = ?
                ORDER BY wh.processed_at ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$workflowId]);
            $workflow['history'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return formatResponse(true, $workflow);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== UNIFORM SALES ====================

    /**
     * Record a (partial) payment against a uniform sale.
     * Live schema: uniform_sales has no amount_paid/balance columns; payments
     * live in uniform_payment_records and totals are unit_price * quantity.
     */
    public function recordUniformSalePayment($saleId, $data = [], $userId = null)
    {
        try {
            $amount = (float)($data['amount_paid'] ?? 0);
            $method = $data['payment_method'] ?? 'cash';
            $reference = $data['reference_no'] ?? null;
            $notes = $data['notes'] ?? null;
            if ($amount <= 0) {
                return formatResponse(false, null, 'amount_paid must be positive', 400);
            }

            $stmt = $this->db->prepare("SELECT * FROM uniform_sales WHERE id = ? LIMIT 1");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$sale) {
                return formatResponse(false, null, 'Sale not found', 404);
            }

            $totalAmount = (float)$sale['unit_price'] * (float)$sale['quantity'];

            $payStmt = $this->db->prepare(
                "INSERT INTO uniform_payment_records (sale_id, amount, payment_date, payment_method, reference_no, recorded_by, notes)
                 VALUES (?, ?, NOW(), ?, ?, ?, ?)"
            );
            $payStmt->execute([$saleId, $amount, $method, $reference, $userId, $notes]);

            $sumStmt = $this->db->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM uniform_payment_records WHERE sale_id = ?"
            );
            $sumStmt->execute([$saleId]);
            $newPaid = (float)$sumStmt->fetchColumn();
            $newBalance = $totalAmount - $newPaid;
            $newStatus = $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending');

            $updStmt = $this->db->prepare("UPDATE uniform_sales SET payment_status = ?, updated_at = NOW() WHERE id = ?");
            $updStmt->execute([$newStatus, $saleId]);

            return formatResponse(true, [
                'sale_id' => (int)$saleId,
                'amount_paid' => round($newPaid, 2),
                'balance' => max(0.0, round($newBalance, 2)),
                'payment_status' => $newStatus,
            ], 'Payment recorded');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * All uniform purchases for a student with running balances.
     */
    public function getUniformSalesStudentInvoice($studentId)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT us.*, ii.name AS item_name, ii.code AS item_code,
                        (us.unit_price * us.quantity) AS total_amount,
                        COALESCE((SELECT SUM(up.amount) FROM " . ReadReplicaService::qualifiedRef("uniform_payment_records") . " WHERE up.sale_id = us.id), 0) AS amount_paid,
                        (us.unit_price * us.quantity)
                            - COALESCE((SELECT SUM(up.amount) FROM " . ReadReplicaService::qualifiedRef("uniform_payment_records") . " up WHERE up.sale_id = us.id), 0) AS outstanding
                 FROM " . ReadReplicaService::qualifiedRef("uniform_sales") . " us
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " ii ON us.item_id = ii.id
                 WHERE us.student_id = ?
                 ORDER BY us.sale_date DESC"
            );
            $stmt->execute([$studentId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalBilled = array_sum(array_map('floatval', array_column($rows, 'total_amount')));
            $totalPaid = array_sum(array_map('floatval', array_column($rows, 'amount_paid')));
            $totalOwed = array_sum(array_map('floatval', array_column($rows, 'outstanding')));

            return formatResponse(true, [
                'student_id' => (int)$studentId,
                'items' => $rows,
                'total_billed' => round($totalBilled, 2),
                'total_paid' => round($totalPaid, 2),
                'total_owed' => round($totalOwed, 2),
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Overall + per-item uniform sales summary.
     */
    public function getUniformSalesSummary($filters = [])
    {
        try {
            $where = ['1=1'];
            $params = [];
            if (!empty($filters['from_date'])) { $where[] = 'us.sale_date >= ?'; $params[] = $filters['from_date']; }
            if (!empty($filters['to_date']))   { $where[] = 'us.sale_date <= ?'; $params[] = $filters['to_date']; }
            if (!empty($filters['status']))    { $where[] = 'us.payment_status = ?'; $params[] = $filters['status']; }
            $ws = implode(' AND ', $where);

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_sales,
                        SUM(us.unit_price * us.quantity) AS total_revenue,
                        COALESCE(SUM((SELECT COALESCE(SUM(up.amount),0) FROM " . ReadReplicaService::qualifiedRef("uniform_payment_records") . " up WHERE up.sale_id = us.id)), 0) AS total_collected,
                        SUM((us.unit_price * us.quantity)
                            - COALESCE((SELECT COALESCE(SUM(up.amount),0) FROM " . ReadReplicaService::qualifiedRef("uniform_payment_records") . " up WHERE up.sale_id = us.id), 0)) AS total_outstanding
                 FROM " . ReadReplicaService::qualifiedRef("uniform_sales") . " us WHERE {$ws}"
            );
            foreach ($params as $i => $v) $stmt->bindValue($i + 1, $v);
            $stmt->execute();
            $totals = $stmt->fetch(\PDO::FETCH_ASSOC);

            $stmt2 = $this->db->prepare(
                "SELECT ii.name AS item_name, ii.code AS item_code,
                        COUNT(*) AS qty_sold, SUM(us.quantity) AS units_sold,
                        SUM(us.unit_price * us.quantity) AS revenue,
                        COALESCE(SUM((SELECT COALESCE(SUM(up.amount),0) FROM " . ReadReplicaService::qualifiedRef("uniform_payment_records") . " WHERE up.sale_id = us.id)), 0) AS collected
                 FROM " . ReadReplicaService::qualifiedRef("uniform_sales") . " us
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " ii ON us.item_id = ii.id
                 WHERE {$ws}
                 GROUP BY us.item_id, ii.name, ii.code ORDER BY revenue DESC"
            );
            foreach ($params as $i => $v) $stmt2->bindValue($i + 1, $v);
            $stmt2->execute();

            return formatResponse(true, [
                'totals' => $totals,
                'by_item' => $stmt2->fetchAll(\PDO::FETCH_ASSOC),
                'filters' => [
                    'from_date' => $filters['from_date'] ?? null,
                    'to_date' => $filters['to_date'] ?? null,
                    'status' => $filters['status'] ?? null,
                ],
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ==================== FIXED ASSETS & DEPRECIATION ====================

    public function getAssets($id = null, $filters = [])
    {
        try {
            if ($id) {
                $stmt = $this->db->prepare(
                    "SELECT fa.*, ac.name AS category_name, ac.depreciation_method, ac.useful_life_years AS cat_life,
                            CONCAT(p.first_name, ' ', p.last_name) AS added_by_name
                     FROM " . ReadReplicaService::qualifiedRef("fixed_assets") . " fa
                     LEFT JOIN asset_categories ac ON ac.id = fa.category_id
                     LEFT JOIN users u ON u.id = fa.added_by
                     LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = u.person_id
                     WHERE fa.id = ? AND fa.deleted_at IS NULL"
                );
                $stmt->execute([$id]);
                $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
                return $asset
                    ? formatResponse(true, $asset)
                    : formatResponse(false, null, 'Asset not found', 404);
            }

            $where = ['fa.deleted_at IS NULL'];
            $params = [];
            if (!empty($filters['category_id'])) { $where[] = 'fa.category_id = ?'; $params[] = $filters['category_id']; }
            if (!empty($filters['status']))      { $where[] = 'fa.status = ?';      $params[] = $filters['status']; }
            if (!empty($filters['search'])) {
                $where[] = '(fa.name LIKE ? OR fa.asset_code LIKE ? OR fa.serial_number LIKE ?)';
                $s = '%' . $filters['search'] . '%';
                array_push($params, $s, $s, $s);
            }

            $stmt = $this->db->prepare(
                "SELECT fa.*, ac.name AS category_name, ac.depreciation_rate AS cat_rate, ac.useful_life_years AS cat_life,
                        sup.name AS supplier_name
                 FROM " . ReadReplicaService::qualifiedRef("fixed_assets") . " fa
                 LEFT JOIN asset_categories ac ON ac.id = fa.category_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " sup ON sup.id = fa.supplier_id
                 WHERE " . implode(' AND ', $where) . " ORDER BY fa.purchase_date DESC LIMIT 500"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stats = $this->db->query(
                "SELECT COUNT(*) AS total_assets, COALESCE(SUM(purchase_price),0) AS total_cost,
                        COALESCE(SUM(current_book_value),0) AS total_book_value,
                        COALESCE(SUM(accumulated_depr),0) AS total_accumulated_depr,
                        COUNT(CASE WHEN YEAR(purchase_date)=YEAR(CURDATE()) THEN 1 END) AS acquired_this_year,
                        COUNT(CASE WHEN status='under_repair' THEN 1 END) AS under_repair
                 FROM fixed_assets WHERE deleted_at IS NULL AND status NOT IN ('disposed','written_off')"
            )->fetch(\PDO::FETCH_ASSOC);

            return formatResponse(true, ['assets' => $rows, 'stats' => $stats]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createAsset($data, $userId = null)
    {
        try {
            $cat = $this->db->prepare("SELECT * FROM asset_categories WHERE id = ?");
            $cat->execute([$data['category_id']]);
            $catRow = $cat->fetch(\PDO::FETCH_ASSOC);
            if (!$catRow) {
                return formatResponse(false, null, 'Invalid category', 400);
            }

            $assetCode = 'AST-' . strtoupper(substr($catRow['code'], 0, 3)) . '-' . date('Y') . '-' . strtoupper(substr(uniqid(), -4));
            $method = $data['depreciation_method'] ?? $catRow['depreciation_method'];
            $life = $data['useful_life_years'] ?? $catRow['useful_life_years'];
            $residual = $data['residual_value'] ?? (($catRow['residual_value_pct'] / 100) * $data['purchase_price']);
            $bookValue = $data['purchase_price'] - $residual;

            $stmt = $this->db->prepare(
                "INSERT INTO fixed_assets (asset_code, name, category_id, description, serial_number, model, brand,
                  location, purchase_date, purchase_price, supplier_id, invoice_number, warranty_expiry, `condition`,
                  status, acquisition_type, depreciation_method, useful_life_years, residual_value, current_book_value,
                  accumulated_depr, added_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,0,?)"
            );
            $stmt->execute([
                $assetCode, $data['name'], $data['category_id'], $data['description'] ?? null,
                $data['serial_number'] ?? null, $data['model'] ?? null, $data['brand'] ?? null,
                $data['location'] ?? null, $data['purchase_date'], $data['purchase_price'],
                $data['supplier_id'] ?? null, $data['invoice_number'] ?? null, $data['warranty_expiry'] ?? null,
                $data['condition'] ?? 'good', $data['status'] ?? 'active',
                $data['acquisition_type'] ?? 'purchase', $method, $life, $residual, $bookValue, $userId
            ]);

            return formatResponse(true, ['id' => $this->db->lastInsertId(), 'asset_code' => $assetCode], 'Asset registered', 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateAsset($id, $data, $userId = null)
    {
        try {
            if (!empty($data['dispose'])) {
                $stmt = $this->db->prepare("SELECT * FROM fixed_assets WHERE id = ?");
                $stmt->execute([$id]);
                $asset = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$asset) {
                    return formatResponse(false, null, 'Asset not found', 404);
                }
                $this->db->prepare(
                    "INSERT INTO asset_disposals (asset_id, disposal_date, disposal_type, book_value_at_disposal, proceeds, reason, authorised_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                )->execute([
                    $id, $data['disposal_date'] ?? date('Y-m-d'), $data['disposal_type'] ?? 'write_off',
                    $asset['current_book_value'], $data['proceeds'] ?? 0, $data['reason'] ?? 'Disposed', $userId
                ]);
                $this->db->prepare("UPDATE fixed_assets SET status = ?, deleted_at = NOW() WHERE id = ?")
                    ->execute([$data['disposal_type'] ?? 'disposed', $id]);
                return formatResponse(true, null, 'Asset disposed');
            }

            $fields = [];
            $params = [];
            $allowed = ['name','description','serial_number','model','brand','location','condition','status','warranty_expiry','category_id','supplier_id','purchase_date','purchase_price','invoice_number'];
            foreach ($allowed as $f) {
                if (array_key_exists($f, $data)) {
                    $fields[] = "$f = ?";
                    $params[] = $data[$f];
                }
            }
            if (empty($fields)) {
                return formatResponse(false, null, 'Nothing to update', 400);
            }
            $fields[] = 'updated_at = NOW()';
            $params[] = $id;
            $this->db->prepare("UPDATE fixed_assets SET " . implode(',', $fields) . " WHERE id = ?")->execute($params);

            return formatResponse(true, null, 'Asset updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAssetCategories()
    {
        try {
            $rows = $this->db->query("SELECT * FROM asset_categories WHERE status = 'active' ORDER BY name")
                ->fetchAll(\PDO::FETCH_ASSOC);
            return formatResponse(true, $rows);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getDepreciationSchedule($year = null, $categoryId = null)
    {
        try {
            $year = $year ?: date('Y');
            $where = ['fa.deleted_at IS NULL', "fa.status NOT IN ('disposed','written_off')"];
            $params = [];
            if ($categoryId) {
                $where[] = 'fa.category_id = ?';
                $params[] = $categoryId;
            }
            $stmt = $this->db->prepare(
                "SELECT fa.*, ac.name AS category_name, ac.depreciation_rate AS cat_rate, ac.useful_life_years AS cat_life
                 FROM " . ReadReplicaService::qualifiedRef("fixed_assets") . "
                 LEFT JOIN asset_categories ac ON ac.id = fa.category_id
                 WHERE " . implode(' AND ', $where) . " ORDER BY ac.name, fa.name"
            );
            $stmt->execute($params);
            $assets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $schedule = [];
            foreach ($assets as $a) {
                $cost = (float)$a['purchase_price'];
                $residual = (float)$a['residual_value'];
                $depreciable = $cost - $residual;
                $life = (int)($a['useful_life_years'] ?: $a['cat_life'] ?: 5);
                $rate = $life > 0 ? (100 / $life) : 20;
                $annualDepr = $life > 0 ? ($depreciable / $life) : 0;
                $startYear = (int)date('Y', strtotime($a['purchase_date']));
                $yearsUsed = max(0, (int)$year - $startYear + 1);
                $accumulated = min($depreciable, $annualDepr * $yearsUsed);
                $bookValue = max($residual, $cost - $accumulated);
                $schedule[] = array_merge($a, [
                    'financial_year' => $year,
                    'annual_depreciation' => round($annualDepr, 2),
                    'accumulated_depr' => round($accumulated, 2),
                    'current_book_value' => round($bookValue, 2),
                    'depreciation_rate_pct' => round($rate, 2),
                    'pct_remaining' => $cost > 0 ? round(($bookValue / $cost) * 100, 1) : 0,
                ]);
            }

            return formatResponse(true, $schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
