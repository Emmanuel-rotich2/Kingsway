<?php
/**
 * Dashboard component registry and safe fallback mapper.
 *
 * Runtime role assignments are read from `role_dashboards` + `dashboards` by
 * the authentication layer. This registry mirrors the approved production
 * mapping, validates component/controller availability, and provides a safe
 * access-denied fallback when database configuration is missing or invalid.
 */
namespace App\Config;

class DashboardRouter
{
    // role_id => dashboard file key (without .php)
    private const ROLE_DASHBOARDS = [
        2  => 'system_administrator_dashboard',
        3  => 'director_owner_dashboard',
        4  => 'school_administrative_officer_dashboard',
        5  => 'headteacher_dashboard',
        6  => 'deputy_head_academic_dashboard',
        7  => 'class_teacher_dashboard',
        8  => 'subject_teacher_dashboard',
        9  => 'intern_student_teacher_dashboard',
        10 => 'school_accountant_dashboard',
        14 => 'store_manager_dashboard',
        16 => 'catering_manager_cook_lead_dashboard',
        18 => 'matron_housemother_dashboard',
        21 => 'hod_talent_development_dashboard',
        23 => 'driver_dashboard',
        24 => 'school_counselor_chaplain_dashboard',
        32 => 'support_staff_dashboard',
        33 => 'support_staff_dashboard',
        34 => 'support_staff_dashboard',
        63 => 'deputy_head_discipline_dashboard',
        64 => 'support_staff_dashboard',
    ];

    private const DEFAULT_DASHBOARD = 'dashboard_access_denied';

    private const DASHBOARD_ALIASES = [
        'dashboard' => self::DEFAULT_DASHBOARD,
        'home' => self::DEFAULT_DASHBOARD,
        'director_dashboard' => 'director_owner_dashboard',
        'school_admin_dashboard' => 'school_administrative_officer_dashboard',
    ];

    // role name → role_id for string-based lookups
    private const ROLE_NAME_MAP = [
        'system administrator'    => 2,
        'director'                => 3,
        'school administrator'    => 4,
        'headteacher'             => 5,
        'deputy head - academic'  => 6,
        'deputy head academic'    => 6,
        'class teacher'           => 7,
        'subject teacher'         => 8,
        'intern/student teacher'  => 9,
        'intern student teacher'  => 9,
        'accountant'              => 10,
        'school accountant'       => 10,
        'inventory manager'       => 14,
        'store manager'           => 14,
        'cateress'                => 16,
        'catering manager'        => 16,
        'boarding master'         => 18,
        'matron'                  => 18,
        'housemother'             => 18,
        'talent development'      => 21,
        'hod talent development'  => 21,
        'driver'                  => 23,
        'chaplain'                => 24,
        'counselor'               => 24,
        'school counselor'        => 24,
        'kitchen staff'           => 32,
        'security staff'          => 33,
        'janitor'                 => 34,
        'deputy head - discipline'=> 63,
        'deputy head discipline'  => 63,
        'staff'                   => 64,
    ];

    /**
     * Get dashboard route for a role (by ID, name, or array of roles).
     */
    public static function getDashboardForRole($role): string
    {
        if (is_array($role)) {
            if (empty($role)) return self::DEFAULT_DASHBOARD;
            $role = $role[0]['id'] ?? $role[0]['name'] ?? $role[0];
        }

        if (is_numeric($role)) {
            return self::ROLE_DASHBOARDS[(int)$role] ?? self::DEFAULT_DASHBOARD;
        }

        // String name lookup — normalise and match
        $key = strtolower(trim((string)$role));
        if (isset(self::ROLE_NAME_MAP[$key])) {
            $id = self::ROLE_NAME_MAP[$key];
            return self::ROLE_DASHBOARDS[$id] ?? self::DEFAULT_DASHBOARD;
        }

        // Unknown or newly-created roles must be explicitly assigned in the
        // database and mirrored in this fallback registry. Never infer a
        // privileged dashboard from an arbitrary role name.
        return self::DEFAULT_DASHBOARD;
    }

    public static function normalizeDashboardKey(string $key): string
    {
        $key = trim($key);
        return self::DASHBOARD_ALIASES[$key] ?? $key;
    }

    public static function getDefaultDashboard(): string
    {
        return self::DEFAULT_DASHBOARD;
    }

    public static function getRoleDashboards(): array
    {
        return self::ROLE_DASHBOARDS;
    }

    public static function getRoleNameMap(): array
    {
        return self::ROLE_NAME_MAP;
    }

    public static function dashboardExists(string $key): bool
    {
        $key = self::normalizeDashboardKey($key);
        return file_exists(__DIR__ . '/../components/dashboards/' . $key . '.php');
    }

    public static function getDashboardPath(string $key): ?string
    {
        $key = self::normalizeDashboardKey($key);
        $path = __DIR__ . '/../components/dashboards/' . $key . '.php';
        return file_exists($path) ? $path : null;
    }

    public static function getDashboardJsPath(string $key): ?string
    {
        $key = self::normalizeDashboardKey($key);
        $path = __DIR__ . '/../js/dashboards/' . $key . '.js';
        return file_exists($path) ? $path : null;
    }

    public static function getDashboardRegistry(): array
    {
        $registry = [];
        foreach (array_unique(self::ROLE_DASHBOARDS) as $key) {
            $registry[$key] = [
                'key' => $key,
                'php' => self::dashboardExists($key),
                'js' => self::getDashboardJsPath($key) !== null,
            ];
        }

        if (!isset($registry[self::DEFAULT_DASHBOARD])) {
            $registry[self::DEFAULT_DASHBOARD] = [
                'key' => self::DEFAULT_DASHBOARD,
                'php' => self::dashboardExists(self::DEFAULT_DASHBOARD),
                'js' => false,
            ];
        }

        foreach (self::DASHBOARD_ALIASES as $alias => $target) {
            $registry[$alias] = [
                'key' => $alias,
                'alias_for' => $target,
                'php' => self::dashboardExists($target),
                'js' => self::getDashboardJsPath($target) !== null,
            ];
        }

        ksort($registry);
        return array_values($registry);
    }

    public static function getDashboardUrl($role): string
    {
        return '?route=' . self::getDashboardForRole($role);
    }

    /** Returns the approved fallback role-to-dashboard pairs. */
    public static function getAllDashboards(): array
    {
        $result = [];
        foreach (self::ROLE_DASHBOARDS as $roleId => $key) {
            if (isset($result[$key])) continue; // dedupe support_staff_dashboard etc.
            $result[$key] = [
                'name'      => $key,
                'title'     => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
                'is_active' => 1,
            ];
        }
        return array_values($result);
    }

    /** Returns fallback dashboard information for one role. */
    public static function getDashboardsForRole(int $roleId): array
    {
        $key = self::ROLE_DASHBOARDS[$roleId] ?? null;
        if (!$key) return [];
        return [[
            'name'       => $key,
            'title'      => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $key))),
            'is_primary' => 1,
            'is_active'  => 1,
        ]];
    }

    /** @deprecated Use getDashboardForRole() */
    public static function getUserDefaultDashboard(): string
    {
        return self::DEFAULT_DASHBOARD;
    }

    public static function redirectToDefaultDashboard(bool $exit = true): void
    {
        header('Location: ?route=' . self::DEFAULT_DASHBOARD);
        if ($exit) exit;
    }

    // No-op: this fallback registry has no runtime cache.
    public static function clearCache(): void {}
}
