<?php
/**
 * Route Authorization Middleware (Database-Driven)
 * 
 * Enforces role-based access control (RBAC) with a DENY-BY-DEFAULT pattern.
 * All authorization data is sourced from the database.
 * Only routes explicitly whitelisted for a role are permitted.
 * All other routes return 403 Forbidden.
 * 
 * CRITICAL SECURITY PRINCIPLES:
 * 1. System Admin (Role 2) = Infrastructure only, NO school operations
 * 2. School operations roles (Finance, Academic, etc.) = NO system administration
 * 3. Explicit whitelist per role - implicit deny all others
 * 4. Separation of concerns: SYSTEM_DOMAIN vs SCHOOL_DOMAIN
 * 5. DATABASE IS THE SINGLE SOURCE OF TRUTH
 * 
 * @package App\API\Middleware
 * @since 2025-12-28
 */

namespace App\API\Middleware;

require_once dirname(__DIR__, 2) . '/database/Database.php';

use App\Database\Database;

class RouteAuthorization
{
    private static ?\PDO $db = null;
    private static array $routeCache = [];
    private static array $roleRoutesCache = [];
    private static array $systemRoutesCache = [];
    private static array $schoolRoutesCache = [];

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
     * Get all routes from database (cached)
     */
    private static function getAllRoutes(): array
    {
        if (empty(self::$routeCache)) {
            $stmt = self::getDb()->query(
                "SELECT id, name, url, domain, is_active FROM routes WHERE is_active = 1"
            );
            self::$routeCache = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return self::$routeCache;
    }

    /**
     * Get routes allowed for a specific role from database (cached)
     */
    private static function getRoleRoutesFromDb(int $roleId): array
    {
        if (!isset(self::$roleRoutesCache[$roleId])) {
            $stmt = self::getDb()->prepare(
                "SELECT r.name 
                 FROM role_routes rr
                 JOIN routes r ON r.id = rr.route_id
                 WHERE rr.role_id = ? AND rr.is_allowed = 1 AND r.is_active = 1"
            );
            $stmt->execute([$roleId]);
            self::$roleRoutesCache[$roleId] = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        return self::$roleRoutesCache[$roleId];
    }

    /**
     * Get SYSTEM domain routes (cached)
     */
    private static function getSystemRoutes(): array
    {
        if (empty(self::$systemRoutesCache)) {
            $stmt = self::getDb()->query(
                "SELECT name FROM routes WHERE domain = 'SYSTEM' AND is_active = 1"
            );
            self::$systemRoutesCache = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        return self::$systemRoutesCache;
    }

    /**
     * Get SCHOOL domain routes (cached)
     */
    private static function getSchoolRoutes(): array
    {
        if (empty(self::$schoolRoutesCache)) {
            $stmt = self::getDb()->query(
                "SELECT name FROM routes WHERE domain = 'SCHOOL' AND is_active = 1"
            );
            self::$schoolRoutesCache = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }
        return self::$schoolRoutesCache;
    }

    /**
     * Clear all caches (call after database updates)
     */
    public static function clearCache(): void
    {
        self::$routeCache = [];
        self::$roleRoutesCache = [];
        self::$systemRoutesCache = [];
        self::$schoolRoutesCache = [];
    }

    /**
     * Check if a user with given role is authorized for a route
     * 
     * @param int|null $role_id The user's role ID
     * @param string $route The route to access
     * @return bool True if authorized, false otherwise
     */
    public static function isAuthorized($role_id, $route): bool
    {
        // Invalid role ID
        if ($role_id === null) {
            return false;
        }

        // Get allowed routes for this role from database
        $allowedRoutes = self::getRoleRoutesFromDb((int) $role_id);

        // Check if route is in the allowed list for this role
        return in_array($route, $allowedRoutes, true);
    }

    /**
     * Enforce authorization and return detailed response
     * 
     * @param int|null $role_id The user's role ID
     * @param string $route The route to access
     * @return array ['success' => bool, 'message' => string, 'http_code' => int]
     */
    public static function enforceAuthorization($role_id, $route): array
    {
        // No role provided - not authenticated
        if ($role_id === null) {
            return [
                'success' => false,
                'message' => 'Authentication required. Please log in.',
                'http_code' => 401
            ];
        }

        // Get allowed routes from database
        $allowedRoutes = self::getRoleRoutesFromDb((int) $role_id);

        // Role has no routes assigned
        if (empty($allowedRoutes)) {
            return [
                'success' => false,
                'message' => 'No routes configured for this role. Contact administrator.',
                'http_code' => 403
            ];
        }

        // Check authorization
        if (!self::isAuthorized($role_id, $route)) {
            $roleName = self::getRoleNameById($role_id);

            return [
                'success' => false,
                'message' => "Route '{$route}' is not available for role '{$roleName}'.",
                'http_code' => 403
            ];
        }

        // Authorized
        return [
            'success' => true,
            'message' => 'Authorized',
            'http_code' => 200
        ];
    }

    /**
     * Get all routes allowed for a specific role
     * 
     * @param int $role_id The user's role ID
     * @return array Routes allowed for this role
     */
    public static function getAllowedRoutesForRole($role_id): array
    {
        return self::getRoleRoutesFromDb((int) $role_id);
    }

    /**
     * Check if a route is system infrastructure (System Admin only)
     * 
     * @param string $route The route to check
     * @return bool True if system infrastructure route
     */
    public static function isSystemRoute($route): bool
    {
        return in_array($route, self::getSystemRoutes(), true);
    }

    /**
     * Check if a route is school operations
     * 
     * @param string $route The route to check
     * @return bool True if school operations route
     */
    public static function isSchoolOperationsRoute($route): bool
    {
        return in_array($route, self::getSchoolRoutes(), true);
    }

    /**
     * Get role name by ID from database
     * 
     * @param int $role_id The role ID
     * @return string Role name
     */
    private static function getRoleNameById($role_id): string
    {
        static $roleNames = [];

        if (!isset($roleNames[$role_id])) {
            $stmt = self::getDb()->prepare("SELECT name FROM roles WHERE id = ?");
            $stmt->execute([$role_id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $roleNames[$role_id] = $result['name'] ?? 'Unknown Role';
        }

        return $roleNames[$role_id];
    }

    /**
     * Check if user has access to any route in a domain
     * 
     * @param int $roleId Role ID
     * @param string $domain 'SYSTEM' or 'SCHOOL'
     * @return bool
     */
    public static function hasAccessToDomain(int $roleId, string $domain): bool
    {
        $stmt = self::getDb()->prepare(
            "SELECT COUNT(*) FROM role_routes rr
             JOIN routes r ON r.id = rr.route_id
             WHERE rr.role_id = ? AND r.domain = ? AND rr.is_allowed = 1"
        );
        $stmt->execute([$roleId, $domain]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get route info by name
     * 
     * @param string $routeName Route name
     * @return array|null Route info or null
     */
    public static function getRouteByName(string $routeName): ?array
    {
        $stmt = self::getDb()->prepare(
            "SELECT id, name, url, domain, description, is_active 
             FROM routes WHERE name = ? AND is_active = 1"
        );
        $stmt->execute([$routeName]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Log authorization attempt
     * 
     * @param int $user_id User ID
     * @param int $role_id Role ID
     * @param string $route Route attempted
     * @param bool $authorized Whether authorization succeeded
     */
    public static function logAuthorizationAttempt($user_id, $role_id, $route, $authorized): void
    {
        try {
            $stmt = self::getDb()->prepare(
                "INSERT INTO audit_logs (user_id, action, details, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $user_id,
                $authorized ? 'route_authorized' : 'route_unauthorized',
                json_encode(['route' => $route, 'role_id' => $role_id]),
                $authorized ? 'success' : 'failure'
            ]);
        } catch (\Exception $e) {
            error_log("Authorization log failed - User: {$user_id}, Role: {$role_id}, Route: {$route}, Result: " . ($authorized ? 'ALLOWED' : 'DENIED'));
        }
    }
}
