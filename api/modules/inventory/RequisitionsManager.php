<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Requisitions Manager
 * 
 * Manages inventory requisitions and purchase requests
 * Integrates with procurement workflow
 */
class RequisitionsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    /**
     * List requisitions with filtering
     */
    public function listRequisitions($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = ['1=1'];
            $bindings = [];

            // Status filter
            if (!empty($params['status'])) {
                $where[] = "r.status = ?";
                $bindings[] = $params['status'];
            }

            // Requisition type filter
            if (!empty($params['requisition_type'])) {
                $where[] = "r.requisition_type = ?";
                $bindings[] = $params['requisition_type'];
            }

            // Date range filter
            if (!empty($params['from_date'])) {
                $where[] = "r.requisition_date >= ?";
                $bindings[] = $params['from_date'];
            }
            if (!empty($params['to_date'])) {
                $where[] = "r.requisition_date <= ?";
                $bindings[] = $params['to_date'];
            }

            // Search
            if (!empty($search)) {
                $where[] = "(r.justification LIKE ? OR u.username LIKE ?)";
                $searchTerm = "%$search%";
                $bindings[] = $searchTerm;
                $bindings[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $where);

            // Count total
            $sql = "
                SELECT COUNT(DISTINCT r.id) 
                FROM inventory_requisitions r
                LEFT JOIN users u ON r.requested_by = u.id
                WHERE $whereClause
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get data
            $sql = "
                SELECT 
                    r.*,
                    u.username as requested_by_name,
                    u.email as requester_email,
                    COUNT(DISTINCT ri.id) as items_count,
                    wi.current_stage as workflow_stage,
                    wi.status as workflow_status
                FROM inventory_requisitions r
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN requisition_items ri ON r.id = ri.requisition_id
                LEFT JOIN workflow_instances wi ON r.workflow_instance_id = wi.id
                WHERE $whereClause
                GROUP BY r.id
                ORDER BY r.$sort $order
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
     * Get single requisition details
     */
    public function getRequisition($id)
    {
        try {
            $sql = "
                SELECT 
                    r.*,
                    u.username as requested_by_name,
                    u.email as requester_email,
                    wi.current_stage as workflow_stage,
                    wi.status as workflow_status,
                    wi.workflow_data
                FROM inventory_requisitions r
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN workflow_instances wi ON r.workflow_instance_id = wi.id
                WHERE r.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $requisition = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$requisition) {
                return formatResponse(false, null, 'Requisition not found');
            }

            // Get requisition items
            $sql = "
                SELECT 
                    ri.*,
                    i.item_name,
                    i.item_code,
                    i.unit_of_measure
                FROM requisition_items ri
                LEFT JOIN inventory_items i ON ri.item_id = i.id
                WHERE ri.requisition_id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $requisition['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get workflow history if exists
            if ($requisition['workflow_instance_id']) {
                $sql = "
                    SELECT 
                        wh.*,
                        u.username as performed_by_name
                    FROM workflow_history wh
                    LEFT JOIN users u ON wh.performed_by = u.id
                    WHERE wh.workflow_instance_id = ?
                    ORDER BY wh.created_at ASC
                ";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$requisition['workflow_instance_id']]);
                $requisition['workflow_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return formatResponse(true, $requisition);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create requisition (basic - without workflow)
     */
    public function createRequisition($data, $userId)
    {
        try {
            $this->beginTransaction();

            // Validate required fields
            $required = ['requisition_type', 'items'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->rollback();
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // Create requisition
            $sql = "
                INSERT INTO inventory_requisitions (
                    requested_by, requisition_date, status, justification,
                    requisition_type, priority, created_at
                ) VALUES (?, NOW(), 'pending', ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $data['justification'] ?? '',
                $data['requisition_type'],
                $data['priority'] ?? 'normal'
            ]);

            $requisitionId = $this->db->lastInsertId();

            // Create requisition items
            $sql = "
                INSERT INTO requisition_items (
                    requisition_id, item_id, quantity_requested, 
                    estimated_cost, notes
                ) VALUES (?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($data['items'] as $item) {
                $stmt->execute([
                    $requisitionId,
                    $item['item_id'],
                    $item['quantity'],
                    $item['estimated_cost'] ?? 0,
                    $item['notes'] ?? null
                ]);
            }

            $this->commit();
            $this->logAction('create', $requisitionId, "Created requisition #{$requisitionId}");

            return formatResponse(true, [
                'requisition_id' => $requisitionId
            ], 'Requisition created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Update requisition status
     */
    public function updateStatus($id, $status, $userId, $remarks = null)
    {
        try {
            $this->beginTransaction();

            $sql = "UPDATE inventory_requisitions SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $id]);

            if ($stmt->rowCount() === 0) {
                $this->rollback();
                return formatResponse(false, null, 'Requisition not found');
            }

            $this->commit();
            $this->logAction('update', $id, "Updated requisition #{$id} status to {$status}" . ($remarks ? ": $remarks" : ""));

            return formatResponse(true, ['requisition_id' => $id, 'status' => $status]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Delete requisition (soft delete)
     */
    public function deleteRequisition($id, $userId)
    {
        try {
            // Check if requisition can be deleted (only pending ones)
            $sql = "SELECT status, workflow_instance_id FROM inventory_requisitions WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $requisition = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$requisition) {
                return formatResponse(false, null, 'Requisition not found');
            }

            if ($requisition['status'] !== 'pending') {
                return formatResponse(false, null, 'Only pending requisitions can be deleted');
            }

            $this->beginTransaction();

            // Soft delete
            $sql = "UPDATE inventory_requisitions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $this->commit();
            $this->logAction('delete', $id, "Cancelled requisition #{$id}");

            return formatResponse(true, null, 'Requisition cancelled successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->rollback();
            }
            return $this->handleException($e);
        }
    }
}
