<?php
/**
 * Menu Builder Service
 * 
 * Builds navigation menus from database, applying role-based filtering,
 * user overrides, and permission checks.
 * 
 * @package App\API\Services
 * @since 2025-12-28
 */

namespace App\API\Services;

use App\Database\Database;
use Exception;

class MenuBuilderService
{
    private static ?MenuBuilderService $instance = null;
    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): MenuBuilderService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // =========================================================================
    // MENU ITEMS CRUD
    // =========================================================================

    /**
     * Get all menu items
     */
    public function getAllMenuItems(bool $activeOnly = true): array
    {
        $sql = "SELECT mi.*, r.name as route_name, r.url as route_url, r.domain as route_domain
                FROM sidebar_menu_items mi
                LEFT JOIN routes r ON r.id = mi.route_id";
        if ($activeOnly) {
            $sql .= " WHERE mi.is_active = 1";
        }
        $sql .= " ORDER BY mi.parent_id NULLS FIRST, mi.display_order";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Get menu item by ID
     */
    public function getMenuItemById(int $id): ?array
    {
        $stmt = $this->db->query(
            "SELECT mi.*, r.name as route_name, r.url as route_url
             FROM sidebar_menu_items mi
             LEFT JOIN routes r ON r.id = mi.route_id
             WHERE mi.id = ?",
            [$id]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Get child menu items
     */
    public function getChildMenuItems(int $parentId): array
    {
        $stmt = $this->db->query(
            "SELECT mi.*, r.name as route_name, r.url as route_url
             FROM sidebar_menu_items mi
             LEFT JOIN routes r ON r.id = mi.route_id
             WHERE mi.parent_id = ? AND mi.is_active = 1
             ORDER BY mi.display_order",
            [$parentId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Create a menu item
     */
    public function createMenuItem(array $data): int
    {
        $stmt = $this->db->query(
            "INSERT INTO sidebar_menu_items (label, icon, route_id, parent_id, display_order, domain, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['label'],
                $data['icon'] ?? null,
                $data['route_id'] ?? null,
                $data['parent_id'] ?? null,
                $data['display_order'] ?? 0,
                $data['domain'] ?? 'SCHOOL',
                $data['is_active'] ?? 1
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a menu item
     */
    public function updateMenuItem(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        foreach (['label', 'icon', 'route_id', 'parent_id', 'display_order', 'domain', 'is_active'] as $field) {
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
            "UPDATE sidebar_menu_items SET " . implode(', ', $fields) . " WHERE id = ?",
            $values
        );
        return true;
    }

    /**
     * Delete a menu item (cascades to children)
     */
    public function deleteMenuItem(int $id): bool
    {
        $this->db->query("DELETE FROM sidebar_menu_items WHERE id = ?", [$id]);
        return true;
    }

    /**
     * Reorder menu items within a parent
     */
    public function reorderMenuItems(array $itemOrders): bool
    {
        $this->db->beginTransaction();
        try {
            foreach ($itemOrders as $order) {
                $this->db->query(
                    "UPDATE sidebar_menu_items SET display_order = ? WHERE id = ?",
                    [$order['order'], $order['id']]
                );
            }
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // =========================================================================
    // MENU ITEM CONFIGS
    // =========================================================================

    /**
     * Get config for a menu item
     */
    public function getMenuItemConfig(int $menuItemId): ?array
    {
        $stmt = $this->db->query(
            "SELECT * FROM sidebar_menu_configs WHERE menu_item_id = ?",
            [$menuItemId]
        );
        return $stmt->fetch() ?: null;
    }

    /**
     * Create or update menu item config
     */
    public function saveMenuItemConfig(int $menuItemId, array $config): bool
    {
        $this->db->query(
            "INSERT INTO sidebar_menu_configs (menu_item_id, show_badge, badge_source, badge_color, open_in_new_tab, requires_confirmation, confirmation_message, visibility_rule, css_class, tooltip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE 
                show_badge = VALUES(show_badge),
                badge_source = VALUES(badge_source),
                badge_color = VALUES(badge_color),
                open_in_new_tab = VALUES(open_in_new_tab),
                requires_confirmation = VALUES(requires_confirmation),
                confirmation_message = VALUES(confirmation_message),
                visibility_rule = VALUES(visibility_rule),
                css_class = VALUES(css_class),
                tooltip = VALUES(tooltip)",
            [
                $menuItemId,
                $config['show_badge'] ?? 0,
                $config['badge_source'] ?? null,
                $config['badge_color'] ?? 'danger',
                $config['open_in_new_tab'] ?? 0,
                $config['requires_confirmation'] ?? 0,
                $config['confirmation_message'] ?? null,
                is_array($config['visibility_rule'] ?? null) ? json_encode($config['visibility_rule']) : ($config['visibility_rule'] ?? null),
                $config['css_class'] ?? null,
                $config['tooltip'] ?? null
            ]
        );
        return true;
    }

    // =========================================================================
    // ROLE MENU ASSIGNMENTS
    // =========================================================================

    /**
     * Get menu items assigned to a role
     */
    public function getMenuItemsForRole(int $roleId): array
    {
        $stmt = $this->db->query(
            "SELECT mi.*, r.name as route_name, r.url as route_url, r.domain as route_domain,
                    rmi.is_default, rmi.custom_order,
                    mic.show_badge, mic.badge_source, mic.badge_color, mic.open_in_new_tab,
                    mic.requires_confirmation, mic.confirmation_message, mic.css_class, mic.tooltip
             FROM sidebar_menu_items mi
             JOIN role_sidebar_menus rmi ON rmi.menu_item_id = mi.id
             LEFT JOIN routes r ON r.id = mi.route_id
             LEFT JOIN sidebar_menu_configs mic ON mic.menu_item_id = mi.id
             WHERE rmi.role_id = ? AND mi.is_active = 1
             ORDER BY COALESCE(rmi.custom_order, mi.display_order)",
            [$roleId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Assign menu item to role
     */
    public function assignMenuItemToRole(int $roleId, int $menuItemId, bool $isDefault = true, ?int $customOrder = null): bool
    {
        $this->db->query(
            "INSERT INTO role_sidebar_menus (role_id, menu_item_id, is_default, custom_order)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_default = VALUES(is_default), custom_order = VALUES(custom_order)",
            [$roleId, $menuItemId, $isDefault ? 1 : 0, $customOrder]
        );
        return true;
    }

    /**
     * Remove menu item from role
     */
    public function removeMenuItemFromRole(int $roleId, int $menuItemId): bool
    {
        $this->db->query(
            "DELETE FROM role_sidebar_menus WHERE role_id = ? AND menu_item_id = ?",
            [$roleId, $menuItemId]
        );
        return true;
    }

    /**
     * Bulk assign menu items to role
     */
    public function bulkAssignMenuItemsToRole(int $roleId, array $menuItemIds): bool
    {
        $this->db->beginTransaction();
        try {
            // First, remove all existing assignments
            $this->db->query("DELETE FROM role_sidebar_menus WHERE role_id = ?", [$roleId]);

            // Then add new assignments
            $order = 0;
            foreach ($menuItemIds as $menuItemId) {
                $this->assignMenuItemToRole($roleId, $menuItemId, true, $order++);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    // =========================================================================
    // USER MENU OVERRIDES
    // =========================================================================

    /**
     * Get menu overrides for a user
     */
    public function getUserMenuOverrides(int $userId): array
    {
        $stmt = $this->db->query(
            "SELECT umo.*, mi.label as menu_label
             FROM user_sidebar_overrides umo
             JOIN sidebar_menu_items mi ON mi.id = umo.menu_item_id
             WHERE umo.user_id = ?",
            [$userId]
        );
        return $stmt->fetchAll();
    }

    /**
     * Set user menu override
     */
    public function setUserMenuOverride(int $userId, int $menuItemId, string $overrideType, ?int $customOrder = null): bool
    {
        $this->db->query(
            "INSERT INTO user_sidebar_overrides (user_id, menu_item_id, override_type, custom_order)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE custom_order = VALUES(custom_order)",
            [$userId, $menuItemId, $overrideType, $customOrder]
        );
        return true;
    }

    /**
     * Remove user menu override
     */
    public function removeUserMenuOverride(int $userId, int $menuItemId, string $overrideType): bool
    {
        $this->db->query(
            "DELETE FROM user_sidebar_overrides WHERE user_id = ? AND menu_item_id = ? AND override_type = ?",
            [$userId, $menuItemId, $overrideType]
        );
        return true;
    }

    // =========================================================================
    // SIDEBAR BUILDING
    // =========================================================================

    /**
     * Build complete sidebar for a user
     * Considers: role menus + user overrides + permissions
     */
    public function buildSidebarForUser(int $userId, int $roleId, array $userPermissions = []): array
    {
        // 1. Get all menu items for the role
        $menuItems = $this->getMenuItemsForRole($roleId);

        // 2. Get user overrides
        $overrides = $this->getUserMenuOverrides($userId);
        $overrideMap = [];
        foreach ($overrides as $override) {
            $key = $override['menu_item_id'] . '_' . $override['override_type'];
            $overrideMap[$key] = $override;
        }

        // 3. Apply overrides
        $filteredItems = [];
        foreach ($menuItems as $item) {
            // Check for hide override
            if (isset($overrideMap[$item['id'] . '_hide'])) {
                continue; // Skip this item
            }

            // Check for show override (adds items not in role)
            // This is handled separately below

            // Apply custom order override
            if (isset($overrideMap[$item['id'] . '_order'])) {
                $item['display_order'] = $overrideMap[$item['id'] . '_order']['custom_order'];
            }

            $filteredItems[] = $item;
        }

        // 4. Check for 'show' overrides that add items to this user
        $showOverrides = array_filter($overrides, fn($o) => $o['override_type'] === 'show');
        foreach ($showOverrides as $override) {
            // Check if item is already included
            $exists = array_filter($filteredItems, fn($i) => $i['id'] == $override['menu_item_id']);
            if (empty($exists)) {
                $menuItem = $this->getMenuItemById($override['menu_item_id']);
                if ($menuItem && $menuItem['is_active']) {
                    $menuItem['display_order'] = $override['custom_order'] ?? 999;
                    $filteredItems[] = $menuItem;
                }
            }
        }

        // 5. Filter by permissions (route-level)
        if (!empty($userPermissions)) {
            $configService = SystemConfigService::getInstance();
            $filteredItems = array_filter($filteredItems, function ($item) use ($userPermissions, $configService) {
                // If no route, always show (parent menu)
                if (empty($item['route_name'])) {
                    return true;
                }

                // Check if user has required permissions for the route
                $requiredPerms = $configService->getPermissionsForRouteName($item['route_name']);
                if (empty($requiredPerms)) {
                    return true; // No permissions required
                }

                // Check if user has any of the required permissions
                foreach ($requiredPerms as $perm) {
                    if (in_array($perm['name'], $userPermissions)) {
                        return true;
                    }
                }

                return false;
            });
        }

        // 6. Build hierarchical tree
        return $this->buildMenuTree(array_values($filteredItems));
    }

    /**
     * Build menu tree from flat list
     */
    public function buildMenuTree(array $items, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildMenuTree($items, $item['id']);

                $menuItem = [
                    'id' => $item['id'],
                    'label' => $item['label'],
                    'icon' => $item['icon'],
                    'url' => $item['url'] ?? $item['route_name'] ?? null,
                    'route_url' => $item['route_url'] ?? null,
                    'domain' => $item['domain'],
                    'display_order' => $item['display_order'] ?? 0,
                    'subitems' => $children,
                    // UI configs
                    'show_badge' => (bool) ($item['show_badge'] ?? false),
                    'badge_source' => $item['badge_source'] ?? null,
                    'badge_color' => $item['badge_color'] ?? 'danger',
                    'open_in_new_tab' => (bool) ($item['open_in_new_tab'] ?? false),
                    'requires_confirmation' => (bool) ($item['requires_confirmation'] ?? false),
                    'confirmation_message' => $item['confirmation_message'] ?? null,
                    'css_class' => $item['css_class'] ?? null,
                    'tooltip' => $item['tooltip'] ?? null
                ];

                $tree[] = $menuItem;
            }
        }

        // Sort by display_order
        usort($tree, fn($a, $b) => ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0));

        return $tree;
    }

    /**
     * Build sidebar for multiple roles (union)
     */
    public function buildSidebarForMultipleRoles(int $userId, array $roleIds, array $userPermissions = []): array
    {
        $allItems = [];
        $seenKeys = [];

        // Build union of menu items but dedupe by canonical key (route_url, route_name, url, name, label)
        foreach ($roleIds as $roleId) {
            $roleItems = $this->getMenuItemsForRole($roleId);
            foreach ($roleItems as $item) {
                // Construct canonical key for deduplication
                $key = $item['route_url'] ?? $item['route_name'] ?? $item['url'] ?? $item['name'] ?? $item['label'];
                if ($key === null) {
                    // Fallback to menu item id as last resort
                    $key = 'id:' . $item['id'];
                }

                // Keep first encountered item for a given key (role ordering determines priority)
                if (!isset($seenKeys[$key])) {
                    $allItems[] = $item;
                    $seenKeys[$key] = $item['id'];
                }
            }
        }

        // Apply user overrides
        $overrides = $this->getUserMenuOverrides($userId);
        $overrideMap = [];
        foreach ($overrides as $override) {
            $key = $override['menu_item_id'] . '_' . $override['override_type'];
            $overrideMap[$key] = $override;
        }

        // Filter items
        $filteredItems = [];
        foreach ($allItems as $item) {
            if (isset($overrideMap[$item['id'] . '_hide'])) {
                continue;
            }

            if (isset($overrideMap[$item['id'] . '_order'])) {
                $item['display_order'] = $overrideMap[$item['id'] . '_order']['custom_order'];
            }

            $filteredItems[] = $item;
        }

        // Filter by permissions
        if (!empty($userPermissions)) {
            $configService = SystemConfigService::getInstance();
            $filteredItems = array_filter($filteredItems, function ($item) use ($userPermissions, $configService) {
                if (empty($item['route_name'])) {
                    return true;
                }

                $requiredPerms = $configService->getPermissionsForRouteName($item['route_name']);
                if (empty($requiredPerms)) {
                    return true;
                }

                foreach ($requiredPerms as $perm) {
                    if (in_array($perm['name'], $userPermissions)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $this->buildMenuTree(array_values($filteredItems));
    }

    // =========================================================================
    // EXPORT FOR FILE SYNC
    // =========================================================================

    /**
     * Export all role menus in the legacy format for file fallback
     */
    public function getAllRoleMenusForExport(): array
    {
        $stmt = $this->db->query("SELECT id, name FROM roles WHERE is_active = 1");
        $roles = $stmt->fetchAll();

        $export = [];
        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $menuItems = $this->getMenuItemsForRole($roleId);
            $menuTree = $this->buildMenuTree($menuItems);

            $export[$roleId] = [
                'role_name' => $role['name'],
                'menus' => $this->convertToLegacyFormat($menuTree)
            ];
        }

        return $export;
    }

    /**
     * Convert menu tree to legacy format
     */
    private function convertToLegacyFormat(array $tree): array
    {
        $legacy = [];
        foreach ($tree as $item) {
            $legacyItem = [
                'label' => $item['label'],
                'icon' => $item['icon'],
                'url' => $item['url'],
                'subitems' => []
            ];

            if (!empty($item['subitems'])) {
                foreach ($item['subitems'] as $subitem) {
                    $legacyItem['subitems'][] = [
                        'label' => $subitem['label'],
                        'url' => $subitem['url']
                    ];
                }
            }

            $legacy[] = $legacyItem;
        }
        return $legacy;
    }

    // =========================================================================
    // IMPORT FROM LEGACY CONFIG
    // =========================================================================

    /**
     * Import menus from legacy dashboards.php format
     */
    public function importFromLegacyConfig(array $legacyConfig): array
    {
        $results = ['imported' => 0, 'errors' => []];
        $configService = SystemConfigService::getInstance();

        $this->db->beginTransaction();
        try {
            foreach ($legacyConfig as $roleId => $roleConfig) {
                $roleName = $roleConfig['role_name'] ?? "Role $roleId";
                $menus = $roleConfig['menus'] ?? [];

                foreach ($menus as $order => $menu) {
                    // Create or find parent menu item
                    $parentId = $this->findOrCreateMenuItem([
                        'label' => $menu['label'],
                        'icon' => $menu['icon'] ?? null,
                        'route_id' => $menu['url'] ? $this->findRouteIdByName($menu['url']) : null,
                        'parent_id' => null,
                        'display_order' => $order,
                        'domain' => $this->inferDomain($roleId)
                    ]);

                    // Assign to role
                    $this->assignMenuItemToRole($roleId, $parentId, true, $order);
                    $results['imported']++;

                    // Create subitems
                    if (!empty($menu['subitems'])) {
                        foreach ($menu['subitems'] as $subOrder => $subitem) {
                            $childId = $this->findOrCreateMenuItem([
                                'label' => $subitem['label'],
                                'icon' => null,
                                'route_id' => $subitem['url'] ? $this->findRouteIdByName($subitem['url']) : null,
                                'parent_id' => $parentId,
                                'display_order' => $subOrder,
                                'domain' => $this->inferDomain($roleId)
                            ]);

                            // Assign subitem to role
                            $this->assignMenuItemToRole($roleId, $childId, true, $subOrder);
                            $results['imported']++;
                        }
                    }
                }
            }

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollback();
            $results['errors'][] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Find or create a menu item
     */
    private function findOrCreateMenuItem(array $data): int
    {
        // Try to find existing
        $stmt = $this->db->query(
            "SELECT id FROM sidebar_menu_items 
             WHERE label = ? AND COALESCE(parent_id, 0) = ? LIMIT 1",
            [$data['label'], $data['parent_id'] ?? 0]
        );
        $existing = $stmt->fetch();

        if ($existing) {
            return (int) $existing['id'];
        }

        return $this->createMenuItem($data);
    }

    /**
     * Find route ID by name
     */
    private function findRouteIdByName(?string $name): ?int
    {
        if (!$name)
            return null;

        $stmt = $this->db->query(
            "SELECT id FROM routes WHERE name = ? LIMIT 1",
            [$name]
        );
        $result = $stmt->fetch();
        return $result ? (int) $result['id'] : null;
    }

    /**
     * Infer domain from role ID
     */
    private function inferDomain(int $roleId): string
    {
        // System Admin is role 2
        return $roleId === 2 ? 'SYSTEM' : 'SCHOOL';
    }
}
