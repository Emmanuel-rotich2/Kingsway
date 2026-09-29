<?php
namespace App\API\Modules\users;

use PDO;
use Exception;
use function App\API\Includes\formatResponse;

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
        $sql = 'INSERT INTO roles (name, description) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            $data['name'],
            $data['description'] ?? null
        ]);
        $id = $this->db->lastInsertId();
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            $id,
            'role_create',
            'Role created: ' . ($data['name'] ?? ''),
            ['details' => ['role_name' => $data['name'] ?? null]]
        );
        return ['success' => $ok, 'id' => $id];
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
        $sql = 'UPDATE roles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $id
        ]);
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            $id,
            'role_update',
            'Role updated: ' . ($data['name'] ?? ''),
            ['details' => ['role_name' => $data['name'] ?? null]]
        );
        return ['success' => $ok, 'id' => $id];
    }
    public function deleteRole($id)
    {
        $stmt = $this->db->prepare('DELETE FROM roles WHERE id = ?');
        $ok = $stmt->execute([$id]);
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            $id,
            'role_delete',
            'Role deleted',
            ['status' => $ok ? 'success' : 'failure']
        );
        return ['success' => $ok, 'id' => $id];
    }
    // Assign/revoke permissions to role
    public function assignPermission($roleId, $formPermissionId)
    {
        $sql = 'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$roleId, $formPermissionId]);
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role_permission',
            $roleId,
            'role_permission_assign',
            'Permission assigned to role',
            ['details' => ['role_id' => $roleId, 'permission_id' => $formPermissionId]]
        );
        return ['success' => $ok, 'role_id' => $roleId, 'form_permission_id' => $formPermissionId];
    }
    public function revokePermission($roleId, $formPermissionId)
    {
        $sql = 'DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([$roleId, $formPermissionId]);
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role_permission',
            $roleId,
            'role_permission_remove',
            'Permission revoked from role',
            ['details' => ['role_id' => $roleId, 'permission_id' => $formPermissionId]]
        );
        return ['success' => $ok, 'role_id' => $roleId, 'form_permission_id' => $formPermissionId];
    }
    // Bulk operations
    public function bulkCreateRoles($roles)
    {
        $sql = 'INSERT INTO roles (name, description) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        $ids = [];
        foreach ($roles as $role) {
            $stmt->execute([
                $role['name'],
                $role['description'] ?? null
            ]);
            $ids[] = $this->db->lastInsertId();
        }
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            null,
            'role_create',
            'Roles created in bulk: ' . count($ids),
            ['details' => ['count' => count($ids), 'ids' => $ids]]
        );
        return ['success' => true, 'ids' => $ids];
    }
    public function bulkUpdateRoles($roles)
    {
        $sql = 'UPDATE roles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $updated = [];
        foreach ($roles as $role) {
            $ok = $stmt->execute([
                $role['name'],
                $role['description'] ?? null,
                $role['id']
            ]);
            if ($ok)
                $updated[] = $role['id'];
        }
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            null,
            'role_update',
            'Roles updated in bulk: ' . count($updated),
            ['details' => ['count' => count($updated), 'ids' => $updated]]
        );
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
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            null,
            'role_delete',
            'Roles deleted in bulk: ' . count($deleted),
            ['details' => ['count' => count($deleted), 'ids' => $deleted]]
        );
        return ['success' => true, 'deleted' => $deleted];
    }
    public function bulkAssignPermissions($roleId, $formPermissionIds)
    {
        $sql = 'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$roleId, $pid]);
        }
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role_permission',
            $roleId,
            'role_permission_assign',
            'Permissions assigned to role in bulk',
            ['details' => ['role_id' => $roleId, 'permission_ids' => $formPermissionIds]]
        );
        return ['success' => true, 'role_id' => $roleId, 'form_permission_ids' => $formPermissionIds];
    }
    public function bulkRevokePermissions($roleId, $formPermissionIds)
    {
        $sql = 'DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?';
        $stmt = $this->db->prepare($sql);
        foreach ($formPermissionIds as $pid) {
            $stmt->execute([$roleId, $pid]);
        }
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role_permission',
            $roleId,
            'role_permission_remove',
            'Permissions revoked from role in bulk',
            ['details' => ['role_id' => $roleId, 'permission_ids' => $formPermissionIds]]
        );
        return ['success' => true, 'role_id' => $roleId, 'form_permission_ids' => $formPermissionIds];
    }

    // ======= Settings-page variants (formatResponse shape; column aliases match js/pages/settings.js DataTables) =======

    public function listRolesForSettings()
    {
        $rows = $this->db->query(
            "SELECT id, name AS role_name, description,
                    is_active AS status, user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = roles.id) AS permission_count
             FROM roles ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return formatResponse(true, $rows);
    }

    public function getRoleForSettings($id)
    {
        $stmt = $this->db->prepare(
            "SELECT id, name AS role_name, description,
                    is_active AS status, user_count,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = roles.id) AS permission_count
             FROM roles WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return formatResponse(false, null, 'Role not found', 404);
        }
        return formatResponse(true, $row);
    }

    public function createRoleForSettings($name, $description = '')
    {
        $check = $this->db->prepare("SELECT id FROM roles WHERE name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            return formatResponse(false, null, 'A role with that name already exists', 409);
        }
        $stmt = $this->db->prepare("INSERT INTO roles (name, description, scope, is_active) VALUES (?, ?, 'school', 1)");
        $stmt->execute([$name, $description]);
        $id = $this->db->lastInsertId();
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            $id,
            'role_create',
            'Role created: ' . $name,
            ['details' => ['role_name' => $name]]
        );
        return formatResponse(true, ['id' => $id, 'role_name' => $name], 'Role created', 201);
    }

    public function updateRoleForSettings($id, $name, $description = '')
    {
        $stmt = $this->db->prepare("SELECT id, is_system FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            return formatResponse(false, null, 'Role not found', 404);
        }
        if (!empty($existing['is_system'])) {
            return formatResponse(false, null, 'System roles cannot be modified', 403);
        }
        if ($name !== '') {
            $dup = $this->db->prepare("SELECT id FROM roles WHERE name = ? AND id <> ?");
            $dup->execute([$name, $id]);
            if ($dup->fetch()) {
                return formatResponse(false, null, 'A role with that name already exists', 409);
            }
        }
        $upd = $this->db->prepare(
            "UPDATE roles SET name = COALESCE(NULLIF(?, ''), name), description = ? WHERE id = ?"
        );
        $upd->execute([$name, $description, $id]);
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            $id,
            'role_update',
            'Role updated: ' . $name,
            ['details' => ['role_name' => $name, 'description' => $description]]
        );
        return formatResponse(true, ['id' => $id], 'Role updated');
    }

    public function deleteRoleForSettings($id)
    {
        $stmt = $this->db->prepare("SELECT id, is_system FROM roles WHERE id = ?");
        $stmt->execute([$id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            return formatResponse(false, null, 'Role not found', 404);
        }
        if (!empty($existing['is_system'])) {
            return formatResponse(false, null, 'System roles cannot be deleted', 403);
        }
        $assigned = $this->db->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id=?');
        $assigned->execute([(int)$id]);
        if ((int)$assigned->fetchColumn() > 0) {
            return formatResponse(false, null, 'This role is assigned to users. Reassign those users before deleting it.', 409);
        }
        $this->db->prepare("DELETE FROM role_permissions WHERE role_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM user_roles WHERE role_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
        \App\API\Includes\SecurityEventNotifier::permissionChange(
            'role',
            $id,
            'role_delete',
            'Role deleted',
            ['status' => 'success']
        );
        return formatResponse(true, ['id' => $id], 'Role deleted');
    }

    public function listPermissionsForSettings()
    {
        $rows = $this->db->query(
            "SELECT p.id, p.code AS permission_key, p.description AS permission_label,
                    p.module, p.entity, p.action,
                    (SELECT COUNT(*) FROM role_permissions rp WHERE rp.permission_id = p.id) AS role_count
             FROM permissions p
             ORDER BY p.module ASC, p.code ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        return formatResponse(true, $rows);
    }
}
