<?php
namespace App\API\Modules\users;

use PDO;
use Exception;

/**
 * UserRoleManager - Manages user role assignments and role-related queries
 * 
 * Handles:
 * - User role assignment and revocation
 * - Querying user roles with permission details
 * - Finding users with specific roles
 * - Bulk role operations
 */
class UserRoleManager
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ============================================================================
    // SECTION 1: Role Assignment & Revocation
    // ============================================================================

    /**
     * Assign role to user
     */
    public function assignRole($userId, $roleId)
    {
        try {
            // Step 1: Assign role to user
            $sql = 'INSERT INTO user_roles (user_id, role_id) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE user_id = user_id';
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([$userId, $roleId]);

            if (!$ok) {
                return ['success' => false, 'error' => 'Failed to assign role'];
            }

            // Step 2: Copy all role permissions to user_permissions table
            // Get all permissions from the role
            $permSql = 'SELECT permission_id FROM role_permissions WHERE role_id = ?';
            $permStmt = $this->db->prepare($permSql);
            $permStmt->execute([$roleId]);
            $rolePermissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rolePermissions)) {
                // Insert each role permission into user_permissions with source='role'
                $insertPermSql = 'INSERT IGNORE INTO user_permissions 
                                 (user_id, permission_id, permission_type, granted_by, created_at) 
                                 VALUES (?, ?, ?, ?, NOW())';
                $insertPermStmt = $this->db->prepare($insertPermSql);

                foreach ($rolePermissions as $perm) {
                    $insertPermStmt->execute([
                        $userId,
                        $perm['permission_id'],
                        'grant',  // default permission type
                        $roleId   // granted_by is the role_id
                    ]);
                }
            }

            return [
                'success' => $ok,
                'data' => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned' => $ok,
                    'permissions_copied' => count($rolePermissions)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revoke role from user
     */
    public function revokeRole($userId, $roleId)
    {
        try {
            // Step 1: Revoke the role
            $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([$userId, $roleId]);

            if ($ok) {
                // Step 2: Remove permissions that came from this specific role
                // Only delete if user doesn't have this permission from another role
                $delPermSql = 'DELETE FROM user_permissions 
                              WHERE user_id = ? 
                              AND permission_id IN (
                                  SELECT permission_id FROM role_permissions WHERE role_id = ?
                              )
                              AND source = "role"
                              AND permission_id NOT IN (
                                  SELECT DISTINCT rp.permission_id 
                                  FROM role_permissions rp
                                  JOIN user_roles ur ON rp.role_id = ur.role_id
                                  WHERE ur.user_id = ?
                              )';
                $delPermStmt = $this->db->prepare($delPermSql);
                $delPermStmt->execute([$userId, $roleId, $userId]);
            }

            return [
                'success' => $ok,
                'data' => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'revoked' => $ok
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 2: Get User Roles
    // ============================================================================

    /**
     * Get user's roles (basic info)
     * Includes both primary role (from users.role_id) and additional roles (from user_roles)
     */
    public function getUserRoles($userId)
    {
        try {
            // Get all roles: primary role from users table + additional roles from user_roles
            $sql = 'SELECT DISTINCT r.* FROM roles r 
                    WHERE r.id = (
                        SELECT role_id FROM users WHERE id = ?
                    )
                    OR r.id IN (
                        SELECT role_id FROM user_roles WHERE user_id = ?
                    )
                    ORDER BY r.name';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $roles,
                'count' => count($roles)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's roles with permission counts and details
     * Uses procedure: sp_user_get_roles_detailed
     */
    public function getRolesDetailed($userId)
    {
        try {
            $sql = 'CALL sp_user_get_roles_detailed(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $roles,
                'count' => count($roles)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 3: Bulk Operations
    // ============================================================================

    /**
     * Assign multiple roles to user
     */
    public function bulkAssignRoles($userId, $roleIds)
    {
        try {
            // Step 1: assign all roles
            $sql = 'INSERT INTO user_roles (user_id, role_id) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE user_id = user_id';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($roleIds as $roleId) {
                if ($stmt->execute([$userId, $roleId])) {
                    $count++;
                }
            }

            // Step 2: copy permissions for all assigned roles into user_permissions
            if ($count > 0) {
                // fetch all permissions for the provided roles
                $inPlaceholders = implode(',', array_fill(0, count($roleIds), '?'));
                $permSql = "SELECT DISTINCT permission_id, role_id FROM role_permissions WHERE role_id IN ($inPlaceholders)";
                $permStmt = $this->db->prepare($permSql);
                $permStmt->execute($roleIds);
                $rolePermissions = $permStmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rolePermissions)) {
                    $insertPermSql = 'INSERT IGNORE INTO user_permissions 
                                     (user_id, permission_id, permission_type, granted_by, created_at) 
                                     VALUES (?, ?, ?, ?, NOW())';
                    $insertPermStmt = $this->db->prepare($insertPermSql);

                    foreach ($rolePermissions as $perm) {
                        $insertPermStmt->execute([
                            $userId,
                            $perm['permission_id'],
                            'grant',        // default grant
                            $perm['role_id']
                        ]);
                    }
                }
            }

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'assigned_roles' => $count,
                    'total_attempted' => count($roleIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revoke multiple roles from user
     */
    public function bulkRevokeRoles($userId, $roleIds)
    {
        try {
            $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($roleIds as $roleId) {
                if ($stmt->execute([$userId, $roleId])) {
                    $count++;
                }
            }

            // Remove permissions that came from the revoked roles, while keeping any that still exist via other roles
            if ($count > 0) {
                $inPlaceholders = implode(',', array_fill(0, count($roleIds), '?'));
                $delPermSql = "DELETE FROM user_permissions 
                              WHERE user_id = ?
                                AND permission_id IN (
                                    SELECT permission_id FROM role_permissions WHERE role_id IN ($inPlaceholders)
                                )
                                AND permission_id NOT IN (
                                    SELECT DISTINCT rp.permission_id
                                    FROM role_permissions rp
                                    JOIN user_roles ur ON rp.role_id = ur.role_id
                                    WHERE ur.user_id = ?
                                )";
                $params = array_merge([$userId], $roleIds, [$userId]);
                $delPermStmt = $this->db->prepare($delPermSql);
                $delPermStmt->execute($params);
            }

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'revoked_roles' => $count,
                    'total_attempted' => count($roleIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assign role to multiple users
     */
    public function bulkAssignUsersToRole($roleId, $userIds)
    {
        try {
            $sql = 'INSERT INTO user_roles (user_id, role_id) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE user_id = user_id';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($userIds as $userId) {
                if ($stmt->execute([$userId, $roleId])) {
                    $count++;
                }
            }

            return [
                'success' => true,
                'data' => [
                    'role_id' => $roleId,
                    'assigned_users' => $count,
                    'total_attempted' => count($userIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revoke role from multiple users
     */
    public function bulkRevokeUsersFromRole($roleId, $userIds)
    {
        try {
            $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($userIds as $userId) {
                if ($stmt->execute([$userId, $roleId])) {
                    $count++;
                }
            }

            return [
                'success' => true,
                'data' => [
                    'role_id' => $roleId,
                    'revoked_users' => $count,
                    'total_attempted' => count($userIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 4: Query Helpers
    // ============================================================================

    /**
     * Get all users with specific role
     * Uses procedure: sp_users_with_role
     */
    public function getUsersWithRole($roleName)
    {
        try {
            $sql = 'CALL sp_users_with_role(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$roleName]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $users,
                'count' => count($users),
                'role' => $roleName
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get users with multiple roles
     * Uses procedure: sp_users_with_multiple_roles
     */
    public function getUsersWithMultipleRoles()
    {
        try {
            $sql = 'CALL sp_users_with_multiple_roles()';
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $users,
                'count' => count($users)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
