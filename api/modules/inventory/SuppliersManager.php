<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Suppliers Manager
 * 
 * Manages supplier operations and relationships
 */
class SuppliersManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    public function listSuppliers($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE supplier_name LIKE ? OR email LIKE ? OR phone LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm, $searchTerm];
            }

            $sql = "SELECT COUNT(*) FROM suppliers $where";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    s.*,
                    COUNT(DISTINCT po.id) as total_orders,
                    SUM(po.total_amount) as total_purchase_value
                FROM suppliers s
                LEFT JOIN purchase_orders po ON s.id = po.supplier_id
                $where
                GROUP BY s.id
                ORDER BY s.$sort $order
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'suppliers' => $suppliers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSupplier($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return formatResponse(false, null, 'Supplier not found', 404);
            }

            // Get recent purchase orders
            $stmt = $this->db->prepare("
                SELECT * FROM purchase_orders 
                WHERE supplier_id = ? 
                ORDER BY order_date DESC 
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $supplier['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $supplier);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createSupplier($data)
    {
        try {
            $required = ['supplier_name', 'contact_person', 'email', 'phone'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $sql = "
                INSERT INTO suppliers (
                    supplier_name, contact_person, email, phone, address,
                    city, country, payment_terms, rating, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['supplier_name'],
                $data['contact_person'],
                $data['email'],
                $data['phone'],
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['country'] ?? 'Kenya',
                $data['payment_terms'] ?? 'Net 30',
                $data['rating'] ?? 0
            ]);

            $supplierId = $this->db->lastInsertId();
            $this->logAction('create', $supplierId, "Created supplier: {$data['supplier_name']}");

            return formatResponse(true, ['id' => $supplierId], 'Supplier created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateSupplier($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, supplier_name FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier) {
                return formatResponse(false, null, 'Supplier not found', 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = [
                'supplier_name',
                'contact_person',
                'email',
                'phone',
                'address',
                'city',
                'country',
                'payment_terms',
                'rating',
                'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $id;
            $sql = "UPDATE suppliers SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $id, "Updated supplier: {$supplier['supplier_name']}");

            return formatResponse(true, null, 'Supplier updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
