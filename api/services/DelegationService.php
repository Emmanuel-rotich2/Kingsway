<?php
namespace App\API\Services;

use App\Database\Database;
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
     * Returns array of permission codes granted (may be empty).
     */
    public function delegateMenuItemToUser(int $delegatorUserId, int $delegateUserId, int $menuItemId, bool $grantPermissions = true, ?string $expiresAt = null): array
    {
        $grantedPermissions = [];
        $this->db->beginTransaction();
        try {
            // 1) Insert user-level delegation
            $this->db->query(
                "INSERT INTO user_delegations_items (delegator_user_id, delegate_user_id, menu_item_id, active, expires_at, created_at)
                 VALUES (?, ?, ?, 1, ?, NOW())
                 ON DUPLICATE KEY UPDATE active = 1, expires_at = VALUES(expires_at)",
                [$delegatorUserId, $delegateUserId, $menuItemId, $expiresAt]
            );

            if ($grantPermissions) {
                // 2) Find route for this menu item and required permissions
                $stmt = $this->db->query(
                    "SELECT r.id as route_id, p.id as permission_id, p.code as permission_code
                     FROM sidebar_menu_items mi
                     JOIN routes r ON r.id = mi.route_id
                     JOIN route_permissions rp ON rp.route_id = r.id
                     JOIN permissions p ON p.id = rp.permission_id
                     WHERE mi.id = ? AND rp.is_required = 1",
                    [$menuItemId]
                );

                $rows = $stmt->fetchAll();
                foreach ($rows as $row) {
                    $permId = $row['permission_id'];
                    $permCode = $row['permission_code'] ?? $row['permission_code'] ?? null;

                    // Insert into user_permissions; grant with no expiry by default
                    $this->db->query(
                        "INSERT INTO user_permissions (user_id, permission_id, expires_at, created_at)
                         VALUES (?, ?, NULL, NOW())
                         ON DUPLICATE KEY UPDATE expires_at = COALESCE(expires_at, NULL)",
                        [$delegateUserId, $permId]
                    );

                    if ($permCode) {
                        $grantedPermissions[] = $permCode;
                    }
                }
            }

            // 3) Insert audit record
            $this->db->query(
                "INSERT INTO delegation_audit (delegator_user_id, delegate_user_id, menu_item_id, granted_permissions, note, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$delegatorUserId, $delegateUserId, $menuItemId, json_encode($grantedPermissions), 'auto-delegation']
            );

            $this->db->commit();
            return $grantedPermissions;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}
