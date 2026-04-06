<?php

namespace App\API\Middleware;

use App\Database\Database;
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
            $codes = self::resolvePermissionsFromProcedure($db, (int) $userId);
            if ($codes === null) {
                $codes = self::resolvePermissionsFromTables($db, (int) $userId);
            }

            return self::expandPermissionAliases($codes);

        } catch (\Exception $e) {
            error_log("RBAC permission resolution failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Preferred resolution path: use the procedure defined in the seed SQL.
     */
    private static function resolvePermissionsFromProcedure(Database $db, int $userId): ?array
    {
        try {
            $stmt = $db->query('CALL sp_user_get_effective_permissions(?)', [$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            while ($stmt->nextRowset()) {
                // Drain remaining result sets so the connection stays usable.
            }

            return array_values(array_unique(array_filter(array_map(
                static fn(array $row): ?string => $row['permission_code'] ?? null,
                $rows
            ))));
        } catch (\Exception $e) {
            error_log("RBAC procedure fallback triggered: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Fallback for environments where the stored procedure has not been loaded.
     */
    private static function resolvePermissionsFromTables(Database $db, int $userId): array
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
     * Some controllers still use dotted permission names while the schema stores
     * underscore codes. Keep both for backward compatibility during cleanup.
     */
    private static function expandPermissionAliases(array $codes): array
    {
        $expanded = [];

        foreach ($codes as $code) {
            if (!is_string($code) || $code === '') {
                continue;
            }

            $expanded[$code] = true;

            if (strpos($code, '_') !== false) {
                $expanded[str_replace('_', '.', $code)] = true;
            }
        }

        return array_keys($expanded);
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
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $payload = json_encode([
            'status'  => 'error',
            'message' => $message,
            'code'    => $code,
        ]);
        echo $payload !== false
            ? $payload
            : '{"status":"error","message":"Internal error","code":500}';
        exit;
    }
}
