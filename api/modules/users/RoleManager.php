<?php
namespace App\API\Modules\users;

use PDO;
use Exception;

class RoleManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // CRUD for roles
    public function createRole($data)
    {
        $sql = 'INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            json_encode($data['permissions'] ?? [])
        ]);
        return ['success' => $ok, 'id' => $this->db->lastInsertId()];
    }
    public function getRole($id)
    {
        $stmt = $this->db->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([$id]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        return ['success' => (bool) $role, 'data' => $role];
    }
    public function getAllRoles()
    {
        $stmt = $this->db->query('SELECT * FROM roles');
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $roles];
    }
    public function updateRole($id, $data)
    {
        $sql = 'UPDATE roles SET name = ?, description = ?, permissions = ?, updated_at = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            json_encode($data['permissions'] ?? []),
            $id
        ]);
        return ['success' => $ok, 'id' => $id];
    }
    public function deleteRole($id)
    {
        $stmt = $this->db->prepare('DELETE FROM roles WHERE id = ?');
        $ok = $stmt->execute([$id]);
        return ['success' => $ok, 'id' => $id];
    }
    // Assign/revoke permissions to role
    public function assignPermission($roleId, $formPermissionId)
    {
        $sql = 'INSERT INTO role_form_permissions (role_id, form_permission_id, allowed_actions) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE allowed_actions = VALUES(allowed_actions)';
        $actions = json_encode(['grant']);
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$roleId, $formPermissionId, $actions]);
        return ['success' => $ok, 'role_id' => $roleId, 'form_permission_id' => $formPermissionId];
    }
    public function revokePermission($roleId, $formPermissionId)
    {
        $sql = 'DELETE FROM role_form_permissions WHERE role_id = ? AND form_permission_id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$roleId, $formPermissionId]);
        return ['success' => $ok, 'role_id' => $roleId, 'form_permission_id' => $formPermissionId];
    }
    // Bulk operations
    public function bulkCreateRoles($roles)
    {
        $sql = 'INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $ids = [];
        foreach ($roles as $role) {
            $stmt->execute([
                $role['name'],
                $role['description'] ?? null,
                json_encode($role['permissions'] ?? [])
            ]);
            $ids[] = $this->db->lastInsertId();
        }
        return ['success' => true, 'ids' => $ids];
    }
    public function bulkUpdateRoles($roles)
    {
        $sql = 'UPDATE roles SET name = ?, description = ?, permissions = ?, updated_at = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $updated = [];
        foreach ($roles as $role) {
            $ok = $stmt->execute([
                $role['name'],
                $role['description'] ?? null,
                json_encode($role['permissions'] ?? []),
                $role['id']
            ]);
            if ($ok)
                $updated[] = $role['id'];
        }
        return ['success' => true, 'updated' => $updated];
    }
    public function bulkDeleteRoles($roleIds)
    {
        $sql = 'DELETE FROM roles WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $deleted = [];
        foreach ($roleIds as $id) {
            $ok = $stmt->execute([$id]);
            if ($ok)
                $deleted[] = $id;
        }
        return ['success' => true, 'deleted' => $deleted];
    }
    public function bulkAssignPermissions($roleId, $formPermissionIds)
    {
        $sql = 'INSERT INTO role_form_permissions (role_id, form_permission_id, allowed_actions) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE allowed_actions = VALUES(allowed_actions)';
        $stmt = $this->db->prepare($sql);
        $actions = json_encode(['grant']);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$roleId, $pid, $actions]);
        }
        return ['success' => true, 'role_id' => $roleId, 'form_permission_ids' => $formPermissionIds];
    }
    public function bulkRevokePermissions($roleId, $formPermissionIds)
    {
        $sql = 'DELETE FROM role_form_permissions WHERE role_id = ? AND form_permission_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$roleId, $pid]);
        }
        return ['success' => true, 'role_id' => $roleId, 'form_permission_ids' => $formPermissionIds];
    }
}
