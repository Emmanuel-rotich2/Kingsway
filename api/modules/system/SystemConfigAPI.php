<?php
/**
 * System Configuration API Module
 * 
 * REST API endpoints for managing system configuration:
 * - Routes
 * - Route Permissions
 * - Menu Items
 * - Role Menu Assignments
 * - User Menu Overrides
 * - Dashboards
 * - Dashboard Widgets
 * - System Policies
 * - Config Sync
 * 
 * @package App\API\Modules\System
 * @since 2025-12-28
 */

namespace App\API\Modules\System;

use App\API\Services\SystemConfigService;
use App\API\Services\MenuBuilderService;
use Exception;

class SystemConfigAPI
{
    private SystemConfigService $configService;
    private MenuBuilderService $menuService;
    private ?int $currentUserId;
    private ?int $currentRoleId;

    public function __construct(?int $userId = null, ?int $roleId = null)
    {
        $this->configService = SystemConfigService::getInstance();
        $this->menuService = MenuBuilderService::getInstance();
        $this->currentUserId = $userId;
        $this->currentRoleId = $roleId;
    }

    // =========================================================================
    // ROUTES ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/routes
     */
    public function getRoutes(array $params = []): array
    {
        try {
            $activeOnly = ($params['active_only'] ?? '1') === '1';
            $domain = $params['domain'] ?? null;

            if ($domain) {
                $routes = $this->configService->getRoutesByDomain($domain);
            } else {
                $routes = $this->configService->getAllRoutes($activeOnly);
            }

            return [
                'success' => true,
                'data' => $routes,
                'count' => count($routes)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * GET /api/system/routes/{id}
     */
    public function getRoute(int $id): array
    {
        try {
            $route = $this->configService->getRouteById($id);
            if (!$route) {
                return $this->errorResponse('Route not found', 404);
            }

            // Include permissions
            $route['permissions'] = $this->configService->getRoutePermissions($id);

            return [
                'success' => true,
                'data' => $route
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/routes
     */
    public function createRoute(array $data): array
    {
        try {
            $this->validateRouteData($data);

            $id = $this->configService->createRoute($data);
            $route = $this->configService->getRouteById($id);

            return [
                'success' => true,
                'message' => 'Route created successfully',
                'data' => $route
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * PUT /api/system/routes/{id}
     */
    public function updateRoute(int $id, array $data): array
    {
        try {
            $existing = $this->configService->getRouteById($id);
            if (!$existing) {
                return $this->errorResponse('Route not found', 404);
            }

            $this->configService->updateRoute($id, $data);
            $route = $this->configService->getRouteById($id);

            return [
                'success' => true,
                'message' => 'Route updated successfully',
                'data' => $route
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/routes/{id}
     */
    public function deleteRoute(int $id): array
    {
        try {
            $existing = $this->configService->getRouteById($id);
            if (!$existing) {
                return $this->errorResponse('Route not found', 404);
            }

            $this->configService->deleteRoute($id);

            return [
                'success' => true,
                'message' => 'Route deleted successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // ROUTE PERMISSIONS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/routes/{id}/permissions
     */
    public function getRoutePermissions(int $routeId): array
    {
        try {
            $permissions = $this->configService->getRoutePermissions($routeId);

            return [
                'success' => true,
                'data' => $permissions,
                'count' => count($permissions)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/routes/{id}/permissions
     */
    public function assignRoutePermission(int $routeId, array $data): array
    {
        try {
            if (empty($data['permission_id'])) {
                return $this->errorResponse('permission_id is required');
            }

            $this->configService->assignPermissionToRoute(
                $routeId,
                (int) $data['permission_id'],
                $data['access_type'] ?? 'view',
                ($data['is_required'] ?? true) ? true : false
            );

            return [
                'success' => true,
                'message' => 'Permission assigned to route'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/routes/{routeId}/permissions/{permissionId}
     */
    public function removeRoutePermission(int $routeId, int $permissionId): array
    {
        try {
            $this->configService->removePermissionFromRoute($routeId, $permissionId);

            return [
                'success' => true,
                'message' => 'Permission removed from route'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // ROLE ROUTES ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/roles/{roleId}/routes
     */
    public function getRoleRoutes(int $roleId): array
    {
        try {
            $routes = $this->configService->getRoutesForRole($roleId);

            return [
                'success' => true,
                'data' => $routes,
                'count' => count($routes)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/roles/{roleId}/routes
     */
    public function assignRouteToRole(int $roleId, array $data): array
    {
        try {
            if (empty($data['route_id'])) {
                return $this->errorResponse('route_id is required');
            }

            $this->configService->assignRouteToRole(
                $roleId,
                (int) $data['route_id'],
                ($data['is_allowed'] ?? true) ? true : false
            );

            return [
                'success' => true,
                'message' => 'Route assigned to role'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/roles/{roleId}/routes/bulk
     */
    public function bulkAssignRoutesToRole(int $roleId, array $data): array
    {
        try {
            if (empty($data['route_ids']) || !is_array($data['route_ids'])) {
                return $this->errorResponse('route_ids array is required');
            }

            $this->configService->bulkAssignRoutesToRole($roleId, $data['route_ids']);

            return [
                'success' => true,
                'message' => 'Routes assigned to role',
                'count' => count($data['route_ids'])
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/roles/{roleId}/routes/{routeId}
     */
    public function removeRouteFromRole(int $roleId, int $routeId): array
    {
        try {
            $this->configService->removeRouteFromRole($roleId, $routeId);

            return [
                'success' => true,
                'message' => 'Route removed from role'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // USER ROUTES ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/users/{userId}/routes
     */
    public function getUserRouteOverrides(int $userId): array
    {
        try {
            $overrides = $this->configService->getUserRouteOverrides($userId);

            return [
                'success' => true,
                'data' => $overrides,
                'count' => count($overrides)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/users/{userId}/routes
     */
    public function grantUserRouteAccess(int $userId, array $data): array
    {
        try {
            if (empty($data['route_id'])) {
                return $this->errorResponse('route_id is required');
            }

            $this->configService->grantUserRouteAccess(
                $userId,
                (int) $data['route_id'],
                $this->currentUserId,
                $data['reason'] ?? null,
                $data['expires_at'] ?? null
            );

            return [
                'success' => true,
                'message' => 'Route access granted to user'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/users/{userId}/routes/{routeId}
     */
    public function revokeUserRouteAccess(int $userId, int $routeId): array
    {
        try {
            $this->configService->revokeUserRouteAccess($userId, $routeId);

            return [
                'success' => true,
                'message' => 'Route access revoked from user'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // MENU ITEMS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/menus
     */
    public function getMenuItems(array $params = []): array
    {
        try {
            $activeOnly = ($params['active_only'] ?? '1') === '1';
            $items = $this->menuService->getAllMenuItems($activeOnly);

            // Build tree if requested
            if (($params['tree'] ?? '0') === '1') {
                $items = $this->menuService->buildMenuTree($items);
            }

            return [
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * GET /api/system/menus/{id}
     */
    public function getMenuItem(int $id): array
    {
        try {
            $item = $this->menuService->getMenuItemById($id);
            if (!$item) {
                return $this->errorResponse('Menu item not found', 404);
            }

            // Include config
            $item['config'] = $this->menuService->getMenuItemConfig($id);

            // Include children
            $item['children'] = $this->menuService->getChildMenuItems($id);

            return [
                'success' => true,
                'data' => $item
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/menus
     */
    public function createMenuItem(array $data): array
    {
        try {
            $this->validateMenuItemData($data);

            $id = $this->menuService->createMenuItem($data);

            // Save config if provided
            if (!empty($data['config'])) {
                $this->menuService->saveMenuItemConfig($id, $data['config']);
            }

            $item = $this->menuService->getMenuItemById($id);

            return [
                'success' => true,
                'message' => 'Menu item created successfully',
                'data' => $item
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * PUT /api/system/menus/{id}
     */
    public function updateMenuItem(int $id, array $data): array
    {
        try {
            $existing = $this->menuService->getMenuItemById($id);
            if (!$existing) {
                return $this->errorResponse('Menu item not found', 404);
            }

            $this->menuService->updateMenuItem($id, $data);

            // Update config if provided
            if (!empty($data['config'])) {
                $this->menuService->saveMenuItemConfig($id, $data['config']);
            }

            $item = $this->menuService->getMenuItemById($id);

            return [
                'success' => true,
                'message' => 'Menu item updated successfully',
                'data' => $item
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/menus/{id}
     */
    public function deleteMenuItem(int $id): array
    {
        try {
            $existing = $this->menuService->getMenuItemById($id);
            if (!$existing) {
                return $this->errorResponse('Menu item not found', 404);
            }

            $this->menuService->deleteMenuItem($id);

            return [
                'success' => true,
                'message' => 'Menu item deleted successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/menus/reorder
     */
    public function reorderMenuItems(array $data): array
    {
        try {
            if (empty($data['items']) || !is_array($data['items'])) {
                return $this->errorResponse('items array is required');
            }

            $this->menuService->reorderMenuItems($data['items']);

            return [
                'success' => true,
                'message' => 'Menu items reordered successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // ROLE MENU ASSIGNMENTS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/roles/{roleId}/menus
     */
    public function getRoleMenuItems(int $roleId, array $params = []): array
    {
        try {
            $items = $this->menuService->getMenuItemsForRole($roleId);

            // Build tree if requested
            if (($params['tree'] ?? '0') === '1') {
                $items = $this->menuService->buildMenuTree($items);
            }

            return [
                'success' => true,
                'data' => $items,
                'count' => count($items)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/roles/{roleId}/menus
     */
    public function assignMenuItemToRole(int $roleId, array $data): array
    {
        try {
            if (empty($data['menu_item_id'])) {
                return $this->errorResponse('menu_item_id is required');
            }

            $this->menuService->assignMenuItemToRole(
                $roleId,
                (int) $data['menu_item_id'],
                ($data['is_default'] ?? true) ? true : false,
                $data['custom_order'] ?? null
            );

            return [
                'success' => true,
                'message' => 'Menu item assigned to role'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/roles/{roleId}/menus/bulk
     */
    public function bulkAssignMenuItemsToRole(int $roleId, array $data): array
    {
        try {
            if (empty($data['menu_item_ids']) || !is_array($data['menu_item_ids'])) {
                return $this->errorResponse('menu_item_ids array is required');
            }

            $this->menuService->bulkAssignMenuItemsToRole($roleId, $data['menu_item_ids']);

            return [
                'success' => true,
                'message' => 'Menu items assigned to role',
                'count' => count($data['menu_item_ids'])
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/roles/{roleId}/menus/{menuItemId}
     */
    public function removeMenuItemFromRole(int $roleId, int $menuItemId): array
    {
        try {
            $this->menuService->removeMenuItemFromRole($roleId, $menuItemId);

            return [
                'success' => true,
                'message' => 'Menu item removed from role'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // USER MENU OVERRIDES ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/users/{userId}/menus
     */
    public function getUserMenuOverrides(int $userId): array
    {
        try {
            $overrides = $this->menuService->getUserMenuOverrides($userId);

            return [
                'success' => true,
                'data' => $overrides,
                'count' => count($overrides)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/users/{userId}/menus
     */
    public function setUserMenuOverride(int $userId, array $data): array
    {
        try {
            if (empty($data['menu_item_id'])) {
                return $this->errorResponse('menu_item_id is required');
            }
            if (empty($data['override_type']) || !in_array($data['override_type'], ['show', 'hide', 'order'])) {
                return $this->errorResponse('override_type must be show, hide, or order');
            }

            $this->menuService->setUserMenuOverride(
                $userId,
                (int) $data['menu_item_id'],
                $data['override_type'],
                $data['custom_order'] ?? null
            );

            return [
                'success' => true,
                'message' => 'User menu override set'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/users/{userId}/menus/{menuItemId}/{overrideType}
     */
    public function removeUserMenuOverride(int $userId, int $menuItemId, string $overrideType): array
    {
        try {
            $this->menuService->removeUserMenuOverride($userId, $menuItemId, $overrideType);

            return [
                'success' => true,
                'message' => 'User menu override removed'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * GET /api/system/users/{userId}/sidebar
     */
    public function buildUserSidebar(int $userId, int $roleId, array $permissions = []): array
    {
        try {
            $sidebar = $this->menuService->buildSidebarForUser($userId, $roleId, $permissions);

            return [
                'success' => true,
                'data' => $sidebar
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // DASHBOARDS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/dashboards
     */
    public function getDashboards(array $params = []): array
    {
        try {
            $activeOnly = ($params['active_only'] ?? '1') === '1';
            $dashboards = $this->configService->getAllDashboards($activeOnly);

            return [
                'success' => true,
                'data' => $dashboards,
                'count' => count($dashboards)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * GET /api/system/dashboards/{id}
     */
    public function getDashboard(int $id): array
    {
        try {
            // Get all dashboards and find the one with matching ID
            $dashboards = $this->configService->getAllDashboards(false);
            $dashboard = null;
            foreach ($dashboards as $d) {
                if ((int) $d['id'] === $id) {
                    $dashboard = $d;
                    break;
                }
            }

            if (!$dashboard) {
                return $this->errorResponse('Dashboard not found', 404);
            }

            return [
                'success' => true,
                'data' => $dashboard
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
            ;
        }
    }

    /**
     * POST /api/system/dashboards
     */
    public function createDashboard(array $data): array
    {
        try {
            $this->validateDashboardData($data);

            $id = $this->configService->createDashboard($data);

            return [
                'success' => true,
                'message' => 'Dashboard created successfully',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * PUT /api/system/dashboards/{id}
     */
    public function updateDashboard(int $id, array $data): array
    {
        try {
            $this->configService->updateDashboard($id, $data);

            return [
                'success' => true,
                'message' => 'Dashboard updated successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/dashboards/{id}
     */
    public function deleteDashboard(int $id): array
    {
        try {
            $this->configService->deleteDashboard($id);

            return [
                'success' => true,
                'message' => 'Dashboard deleted successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // POLICIES ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/policies
     */
    public function getPolicies(): array
    {
        try {
            $policies = $this->configService->getActivePolicies();

            return [
                'success' => true,
                'data' => $policies,
                'count' => count($policies)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * GET /api/system/policies/{id}
     */
    public function getPolicy(int $id): array
    {
        try {
            $policy = $this->configService->getPolicyById($id);
            if (!$policy) {
                return $this->errorResponse('Policy not found', 404);
            }

            return [
                'success' => true,
                'data' => $policy
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/policies
     */
    public function createPolicy(array $data): array
    {
        try {
            $this->validatePolicyData($data);

            $data['created_by'] = $this->currentUserId;
            $id = $this->configService->createPolicy($data);

            return [
                'success' => true,
                'message' => 'Policy created successfully',
                'data' => ['id' => $id]
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * PUT /api/system/policies/{id}
     */
    public function updatePolicy(int $id, array $data): array
    {
        try {
            $this->configService->updatePolicy($id, $data);

            return [
                'success' => true,
                'message' => 'Policy updated successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * DELETE /api/system/policies/{id}
     */
    public function deletePolicy(int $id): array
    {
        try {
            $this->configService->deletePolicy($id);

            return [
                'success' => true,
                'message' => 'Policy deleted successfully'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // AUTHORIZATION CHECK ENDPOINT
    // =========================================================================

    /**
     * POST /api/system/authorize
     */
    public function checkAuthorization(array $data): array
    {
        try {
            if (empty($data['user_id']) || empty($data['role_id']) || empty($data['route'])) {
                return $this->errorResponse('user_id, role_id, and route are required');
            }

            $result = $this->configService->isUserAuthorizedForRoute(
                (int) $data['user_id'],
                (int) $data['role_id'],
                $data['route']
            );

            return [
                'success' => true,
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // CONFIG SYNC ENDPOINTS
    // =========================================================================

    /**
     * POST /api/system/sync
     */
    public function syncConfigToFiles(): array
    {
        try {
            $result = $this->configService->syncConfigToFiles($this->currentUserId);

            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Configuration synced to files' : 'Sync failed',
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // IMPORT ENDPOINTS
    // =========================================================================

    /**
     * POST /api/system/import/menus
     */
    public function importLegacyMenus(): array
    {
        try {
            $legacyConfig = require dirname(__DIR__, 2) . '/includes/dashboards.php';

            if (!is_array($legacyConfig)) {
                return $this->errorResponse('Could not load legacy config');
            }

            $result = $this->menuService->importFromLegacyConfig($legacyConfig);

            return [
                'success' => empty($result['errors']),
                'message' => "Imported {$result['imported']} menu items",
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/import/routes
     * Import routes from legacy RouteAuthorization.php
     */
    public function importLegacyRoutes(): array
    {
        try {
            // Path to legacy route authorization file
            $legacyFile = dirname(dirname(__DIR__)) . '/middleware/RouteAuthorization.php';

            if (!file_exists($legacyFile)) {
                return $this->errorResponse('Legacy RouteAuthorization.php file not found');
            }

            require_once $legacyFile;

            // Get ROLE_ROUTE_MATRIX using reflection
            $reflectionClass = new \ReflectionClass('RouteAuthorization');
            $matrix = $reflectionClass->getConstant('ROLE_ROUTE_MATRIX');

            if (!$matrix || !is_array($matrix)) {
                return $this->errorResponse('ROLE_ROUTE_MATRIX not found or invalid');
            }

            $imported = 0;
            $errors = [];

            foreach ($matrix as $roleId => $routes) {
                foreach ($routes as $routeName) {
                    try {
                        // Check if route exists in database
                        $route = $this->configService->getRouteByName($routeName);

                        if (!$route) {
                            // Create route if it doesn't exist
                            $routeId = $this->configService->createRoute([
                                'name' => $routeName,
                                'path' => $routeName,
                                'domain' => 'SCHOOL',
                                'description' => "Imported from legacy ROLE_ROUTE_MATRIX for role {$roleId}",
                                'is_active' => 1
                            ]);
                        } else {
                            $routeId = $route['id'];
                        }

                        // Assign route to role
                        $this->configService->assignRouteToRole($roleId, $routeId, true, $this->currentUserId);
                        $imported++;
                    } catch (Exception $e) {
                        $errors[] = "Route {$routeName} for role {$roleId}: " . $e->getMessage();
                    }
                }
            }

            return [
                'success' => empty($errors),
                'message' => "Imported {$imported} role-route assignments",
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors
                ]
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // ROLE DASHBOARDS ENDPOINTS
    // =========================================================================

    /**
     * GET /api/system/roles/{roleId}/dashboards
     */
    public function getRoleDashboards(int $roleId): array
    {
        try {
            $dashboards = $this->configService->getDashboardsForRole($roleId);

            return [
                'success' => true,
                'data' => $dashboards,
                'count' => count($dashboards)
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * POST /api/system/roles/{roleId}/dashboards
     */
    public function assignDashboardToRole(int $roleId, array $data): array
    {
        try {
            if (empty($data['dashboard_id'])) {
                return $this->errorResponse('dashboard_id is required');
            }

            $this->configService->assignDashboardToRole(
                $roleId,
                (int) $data['dashboard_id'],
                ($data['is_primary'] ?? false) ? true : false,
                $data['display_order'] ?? 0
            );

            return [
                'success' => true,
                'message' => 'Dashboard assigned to role'
            ];
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }

    // =========================================================================
    // VALIDATION HELPERS
    // =========================================================================

    private function validateRouteData(array $data): void
    {
        if (empty($data['name'])) {
            throw new Exception('Route name is required');
        }
        if (empty($data['url'])) {
            throw new Exception('Route URL is required');
        }
        if (!empty($data['domain']) && !in_array($data['domain'], ['SYSTEM', 'SCHOOL'])) {
            throw new Exception('Domain must be SYSTEM or SCHOOL');
        }
    }

    private function validateMenuItemData(array $data): void
    {
        if (empty($data['label'])) {
            throw new Exception('Menu item label is required');
        }
    }

    private function validateDashboardData(array $data): void
    {
        if (empty($data['name'])) {
            throw new Exception('Dashboard name is required');
        }
        if (empty($data['display_name'])) {
            throw new Exception('Dashboard display_name is required');
        }
    }

    private function validatePolicyData(array $data): void
    {
        if (empty($data['name'])) {
            throw new Exception('Policy name is required');
        }
        if (empty($data['display_name'])) {
            throw new Exception('Policy display_name is required');
        }
        if (empty($data['rule_expression'])) {
            throw new Exception('Policy rule_expression is required');
        }
    }

    private function errorResponse(string $message, int $code = 400): array
    {
        return [
            'success' => false,
            'message' => $message,
            'http_code' => $code
        ];
    }
}
