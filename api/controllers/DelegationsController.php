<?php
namespace App\API\Controllers;

use App\API\Services\DelegationService;
use App\API\Modules\users\UserPermissionManager;
use Exception;

class DelegationsController extends BaseController
{
    private DelegationService $delegationService;
    private UserPermissionManager $permissionManager;

    public function __construct()
    {
        parent::__construct();
        $this->delegationService = new DelegationService();
        $this->permissionManager = new UserPermissionManager($this->db->getConnection());
    }

    // GET /api/delegations or GET /api/delegations/{id}
    public function get($id = null, $data = [], $segments = [])
    {
        // Authorization: require 'manage_delegations' permission
        $userId = $_SERVER['auth_user']['id'] ?? null;
        $permCheck = $this->permissionManager->hasPermission($userId, 'manage_delegations');
        if (!$permCheck['success'] || !$permCheck['has_permission']) {
            return $this->forbidden('Insufficient permissions');
        }

        if ($id) {
            $stmt = $this->db->getConnection()->prepare(
                'SELECT udi.*, du.username as delegator_username, dv.username as delegate_username, mi.label as menu_label, mi.id as menu_item_id, r.name as route_name
                 FROM user_delegations_items udi
                 LEFT JOIN users du ON du.id = udi.delegator_user_id
                 LEFT JOIN users dv ON dv.id = udi.delegate_user_id
                 LEFT JOIN sidebar_menu_items mi ON mi.id = udi.menu_item_id
                 LEFT JOIN routes r ON r.id = mi.route_id
                 WHERE udi.id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if (!$row)
                return $this->notFound('Delegation not found');
            return $this->success($row);
        }

        // List with pagination & filters
        list($page, $limit, $offset) = $this->getPaginationParams();
        list($search, $sort, $order) = $this->getSearchParams();

        $where = [];
        $params = [];
        if (!empty($_GET['delegator_user_id'])) {
            $where[] = 'udi.delegator_user_id = ?';
            $params[] = (int) $_GET['delegator_user_id'];
        }
        if (!empty($_GET['delegate_user_id'])) {
            $where[] = 'udi.delegate_user_id = ?';
            $params[] = (int) $_GET['delegate_user_id'];
        }
        if (isset($_GET['active'])) {
            $where[] = 'udi.active = ?';
            $params[] = (int) $_GET['active'];
        }
        if (!empty($search)) {
            $where[] = '(du.username LIKE ? OR dv.username LIKE ? OR mi.label LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $whereSql = empty($where) ? '1' : implode(' AND ', $where);

        $sql = "SELECT SQL_CALC_FOUND_ROWS udi.*, du.username as delegator_username, dv.username as delegate_username, mi.label as menu_label, mi.id as menu_item_id, r.name as route_name
                FROM user_delegations_items udi
                LEFT JOIN users du ON du.id = udi.delegator_user_id
                LEFT JOIN users dv ON dv.id = udi.delegate_user_id
                LEFT JOIN sidebar_menu_items mi ON mi.id = udi.menu_item_id
                LEFT JOIN routes r ON r.id = mi.route_id
                WHERE {$whereSql}
                ORDER BY {$sort} {$order}
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $total = (int) $this->db->getConnection()->query('SELECT FOUND_ROWS()')->fetchColumn();
        return $this->success(['items' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    // POST /api/delegations
    public function post($id = null, $data = [], $segments = [])
    {
        $userId = $_SERVER['auth_user']['id'] ?? null;
        $permCheck = $this->permissionManager->hasPermission($userId, 'manage_delegations');
        if (!$permCheck['success'] || !$permCheck['has_permission']) {
            return $this->forbidden('Insufficient permissions');
        }

        $delegator = (int) ($data['delegator_user_id'] ?? 0);
        $delegate = (int) ($data['delegate_user_id'] ?? 0);
        $menuItem = (int) ($data['menu_item_id'] ?? 0);
        $expiresAt = $data['expires_at'] ?? null;

        if (!$delegator || !$delegate || !$menuItem) {
            return $this->badRequest('delegator_user_id, delegate_user_id and menu_item_id are required');
        }

        try {
            $granted = $this->delegationService->delegateMenuItemToUser($delegator, $delegate, $menuItem, true, $expiresAt);
            // Return the created row
            $stmt = $this->db->getConnection()->prepare('SELECT * FROM user_delegations_items WHERE delegator_user_id = ? AND delegate_user_id = ? AND menu_item_id = ? LIMIT 1');
            $stmt->execute([$delegator, $delegate, $menuItem]);
            $row = $stmt->fetch();
            return $this->created(['row' => $row, 'granted_permissions' => $granted]);
        } catch (Exception $e) {
            return $this->serverError('Failed to create delegation', $e->getMessage());
        }
    }

    // PUT /api/delegations/{id}
    public function put($id = null, $data = [], $segments = [])
    {
        $userId = $_SERVER['auth_user']['id'] ?? null;
        $permCheck = $this->permissionManager->hasPermission($userId, 'manage_delegations');
        if (!$permCheck['success'] || !$permCheck['has_permission']) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id)
            return $this->badRequest('Delegation id required');

        $fields = [];
        $params = [];
        if (isset($data['active'])) {
            $fields[] = 'active = ?';
            $params[] = (int) $data['active'];
        }
        if (array_key_exists('expires_at', $data)) {
            $fields[] = 'expires_at = ?';
            $params[] = $data['expires_at'];
        }
        if (empty($fields))
            return $this->badRequest('No fields to update');

        $params[] = $id;
        $sql = 'UPDATE user_delegations_items SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->db->getConnection()->prepare($sql);
        $ok = $stmt->execute($params);

        // If deactivated, attempt to revoke permissions granted specifically for this delegation
        if (isset($data['active']) && (int) $data['active'] === 0) {
            $this->revokeDelegationPermissionsById($id);
        }

        return $ok ? $this->success(['updated' => $ok]) : $this->serverError('Update failed');
    }

    // DELETE /api/delegations/{id}
    public function delete($id = null, $data = [], $segments = [])
    {
        $userId = $_SERVER['auth_user']['id'] ?? null;
        $permCheck = $this->permissionManager->hasPermission($userId, 'manage_delegations');
        if (!$permCheck['success'] || !$permCheck['has_permission']) {
            return $this->forbidden('Insufficient permissions');
        }
        if (!$id)
            return $this->badRequest('Delegation id required');

        // Revoke permissions safely
        $this->revokeDelegationPermissionsById($id);

        // Delete the delegation
        $stmt = $this->db->getConnection()->prepare('DELETE FROM user_delegations_items WHERE id = ?');
        $ok = $stmt->execute([$id]);

        // Audit deletion
        $this->db->getConnection()->prepare("INSERT INTO delegation_audit (delegator_user_id, delegate_user_id, menu_item_id, granted_permissions, note, created_at) SELECT delegator_user_id, delegate_user_id, menu_item_id, '[]', 'admin-deleted', NOW() FROM user_delegations_items WHERE id = ?")->execute([$id]);

        return $ok ? $this->noContent() : $this->serverError('Delete failed');
    }

    // Helper: revoke permissions for a given delegation id using delegation_audit as source
    private function revokeDelegationPermissionsById(int $id)
    {
        // Find delegation
        $stmt = $this->db->getConnection()->prepare('SELECT udi.*, da.granted_permissions FROM user_delegations_items udi LEFT JOIN delegation_audit da ON da.delegator_user_id = udi.delegator_user_id AND da.delegate_user_id = udi.delegate_user_id AND da.menu_item_id = udi.menu_item_id WHERE udi.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row)
            return;

        $delegateUserId = $row['delegate_user_id'];
        $granted = $row['granted_permissions'] ?? null;
        $permIds = [];
        if ($granted && $granted !== '[]') {
            $decoded = json_decode($granted, true);
            if (is_array($decoded))
                $permIds = $decoded;
        } else {
            // Best-effort: derive required permissions from menu item route
            $stmt2 = $this->db->getConnection()->prepare('SELECT rp.permission_id FROM sidebar_menu_items mi JOIN routes r ON r.id = mi.route_id JOIN route_permissions rp ON rp.route_id = r.id AND rp.is_required = 1 WHERE mi.id = ?');
            $stmt2->execute([$row['menu_item_id']]);
            $permIds = array_map(fn($r) => $r['permission_id'], $stmt2->fetchAll());
        }

        foreach ($permIds as $pid) {
            // Only revoke if no other active delegation grants this permission to the user
            $checkStmt = $this->db->getConnection()->prepare('SELECT COUNT(*) FROM user_delegations_items udi JOIN sidebar_menu_items mi ON mi.id = udi.menu_item_id JOIN routes r ON r.id = mi.route_id JOIN route_permissions rp ON rp.route_id = r.id AND rp.is_required = 1 WHERE udi.delegate_user_id = ? AND rp.permission_id = ? AND udi.active = 1 AND udi.id != ?');
            $checkStmt->execute([$delegateUserId, $pid, $id]);
            $cnt = (int) $checkStmt->fetchColumn();
            if ($cnt === 0) {
                $this->permissionManager->revokePermission($delegateUserId, $pid);
            }
        }
    }
}
