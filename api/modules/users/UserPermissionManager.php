<?php
namespace App\API\Modules\users;

use App\API\Services\ReadReplicaService;
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

    /** Distinct user ids holding ANY of the given effective permission codes. */
    public function userIdsWithPermissions(array $codes): array
    {
        $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
        if ($codes === []) return [];
        $marks = implode(',', array_fill(0, count($codes), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT user_id FROM v_user_permissions_effective
             WHERE permission_code IN ($marks) AND user_id IS NOT NULL AND user_id > 0"
        );
        $stmt->execute($codes);
        return array_values(array_unique(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN))));
    }

    /** Effective permissions keyed by user id for the given user id set. */
    public function effectivePermissionsByUser(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) return [];
        $marks = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT user_id, permission_code FROM v_user_permissions_effective WHERE user_id IN ($marks)"
        );
        $stmt->execute($userIds);
        $byUser = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            $perm = trim((string) ($row['permission_code'] ?? ''));
            if ($uid > 0 && $perm !== '') $byUser[$uid][] = $perm;
        }
        return $byUser;
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

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_permission',
                $userId,
                'permission_assign',
                'Permission assigned to user',
                ['details' => [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'permission_type' => $permissionType,
                    'expires_at' => $expiresAt,
                    'reason' => $reason,
                ]]
            );

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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_permission',
                $userId,
                'permission_revoke',
                'Permission revoked from user',
                ['details' => ['user_id' => $userId, 'permission_id' => $permissionId]]
            );

            return [
                'success' => $ok,
                'data' => [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                    'revoked' => $ok
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            // Optimized direct query instead of stored procedure to avoid view overhead
            // Gets role-based permissions (fast)
            $sql = 'SELECT DISTINCT p.id, p.code, p.entity, p.action
                    FROM permissions p
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                    WHERE ur.user_id = ?
                    UNION ALL
                    SELECT DISTINCT p.id, p.code, p.entity, p.action
                    FROM permissions p
                    INNER JOIN user_permissions up ON p.id = up.permission_id
                    WHERE up.user_id = ?
                    AND up.permission_type IN (\'grant\', \'override\')
                    AND (up.expires_at IS NULL OR up.expires_at > NOW())
                    ORDER BY code';
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId, $userId]);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $permissions,
                'count' => count($permissions)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_permission',
                $userId,
                'permission_assign',
                'Permissions assigned to user in bulk',
                ['details' => ['user_id' => $userId, 'assigned_count' => count($results), 'details' => $results]]
            );

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'assigned_count' => count($results),
                    'details' => $results
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_permission',
                $userId,
                'permission_revoke',
                'Permissions revoked from user in bulk',
                ['details' => ['user_id' => $userId, 'revoked_count' => $count, 'total_attempted' => count($permissionIds)]]
            );

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'revoked_count' => $count,
                    'total_attempted' => count($permissionIds)
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserPermissionManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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

    /** Account-level profile for a user (person fields + auth shapes). */
    public function profileForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.first_name, p.middle_name, p.last_name, p.email,
                    p.phone, p.gender, p.dob AS date_of_birth,
                    u.username, u.status, u.last_login
             FROM users u
             JOIN ' . ReadReplicaService::masterRef('persons') . ' p ON p.id = u.person_id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Display labels (username + full name) for a set of user ids. */
    public function displayNamesById(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if ($userIds === []) return [];
        $marks = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT u.id AS user_id, u.username,
                    CONCAT_WS(' ', p.first_name, p.last_name) AS full_name
             FROM users u
             LEFT JOIN " . ReadReplicaService::masterRef("persons") . " p ON p.id = u.person_id
             WHERE u.id IN ($marks)"
        );
        $stmt->execute($userIds);
        $names = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $names[(int) $row['user_id']] = ['username' => $row['username'], 'full_name' => $row['full_name']];
        }
        return $names;
    }


}
