<?php
/**
 * Dashboard Manager (Database-Driven)
 * 
 * Handles dashboard and menu selection based on user roles and permissions.
 * ALL DATA IS SOURCED FROM THE DATABASE - no hard-coded configurations.
 * 
 * Database Tables Used:
 * - dashboards: Dashboard definitions
 * - role_dashboards: Role to dashboard mappings
 * - sidebar_menu_items: Navigation menu items with hierarchy
 * - role_sidebar_menus: Role to menu item mappings
 * - routes: Route definitions with domain classification
 * - role_routes: Role to route access permissions
 * 
 * @package App\Includes
 * @since 2025-12-28
 */

require_once dirname(__DIR__, 1) . '/../database/Database.php';

use App\Database\Database;

class DashboardManager
{
    private ?\PDO $db = null;
    private ?array $currentUser = null;
    private array $permissionCache = [];
    private array $dashboardCache = [];
    private array $menuCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Set current user context from session or API response
     * 
     * @param array $userData User data array with 'roles' and 'permissions'
     */
    public function setUser($userData): void
    {
        $this->currentUser = $userData;
        $this->permissionCache = [];
        $this->dashboardCache = [];
        $this->menuCache = [];

        // Build permission cache for fast lookups
        if (isset($userData['permissions']) && is_array($userData['permissions'])) {
            foreach ($userData['permissions'] as $perm) {
                if (is_string($perm)) {
                    $this->permissionCache[] = $perm;
                } elseif (is_array($perm)) {
                    $code = $perm['permission_code'] ?? $perm['code'] ?? null;
                    if ($code) {
                        $this->permissionCache[] = $code;
                    }
                }
            }
        }
    }

