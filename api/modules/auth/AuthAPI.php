<?php
namespace App\API\Modules\auth;

use App\API\Includes\BaseAPI;
use App\API\Modules\users\UsersAPI;
use App\API\Modules\users\RoleManager;
use App\API\Modules\users\PermissionManager;
use App\API\Modules\users\UserRoleManager;
use App\API\Modules\users\UserPermissionManager;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\MenuBuilderService;
use App\API\Services\SystemConfigService;
use App\Services\PolicyEngine;
use Firebase\JWT\JWT;

require_once __DIR__ . '/../../includes/DashboardManager.php';
require_once __DIR__ . '/../../../config/DashboardRouter.php';

class AuthAPI extends BaseAPI
{
    private $usersApi;
    private $roleManager;
    private $permissionManager;
    private $userRoleManager;
    private $userPermissionManager;
    private $communicationsApi;

    // New database-driven services
    private ?MenuBuilderService $menuBuilder = null;
    private ?SystemConfigService $configService = null;

    // Feature flag: use database-driven config (set to true when migration is complete)
    private bool $useDatabaseConfig = false;

    public function __construct()
    {
        parent::__construct('auth');
        $this->usersApi = new UsersAPI();
        $this->roleManager = new RoleManager($this->db);
        $this->permissionManager = new PermissionManager($this->db);
        $this->userRoleManager = new UserRoleManager($this->db);
        $this->userPermissionManager = new UserPermissionManager($this->db);
        $this->communicationsApi = new CommunicationsAPI();

        // Check if database-driven config is available
        $this->useDatabaseConfig = $this->checkDatabaseConfigAvailable();
    }

