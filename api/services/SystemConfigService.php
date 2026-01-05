<?php
/**
 * System Configuration Service
 * 
 * Central service for loading and managing database-driven system configuration.
 * Provides runtime access to routes, menus, dashboards, widgets, and policies.
 * 
 * @package App\API\Services
 * @since 2025-12-28
 */

namespace App\API\Services;

use App\Database\Database;
use Exception;

class SystemConfigService
{
    private static ?SystemConfigService $instance = null;
    private Database $db;

    // Cache for frequently accessed data
    private array $routeCache = [];
    private array $menuCache = [];
    private array $dashboardCache = [];
    private array $policyCache = [];
    private bool $cacheLoaded = false;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): SystemConfigService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // =========================================================================
    // ROUTES
    // =========================================================================

    /**
     * Get all active routes
     */
    public function getAllRoutes(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM routes";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY domain, name";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get routes by domain
     */
    public function getRoutesByDomain(string $domain): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM routes WHERE domain = ? AND is_active = 1 ORDER BY name",
            [$domain]
        );
        return $stmt->fetchAll();
    }

    /**
     * Get a single route by name
     */
    public function getRouteByName(string $name): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM routes WHERE name = ? LIMIT 1",
            [$name]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Get route by ID
     */
    public function getRouteById(int $id): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM routes WHERE id = ? LIMIT 1",
            [$id]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a new route
     */
    public function createRoute(array $data): int
    {
        $stmt = $this->db->query(
            "INSERT INTO routes (name, url, domain, description, controller, action, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['url'],
                $data['domain'] ?? 'SCHOOL',
                $data['description'] ?? null,
                $data['controller'] ?? null,
                $data['action'] ?? null,
                $data['is_active'] ?? 1
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update an existing route
     */
    public function updateRoute(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['name', 'url', 'domain', 'description', 'controller', 'action', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $this->db->query(
            "UPDATE routes SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
        return true;
    }

    /**
     * Delete a route
     */
    public function deleteRoute(int $id): bool
    {
        $this->db->query("DELETE FROM routes WHERE id = ?", [$id]);
        return true;
    }

    // =========================================================================
    // ROUTE PERMISSIONS
    // =========================================================================

    /**
     * Get permissions required for a route
     */
    public function getRoutePermissions(int $routeId): array
    {
        $stmt = $this->db->query(
            "SELECT rp.*, p.name as permission_name, p.description as permission_description
             FROM route_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.route_id = ?",
            [$routeId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Get permissions required for a route by route name
     */
    public function getPermissionsForRouteName(string $routeName): array
    {
        $stmt = $this->db->query(
            "SELECT p.name, p.id, rp.access_type, rp.is_required
             FROM routes r
             JOIN route_permissions rp ON rp.route_id = r.id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = ? AND r.is_active = 1",
            [$routeName]
        );
        return $stmt->fetchAll();
    }

    /**
     * Assign permission to route
     */
    public function assignPermissionToRoute(int $routeId, int $permissionId, string $accessType = 'view', bool $isRequired = true): int
    {
        $stmt = $this->db->query(
            "INSERT INTO route_permissions (route_id, permission_id, access_type, is_required)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE access_type = VALUES(access_type), is_required = VALUES(is_required)",
            [$routeId, $permissionId, $accessType, $isRequired ? 1 : 0]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Remove permission from route
     */
    public function removePermissionFromRoute(int $routeId, int $permissionId): bool
    {
        $this->db->query(
            "DELETE FROM route_permissions WHERE route_id = ? AND permission_id = ?",
            [$routeId, $permissionId]
        );
        return true;
    }

    // =========================================================================
    // ROLE ROUTES (Authorization)
    // =========================================================================

    /**
     * Get all routes allowed for a role
     */
    public function getRoutesForRole(int $roleId): array
    {
        $stmt = $this->db->query(
            "SELECT r.* FROM routes r
             JOIN role_routes rr ON rr.route_id = r.id
             WHERE rr.role_id = ? AND rr.is_allowed = 1 AND r.is_active = 1
             ORDER BY r.domain, r.name",
            [$roleId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Check if a role is allowed to access a route
     */
    public function isRoleAllowedRoute(int $roleId, string $routeName): bool
    {
        $stmt = $this->db->query(
            "SELECT rr.is_allowed FROM role_routes rr
             JOIN routes r ON r.id = rr.route_id
             WHERE rr.role_id = ? AND r.name = ? AND r.is_active = 1
             LIMIT 1",
            [$roleId, $routeName]
        );
        $result = $stmt->fetch();

        // Deny by default if no explicit assignment
        return $result ? (bool) $result['is_allowed'] : false;
    }

    /**
     * Assign route to role
     */
    public function assignRouteToRole(int $roleId, int $routeId, bool $isAllowed = true): bool
    {
        $this->db->query(
            "INSERT INTO role_routes (role_id, route_id, is_allowed)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE is_allowed = VALUES(is_allowed)",
            [$roleId, $routeId, $isAllowed ? 1 : 0]
        );
        return true;
    }

    /**
     * Remove route from role
     */
    public function removeRouteFromRole(int $roleId, int $routeId): bool
    {
        $this->db->query(
            "DELETE FROM role_routes WHERE role_id = ? AND route_id = ?",
            [$roleId, $routeId]
        );
        return true;
    }

    /**
     * Bulk assign routes to role
     */
    public function bulkAssignRoutesToRole(int $roleId, array $routeIds): bool
    {
        $this->db->beginTransaction();
        try {
            foreach ($routeIds as $routeId) {
                $this->assignRouteToRole($roleId, $routeId, true);
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // =========================================================================
    // USER ROUTES (Per-user overrides)
    // =========================================================================

    /**
     * Get all route overrides for a user
     */
    public function getUserRouteOverrides(int $userId): array
    {
        $stmt = $this->db->query(
            "SELECT ur.*, r.name as route_name, r.domain
             FROM user_routes ur
             JOIN routes r ON r.id = ur.route_id
             WHERE ur.user_id = ?
             AND (ur.expires_at IS NULL OR ur.expires_at > NOW())",
            [$userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Check if user has override for a route
     */
    public function getUserRouteOverride(int $userId, string $routeName): ?array
    {
        $stmt = $this->db->query(
            "SELECT ur.* FROM user_routes ur
             JOIN routes r ON r.id = ur.route_id
             WHERE ur.user_id = ? AND r.name = ?
             AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
             LIMIT 1",
            [$userId, $routeName]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Grant user access to a route
     */
    public function grantUserRouteAccess(int $userId, int $routeId, ?int $grantedBy = null, ?string $reason = null, ?string $expiresAt = null): bool
    {
        $this->db->query(
            "INSERT INTO user_routes (user_id, route_id, is_allowed, granted_by, reason, expires_at)
             VALUES (?, ?, 1, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_allowed = 1, granted_by = VALUES(granted_by), reason = VALUES(reason), expires_at = VALUES(expires_at)",
            [$userId, $routeId, $grantedBy, $reason, $expiresAt]
        );
        return true;
    }

    /**
     * Revoke user access to a route
     */
    public function revokeUserRouteAccess(int $userId, int $routeId): bool
    {
        $this->db->query(
            "DELETE FROM user_routes WHERE user_id = ? AND route_id = ?",
            [$userId, $routeId]
        );
        return true;
    }

    // =========================================================================
    // EFFECTIVE AUTHORIZATION (combines role + user + policy)
    // =========================================================================

    /**
     * Check if a user is authorized to access a route
     * Considers: role routes + user overrides + policies
     */
    public function isUserAuthorizedForRoute(int $userId, int $roleId, string $routeName): array
    {
        $result = [
            'authorized' => false,
            'reason' => 'denied',
            'source' => 'default'
        ];

        // 1. Check user-level override first (highest priority)
        $userOverride = $this->getUserRouteOverride($userId, $routeName);
        if ($userOverride !== null) {
            $result['authorized'] = (bool) $userOverride['is_allowed'];
            $result['source'] = 'user_override';
            $result['reason'] = $userOverride['is_allowed'] ? 'granted_by_override' : 'denied_by_override';

            if (!$userOverride['is_allowed']) {
                return $result; // Explicit deny overrides everything
            }
        }

        // 2. Check applicable policies
        $route = $this->getRouteByName($routeName);
        if (!$route) {
            $result['reason'] = 'route_not_found';
            return $result;
        }

        $policyResult = $this->evaluatePolicies($userId, $roleId, $route);
        if ($policyResult['denied']) {
            $result['authorized'] = false;
            $result['source'] = 'policy';
            $result['reason'] = 'denied_by_policy';
            $result['policy'] = $policyResult['policy_name'] ?? null;
            return $result;
        }

        // 3. Check role-level authorization (deny-by-default)
        if ($this->isRoleAllowedRoute($roleId, $routeName)) {
            $result['authorized'] = true;
            $result['source'] = 'role';
            $result['reason'] = 'allowed_by_role';
            return $result;
        }

        // 4. If user had an override granting access, honor it
        if ($userOverride !== null && $userOverride['is_allowed']) {
            $result['authorized'] = true;
            return $result;
        }

        // Deny by default
        $result['reason'] = 'not_in_role_whitelist';
        return $result;
    }

    // =========================================================================
    // POLICIES
    // =========================================================================

    /**
     * Get all active policies
     */
    public function getActivePolicies(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM system_policies 
             WHERE is_active = 1
             AND (effective_from IS NULL OR effective_from <= NOW())
             AND (effective_until IS NULL OR effective_until > NOW())
             ORDER BY priority DESC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Evaluate policies against a route access attempt
     */
    public function evaluatePolicies(int $userId, int $roleId, array $route): array
    {
        $policies = $this->getActivePolicies();

        foreach ($policies as $policy) {
            $ruleExpression = json_decode($policy['rule_expression'], true);
            if (!$ruleExpression)
                continue;

            $matches = $this->evaluatePolicyRule($ruleExpression, [
                'user_id' => $userId,
                'role_id' => $roleId,
                'route' => $route
            ]);

            if ($matches && $policy['rule_type'] === 'deny') {
                return [
                    'denied' => true,
                    'policy_name' => $policy['name'],
                    'policy_id' => $policy['id']
                ];
            }
        }

        return ['denied' => false];
    }

    /**
     * Evaluate a single policy rule
     */
    private function evaluatePolicyRule(array $rule, array $context): bool
    {
        if (!isset($rule['condition']) || !isset($rule['rules'])) {
            return false;
        }

        $condition = strtoupper($rule['condition']);
        $results = [];

        foreach ($rule['rules'] as $subRule) {
            $field = $subRule['field'] ?? '';
            $operator = $subRule['operator'] ?? '=';
            $value = $subRule['value'] ?? null;

            // Resolve field value from context
            $actualValue = $this->resolveFieldValue($field, $context);

            // Evaluate condition
            $results[] = $this->evaluateCondition($actualValue, $operator, $value);
        }

        if ($condition === 'AND') {
            return !in_array(false, $results, true);
        } elseif ($condition === 'OR') {
            return in_array(true, $results, true);
        }

        return false;
    }

    /**
     * Resolve a field path to its value
     */
    private function resolveFieldValue(string $field, array $context)
    {
        $parts = explode('.', $field);
        $current = $context;

        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return null;
            }
        }

        return $current;
    }

    /**
     * Evaluate a single condition
     */
    private function evaluateCondition($actual, string $operator, $expected): bool
    {
        switch (strtoupper($operator)) {
            case '=':
            case '==':
                return $actual == $expected;
            case '!=':
            case '<>':
                return $actual != $expected;
            case '>':
                return $actual > $expected;
            case '>=':
                return $actual >= $expected;
            case '<':
                return $actual < $expected;
            case '<=':
                return $actual <= $expected;
            case 'IN':
                return is_array($expected) && in_array($actual, $expected);
            case 'NOT IN':
                return is_array($expected) && !in_array($actual, $expected);
            case 'CONTAINS':
                return is_string($actual) && str_contains($actual, $expected);
            case 'STARTS_WITH':
                return is_string($actual) && str_starts_with($actual, $expected);
            case 'ENDS_WITH':
                return is_string($actual) && str_ends_with($actual, $expected);
            default:
                return false;
        }
    }

    /**
     * Get policy by ID
     */
    public function getPolicyById(int $id): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM system_policies WHERE id = ? LIMIT 1",
            [$id]
        );
        $policy = $stmt->fetch();

        if ($policy) {
            // Decode JSON fields
            if (!empty($policy['rule_expression'])) {
                $policy['rule_expression'] = json_decode($policy['rule_expression'], true);
            }
            if (!empty($policy['target_ids'])) {
                $policy['target_ids'] = json_decode($policy['target_ids'], true);
            }
        }

        return $policy ?: null;
    }

    /**
     * Create a new policy
     */
    public function createPolicy(array $data): int
    {
        $stmt = $this->db->query(
            "INSERT INTO system_policies (name, display_name, rule_type, priority, rule_expression, description, applies_to, target_ids, effective_from, effective_until, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['display_name'],
                $data['rule_type'] ?? 'deny',
                $data['priority'] ?? 0,
                is_array($data['rule_expression']) ? json_encode($data['rule_expression']) : $data['rule_expression'],
                $data['description'] ?? null,
                $data['applies_to'] ?? 'global',
                is_array($data['target_ids'] ?? null) ? json_encode($data['target_ids']) : ($data['target_ids'] ?? null),
                $data['effective_from'] ?? null,
                $data['effective_until'] ?? null,
                $data['is_active'] ?? 1,
                $data['created_by'] ?? null
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a policy
     */
    public function updatePolicy(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['name', 'display_name', 'rule_type', 'priority', 'description', 'applies_to', 'effective_from', 'effective_until', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (array_key_exists('rule_expression', $data)) {
            $fields[] = "rule_expression = ?";
            $values[] = is_array($data['rule_expression']) ? json_encode($data['rule_expression']) : $data['rule_expression'];
        }

        if (array_key_exists('target_ids', $data)) {
            $fields[] = "target_ids = ?";
            $values[] = is_array($data['target_ids']) ? json_encode($data['target_ids']) : $data['target_ids'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $this->db->query(
            "UPDATE system_policies SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
        return true;
    }

    /**
     * Delete a policy
     */
    public function deletePolicy(int $id): bool
    {
        $this->db->query("DELETE FROM system_policies WHERE id = ?", [$id]);
        return true;
    }

    // =========================================================================
    // CONFIG SYNC (DB to File fallback)
    // =========================================================================

    /**
     * Sync all configurations to PHP files for fallback
     */
    public function syncConfigToFiles(?int $syncedBy = null): array
    {
        $results = [];

        try {
            $results['routes'] = $this->syncRoutesToFile($syncedBy);
            $results['menus'] = $this->syncMenusToFile($syncedBy);
            $results['dashboards'] = $this->syncDashboardsToFile($syncedBy);
            $results['policies'] = $this->syncPoliciesToFile($syncedBy);
            $results['success'] = true;
        } catch (Exception $e) {
            $results['success'] = false;
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Sync routes to PHP file
     */
    private function syncRoutesToFile(?int $syncedBy = null): array
    {
        $routes = $this->getAllRoutes(false);
        $filePath = dirname(__DIR__) . '/includes/generated/routes.generated.php';

        $content = "<?php\n/**\n * AUTO-GENERATED ROUTES CONFIGURATION\n * Generated: " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT - Changes will be overwritten\n */\n\nreturn " . var_export($routes, true) . ";\n";

        $this->ensureDirectoryExists(dirname($filePath));
        file_put_contents($filePath, $content);

        $checksum = hash('sha256', $content);
        $this->logConfigSync('routes', $filePath, $checksum, count($routes), 'success', null, $syncedBy);

        return ['file' => $filePath, 'count' => count($routes), 'checksum' => $checksum];
    }

    /**
     * Sync menus to PHP file
     */
    private function syncMenusToFile(?int $syncedBy = null): array
    {
        $menuService = MenuBuilderService::getInstance();
        $roleMenus = $menuService->getAllRoleMenusForExport();

        $filePath = dirname(__DIR__) . '/includes/generated/dashboards.generated.php';

        $content = "<?php\n/**\n * AUTO-GENERATED DASHBOARD/MENU CONFIGURATION\n * Generated: " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT - Changes will be overwritten\n */\n\nreturn " . var_export($roleMenus, true) . ";\n";

        $this->ensureDirectoryExists(dirname($filePath));
        file_put_contents($filePath, $content);

        $checksum = hash('sha256', $content);
        $this->logConfigSync('menus', $filePath, $checksum, count($roleMenus), 'success', null, $syncedBy);

        return ['file' => $filePath, 'count' => count($roleMenus), 'checksum' => $checksum];
    }

    /**
     * Sync dashboards to PHP file
     */
    private function syncDashboardsToFile(?int $syncedBy = null): array
    {
        $dashboards = $this->getAllDashboards();
        $filePath = dirname(__DIR__) . '/includes/generated/dashboard_widgets.generated.php';

        $content = "<?php\n/**\n * AUTO-GENERATED DASHBOARD WIDGETS CONFIGURATION\n * Generated: " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT - Changes will be overwritten\n */\n\nreturn " . var_export($dashboards, true) . ";\n";

        $this->ensureDirectoryExists(dirname($filePath));
        file_put_contents($filePath, $content);

        $checksum = hash('sha256', $content);
        $this->logConfigSync('dashboards', $filePath, $checksum, count($dashboards), 'success', null, $syncedBy);

        return ['file' => $filePath, 'count' => count($dashboards), 'checksum' => $checksum];
    }

    /**
     * Sync policies to PHP file
     */
    private function syncPoliciesToFile(?int $syncedBy = null): array
    {
        $policies = $this->getActivePolicies();
        $filePath = dirname(__DIR__) . '/includes/generated/policies.generated.php';

        $content = "<?php\n/**\n * AUTO-GENERATED POLICY CONFIGURATION\n * Generated: " . date('Y-m-d H:i:s') . "\n * DO NOT EDIT - Changes will be overwritten\n */\n\nreturn " . var_export($policies, true) . ";\n";

        $this->ensureDirectoryExists(dirname($filePath));
        file_put_contents($filePath, $content);

        $checksum = hash('sha256', $content);
        $this->logConfigSync('policies', $filePath, $checksum, count($policies), 'success', null, $syncedBy);

        return ['file' => $filePath, 'count' => count($policies), 'checksum' => $checksum];
    }

    /**
     * Log config sync operation
     */
    private function logConfigSync(string $type, string $filePath, string $checksum, int $count, string $status, ?string $error = null, ?int $syncedBy = null): void
    {
        try {
            $this->db->query(
                "INSERT INTO config_sync_log (config_type, file_path, checksum, records_count, sync_status, error_message, synced_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$type, $filePath, $checksum, $count, $status, $error, $syncedBy]
            );
        } catch (Exception $e) {
            error_log("Failed to log config sync: " . $e->getMessage());
        }
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // =========================================================================
    // DASHBOARDS
    // =========================================================================

    /**
     * Get all dashboards
     */
    public function getAllDashboards(bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM dashboards";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY domain, name";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get dashboards for a role
     */
    public function getDashboardsForRole(int $roleId): array
    {
        $stmt = $this->db->query(
            "SELECT d.*, rd.is_primary, rd.display_order
             FROM dashboards d
             JOIN role_dashboards rd ON rd.dashboard_id = d.id
             WHERE rd.role_id = ? AND d.is_active = 1
             ORDER BY rd.display_order, d.name",
            [$roleId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Get primary dashboard for a role
     */
    public function getPrimaryDashboardForRole(int $roleId): ?array
    {
        $stmt = $this->db->query(
            "SELECT d.* FROM dashboards d
             JOIN role_dashboards rd ON rd.dashboard_id = d.id
             WHERE rd.role_id = ? AND rd.is_primary = 1 AND d.is_active = 1
             LIMIT 1",
            [$roleId]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Create a dashboard
     */
    public function createDashboard(array $data): int
    {
        $stmt = $this->db->query(
            "INSERT INTO dashboards (name, display_name, description, domain, route_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['name'],
                $data['display_name'],
                $data['description'] ?? null,
                $data['domain'] ?? 'SCHOOL',
                $data['route_id'] ?? null,
                $data['is_active'] ?? 1
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a dashboard
     */
    public function updateDashboard(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['name', 'display_name', 'description', 'domain', 'route_id', 'is_active'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $this->db->query(
            "UPDATE dashboards SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
        return true;
    }

    /**
     * Delete a dashboard
     */
    public function deleteDashboard(int $id): bool
    {
        $this->db->query("DELETE FROM dashboards WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Assign dashboard to role
     */
    public function assignDashboardToRole(int $roleId, int $dashboardId, bool $isPrimary = false, int $order = 0): bool
    {
        $this->db->query(
            "INSERT INTO role_dashboards (role_id, dashboard_id, is_primary, display_order)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), display_order = VALUES(display_order)",
            [$roleId, $dashboardId, $isPrimary ? 1 : 0, $order]
        );
        return true;
    }

    /**
     * Get dashboard for role (alias for getPrimaryDashboardForRole)
     * Used by AuthAPI for login response
     */
    public function getDashboardForRole(int $roleId): ?array
    {
        $dashboard = $this->getPrimaryDashboardForRole($roleId);

        if ($dashboard) {
            // Get the route info for the dashboard
            if (!empty($dashboard['route_id'])) {
                $route = $this->getRouteById($dashboard['route_id']);
                if ($route) {
                    $dashboard['route'] = $route['url'] ?? '';
                    $dashboard['route_name'] = $route['name'] ?? '';
                }
            }
        }

        return $dashboard;
    }
}