    /**
     * Get primary role ID from current user
     */
    private function getPrimaryRoleId(): ?int
    {
        if (!$this->currentUser) {
            return null;
        }

        $roles = $this->currentUser['roles'] ?? [];
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
     * Get all role IDs for current user
     */
    private function getUserRoleIds(): array
    {
        if (!$this->currentUser) {
            return [];
        }

        $roles = $this->currentUser['roles'] ?? [];
        $roleIds = [];

        foreach ($roles as $role) {
            if (is_array($role)) {
                $roleIds[] = (int) ($role['id'] ?? $role['role_id'] ?? 0);
            } else {
                $roleIds[] = (int) $role;
            }
        }

        return array_filter($roleIds);
    }

    /**
     * Get all dashboards from database
     * 
     * @return array List of all active dashboards
     */
    public function getAllDashboards(): array
    {
        $stmt = $this->db->query(
            "SELECT d.*, r.name as route_name, r.url as route_url
             FROM dashboards d
             LEFT JOIN routes r ON r.id = d.route_id
             WHERE d.is_active = 1
             ORDER BY d.id"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get dashboards accessible to current user based on role
     * 
     * @return array Accessible dashboards
     */
    public function getAccessibleDashboards(): array
    {
        if (!$this->currentUser) {
            return [];
        }

        $roleIds = $this->getUserRoleIds();
        if (empty($roleIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT DISTINCT d.*, r.name as route_name, r.url as route_url, rd.is_primary
             FROM dashboards d
             JOIN role_dashboards rd ON rd.dashboard_id = d.id
             LEFT JOIN routes r ON r.id = d.route_id
             WHERE rd.role_id IN ({$placeholders}) AND d.is_active = 1
             ORDER BY rd.is_primary DESC, rd.display_order"
        );
        $stmt->execute($roleIds);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get the default/primary dashboard for current user
     * 
     * @return array|null Dashboard config or null if none accessible
     */
    public function getDefaultDashboard(): ?array
    {
        $roleId = $this->getPrimaryRoleId();
        if (!$roleId) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT d.*, r.name as route_name, r.url as route_url
             FROM dashboards d
             JOIN role_dashboards rd ON rd.dashboard_id = d.id
             LEFT JOIN routes r ON r.id = d.route_id
             WHERE rd.role_id = ? AND d.is_active = 1
             ORDER BY rd.is_primary DESC, rd.display_order
             LIMIT 1"
        );
        $stmt->execute([$roleId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get specific dashboard by ID or name
     * 
     * @param string|int $dashboardKey Dashboard ID or name
     * @return array|null Dashboard config or null if not found
     */
    public function getDashboard($dashboardKey): ?array
    {
        if (is_numeric($dashboardKey)) {
            $stmt = $this->db->prepare(
                "SELECT d.*, r.name as route_name, r.url as route_url
                 FROM dashboards d
                 LEFT JOIN routes r ON r.id = d.route_id
                 WHERE d.id = ? AND d.is_active = 1"
            );
        } else {
            $stmt = $this->db->prepare(
                "SELECT d.*, r.name as route_name, r.url as route_url
                 FROM dashboards d
                 LEFT JOIN routes r ON r.id = d.route_id
                 WHERE d.name = ? AND d.is_active = 1"
            );
        }
        $stmt->execute([$dashboardKey]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get menu items for current user's role from database
     * Returns hierarchical menu structure
     * 
     * @param string|int|null $dashboardKey Optional dashboard key (uses primary role if null)
     * @return array Filtered menu items with hierarchy
     */
    public function getMenuItems($dashboardKey = null): array
    {
        $roleId = $this->getPrimaryRoleId();
        if (!$roleId) {
            return [];
        }

        // Get all menu items assigned to this role
        $stmt = $this->db->prepare(
            "SELECT smi.*, rsm.custom_order
             FROM sidebar_menu_items smi
             JOIN role_sidebar_menus rsm ON rsm.menu_item_id = smi.id
             WHERE rsm.role_id = ? AND smi.is_active = 1
             ORDER BY COALESCE(rsm.custom_order, smi.display_order)"
        );
        $stmt->execute([$roleId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Hide 'My Classes' (ids 400,402) for Class Teacher (role id 7) at code level
        if ($roleId === 7) {
            $items = array_filter($items, function($it) {
                return !in_array((int)$it['id'], [400, 402], true);
            });
            // Reindex array
            $items = array_values($items);
        }

        // Build hierarchical structure
        return $this->buildMenuHierarchy($items);
    }

    /**
     * Build hierarchical menu structure from flat database results
     * 
     * @param array $items Flat array of menu items
     * @return array Hierarchical menu structure
     */
    private function buildMenuHierarchy(array $items): array
    {
        // First, index all items by ID
        $indexed = [];
        foreach ($items as $item) {
            $indexed[$item['id']] = [
                'label' => $item['label'],
                'url' => $item['url'],
                'icon' => $item['icon'],
                'id' => $item['id'],
                'parent_id' => $item['parent_id'],
                'display_order' => $item['custom_order'] ?? $item['display_order'],
                'subitems' => []
            ];
        }

        // Build the tree
        $tree = [];
        foreach ($indexed as $id => &$item) {
            if ($item['parent_id'] === null) {
                // Top-level item
                $tree[] = &$item;
            } else {
                // Child item - attach to parent if parent exists
                $parentId = $item['parent_id'];
                if (isset($indexed[$parentId])) {
                    $indexed[$parentId]['subitems'][] = &$item;
                }
            }
        }
        unset($item);

        // Sort each level by display_order
        usort($tree, fn($a, $b) => ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0));
        foreach ($indexed as &$item) {
            if (!empty($item['subitems'])) {
                usort($item['subitems'], fn($a, $b) => ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0));
            }
        }

        return $tree;
    }

    /**
     * Filter menu items based on user permissions
     * 
     * @param array $menuItems Menu items to filter
     * @return array Filtered menu items
     */
    public function filterMenuItems($menuItems): array
    {
        // For database-driven menus, filtering is already done via role_sidebar_menus
        // This method is kept for backwards compatibility
        return $menuItems;
    }

    /**
     * Check if user can access a specific route
     * 
     * @param string $route Route name
     * @return bool Whether user can access this route
     */
    public function canAccessRoute($route): bool
    {
        $roleIds = $this->getUserRoleIds();
        if (empty($roleIds)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM role_routes rr
             JOIN routes r ON r.id = rr.route_id
             WHERE rr.role_id IN ({$placeholders}) AND r.name = ? AND rr.is_allowed = 1"
        );
        $params = array_merge($roleIds, [$route]);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Get dashboard info by route name
     * 
     * @param string $routeName Route name
     * @return array|null Dashboard config or null if not found
     */
    public function getDashboardByRoute($routeName): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT d.*, r.name as route_name, r.url as route_url
             FROM dashboards d
             JOIN routes r ON r.id = d.route_id
             WHERE r.name = ? AND d.is_active = 1"
        );
        $stmt->execute([$routeName]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get breadcrumb path for current menu item
     * 
     * @param string $dashboardKey Current dashboard
     * @param string $currentRoute Current route/page
     * @return array Breadcrumb trail
     */
    public function getBreadcrumbs($dashboardKey, $currentRoute): array
    {
        $breadcrumbs = [];

        // Get the menu item for current route
        $stmt = $this->db->prepare(
            "SELECT id, label, url, parent_id FROM sidebar_menu_items WHERE url = ? AND is_active = 1"
        );
        $stmt->execute([$currentRoute]);
        $current = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$current) {
            return $breadcrumbs;
        }

        // Build breadcrumb trail by walking up the parent chain
        $trail = [];
        $itemId = $current['id'];
        $visited = [];

        while ($itemId !== null && !in_array($itemId, $visited)) {
            $visited[] = $itemId;

            $stmt = $this->db->prepare(
                "SELECT id, label, url, parent_id FROM sidebar_menu_items WHERE id = ?"
            );
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($item) {
                array_unshift($trail, [
                    'label' => $item['label'],
                    'url' => $item['url']
                ]);
                $itemId = $item['parent_id'];
            } else {
                break;
            }
        }

        return $trail;
    }

    /**
     * Get sidebar menu for a specific role (for rendering)
     * 
     * @param int $roleId Role ID
     * @return array Menu items with hierarchy
     */
    public function getSidebarForRole(int $roleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT smi.*, rsm.custom_order
             FROM sidebar_menu_items smi
             JOIN role_sidebar_menus rsm ON rsm.menu_item_id = smi.id
             WHERE rsm.role_id = ? AND smi.is_active = 1
             ORDER BY COALESCE(rsm.custom_order, smi.display_order)"
        );
        $stmt->execute([$roleId]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Hide 'My Classes' (ids 400,402) for Class Teacher (role id 7)
        if ($roleId === 7) {
            $items = array_filter($items, function($it) {
                return !in_array((int)$it['id'], [400, 402], true);
            });
            $items = array_values($items);
        }

        return $this->buildMenuHierarchy($items);
    }

    /**
     * Get dashboard route for a role (for login redirect)
     * 
     * @param int $roleId Role ID
     * @return string|null Dashboard route name or null
     */
    public function getDashboardRouteForRole(int $roleId): ?string
    {
        $stmt = $this->db->prepare(
            "SELECT r.name
             FROM dashboards d
             JOIN role_dashboards rd ON rd.dashboard_id = d.id
             JOIN routes r ON r.id = d.route_id
             WHERE rd.role_id = ? AND d.is_active = 1
             ORDER BY rd.is_primary DESC
             LIMIT 1"
        );
        $stmt->execute([$roleId]);
        $result = $stmt->fetchColumn();
        return $result ?: null;
    }

    /**
     * Check if a dashboard belongs to SYSTEM domain
     * 
     * @param string $dashboardName Dashboard name
     * @return bool
     */
    public function isSystemDashboard(string $dashboardName): bool
    {
        $stmt = $this->db->prepare(
            "SELECT domain FROM dashboards WHERE name = ?"
        );
        $stmt->execute([$dashboardName]);
        $domain = $stmt->fetchColumn();
        return $domain === 'SYSTEM';
    }

    /**
     * Get all menu items for a role (flat list for quick lookups)
     * 
     * @param int $roleId Role ID
     * @return array Flat list of menu item URLs
     */
    public function getAllMenuRoutesForRole(int $roleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT smi.url
             FROM sidebar_menu_items smi
             JOIN role_sidebar_menus rsm ON rsm.menu_item_id = smi.id
             WHERE rsm.role_id = ? AND smi.is_active = 1 AND smi.url IS NOT NULL"
        );
        $stmt->execute([$roleId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
