<?php
/**
 * Dashboard Manager
 * 
 * Handles dashboard and menu selection based on user roles and permissions.
 * Caches dashboard configuration for fast loading.
 */

class DashboardManager
{
    private $dashboardConfig = [];
    private $currentUser = null;
    private $permissionCache = [];

    public function __construct()
    {
        // Load dashboard configuration
        $this->dashboardConfig = include __DIR__ . '/dashboards.php';
    }

    /**
     * Set current user context from session or API response
     * 
     * @param array $userData User data array with 'roles' and 'permissions'
     */
    public function setUser($userData)
    {
        $this->currentUser = $userData;

        // Build permission cache for fast lookups
        if (isset($userData['permissions']) && is_array($userData['permissions'])) {
            $this->permissionCache = [];
            foreach ($userData['permissions'] as $perm) {
                $code = isset($perm['permission_code']) ? $perm['permission_code'] : null;
                if ($code) {
                    $this->permissionCache[] = $code;
                }
            }
        }
    }

    /**
     * Get all available dashboards
     * 
     * @return array List of all dashboards from config
     */
    public function getAllDashboards()
    {
        return $this->dashboardConfig;
    }

    /**
     * Get dashboards accessible to current user
     * Filters by roles and permissions
     * 
     * @return array Accessible dashboards keyed by route name
     */
    public function getAccessibleDashboards()
    {
        if (!$this->currentUser) {
            return [];
        }

        $accessible = [];
        $userRoles = $this->currentUser['roles'] ?? [];

        // Ensure roles is an array of strings
        if (is_array($userRoles) && count($userRoles) > 0) {
            if (is_array($userRoles[0])) {
                // If roles is array of objects, extract names
                $extracted = [];
                foreach ($userRoles as $role) {
                    $extracted[] = isset($role['name']) ? $role['name'] : $role;
                }
                $userRoles = $extracted;
            }
        }

        foreach ($this->dashboardConfig as $key => $dashboard) {
            // Check role requirement
            $requiredRoles = $dashboard['roles'] ?? [];
            if (!empty($requiredRoles)) {
                $hasRole = false;
                foreach ($requiredRoles as $role) {
                    if (in_array($role, $userRoles)) {
                        $hasRole = true;
                        break;
                    }
                }
                if (!$hasRole) {
                    continue;
                }
            }

            // Check permission requirement
            $requiredPerms = $dashboard['permissions'] ?? [];
            if (!empty($requiredPerms)) {
                $hasAllPerms = true;
                foreach ($requiredPerms as $perm) {
                    if (!in_array($perm, $this->permissionCache)) {
                        $hasAllPerms = false;
                        break;
                    }
                }
                if (!$hasAllPerms) {
                    continue;
                }
            }

            $accessible[$key] = $dashboard;
        }

        return $accessible;
    }

    /**
     * Get first accessible dashboard for user
     * Used as default landing page
     * 
     * @return array|null Dashboard config or null if none accessible
     */
    public function getDefaultDashboard()
    {
        $accessible = $this->getAccessibleDashboards();
        if (empty($accessible)) {
            return null;
        }

        return array_shift($accessible);
    }

    /**
     * Get specific dashboard by key
     * 
     * @param string $dashboardKey Dashboard key from config
     * @return array|null Dashboard config or null if not found
     */
    public function getDashboard($dashboardKey)
    {
        return $this->dashboardConfig[$dashboardKey] ?? null;
    }

    /**
     * Get menu items for a dashboard, filtered by user permissions
     * 
     * @param string $dashboardKey Dashboard key
     * @return array Filtered menu items
     */
    public function getMenuItems($dashboardKey)
    {
        $dashboard = $this->getDashboard($dashboardKey);
        if (!$dashboard || !isset($dashboard['menu_items'])) {
            return [];
        }

        return $this->filterMenuByPermissions($dashboard['menu_items']);
    }

