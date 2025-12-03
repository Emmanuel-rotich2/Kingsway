<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

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
                $where[] = "(po.po_number LIKE ? OR s.supplier_name LIKE ?)";
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
                    COUNT(DISTINCT poi.id) as item_count
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                LEFT JOIN purchase_order_items poi ON po.id = poi.po_id
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
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                WHERE po.id = ?
            ");
            $stmt->execute([$id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                return formatResponse(false, null, 'Purchase order not found', 404);
            }

            // Get PO items
            $stmt = $this->db->prepare("
                SELECT 
                    poi.*,
                    i.item_name,
                    i.item_code
                FROM purchase_order_items poi
                LEFT JOIN inventory_items i ON poi.item_id = i.id
                WHERE poi.po_id = ?
            ");
            $stmt->execute([$id]);
            $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, $order);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updatePOStatus($id, $status, $remarks = null)
    {
        try {
            $validStatuses = ['draft', 'sent', 'confirmed', 'partially_received', 'received', 'cancelled'];
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
