<?php
namespace App\API\Modules\users;

use App\API\Includes\BaseAPI;
use App\API\Includes\ValidationHelper;
use App\API\Includes\AuditLogger;
use App\API\Modules\communications\CommunicationsAPI;
use Firebase\JWT\JWT;
use PDO;
use Exception;

class UsersAPI extends BaseAPI
{


    private $communicationsApi;
    private $roleManager;
    private $permissionManager;
    private $userRoleManager;
    private $userPermissionManager;
    private $auditLogger;

    public function __construct()
    {
        parent::__construct('users');
        $this->communicationsApi = new CommunicationsAPI();
        $this->roleManager = new RoleManager($this->db);
        $this->permissionManager = new PermissionManager($this->db);
        $this->userRoleManager = new UserRoleManager($this->db);
        $this->userPermissionManager = new UserPermissionManager($this->db);
        $this->auditLogger = new AuditLogger($this->db);
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
    public function getUserRolesDetailed($userId)
    {
        return $this->userRoleManager->getRolesDetailed($userId);
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
    public function getUsersWithRole($roleName)
    {
        return $this->userRoleManager->getUsersWithRole($roleName);
    }
    public function getUsersWithMultipleRoles()
    {
        return $this->userRoleManager->getUsersWithMultipleRoles();
    }

    // --- UserPermission assignment and bulk ---
    public function assignPermissionToUserDirect($userId, $permission)
    {
        return $this->userPermissionManager->assignPermission($userId, $permission);
    }
    public function revokePermissionFromUserDirect($userId, $permissionId)
    {
        return $this->userPermissionManager->revokePermission($userId, $permissionId);
    }
    public function getUserPermissionsEffective($userId)
    {
        return $this->userPermissionManager->getEffectivePermissions($userId);
    }
    public function getUserPermissionsDirect($userId)
    {
        return $this->userPermissionManager->getDirectPermissions($userId);
    }
    public function getUserPermissionsDenied($userId)
    {
        return $this->userPermissionManager->getDeniedPermissions($userId);
    }
    public function getUserPermissionsByEntity($userId)
    {
        return $this->userPermissionManager->getPermissionsByEntity($userId);
    }
    public function getUserPermissionSummary($userId)
    {
        return $this->userPermissionManager->getPermissionSummary($userId);
    }
    public function checkUserPermission($userId, $permissionCode)
    {
        return $this->userPermissionManager->hasPermission($userId, $permissionCode);
    }
    public function checkUserPermissions($userId, $permissionCodes)
    {
        return $this->userPermissionManager->hasPermissions($userId, $permissionCodes);
    }
    public function bulkAssignPermissionsToUserDirect($userId, $permissions)
    {
        return $this->userPermissionManager->bulkAssignPermissions($userId, $permissions);
    }
    public function bulkRevokePermissionsFromUserDirect($userId, $permissionIds)
    {
        return $this->userPermissionManager->bulkRevokePermissions($userId, $permissionIds);
    }
    public function bulkAssignUsersToPermission($permissionId, $userIds, $permType = 'grant')
    {
        return $this->userPermissionManager->bulkAssignUsersToPermission($permissionId, $userIds, $permType);
    }
    public function bulkRevokeUsersFromPermission($permissionId, $userIds)
    {
        return $this->userPermissionManager->bulkRevokeUsersFromPermission($permissionId, $userIds);
    }
    public function getUsersWithPermission($permissionCode)
    {
        return $this->userPermissionManager->getUsersWithPermission($permissionCode);
    }
    public function getUsersWithTemporaryPermissions()
    {
        return $this->userPermissionManager->getUsersWithTemporaryPermissions();
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
        // Validate input data
        $validation = ValidationHelper::validateUserData($data, $this->db, false);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }

        $validatedData = $validation['data'];

        // Create user with validated data
        $sql = 'INSERT INTO users (username, email, password, first_name, last_name, role_id, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
        $stmt = $this->db->prepare($sql);

        try {
            $ok = $stmt->execute([
                $validatedData['username'],
                $validatedData['email'],
                password_hash($validatedData['password'], PASSWORD_DEFAULT),
                $validatedData['first_name'],
                $validatedData['last_name'],
                $validatedData['role_id'] ?? 1,
                $validatedData['status'] ?? 'active'
            ]);

            if ($ok) {
                $id = $this->db->lastInsertId();

                // Audit log
                $currentUserId = $this->getCurrentUserId();
                $this->auditLogger->logUserCreate($currentUserId, $id, $validatedData);

                return ['success' => true, 'data' => $this->get($id)['data']];
            } else {
                return ['success' => false, 'error' => 'User creation failed'];
            }
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }

    public function bulkCreate($data)
    {
        // Create multiple users in a single transaction
        // Expected input: {"users": [{"username": "...", "email": "...", ...}, ...]}
        if (!isset($data['users']) || !is_array($data['users']) || empty($data['users'])) {
            return ['success' => false, 'error' => 'users array is required and must not be empty'];
        }

        $this->db->beginTransaction();
        $created = [];
        $failed = [];

        try {
            $stmt = $this->db->prepare('INSERT INTO users (username, email, password, first_name, last_name, role_id, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');

            foreach ($data['users'] as $index => $userData) {
                // Validate required fields
                if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'Missing required fields: username, email, password'
                    ];
                    continue;
                }

                $ok = $stmt->execute([
                    $userData['username'],
                    $userData['email'],
                    password_hash($userData['password'], PASSWORD_DEFAULT),
                    $userData['first_name'] ?? '',
                    $userData['last_name'] ?? '',
                    $userData['role_id'] ?? 1,
                    $userData['status'] ?? 'active'
                ]);

                if ($ok) {
                    $userId = $this->db->lastInsertId();

                    // If role_ids provided, assign roles and copy permissions
                    if (isset($userData['role_ids']) && is_array($userData['role_ids']) && !empty($userData['role_ids'])) {
                        $roleResult = $this->userRoleManager->bulkAssignRoles($userId, $userData['role_ids']);
                        if (!$roleResult['success']) {
                            $failed[] = [
                                'index' => $index,
                                'user_id' => $userId,
                                'data' => $userData,
                                'error' => 'User created but role assignment failed: ' . ($roleResult['error'] ?? 'Unknown error')
                            ];
                            continue;
                        }
                    }

                    $created[] = [
                        'index' => $index,
                        'user_id' => $userId,
                        'username' => $userData['username'],
                        'email' => $userData['email']
                    ];
                } else {
                    $failed[] = [
                        'index' => $index,
                        'data' => $userData,
                        'error' => 'User creation failed'
                    ];
                }
            }

            $this->db->commit();
            return [
                'success' => true,
                'data' => [
                    'created' => $created,
                    'failed' => $failed,
                    'summary' => [
                        'total' => count($data['users']),
                        'created_count' => count($created),
                        'failed_count' => count($failed)
                    ]
                ]
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => 'Bulk creation failed: ' . $e->getMessage()];
        }
    }
    public function update($id, $data)
    {
        // Get current user data for audit log
        $oldDataResult = $this->get($id);
        if (!$oldDataResult['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $oldData = $oldDataResult['data'];

        // Validate input data
        $validation = ValidationHelper::validateUserData($data, $this->db, true, $id);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validation['errors']
            ];
        }

        $validatedData = $validation['data'];

        // Build update query with validated data
        $fields = [];
        $params = [];

        foreach (['username', 'email', 'first_name', 'last_name', 'status', 'role_id'] as $field) {
            if (isset($validatedData[$field])) {
                $fields[] = "$field = ?";
                $params[] = $validatedData[$field];
            }
        }

        if (isset($validatedData['password'])) {
            $fields[] = 'password = ?';
            $params[] = password_hash($validatedData['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            return ['success' => false, 'error' => 'No fields to update'];
        }
        
        $params[] = $id;
        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?';

        try {
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute($params);

            if ($ok) {
                // Audit log
                $currentUserId = $this->getCurrentUserId();
                $this->auditLogger->logUserUpdate($currentUserId, $id, $oldData, $validatedData);

                return ['success' => true, 'data' => $this->get($id)['data']];
            } else {
                return ['success' => false, 'error' => 'User update failed'];
            }
        } catch (Exception $e) {
            error_log("User update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    public function delete($id)
    {
        // Get user data before deletion for audit log
        $userDataResult = $this->get($id);
        if (!$userDataResult['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $userData = $userDataResult['data'];

        // Prevent deletion of own account
        $currentUserId = $this->getCurrentUserId();
        if ($currentUserId == $id) {
            return ['success' => false, 'error' => 'Cannot delete your own account'];
        }

        try {
            // Delete user
            $stmt = $this->db->prepare('DELETE FROM users WHERE id = ?');
            $ok = $stmt->execute([$id]);

            if ($ok) {
                // Audit log
                $this->auditLogger->logUserDelete($currentUserId, $id, $userData);

                return ['success' => true, 'data' => ['id' => $id, 'deleted' => true]];
            } else {
                return ['success' => false, 'error' => 'User deletion failed'];
            }
        } catch (Exception $e) {
            error_log("User deletion error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error occurred'];
        }
    }
    public function getProfile($userId)
    {
        // Fetch user profile (basic info + roles + permissions)
        $user = $this->get($userId);
        if (!$user['success']) {
            return ['success' => false, 'error' => 'User not found'];
        }
        $roles = $this->userRoleManager->getUserRoles($userId);
        $permissions = $this->userPermissionManager->getEffectivePermissions($userId);
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
        // Get current permissions IDs
        $currentPerms = $this->userPermissionManager->getDirectPermissions($id);
        $currentPermIds = array_column($currentPerms['data'] ?? [], 'id');

        // Remove all current direct permissions
        if (!empty($currentPermIds)) {
            $this->userPermissionManager->bulkRevokePermissions($id, $currentPermIds);
        }
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
        // Get roles and permissions
        $roles = $this->userRoleManager->getUserRoles($user['id']);
        $permissions = $this->userPermissionManager->getEffectivePermissions($user['id']);

        // Extract permission CODES only (not full objects)
        $permissionCodes = [];
        if (!empty($permissions['data'])) {
            foreach ($permissions['data'] as $perm) {
                // Handle both objects and arrays
                $code = is_array($perm) ? ($perm['code'] ?? $perm['permission_code'] ?? null) : $perm;
                if ($code) {
                    $permissionCodes[] = $code;
                }
            }
        }

        // IMPORTANT: DO NOT store permissions in JWT token!
        // JWT tokens are sent with EVERY request in the Authorization header
        // Permissions should be stored in localStorage and sent separately when needed
        // This keeps the token small and prevents "Request Header Too Large" errors

        // Generate JWT token - ONLY authentication data (no permissions!)
        $token = $this->generateJWT([
            'user_id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'roles' => $roles['data'] ?? []
            // NO permissions in token!
        ]);

        // Return user info with token
        // Permissions are returned in the response body (not in token)
        // Return user info with token
        // Permissions are returned in the response body (not in token)
        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role_id' => $user['role_id'],
                    'status' => $user['status'] ?? null,
                    'roles' => $roles['data'] ?? [],
                    'permissions' => $permissionCodes  // In response body, NOT in token
                ]
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

    /**
     * Generate JWT token for authenticated user
     */
    private function generateJWT($userData)
    {
        $issuedAt = time();
        $expire = $issuedAt + (3600); // 1 hour expiry

        $payload = array_merge(
            $userData,
            [
                'iat' => $issuedAt,
                'exp' => $expire,
                'iss' => 'kingsway.ac.ke'
            ]
        );

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }
    
}