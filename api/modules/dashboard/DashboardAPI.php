<?php
/**
 * DashboardAPI - Exposes PHP DashboardRouter config to JavaScript
 * This ensures JS router uses PHP config as single source of truth
 */

// role_sidebars.php is procedural config data (returns an array), not a class,
// so it cannot be autoloaded and is intentionally required here.
require_once __DIR__ . '/../../../config/role_sidebars.php';

use App\Config\DashboardRouter;

class DashboardAPI
{
    public function handle()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $action = $_GET['action'] ?? '';

        header('Content-Type: application/json');

        try {
            switch ($action) {
                case 'config':
                    return $this->getConfig();
                case 'route':
                    return $this->getRouteForRole();
                case 'sidebars':
                    return $this->getSidebars();
                default:
                    http_response_code(400);
                    return ['success' => false, 'message' => 'Invalid action'];
            }
        } catch (\Exception $e) {
            http_response_code(500);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function getConfig()
    {
        // Get role dashboard mappings
        $roleDashboards = DashboardRouter::getRoleDashboards();

        // Get role name map
        $roleNameMap = DashboardRouter::getRoleNameMap();

        // Get default dashboard
        $defaultDashboard = DashboardRouter::getDefaultDashboard();

        return [
            'success' => true,
            'data' => [
                'role_dashboards' => $roleDashboards,
                'role_name_map' => $roleNameMap,
                'default_dashboard' => $defaultDashboard,
                'dashboard_registry' => DashboardRouter::getDashboardRegistry(),
            ],
            'message' => 'Dashboard config retrieved',
        ];
    }

    private function getRouteForRole()
    {
        $roleId = isset($_GET['role_id']) ? (int)$_GET['role_id'] : null;

        if (!$roleId) {
            http_response_code(400);
            return ['success' => false, 'message' => 'role_id required'];
        }

        $dashboardKey = DashboardRouter::getDashboardForRole($roleId);

        return [
            'success' => true,
            'data' => [
                'role_id' => $roleId,
                'dashboard_key' => $dashboardKey,
                'dashboard_file' => $dashboardKey . '.php',
                'dashboard_exists' => DashboardRouter::dashboardExists($dashboardKey),
                'controller_exists' => DashboardRouter::getDashboardJsPath($dashboardKey) !== null,
            ],
            'message' => 'Dashboard route retrieved',
        ];
    }

    private function getSidebars()
    {
        global $role_sidebars;

        return [
            'success' => true,
            'data' => $role_sidebars,
            'message' => 'Sidebar config retrieved',
        ];
    }
}

// Handle request if called directly
if (php_sapi_name() === 'cli-server' || php_sapi_name() === 'apache2handler') {
    $api = new DashboardAPI();
    $result = $api->handle();
    echo json_encode($result);
}
