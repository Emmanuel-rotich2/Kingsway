<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Inventory Items Manager
 * 
 * Manages CRUD operations for inventory_items table
 * Leverages stored procedures and database triggers for automation
 */
class InventoryItemsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    /**
     * List inventory items with advanced filtering
     * @param array $params Filter parameters
     * @return array Response
     */
    public function listItems($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = [];
            $bindings = [];

            // Search filter
            if (!empty($search)) {
                $where[] = "(i.item_name LIKE ? OR i.item_code LIKE ? OR i.barcode LIKE ? OR i.sku LIKE ?)";
                $searchTerm = "%$search%";
                $bindings = array_merge($bindings, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }

            // Category filter
            if (!empty($params['category_id'])) {
                $where[] = "i.category_id = ?";
                $bindings[] = $params['category_id'];
            }

            // Location filter
            if (!empty($params['location_id'])) {
                $where[] = "i.location_id = ?";
                $bindings[] = $params['location_id'];
            }

            // Status filter
            if (!empty($params['status'])) {
                $where[] = "i.status = ?";
                $bindings[] = $params['status'];
            }

            // Low stock filter
            if (!empty($params['low_stock'])) {
                $where[] = "i.quantity_on_hand <= i.reorder_level";
            }

            // Out of stock filter
            if (!empty($params['out_of_stock'])) {
                $where[] = "i.quantity_on_hand = 0";
            }

            // Expiring soon filter (within 30 days)
            if (!empty($params['expiring_soon'])) {
                $where[] = "i.expiry_date IS NOT NULL AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM inventory_items i
                $whereClause
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    i.*,
                    c.category_name,
                    l.location_name,
                    s.supplier_name,
                    (i.quantity_on_hand * i.unit_cost) as total_value,
                    CASE 
                        WHEN i.quantity_on_hand = 0 THEN 'Out of Stock'
                        WHEN i.quantity_on_hand <= i.reorder_level THEN 'Low Stock'
                        ELSE 'In Stock'
                    END as stock_status,
                    DATEDIFF(i.expiry_date, CURDATE()) as days_to_expiry
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                LEFT JOIN inventory_locations l ON i.location_id = l.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                $whereClause
                ORDER BY i.$sort $order
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'items' => $items,
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

    /**
     * Get single inventory item with full details
     * @param int $id Item ID
     * @return array Response
     */
    public function getItem($id)
    {
        try {
            $sql = "
                SELECT 
                    i.*,
                    c.category_name,
                    l.location_name,
                    s.supplier_name,
                    s.contact_person,
                    s.email as supplier_email,
                    s.phone as supplier_phone,
                    (i.quantity_on_hand * i.unit_cost) as total_value,
                    i.last_purchase_date,
                    i.last_purchase_price,
                    i.last_audit_date
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                LEFT JOIN inventory_locations l ON i.location_id = l.id
                LEFT JOIN suppliers s ON i.supplier_id = s.id
                WHERE i.id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return formatResponse(false, null, 'Inventory item not found', 404);
            }

            // Get transaction history
            $stmt = $this->db->prepare("
                SELECT * FROM inventory_transactions 
                WHERE item_id = ? 
                ORDER BY transaction_date DESC 
                LIMIT 20
            ");
            $stmt->execute([$id]);
            $item['recent_transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get stock movements summary
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(CASE WHEN transaction_type IN ('purchase', 'adjustment_in') THEN quantity ELSE 0 END) as total_in,
                    SUM(CASE WHEN transaction_type IN ('issue', 'adjustment_out', 'sale') THEN quantity ELSE 0 END) as total_out
                FROM inventory_transactions
                WHERE item_id = ?
            ");
            $stmt->execute([$id]);
            $item['stock_movements'] = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, $item);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create new inventory item
     * Uses sp_add_item_to_inventory if available
     * @param array $data Item data
     * @return array Response
     */
    public function createItem($data)
    {
        try {
            // Validate required fields
            $required = ['item_name', 'item_code', 'category_id', 'unit_of_measure', 'reorder_level'];
            $missing = [];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            // Check if item code already exists
            $stmt = $this->db->prepare("SELECT id FROM inventory_items WHERE item_code = ?");
            $stmt->execute([$data['item_code']]);
            if ($stmt->fetch()) {
                return formatResponse(false, null, 'Item code already exists');
            }

            $this->beginTransaction();

            // Try using stored procedure first
            if ($this->routineExists('sp_add_item_to_inventory', 'PROCEDURE')) {
                $stmt = $this->db->prepare("CALL sp_add_item_to_inventory(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @new_item_id)");
                $stmt->execute([
                    $data['item_name'],
                    $data['item_code'],
                    $data['category_id'],
                    $data['description'] ?? null,
                    $data['unit_of_measure'],
                    $data['quantity_on_hand'] ?? 0,
                    $data['unit_cost'] ?? 0,
                    $data['reorder_level'],
                    $data['location_id'] ?? null,
                    $data['supplier_id'] ?? null,
                    $data['barcode'] ?? null,
                    $data['sku'] ?? null,
                    $data['expiry_date'] ?? null
                ]);

                $result = $this->db->query("SELECT @new_item_id as id")->fetch(PDO::FETCH_ASSOC);
                $itemId = $result['id'];

            } else {
                // Fallback to direct insert
                $sql = "
                    INSERT INTO inventory_items (
                        item_name, item_code, category_id, description,
                        unit_of_measure, quantity_on_hand, unit_cost,
                        reorder_level, location_id, supplier_id,
                        barcode, sku, expiry_date, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['item_name'],
                    $data['item_code'],
                    $data['category_id'],
                    $data['description'] ?? null,
                    $data['unit_of_measure'],
                    $data['quantity_on_hand'] ?? 0,
                    $data['unit_cost'] ?? 0,
                    $data['reorder_level'],
                    $data['location_id'] ?? null,
                    $data['supplier_id'] ?? null,
                    $data['barcode'] ?? null,
                    $data['sku'] ?? null,
                    $data['expiry_date'] ?? null
                ]);
                $itemId = $this->db->lastInsertId();
            }

            $this->commit();
            $this->logAction('create', $itemId, "Created inventory item: {$data['item_name']}");
            $this->emitEvent('inventory_item_created', ['item_id' => $itemId, 'item_name' => $data['item_name']]);

            return formatResponse(true, ['id' => $itemId], 'Inventory item created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Update inventory item
     * @param int $id Item ID
     * @param array $data Update data
     * @return array Response
     */
    public function updateItem($id, $data)
    {
        try {
            // Check if item exists
            $stmt = $this->db->prepare("SELECT id, item_name FROM inventory_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return formatResponse(false, null, 'Inventory item not found', 404);
            }

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = [
                'item_name',
                'category_id',
                'description',
                'unit_of_measure',
                'unit_cost',
                'reorder_level',
                'location_id',
                'supplier_id',
                'barcode',
                'sku',
                'expiry_date',
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
            $sql = "UPDATE inventory_items SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $id, "Updated inventory item: {$item['item_name']}");
            $this->emitEvent('inventory_item_updated', ['item_id' => $id]);

            return formatResponse(true, null, 'Inventory item updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete inventory item (soft delete)
     * @param int $id Item ID
     * @return array Response
     */
    public function deleteItem($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, item_name FROM inventory_items WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return formatResponse(false, null, 'Inventory item not found', 404);
            }

            // Soft delete - set status to inactive
            $stmt = $this->db->prepare("UPDATE inventory_items SET status = 'inactive', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);

            $this->logAction('delete', $id, "Deleted inventory item: {$item['item_name']}");
            $this->emitEvent('inventory_item_deleted', ['item_id' => $id]);

            return formatResponse(true, null, 'Inventory item deleted successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get low stock items
     * @return array Response
     */
    public function getLowStock()
    {
        try {
            $sql = "
                SELECT 
                    i.*,
                    c.category_name,
                    l.location_name,
                    (i.reorder_level - i.quantity_on_hand) as shortage_quantity
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                LEFT JOIN inventory_locations l ON i.location_id = l.id
                WHERE i.quantity_on_hand <= i.reorder_level
                AND i.status = 'active'
                ORDER BY (i.reorder_level - i.quantity_on_hand) DESC
            ";
            $stmt = $this->db->query($sql);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['items' => $items, 'count' => count($items)]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get expiring items
     * @param int $days Days to expiry threshold
     * @return array Response
     */
    public function getExpiringItems($days = 30)
    {
        try {
            $sql = "
                SELECT 
                    i.*,
                    c.category_name,
                    l.location_name,
                    DATEDIFF(i.expiry_date, CURDATE()) as days_to_expiry
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                LEFT JOIN inventory_locations l ON i.location_id = l.id
                WHERE i.expiry_date IS NOT NULL
                AND i.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND i.status = 'active'
                ORDER BY i.expiry_date ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$days]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['items' => $items, 'count' => count($items)]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get stock valuation
     * @return array Response
     */
    public function getStockValuation()
    {
        try {
            $sql = "
                SELECT 
                    c.category_name,
                    COUNT(i.id) as item_count,
                    SUM(i.quantity_on_hand) as total_quantity,
                    SUM(i.quantity_on_hand * i.unit_cost) as total_value
                FROM inventory_items i
                LEFT JOIN inventory_categories c ON i.category_id = c.id
                WHERE i.status = 'active'
                GROUP BY c.id
                ORDER BY total_value DESC
            ";
            $stmt = $this->db->query($sql);
            $valuation = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get overall total
            $stmt = $this->db->query("
                SELECT SUM(quantity_on_hand * unit_cost) as grand_total 
                FROM inventory_items 
                WHERE status = 'active'
            ");
            $grandTotal = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'by_category' => $valuation,
                'grand_total' => $grandTotal['grand_total'] ?? 0
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
