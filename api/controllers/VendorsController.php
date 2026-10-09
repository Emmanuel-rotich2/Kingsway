<?php
namespace App\API\Controllers;

use App\API\Modules\inventory\InventoryAPI;

class VendorsController extends BaseController
{
    private $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = $this->contract('App\API\Modules\inventory\InventoryAPI');
    }

    // GET /api/vendors - list vendors
    public function getVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->listSuppliers($data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load vendors');
        }

        return $this->success(['vendors' => $result['data']['suppliers'] ?? []]);
    }

    // POST /api/vendors - create vendor
    public function postVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $data['supplier_name'] = $data['supplier_name'] ?? $data['name'] ?? null;
        if (!$data['supplier_name']) {
            return $this->badRequest('Missing vendor name');
        }

        $result = $this->api->createSupplier($data, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to create vendor');
        }

        return $this->success($result['data'] ?? ['id' => null], $result['message'] ?? 'Vendor created');
    }

    // GET /api/vendors/purchase-orders
    public function getPurchaseOrders($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->listPurchaseOrders($data);
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load purchase orders');
        }

        return $this->success(['purchase_orders' => $result['data']['orders'] ?? []]);
    }

    // POST /api/vendors/purchase-orders - create PO
    public function postPurchaseOrders($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $data['supplier_id'] = $data['vendor_id'] ?? $data['supplier_id'] ?? null;
        $data['total_amount'] = $data['amount'] ?? $data['total_amount'] ?? null;
        $data['remarks'] = $data['remarks'] ?? $data['description'] ?? null;

        if (!$data['supplier_id'] || !$data['total_amount']) {
            return $this->badRequest('Missing vendor or amount');
        }

        $result = $this->api->createPurchaseOrder($data, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to create purchase order');
        }

        return $this->success($result['data'] ?? ['id' => null], $result['message'] ?? 'Purchase order created');
    }

    // PUT /api/vendors/{id} - update vendor
    public function putVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id) {
            return $this->badRequest('Vendor ID required');
        }

        if (isset($data['name']) && !isset($data['supplier_name'])) {
            $data['supplier_name'] = $data['name'];
        }

        $result = $this->api->updateSupplier($id, $data, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to update vendor');
        }

        return $this->success(['id' => $id], $result['message'] ?? 'Vendor updated');
    }

    // DELETE /api/vendors/{id} - soft delete vendor
    public function deleteVendors($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id) {
            return $this->badRequest('Vendor ID required');
        }

        $result = $this->api->deleteSupplier($id, $this->getUserId());
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to deactivate vendor');
        }

        return $this->success(['id' => $id], $result['message'] ?? 'Vendor deactivated');
    }

    // GET /api/vendors/outstanding-liabilities
    public function getOutstandingLiabilities($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $result = $this->api->getOutstandingLiabilities();
        if (($result['code'] ?? 200) >= 400) {
            return $this->error($result['message'] ?? 'Failed to load liabilities');
        }

        return $this->success($result['data'] ?? ['outstanding' => []]);
    }

    /** GET /api/vendors/{id}/payment-accounts */
    public function getPaymentAccounts($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.view', 'finance_view'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        $supplierId = (int) $id;
        if (!$supplierId) return $this->badRequest('Vendor ID required');
        try {
            $suppliers = $this->contract('App\API\Modules\inventory\SuppliersManager');
            return $this->success([
                'bank_accounts' => $suppliers->bankAccountsFor($supplierId),
                'mobile_accounts' => $suppliers->mobileAccountsFor($supplierId),
            ]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[VendorsController] payment accounts: ' . $e->getMessage());
            return $this->badRequest('Failed to load vendor payment accounts.');
        }
    }

    /** POST /api/vendors/{id}/bank-account */
    public function postBankAccount($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) return $this->forbidden('Insufficient permissions');
        $supplierId = (int) $id;
        if (!$supplierId || empty($data['bank_name']) || empty($data['account_name']) || empty($data['account_number'])) return $this->badRequest('Vendor, bank name, account name and account number are required.');
        try {
            $newId = $this->contract('App\API\Modules\inventory\SuppliersManager')->createBankAccount($supplierId, $data);
            return $this->created(['id' => $newId], 'Bank account saved for verification.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[VendorsController] bank account: ' . $e->getMessage());
            return $this->badRequest('Unable to save bank account.');
        }
    }

    /** POST /api/vendors/{id}/mobile-account */
    public function postMobileAccount($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) return $this->forbidden('Insufficient permissions');
        $supplierId = (int) $id;
        if (!$supplierId || empty($data['phone_number']) || empty($data['account_name'])) return $this->badRequest('Vendor, phone number and account name are required.');
        try {
            $newId = $this->contract('App\API\Modules\inventory\SuppliersManager')->createMobileAccount($supplierId, $data);
            return $this->created(['id' => $newId], 'M-Pesa account saved for verification.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[VendorsController] mobile account: ' . $e->getMessage());
            return $this->badRequest('Unable to save mobile account.');
        }
    }

    /** PUT /api/vendors/bank-account/{id} — verification/primary controls. */
    public function putBankAccount($id = null, $data = [], $segments = [])
    {
        return $this->updatePaymentAccount('supplier_bank_accounts', (int) $id, $data);
    }

    /** PUT /api/vendors/mobile-account/{id} — verification/primary controls. */
    public function putMobileAccount($id = null, $data = [], $segments = [])
    {
        return $this->updatePaymentAccount('supplier_mobile_accounts', (int) $id, $data);
    }

    private function updatePaymentAccount($table, $id, array $data)
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [3, 4, 10])) return $this->forbidden('Insufficient permissions');
        if (!$id || !in_array($table, ['supplier_bank_accounts', 'supplier_mobile_accounts'], true)) return $this->badRequest('Payment account ID required');
        try {
            $this->contract('App\API\Modules\inventory\SuppliersManager')->updatePaymentAccount($table, $id, $data);
            return $this->success(['id' => $id], 'Payment account updated.');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[VendorsController] update payment account: ' . $e->getMessage());
            return $this->badRequest('Unable to update payment account.');
        }
    }
}
