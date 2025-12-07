<?php
namespace App\API\Modules\users;

use PDO;
use Exception;

/**
 * UserPermissionManager - Manages user permissions and permission-related queries
 * 
 * Handles:
 * - User permission grants, denials, and overrides
 * - Querying user effective permissions (role-based + direct)
 * - Managing temporary permissions
 * - Bulk permission operations
 * - Permission precedence (deny > override > grant > role-based)
 */
class UserPermissionManager
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ============================================================================
    // SECTION 1: Permission Assignment & Revocation
    // ============================================================================

    /**
     * Assign a permission to a user (direct grant)
     * 
     * @param int $userId
     * @param array $permission - ['permission_id' or 'permission_code', 'permission_type' => 'grant'|'deny'|'override', 'expires_at' => optional, 'reason' => optional, 'granted_by' => optional]
     * @return array
     */
    public function assignPermission($userId, $permission)
    {
        try {
            $permissionId = $this->getPermissionId($permission);
            if (!$permissionId) {
                return ['success' => false, 'error' => 'Permission not found'];
            }

            $permissionType = $permission['permission_type'] ?? 'grant';
            $expiresAt = $permission['expires_at'] ?? null;
            $reason = $permission['reason'] ?? null;
            $grantedBy = $permission['granted_by'] ?? null;

            $sql = 'INSERT INTO user_permissions (user_id, permission_id, permission_type, expires_at, reason, granted_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        permission_type = VALUES(permission_type),
                        expires_at = VALUES(expires_at),
                        reason = VALUES(reason),
                        granted_by = VALUES(granted_by),
                        updated_at = NOW()';

            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([$userId, $permissionId, $permissionType, $expiresAt, $reason, $grantedBy]);

            return [
                'success' => $ok,
                'data' => [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'permission_type' => $permissionType,
                    'expires_at' => $expiresAt,
                    'assigned' => $ok
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revoke a permission from a user
     */
    public function revokePermission($userId, $permissionId)
    {
        try {
            $sql = 'DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?';
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([$userId, $permissionId]);

            return [
                'success' => $ok,
                'data' => [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'revoked' => $ok
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 2: Get User Permissions (Effective & Direct)
    // ============================================================================

    /**
     * Get user's effective permissions (role-based + direct grants - denials)
     * Uses procedure: sp_user_get_effective_permissions
     */
    public function getEffectivePermissions($userId)
    {
        try {
            $sql = 'CALL sp_user_get_effective_permissions(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $permissions,
                'count' => count($permissions)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's role-based permissions only
     */
    public function getRoleBasedPermissions($userId)
    {
        try {
            $sql = 'SELECT DISTINCT p.* FROM permissions p 
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                    WHERE ur.user_id = ?
                    ORDER BY p.code';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $permissions,
                'source' => 'role',
                'count' => count($permissions)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's direct permissions (grants, denials, overrides)
     */
    public function getDirectPermissions($userId)
    {
        try {
            $sql = 'SELECT p.*, up.permission_type, up.expires_at, up.reason, up.granted_by
                    FROM permissions p
                    INNER JOIN user_permissions up ON p.id = up.permission_id
                    WHERE up.user_id = ?
                    ORDER BY p.code';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $permissions,
                'count' => count($permissions)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's denied permissions
     * Uses procedure: sp_user_get_denied_permissions
     */
    public function getDeniedPermissions($userId)
    {
        try {
            $sql = 'CALL sp_user_get_denied_permissions(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $permissions,
                'count' => count($permissions)
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user's permissions organized by entity
     * Uses procedure: sp_user_get_permissions_by_entity
     */
    public function getPermissionsByEntity($userId)
    {
        try {
            $sql = 'CALL sp_user_get_permissions_by_entity(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $permissions
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get permission summary for user (total, role-based, direct, denied)
     * Uses procedure: sp_user_get_permission_summary
     */
    public function getPermissionSummary($userId)
    {
        try {
            $sql = 'CALL sp_user_get_permission_summary(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);

            $summary = [];
            while ($stmt->nextRowset()) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($results)) {
                    foreach ($results as $row) {
                        $summary[] = $row;
                    }
                }
            }

            return [
                'success' => true,
                'data' => $summary
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 3: Permission Checking
    // ============================================================================

    /**
     * Check if user has specific permission (returns boolean)
     * Uses function: fn_user_has_permission
     */
    public function hasPermission($userId, $permissionCode)
    {
        try {
            $sql = 'SELECT fn_user_has_permission(?, ?) as has_perm';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $permissionCode]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'has_permission' => (bool) $result['has_perm'],
                'permission_code' => $permissionCode
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check multiple permissions for a user
     */
    public function hasPermissions($userId, $permissionCodes)
    {
        try {
            $results = [];
            foreach ($permissionCodes as $code) {
                $sql = 'SELECT fn_user_has_permission(?, ?) as has_perm';
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$userId, $code]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $results[$code] = (bool) $result['has_perm'];
            }

            return [
                'success' => true,
                'data' => $results
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 4: Bulk Operations
    // ============================================================================

    /**
     * Assign multiple permissions to user
     */
    public function bulkAssignPermissions($userId, $permissions)
    {
        try {
            $results = [];
            $sql = 'INSERT INTO user_permissions (user_id, permission_id, permission_type, expires_at, reason, granted_by) 
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        permission_type = VALUES(permission_type),
                        expires_at = VALUES(expires_at)';
            $stmt = $this->db->prepare($sql);

            foreach ($permissions as $perm) {
                $permId = $this->getPermissionId($perm);
                if ($permId) {
                    $permType = $perm['permission_type'] ?? 'grant';
                    $expiresAt = $perm['expires_at'] ?? null;
                    $reason = $perm['reason'] ?? null;
                    $grantedBy = $perm['granted_by'] ?? null;

                    $ok = $stmt->execute([$userId, $permId, $permType, $expiresAt, $reason, $grantedBy]);
                    $results[] = ['permission_id' => $permId, 'success' => $ok];
                }
            }

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'assigned_count' => count($results),
                    'details' => $results
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revoke multiple permissions from user
     */
    public function bulkRevokePermissions($userId, $permissionIds)
    {
        try {
            $sql = 'DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($permissionIds as $permId) {
                if ($stmt->execute([$userId, $permId])) {
                    $count++;
                }
            }

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'revoked_count' => $count,
                    'total_attempted' => count($permissionIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Assign permission to multiple users
     */
    public function bulkAssignUsersToPermission($permissionId, $userIds, $permType = 'grant')
    {
        try {
            $sql = 'INSERT INTO user_permissions (user_id, permission_id, permission_type) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE permission_type = VALUES(permission_type)';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($userIds as $userId) {
                if ($stmt->execute([$userId, $permissionId, $permType])) {
                    $count++;
                }
            }

            return [
                'success' => true,
                'data' => [
                    'permission_id' => $permissionId,
                    'assigned_users' => $count,
                    'total_attempted' => count($userIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Revoke permission from multiple users
     */
    public function bulkRevokeUsersFromPermission($permissionId, $userIds)
    {
        try {
            $sql = 'DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($userIds as $userId) {
                if ($stmt->execute([$userId, $permissionId])) {
                    $count++;
                }
            }

            return [
                'success' => true,
                'data' => [
                    'permission_id' => $permissionId,
                    'revoked_users' => $count,
                    'total_attempted' => count($userIds)
                ]
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // SECTION 5: Query Helpers
    // ============================================================================

    /**
     * Get all users with specific permission
     * Uses procedure: sp_users_with_permission
     */
    public function getUsersWithPermission($permissionCode)
    {
        try {
            $sql = 'CALL sp_users_with_permission(?)';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$permissionCode]);
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

    /**
     * Get users with temporary permissions expiring soon
     * Uses procedure: sp_users_with_temporary_permissions
     */
    public function getUsersWithTemporaryPermissions()
    {
        try {
            $sql = 'CALL sp_users_with_temporary_permissions()';
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

    // ============================================================================
    // SECTION 6: Helper Methods
    // ============================================================================

    /**
     * Get permission ID from either ID or code
     */
    private function getPermissionId($permission)
    {
        if (isset($permission['permission_id'])) {
            return $permission['permission_id'];
        }

        if (isset($permission['permission_code'])) {
            $sql = 'SELECT id FROM permissions WHERE code = ? LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$permission['permission_code']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
        }

        return null;
    }
}
