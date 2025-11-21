<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Inventory Transactions Manager
 * 
 * Manages stock movements, adjustments, and transaction history
 */
class TransactionsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    public function listTransactions($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();

            $where = [];
            $bindings = [];

            if (!empty($params['item_id'])) {
                $where[] = "it.item_id = ?";
                $bindings[] = $params['item_id'];
            }

            if (!empty($params['transaction_type'])) {
                $where[] = "it.transaction_type = ?";
                $bindings[] = $params['transaction_type'];
            }

            if (!empty($params['location_id'])) {
                $where[] = "it.location_id = ?";
                $bindings[] = $params['location_id'];
            }

            if (!empty($params['from_date'])) {
                $where[] = "DATE(it.transaction_date) >= ?";
                $bindings[] = $params['from_date'];
            }

            if (!empty($params['to_date'])) {
                $where[] = "DATE(it.transaction_date) <= ?";
                $bindings[] = $params['to_date'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT COUNT(*) FROM inventory_transactions it $whereClause";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    it.*,
                    i.item_name,
                    i.item_code,
                    l.location_name,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name
                FROM inventory_transactions it
                LEFT JOIN inventory_items i ON it.item_id = i.id
                LEFT JOIN inventory_locations l ON it.location_id = l.id
                LEFT JOIN users u ON it.created_by = u.id
                $whereClause
                ORDER BY it.transaction_date DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'transactions' => $transactions,
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

    public function createStockAdjustment($data)
    {
        try {
            $required = ['item_id', 'location_id', 'quantity_change', 'reason'];
            $missing = [];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->beginTransaction();

            try {
                // Record adjustment transaction
                $sql = "
                    INSERT INTO inventory_transactions (
                        item_id, location_id, transaction_type, quantity_change,
                        reference_type, reference_id, notes, created_by, transaction_date
                    ) VALUES (?, ?, 'adjustment', ?, 'manual_adjustment', NULL, ?, ?, NOW())
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['item_id'],
                    $data['location_id'],
                    $data['quantity_change'],
                    $data['reason'],
                    $this->getCurrentUserId()
                ]);

                $transactionId = $this->db->lastInsertId();

                // Update item stock
                $sql = "
                    UPDATE inventory_items 
                    SET 
                        quantity_on_hand = quantity_on_hand + ?,
                        last_updated = NOW()
                    WHERE id = ?
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$data['quantity_change'], $data['item_id']]);

                $this->commit();
                $this->logAction('create', $transactionId, "Stock adjustment: {$data['reason']}");

                return formatResponse(true, ['id' => $transactionId], 'Stock adjustment recorded');

            } catch (Exception $e) {
                $this->rollback();
                throw $e;
            }

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStockMovementReport($params = [])
    {
        try {
            $where = [];
            $bindings = [];

            if (!empty($params['item_id'])) {
                $where[] = "it.item_id = ?";
                $bindings[] = $params['item_id'];
            }

            if (!empty($params['from_date'])) {
                $where[] = "DATE(it.transaction_date) >= ?";
                $bindings[] = $params['from_date'];
            }

            if (!empty($params['to_date'])) {
                $where[] = "DATE(it.transaction_date) <= ?";
                $bindings[] = $params['to_date'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "
                SELECT 
                    i.item_name,
                    i.item_code,
                    SUM(CASE WHEN it.quantity_change > 0 THEN it.quantity_change ELSE 0 END) as total_in,
                    SUM(CASE WHEN it.quantity_change < 0 THEN ABS(it.quantity_change) ELSE 0 END) as total_out,
                    SUM(it.quantity_change) as net_change,
                    i.quantity_on_hand as current_stock
                FROM inventory_transactions it
                JOIN inventory_items i ON it.item_id = i.id
                $whereClause
                GROUP BY i.id
                ORDER BY i.item_name
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['movements' => $movements]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
