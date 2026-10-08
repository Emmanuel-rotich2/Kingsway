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
    /**
     * Teaching is the base. A teaching office is an ADDITIONAL duty on top of
     * Subject Teacher, never a substitute for it — the Headteacher and both
     * Deputy Heads go to class and teach their speciality every day.
     *
     * The rule is data (role_implied_roles), not code, so a new teaching office
     * is added with a row rather than a code change. Returns the extra role ids
     * that must accompany the requested one.
     *
     * @return list<int>
     */
    private function impliedRoleIds(int $roleId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT implies_role_id FROM role_implied_roles WHERE role_id = ?'
            );
            $stmt->execute([$roleId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (Exception $e) {
            // Never block a legitimate assignment because the advisory table is
            // missing on an older deployment.
            \App\API\Services\Logger::legacyError('[UserRoleManager] impliedRoleIds: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Record a role and every role it implies, copying permissions for each.
     *
     * @param list<int> $roleIds
     * @return list<int> all role ids actually written
     */
    private function recordRoles(int $userId, array $roleIds): array
    {
        $written = [];
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));

        foreach ($roleIds as $roleId) {
            $this->db->prepare(
                'INSERT INTO user_roles (user_id, role_id)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE user_id = user_id'
            )->execute([$userId, $roleId]);
            $written[] = $roleId;
        }

        // Copy the roles' permissions in ONE set-based statement.
        // A row-at-a-time loop cost ~1265 round-trips per role (~27s), and
        // assigning a teaching office copies two roles, so it doubled the cost
        // of every role assignment. Let the unique key absorb the duplicates.
        if ($roleIds) {
            $in = implode(',', array_fill(0, count($roleIds), '?'));
            $this->db->prepare(
                "INSERT IGNORE INTO user_permissions
                   (user_id, permission_id, permission_type, granted_by, created_at)
                 SELECT ?, rp.permission_id, 'grant', rp.role_id, NOW()
                   FROM role_permissions rp
                  WHERE rp.role_id IN ($in)"
            )->execute(array_merge([$userId], $roleIds));
        }

        return $written;
    }

    // SECTION 1: Role Assignment & Revocation
    // ============================================================================

    /**
     * Assign a role to a user. An explicitly assigned role becomes primary by
     * default; implied roles are always secondary. Callers adding an already
     * selected secondary role pass false.
     */
    public function assignRole($userId, $roleId, bool $makePrimary = true)
    {
        $userId = (int) $userId;
        $roleId = (int) $roleId;
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) $this->db->beginTransaction();
            $requested = [$roleId];

            // A teaching office carries its base role with it.
            foreach ($this->impliedRoleIds($roleId) as $implied) {
                $requested[] = $implied;
            }

            $written = $this->recordRoles((int) $userId, array_values(array_unique($requested)));
            if ($makePrimary || !$this->primaryRoleId($userId)) {
                $this->setPrimaryRole($userId, $roleId);
            }
            if ($ownsTransaction) $this->db->commit();

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_role',
                $userId,
                'role_assigned',
                'Role assigned to user',
                ['details' => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'roles_written' => $written,
                    'implied_added' => array_values(array_diff($written, [$roleId])),
                ]]
            );

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'assigned' => true,
                    'roles_written' => $written,
                    'implied_roles_added' => array_values(array_diff($written, [$roleId])),
                ]
            ];
        } catch (Exception $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    public function primaryRoleId(int $userId): ?int
    {
        $stmt = $this->db->prepare('SELECT role_id FROM user_roles WHERE user_id=? AND is_primary=1 LIMIT 1');
        $stmt->execute([$userId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int) $value;
    }

    public function setPrimaryRole(int $userId, int $roleId): void
    {
        $exists = $this->db->prepare('SELECT 1 FROM user_roles WHERE user_id=? AND role_id=? LIMIT 1');
        $exists->execute([$userId, $roleId]);
        if (!$exists->fetchColumn()) throw new Exception('Primary role must already be assigned to the user.');

        $this->db->prepare('UPDATE user_roles SET is_primary=NULL WHERE user_id=? AND is_primary=1')->execute([$userId]);
        $this->db->prepare('UPDATE user_roles SET is_primary=1 WHERE user_id=? AND role_id=?')->execute([$userId, $roleId]);
    }

    private function promoteFallbackPrimary(int $userId): void
    {
        if ($this->primaryRoleId($userId)) return;
        $stmt = $this->db->prepare('SELECT role_id FROM user_roles WHERE user_id=? ORDER BY id LIMIT 1');
        $stmt->execute([$userId]);
        $fallback = $stmt->fetchColumn();
        if ($fallback !== false) $this->setPrimaryRole($userId, (int) $fallback);
    }

    /**
     * Revoke role from user
     */
    /**
     * Roles that must not be revoked while the user still holds a role which
     * implies them.
     *
     * The teaching office is the REMOVABLE layer and the base profession is the
     * PERMANENT layer. If the school changes a deputy or removes the headteacher,
     * the office is revoked and the person is still a Subject Teacher with their
     * learning areas and timetable intact. Revoking the base role out from under
     * a live office would silently un-teach a teacher and break their timetable,
     * so it is refused.
     */
    private function protectedRoleIds(int $userId, int $roleId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT DISTINCT ir.implies_role_id
                   FROM user_roles held
                   JOIN role_implied_roles ir ON ir.role_id = held.role_id
                  WHERE held.user_id = ?
                    AND ir.implies_role_id = ?'
            );
            $stmt->execute([$userId, $roleId]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserRoleManager] protectedRoleIds: ' . $e->getMessage());
            return [];
        }
    }

    public function revokeRole($userId, $roleId)
    {
        try {
            $blocking = $this->protectedRoleIds((int) $userId, (int) $roleId);
            if ($blocking) {
                $offices = $this->db->query(
                    'SELECT r.name FROM user_roles ur
                       JOIN roles r ON r.id = ur.role_id
                       JOIN role_implied_roles ir ON ir.role_id = ur.role_id
                      WHERE ur.user_id = ' . (int) $userId . ' AND ir.implies_role_id = ' . (int) $roleId
                )->fetchAll(PDO::FETCH_COLUMN);
                return [
                    'status' => 'error',
                    'message' => 'Cannot revoke a role that is required by another role this user still holds. '
                        . 'Revoke the office role first: '
                        . implode(', ', array_unique($offices)) . '.',
                    'blocked_by' => $offices,
                ];
            }

            // Step 1: Revoke the role
            $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute([$userId, $roleId]);
            if ($ok) $this->promoteFallbackPrimary((int) $userId);

            if ($ok) {
                // Step 2: Remove permissions that came from this specific role
                // Only delete if user doesn't have this permission from another role
                $delPermSql = 'DELETE FROM user_permissions 
                              WHERE user_id = ? 
                              AND permission_type = "grant"
                              AND permission_id IN (
                                  SELECT permission_id FROM role_permissions WHERE role_id = ?
                              )
                              AND permission_id NOT IN (
                                  SELECT DISTINCT rp.permission_id 
                                  FROM role_permissions rp
                                  JOIN user_roles ur ON rp.role_id = ur.role_id
                                  WHERE ur.user_id = ?
                              )';
                $delPermStmt = $this->db->prepare($delPermSql);
                $delPermStmt->execute([$userId, $roleId, $userId]);
            }

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_role',
                $userId,
                'role_revoked',
                'Role revoked from user',
                ['details' => ['user_id' => $userId, 'role_id' => $roleId]]
            );

            return [
                'success' => $ok,
                'data' => [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'revoked' => $ok
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    // ============================================================================
    // SECTION 2: Get User Roles
    // ============================================================================

    /**
     * Get user's roles (basic info)
     * Roles are stored in the user_roles join table (users no longer has a role_id column)
     */
    public function getUserRoles($userId)
    {
        try {
            $sql = 'SELECT r.*, ur.is_primary
                    FROM roles r
                    JOIN user_roles ur ON ur.role_id = r.id
                    WHERE ur.user_id = ?
                    ORDER BY ur.is_primary DESC, ur.id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $roles,
                'count' => count($roles)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            // Expand with implied base roles first. A teaching office must not
            // be assignable without the Subject Teacher role it sits on top of.
            $expanded = [];
            foreach ($roleIds as $roleId) {
                $roleId = (int) $roleId;
                $expanded[] = $roleId;
                foreach ($this->impliedRoleIds($roleId) as $implied) {
                    $expanded[] = $implied;
                }
            }
            $roleIds = array_values(array_unique($expanded));

            // recordRoles writes the role rows and copies permissions set-based.
            $written = $roleIds ? $this->recordRoles((int) $userId, $roleIds) : [];
            $count = count($written);
            if ($roleIds && !$this->primaryRoleId((int) $userId)) {
                $this->setPrimaryRole((int) $userId, (int) $roleIds[0]);
            }

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_role',
                $userId,
                'role_assigned',
                'Roles assigned to user in bulk',
                ['details' => ['user_id' => $userId, 'role_ids' => $roleIds, 'assigned' => $count]]
            );

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'assigned_roles' => $count,
                    'total_attempted' => count($roleIds)
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /** Replace school roles as one unit and set the chosen dashboard role. */
    public function replaceRoles(int $userId, array $roleIds, int $primaryRoleId, string $scope = 'school'): array
    {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', $roleIds), static fn($id) => $id > 0)));
        if (!$roleIds || !in_array($primaryRoleId, $roleIds, true)) {
            return ['success' => false, 'error' => 'The primary role must be one of the selected roles.'];
        }
        if (!in_array($scope, ['school', 'system', 'all'], true)) {
            return ['success' => false, 'error' => 'Invalid role scope.'];
        }

        $expanded = [];
        foreach ($roleIds as $roleId) {
            $expanded[] = $roleId;
            array_push($expanded, ...$this->impliedRoleIds($roleId));
        }
        $expanded = array_values(array_unique($expanded));
        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) $this->db->beginTransaction();
            if ($scope === 'all') {
                $this->db->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$userId]);
            } else {
                $this->db->prepare(
                    "DELETE ur FROM user_roles ur JOIN roles r ON r.id=ur.role_id
                      WHERE ur.user_id=? AND r.scope=?
                        AND r.is_system=0 AND LOWER(r.name)<>'parent'"
                )->execute([$userId, $scope]);
            }
            $this->recordRoles($userId, $expanded);
            $this->setPrimaryRole($userId, $primaryRoleId);
            if ($ownsTransaction) $this->db->commit();
            return ['success' => true, 'data' => ['user_id' => $userId, 'role_ids' => $expanded, 'primary_role_id' => $primaryRoleId]];
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[UserRoleManager] replaceRoles: ' . $error->getMessage());
            return ['success' => false, 'error' => 'Roles could not be updated.'];
        }
    }

    /**
     * Revoke multiple roles from user.
     *
     * Same protection as revokeRole(): a role required by another role the user
     * still holds cannot be revoked. Removing the office and the base role
     * together in one call is allowed, because after the batch the user no
     * longer holds the office that requires the base.
     */
    public function bulkRevokeRoles($userId, $roleIds)
    {
        try {
            $userId = (int) $userId;
            $removing = array_map('intval', $roleIds);
            $blocking = [];
            foreach ($removing as $candidate) {
                foreach ($this->protectedRoleIds($userId, $candidate) as $implied) {
                    // Allowed when the office that requires it is part of the same batch.
                    $offices = $this->db->query(
                        'SELECT ir.role_id FROM user_roles ur
                           JOIN role_implied_roles ir ON ir.role_id = ur.role_id
                          WHERE ur.user_id = ' . $userId . ' AND ir.implies_role_id = ' . $candidate
                    )->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($offices as $office) {
                        if (!in_array((int) $office, $removing, true)) {
                            $blocking[(int) $office] = true;
                        }
                    }
                }
            }
            if ($blocking) {
                $names = $this->db->query(
                    'SELECT name FROM roles WHERE id IN (' . implode(',', array_keys($blocking)) . ')'
                )->fetchAll(PDO::FETCH_COLUMN);
                return [
                    'status' => 'error',
                    'message' => 'Cannot revoke a role that is required by another role this user still holds. '
                        . 'Revoke the office role first: ' . implode(', ', $names) . '.',
                    'blocked_by' => $names,
                ];
            }

            $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($roleIds as $roleId) {
                if ($stmt->execute([$userId, $roleId])) {
                    $count++;
                }
            }
            $this->promoteFallbackPrimary((int) $userId);

            // Remove permissions that came from the revoked roles, while keeping any that still exist via other roles
            if ($count > 0) {
                $inPlaceholders = implode(',', array_fill(0, count($roleIds), '?'));
                $delPermSql = "DELETE FROM user_permissions 
                              WHERE user_id = ?
                                AND permission_type = 'grant'
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

            \App\API\Includes\SecurityEventNotifier::permissionChange(
                'user_role',
                $userId,
                'role_revoked',
                'Roles revoked from user in bulk',
                ['details' => ['user_id' => $userId, 'role_ids' => $roleIds, 'revoked' => $count]]
            );

            return [
                'success' => true,
                'data' => [
                    'user_id' => $userId,
                    'revoked_roles' => $count,
                    'total_attempted' => count($roleIds)
                ]
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }

    /**
     * Assign role to multiple users
     */
    public function bulkAssignUsersToRole($roleId, $userIds)
    {
        $roleId = (int) $roleId;
        $ids = array_values(array_unique(array_map('intval', is_array($userIds) ? $userIds : [])));
        $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
        if ($roleId <= 0 || !$ids) {
            return ['success' => false, 'error' => 'A role and at least one valid user are required.'];
        }
        if (count($ids) > 500) {
            return ['success' => false, 'error' => 'A role assignment is limited to 500 accounts.'];
        }

        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) $this->db->beginTransaction();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $findUsers = $this->db->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
            $findUsers->execute($ids);
            $validIds = array_map('intval', $findUsers->fetchAll(PDO::FETCH_COLUMN));
            if (!$validIds) {
                if ($ownsTransaction) $this->db->commit();
                return ['success' => false, 'error' => 'None of the selected accounts still exist.'];
            }

            // Implied base roles come along. Assigning a teaching office in bulk
            // (e.g. to every class teacher) must not strip their base role.
            $impliedForRole = $this->impliedRoleIds((int) $roleId);
            $allRoles = array_values(array_unique(array_merge([(int) $roleId], $impliedForRole)));
            foreach ($validIds as $userId) {
                $this->recordRoles((int) $userId, $allRoles);
                $this->setPrimaryRole((int) $userId, $roleId);
            }
            if ($ownsTransaction) $this->db->commit();

            foreach ($validIds as $userId) {
                \App\API\Includes\SecurityEventNotifier::permissionChange(
                    'user_role',
                    $userId,
                    'role_assigned',
                    'Role assigned to user',
                    ['details' => ['user_id' => $userId, 'role_id' => $roleId, 'bulk' => true]]
                );
            }

            return [
                'success' => true,
                'data' => [
                    'role_id' => $roleId,
                    'assigned_users' => count($validIds),
                    'user_ids' => $validIds,
                    'skipped' => array_values(array_diff($ids, $validIds)),
                    'total_attempted' => count($ids)
                ]
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return ['success' => false, 'code' => 500, 'error' => 'The role could not be assigned to the selected accounts.'];
        }
    }

    /**
     * Revoke role from multiple users
     */
    /**
     * Revoke role from multiple users.
     *
     * A base role required by an office the user still holds is refused, for
     * the same reason as revokeRole(): the office is the removable layer.
     */
    public function bulkRevokeUsersFromRole($roleId, $userIds)
    {
        try {
            $roleId = (int) $roleId;
            $blocked = [];
            foreach ($userIds as $userId) {
                if ($this->protectedRoleIds((int) $userId, $roleId)) {
                    $blocked[] = (int) $userId;
                }
            }
            if ($blocked) {
                return [
                    'success' => false,
                    'code' => 409,
                    'error' => 'This role is required by another role those users still hold. '
                        . 'Revoke the office role first.',
                    'blocked_user_ids' => $blocked,
                ];
            }

            $sql = 'DELETE FROM user_roles WHERE user_id = ? AND role_id = ?';
            $stmt = $this->db->prepare($sql);
            $count = 0;

            foreach ($userIds as $userId) {
                if ($stmt->execute([$userId, $roleId])) {
                    $count++;
                    $this->promoteFallbackPrimary((int) $userId);
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
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
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
            \App\API\Services\Logger::legacyError('[UserRoleManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return ['success' => false, 'error' => 'An internal error occurred.'];
        }
    }
}
