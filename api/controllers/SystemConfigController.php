<?php
/**
 * System Configuration Controller
 * 
 * REST endpoint router for system configuration management.
 * All endpoints require System Administrator role (role_id = 2).
 * 
 * Endpoints:
 * - GET /api/systemconfig/routes - List all routes
 * - GET /api/systemconfig/routes/{id} - Get route by ID
 * - POST /api/systemconfig/routes - Create route
 * - PUT /api/systemconfig/routes/{id} - Update route
 * - DELETE /api/systemconfig/routes/{id} - Delete route
 * - GET /api/systemconfig/menus - List all menu items
 * - POST /api/systemconfig/menus - Create menu item
 * - PUT /api/systemconfig/menus/{id} - Update menu item
 * - DELETE /api/systemconfig/menus/{id} - Delete menu item
 * - GET /api/systemconfig/dashboards - List all dashboards
 * - POST /api/systemconfig/dashboards - Create dashboard
 * - GET /api/systemconfig/policies - List all policies
 * - POST /api/systemconfig/policies - Create policy
 * - POST /api/systemconfig/sync - Sync config to files
 * - POST /api/systemconfig/import/menus - Import legacy menus
 * - POST /api/systemconfig/import/routes - Import legacy routes
 * 
 * @package App\API\Controllers
 * @since 2025-12-28
 */

namespace App\API\Controllers;

require_once dirname(__DIR__) . '/modules/system/SystemConfigAPI.php';
require_once dirname(__DIR__) . '/services/PolicyEngine.php';

use App\API\Modules\System\SystemConfigAPI;
use Exception;

class SystemConfigController extends BaseController
{
    private SystemConfigAPI $api;

    public function __construct()
    {
        parent::__construct();
        $userId = $this->user['id'] ?? $this->user['user_id'] ?? null;
        $roleId = $this->getPrimaryRoleId();
        $this->api = new SystemConfigAPI($userId, $roleId);
    }

    /**
     * Get the primary role ID from user context
     */
    private function getPrimaryRoleId(): ?int
    {
        $roles = $this->user['roles'] ?? [];
        if (empty($roles)) {
            return null;
        }

        $firstRole = $roles[0];
        if (is_array($firstRole)) {
            return (int) ($firstRole['id'] ?? $firstRole['role_id'] ?? null);
        }
        return (int) $firstRole;
    }

    /**
     * Handle incoming request - dispatches to appropriate handler method
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $pathParts Path segments after /api/system/
     * @param array $params Query parameters
     * @param array $body Request body
     * @return array Response with 'status' and 'body' keys
     */
    public function handleRequest(string $method, array $pathParts, array $params, array $body): array
    {
        $method = strtolower($method);
        $resource = $pathParts[0] ?? '';
        $id = isset($pathParts[1]) && is_numeric($pathParts[1]) ? (int) $pathParts[1] : null;
        $subResource = $pathParts[2] ?? null;

        // Build method name: {method}{Resource}{SubResource}
        // e.g., GET /routes -> getRoutes, POST /routes/1/permissions -> postRoutesPermissions
        $methodName = $method . ucfirst($resource);
        if ($subResource) {
            $methodName .= ucfirst($subResource);
        }

        // Check if method exists
        if (!method_exists($this, $methodName)) {
            return [
                'status' => 404,
                'body' => [
                    'success' => false,
                    'message' => "Endpoint not found: {$method} /{$resource}" . ($subResource ? "/{$subResource}" : "")
                ]
            ];
        }

        try {
            $result = $this->$methodName($id, $body, $pathParts);

            // Determine status code from result
            $status = 200;
            if (isset($result['http_code'])) {
                $status = $result['http_code'];
                unset($result['http_code']);
            } elseif (isset($result['success']) && !$result['success']) {
                $status = 400;
            }

            return [
                'status' => $status,
                'body' => $result
            ];
        } catch (Exception $e) {
            return [
                'status' => 500,
                'body' => [
                    'success' => false,
                    'message' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Check if user is System Admin (role_id = 2)
     */
    private function requireSystemAdmin(): bool
    {
        $roleId = $this->getPrimaryRoleId();
        if ($roleId !== 2) {
            return false;
        }
        return true;
    }

    // ========================================================================
    // ROUTES ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/routes
     */
    public function getRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getRoute((int) $id));
        }

        $params = [];
        $domain = $data['domain'] ?? $_GET['domain'] ?? null;
        if ($domain) {
            $params['domain'] = $domain;
        }

        return $this->success($this->api->getRoutes($params));
    }

    /**
     * POST /api/systemconfig/routes
     */
    public function postRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->created($this->api->createRoute($data), 'Route created successfully');
    }

