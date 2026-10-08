<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

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
                $where[] = "i.location_id = ?";
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

            $sql = "SELECT COUNT(*) FROM " . ReadReplicaService::qualifiedRef("inventory_transactions_items") . " it $whereClause";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    it.*,
                    it.item_name,
                    it.item_code
                FROM " . ReadReplicaService::qualifiedRef("inventory_transactions_items") . " it
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

            $this->db->beginTransaction();

            try {
                $quantity = (int) $data['quantity_change'];
                $direction = $quantity >= 0 ? 'in' : 'out';
                $absQuantity = abs($quantity);

                // Record adjustment transaction
                $sql = "
                    INSERT INTO inventory_transactions (
                        item_id, transaction_type, quantity,
                        reference_type, reference_id, notes, transaction_date
                    ) VALUES (?, ?, ?, 'adjustment', NULL, ?, NOW())
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    $data['item_id'],
                    $direction,
                    $absQuantity,
                    $data['reason']
                ]);

                $transactionId = $this->db->lastInsertId();

                // Update item stock
                $sql = "
                    UPDATE inventory_items 
                    SET 
                        current_quantity = current_quantity + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$quantity, $data['item_id']]);

                $this->db->commit();
                $this->logAction('create', $transactionId, "Stock adjustment: {$data['reason']}");

                return formatResponse(true, ['id' => $transactionId], 'Stock adjustment recorded');

            } catch (Exception $e) {
                $this->db->rollBack();
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
                    it.item_name,
                    it.item_code,
                    SUM(CASE WHEN it.transaction_type = 'in' THEN it.quantity ELSE 0 END) as total_in,
                    SUM(CASE WHEN it.transaction_type = 'out' THEN it.quantity ELSE 0 END) as total_out,
                    SUM(CASE WHEN it.transaction_type = 'in' THEN it.quantity ELSE -it.quantity END) as net_change,
                    it.current_stock
                FROM " . ReadReplicaService::qualifiedRef("inventory_transactions_items") . " it
                $whereClause
                GROUP BY it.item_id, it.item_name, it.item_code, it.current_stock
                ORDER BY it.item_name
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
