<?php
namespace App\API\Services;

use App\Database\Database;
use App\API\Includes\AuditLogger;
use Exception;

class DelegationService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Atomically delegate a menu item from one user to another and optionally grant
     * required permissions to the delegate so the UI item is usable and backend
     * calls are not blocked by middleware.
     *
     * Persists the delegation in `permission_delegations` (form_permission_id
     * carries the menu_item_id — the normalised schema has no menu-level
     * delegation table) and audits the action in `audit_logs`.
     *
     * Returns array of permission codes granted (may be empty).
     */
    public function delegateMenuItemToUser(int $delegatorUserId, int $delegateUserId, int $menuItemId, bool $grantPermissions = true, ?string $expiresAt = null): array
    {
        $grantedPermissions = [];
        $this->db->beginTransaction();
        try {
            if ($grantPermissions) {
                // Find route for this menu item and required permissions
                $stmt = $this->db->query(
                    "SELECT r.id as route_id, p.id as permission_id, p.code as permission_code
                     FROM sidebar_menu_items mi
                     JOIN routes_registry r ON r.id = mi.route_id
                     JOIN route_permissions rp ON rp.route_id = r.id
                     JOIN permissions p ON p.id = rp.permission_id
                     WHERE mi.id = ? AND rp.is_required = 1",
                    [$menuItemId]
                );

                $rows = $stmt->fetchAll();
                foreach ($rows as $row) {
                    $permId = $row['permission_id'];
                    $permCode = $row['permission_code'] ?? null;

                    // Insert into user_permissions; grant with expiry by default
                    $this->db->query(
                        "INSERT INTO user_permissions (user_id, permission_id, expires_at, granted_by, reason, created_at)
                         VALUES (?, ?, ?, ?, 'menu_delegation', NOW())
                         ON DUPLICATE KEY UPDATE expires_at = COALESCE(VALUES(expires_at), expires_at)",
                        [$delegateUserId, $permId, $this->normalizeExpiry($expiresAt), $delegatorUserId]
                    );

                    if ($permCode) {
                        $grantedPermissions[] = $permCode;
                    }
                }
            }

            // Store the delegation row in permission_delegations; form_permission_id
            // holds the menu_item_id (no FK constraint; table is otherwise unused).
            $endDate = $this->normalizeExpiry($expiresAt);
            $this->db->query(
                "INSERT INTO permission_delegations
                    (delegated_from_user_id, delegated_to_user_id, form_permission_id, delegation_start_date, delegation_end_date, reason, approved_by, approval_date)
                 VALUES (?, ?, ?, CURDATE(), ?, ?, ?, NOW())",
                [
                    $delegatorUserId,
                    $delegateUserId,
                    $menuItemId,
                    $endDate,
                    'menu_delegation:menu_item_' . $menuItemId,
                    $delegatorUserId
                ]
            );
            $delegationId = (int) $this->db->lastInsertId();

            // Audit the creation
            (new AuditLogger($this->db->getConnection()))->log(
                'delegation_create',
                'delegation',
                $delegationId,
                $delegatorUserId,
                [
                    'delegate_user_id' => $delegateUserId,
                    'menu_item_id' => $menuItemId,
                    'expires_at' => $endDate,
                    'granted_permissions' => $grantedPermissions,
                ],
                'success'
            );

            $this->db->commit();
            return $grantedPermissions;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Fetch a single delegation joined with display names.
     */
    public function getDelegation(int $id): ?array
    {
        $stmt = $this->db->query(
            "SELECT pd.id, pd.delegated_from_user_id AS delegator_user_id, pd.delegated_to_user_id AS delegate_user_id,
                    pd.form_permission_id AS menu_item_id, pd.delegation_start_date, pd.delegation_end_date AS expires_at,
                    du.username AS delegator_username, dv.username AS delegate_username,
                    mi.label AS menu_label, r.name AS route_name,
                    (pd.delegation_end_date >= CURDATE()) AS active
             FROM permission_delegations pd
             LEFT JOIN users du ON du.id = pd.delegated_from_user_id
             LEFT JOIN users dv ON dv.id = pd.delegated_to_user_id
             LEFT JOIN sidebar_menu_items mi ON mi.id = pd.form_permission_id
             LEFT JOIN routes_registry r ON r.id = mi.route_id
             WHERE pd.id = ? LIMIT 1",
            [$id]
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * List delegations with filters, search, and pagination.
     */
    public function listDelegations(array $filters = [], string $search = '', string $sort = 'pd.id', string $order = 'ASC', int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['delegator_user_id'])) {
            $where[] = 'pd.delegated_from_user_id = ?';
            $params[] = (int) $filters['delegator_user_id'];
        }
        if (!empty($filters['delegate_user_id'])) {
            $where[] = 'pd.delegated_to_user_id = ?';
            $params[] = (int) $filters['delegate_user_id'];
        }
        if (isset($filters['active']) && $filters['active'] !== null && $filters['active'] !== '') {
            if ((int) $filters['active'] === 1) {
                $where[] = 'pd.delegation_end_date >= CURDATE()';
            } else {
                $where[] = 'pd.delegation_end_date < CURDATE()';
            }
        }
        if ($search) {
            $where[] = '(du.username LIKE ? OR dv.username LIKE ? OR mi.label LIKE ?)';
            array_push($params, "%{$search}%", "%{$search}%", "%{$search}%");
        }

        $whereSql = empty($where) ? '1' : implode(' AND ', $where);

        $allowedSorts = ['pd.id', 'pd.delegation_start_date', 'pd.delegation_end_date', 'du.username', 'dv.username', 'mi.label'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'pd.id';
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT SQL_CALC_FOUND_ROWS pd.id, pd.delegated_from_user_id AS delegator_user_id, pd.delegated_to_user_id AS delegate_user_id,
                    pd.form_permission_id AS menu_item_id, pd.delegation_start_date, pd.delegation_end_date AS expires_at,
                    du.username AS delegator_username, dv.username AS delegate_username,
                    mi.label AS menu_label, r.name AS route_name,
                    (pd.delegation_end_date >= CURDATE()) AS active
                FROM permission_delegations pd
                LEFT JOIN users du ON du.id = pd.delegated_from_user_id
                LEFT JOIN users dv ON dv.id = pd.delegated_to_user_id
                LEFT JOIN sidebar_menu_items mi ON mi.id = pd.form_permission_id
                LEFT JOIN routes_registry r ON r.id = mi.route_id
                WHERE {$whereSql}
                ORDER BY {$sort} {$order}
                LIMIT ? OFFSET ?";

        $stmt = $this->db->query($sql, array_merge($params, [$limit, $offset]));
        $rows = $stmt->fetchAll();

        $total = (int) $this->db->query('SELECT FOUND_ROWS()')->fetchColumn();

        return ['items' => $rows, 'total' => $total];
    }

    /**
     * Fetch the delegation row created for a delegation.
     */
    public function findDelegation(int $delegatorUserId, int $delegateUserId, int $menuItemId): ?array
    {
        $stmt = $this->db->query(
            "SELECT id, delegated_from_user_id AS delegator_user_id, delegated_to_user_id AS delegate_user_id,
                    form_permission_id AS menu_item_id, delegation_start_date, delegation_end_date AS expires_at,
                    (delegation_end_date >= CURDATE()) AS active
             FROM permission_delegations
             WHERE delegated_from_user_id = ? AND delegated_to_user_id = ? AND form_permission_id = ?
             LIMIT 1",
            [$delegatorUserId, $delegateUserId, $menuItemId]
        );
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * Update selectable delegation fields (active, expires_at).
     */
    public function updateDelegation(int $id, array $fields): bool
    {
        $allowed = ['active', 'expires_at'];
        $set = [];
        $params = [];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $fields)) {
                continue;
            }
            if ($field === 'active') {
                $set[] = 'delegation_end_date = ?';
                $params[] = (int) $fields['active'] === 1 ? $this->defaultExpiry() : date('Y-m-d', strtotime('-1 day'));
            } elseif ($field === 'expires_at' && $fields['expires_at'] !== null && $fields['expires_at'] !== '') {
                $set[] = 'delegation_end_date = ?';
                $params[] = $this->normalizeExpiry($fields['expires_at']);
            }
        }

        if (empty($set)) {
            return false;
        }

        $params[] = $id;
        $stmt = $this->db->query('UPDATE permission_delegations SET ' . implode(', ', $set) . ' WHERE id = ?', $params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete a delegation and audit the removal.
     */
    public function deleteDelegation(int $id): bool
    {
        $row = $this->getDelegation($id);
        if (!$row) {
            return false;
        }

        $stmt = $this->db->query('DELETE FROM permission_delegations WHERE id = ?', [$id]);
        $deleted = $stmt->rowCount() > 0;

        (new AuditLogger($this->db->getConnection()))->log(
            'delegation_delete',
            'delegation',
            $id,
            (int) ($row['delegator_user_id'] ?? 0),
            [
                'delegate_user_id' => $row['delegate_user_id'] ?? null,
                'menu_item_id' => $row['menu_item_id'] ?? null,
            ],
            'success'
        );

        return $deleted;
    }

    /**
     * Revoke permissions granted for a delegation, unless another active
     * delegation still grants them.
     */
    public function revokeDelegationPermissionsById(int $id): void
    {
        $stmt = $this->db->query(
            "SELECT id, delegated_from_user_id AS delegator_user_id, delegated_to_user_id AS delegate_user_id,
                    form_permission_id AS menu_item_id
             FROM permission_delegations
             WHERE id = ? LIMIT 1",
            [$id]
        );
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $delegateUserId = (int) $row['delegate_user_id'];

        $stmt2 = $this->db->query(
            'SELECT rp.permission_id FROM sidebar_menu_items mi JOIN routes_registry r ON r.id = mi.route_id JOIN route_permissions rp ON rp.route_id = r.id AND rp.is_required = 1 WHERE mi.id = ?',
            [$row['menu_item_id']]
        );
        $permIds = array_map(function ($r) {
            return $r['permission_id'];
        }, $stmt2->fetchAll());

        $permissionManager = ServiceContractBroker::contract('App\API\Modules\users\UserPermissionManager', [], $this->db->getConnection());

        foreach ($permIds as $pid) {
            $checkStmt = $this->db->query(
                'SELECT COUNT(*) FROM permission_delegations pd
                 JOIN sidebar_menu_items mi ON mi.id = pd.form_permission_id
                 JOIN routes_registry r ON r.id = mi.route_id
                 JOIN route_permissions rp ON rp.route_id = r.id AND rp.is_required = 1
                 WHERE pd.delegated_to_user_id = ? AND rp.permission_id = ? AND pd.delegation_end_date >= CURDATE() AND pd.id != ?',
                [$delegateUserId, $pid, $id]
            );
            $cnt = (int) $checkStmt->fetchColumn();
            if ($cnt === 0) {
                $permissionManager->revokePermission($delegateUserId, $pid);
            }
        }
    }

    /**
     * Normalize an arbitrary expiry value (datetime string or null) to a DATE
     * for permission_delegations.delegation_end_date.
     */
    private function normalizeExpiry(?string $expiresAt): string
    {
        if ($expiresAt !== null && $expiresAt !== '') {
            $ts = strtotime($expiresAt);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }
        return $this->defaultExpiry();
    }

    private function defaultExpiry(): string
    {
        return date('Y-m-d', strtotime('+1 year'));
    }
}
