<?php
/**
 * Dashboard Route Mapper (Database-Driven)
 * 
 * Maps user roles to their corresponding dashboards using database configuration.
 * ALL DATA IS SOURCED FROM THE DATABASE - no hard-coded role mappings.
 * 
 * Database Tables Used:
 * - dashboards: Dashboard definitions
 * - role_dashboards: Role to dashboard mappings
 * - roles: Role definitions
 * 
 * @package App\Config
 * @since 2025-12-28
 */

require_once __DIR__ . '/../database/Database.php';

use App\Database\Database;

class DashboardRouter
{
    /**
     * Map of role names to dashboard file keys
     * Role names should match the database role_name (lowercase, normalized)
     */
    private static $roleToDashboard = [
        // System & Administration
        'system_administrator' => 'system_administrator_dashboard',
        'system administrator' => 'system_administrator_dashboard',
        'admin' => 'system_administrator_dashboard',

        // Leadership
        'director/owner' => 'director_owner_dashboard',
        'director_owner' => 'director_owner_dashboard',
        'director' => 'director_owner_dashboard',
        'headteacher' => 'headteacher_dashboard',
        'head_teacher' => 'headteacher_dashboard',
        'deputy_headteacher' => 'deputy_headteacher_dashboard',
        'deputy headteacher' => 'deputy_headteacher_dashboard',

        // Administrative Staff
        'school_administrative_officer' => 'school_administrative_officer_dashboard',
        'school administrative officer' => 'school_administrative_officer_dashboard',
        'registrar' => 'registrar_dashboard',
        'secretary' => 'secretary_dashboard',

        // Teaching Staff
        'class_teacher' => 'class_teacher_dashboard',
        'class teacher' => 'class_teacher_dashboard',
        'subject_teacher' => 'subject_teacher_dashboard',
        'subject teacher' => 'subject_teacher_dashboard',
        'teacher' => 'teacher_dashboard',
        'intern/student_teacher' => 'intern_student_teacher_dashboard',
        'intern_student_teacher' => 'intern_student_teacher_dashboard',

        // Finance
        'school_accountant' => 'school_accountant_dashboard',
        'school accountant' => 'school_accountant_dashboard',
        'accountant' => 'school_accountant_dashboard',
        'accounts_assistant' => 'accounts_assistant_dashboard',
        'accounts assistant' => 'accounts_assistant_dashboard',
        'accounts' => 'school_accountant_dashboard',

        // Operations - Stores
        'store_manager' => 'store_manager_dashboard',
        'store manager' => 'store_manager_dashboard',
        'store_attendant' => 'store_attendant_dashboard',
        'store attendant' => 'store_attendant_dashboard',

        // Operations - Catering
        'catering_manager/cook_lead' => 'catering_manager_cook_lead_dashboard',
        'catering_manager_cook_lead' => 'catering_manager_cook_lead_dashboard',
        'cook/food_handler' => 'cook_food_handler_dashboard',
        'cook_food_handler' => 'cook_food_handler_dashboard',
        'matron/housemother' => 'matron_housemother_dashboard',
        'matron_housemother' => 'matron_housemother_dashboard',

        // Heads of Department
        'hod_food_&_nutrition' => 'hod_food_nutrition_dashboard',
        'hod_food_nutrition' => 'hod_food_nutrition_dashboard',
        'hod_games_&_sports' => 'hod_games_sports_dashboard',
        'hod_games_sports' => 'hod_games_sports_dashboard',
        'hod_talent_development' => 'hod_talent_development_dashboard',
        'hod transport' => 'hod_transport_dashboard',
        'hod_transport' => 'hod_transport_dashboard',

        // Support Services
        'driver' => 'driver_dashboard',
        'school_counselor/chaplain' => 'school_counselor_chaplain_dashboard',
        'school_counselor_chaplain' => 'school_counselor_chaplain_dashboard',
        'security_officer' => 'security_officer_dashboard',
        'security officer' => 'security_officer_dashboard',
        'cleaner/janitor' => 'cleaner_janitor_dashboard',
        'cleaner_janitor' => 'cleaner_janitor_dashboard',
        'librarian' => 'librarian_dashboard',
        'activities_coordinator' => 'activities_coordinator_dashboard',
        'activities coordinator' => 'activities_coordinator_dashboard',

        // External
        'parent/guardian' => 'parent_guardian_dashboard',
        'parent_guardian' => 'parent_guardian_dashboard',
        'parent' => 'parent_guardian_dashboard',
        'visiting_staff' => 'visiting_staff_dashboard',
        'visiting staff' => 'visiting_staff_dashboard',
    ];

