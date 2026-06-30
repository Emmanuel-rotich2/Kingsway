<?php
/**
 * DashboardAPI - Exposes PHP DashboardRouter config to JavaScript
 * This ensures JS router uses PHP config as single source of truth
 */

require_once __DIR__ . '/../../config/DashboardRouter.php';
require_once __DIR__ . '/../../config/role_sidebars.php';

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
        $router = new \DashboardRouter();

        // Get role dashboard mappings
        $roleDashboards = $router->getRoleDashboards();

        // Get role name map
        $roleNameMap = $router->getRoleNameMap();

        // Get default dashboard
        $defaultDashboard = $router->getDefaultDashboard();

        return [
            'success' => true,
            'data' => [
                'role_dashboards' => $roleDashboards,
                'role_name_map' => $roleNameMap,
                'default_dashboard' => $defaultDashboard,
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

        $router = new \DashboardRouter();
        $dashboardKey = $router->getDashboardForRole($roleId);

        return [
            'success' => true,
            'data' => [
                'role_id' => $roleId,
                'dashboard_key' => $dashboardKey,
                'dashboard_file' => $dashboardKey . '.php',
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