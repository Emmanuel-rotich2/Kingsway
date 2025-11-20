<?php
namespace App\API\Modules\Users;

use App\API\Includes\BaseAPI;
use App\API\Modules\communications\CommunicationsAPI;
use PDO;
use Exception;

class UsersAPI extends BaseAPI
{


    private $communicationsApi;
    private $roleManager;
    private $permissionManager;
    private $userRoleManager;
    private $userPermissionManager;

    public function __construct()
    {
        parent::__construct('users');
        $this->communicationsApi = new CommunicationsAPI();
        $this->roleManager = new RoleManager($this->db);
        $this->permissionManager = new PermissionManager($this->db);
        $this->userRoleManager = new UserRoleManager($this->db);
        $this->userPermissionManager = new UserPermissionManager($this->db);
    }

    // --- Role CRUD and bulk ---
    public function createRole($data)
    {
        return $this->roleManager->createRole($data);
    }
    public function getRole($id)
    {
        return $this->roleManager->getRole($id);
    }
    public function getAllRoles()
    {
        return $this->roleManager->getAllRoles();
    }
    public function updateRole($id, $data)
    {
        return $this->roleManager->updateRole($id, $data);
    }
    public function deleteRole($id)
    {
        return $this->roleManager->deleteRole($id);
    }
    public function bulkCreateRoles($roles)
    {
        return $this->roleManager->bulkCreateRoles($roles);
    }
    public function bulkUpdateRoles($roles)
    {
        return $this->roleManager->bulkUpdateRoles($roles);
    }
    public function bulkDeleteRoles($roleIds)
    {
        return $this->roleManager->bulkDeleteRoles($roleIds);
    }

    // --- Permission CRUD and bulk ---
    public function getAllPermissions()
    {
        return $this->permissionManager->getAllPermissions();
    }
    public function getPermissionsByUser($userId)
    {
        return $this->permissionManager->getPermissionsByUser($userId);
    }
    public function getPermissionsByRole($roleId)
    {
        return $this->permissionManager->getPermissionsByRole($roleId);
    }
    public function assignPermissionToUser($userId, $permission)
    {
        return $this->permissionManager->assignPermissionToUser($userId, $permission);
    }
    public function revokePermissionFromUser($userId, $permission)
    {
        return $this->permissionManager->revokePermissionFromUser($userId, $permission);
    }
    public function bulkAssignPermissionsToUser($userId, $permissions)
    {
        return $this->permissionManager->bulkAssignPermissionsToUser($userId, $permissions);
    }
    public function bulkRevokePermissionsFromUser($userId, $permissions)
    {
        return $this->permissionManager->bulkRevokePermissionsFromUser($userId, $permissions);
    }
    public function assignPermissionToRole($roleId, $permission)
    {
        return $this->permissionManager->assignPermissionToRole($roleId, $permission);
    }
    public function revokePermissionFromRole($roleId, $permission)
    {
        return $this->permissionManager->revokePermissionFromRole($roleId, $permission);
    }
    public function bulkAssignPermissionsToRole($roleId, $permissions)
    {
        return $this->permissionManager->bulkAssignPermissionsToRole($roleId, $permissions);
    }
    public function bulkRevokePermissionsFromRole($roleId, $permissions)
    {
        return $this->permissionManager->bulkRevokePermissionsFromRole($roleId, $permissions);
    }

    // --- UserRole assignment and bulk ---
    public function assignRoleToUser($userId, $roleId)
    {
        return $this->userRoleManager->assignRole($userId, $roleId);
    }
    public function revokeRoleFromUser($userId, $roleId)
    {
        return $this->userRoleManager->revokeRole($userId, $roleId);
    }
    public function getUserRoles($userId)
    {
        return $this->userRoleManager->getUserRoles($userId);
    }
    public function bulkAssignRolesToUser($userId, $roleIds)
    {
        return $this->userRoleManager->bulkAssignRoles($userId, $roleIds);
    }
    public function bulkRevokeRolesFromUser($userId, $roleIds)
    {
        return $this->userRoleManager->bulkRevokeRoles($userId, $roleIds);
    }
    public function bulkAssignUsersToRole($roleId, $userIds)
    {
        return $this->userRoleManager->bulkAssignUsersToRole($roleId, $userIds);
    }
    public function bulkRevokeUsersFromRole($roleId, $userIds)
    {
        return $this->userRoleManager->bulkRevokeUsersFromRole($roleId, $userIds);
    }

