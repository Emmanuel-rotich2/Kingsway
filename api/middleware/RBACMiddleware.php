<?php

namespace App\API\Middleware;

use App\Config\Database;
use PDO;

class RBACMiddleware
{
    /**
     * Query and resolve effective permissions for authenticated user
     * Store in $_SERVER['auth_user']['effective_permissions']
     */
    public static function handle()
    {
        // Only resolve permissions if user is authenticated
        if (!isset($_SERVER['auth_user'])) {
            return;
        }

        $userId = $_SERVER['auth_user']['user_id'] ?? $_SERVER['auth_user']['sub'] ?? null;
        if (!$userId) {
            return;
        }

        // Get effective permissions from database
        $permissions = self::resolvePermissions($userId);

        // Attach to auth_user
        $_SERVER['auth_user']['effective_permissions'] = $permissions;
    }

    /**
     * Resolve effective permissions: role permissions + user permissions - denied permissions
     * Uses allow > deny logic
     */
    private static function resolvePermissions($userId)
    {
        try {
            $db = Database::getInstance();

            // Query all permissions for user: from roles and direct assignments
            $stmt = $db->query(
                "SELECT DISTINCT p.id, p.name, 
                        COALESCE(up.type, rp.type) as permission_type
                 FROM permissions p
                 LEFT JOIN role_permissions rp ON rp.permission_id = p.id
                 LEFT JOIN user_roles ur ON ur.role_id = rp.role_id
                 LEFT JOIN user_permissions up ON up.permission_id = p.id
                 WHERE ur.user_id = ? OR up.user_id = ?
                 ORDER BY p.name, permission_type DESC",
                [$userId, $userId]
            );

            $permissionMap = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $permName = $row['name'];
                $permType = $row['permission_type'] ?? 'allow'; // default to allow

                // Allow > Deny: if permission is allowed explicitly, keep it even if there's a deny
                if ($permType === 'allow' || !isset($permissionMap[$permName])) {
                    $permissionMap[$permName] = $permType;
                }
            }

            // Extract only allowed permissions
            return array_keys(array_filter($permissionMap, function ($type) {
                return $type === 'allow';
            }));

        } catch (\Exception $e) {
            error_log("RBAC permission resolution failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if user has required permission
     * Returns true if user has permission, false otherwise
     */
    public static function hasPermission($userId, $permissionName)
    {
        // Check if auth_user permissions are cached
        if (isset($_SERVER['auth_user']['effective_permissions'])) {
            return in_array($permissionName, $_SERVER['auth_user']['effective_permissions']);
        }

        // Fallback: query database
        $permissions = self::resolvePermissions($userId);
        return in_array($permissionName, $permissions);
    }

    /**
     * Authorize user for a required permission
     * Throws exception if user lacks permission
     */
    public static function authorize($requiredPermissions)
    {
        if (!is_array($requiredPermissions)) {
            $requiredPermissions = [$requiredPermissions];
        }

        $effectivePermissions = $_SERVER['auth_user']['effective_permissions'] ?? [];

        // Check if user has at least one required permission
        $hasPermission = false;
        foreach ($requiredPermissions as $perm) {
            if (in_array($perm, $effectivePermissions)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            self::deny(
                403,
                'User lacks required permission: ' . implode(', ', $requiredPermissions)
            );
        }
    }

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ]);
        exit;
    }
}
