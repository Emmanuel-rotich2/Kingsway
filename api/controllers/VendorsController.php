<?php
namespace App\API\Controllers;
use Exception;
use PDO;

class VendorsController extends BaseController
{
    public function __construct($request = null)
    {
        parent::__construct($request);
    }

    // GET /api/vendors - list vendors
    public function getVendors($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            // suppliers table exists in this schema
            $stmt = $this->db->query('SELECT * FROM suppliers ORDER BY name');
            $rows = $stmt ? $stmt->fetchAll() : [];
            return $this->success(['vendors' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch vendors: ' . $e->getMessage());
        }
    }

    // POST /api/vendors - create vendor
    public function postVendors($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $name = $data['name'] ?? null;
        if (!$name) return $this->badRequest('Missing vendor name');

        try {
            $this->db->query(
                'INSERT INTO suppliers (name, contact_person, phone, email, address, category, bank_name, account_number, notes, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "active", NOW())',
                [
                    $name,
                    $data['contact_person'] ?? $data['contact'] ?? null,
                    $data['phone']          ?? null,
                    $data['email']          ?? null,
                    $data['address']        ?? null,
                    $data['category']       ?? 'other',
                    $data['bank_name']      ?? null,
                    $data['account_number'] ?? null,
                    $data['notes']          ?? null,
                ]
            );
            return $this->success(['id' => $this->db->getConnection()->lastInsertId()], 'Vendor created');
        } catch (Exception $e) {
            return $this->error('Failed to create vendor: ' . $e->getMessage());
        }
    }

    // GET /api/vendors/purchase-orders
    public function getPurchaseOrders($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            // purchase_orders uses supplier_id in this schema
            $stmt = $this->db->query('SELECT po.*, s.name as vendor_name FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id ORDER BY po.created_at DESC LIMIT 200');
            $rows = $stmt ? $stmt->fetchAll() : [];
            return $this->success(['purchase_orders' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch purchase orders: ' . $e->getMessage());
        }
    }

    // POST /api/vendors/purchase-orders - create PO
    public function postPurchaseOrders($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10])) {
            return $this->forbidden('Insufficient permissions');
        }

        $vendorId = $data['vendor_id'] ?? $data['supplier_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $description = $data['description'] ?? null;
        if (!$vendorId || !$amount)
            return $this->badRequest('Missing vendor or amount');

        try {
            // Use query() method which handles prepare internally
            $this->db->query(
                'INSERT INTO purchase_orders (supplier_id, total_amount, remarks, created_by, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$vendorId, $amount, $description, $user['id'] ?? null]
            );
            return $this->success(['id' => $this->db->getConnection()->lastInsertId()], 'Purchase order created');
        } catch (Exception $e) {
            return $this->error('Failed to create purchase order: ' . $e->getMessage());
        }
    }

    // PUT /api/vendors/{id} - update vendor
    public function putVendors($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10]))
            return $this->forbidden('Insufficient permissions');
        if (!$id) return $this->badRequest('Vendor ID required');

        try {
            $fields  = ['name','contact_person','phone','email','address','category',
                        'status','bank_name','account_number','notes'];
            $setClauses = [];
            $params     = [];
            foreach ($fields as $f) {
                if (array_key_exists($f, $data)) {
                    $setClauses[] = "$f = ?";
                    $params[]     = $data[$f];
                }
            }
            if (empty($setClauses)) return $this->badRequest('No fields to update');
            $params[] = $id;
            $this->db->query('UPDATE suppliers SET ' . implode(', ', $setClauses) . ', updated_at=NOW() WHERE id=?', $params);
            return $this->success(['id' => $id], 'Vendor updated');
        } catch (Exception $e) {
            return $this->error('Failed to update vendor: ' . $e->getMessage());
        }
    }

    // DELETE /api/vendors/{id} - soft delete vendor
    public function deleteVendors($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.manage', 'finance_manage'], [10]))
            return $this->forbidden('Insufficient permissions');
        if (!$id) return $this->badRequest('Vendor ID required');

        try {
            $this->db->query('UPDATE suppliers SET status=\'inactive\', updated_at=NOW() WHERE id=?', [$id]);
            return $this->success(['id' => $id], 'Vendor deactivated');
        } catch (Exception $e) {
            return $this->error('Failed to delete vendor: ' . $e->getMessage());
        }
    }

    // GET /api/vendors/outstanding-liabilities
    public function getOutstandingLiabilities($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['finance.view', 'finance_view'], [10]))
            return $this->forbidden('Insufficient permissions');

        try {
            $sql = "SELECT s.id as vendor_id, s.name as vendor,
                           COALESCE(SUM(CASE WHEN po.status != 'received' THEN po.total_amount ELSE 0 END),0) as outstanding
                    FROM suppliers s
                    LEFT JOIN purchase_orders po ON po.supplier_id = s.id
                    GROUP BY s.id
                    ORDER BY outstanding DESC";
            $stmt = $this->db->query($sql);
            return $this->success(['outstanding' => $stmt ? $stmt->fetchAll() : []]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch outstanding liabilities: ' . $e->getMessage());
        }
    }
}
