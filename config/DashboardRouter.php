<?php
/**
 * Dashboard Route Mapper
 * 
 * Maps user roles (from database) to their corresponding dashboard files.
 * This ensures each user loads their role-specific dashboard by default.
 */

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
     * Get dashboard route for a given role name
     * 
     * @param string|array $role Role name or array of roles
     * @return string Dashboard file key (without .php extension)
     */
    public static function getDashboardForRole($role)
    {
        // Handle array of roles (use first role)
        if (is_array($role)) {
            if (empty($role)) {
                return self::getDefaultDashboard();
            }
            // If role is array of objects with 'name' key
            if (isset($role[0]['name'])) {
                $role = $role[0]['name'];
            } else {
                $role = $role[0];
            }
        }

        // Normalize role name
        $normalized = self::normalizeRoleName($role);

        // Check direct mapping
        if (isset(self::$roleToDashboard[$normalized])) {
            return self::$roleToDashboard[$normalized];
        }

        // Check original (non-normalized) mapping
        if (isset(self::$roleToDashboard[$role])) {
            return self::$roleToDashboard[$role];
        }

        // Fallback: try to construct dashboard name from role
        $constructed = strtolower(str_replace(['/', ' ', '-'], '_', $role)) . '_dashboard';
        $dashboardPath = __DIR__ . '/../components/dashboards/' . $constructed . '.php';

        if (file_exists($dashboardPath)) {
            return $constructed;
        }

        // Ultimate fallback
        return self::getDefaultDashboard();
    }

    /**
     * Get the default dashboard (system administrator)
     * 
     * @return string
     */
    public static function getDefaultDashboard()
    {
        return 'system_administrator_dashboard';
    }

    /**
     * Normalize role name for consistent matching
     * 
     * @param string $roleName
     * @return string
     */
    private static function normalizeRoleName($roleName)
    {
        return strtolower(trim(str_replace(['-', ' '], '_', $roleName)));
    }

    /**
     * Check if a dashboard file exists
     * 
     * @param string $dashboardKey Dashboard file key (without .php)
     * @return bool
     */
    public static function dashboardExists($dashboardKey)
    {
        $path = __DIR__ . '/../components/dashboards/' . $dashboardKey . '.php';
        return file_exists($path);
    }

    /**
     * Get dashboard path for rendering
     * 
     * @param string $dashboardKey
     * @return string|null Full path to dashboard file, or null if not found
     */
    public static function getDashboardPath($dashboardKey)
    {
        $path = __DIR__ . '/../components/dashboards/' . $dashboardKey . '.php';
        return file_exists($path) ? $path : null;
    }

    /**
     * Get all available dashboards
     * 
     * @return array Array of dashboard keys
     */
    public static function getAllDashboards()
    {
        $dashboardsDir = __DIR__ . '/../components/dashboards/';
        $files = glob($dashboardsDir . '*_dashboard.php');

        $dashboards = [];
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $dashboards[] = $key;
        }

        return $dashboards;
    }

    /**
     * Get user's default dashboard route
     * NOTE: This method should NOT be used in stateless JWT architecture
     * Dashboard routing should happen on frontend based on AuthContext
     * 
     * @deprecated Use getDashboardForRole() with role from JWT token instead
     * @return string Dashboard key
     */
    public static function getUserDefaultDashboard()
    {
        // DEPRECATED: This relies on PHP sessions which breaks stateless architecture
        // Frontend should determine dashboard from AuthContext.getDashboardInfo()
        // Kept for backward compatibility only

        // Fallback to default dashboard
        // In JWT architecture, role comes from token, not session
        return self::getDefaultDashboard();
    }

    /**
     * Redirect to user's default dashboard
     * 
     * @param bool $exit Whether to exit after redirect
     */
    public static function redirectToDefaultDashboard($exit = true)
    {
        $dashboard = self::getUserDefaultDashboard();
        $url = '?route=' . $dashboard;

        header('Location: ' . $url);

        if ($exit) {
            exit;
        }
    }

    /**
     * Get dashboard URL for a role
     * 
     * @param string|array $role
     * @return string
     */
    public static function getDashboardUrl($role)
    {
        $dashboard = self::getDashboardForRole($role);
        return '?route=' . $dashboard;
    }
}