    /**
     * Get dashboard route for a given role (by ID or name)
     * 
     * @param string|int|array $role Role ID, name, or array of roles
     * @return string Dashboard route name
     */
    public static function getDashboardForRole($role): string
    {
        // Handle array of roles (use first role)
        if (is_array($role)) {
            if (empty($role)) {
                return self::getDefaultDashboard();
            }
            // If role is array of objects with 'id' or 'name' key
            if (isset($role[0]['id'])) {
                $role = $role[0]['id'];
            } elseif (isset($role[0]['name'])) {
                $role = $role[0]['name'];
            } else {
                $role = $role[0];
            }
        }

        // Try to get from database first
        try {
            $dashboardRoute = null;

            if (is_numeric($role)) {
                // Role ID provided
                $dashboardRoute = self::getDashboardRouteForRoleId((int) $role);
            } else {
                // Role name provided - look up role ID
                $roleId = self::getRoleIdByName($role);
                if ($roleId) {
                    $dashboardRoute = self::getDashboardRouteForRoleId($roleId);
                }
            }

            if ($dashboardRoute) {
                return $dashboardRoute;
            }
        } catch (\Exception $e) {
            error_log("DashboardRouter::getDashboardForRole() error: " . $e->getMessage());
        }

        // Fallback: try to construct dashboard name from role
        if (is_string($role)) {
            $constructed = self::constructDashboardName($role);
            if (self::dashboardExists($constructed)) {
                return $constructed;
            }
        }

        // Ultimate fallback
        return self::getDefaultDashboard();
    }