    /**
     * PUT /api/systemconfig/routes/{id}
     */
    public function putRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Route ID is required');
        }

        return $this->success($this->api->updateRoute((int) $id, $data), 'Route updated successfully');
    }

    /**
     * DELETE /api/systemconfig/routes/{id}
     */
    public function deleteRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Route ID is required');
        }

        $this->api->deleteRoute((int) $id);
        return $this->success(null, 'Route deleted successfully');
    }

    // ========================================================================
    // ROUTE PERMISSIONS ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/routes-permissions/{routeId}
     */
    public function getRoutesPermissions($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getRoutePermissions((int) $id));
        }

        return $this->badRequest('Route ID is required');
    }

    /**
     * POST /api/systemconfig/routes-permissions
     */
    public function postRoutesPermissions($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $routeId = $data['route_id'] ?? null;
        $permissionId = $data['permission_id'] ?? null;
        $accessType = $data['access_type'] ?? 'required';

        if (!$routeId || !$permissionId) {
            return $this->badRequest('route_id and permission_id are required');
        }

        return $this->created(
            $this->api->assignRoutePermission((int) $routeId, [
                'permission_id' => (int) $permissionId,
                'access_type' => $accessType
            ]),
            'Permission assigned to route'
        );
    }

    /**
     * DELETE /api/systemconfig/routes-permissions/{routeId}/{permissionId}
     */
    public function deleteRoutesPermissions($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $routeId = $id;
        $permissionId = $segments[0] ?? null;

        if (!$routeId || !$permissionId) {
            return $this->badRequest('Route ID and Permission ID are required');
        }

        $this->api->removeRoutePermission((int) $routeId, (int) $permissionId);
        return $this->success(null, 'Permission removed from route');
    }

    // ========================================================================
    // ROLE ROUTES ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/role-routes/{roleId}
     */
    public function getRoleRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getRoleRoutes((int) $id));
        }

        return $this->badRequest('Role ID is required');
    }

    /**
     * POST /api/systemconfig/role-routes
     */
    public function postRoleRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $roleId = $data['role_id'] ?? null;
        $routeId = $data['route_id'] ?? null;

        if (!$roleId || !$routeId) {
            return $this->badRequest('role_id and route_id are required');
        }

        return $this->created(
            $this->api->assignRouteToRole((int) $roleId, [
                'route_id' => (int) $routeId
            ]),
            'Route assigned to role'
        );
    }

    /**
     * POST /api/systemconfig/role-routes-bulk
     */
    public function postRoleRoutesBulk($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $roleId = $data['role_id'] ?? null;
        $routeIds = $data['route_ids'] ?? [];

        if (!$roleId || empty($routeIds)) {
            return $this->badRequest('role_id and route_ids are required');
        }

        return $this->success(
            $this->api->bulkAssignRoutesToRole((int) $roleId, $routeIds),
            'Routes assigned to role'
        );
    }

    /**
     * DELETE /api/systemconfig/role-routes/{roleId}/{routeId}
     */
    public function deleteRoleRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $roleId = $id;
        $routeId = $segments[0] ?? null;

        if (!$roleId || !$routeId) {
            return $this->badRequest('Role ID and Route ID are required');
        }

        $this->api->removeRouteFromRole((int) $roleId, (int) $routeId);
        return $this->success(null, 'Route removed from role');
    }

    // ========================================================================
    // MENUS ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/menus
     */
    public function getMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getMenuItem((int) $id));
        }

        return $this->success($this->api->getMenuItems());
    }

    /**
     * POST /api/systemconfig/menus
     */
    public function postMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->created($this->api->createMenuItem($data), 'Menu item created successfully');
    }

    /**
     * PUT /api/systemconfig/menus/{id}
     */
    public function putMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Menu item ID is required');
        }

        return $this->success($this->api->updateMenuItem((int) $id, $data), 'Menu item updated successfully');
    }

    /**
     * DELETE /api/systemconfig/menus/{id}
     */
    public function deleteMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Menu item ID is required');
        }

        $this->api->deleteMenuItem((int) $id);
        return $this->success(null, 'Menu item deleted successfully');
    }

    /**
     * POST /api/systemconfig/menus-reorder
     */
    public function postMenusReorder($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $orders = $data['orders'] ?? [];
        if (empty($orders)) {
            return $this->badRequest('orders array is required');
        }

        return $this->success($this->api->reorderMenuItems($orders), 'Menu items reordered');
    }

    // ========================================================================
    // ROLE MENUS ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/role-menus/{roleId}
     */
    public function getRoleMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getRoleMenuItems((int) $id));
        }

        return $this->badRequest('Role ID is required');
    }

    /**
     * POST /api/systemconfig/role-menus
     */
    public function postRoleMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $roleId = $data['role_id'] ?? null;
        $menuItemId = $data['menu_item_id'] ?? null;

        if (!$roleId || !$menuItemId) {
            return $this->badRequest('role_id and menu_item_id are required');
        }

        return $this->created(
            $this->api->assignMenuItemToRole((int) $roleId, [
                'menu_item_id' => (int) $menuItemId
            ]),
            'Menu item assigned to role'
        );
    }

    /**
     * POST /api/systemconfig/role-menus-bulk
     */
    public function postRoleMenusBulk($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $roleId = $data['role_id'] ?? null;
        $menuItemIds = $data['menu_item_ids'] ?? [];

        if (!$roleId || empty($menuItemIds)) {
            return $this->badRequest('role_id and menu_item_ids are required');
        }

        return $this->success(
            $this->api->bulkAssignMenuItemsToRole((int) $roleId, $menuItemIds),
            'Menu items assigned to role'
        );
    }

    // ========================================================================
    // USER SIDEBAR ENDPOINT
    // ========================================================================

    /**
     * GET /api/systemconfig/user-sidebar/{userId}
     */
    public function getUserSidebar($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        $userId = $id ? (int) $id : null;
        $roleId = $data['role_id'] ?? $_GET['role_id'] ?? null;

        if (!$userId) {
            return $this->badRequest('User ID is required');
        }

        return $this->success($this->api->buildUserSidebar($userId, $roleId ? (int) $roleId : null));
    }

    // ========================================================================
    // DASHBOARDS ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/dashboards
     */
    public function getDashboards($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getDashboard((int) $id));
        }

        return $this->success($this->api->getDashboards());
    }

    /**
     * POST /api/systemconfig/dashboards
     */
    public function postDashboards($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->created($this->api->createDashboard($data), 'Dashboard created successfully');
    }

    /**
     * PUT /api/systemconfig/dashboards/{id}
     */
    public function putDashboards($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Dashboard ID is required');
        }

        return $this->success($this->api->updateDashboard((int) $id, $data), 'Dashboard updated successfully');
    }

    /**
     * DELETE /api/systemconfig/dashboards/{id}
     */
    public function deleteDashboards($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Dashboard ID is required');
        }

        $this->api->deleteDashboard((int) $id);
        return $this->success(null, 'Dashboard deleted successfully');
    }

    // ========================================================================
    // POLICIES ENDPOINTS
    // ========================================================================

    /**
     * GET /api/systemconfig/policies
     */
    public function getPolicies($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if ($id) {
            return $this->success($this->api->getPolicy((int) $id));
        }

        return $this->success($this->api->getPolicies());
    }

    /**
     * POST /api/systemconfig/policies
     */
    public function postPolicies($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->created($this->api->createPolicy($data), 'Policy created successfully');
    }

    /**
     * PUT /api/systemconfig/policies/{id}
     */
    public function putPolicies($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Policy ID is required');
        }

        return $this->success($this->api->updatePolicy((int) $id, $data), 'Policy updated successfully');
    }

    /**
     * DELETE /api/systemconfig/policies/{id}
     */
    public function deletePolicies($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        if (!$id) {
            return $this->badRequest('Policy ID is required');
        }

        $this->api->deletePolicy((int) $id);
        return $this->success(null, 'Policy deleted successfully');
    }

    // ========================================================================
    // UTILITY ENDPOINTS
    // ========================================================================

    /**
     * POST /api/systemconfig/authorize
     * Check if user is authorized for a route
     */
    public function postAuthorize($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->success($this->api->checkAuthorization($data));
    }

    /**
     * POST /api/systemconfig/sync
     * Sync config from database to PHP files
     */
    public function postSync($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->success($this->api->syncConfigToFiles(), 'Configuration synced to files');
    }

    /**
     * POST /api/systemconfig/import-menus
     * Import menus from legacy dashboards.php
     */
    public function postImportMenus($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->success($this->api->importLegacyMenus(), 'Legacy menus imported successfully');
    }

    /**
     * POST /api/systemconfig/import-routes
     * Import routes from legacy RouteAuthorization.php
     */
    public function postImportRoutes($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->success($this->api->importLegacyRoutes(), 'Legacy routes imported successfully');
    }

    /**
     * GET /api/systemconfig/index
     * Get overview of system configuration
     */
    public function index($id = null, $data = null, $segments = [])
    {
        if (!$this->requireSystemAdmin()) {
            return $this->forbidden('System Administrator role required');
        }

        return $this->success([
            'endpoints' => [
                'routes' => '/api/systemconfig/routes',
                'routes_permissions' => '/api/systemconfig/routes-permissions/{routeId}',
                'role_routes' => '/api/systemconfig/role-routes/{roleId}',
                'menus' => '/api/systemconfig/menus',
                'role_menus' => '/api/systemconfig/role-menus/{roleId}',
                'user_sidebar' => '/api/systemconfig/user-sidebar/{userId}',
                'dashboards' => '/api/systemconfig/dashboards',
                'widgets' => '/api/systemconfig/widgets/{dashboardId}',
                'policies' => '/api/systemconfig/policies',
                'authorize' => '/api/systemconfig/authorize',
                'sync' => '/api/systemconfig/sync',
                'import_menus' => '/api/systemconfig/import-menus',
                'import_routes' => '/api/systemconfig/import-routes'
            ],
            'description' => 'System Configuration API - Manage routes, menus, dashboards, and policies'
        ]);
    }
}
