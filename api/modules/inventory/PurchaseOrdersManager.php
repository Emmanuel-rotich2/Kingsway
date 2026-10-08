<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

/**
 * Purchase Orders Manager
 * 
 * Manages purchase order operations
 * Integrates with procurement workflow
 */
class PurchaseOrdersManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    public function listPurchaseOrders($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = [];
            $bindings = [];

            if (!empty($search)) {
                $where[] = "(po.order_number LIKE ? OR s.supplier_name LIKE ?)";
                $searchTerm = "%$search%";
                $bindings = array_merge($bindings, [$searchTerm, $searchTerm]);
            }

            if (!empty($params['status'])) {
                $where[] = "po.status = ?";
                $bindings[] = $params['status'];
            }

            if (!empty($params['supplier_id'])) {
                $where[] = "po.supplier_id = ?";
                $bindings[] = $params['supplier_id'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT COUNT(*) FROM purchase_orders po $whereClause";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            $sql = "
                SELECT 
                    po.*,
                    s.supplier_name,
                    s.contact_person,
                    s.email as supplier_email,
                    COUNT(DISTINCT t.id) as item_count
                FROM " . ReadReplicaService::qualifiedRef("purchase_orders") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON po.supplier_id = s.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_transactions") . " t ON t.reference_type = 'purchase' AND t.reference_id = po.id
                $whereClause
                GROUP BY po.id
                ORDER BY po.order_date DESC
                LIMIT ? OFFSET ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'orders' => $orders,
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

    public function getPurchaseOrder($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    po.*,
                    s.supplier_name,
                    s.contact_person,
                    s.email,
                    s.phone,
                    s.address
                FROM " . ReadReplicaService::qualifiedRef("inventory_transactions") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON po.supplier_id = s.id
                WHERE po.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return formatResponse(false, null, 'Purchase order not found', 404);
            }

            // Get PO items (recorded as purchase stock movements against the PO)
            $stmt = $this->db->prepare("
                SELECT 
                    t.id,
                    t.item_id,
                    t.quantity,
                    t.unit_cost,
                    t.notes,
                    i.item_name,
                    i.code AS item_code
                FROM " . ReadReplicaService::qualifiedRef("inventory_transactions") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("inventory_items") . " i ON t.item_id = i.id
                WHERE t.reference_type = 'purchase' AND t.reference_id = ?
            ");
            $stmt->execute([$id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $order);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a new purchase order for a supplier.
     */
    public function createPurchaseOrder($data, $userId = null)
    {
        try {
            $supplierId = $data['supplier_id'] ?? $data['vendor_id'] ?? null;
            $amount     = $data['total_amount'] ?? $data['amount'] ?? null;

            if (!$supplierId || !$amount) {
                return formatResponse(false, null, 'Missing supplier or amount');
            }

            $number = $this->nextOrderNumber();
            $sql = "
                INSERT INTO purchase_orders (
                    supplier_id, order_number, order_date, total_amount,
                    payment_terms, remarks, status, created_by, created_at
                ) VALUES (?, ?, NOW(), ?, ?, ?, 'draft', ?, NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $supplierId,
                $number,
                $amount,
                $data['payment_terms'] ?? 'Net 30',
                $data['remarks'] ?? $data['description'] ?? null,
                $userId
            ]);

            $poId = $this->db->lastInsertId();
            $this->logAction('create', $poId, "Created purchase order {$number} for supplier {$supplierId}");

            return formatResponse(true, ['id' => $poId, 'order_number' => $number], 'Purchase order created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    private function nextOrderNumber()
    {
        $stmt = $this->db->query("SELECT MAX(CAST(SUBSTRING(order_number, 4) AS UNSIGNED)) FROM purchase_orders");
        $max = $stmt ? (int) $stmt->fetchColumn() : 0;
        return 'PO-' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    public function updatePOStatus($id, $status, $remarks = null)
    {
        try {
            $validStatuses = ['draft', 'pending', 'approved', 'ordered', 'received', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                return formatResponse(false, null, 'Invalid status');
            }

            $stmt = $this->db->prepare("
                UPDATE purchase_orders 
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $id]);

            $this->logAction('update', $id, "Updated PO status to: $status. $remarks");

            return formatResponse(true, null, 'Purchase order status updated');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
