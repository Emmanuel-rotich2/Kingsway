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
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('finance.view', (array) $perms) && !in_array(10, (array) $user['roles'])) {
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
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('finance.manage', (array) $perms) && !in_array(10, (array) $user['roles'])) {
            return $this->forbidden('Insufficient permissions');
        }

        $name = $data['name'] ?? null;
        $contact = $data['contact'] ?? null;
        $phone = $data['phone'] ?? null;
        $email = $data['email'] ?? null;
        $address = $data['address'] ?? null;
        if (!$name)
            return $this->badRequest('Missing vendor name');

        try {
            // Use query() method which handles prepare internally
            $this->db->query(
                'INSERT INTO suppliers (name, contact_person, phone, email, address, status, created_at) VALUES (?, ?, ?, ?, ?, "active", NOW())',
                [$name, $contact, $phone, $email, $address]
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
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('finance.view', (array) $perms) && !in_array(10, (array) $user['roles'])) {
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
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('finance.manage', (array) $perms) && !in_array(10, (array) $user['roles'])) {
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

    // GET /api/vendors/outstanding - outstanding liabilities
    public function getOutstandingLiabilities($id = null, $data = [], $segments = [])
    {
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        $perms = $user['effective_permissions'] ?? [];
        if (!in_array('finance.view', (array) $perms) && !in_array(10, (array) $user['roles'])) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            // vendor_invoices table not present; approximate outstanding by summing pending/ordered purchase orders
            $sql = "SELECT s.id as vendor_id, s.name as vendor, COALESCE(SUM(po.total_amount),0) as outstanding FROM suppliers s LEFT JOIN purchase_orders po ON po.supplier_id = s.id AND po.status != 'received' GROUP BY s.id ORDER BY outstanding DESC";
            $stmt = $this->db->query($sql);
            $rows = $stmt ? $stmt->fetchAll() : [];
            return $this->success(['outstanding' => $rows]);
        } catch (Exception $e) {
            return $this->error('Failed to fetch outstanding liabilities: ' . $e->getMessage());
        }
    }
}
