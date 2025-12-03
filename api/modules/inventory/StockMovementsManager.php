<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Stock Movements Manager
 * 
 * Tracks all inventory movements and transactions
 * Leverages database triggers for automatic tracking
 */
class StockMovementsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    /**
     * Record stock movement
     * Called by other managers or database triggers
     */
    public function recordMovement($data, $userId)
    {
        try {
            $this->db->beginTransaction();

            $sql = "
                INSERT INTO inventory_transactions (
                    item_id, transaction_type, quantity, unit_cost,
                    total_cost, reference_type, reference_id, location_id,
                    notes, transaction_date, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['item_id'],
                $data['transaction_type'],
                $data['quantity'],
                $data['unit_cost'] ?? 0,
                $data['total_cost'] ?? ($data['quantity'] * ($data['unit_cost'] ?? 0)),
                $data['reference_type'] ?? null,
                $data['reference_id'] ?? null,
                $data['location_id'] ?? null,
                $data['notes'] ?? null,
                $data['transaction_date'] ?? date('Y-m-d H:i:s'),
                $userId
            ]);

            $transactionId = $this->db->lastInsertId();

            // Update item quantity if not handled by trigger
            if (!empty($data['update_quantity'])) {
                $this->updateItemQuantity($data['item_id'], $data['transaction_type'], $data['quantity']);
            }

            $this->db->commit();
            $this->logAction('create', $transactionId, "Recorded stock movement: {$data['transaction_type']}");

            return formatResponse(true, ['transaction_id' => $transactionId]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Update item quantity based on transaction type
     */
    private function updateItemQuantity($itemId, $transactionType, $quantity)
    {
        $inboundTypes = ['purchase', 'return', 'adjustment_in', 'transfer_in'];
        $outboundTypes = ['sale', 'issue', 'adjustment_out', 'transfer_out', 'disposal'];

        if (in_array($transactionType, $inboundTypes)) {
            $sql = "UPDATE inventory_items SET quantity_on_hand = quantity_on_hand + ? WHERE id = ?";
        } elseif (in_array($transactionType, $outboundTypes)) {
            $sql = "UPDATE inventory_items SET quantity_on_hand = quantity_on_hand - ? WHERE id = ?";
        } else {
            return;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$quantity, $itemId]);
    }

    /**
     * Get stock movements with filtering
     */
    public function listMovements($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = ['1=1'];
            $bindings = [];

            // Item filter
            if (!empty($params['item_id'])) {
                $where[] = "t.item_id = ?";
                $bindings[] = $params['item_id'];
            }

            // Transaction type filter
            if (!empty($params['transaction_type'])) {
                $where[] = "t.transaction_type = ?";
                $bindings[] = $params['transaction_type'];
            }

            // Location filter
            if (!empty($params['location_id'])) {
                $where[] = "t.location_id = ?";
                $bindings[] = $params['location_id'];
            }

            // Date range filter
            if (!empty($params['from_date'])) {
                $where[] = "t.transaction_date >= ?";
                $bindings[] = $params['from_date'];
            }
            if (!empty($params['to_date'])) {
                $where[] = "t.transaction_date <= ?";
                $bindings[] = $params['to_date'];
            }

            // Search
            if (!empty($search)) {
                $where[] = "(i.item_name LIKE ? OR t.notes LIKE ?)";
                $searchTerm = "%$search%";
                $bindings[] = $searchTerm;
                $bindings[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $where);

            // Count total
            $sql = "
                SELECT COUNT(*) 
                FROM inventory_transactions t
                LEFT JOIN inventory_items i ON t.item_id = i.id
                WHERE $whereClause
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get data
            $sql = "
                SELECT 
                    t.*,
                    i.item_name,
                    i.item_code,
                    i.sku,
                    i.unit_of_measure,
                    l.location_name,
                    u.username as created_by_name
                FROM inventory_transactions t
                LEFT JOIN inventory_items i ON t.item_id = i.id
                LEFT JOIN locations l ON t.location_id = l.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE $whereClause
                ORDER BY t.$sort $order
                LIMIT ? OFFSET ?
            ";

            $bindings[] = $limit;
            $bindings[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'items' => $items,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get stock movement summary/analytics
     */
    public function getMovementSummary($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            // Date range filter
            if (!empty($params['from_date'])) {
                $where[] = "transaction_date >= ?";
                $bindings[] = $params['from_date'];
            }
            if (!empty($params['to_date'])) {
                $where[] = "transaction_date <= ?";
                $bindings[] = $params['to_date'];
            }

            $whereClause = implode(' AND ', $where);

            // Get summary by transaction type
            $sql = "
                SELECT 
                    transaction_type,
                    COUNT(*) as transaction_count,
                    SUM(quantity) as total_quantity,
                    SUM(total_cost) as total_value
                FROM inventory_transactions
                WHERE $whereClause
                GROUP BY transaction_type
                ORDER BY total_value DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get top items by movement volume
            $sql = "
                SELECT 
                    t.item_id,
                    i.item_name,
                    i.item_code,
                    COUNT(*) as movement_count,
                    SUM(t.quantity) as total_quantity,
                    SUM(t.total_cost) as total_value
                FROM inventory_transactions t
                LEFT JOIN inventory_items i ON t.item_id = i.id
                WHERE $whereClause
                GROUP BY t.item_id
                ORDER BY total_quantity DESC
                LIMIT 10
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get movement trends (daily)
            $sql = "
                SELECT 
                    DATE(transaction_date) as date,
                    transaction_type,
                    COUNT(*) as count,
                    SUM(quantity) as quantity,
                    SUM(total_cost) as value
                FROM inventory_transactions
                WHERE $whereClause
                GROUP BY DATE(transaction_date), transaction_type
                ORDER BY date DESC
                LIMIT 30
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'by_type' => $byType,
                'top_items' => $topItems,
                'trends' => $trends
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get item movement history
     */
    public function getItemHistory($itemId, $limit = 50)
    {
        try {
            $sql = "
                SELECT 
                    t.*,
                    l.location_name,
                    u.username as created_by_name
                FROM inventory_transactions t
                LEFT JOIN locations l ON t.location_id = l.id
                LEFT JOIN users u ON t.created_by = u.id
                WHERE t.item_id = ?
                ORDER BY t.transaction_date DESC, t.created_at DESC
                LIMIT ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$itemId, $limit]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $history);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Adjust stock levels (with reason)
     */
    public function adjustStock($data, $userId)
    {
        try {
            $this->db->beginTransaction();

            // Validate
            if (empty($data['item_id']) || empty($data['adjustment_type']) || empty($data['quantity'])) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Missing required fields');
            }

            // Record the adjustment
            $transactionType = $data['adjustment_type'] === 'increase' ? 'adjustment_in' : 'adjustment_out';

            $result = $this->recordMovement([
                'item_id' => $data['item_id'],
                'transaction_type' => $transactionType,
                'quantity' => $data['quantity'],
                'unit_cost' => $data['unit_cost'] ?? 0,
                'location_id' => $data['location_id'] ?? null,
                'notes' => $data['reason'] ?? 'Stock adjustment',
                'update_quantity' => true
            ], $userId);

            if (!$result['success']) {
                $this->db->rollBack();
                return $result;
            }

            $this->db->commit();

            return formatResponse(true, [
                'transaction_id' => $result['data']['transaction_id'],
                'adjustment_type' => $data['adjustment_type'],
                'quantity' => $data['quantity']
            ], 'Stock adjusted successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }
}
