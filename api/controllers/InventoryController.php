<?php
namespace App\API\Controllers;

use App\API\Modules\inventory\InventoryAPI;
use Exception;

/**
 * InventoryController - REST endpoints for all inventory operations
 * Handles items, categories, locations, suppliers, purchase orders, requisitions,
 * movements, and workflows (procurement, disposal, transfer, audit)
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class InventoryController extends BaseController
{
    private InventoryAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new InventoryAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Inventory API is running']);
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/inventory - List all inventory items
     * GET /api/inventory/{id} - Get single inventory item
     */
    public function getInventory($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->getItem($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->listItems($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory - Create new inventory item
     */
    public function postInventory($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->createItem($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/{id} - Update inventory item
     */
    public function putInventory($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Inventory item ID is required for update');
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPut($resource, $id, $data, $segments);
        }
        
        $result = $this->api->updateItem($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/inventory/{id} - Delete inventory item
     */
    public function deleteInventory($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Inventory item ID is required for deletion');
        }
        
        $result = $this->api->deleteItem($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Item Operations
    // ========================================

    /**
     * GET /api/inventory/items/list
     */
    public function getItemsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listItems($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/items/{id}/with-stock
     */
    public function getItemsWithStock($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Item ID is required');
        }
        
        $result = $this->api->getItemWithStock($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/items/low-stock
     */
    public function getItemsLowStock($id = null, $data = [], $segments = [])
    {
        $threshold = $data['threshold'] ?? null;
        $result = $this->api->getLowStockItems($threshold);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/items/stock-valuation
     */
    public function getItemsStockValuation($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStockValuation();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/items/{id}/history
     */
    public function getItemsHistory($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['item_id'])) {
            $id = $data['item_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Item ID is required');
        }
        
        $limit = $data['limit'] ?? 50;
        $result = $this->api->getItemHistory($id, $limit);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Category Management
    // ========================================

    /**
     * GET /api/inventory/categories/list
     */
    public function getCategoriesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listCategories($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/categories/{id}/get
     */
    public function getCategoriesGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->getCategory($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/categories/create
     */
    public function postCategoriesCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createCategory($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/categories/{id}/update
     */
    public function putCategoriesUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->updateCategory($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/inventory/categories/{id}/delete
     */
    public function deleteCategoriesDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Category ID is required');
        }
        
        $result = $this->api->deleteCategory($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Location Management
    // ========================================

    /**
     * GET /api/inventory/locations/list
     */
    public function getLocationsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listLocations($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/locations/{id}/get
     */
    public function getLocationsGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Location ID is required');
        }
        
        $result = $this->api->getLocation($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/locations/create
     */
    public function postLocationsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createLocation($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/locations/{id}/update
     */
    public function putLocationsUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Location ID is required');
        }
        
        $result = $this->api->updateLocation($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/inventory/locations/{id}/delete
     */
    public function deleteLocationsDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Location ID is required');
        }
        
        $result = $this->api->deleteLocation($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Supplier Management
    // ========================================

    /**
     * GET /api/inventory/suppliers/list
     */
    public function getSuppliersList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listSuppliers($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/suppliers/{id}/get
     */
    public function getSuppliersGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Supplier ID is required');
        }
        
        $result = $this->api->getSupplier($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/suppliers/create
     */
    public function postSuppliersCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createSupplier($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/suppliers/{id}/update
     */
    public function putSuppliersUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Supplier ID is required');
        }
        
        $result = $this->api->updateSupplier($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/inventory/suppliers/{id}/delete
     */
    public function deleteSuppliersDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Supplier ID is required');
        }
        
        $result = $this->api->deleteSupplier($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Purchase Orders
    // ========================================

    /**
     * GET /api/inventory/purchase-orders/list
     */
    public function getPurchaseOrdersList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listPurchaseOrders($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/purchase-orders/{id}/get
     */
    public function getPurchaseOrdersGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Purchase order ID is required');
        }
        
        $result = $this->api->getPurchaseOrder($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/purchase-orders/create
     */
    public function postPurchaseOrdersCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createPurchaseOrder($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/purchase-orders/{id}/update
     */
    public function putPurchaseOrdersUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Purchase order ID is required');
        }
        
        $result = $this->api->updatePurchaseOrder($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/purchase-orders/{id}/receive
     */
    public function postPurchaseOrdersReceive($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Purchase order ID is required');
        }
        
        $result = $this->api->receivePurchaseOrder($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Requisitions
    // ========================================

    /**
     * GET /api/inventory/requisitions/list
     */
    public function getRequisitionsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listRequisitions($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/requisitions/{id}/get
     */
    public function getRequisitionsGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Requisition ID is required');
        }
        
        $result = $this->api->getRequisition($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/requisitions/create
     */
    public function postRequisitionsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createRequisition($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/requisitions/{id}/update-status
     */
    public function putRequisitionsUpdateStatus($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Requisition ID is required');
        }
        
        $status = $data['status'] ?? null;
        $remarks = $data['remarks'] ?? null;
        
        if ($status === null) {
            return $this->badRequest('Status is required');
        }
        
        $result = $this->api->updateRequisitionStatus($id, $status, $this->getCurrentUserId(), $remarks);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/inventory/requisitions/{id}/delete
     */
    public function deleteRequisitionsDelete($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Requisition ID is required');
        }
        
        $result = $this->api->deleteRequisition($id, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Stock Movements
    // ========================================

    /**
     * GET /api/inventory/movements/list
     */
    public function getMovementsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listMovements($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/movements/summary
     */
    public function getMovementsSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getMovementSummary($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/movements/adjust-stock
     */
    public function postMovementsAdjustStock($id = null, $data = [], $segments = [])
    {
        $result = $this->api->adjustStock($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/movements/record
     */
    public function postMovementsRecord($id = null, $data = [], $segments = [])
    {
        $result = $this->api->recordMovement($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Procurement Workflow
    // ========================================

    /**
     * POST /api/inventory/procurement/initiate
     */
    public function postProcurementInitiate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->initiateProcurement($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/procurement/{id}/verify-budget
     */
    public function postProcurementVerifyBudget($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->verifyProcurementBudget($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/procurement/{id}/request-quotations
     */
    public function postProcurementRequestQuotations($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->requestQuotations($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/procurement/{id}/evaluate-quotations
     */
    public function postProcurementEvaluateQuotations($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->evaluateQuotations($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/procurement/{id}/approve
     */
    public function postProcurementApprove($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveProcurement($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/procurement/{id}/create-po
     */
    public function postProcurementCreatePo($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->createProcurementPO($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 10: Disposal Workflow
    // ========================================

    /**
     * POST /api/inventory/disposal/initiate
     */
    public function postDisposalInitiate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->initiateDisposal($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/disposal/{id}/assess-condition
     */
    public function postDisposalAssessCondition($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->assessAssetCondition($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/disposal/{id}/perform-valuation
     */
    public function postDisposalPerformValuation($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->performAssetValuation($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/disposal/{id}/select-method
     */
    public function postDisposalSelectMethod($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->selectDisposalMethod($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/disposal/{id}/approve
     */
    public function postDisposalApprove($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveDisposal($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/disposal/{id}/execute
     */
    public function postDisposalExecute($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->executeDisposal($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 11: Transfer Workflow
    // ========================================

    /**
     * POST /api/inventory/transfer/initiate
     */
    public function postTransferInitiate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->initiateTransfer($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/approve
     */
    public function postTransferApprove($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveTransfer($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/pick-stock
     */
    public function postTransferPickStock($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->pickStock($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/quality-check
     */
    public function postTransferQualityCheck($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->performTransferQualityCheck($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/dispatch
     */
    public function postTransferDispatch($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->dispatchTransfer($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/receive
     */
    public function postTransferReceive($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->receiveTransfer($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/inspect
     */
    public function postTransferInspect($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->inspectReceivedTransfer($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 12: Audit Workflow
    // ========================================

    /**
     * POST /api/inventory/audit/initiate
     */
    public function postAuditInitiate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->initiateAudit($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/schedule
     */
    public function postAuditSchedule($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->scheduleAudit($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/prepare-count
     */
    public function postAuditPrepareCount($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->prepareAuditCount($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/perform-count
     */
    public function postAuditPerformCount($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->performPhysicalCount($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/verify-count
     */
    public function postAuditVerifyCount($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->verifyAuditCount($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/analyze-variances
     */
    public function postAuditAnalyzeVariances($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->analyzeAuditVariances($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/approve-adjustments
     */
    public function postAuditApproveAdjustments($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->approveAuditAdjustments($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/post-adjustments
     */
    public function postAuditPostAdjustments($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->postAuditAdjustments($id, $data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 13: Dashboard & Reporting
    // ========================================

    /**
     * GET /api/inventory/dashboard
     */
    public function getDashboard($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getDashboard();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/workflow/{id}/get
     */
    public function getWorkflowGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['workflow_id'])) {
            $id = $data['workflow_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Workflow ID is required');
        }
        
        $result = $this->api->getWorkflowInstance($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 14: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested DELETE requests to appropriate methods
     */
    private function routeNestedDelete($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'delete' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }

        return $this->success($result);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
