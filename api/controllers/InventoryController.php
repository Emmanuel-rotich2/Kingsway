<?php
namespace App\API\Controllers;

use App\API\Modules\inventory\InventoryAPI;
use App\Database\Database;
use App\API\Services\payments\UniformPaymentService;
use App\API\Services\payments\UniformCatalogService;
use App\API\Services\UploadService;
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
        $this->api = $this->contract('App\API\Modules\inventory\InventoryAPI');
    }

    private function guardInventory(string $permission = 'inventory.view'): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    private function guardInventoryWrite(string $permission = 'inventory.manage'): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny([$permission, 'inventory.admin', 'system administrator'])) {
            return $this->forbidden('You do not have permission for this action');
        }
        return null;
    }

    /** Product-catalogue ownership belongs only to School Administration and the Uniform Store. */
    private function guardUniformCatalogManage(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny([], [4, 14], ['school administrator', 'uniform store manager'])) {
            return $this->forbidden('Only the School Administrator or Uniform Store Manager may manage the catalogue');
        }
        return null;
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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->createCategory($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/categories/{id}/update
     */
    public function putCategoriesUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->createLocation($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/locations/{id}/update
     */
    public function putLocationsUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->createSupplier($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/suppliers/{id}/update
     */
    public function putSuppliersUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->createPurchaseOrder($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/purchase-orders/{id}/update
     */
    public function putPurchaseOrdersUpdate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->createRequisition($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/requisitions/{id}/update-status
     */
    public function putRequisitionsUpdateStatus($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->adjustStock($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/movements/record
     */
    public function postMovementsRecord($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->initiateProcurement($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/procurement/{id}/verify-budget
     */
    public function postProcurementVerifyBudget($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->initiateDisposal($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/disposal/{id}/assess-condition
     */
    public function postDisposalAssessCondition($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->initiateTransfer($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/transfer/{id}/approve
     */
    public function postTransferApprove($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $result = $this->api->initiateAudit($data, $this->getCurrentUserId());
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/audit/{id}/schedule
     */
    public function postAuditSchedule($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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
        if ($guard = $this->guardInventoryWrite()) return $guard;


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

    // ========================================
    // SECTION 9: Uniform Sales Management
    // ========================================

    /**
     * GET /api/inventory/uniforms - List all uniform items
     */
    public function getUniformItems($id = null, $data = [], $segments = [])
    {
        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->listUniformItems($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniforms/{id}/sizes - Get size variants for uniform item
     */
    public function getUniformSizes($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Uniform item ID is required');
        }

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getUniformSizes($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/uniforms/sales - Register a uniform sale
     */
    public function postUniformSales($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $student_id = $data['student_id'] ?? null;
        $item_id = $data['item_id'] ?? null;

        if (!$student_id || !$item_id) {
            return $this->badRequest('Student ID and item ID are required');
        }

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->registerUniformSale($student_id, $item_id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniforms/sales/{student_id} - Get student uniform sales history
     */
    public function getUniformSalesByStudent($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Student ID is required');
        }

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getStudentUniformSales($id);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/uniforms/sales/{id}/payment - Update payment status
     */
    public function putUniformSalesPayment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


        if ($id === null) {
            return $this->badRequest('Sale ID is required');
        }

        $payment_status = $data['payment_status'] ?? 'paid';

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->updateUniformSalePayment($id, $payment_status);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniforms/dashboard - Uniform sales dashboard metrics
     */
    public function getUniformDashboard($id = null, $data = [], $segments = [])
    {
        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getUniformSalesDashboard();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniforms/payments/summary - Payment status summary
     */
    public function getUniformPaymentSummary($id = null, $data = [], $segments = [])
    {
        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getUniformPaymentSummary();
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/inventory/uniforms/students/{id}/profile - Update student uniform profile
     */
    public function putUniformStudentProfile($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


        if ($id === null) {
            return $this->badRequest('Student ID is required');
        }

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->updateStudentUniformProfile($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniforms/students/{id}/profile - Get student uniform profile
     */
    public function getUniformStudentProfile($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Student ID is required');
        }

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getStudentUniformProfile($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniform-sales-list - List all uniform sales with filters
     */
    public function getUniformSalesList($id = null, $data = [], $segments = [])
    {
        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->listAllUniformSales($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/inventory/uniform-restock - Restock a uniform size
     */
    public function postUniformRestock($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->restockUniformSize($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniform-low-stock - Get low stock uniform items
     */
    public function getUniformLowStock($id = null, $data = [], $segments = [])
    {
        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getLowStockUniforms();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/inventory/uniform-sales-report - Get uniform sales report
     */
    public function getUniformSalesReport($id = null, $data = [], $segments = [])
    {
        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->getUniformSalesReport($data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/inventory/uniform-sales/{id} - Delete a uniform sale
     */
    public function deleteUniformSales($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;


        if ($id === null) {
            return $this->badRequest('Sale ID is required');
        }

        $uniformsApi = $this->contract('App\API\Modules\inventory\UniformSalesManager');
        $result = $uniformsApi->deleteUniformSale($id);
        return $this->handleResponse($result);
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            // formatResponse shape: {status, message, type, code, data}
            if (isset($result['code']) && isset($result['status'])) {
                $code = (int)$result['code'];
                $message = $result['message'] ?? 'Operation failed';
                $data = $result['data'] ?? null;
                if ($code >= 200 && $code < 300) {
                    return $this->success($data, $message);
                }
                if ($code === 401) return $this->unauthorized($message);
                if ($code === 403) return $this->forbidden($message);
                if ($code === 404) return $this->notFound($message);
                if ($code === 422) return $this->error($message, 422);
                if ($code >= 500) return $this->serverError($message, $data);
                return $this->badRequest($message, $data);
            }
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

    // ================================================================
    // UNIFORM SALES PAYMENT ENDPOINTS
    // ================================================================

    /**
     * POST /api/inventory/uniform-sales-record-payment/{id}
     * Records a (partial) payment against a uniform sale
     */
    public function postUniformSalesRecordPayment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;

        if (!$id) return $this->badRequest('sale_id required');
        $receivedBy = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->recordUniformSalePayment((int)$id, $data, $receivedBy);
        return $this->handleResponse($result);
    }

    /** POST /api/inventory/uniform-payment-intents */
    public function postUniformPaymentIntents($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;
        try {
            $service = $this->contract('App\API\Services\payments\UniformPaymentService', Database::getInstance()->getConnection());
            $result = !empty($data['accumulated'])
                ? $service->initiateAccumulated($data, (int) $this->getCurrentUserId())
                : $service->initiate($data, (int) $this->getCurrentUserId());
            return $this->created($result, 'Uniform payment request created');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[InventoryController] uniform payment initiate: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /** GET /api/inventory/uniform-payment-intents/{id} */
    public function getUniformPaymentIntents($id = null, $data = [], $segments = [])
    {
        if ($id === null) return $this->badRequest('Uniform payment intent ID is required');
        return $this->handleResponse(($this->contract('App\API\Services\payments\UniformPaymentService', Database::getInstance()->getConnection()))->get((int) $id));
    }

    /** GET /api/inventory/uniform-catalog */
    public function getUniformCatalog($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventory()) return $guard;
        $canManage = $this->userHasAny([], [4, 14], ['school administrator', 'uniform store manager']);
        $managementView = $canManage && !empty($data['management']);
        $scope = $managementView ? ['staff' => true] : ['internal' => true];
        return $this->success([
            'products' => ($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->list($scope + $data),
            'can_manage' => $canManage,
        ]);
    }

    /** POST /api/inventory/uniform-catalog-products */
    public function postUniformCatalogProducts($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardUniformCatalogManage()) return $guard;
        try { return $this->created(($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->saveProduct($data, (int)$this->getCurrentUserId()), 'Uniform catalogue product saved'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    /** POST /api/inventory/uniform-catalog-images */
    public function postUniformCatalogImages($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardUniformCatalogManage()) return $guard;
        $productId=(int)($data['product_id']??$id??0); if(!$productId||empty($_FILES['file']))return $this->badRequest('product_id and image file are required');
        try { $stored=($this->contract('App\API\Services\UploadService'))->store($_FILES['file'],'uniform_catalog_image',['owner_id'=>(string)$productId,'prefix'=>'uniform']); return $this->created(($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->addImage($productId,(string)($stored['relative_path']??''),$data['alt_text']??null,!empty($data['is_primary']),(int)($data['variant_id']??0)?:null,(string)($data['view_type']??'catalog')),'Uniform catalogue image uploaded'); }
        catch (\Throwable $e) { return $this->badRequest($e->getMessage()); }
    }

    public function postUniformCatalogVariants($id = null, $data = [], $segments = [])
    { if ($guard=$this->guardUniformCatalogManage()) return $guard; try{return $this->created(($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->saveVariant($data),'Catalogue variant saved');}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function postUniformCatalogSizes($id = null, $data = [], $segments = [])
    { if ($guard=$this->guardUniformCatalogManage()) return $guard; try{return $this->created(($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->saveSize($data),'Catalogue size and stock saved');}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    public function deleteUniformCatalogImages($id = null, $data = [], $segments = [])
    { if ($guard=$this->guardUniformCatalogManage()) return $guard; try{($this->contract('App\API\Services\payments\UniformCatalogService', Database::getInstance()->getConnection()))->deleteImage((int)($id??0));return $this->success([], 'Catalogue image removed');}catch(\Throwable $e){return $this->badRequest($e->getMessage());} }

    /** POST /api/inventory/uniform-catalog-purchases — authorised staff sale */
    public function postUniformCatalogPurchases($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardUniformCatalogManage()) return $guard;
        $studentId=(int)($data['student_id']??0);$productId=(int)($data['product_id']??0);$variantId=(int)($data['variant_id']??0)?:null;$sizeId=(int)($data['size_id']??0);$quantity=(int)($data['quantity']??0);
        if(!$studentId||!$productId||!$sizeId||$quantity<1)return $this->badRequest('student_id, product_id, size_id and quantity are required');
        try{$pdo=Database::getInstance()->getConnection();$s=$pdo->prepare('SELECT i.id AS item_id,us.size,us.unit_price FROM uniform_catalog_products cp LEFT JOIN uniform_catalog_variants v ON v.id=? AND v.product_id=cp.id JOIN inventory_items i ON i.id=COALESCE(v.item_id,cp.item_id) JOIN uniform_sizes us ON us.item_id=i.id WHERE cp.id=? AND us.id=? AND (v.id IS NULL OR v.status=\'active\') AND us.quantity_available-us.quantity_reserved>=?');$s->execute([$variantId,$productId,$sizeId,$quantity]);$size=$s->fetch(\PDO::FETCH_ASSOC);if(!$size)return $this->badRequest('Selected variant or size is unavailable');$manager=$this->contract('App\API\Modules\inventory\UniformSalesManager');return $this->created($manager->registerUniformSale($studentId,(int)$size['item_id'],['size'=>$size['size'],'quantity'=>$quantity,'unit_price'=>$size['unit_price'],'sold_by'=>$this->getCurrentUserId(),'notes'=>'Internal catalogue purchase']), 'Uniform purchase created');}catch(\Throwable $e){return $this->badRequest($e->getMessage());}
    }

    /** POST /api/inventory/uniform-payment-intent-confirm/{id} */
    public function postUniformPaymentIntentConfirm($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;
        if ($id === null) return $this->badRequest('Uniform payment intent ID is required');
        try {
            $service = $this->contract('App\API\Services\payments\UniformPaymentService', Database::getInstance()->getConnection());
            return $this->success($service->confirmManual((int) $id, (int) $this->getCurrentUserId()), 'Uniform payment confirmed');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[InventoryController] uniform payment confirm: ' . $e->getMessage());
            return $this->badRequest($e->getMessage());
        }
    }

    /**
     * GET /api/inventory/uniform-sales-student-invoice/{id}
     * All uniform purchases for a student with running balances
     */
    public function getUniformSalesStudentInvoice($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        return $this->handleResponse($this->api->getUniformSalesStudentInvoice((int)$id));
    }

    /**
     * GET /api/inventory/uniform-sales-summary
     * Overall + per-item sales summary; supports from_date, to_date, payment_status filters
     */
    public function getUniformSalesSummary($id = null, $data = [], $segments = [])
    {
        $filters = [
            'from_date' => $_GET['from_date'] ?? $data['from_date'] ?? null,
            'to_date'   => $_GET['to_date']   ?? $data['to_date']   ?? null,
            'status'    => $_GET['payment_status'] ?? $data['payment_status'] ?? null,
        ];
        return $this->handleResponse($this->api->getUniformSalesSummary($filters));
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }

    // ========================================
    // SECTION: Fixed Assets & Depreciation
    // ========================================

    /** GET /api/inventory/assets — list fixed assets */
    public function getAssets($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getAssets($id, $data));
    }

    /** POST /api/inventory/assets — register a new fixed asset */
    public function postAssets($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;

        if (empty($data['name']) || empty($data['category_id']) || empty($data['purchase_date']) || empty($data['purchase_price'])) {
            return $this->badRequest('name, category_id, purchase_date, purchase_price are required');
        }
        $userId = $this->getCurrentUserId();
        return $this->handleResponse($this->api->createAsset($data, $userId));
    }

    /** PUT /api/inventory/assets/{id} — update asset or record disposal */
    public function putAssets($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardInventoryWrite()) return $guard;

        if (!$id) return $this->badRequest('Asset ID required');
        $userId = $this->getCurrentUserId();
        return $this->handleResponse($this->api->updateAsset((int)$id, $data, $userId));
    }

    /** GET /api/inventory/asset-categories — list asset categories */
    public function getAssetCategories($id = null, $data = [], $segments = [])
    {
        return $this->handleResponse($this->api->getAssetCategories());
    }

    /** GET /api/inventory/depreciation — depreciation schedule for assets */
    public function getDepreciation($id = null, $data = [], $segments = [])
    {
        $year = $data['year'] ?? date('Y');
        $catId = $data['category_id'] ?? null;
        return $this->handleResponse($this->api->getDepreciationSchedule($year, $catId));
    }
}