    // --- UserPermission assignment and bulk ---
    public function assignPermissionToUserDirect($userId, $permission)
    {
        return $this->userPermissionManager->assignPermission($userId, $permission);
    }
    public function revokePermissionFromUserDirect($userId, $permission)
    {
        return $this->userPermissionManager->revokePermission($userId, $permission);
    }
    public function getUserPermissions($userId)
    {
        return $this->userPermissionManager->getUserPermissions($userId);
    }
    public function bulkAssignPermissionsToUserDirect($userId, $permissions)
    {
        return $this->userPermissionManager->bulkAssignPermissions($userId, $permissions);
    }
    public function bulkRevokePermissionsFromUserDirect($userId, $permissions)
    {
        return $this->userPermissionManager->bulkRevokePermissions($userId, $permissions);
    }
    public function bulkAssignUsersToPermission($permission, $userIds)
    {
        return $this->userPermissionManager->bulkAssignUsersToPermission($permission, $userIds);
    }
    public function bulkRevokeUsersFromPermission($permission, $userIds)
    {
        return $this->userPermissionManager->bulkRevokeUsersFromPermission($permission, $userIds);
    }

    // === Controller-required CRUD and utility methods ===
    public function get($id)
    {
        // Fetch user by ID
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return ['success' => true, 'data' => $user];
        } else {
            return ['success' => false, 'error' => 'User not found'];
        }
    }
    public function list($data = [])
    {
        // List all users (optionally filter by status, role, etc.)
        $sql = 'SELECT * FROM users';
        $params = [];
        if (isset($data['status'])) {
            $sql .= ' WHERE status = ?';
            $params[] = $data['status'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['success' => true, 'data' => $users];
    }
    public function create($data)
    {
        // Create a new user
        $sql = 'INSERT INTO users (username, email, password, first_name, last_name, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            $data['username'],
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['first_name'],
            $data['last_name'],
            $data['status'] ?? 'active'
        ]);
        if ($ok) {
            $id = $this->db->lastInsertId();
            return ['success' => true, 'data' => $this->get($id)['data']];
        } else {
            return ['success' => false, 'error' => 'User creation failed'];
        }
    }
    public function update($id, $data)
    {
        // Update user fields
        $fields = [];
        $params = [];
        foreach (['username', 'email', 'first_name', 'last_name', 'status'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        if (isset($data['password'])) {
            $fields[] = 'password = ?';
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        if (empty($fields)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }
        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute($params);
        if ($ok) {
            return ['success' => true, 'data' => $this->get($id)['data']];
        } else {
            return ['success' => false, 'error' => 'User update failed'];
        }
    }
    public function delete($id)
    {
        // Delete user by ID
        $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
        $ok = $stmt->execute([$id]);
        return ['success' => $ok, 'data' => ['id' => $id, 'deleted' => $ok]];
    }
    public function getProfile($userId)
    {
        // Fetch user profile (basic info + roles + permissions)
        $user = $this->get($userId);
        if (!$user['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $roles = $this->userRoleManager->getUserRoles($userId);
        $permissions = $this->userPermissionManager->getUserPermissions($userId);
        return [
            'success' => true,
            'data' => [
                'id' => $userId,
                'profile' => $user['data'],
                'roles' => $roles['data'] ?? [],
                'permissions' => $permissions['data'] ?? []
            ]
        ];
    }

    public function getRoles()
    {
        // Delegate to getAllRoles
        return ['success' => true, 'data' => $this->getAllRoles()];
    }
    public function getPermissions()
    {
        // Delegate to getAllPermissions
        return ['success' => true, 'data' => $this->getAllPermissions()];
    }
    public function updatePermissions($id, $data)
    {
        // Replace all direct user permissions with the provided list
        if (!isset($data['permissions']) || !is_array($data['permissions'])) {
            return ['success' => false, 'error' => 'permissions array required'];
        }
        // Remove all current direct permissions
        $this->userPermissionManager->bulkRevokePermissions($id, array_column($this->userPermissionManager->getUserPermissions($id)['data'], 'id'));
        // Assign new permissions
        $result = $this->userPermissionManager->bulkAssignPermissions($id, $data['permissions']);
        return ['success' => $result['success'], 'data' => ['id' => $id, 'permissions_updated' => true]];
    }
    public function assignRole($id, $data)
    {
        // Assign a single role to user (many-to-many)
        if (!isset($data['role_id'])) {
            return ['success' => false, 'error' => 'role_id required'];
        }
        $result = $this->userRoleManager->assignRole($id, $data['role_id']);
        return ['success' => $result['success'], 'data' => ['id' => $id, 'role_assigned' => $result['success']]];
    }
    public function assignPermission($id, $data)
    {
        // Assign a single permission to user (many-to-many)
        if (!isset($data['permission_id'])) {
            return ['success' => false, 'error' => 'permission_id required'];
        }
        $result = $this->userPermissionManager->assignPermission($id, $data['permission_id']);
        return ['success' => $result['success'], 'data' => ['id' => $id, 'permission_assigned' => $result['success']]];
    }
    public function getMainRole($id)
    {
        // Main role: first role assigned to user (if any)
        $roles = $this->userRoleManager->getUserRoles($id);
        $mainRole = null;
        if ($roles['success'] && !empty($roles['data'])) {
            $mainRole = $roles['data'][0];
        }
        return ['success' => true, 'data' => ['id' => $id, 'main_role' => $mainRole]];
    }
    public function getExtraRoles($id)
    {
        // Extra roles: all except the first assigned role
        $roles = $this->userRoleManager->getUserRoles($id);
        $extraRoles = [];
        if ($roles['success'] && count($roles['data']) > 1) {
            $extraRoles = array_slice($roles['data'], 1);
        }
        return ['success' => true, 'data' => ['id' => $id, 'extra_roles' => $extraRoles]];
    }
    public function getSidebarItems($data)
    {
        // Determine user ID
        $userId = $data['user_id'] ?? null;
        if (!$userId) {
            return ['success' => false, 'error' => 'user_id required'];
        }

        // Get user's main role
        $rolesResult = $this->userRoleManager->getUserRoles($userId);
        if (!$rolesResult['success'] || empty($rolesResult['data'])) {
            // Fallback: return a minimal menu
            return [
                'success' => true,
                'data' => [
                    [
                        'label' => 'Dashboard',
                        'icon' => 'bi bi-speedometer2',
                        'url' => 'dashboard'
                    ]
                ]
            ];
        }
        $mainRole = strtolower($rolesResult['data'][0]['name']);

        // Load menu config
        $menuConfig = include(__DIR__ . '/../../../config/menu_items.php');
        $items = $menuConfig[$mainRole] ?? $menuConfig['admin'] ?? [];
        return ['success' => true, 'data' => $items];
    }
    public function login($data)
    {
        // Validate input
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;
        if (!$username || !$password) {
            return ['success' => false, 'error' => 'Username and password required'];
        }

        // Lookup user by username or email
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Invalid username or password'];
        }

        // Optionally: check user status
        if (isset($user['status']) && $user['status'] !== 'active') {
            return ['success' => false, 'error' => 'Account is not active'];
        }

        // Get roles and permissions
        $roles = $this->userRoleManager->getUserRoles($user['id']);
        $permissions = $this->userPermissionManager->getUserPermissions($user['id']);

        // Optionally: generate JWT or session token (handled by AuthAPI)
        // Return user info for token generation
        return [
            'success' => true,
            'data' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'roles' => $roles['data'] ?? [],
                'permissions' => $permissions['data'] ?? [],
                'status' => $user['status'] ?? null
            ]
        ];
    }
    public function changePassword($userId, $data)
    {
        // Validate input
        $oldPassword = $data['old_password'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        if (!$oldPassword || !$newPassword) {
            return ['success' => false, 'error' => 'Old and new password required'];
        }

        // Fetch user
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }
        // Verify old password
        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Old password is incorrect'];
        }
        // Update password
        $stmt = $this->db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
        $ok = $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $userId
        ]);
        return ['success' => $ok, 'data' => ['id' => $userId, 'changed' => $ok]];
    }
    public function resetPassword($data)
    {
        // Validate input
        $token = $data['token'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        if (!$token || !$newPassword) {
            return ['success' => false, 'error' => 'Token and new password required'];
        }

        // Lookup password reset request
        $stmt = $this->db->prepare('SELECT * FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()');
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$reset) {
            return ['success' => false, 'error' => 'Invalid or expired token'];
        }

        // Update user password
        $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
        $ok = $stmt->execute([
            password_hash($newPassword, PASSWORD_DEFAULT),
            $reset['user_id']
        ]);
        if ($ok) {
            // Mark token as used
            $stmt = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
            $stmt->execute([$token]);
        }
        return ['success' => $ok, 'data' => ['reset' => $ok]];
    }
    
}