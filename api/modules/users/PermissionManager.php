<?php
namespace App\API\Modules\users;

use PDO;
use Exception;

class PermissionManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // List all permissions
    public function getAllPermissions()
    {
        $stmt = $this->db->query('SELECT * FROM form_permissions');
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $permissions];
    }
    // Assign/revoke permission to user
    public function assignPermissionToUser($userId, $formPermissionId)
    {
        $sql = 'INSERT INTO record_permissions (user_id, table_name, record_id, permission_type, granted_date) VALUES (?, ?, ?, ?, NOW())';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$userId, 'form_permissions', $formPermissionId, 'grant']);
        return ['success' => $ok, 'data' => ['user_id' => $userId, 'form_permission_id' => $formPermissionId]];
    }
    public function revokePermissionFromUser($userId, $formPermissionId)
    {
        $sql = 'DELETE FROM record_permissions WHERE user_id = ? AND table_name = ? AND record_id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$userId, 'form_permissions', $formPermissionId]);
        return ['success' => $ok, 'data' => ['user_id' => $userId, 'form_permission_id' => $formPermissionId]];
    }
    // Assign/revoke permission to role
    public function assignPermissionToRole($roleId, $formPermissionId)
    {
        // Add a row to role_form_permissions
        $sql = 'INSERT INTO role_form_permissions (role_id, form_permission_id, allowed_actions) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE allowed_actions = VALUES(allowed_actions)';
        $actions = json_encode(['grant']);
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$roleId, $formPermissionId, $actions]);
        return ['success' => $ok, 'data' => ['role_id' => $roleId, 'form_permission_id' => $formPermissionId]];
    }
    public function revokePermissionFromRole($roleId, $formPermissionId)
    {
        $sql = 'DELETE FROM role_form_permissions WHERE role_id = ? AND form_permission_id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$roleId, $formPermissionId]);
        return ['success' => $ok, 'data' => ['role_id' => $roleId, 'form_permission_id' => $formPermissionId]];
    }
    public function getPermissionsByUser($userId)
    {
        $sql = 'SELECT rp.*, fp.form_code, fp.form_name FROM record_permissions rp
                JOIN form_permissions fp ON rp.record_id = fp.id
                WHERE rp.user_id = ? AND rp.table_name = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId, 'form_permissions']);
        $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $perms];
    }
    public function getPermissionsByRole($roleId)
    {
        $sql = 'SELECT rfp.*, fp.form_code, fp.form_name FROM role_form_permissions rfp
                JOIN form_permissions fp ON rfp.form_permission_id = fp.id
                WHERE rfp.role_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$roleId]);
        $perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $perms];
    }
    public function bulkAssignPermissionsToUser($userId, $formPermissionIds)
    {
        $sql = 'INSERT INTO record_permissions (user_id, table_name, record_id, permission_type, granted_date) VALUES (?, ?, ?, ?, NOW())';
        $stmt = $this->db->prepare($sql);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$userId, 'form_permissions', $pid, 'grant']);
        }
        return ['success' => true, 'data' => ['user_id' => $userId, 'form_permission_ids' => $formPermissionIds]];
    }
    public function bulkRevokePermissionsFromUser($userId, $formPermissionIds)
    {
        $sql = 'DELETE FROM record_permissions WHERE user_id = ? AND table_name = ? AND record_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$userId, 'form_permissions', $pid]);
        }
        return ['success' => true, 'data' => ['user_id' => $userId, 'form_permission_ids' => $formPermissionIds]];
    }
    public function bulkAssignPermissionsToRole($roleId, $formPermissionIds)
    {
        $sql = 'INSERT INTO role_form_permissions (role_id, form_permission_id, allowed_actions) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE allowed_actions = VALUES(allowed_actions)';
        $stmt = $this->db->prepare($sql);
        $actions = json_encode(['grant']);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$roleId, $pid, $actions]);
        }
        return ['success' => true, 'data' => ['role_id' => $roleId, 'form_permission_ids' => $formPermissionIds]];
    }
    public function bulkRevokePermissionsFromRole($roleId, $formPermissionIds)
    {
        $sql = 'DELETE FROM role_form_permissions WHERE role_id = ? AND form_permission_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$roleId, $pid]);
        }
        return ['success' => true, 'data' => ['role_id' => $roleId, 'form_permission_ids' => $formPermissionIds]];
    }
}
