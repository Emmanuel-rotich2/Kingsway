<?php

namespace App\API\Middleware;

use App\Database\Database;
use PDO;

/**
 * Enhanced RBAC Middleware with Module & Workflow Support
 *
 * Resolves effective permissions with:
 * - Module-scoped permission checking
 * - Workflow stage guards
 * - Data scope filtering
 * - Permission caching
 *
 * @since 2026-03-29
 */
class EnhancedRBACMiddleware
{
    /**
     * Resolve effective permissions with workflow context
     */
    public static function resolvePermissionsWithContext($userId, $workflowId = null, $stageId = null)
    {
        try {
            $db = Database::getInstance();

            // Get base permissions from role + user
            $basePermissions = self::resolveBasePermissions($db, $userId);

            // If in workflow context, add stage-specific permissions
            if ($workflowId && $stageId) {
                $stagePermissions = self::resolveWorkflowStagePermissions($db, $stageId, $userId, $workflowId);
                $basePermissions = array_merge($basePermissions, $stagePermissions);
            }

            // Expand aliases (underscore to dot notation)
            return self::expandPermissionAliases($basePermissions);

        } catch (\Exception $e) {
            error_log("Enhanced RBAC resolution failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get base permissions (roles + direct user overrides)
     */
    private static function resolveBasePermissions($db, $userId)
    {
        $stmt = $db->query(
            "SELECT DISTINCT p.code
             FROM user_roles ur
             JOIN role_permissions rp ON rp.role_id = ur.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?

             UNION DISTINCT

             SELECT DISTINCT p.code
             FROM user_permissions up
             JOIN permissions p ON p.id = up.permission_id
             WHERE up.user_id = ?
             AND up.permission_type IN ('grant', 'override')
             AND (up.expires_at IS NULL OR up.expires_at > NOW())",
            [$userId, $userId]
        );

        return array_values(array_unique(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN))));
    }

    /**
     * Get permissions specific to a workflow stage
     */
    private static function resolveWorkflowStagePermissions($db, $stageId, $userId, $workflowId)
    {
        try {
            // Check if user's role(s) are responsible for this stage
            $stmt = $db->query(
                "SELECT DISTINCT p.code
                 FROM workflow_stage_permissions wsp
                 JOIN permissions p ON p.id = wsp.permission_id
                 JOIN workflow_stages ws ON ws.id = wsp.workflow_stage_id
                 JOIN user_roles ur ON ur.user_id = ?
                 WHERE ws.id = ?
                 AND (
                   wsp.role_id IS NULL OR
                   wsp.role_id = ur.role_id
                 )
                 AND ws.workflow_id = ?",
                [$userId, $stageId, $workflowId]
            );

            return array_values(array_unique(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN))));

        } catch (\Exception $e) {
            error_log("Workflow stage permission resolution failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user can access route
     */
    public static function canAccessRoute($userId, $routeName)
    {
        try {
            $db = Database::getInstance();

            // Check route_permissions: does this route require a permission?
            $stmt = $db->query(
                "SELECT p.id
                 FROM routes r
                 LEFT JOIN route_permissions rp ON rp.route_id = r.id
                 LEFT JOIN permissions p ON p.id = rp.permission_id
                 WHERE r.name = ? AND r.is_active = 1
                 LIMIT 1",
                [$routeName]
            );

            $permission = $stmt->fetch();

            // If no permission requirement, allow via role_routes
            if (!$permission) {
                return self::canAccessViaRole($db, $userId, $routeName);
            }

            // Check if user has the required permission
            $userPermissions = self::resolveBasePermissions($db, $userId);
            $permissionCode = $permission->code ?? null;

            return $permissionCode && in_array($permissionCode, $userPermissions);

        } catch (\Exception $e) {
            error_log("Route access check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback: check role-based route access
     */
    private static function canAccessViaRole($db, $userId, $routeName)
    {
        $stmt = $db->query(
            "SELECT COUNT(*) as cnt
             FROM user_roles ur
             JOIN role_routes rr ON rr.role_id = ur.role_id
             JOIN routes r ON r.id = rr.route_id
             WHERE ur.user_id = ?
             AND r.name = ?
             AND r.is_active = 1",
            [$userId, $routeName]
        );

        $result = $stmt->fetch();
        return ($result->cnt ?? 0) > 0;
    }

    /**
     * Get user's data scope (which data they can see)
     */
    public static function getUserDataScope($userId)
    {
        try {
            $db = Database::getInstance();

            // Get user's roles and their scope context
            $stmt = $db->query(
                "SELECT DISTINCT ur.role_id, r.name as role_name
                 FROM user_roles ur
                 JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = ?",
                [$userId]
            );

            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $scope = [
                'roles' => $roles,
                'is_system_admin' => false,
                'is_director' => false,
                'is_school_admin' => false,
                'data_level' => 'limited' // default: user sees only their data
            ];

            // Determine scope based on roles
            foreach ($roles as $role) {
                if ($role['role_id'] == 2) {
                    $scope['is_system_admin'] = true;
                    $scope['data_level'] = 'full';
                } elseif ($role['role_id'] == 3) {
                    $scope['is_director'] = true;
                    $scope['data_level'] = 'full';
                } elseif ($role['role_id'] == 4) {
                    $scope['is_school_admin'] = true;
                    $scope['data_level'] = 'full';
                } elseif ($role['role_id'] == 5) { // Headteacher
                    $scope['data_level'] = 'school';
                }
            }

            return $scope;

        } catch (\Exception $e) {
            error_log("Data scope resolution failed: " . $e->getMessage());
            return ['data_level' => 'minimal', 'roles' => []];
        }
    }

    /**
     * Expand aliases (both directions: underscore ↔ dot)
     */
    private static function expandPermissionAliases($codes)
    {
        $expanded = [];

        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                continue;
            }

            $expanded[$code] = true;

            // Add dot notation alias
            if (strpos($code, '_') !== false) {
                $expanded[str_replace('_', '.', $code)] = true;
            }

            // Add underscore notation alias
            if (strpos($code, '.') !== false) {
                $expanded[str_replace('.', '_', $code)] = true;
            }
        }

        return array_keys($expanded);
    }
}
