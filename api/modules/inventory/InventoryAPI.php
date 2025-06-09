<?php

namespace App\API\Modules\inventory;

require_once __DIR__ . '/../../includes/BaseAPI.php';

use App\API\Includes\BaseAPI;

use PDO;
use Exception;

class InventoryAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    // List inventory adjustments with pagination and filtering
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE i.name LIKE ? OR c.name LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM inventory_adjustments ia
                JOIN inventory_items i ON ia.item_id = i.id
                JOIN inventory_categories c ON i.category_id = c.id
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    ia.*,
                    i.name as item_name,
                    c.name as category_name
                FROM inventory_adjustments ia
                JOIN inventory_items i ON ia.item_id = i.id
                JOIN inventory_categories c ON i.category_id = c.id
                $where
                ORDER BY ia.id DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get single inventory adjustment
    public function get($id)
    {
        try {
            $sql = "
                SELECT 
                    ia.*,
                    c.name as category_name
                FROM inventory_adjustments ia
                LEFT JOIN inventory_categories c ON ia.category_id = c.id
                WHERE ia.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return $this->response(['status' => 'error', 'message' => 'Inventory record not found'], 404);
            }

            return $this->response(['status' => 'success', 'data' => $item]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new inventory adjustment
    public function create($data)
    {
        try {
            // Validate required fields
            $required = ['category_id', 'description', 'quantity', 'unit_cost'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->beginTransaction();

            // Insert inventory adjustment record
            $sql = "
                INSERT INTO inventory_adjustments (
                    category_id,
                    description,
                    quantity,
                    unit_cost,
                    adjustment_date,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW(), ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['category_id'],
                $data['description'],
                $data['quantity'],
                $data['unit_cost'],
                $_SESSION['user_id'] ?? null
            ]);

            $adjustmentId = $this->db->lastInsertId();

            $this->commit();

            // Log the action
            $this->logAction('create', $adjustmentId, "Created new inventory adjustment: {$data['description']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Inventory adjustment created successfully',
                'data' => ['id' => $adjustmentId]
            ], 201);
        } catch (Exception $e) {
            $this->rollBack();
            return $this->handleException($e);
        }
    }

    // Update inventory adjustment
    public function update($id, $data)
    {
        try {
            // Check if adjustment exists
            $stmt = $this->db->prepare("SELECT id, description FROM inventory_adjustments WHERE id = ?");
            $stmt->execute([$id]);
            $adjustment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adjustment) {
                return $this->response(['status' => 'error', 'message' => 'Adjustment not found'], 404);
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = [
                'category_id',
                'description',
                'quantity',
                'unit_cost'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE inventory_adjustments SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Log the action
            $this->logAction('update', $id, "Updated inventory adjustment: {$adjustment['description']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Inventory adjustment updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Delete inventory adjustment
    public function delete($id)
    {
        try {
            // Check if adjustment exists
            $stmt = $this->db->prepare("SELECT id, description FROM inventory_adjustments WHERE id = ?");
            $stmt->execute([$id]);
            $adjustment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adjustment) {
                return $this->response(['status' => 'error', 'message' => 'Adjustment not found'], 404);
            }

            // Delete the adjustment
            $stmt = $this->db->prepare("DELETE FROM inventory_adjustments WHERE id = ?");
            $stmt->execute([$id]);

            // Log the action
            $this->logAction('delete', $id, "Deleted inventory adjustment: {$adjustment['description']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Inventory adjustment deleted successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get inventory counts
    public function getInventoryCounts()
    {
        try {
            $sql = "
                SELECT 
                    c.id as category_id,
                    c.name as category_name,
                    SUM(CASE WHEN ia.quantity > 0 THEN ia.quantity ELSE 0 END) as total_received,
                    SUM(CASE WHEN ia.quantity < 0 THEN -ia.quantity ELSE 0 END) as total_issued,
                    SUM(ia.quantity) as current_quantity
                FROM inventory_categories c
                LEFT JOIN inventory_adjustments ia ON c.id = ia.category_id
                GROUP BY c.id
                ORDER BY c.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $counts
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    public function getLowStock()
    {
        $stmt = $this->db->query("SELECT * FROM inventory_items WHERE current_quantity <= minimum_quantity");
        return ['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function getStockValuation()
    {
        $stmt = $this->db->query("SELECT SUM(current_quantity * unit_cost) as total_value FROM inventory_items");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['status' => 'success', 'data' => ['total_value' => $row['total_value'] ?? 0]];
    }

    // Create a new inventory item
    public function createItem($data)
    {
        try {
            // Validate required fields
            $required = ['category_id', 'name', 'code', 'unit', 'minimum_quantity', 'current_quantity', 'unit_cost', 'status'];
            $missing = [];
            foreach ($required as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $missing[] = $field;
                }
            }
            if (!empty($missing)) {
                return ['status' => 'error', 'message' => 'Missing required fields: ' . implode(', ', $missing)];
            }
            $sql = "INSERT INTO inventory_items (category_id, name, code, barcode, sku, description, unit, minimum_quantity, current_quantity, unit_cost, location, status, brand, model, reorder_level, expiry_date, batch_tracking, serial_tracking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['category_id'],
                $data['name'],
                $data['code'],
                $data['barcode'] ?? null,
                $data['sku'] ?? null,
                $data['description'] ?? null,
                $data['unit'],
                $data['minimum_quantity'],
                $data['current_quantity'],
                $data['unit_cost'],
                $data['location'] ?? null,
                $data['status'],
                $data['brand'] ?? null,
                $data['model'] ?? null,
                $data['reorder_level'] ?? 0,
                $data['expiry_date'] ?? null,
                $data['batch_tracking'] ?? 0,
                $data['serial_tracking'] ?? 0
            ]);
            return ['status' => 'success', 'message' => 'Item created successfully'];
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Only allow adjustments for valid item_id
    public function recordTransaction($data)
    {
        // Validate required fields
        if (empty($data['item_id']) || !isset($data['quantity_change']) || empty($data['reason'])) {
            return ['status' => 'error', 'message' => 'Missing required fields'];
        }
        // Check if item_id exists
        $stmt = $this->db->prepare("SELECT id FROM inventory_items WHERE id = ?");
        $stmt->execute([$data['item_id']]);
        if (!$stmt->fetch()) {
            return ['status' => 'error', 'message' => 'Invalid item_id'];
        }
        // Insert adjustment
        $stmt = $this->db->prepare("INSERT INTO inventory_adjustments (item_id, quantity_change, reason, adjusted_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $data['item_id'],
            $data['quantity_change'],
            $data['reason'],
            $data['adjusted_by'] ?? 1
        ]);
        // Update inventory_items current_quantity
        $stmt = $this->db->prepare("UPDATE inventory_items SET current_quantity = current_quantity + ? WHERE id = ?");
        $stmt->execute([
            $data['quantity_change'],
            $data['item_id']
        ]);
        return ['status' => 'success', 'message' => 'Transaction recorded'];
    }
}