    /**
     * Get dashboard route for a role ID from database (cached)
     */
    private static function getDashboardRouteForRoleId(int $roleId): ?string
    {
        if (isset(self::$roleDashboardCache[$roleId])) {
            return self::$roleDashboardCache[$roleId];
        }

        try {
            $stmt = self::getDb()->prepare(
                "SELECT d.name
                 FROM role_dashboards rd
                 JOIN dashboards d ON d.id = rd.dashboard_id
                 WHERE rd.role_id = ? AND rd.is_primary = 1 AND d.is_active = 1
                 ORDER BY rd.id ASC
                 LIMIT 1"
            );
            $stmt->execute([$roleId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $route = $result['name'] ?? null;
            self::$roleDashboardCache[$roleId] = $route;
            return $route;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get role ID by name
     */
    private static function getRoleIdByName(string $roleName): ?int
    {
        try {
            // Normalize role name
            $normalized = strtolower(trim(str_replace(['-', ' '], '_', $roleName)));

            $stmt = self::getDb()->prepare(
                "SELECT id FROM roles WHERE LOWER(REPLACE(REPLACE(name, ' ', '_'), '-', '_')) = ? LIMIT 1"
            );
            $stmt->execute([$normalized]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result ? (int) $result['id'] : null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Construct dashboard name from role name
     */
    private static function constructDashboardName(string $roleName): string
    {
        return strtolower(str_replace(['/', ' ', '-'], '_', $roleName)) . '_dashboard';
    }

    /**
     * Get the default dashboard
     * 
     * @return string Default dashboard route name
     */
    public static function getDefaultDashboard(): string
    {
        try {
            // Get the dashboard marked as system default
            $stmt = self::getDb()->query(
                "SELECT name FROM dashboards WHERE is_active = 1 ORDER BY id ASC LIMIT 1"
            );
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result) {
                return $result['name'];
            }
        } catch (\Exception $e) {
            // Fallback
        }

        return '#';
    }

    /**
     * Check if a dashboard file exists
     * 
     * @param string $dashboardKey Dashboard file key (without .php)
     * @return bool
     */
    public static function dashboardExists($dashboardKey): bool
    {
        if (isset(self::$dashboardCache[$dashboardKey])) {
            return self::$dashboardCache[$dashboardKey];
        }

        $path = __DIR__ . '/../components/dashboards/' . $dashboardKey . '.php';
        $exists = file_exists($path);
        self::$dashboardCache[$dashboardKey] = $exists;
        return $exists;
    }

    /**
     * Get dashboard path for rendering
     * 
     * @param string $dashboardKey Dashboard file key (without .php)
     * @return string|null Full path to dashboard file, or null if not found
     */
    public static function getDashboardPath($dashboardKey): ?string
    {
        $path = __DIR__ . '/../components/dashboards/' . $dashboardKey . '.php';
        return file_exists($path) ? $path : null;
    }

    /**
     * Get all available dashboards from database
     * 
     * @return array Array of dashboard info
     */
    public static function getAllDashboards(): array
    {
        try {
            $stmt = self::getDb()->query(
                "SELECT id, name, title, icon, description, domain, is_active
                 FROM dashboards
                 WHERE is_active = 1
                 ORDER BY domain, title"
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            // Fallback: scan filesystem
            return self::getAllDashboardsFromFiles();
        }
    }

    /**
     * Fallback: Get dashboards from filesystem
     */
    private static function getAllDashboardsFromFiles(): array
    {
        $dashboardsDir = __DIR__ . '/../components/dashboards/';
        $files = glob($dashboardsDir . '*_dashboard.php');

        $dashboards = [];
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $dashboards[] = [
                'name' => $key,
                'title' => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
                'is_active' => 1
            ];
        }

        return $dashboards;
    }

    /**
     * Get dashboard URL for a role
     * 
     * @param string|int|array $role
     * @return string URL with route parameter
     */
    public static function getDashboardUrl($role): string
    {
        $dashboard = self::getDashboardForRole($role);
        return '?route=' . $dashboard;
    }

    /**
     * Get all dashboards accessible by a role
     * 
     * @param int $roleId Role ID
     * @return array Array of dashboard info
     */
    public static function getDashboardsForRole(int $roleId): array
    {
        try {
            $stmt = self::getDb()->prepare(
                "SELECT d.id, d.name, d.title, d.icon, d.description, d.domain, rd.is_default
                 FROM role_dashboards rd
                 JOIN dashboards d ON d.id = rd.dashboard_id
                 WHERE rd.role_id = ? AND d.is_active = 1
                 ORDER BY rd.is_default DESC, d.title"
            );
            $stmt->execute([$roleId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Clear the cache
     */
    public static function clearCache(): void
    {
        self::$roleDashboardCache = [];
        self::$dashboardCache = [];
    }

    /**
     * Get user's default dashboard route
     * NOTE: In JWT architecture, this should use role from token
     * 
     * @deprecated Use getDashboardForRole() with role from JWT token instead
     * @return string Dashboard key
     */
    public static function getUserDefaultDashboard(): string
    {
        return self::getDefaultDashboard();
    }

    /**
     * Redirect to default dashboard
     * 
     * @param bool $exit Whether to exit after redirect
     */
    public static function redirectToDefaultDashboard(bool $exit = true): void
    {
        $dashboard = self::getDefaultDashboard();
        $url = '?route=' . $dashboard;

        header('Location: ' . $url);

        if ($exit) {
            exit;
        }
    }
}