<?php
/**
 * RoutePermissionsStore (Database-Driven)
 *
 * Loads route→permission mappings exclusively from the database.
 * ALL DATA IS SOURCED FROM THE DATABASE - no static file fallback.
 * 
 * Database Tables Used:
 * - route_permissions: Links routes to required permissions
 * - routes: Route definitions
 * - permissions: Permission definitions
 * 
 * @package App\Includes
 * @since 2025-12-28
 */

require_once dirname(__DIR__, 2) . '/database/Database.php';

use App\Database\Database;

class RoutePermissionsStore
{
    private static ?\PDO $db = null;
    private static array $cache = [];

    /**
     * Get database connection
     */
    private static function getDb(): \PDO
    {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * Load all route permissions as [route_name => [perm_code1, perm_code2...]]
     * 
     * @return array Associative array of route names to permission code arrays
     */
    public function loadAll(): array
    {
        if (!empty(self::$cache)) {
            return self::$cache;
        }

        try {
            $stmt = self::getDb()->query(
                "SELECT r.name as route_name, p.code as permission_code
                 FROM route_permissions rp
                 JOIN routes r ON r.id = rp.route_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.is_required = 1
                 ORDER BY r.name, p.code"
            );

            $map = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $routeName = $row['route_name'];
                $permCode = $row['permission_code'];

                if (!isset($map[$routeName])) {
                    $map[$routeName] = [];
                }
                $map[$routeName][] = $permCode;
            }

            self::$cache = $map;
            return $map;

        } catch (\Exception $e) {
            error_log("RoutePermissionsStore::loadAll() error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get permissions required for a specific route
     * 
     * @param string $routeName The route name
     * @return array Array of permission codes required (empty = no restrictions)
     */
    public function getPermissionsForRoute(string $routeName): array
    {
        $all = $this->loadAll();
        return $all[$routeName] ?? [];
    }

    /**
     * Check if a user has access to a route based on their permissions
     * 
     * @param string $routeName The route name
     * @param array $userPermissions Array of permission codes the user has
     * @return bool True if user has at least one required permission (OR logic)
     */
    public function hasAccess(string $routeName, array $userPermissions): bool
    {
        $required = $this->getPermissionsForRoute($routeName);

        // No permissions required = public access
        if (empty($required)) {
            return true;
        }

        // Check if user has ANY of the required permissions (OR logic)
        foreach ($required as $perm) {
            if (in_array($perm, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear the cache (useful after database updates)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * Get all routes that require a specific permission
     * 
     * @param string $permissionCode The permission code to search for
     * @return array Array of route names requiring this permission
     */
    public function getRoutesForPermission(string $permissionCode): array
    {
        try {
            $stmt = self::getDb()->prepare(
                "SELECT r.name as route_name
                 FROM route_permissions rp
                 JOIN routes r ON r.id = rp.route_id
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE p.code = ? AND rp.is_required = 1
                 ORDER BY r.name"
            );
            $stmt->execute([$permissionCode]);

            return $stmt->fetchAll(\PDO::FETCH_COLUMN);

        } catch (\Exception $e) {
            error_log("RoutePermissionsStore::getRoutesForPermission() error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get permission statistics
     * 
     * @return array Stats about route permissions
     */
    public function getStats(): array
    {
        try {
            $stmt = self::getDb()->query(
                "SELECT 
                    COUNT(DISTINCT rp.route_id) as routes_with_permissions,
                    COUNT(DISTINCT rp.permission_id) as unique_permissions,
                    COUNT(*) as total_mappings
                 FROM route_permissions rp
                 WHERE rp.is_required = 1"
            );
            return $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            return [
                'routes_with_permissions' => 0,
                'unique_permissions' => 0,
                'total_mappings' => 0
            ];
        }
    }
}
