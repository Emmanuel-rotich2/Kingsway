<?php
namespace App\API\Modules\Users;

use PDO;
use Exception;

class UserRoleManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // Assign/revoke roles to user
    public function assignRole($userId, $roleId)
    {
        $sql = 'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$userId, $roleId]);
        return ['success' => $ok, 'data' => ['user_id' => $userId, 'role_id' => $roleId, 'assigned' => $ok]];
    }
    public function revokeRole($userId, $roleId)
    {
        $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$userId, $roleId]);
        return ['success' => $ok, 'data' => ['user_id' => $userId, 'role_id' => $roleId, 'revoked' => $ok]];
    }
    public function getUserRoles($userId)
    {
        $sql = 'SELECT r.* FROM roles r INNER JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $roles];
    }
    // Bulk operations
    public function bulkAssignRoles($userId, $roleIds)
    {
        $sql = 'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id';
        $stmt = $this->db->prepare($sql);
        foreach ($roleIds as $roleId) {
            $stmt->execute([$userId, $roleId]);
        }
        return ['success' => true, 'data' => ['user_id' => $userId, 'role_ids' => $roleIds]];
    }
    public function bulkRevokeRoles($userId, $roleIds)
    {
        $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($roleIds as $roleId) {
            $stmt->execute([$userId, $roleId]);
        }
        return ['success' => true, 'data' => ['user_id' => $userId, 'role_ids' => $roleIds]];
    }
    public function bulkAssignUsersToRole($roleId, $userIds)
    {
        $sql = 'INSERT INTO user_roles (user_id, role_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE user_id = user_id';
        $stmt = $this->db->prepare($sql);
        foreach ($userIds as $userId) {
            $stmt->execute([$userId, $roleId]);
        }
        return ['success' => true, 'data' => ['role_id' => $roleId, 'user_ids' => $userIds]];
    }
    public function bulkRevokeUsersFromRole($roleId, $userIds)
    {
        $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($userIds as $userId) {
            $stmt->execute([$userId, $roleId]);
        }
        return ['success' => true, 'data' => ['role_id' => $roleId, 'user_ids' => $userIds]];
    }
}