    /**
     * Filter menu items based on user permissions
     * Recursively filters subitems as well
     * 
     * @param array $menuItems Raw menu items
     * @return array Filtered menu items user has access to
     */
    private function filterMenuByPermissions($menuItems)
    {
        $filtered = [];

        foreach ($menuItems as $item) {
            // Check if item has permission requirement
            if (isset($item['permissions'])) {
                $hasAccess = false;
                foreach ($item['permissions'] as $perm) {
                    if (in_array($perm, $this->permissionCache)) {
                        $hasAccess = true;
                        break;
                    }
                }
                if (!$hasAccess) {
                    continue;
                }
            }

            // Recursively filter subitems
            if (isset($item['subitems']) && is_array($item['subitems'])) {
                $item['subitems'] = $this->filterMenuByPermissions($item['subitems']);

                // Skip item if it has no accessible subitems
                if (empty($item['subitems'])) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return $filtered;
    }

    /**
     * Check if user can access a specific route
     * 
     * @param string $route Page/dashboard route name
     * @return bool Whether user can access this route
     */
    public function canAccessRoute($route)
    {
        // Check all dashboards for this route
        foreach ($this->dashboardConfig as $dashboard) {
            if ($dashboard['route'] === $route) {
                // Found dashboard, check if user can access it
                $accessible = $this->getAccessibleDashboards();
                foreach ($accessible as $key => $dash) {
                    if ($dash['route'] === $route) {
                        return true;
                    }
                }
                return false;
            }

            // Check menu items for this route
            $this->checkMenuRoutes($dashboard['menu_items'] ?? [], $route);
        }

        return true; // Unknown routes allowed (backend will enforce)
    }

    /**
     * Helper to check if route exists in menu items
     * 
     * @param array $menuItems Menu items to search
     * @param string $route Route to find
     * @return bool Whether route found in menu
     */
    private function checkMenuRoutes($menuItems, $route)
    {
        foreach ($menuItems as $item) {
            if (($item['url'] ?? null) === $route) {
                return true;
            }
            if (isset($item['subitems'])) {
                if ($this->checkMenuRoutes($item['subitems'], $route)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get dashboard info by route name
     * 
     * @param string $routeName Route name (e.g., 'admin_dashboard')
     * @return array|null Dashboard config or null if not found
     */
    public function getDashboardByRoute($routeName)
    {
        foreach ($this->dashboardConfig as $dashboard) {
            if ($dashboard['route'] === $routeName) {
                return $dashboard;
            }
        }
        return null;
    }

    /**
     * Get breadcrumb path for current menu item
     * 
     * @param string $dashboardKey Current dashboard
     * @param string $currentRoute Current route/page
     * @return array Breadcrumb trail [['label' => ..., 'url' => ...], ...]
     */
    public function getBreadcrumbs($dashboardKey, $currentRoute)
    {
        $breadcrumbs = [];
        $dashboard = $this->getDashboard($dashboardKey);

        if ($dashboard) {
            // Add dashboard
            $breadcrumbs[] = [
                'label' => $dashboard['label'],
                'url' => '?route=' . $dashboard['route']
            ];

            // Find current route in menu
            $menuItems = $this->getMenuItems($dashboardKey);
            $found = $this->findBreadcrumb($menuItems, $currentRoute, $breadcrumbs);
        }

        return $breadcrumbs;
    }

    /**
     * Helper to find breadcrumb path recursively
     * 
     * @param array $menuItems Menu items to search
     * @param string $targetRoute Target route to find
     * @param array &$breadcrumbs Breadcrumb array to build
     * @return bool Whether route was found
     */
    private function findBreadcrumb($menuItems, $targetRoute, &$breadcrumbs)
    {
        foreach ($menuItems as $item) {
            if (($item['url'] ?? null) === $targetRoute) {
                $breadcrumbs[] = [
                    'label' => $item['label'],
                    'url' => '?route=' . $item['url']
                ];
                return true;
            }

            if (isset($item['subitems'])) {
                $before = count($breadcrumbs);
                if ($this->findBreadcrumb($item['subitems'], $targetRoute, $breadcrumbs)) {
                    // Add parent to breadcrumb
                    array_splice($breadcrumbs, $before, 0, [
                        [
                            'label' => $item['label'],
                            'url' => null // Parent items not directly clickable
                        ]
                    ]);
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Export sidebar as JSON for JavaScript
     * For faster client-side rendering
     * 
     * @param string $dashboardKey Dashboard key
     * @return string JSON string of menu items
     */
    public function getMenuItemsJson($dashboardKey)
    {
        return json_encode($this->getMenuItems($dashboardKey));
    }

    /**
     * Cache dashboards to file for faster loading
     * Call this during deployment or configuration changes
     * 
     * @param string $cacheDir Directory to store cache files
     * @return bool Whether cache was created successfully
     */
    public function cacheDashboards($cacheDir = null)
    {
        if (!$cacheDir) {
            $cacheDir = __DIR__ . '/../temp/cache';
        }

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/dashboards_cache.json';

        try {
            file_put_contents(
                $cacheFile,
                json_encode($this->dashboardConfig, JSON_PRETTY_PRINT)
            );
            return true;
        } catch (Exception $e) {
            error_log("Failed to cache dashboards: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load dashboards from cache if available
     * Falls back to config file if cache doesn't exist or is stale
     * 
     * @param string $cacheDir Directory where cache files are stored
     * @return void
     */
    public function loadFromCacheIfAvailable($cacheDir = null)
    {
        if (!$cacheDir) {
            $cacheDir = __DIR__ . '/../temp/cache';
        }

        $cacheFile = $cacheDir . '/dashboards_cache.json';
        $configFile = __DIR__ . '/dashboards.php';

        // Check if cache exists and is newer than config
        if (file_exists($cacheFile) && file_exists($configFile)) {
            if (filemtime($cacheFile) >= filemtime($configFile)) {
                try {
                    $cached = json_decode(file_get_contents($cacheFile), true);
                    if ($cached && is_array($cached)) {
                        $this->dashboardConfig = $cached;
                    }
                } catch (Exception $e) {
                    error_log("Failed to load dashboards from cache: " . $e->getMessage());
                }
            }
        }
    }
}
