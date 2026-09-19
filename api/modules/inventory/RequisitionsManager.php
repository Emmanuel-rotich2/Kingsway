<?php
namespace App\API\Modules\inventory;

use App\API\Includes\BaseAPI;
use App\API\Services\NotificationService;
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
                $where[] = "(r.notes LIKE ? OR u.username LIKE ?)";
                $searchTerm = "%$search%";
                $bindings[] = $searchTerm;
                $bindings[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $where);

            // Count total
            $sql = "
                SELECT COUNT(DISTINCT r.id) 
                FROM requisitions r
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
                    CONCAT(p.first_name, ' ', p.last_name) as requested_by_name,
                    p.email as requester_email,
                    COUNT(DISTINCT ri.id) as items_count
                FROM requisitions r
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN persons p ON p.id = u.person_id
                LEFT JOIN requisition_items ri ON r.id = ri.requisition_id
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
                    CONCAT(p.first_name, ' ', p.last_name) as requested_by_name,
                    p.email as requester_email
                FROM requisitions r
                LEFT JOIN users u ON r.requested_by = u.id
                LEFT JOIN persons p ON p.id = u.person_id
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
                    i.code as item_code,
                    i.unit as unit_of_measure
                FROM requisition_items ri
                LEFT JOIN inventory_items i ON ri.item_id = i.id
                WHERE ri.requisition_id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $requisition['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
            $this->db->beginTransaction();

            // Validate required fields
            $required = ['items'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $this->db->rollBack();
                    return formatResponse(false, null, "Missing required field: $field");
                }
            }

            // Create requisition
            $priority = ['low' => 'low', 'normal' => 'medium', 'medium' => 'medium', 'high' => 'high', 'urgent' => 'urgent'];
            $priority = $priority[$data['priority'] ?? 'normal'] ?? 'medium';
            $sql = "
                INSERT INTO requisitions (
                    requisition_number, requested_by, requisition_date, status, notes,
                    priority
                ) VALUES (?, ?, NOW(), 'pending', ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'REQ-' . uniqid(),
                $userId,
                $data['justification'] ?? '',
                $priority
            ]);
            $requisitionId = (int) $this->db->lastInsertId();

            // Create requisition items
            $sql = "
                INSERT INTO requisition_items (
                    requisition_id, item_id, requested_quantity, 
                    unit, unit_cost, notes
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            foreach ($data['items'] as $item) {
                $stmt->execute([
                    $requisitionId,
                    $item['item_id'],
                    $item['quantity'],
                    $item['unit'] ?? 'pcs',
                    $item['estimated_cost'] ?? 0,
                    $item['notes'] ?? null
                ]);
            }

            $this->db->commit();
            $this->logAction('create', $requisitionId, "Created requisition #{$requisitionId}");

            return formatResponse(true, [
                'requisition_id' => $requisitionId
            ], 'Requisition created successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
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
            $this->db->beginTransaction();

            $fetch = $this->db->prepare("SELECT id, requisition_number, requested_by FROM requisitions WHERE id = ?");
            $fetch->execute([$id]);
            $requisition = $fetch->fetch(PDO::FETCH_ASSOC);

            $sql = "UPDATE requisitions SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $id]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Requisition not found');
            }

            $this->db->commit();
            $this->logAction('update', $id, "Updated requisition #{$id} status to {$status}" . ($remarks ? ": $remarks" : ""));

            $this->notifyRequisitionStatus($requisition, $status, (int) $userId, $remarks);

            return formatResponse(true, ['requisition_id' => $id, 'status' => $status]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
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
            $sql = "SELECT status FROM requisitions WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $requisition = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$requisition) {
                return formatResponse(false, null, 'Requisition not found');
            }

            if ($requisition['status'] !== 'pending') {
                return formatResponse(false, null, 'Only pending requisitions can be deleted');
            }

            $this->db->beginTransaction();

            // Soft delete
            $sql = "UPDATE requisitions SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $this->db->commit();
            $this->logAction('delete', $id, "Cancelled requisition #{$id}");

            return formatResponse(true, null, 'Requisition cancelled successfully');

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return $this->handleException($e);
        }
    }

    /**
     * Notify the requisition requester of an approved/rejected/fulfilled outcome.
     */
    private function notifyRequisitionStatus(?array $requisition, string $status, int $actorUserId, $remarks): void
    {
        if (empty($requisition) || empty($requisition['requested_by']) || !in_array($status, ['approved', 'rejected', 'fulfilled'], true)) {
            return;
        }
        try {
            $service = new NotificationService($this->db);
            $requester = (int) $requisition['requested_by'];
            $actor = $service->userName($actorUserId) ?: 'the approver';
            $label = 'requisition ' . ($requisition['requisition_number'] ?? ('#' . (int) $requisition['id']));

            if ($status === 'approved') {
                $title = 'Requisition approved';
                $message = NotificationService::approvedText($label, $actor);
            } elseif ($status === 'fulfilled') {
                $title = 'Requisition fulfilled';
                $message = 'Your ' . $label . ' has been fulfilled.';
            } else {
                $title = 'Requisition declined';
                $message = NotificationService::deniedText($label, $actor, (string) ($remarks ?? ''));
            }

            $service->push([$requester], 'requisition', $title, $message, 'medium');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[RequisitionsManager] Notification push failed: ' . $e->getMessage());
        }
    }
}
