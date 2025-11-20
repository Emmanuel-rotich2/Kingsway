<?php
namespace App\API\Modules\Users;

use PDO;
use Exception;

class UserPermissionManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // Assign/revoke permissions to user
    public function assignPermission($userId, $permissionId)
    {
        $sql = 'INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$userId, $permissionId]);
        return ['success' => $ok, 'data' => ['user_id' => $userId, 'permission_id' => $permissionId, 'assigned' => $ok]];
    }
    public function revokePermission($userId, $permissionId)
    {
        $sql = 'DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$userId, $permissionId]);
        return ['success' => $ok, 'data' => ['user_id' => $userId, 'permission_id' => $permissionId, 'revoked' => $ok]];
    }
    public function getUserPermissions($userId)
    {
        $sql = 'SELECT p.* FROM permissions p INNER JOIN user_permissions up ON p.id = up.permission_id WHERE up.user_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $permissions];
    }
    // Bulk operations
    public function bulkAssignPermissions($userId, $permissionIds)
    {
        $sql = 'INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id';
        $stmt = $this->db->prepare($sql);
        foreach ($permissionIds as $permissionId) {
            $stmt->execute([$userId, $permissionId]);
        }
        return ['success' => true, 'data' => ['user_id' => $userId, 'permission_ids' => $permissionIds]];
    }
    public function bulkRevokePermissions($userId, $permissionIds)
    {
        $sql = 'DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($permissionIds as $permissionId) {
            $stmt->execute([$userId, $permissionId]);
        }
        return ['success' => true, 'data' => ['user_id' => $userId, 'permission_ids' => $permissionIds]];
    }
    public function bulkAssignUsersToPermission($permissionId, $userIds)
    {
        $sql = 'INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id';
        $stmt = $this->db->prepare($sql);
        foreach ($userIds as $userId) {
            $stmt->execute([$userId, $permissionId]);
        }
        return ['success' => true, 'data' => ['permission_id' => $permissionId, 'user_ids' => $userIds]];
    }
    public function bulkRevokeUsersFromPermission($permissionId, $userIds)
    {
        $sql = 'DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($userIds as $userId) {
            $stmt->execute([$userId, $permissionId]);
        }
        return ['success' => true, 'data' => ['permission_id' => $permissionId, 'user_ids' => $userIds]];
    }
}