    /**
     * Check if database-driven config tables exist
     */
    private function checkDatabaseConfigAvailable(): bool
    {
        try {
            // Check for sidebar_menu_items table (renamed from menu_items to avoid collision with food menu)
            $stmt = $this->db->query("SHOW TABLES LIKE 'sidebar_menu_items'");
            if ($stmt->rowCount() === 0) {
                return false;
            }

            // Consider the database config available if either per-role sidebar mappings exist
            // OR role->dashboard mappings exist (role_dashboards). Some deployments use one or the other.
            $stmt = $this->db->query("SELECT COUNT(*) as cnt FROM role_sidebar_menus");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $roleSidebarCount = (int) ($result['cnt'] ?? 0);

            $stmt = $this->db->query("SELECT COUNT(*) as cnt FROM role_dashboards");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $roleDashboardsCount = (int) ($result['cnt'] ?? 0);

            return ($roleSidebarCount > 0) || ($roleDashboardsCount > 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get MenuBuilderService (lazy load)
     */
    private function getMenuBuilder(): MenuBuilderService
    {
        if ($this->menuBuilder === null) {
            $this->menuBuilder = MenuBuilderService::getInstance();
        }
        return $this->menuBuilder;
    }

    /**
     * Get SystemConfigService (lazy load)
     */
    private function getConfigService(): SystemConfigService
    {
        if ($this->configService === null) {
            $this->configService = SystemConfigService::getInstance();
        }
        return $this->configService;
    }
    // Logout user (invalidate session/token as needed)
    public function logout($data)
    {
        // Example: Invalidate token on client side, optionally log event
        // If using server-side sessions, destroy session here
        // For JWT, usually just instruct client to delete token
        return [
            'success' => true,
            'message' => 'Logged out successfully.'
        ];
    }

    // Forgot password workflow (send reset email or SMS with code and link)
    public function forgotPassword($data)
    {
        $email = $data['email'] ?? null;
        if (!$email) {
            return [
                'success' => false,
                'message' => 'Email is required.'
            ];
        }
        // Generate a reset code and link (store code in DB or cache with expiry)
        $resetCode = bin2hex(random_bytes(4));
        $resetLink = $this->generateResetLink($email, $resetCode);
        // Store code and expiry (pseudo, implement as needed)
        // $this->storeResetCode($email, $resetCode);
        // Send email (or SMS) with code and link
        $this->sendResetEmail($email, $email, $resetLink); // username/email for demo
        return [
            'success' => true,
            'message' => 'Password reset instructions sent to your email.'
        ];
    }

    // Reset password using code
    public function resetPassword($data)
    {
        $email = $data['email'] ?? null;
        $code = $data['code'] ?? null;
        $newPassword = $data['new_password'] ?? null;
        if (!$email || !$code || !$newPassword) {
            return [
                'success' => false,
                'message' => 'Email, code, and new password are required.'
            ];
        }
        // Validate code (pseudo, implement as needed)
        // $valid = $this->validateResetCode($email, $code);
        $valid = true; // For demo, always valid
        if (!$valid) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset code.'
            ];
        }
        // Update password (pseudo, implement as needed)
        // $this->usersApi->updatePasswordByEmail($email, $newPassword);
        return [
            'success' => true,
            'message' => 'Password has been reset successfully.'
        ];
    }

    // Refresh JWT token (issue new token if refresh token is valid)
    public function refreshToken($data)
    {
        $refreshToken = $data['refresh_token'] ?? null;
        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Refresh token is required.'
            ];
        }
        // Validate refresh token (pseudo, implement as needed)
        // $userData = $this->validateRefreshToken($refreshToken);
        $userData = [
            'user_id' => 1,
            'username' => 'demo',
            'email' => 'demo@example.com',
            'roles' => [],
            'display_name' => 'Demo User',
            'permissions' => []
        ]; // For demo
        if (!$userData) {
            return [
                'success' => false,
                'message' => 'Invalid refresh token.'
            ];
        }
        $token = $this->generateToken($userData);
        return [
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'data' => [
                'token' => $token
            ]
        ];
    }

    // Helper to generate a reset link (implement as needed)
    private function generateResetLink($email, $code)
    {
        $baseUrl = 'https://yourdomain.com/reset-password';
        return $baseUrl . '?email=' . urlencode($email) . '&code=' . urlencode($code);
    }
     

    // Login user
    public function login($data)
    {
        // Delegate to UsersAPI for authentication and user info
        $result = $this->usersApi->login($data);
        if ($result['success']) {
            // Extract user data - it's nested in $result['data']['user']
            $userData = $result['data']['user'] ?? $result['data'];

            // DO NOT put permissions in token - they're already in userData
            // Token should only contain authentication info (who you are)
            // Permissions are for authorization (what you can do) - stored in localStorage
            $token = $this->generateToken([
                'user_id' => $userData['id'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'roles' => $userData['roles'] ?? [],
                'display_name' => $userData['first_name'] . ' ' . $userData['last_name']
                // NO permissions in token!
            ]);

            // Get user's primary role for dashboard selection
            $userRoles = $userData['roles'] ?? [];
            $primaryRole = null;
            $primaryRoleId = null;

            if (!empty($userRoles)) {
                $primaryRoleData = $userRoles[0];
                if (is_array($primaryRoleData)) {
                    $primaryRoleId = $primaryRoleData['id'] ?? null;
                    $primaryRole = $primaryRoleData['name'] ?? null;
                } else {
                    $primaryRole = $primaryRoleData;
                }
            }

            // Get all role IDs for multi-role support
            $roleIds = [];
            foreach ($userRoles as $role) {
                if (is_array($role)) {
                    $rid = $role['id'] ?? $role['role_id'] ?? null;
                } else {
                    $rid = $role;
                }
                if ($rid) {
                    $roleIds[] = (int) $rid;
                }
            }
            $roleIds = array_values(array_unique($roleIds));

            // Use database-driven config if available, otherwise fall back to file-based
            if ($this->useDatabaseConfig) {
                $loginData = $this->buildLoginResponseFromDatabase(
                    $userData,
                    $primaryRoleId,
                    $roleIds,
                    $token
                );
            } else {
                $loginData = $this->buildLoginResponseFromFiles(
                    $userData,
                    $primaryRole,
                    $primaryRoleId,
                    $roleIds,
                    $token
                );
            }

            return $loginData;
        }
        // If not successful, return error (include debug info in dev)
        return [
            'status' => 'error',
            'message' => $result['error'] ?? $result['message'] ?? 'Login failed',
            'data' => [
                'debug' => $result
            ]
        ];
    }

    /**
     * Build login response using database-driven config
     */
    private function buildLoginResponseFromDatabase(
        array $userData,
        ?int $primaryRoleId,
        array $roleIds,
        string $token
    ): array {
        $userId = $userData['id'];

        // Ensure roles and permissions are present on $userData (some code paths return bare user row)
        if (empty($userData['roles'])) {
            $rolesRes = $this->userRoleManager->getUserRoles($userId);
            $userData['roles'] = $rolesRes['data'] ?? [];
        }
        if (empty($userData['permissions'])) {
            $permsRes = $this->userPermissionManager->getEffectivePermissions($userId);
            $userData['permissions'] = $permsRes['data'] ?? [];
        }

        $userPermissions = array_column($userData['permissions'] ?? [], 'code');

        // Add default role permissions for well-known roles (e.g., Accountant role id 10)
        if (in_array(10, $roleIds, true)) {
            $userPermissions = array_values(array_unique(array_merge($userPermissions, $this->getDefaultRolePermissions(10))));
        }

        // NOTE: We no longer add Headteacher (role 5) wholesale to a Deputy's
        // effective roles when delegation exists. Delegation is performed at
        // the per-menu-item level (see `role_delegations_items`). MenuBuilderService
        // will include only explicitly delegated items for a role. This prevents
        // accidental sharing of the entire sidebar and avoids duplicate dashboard
        // entries between Headteacher and Deputy roles.

        // Merge delegated permissions (per-item) into effective permissions so
        // that delegated menu items that require permissions are accessible.
        $delegatedPermissions = [];
        // If roleIds wasn't provided (some callers), derive from userData
        if (empty($roleIds)) {
            $roleIds = [];
            foreach ($userData['roles'] as $r) {
                $roleIds[] = is_array($r) ? ($r['id'] ?? $r['role_id'] ?? null) : $r;
            }
            $roleIds = array_values(array_filter(array_unique($roleIds)));
        }
        try {
            // Role-level per-item delegations (backwards compatible)
            foreach ($roleIds as $rid) {
                $delegatedItems = $this->getMenuBuilder()->getDelegatedMenuItemsForRole($rid);
                foreach ($delegatedItems as $dItem) {
                    if (!empty($dItem['route_name'])) {
                        $reqPerms = $this->getConfigService()->getPermissionsForRouteName($dItem['route_name']);
                        foreach ($reqPerms as $rp) {
                            $delegatedPermissions[] = $rp['name'];
                        }
                    }
                }
            }

            // User-level per-item delegations (preferred)
            $userDelegatedItems = $this->getMenuBuilder()->getDelegatedMenuItemsForUser($userId);
            foreach ($userDelegatedItems as $dItem) {
                if (!empty($dItem['route_name'])) {
                    $reqPerms = $this->getConfigService()->getPermissionsForRouteName($dItem['route_name']);
                    foreach ($reqPerms as $rp) {
                        $delegatedPermissions[] = $rp['name'];
                    }
                }
            }
        } catch (\Exception $e) {
            // If config service or delegations table not present, skip silently
        }

        // Effective permissions for filtering the sidebar
        $effectivePermissions = array_values(array_unique(array_merge($userPermissions, $delegatedPermissions)));

        try {
            // Build sidebar from database using MenuBuilderService
            if (count($roleIds) > 1) {
                // Multi-role: combine menus from all roles
                $sidebarItems = $this->getMenuBuilder()->buildSidebarForMultipleRoles(
                    $userId,
                    $roleIds,
                    $effectivePermissions
                );
            } else {
                // Single role: get menu for that role
                $sidebarItems = $this->getMenuBuilder()->buildSidebarForUser(
                    $userId,
                    $primaryRoleId ?? 0,
                    $effectivePermissions
                );
            }

            // Resolve dashboard strictly by the user's primary role to avoid cross-role defaults
            $defaultRoute = null;
            $dashboardInfo = null;

            // First, try to get an explicit database mapping for this role
            try {
                $dashboardInfo = $this->getConfigService()->getDashboardForRole($primaryRoleId ?? 0);
                // Normalize dashboard info to ensure name is route key, not full URL
                $dashboardInfo = $this->normalizeDashboardInfo($dashboardInfo);
            } catch (\Exception $e) {
                // Could be missing role_dashboards table or other DB issue
                error_log('getDashboardForRole failed: ' . $e->getMessage());
                $dashboardInfo = null;
            }

            // If database mapping not available, try to derive a dashboard key for the role using DashboardRouter
            if (empty($dashboardInfo)) {
                try {
                    $roleForDashboard = $primaryRoleId ?? ($primaryRole ?? (!empty($userRoles) ? $userRoles[0] : null));
                    if ($roleForDashboard !== null) {
                        $dashboardKey = \DashboardRouter::getDashboardForRole($roleForDashboard);
                        if (!empty($dashboardKey)) {
                            // Attempt to read dashboard record by name
                            $dashboardInfo = $this->getConfigService()->getDashboardByName($dashboardKey);
                            $dashboardInfo = $this->normalizeDashboardInfo($dashboardInfo);
                            $defaultRoute = $dashboardKey;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('DashboardRouter fallback failed: ' . $e->getMessage());
                }
            }

            // If still no dashboard info, try to find a dashboard by role name (useful when role_dashboards table is absent)
            if (empty($dashboardInfo) && !empty($userData['roles'])) {
                $firstRoleName = is_array($userData['roles'][0]) ? ($userData['roles'][0]['name'] ?? null) : $userData['roles'][0];
                if (!empty($firstRoleName)) {
                    try {
                        $found = $this->getConfigService()->findDashboardForRoleName($firstRoleName);
                        if (!empty($found)) {
                            $dashboardInfo = $this->normalizeDashboardInfo($found);
                            $defaultRoute = $found['name'];
                        }
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }

            // Still no dashboard info? fall back to a safe default (system default dashboard)
            if (empty($dashboardInfo)) {
                $defaultRoute = $defaultRoute ?? \DashboardRouter::getDefaultDashboard();
                $dashboardInfo = [
                    'name' => $defaultRoute,
                    'display_name' => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $defaultRoute)))
                ];
            }

            // Ensure defaultRoute is set (route name/key)
            $defaultRoute = $dashboardInfo['name'] ?? $defaultRoute ?? \DashboardRouter::getDefaultDashboard();

            // Normalize user permissions to codes and merge delegated permissions
            $userData = $this->normalizeUserPermissions($userData);
            $userData['permissions'] = array_values(array_unique(array_merge($userData['permissions'] ?? [], $delegatedPermissions)));

            // Add default role permissions for known roles (e.g., Accountant role id 10)
            if (in_array(10, $roleIds, true)) {
                $userData['permissions'] = array_values(array_unique(array_merge($userData['permissions'], $this->getDefaultRolePermissions(10))));
            }

            // Generate refresh token and set it as HttpOnly secure cookie (do not return in body)
            $refreshToken = $this->generateRefreshToken($userId);
            if ($refreshToken) {
                // Set cookie for secure contexts; SameSite=Lax for compatibility
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                setcookie('refresh_token', $refreshToken, time() + (7 * 24 * 60 * 60), '/', '', $secure, true);
                // Also set SameSite attribute if PHP < 7.3 doesn't support options array
                header("Set-Cookie: refresh_token=$refreshToken; Path=/; Max-Age=" . (7 * 24 * 60 * 60) . "; HttpOnly; " . ($secure ? 'Secure; ' : '') . "SameSite=Lax");
            }

            // Determine the role to resolve dashboard for (prefer primary role id)
            $roleForDashboard = $primaryRoleId ?? ($primaryRole ?? (!empty($userRoles) ? $userRoles[0] : null));
            if ($roleForDashboard !== null) {
                $resolvedKey = $dashboardInfo['name'] ?? \DashboardRouter::getDashboardForRole($roleForDashboard);
            } else {
                $resolvedKey = $dashboardInfo['name'] ?? \DashboardRouter::getDefaultDashboard();
            }

            // Normalize resolvedKey to ensure it's a route name, not full URL
            if (preg_match('/[?&]route=([^&]*)/', $resolvedKey, $matches)) {
                $resolvedKey = $matches[1];
            }

            $resolvedUrl = $dashboardInfo['route'] ?? ('?route=' . $resolvedKey);
            $resolvedLabel = $dashboardInfo['display_name'] ?? ($dashboardInfo['title'] ?? ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $resolvedKey))));

            // If we fell back to system default and role is known, try to find a role-specific dashboard by name
            if ($resolvedKey === \DashboardRouter::getDefaultDashboard() && $roleForDashboard !== null) {
                // Try to determine role name from the provided context (prefer userData.roles)
                $roleName = null;
                if (!empty($userData['roles'])) {
                    $firstRole = $userData['roles'][0];
                    if (is_array($firstRole) && !empty($firstRole['name'])) {
                        $roleName = $firstRole['name'];
                    } elseif (is_string($firstRole)) {
                        $roleName = $firstRole;
                    }
                }
                // Fallback to roleForDashboard if it is a string (rare)
                if (empty($roleName) && is_string($roleForDashboard)) {
                    $roleName = $roleForDashboard;
                }
                if (!empty($roleName)) {
                    $found = $this->getConfigService()->findDashboardForRoleName($roleName);
                    if (!empty($found)) {
                        $resolvedKey = $found['name'];
                        $resolvedUrl = $found['route'] ?? ('?route=' . $resolvedKey);
                        $resolvedLabel = $found['display_name'] ?? ($found['title'] ?? $resolvedLabel);
                    }
                }
            }

            // Add dashboard as first menu item
            $dashboardMenuItem = [
                'id' => 'dashboard_' . $primaryRoleId,
                'label' => $resolvedLabel,
                'icon' => 'bi-house-door',
                'url' => $resolvedUrl,
                'route_url' => $resolvedUrl,
                'domain' => 'SCHOOL',
                'display_order' => -200,
                'subitems' => [],
                'show_badge' => false,
                'badge_source' => null,
                'badge_color' => 'danger',
                'open_in_new_tab' => false,
                'requires_confirmation' => false,
                'confirmation_message' => null,
                'css_class' => null,
                'tooltip' => null
            ];
            array_unshift($sidebarItems, $dashboardMenuItem);

            // Final normalization of dashboard key
            if (preg_match('/[?&]route=([^&]*)/', $resolvedKey, $matches)) {
                $resolvedKey = $matches[1];
            }

            return [
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'token_expires_in' => JWT_EXPIRY,
                    'user' => $userData,
                    'sidebar_items' => $this->normalizeSidebarItems($sidebarItems),
                    'dashboard' => [
                        'key' => $resolvedKey,
                        'url' => $resolvedUrl,
                        'label' => $resolvedLabel
                    ],
                    'delegated_permissions' => array_values(array_unique($delegatedPermissions)),
                    'config_source' => 'database'
                ]
            ];
        } catch (\Exception $e) {
            error_log("Database config failed, falling back to files: " . $e->getMessage());
            // Fall back to file-based config on error
            return $this->buildLoginResponseFromFiles(
                $userData,
                $userData['roles'][0]['name'] ?? null,
                $primaryRoleId,
                $roleIds,
                $token
            );
        }
    }

    /**
     * Get default route for a role from database
     */
    private function getDefaultRouteForRole(int $roleId): string
    {
        try {
            // Get the dashboard route for this role from role_dashboards -> dashboards -> routes
            $stmt = $this->db->prepare(
                "SELECT r.name 
                 FROM role_dashboards rd
                 JOIN dashboards d ON d.id = rd.dashboard_id
                 JOIN routes r ON r.id = d.route_id
                 WHERE rd.role_id = ? AND rd.is_primary = 1
                 LIMIT 1"
            );
            $stmt->execute([$roleId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && !empty($result['name'])) {
                return $result['name'];
            }

            // Fallback: try to get any dashboard for this role
            $stmt = $this->db->prepare(
                "SELECT r.name 
                 FROM role_dashboards rd
                 JOIN dashboards d ON d.id = rd.dashboard_id
                 JOIN routes r ON r.id = d.route_id
                 WHERE rd.role_id = ?
                 ORDER BY rd.is_primary DESC
                 LIMIT 1"
            );
            $stmt->execute([$roleId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $result['name'] ?? 'home';
        } catch (\Exception $e) {
            error_log("getDefaultRouteForRole error: " . $e->getMessage());
            return 'home';
        }
    }

    /**
     * Normalize dashboard info to ensure name contains route key, not full URL
     */
    private function normalizeDashboardInfo(?array $dashboardInfo): ?array
    {
        if (!$dashboardInfo) {
            return null;
        }

        // If route contains a full URL with route parameter, extract the route name
        if (isset($dashboardInfo['route']) && preg_match('/[?&]route=([^&]*)/', $dashboardInfo['route'], $matches)) {
            $dashboardInfo['name'] = $matches[1];
        }

        // Also check if name itself is a full URL and extract route
        if (isset($dashboardInfo['name']) && preg_match('/[?&]route=([^&]*)/', $dashboardInfo['name'], $matches)) {
            $dashboardInfo['name'] = $matches[1];
        }

        return $dashboardInfo;
    }

    /**
     * Build login response using file-based config (legacy fallback)
     */
    private function buildLoginResponseFromFiles(
        array $userData,
        ?string $primaryRole,
        ?int $primaryRoleId,
        array $roleIds,
        string $token
    ): array {
        // Generate sidebar menu items based on user's roles and permissions
        $dashboardManager = new \DashboardManager();
        $dashboardManager->setUser($userData);

        // Get filtered menu items for user's dashboard
        $sidebarItems = [];
        $defaultDashboard = null;
        $dashboardKey = null;

        // Get dashboard key using DashboardRouter (prefer role ID if available)
        if ($primaryRoleId) {
            $dashboardKey = \DashboardRouter::getDashboardForRole($primaryRoleId);

            // Try to get menu items using role ID as key
            $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
            $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
        } elseif ($primaryRole) {
            $dashboardKey = \DashboardRouter::getDashboardForRole($primaryRole);

            // Fall back to role name lookup if role ID wasn't provided
            if ($primaryRoleId) {
                $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
                $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
            }
        }

            // If no items for primary role, combine from all roles
            if (empty($sidebarItems) && !empty($roleIds)) {
                // Union menus from dashboards config
                $dashConfig = include __DIR__ . '/../../includes/dashboards.php';
                $menusUnion = [];
                $seen = [];

                foreach ($roleIds as $rid) {
                    $menus = $dashConfig[$rid]['menus'] ?? [];
                    foreach ($menus as $menu) {
                        $key = ($menu['label'] ?? '') . '|' . ($menu['url'] ?? '') . '|' . ($menu['icon'] ?? '');
                        if (!isset($seen[$key])) {
                            $menusUnion[] = $menu;
                            $seen[$key] = true;
                        }
                    }
                }

                if (empty($menusUnion)) {
                    $menusUnion = $dashConfig[2]['menus'] ?? [];
                }

                // Filter by permissions via DashboardManager
                $sidebarItems = $dashboardManager->filterMenuItems($menusUnion);

                // Choose default dashboard
                if (!empty($sidebarItems)) {
                    $first = $sidebarItems[0];
                    $defaultDashboard = [
                        'label' => $first['label'] ?? 'Dashboard',
                        'route' => $first['url'] ?? 'home',
                    ];
                    $dashboardKey = $first['url'] ?? 'home';
                }
            }

        error_log("Login (file-based): Role=$primaryRole (ID: $primaryRoleId), DashboardKey=$dashboardKey, MenuItems=" . count($sidebarItems));

        // If no sidebar items found, try to get first accessible dashboard
        if (empty($sidebarItems)) {
            $defaultDashboard = $dashboardManager->getDefaultDashboard();
            if ($defaultDashboard) {
                $sidebarItems = $defaultDashboard['menu_items'] ?? $defaultDashboard['menus'] ?? [];
            }
        }

        // Normalize user permissions and merge any role-specific defaults
        $userData = $this->normalizeUserPermissions($userData);
        if ($primaryRoleId === 10) {
            $userData['permissions'] = array_values(array_unique(array_merge($userData['permissions'] ?? [], $this->getDefaultRolePermissions(10))));
        }

        // Generate refresh token and set as HttpOnly cookie (do not return in body)
        $refreshToken = $this->generateRefreshToken($userData['id']);
        if ($refreshToken) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('refresh_token', $refreshToken, time() + (7 * 24 * 60 * 60), '/', '', $secure, true);
            header("Set-Cookie: refresh_token=$refreshToken; Path=/; Max-Age=" . (7 * 24 * 60 * 60) . "; HttpOnly; " . ($secure ? 'Secure; ' : '') . "SameSite=Lax");
        }

        // Determine dashboard details
        $dashboardKeyResolved = $dashboardKey ?? \DashboardRouter::getDashboardForRole($primaryRoleId ?? $primaryRole);

        // Normalize dashboard key to ensure it's a route name, not full URL
        if (preg_match('/[?&]route=([^&]*)/', $dashboardKeyResolved, $matches)) {
            $dashboardKeyResolved = $matches[1];
        }
        $dashboardLabel = (
            ($defaultDashboard['label'] ?? null) ? $defaultDashboard['label'] : (
                (($dbDash = $this->getConfigService()->getDashboardByName($dashboardKeyResolved)) && !empty($dbDash['display_name']))
                ? $dbDash['display_name']
                : ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $dashboardKeyResolved)))
            )
        );

        // Add dashboard as first menu item
        $dashboardMenuItem = [
            'id' => 'dashboard_' . $primaryRoleId,
            'label' => $dashboardLabel,
            'icon' => 'bi-house-door',
            'url' => '?route=' . $dashboardKeyResolved,
            'route_url' => '?route=' . $dashboardKeyResolved,
            'domain' => 'SCHOOL',
            'display_order' => -200,
            'subitems' => [],
            'show_badge' => false,
            'badge_source' => null,
            'badge_color' => 'danger',
            'open_in_new_tab' => false,
            'requires_confirmation' => false,
            'confirmation_message' => null,
            'css_class' => null,
            'tooltip' => null
        ];
        array_unshift($sidebarItems, $dashboardMenuItem);

        // Final normalization of dashboard key
        if (preg_match('/[?&]route=([^&]*)/', $dashboardKeyResolved, $matches)) {
            $dashboardKeyResolved = $matches[1];
        }

        return [
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'token_expires_in' => JWT_EXPIRY,
                'user' => $userData,
                'sidebar_items' => $this->normalizeSidebarItems($sidebarItems),
                'dashboard' => [
                    'key' => $dashboardKeyResolved,
                    'url' => '?route=' . $dashboardKeyResolved,
                    'label' => $dashboardLabel
                ],
                'config_source' => 'file'
            ]
        ];
    }

    // Generate JWT token
    private function generateToken($userData)
    {
        $issuedAt = time();
        $expire = $issuedAt + JWT_EXPIRY;

        $payload = array_merge(
            $userData,
            [
                'iat' => $issuedAt,
                'exp' => $expire,
                'iss' => JWT_ISSUER,
                'aud' => JWT_AUDIENCE
            ]
        );

        return JWT::encode($payload, JWT_SECRET, 'HS256');
    }

    // Generate refresh token (stored in DB, expires in 7 days)
    private function generateRefreshToken($userId)
    {
        $token = bin2hex(random_bytes(32)); // 64-char hex token
        $expiresAt = date('Y-m-d H:i:s', time() + (7 * 24 * 60 * 60)); // 7 days

        try {
            $stmt = $this->db->prepare('
                INSERT INTO refresh_tokens (user_id, token, expires_at) 
                VALUES (?, ?, ?)
            ');
            $stmt->execute([$userId, $token, $expiresAt]);
            return $token;
        } catch (\Exception $e) {
            error_log('Error generating refresh token: ' . $e->getMessage());
            return null;
        }
    }

    // Validate and exchange refresh token for new access token
    public function exchangeRefreshToken($data)
    {
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Refresh token is required'
            ];
        }

        try {
            // Find valid, non-revoked refresh token
            $stmt = $this->db->prepare('
                SELECT rt.user_id FROM refresh_tokens rt
                WHERE rt.token = ? 
                AND rt.expires_at > NOW()
                AND rt.revoked_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([$refreshToken]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Invalid or expired refresh token'
                ];
            }

            // Get user and generate new access token
            $userId = $result['user_id'];
            $userData = $this->usersApi->get($userId);

            if (!$userData) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Extract permission codes only
            $permissionCodes = [];
            if (!empty($userData['permissions'])) {
                foreach ($userData['permissions'] as $perm) {
                    $code = is_array($perm) ? ($perm['code'] ?? $perm['permission_code'] ?? null) : $perm;
                    if ($code) {
                        $permissionCodes[] = $code;
                    }
                }
            }

            // Generate new access token
            $newToken = $this->generateToken([
                'user_id' => $userData['id'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'roles' => $userData['roles'] ?? [],
                'display_name' => $userData['first_name'] . ' ' . $userData['last_name'],
                'permissions' => $permissionCodes
            ]);

            return [
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'refresh_token' => $refreshToken,
                    'token_expires_in' => JWT_EXPIRY
                ]
            ];
        } catch (\Exception $e) {
            error_log('Error exchanging refresh token: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Token refresh failed'
            ];
        }
    }

    // Revoke refresh token (logout)
    public function revokeRefreshToken($data)
    {
        $refreshToken = $data['refresh_token'] ?? null;

        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Refresh token is required'
            ];
        }

        try {
            $stmt = $this->db->prepare('
                UPDATE refresh_tokens 
                SET revoked_at = NOW()
                WHERE token = ? AND revoked_at IS NULL
            ');
            $stmt->execute([$refreshToken]);

            return [
                'success' => true,
                'message' => 'Refresh token revoked successfully'
            ];
        } catch (\Exception $e) {
            error_log('Error revoking refresh token: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Token revocation failed'
            ];
        }
    }

    // Send reset email
    private function sendResetEmail($email, $username, $resetLink)
    {
        $template = "Dear {{username}},\n\n"
            . "A password reset has been requested for your account.\n"
            . "Please click the link below to reset your password:\n\n"
            . "{{resetLink}}\n\n"
            . "This link will expire in 1 hour.\n"
            . "If you did not request this reset, please ignore this email.";

        $subject = 'Password Reset Request';
        $body = $this->parseTemplate($template, [
            'username' => $username,
            'resetLink' => $resetLink
        ]);
        return $this->communicationsApi->sendEmail(
            [$email],
            $subject,
            $body
        );
    }

    private function parseTemplate($template, $data)
    {
        $parsed = $template;
        foreach ($data as $key => $value) {
            $parsed = str_replace('{{' . $key . '}}', $value, $parsed);
        }
        return $parsed;
    }

    /**
     * Normalize user permissions payload to a flat list of permission codes
     */
    private function normalizeUserPermissions(array $userData): array
    {
        $perms = $userData['permissions'] ?? [];
        $codes = [];
        foreach ($perms as $p) {
            if (is_array($p)) {
                $codes[] = $p['code'] ?? $p['permission_code'] ?? null;
            } else {
                $codes[] = $p;
            }
        }
        $codes = array_values(array_filter(array_unique($codes)));
        $userData['permissions'] = $codes;
        return $userData;
    }

    /**
     * Return default role permissions for well-known roles
     */
    private function getDefaultRolePermissions(int $roleId): array
    {
        switch ($roleId) {
            case 10: // Accountant
                return [
                    'finance_view',
                    'manage_payments',
                    'bank_accounts_view',
                    'bank_transactions_view',
                    'mpesa_view',
                    'payroll_view',
                    'payslips_view',
                    'vendors_manage',
                    'purchase_orders_manage',
                    'finance_reports_view',
                    'fee_structure_manage'
                ];
            default:
                return [];
        }
    }

    /**
     * Normalize sidebar item URLs: convert 'home.php?route=xyz' to 'xyz' recursively
     */
    private function normalizeSidebarItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                $normalized[] = $item;
                continue;
            }
            $it = $item;
            $url = $it['url'] ?? null;
            if ($url && is_string($url)) {
                // handle full query string: home.php?route=...
                if (strpos($url, 'route=') !== false) {
                    $parts = parse_url($url);
                    if (isset($parts['query'])) {
                        parse_str($parts['query'], $qs);
                        if (!empty($qs['route'])) {
                            $it['url'] = $qs['route'];
                        }
                    } else {
                        // fallback: extract after 'route='
                        $pos = strpos($url, 'route=');
                        if ($pos !== false) {
                            $it['url'] = substr($url, $pos + strlen('route='));
                        }
                    }
                }

                // handle legacy file-based pages (e.g., /pages/bank_accounts.php or pages/bank_accounts.php)
                if (strpos($url, 'pages/') !== false) {
                    $base = basename($url); // e.g., bank_accounts.php
                    $base = preg_replace('/\.php$/i', '', $base);
                    if ($base) {
                        $it['url'] = $base;
                    }
                }
            }

            // Recursively normalize subitems
            if (!empty($it['subitems']) && is_array($it['subitems'])) {
                $it['subitems'] = $this->normalizeSidebarItems($it['subitems']);
            }

            $normalized[] = $it;
        }
        return $normalized;
    }
}
