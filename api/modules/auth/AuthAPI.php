<?php
namespace App\API\Modules\auth;

use App\API\Includes\BaseAPI;
use App\API\Includes\ValidationHelper;
use App\API\Modules\users\UsersAPI;
use App\API\Modules\users\RoleManager;
use App\API\Modules\users\PermissionManager;
use App\API\Modules\users\UserRoleManager;
use App\API\Modules\users\UserPermissionManager;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Services\AuthSessionService;
use App\API\Services\SystemConfigService;
use App\Config\DashboardRouter;
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
    private AuthSessionService $authSessionService;

    // New database-driven services
    private ?SystemConfigService $configService = null;

    // Feature flag: use database-driven config (set to true when migration is complete)
    // TEMPORARILY DISABLED due to performance issues in buildLoginResponseFromDatabase
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
        $this->authSessionService = new AuthSessionService($this->db);

        // Check if database-driven config is available
        // DISABLED: $this->useDatabaseConfig = $this->checkDatabaseConfigAvailable();
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
     * Get SystemConfigService (lazy load)
     */
    private function getConfigService(): SystemConfigService
    {
        if ($this->configService === null) {
            $this->configService = SystemConfigService::getInstance();
        }
        return $this->configService;
    }
    // Logout user through the same canonical refresh/session revocation path.
    public function logout($data)
    {
        return $this->revokeRefreshToken($data);
    }

    public function forgotPassword($data)
    {
        $identifier = trim($data['email'] ?? '');
        if ($identifier === '') {
            return [
                'success' => false,
                'message' => 'Email is required.'
            ];
        }

        $message = 'If an account exists for that email, password reset instructions have been sent.';

        try {
            $stmt = $this->db->prepare('
                SELECT id, email, username, first_name, last_name
                FROM users
                WHERE email = ? OR username = ?
                LIMIT 1
            ');
            $stmt->execute([$identifier, $identifier]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                return [
                    'success' => true,
                    'message' => $message
                ];
            }

            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = $this->hashResetToken($rawToken);

            $this->db->beginTransaction();

            $stmt = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0');
            $stmt->execute([$user['email']]);

            $stmt = $this->db->prepare('
                INSERT INTO password_resets (email, token, created_at, expires_at, used)
                VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
            ');
            $stmt->execute([$user['email'], $tokenHash]);

            $this->db->commit();

            $displayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            $resetLink = $this->generateResetLink($rawToken);

            try {
                $this->sendResetEmail($user['email'], $displayName ?: ($user['username'] ?? $user['email']), $resetLink);
            } catch (\Throwable $e) {
                error_log('Password reset email failed: ' . $e->getMessage());
            }
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Forgot password failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => $message
        ];
    }

    public function verifyResetToken($data)
    {
        $token = trim($data['token'] ?? '');
        if ($token === '') {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset link.'
            ];
        }

        $stmt = $this->db->prepare('
            SELECT id
            FROM password_resets
            WHERE token = ? AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ');
        $stmt->execute([$this->hashResetToken($token)]);

        if (!$stmt->fetch(\PDO::FETCH_ASSOC)) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset link.'
            ];
        }

        return [
            'success' => true,
            'message' => 'Reset link is valid.'
        ];
    }

    public function resetPassword($data)
    {
        $token = trim($data['token'] ?? '');
        $newPassword = $data['new_password'] ?? $data['password'] ?? null;

        if ($token === '' || !$newPassword) {
            return [
                'success' => false,
                'message' => 'Token and new password are required.'
            ];
        }

        $passwordValidation = ValidationHelper::validatePassword($newPassword);
        if (!$passwordValidation['valid']) {
            return [
                'success' => false,
                'message' => $passwordValidation['error']
            ];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare('
                SELECT id, email
                FROM password_resets
                WHERE token = ? AND used = 0 AND expires_at > NOW()
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute([$this->hashResetToken($token)]);
            $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$reset) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset link.'
                ];
            }

            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$reset['email']]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset link.'
                ];
            }

            $stmt = $this->db->prepare('
                UPDATE users
                SET password = ?, password_changed_at = NOW(), updated_at = NOW(), force_password_change = 0
                WHERE id = ?
            ');
            $stmt->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                $user['id']
            ]);

            $stmt = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
            $stmt->execute([$reset['id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Password has been reset successfully.'
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Reset password failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password reset failed. Please try again.'
            ];
        }
    }

    private function hashResetToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function generateResetLink(string $token): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
        $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;

        return $scheme . '://' . $host . $appBase . '/reset_password.php?token=' . urlencode($token);
    }

    // Login user
    public function login($data)
    {
        // UsersAPI owns credential verification and telemetry. AuthAPI remains
        // the single token/session issuer for the canonical /auth/login path.
        $result = $this->usersApi->login($data, false);
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
                    $token,
                    filter_var(
                        $data['remember_me'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    )
                );
            } else {
                $loginData = $this->buildLoginResponseFromFiles(
                    $userData,
                    $primaryRole,
                    $primaryRoleId,
                    $roleIds,
                    $token,
                    filter_var($data['remember_me'] ?? false, FILTER_VALIDATE_BOOLEAN)
                );
            }

            try {
                if (!empty($userData['force_password_change'])) {
                    $setupToken = $this->createPasswordSetupInvitation((int)$userData['id']);
                    $loginData['data']['password_setup_required'] = true;
                    $loginData['data']['password_setup_url'] = $this->passwordSetupUrl($setupToken);
                    $loginData['data']['dashboard'] = [
                        'key' => 'reset_default_password',
                        'url' => $loginData['data']['password_setup_url'],
                        'label' => 'Create Password',
                    ];
                } elseif ($this->staffProfileCompletionRequired((int)$userData['id'])) {
                    $loginData['data']['profile_completion_required'] = true;
                    $loginData['data']['dashboard'] = [
                        'key' => 'complete_staff_profile',
                        'url' => 'complete_staff_profile',
                        'label' => 'Complete Staff Profile',
                    ];
                }

                return $this->attachTrackedSession(
                    $loginData,
                    (int) $userData['id'],
                    $token
                );
            } catch (\Throwable $error) {
                error_log(
                    'Authenticated session creation failed: ' .
                    $error->getMessage()
                );
                $issuedRefreshToken = (string) (
                    $loginData['data']['refresh_token'] ?? ''
                );
                if ($issuedRefreshToken !== '') {
                    try {
                        $this->authSessionService->revokeByRefreshToken(
                            $issuedRefreshToken
                        );
                    } catch (\Throwable $cleanupError) {
                        error_log(
                            'Failed to clean up refresh token after session ' .
                            'creation error: ' .
                            $cleanupError->getMessage()
                        );
                    }
                }
                return [
                    'status' => 'error',
                    'message' => 'The authenticated session could not be established',
                    'data' => null,
                ];
            }
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
        string $token,
        bool $rememberMe = false,
        ?string $existingRefreshToken = null
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
            error_log("DEBUG: Fetched permissions for user $userId: " . count($userData['permissions']) . " items");
            error_log("DEBUG: First permission: " . json_encode($userData['permissions'][0] ?? 'EMPTY'));
        }

        $userRoles = $userData['roles'] ?? [];
        $primaryRole = null;
        if (!empty($userRoles)) {
            $firstRole = $userRoles[0];
            if (is_array($firstRole)) {
                $primaryRoleId = $primaryRoleId
                    ?? (isset($firstRole['id'])
                        ? (int) $firstRole['id']
                        : (isset($firstRole['role_id'])
                            ? (int) $firstRole['role_id']
                            : null));
                $primaryRole = $firstRole['name'] ?? null;
            } elseif (is_string($firstRole)) {
                $primaryRole = $firstRole;
            } elseif (is_numeric($firstRole)) {
                $primaryRoleId = $primaryRoleId ?? (int) $firstRole;
            }
        }
        if (empty($roleIds)) {
            foreach ($userRoles as $role) {
                $roleId = is_array($role)
                    ? ($role['id'] ?? $role['role_id'] ?? null)
                    : (is_numeric($role) ? $role : null);
                if ($roleId) {
                    $roleIds[] = (int) $roleId;
                }
            }
            $roleIds = array_values(array_unique($roleIds));
        }

        // Per-item delegation tables have been retired. Keep the response
        // contract explicit without consulting a second permission source.
        $delegatedPermissions = [];

        // Extract permission codes - handle both 'code' and 'permission_code' field names
        $userPermissions = [];
        foreach ($userData['permissions'] ?? [] as $perm) {
            if (is_array($perm)) {
                $code = $perm['code'] ?? $perm['permission_code'] ?? null;
                if ($code) {
                    $userPermissions[] = $code;
                }
            } else {
                $userPermissions[] = $perm;
            }
        }
        $userPermissions = array_values(array_filter(array_unique($userPermissions)));
        error_log("DEBUG: userPermissions extracted: " . count($userPermissions) . " items");

        // NOTE: Per-item menu delegation (the legacy `role_delegations_items` /
        // `user_delegations_items` tables) has been retired. Those tables no
        // longer exist in the schema, so role-level menu visibility is driven
        // solely by config/role_sidebars.php (the single source of truth).
        // Effective permissions below are the base user/role grants only.

        // Effective permissions for filtering the sidebar
        $effectivePermissions = array_values(array_unique($userPermissions));

        // Single source of truth: sidebar items come from config/role_sidebars.php.
        // (Legacy DB-menu path via MenuBuilderService was retired: it queried the
        // deprecated sidebar_menu_configs / role_delegations_items tables which are
        // no longer part of the schema, and duplicated role_sidebars.php coverage.)
        try {
            $sidebarItems = $this->getHardcodedSidebarItems($primaryRoleId ?? 0);
            if ($sidebarItems === null) {
                // Fallback only if a role has no entry in role_sidebars.php
                $sidebarItems = [];
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
                        $dashboardKey = DashboardRouter::getDashboardForRole($roleForDashboard);
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
                $defaultRoute = $defaultRoute ?? DashboardRouter::getDefaultDashboard();
                $dashboardInfo = [
                    'name' => $defaultRoute,
                    'display_name' => ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $defaultRoute)))
                ];
            }

            // Ensure defaultRoute is set (route name/key)
            $defaultRoute = $dashboardInfo['name'] ?? $defaultRoute ?? DashboardRouter::getDefaultDashboard();

            // Normalize user permissions to codes and merge delegated permissions
            $userData = $this->normalizeUserPermissions($userData);
            $userData['permissions'] = array_values(array_unique(array_merge($userData['permissions'] ?? [], $delegatedPermissions)));

            // Create a refresh token only for a new login. A refresh exchange
            // reuses its existing token so one browser session remains one
            // auth_sessions record instead of spawning a new row per refresh.
            $maxAge = $rememberMe ? (30 * 24 * 60 * 60) : 0;
            $refreshToken = $existingRefreshToken
                ?? $this->generateRefreshToken(
                    $userId,
                    $maxAge > 0
                        ? $maxAge
                        : (7 * 24 * 60 * 60)
                );
            if ($refreshToken && $existingRefreshToken === null) {
                $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                $expires = $rememberMe ? (time() + $maxAge) : 0;
                setcookie('refresh_token', $refreshToken, $expires, '/', '', $secure, true);
                $cookieParts = [
                    "refresh_token=$refreshToken",
                    'Path=/',
                    'HttpOnly',
                    'SameSite=Lax'
                ];
                if ($maxAge > 0) {
                    $cookieParts[] = "Max-Age=$maxAge";
                }
                if ($secure) {
                    $cookieParts[] = 'Secure';
                }
                header('Set-Cookie: ' . implode('; ', $cookieParts));
            }

            // Determine the role to resolve dashboard for (prefer primary role id)
            $roleForDashboard = $primaryRoleId ?? ($primaryRole ?? (!empty($userRoles) ? $userRoles[0] : null));
            if ($roleForDashboard !== null) {
                $resolvedKey = $dashboardInfo['name'] ?? DashboardRouter::getDashboardForRole($roleForDashboard);
            } else {
                $resolvedKey = $dashboardInfo['name'] ?? DashboardRouter::getDefaultDashboard();
            }

            // Normalize resolvedKey to ensure it's a route name, not full URL
            if (preg_match('/[?&]route=([^&]*)/', $resolvedKey, $matches)) {
                $resolvedKey = $matches[1];
            }

            $resolvedUrl = $dashboardInfo['route'] ?? ('?route=' . $resolvedKey);
            $resolvedLabel = $dashboardInfo['display_name'] ?? ($dashboardInfo['title'] ?? ucwords(str_replace('_', ' ', str_replace('_dashboard', '', $resolvedKey))));

            // If we fell back to system default and role is known, try to find a role-specific dashboard by name
            if ($resolvedKey === DashboardRouter::getDefaultDashboard() && $roleForDashboard !== null) {
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

            // Final normalization of dashboard key
            if (preg_match('/[?&]route=([^&]*)/', $resolvedKey, $matches)) {
                $resolvedKey = $matches[1];
            }

            // Only prepend a dashboard item if no sidebar item already links to this route.
            // This prevents duplicate "Dashboard" / "Director Dashboard" entries when the
            // DB sidebar menus already contain a home/dashboard link for this role.
            if (!$this->sidebarAlreadyHasRoute($sidebarItems, $resolvedKey)) {
                $dashboardMenuItem = [
                    'id' => 'dashboard_' . $primaryRoleId,
                    'label' => $resolvedLabel,
                    'icon' => 'bi-house-door',
                    'url' => $resolvedKey,
                    'route_url' => $resolvedKey,
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
            }

            return [
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'refresh_token' => $refreshToken,
                    'token_expires_in' => JWT_EXPIRY,
                    'remember_me' => $rememberMe,
                    'user' => $userData,
                    'sidebar_items' => $this->normalizeSidebarItems($sidebarItems),
                    'dashboard' => [
                        'key' => $resolvedKey,
                        'url' => $resolvedKey,
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
                $token,
                $rememberMe,
                $existingRefreshToken
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
        string $token,
        bool $rememberMe = false,
        ?string $existingRefreshToken = null
    ): array {
        // Generate sidebar menu items based on user's roles and permissions
        $dashboardManager = new \DashboardManager();
        $dashboardManager->setUser($userData);

        // Get filtered menu items for user's dashboard
        $sidebarItems = [];
        $defaultDashboard = null;
        $dashboardKey = null;

        // Fast path: use hardcoded sidebar if defined for this role (no DB queries needed)
        $hardcodedSidebar = $this->getHardcodedSidebarItems($primaryRoleId ?? 0);
        if ($hardcodedSidebar !== null) {
            $sidebarItems = $hardcodedSidebar;
        }

        // Get dashboard key using DashboardRouter (prefer role ID if available)
        if ($primaryRoleId) {
            $dashboardKey = DashboardRouter::getDashboardForRole($primaryRoleId);

            if ($hardcodedSidebar === null) {
                // Only query DB menu items when no hardcoded sidebar exists
                $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
            }
            $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
        } elseif ($primaryRole) {
            $dashboardKey = DashboardRouter::getDashboardForRole($primaryRole);

            // Fall back to role name lookup if role ID wasn't provided
            if ($hardcodedSidebar === null && $primaryRoleId) {
                $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
                $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
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

        // Normalize user permissions (effective permissions come from DB / stored procedure only)
        $userData = $this->normalizeUserPermissions($userData);

        // Create only on login; refresh exchanges retain the current token.
        $maxAge = $rememberMe ? (30 * 24 * 60 * 60) : 0;
        $refreshToken = $existingRefreshToken
            ?? $this->generateRefreshToken(
                $userData['id'],
                $maxAge > 0
                    ? $maxAge
                    : (7 * 24 * 60 * 60)
            );
        if ($refreshToken && $existingRefreshToken === null) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $expires = $rememberMe ? (time() + $maxAge) : 0;
            setcookie('refresh_token', $refreshToken, $expires, '/', '', $secure, true);
            $cookieParts = [
                "refresh_token=$refreshToken",
                'Path=/',
                'HttpOnly',
                'SameSite=Lax'
            ];
            if ($maxAge > 0) {
                $cookieParts[] = "Max-Age=$maxAge";
            }
            if ($secure) {
                $cookieParts[] = 'Secure';
            }
            header('Set-Cookie: ' . implode('; ', $cookieParts));
        }

        // Determine dashboard details
        $dashboardKeyResolved = $dashboardKey ?? DashboardRouter::getDashboardForRole($primaryRoleId ?? $primaryRole);

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

        // Final normalization of dashboard key
        if (preg_match('/[?&]route=([^&]*)/', $dashboardKeyResolved, $matches)) {
            $dashboardKeyResolved = $matches[1];
        }

        // Only prepend a dashboard item if no sidebar item already links to this route.
        if (!$this->sidebarAlreadyHasRoute($sidebarItems, $dashboardKeyResolved)) {
            $dashboardMenuItem = [
                'id' => 'dashboard_' . $primaryRoleId,
                'label' => $dashboardLabel,
                'icon' => 'bi-house-door',
                'url' => $dashboardKeyResolved,
                'route_url' => $dashboardKeyResolved,
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
        }

        return [
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'token_expires_in' => JWT_EXPIRY,
                'remember_me' => $rememberMe,
                'user' => $userData,
                'sidebar_items' => $this->normalizeSidebarItems($sidebarItems),
                'dashboard' => [
                    'key' => $dashboardKeyResolved,
                    'url' => $dashboardKeyResolved,
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
    private function generateRefreshToken($userId, int $ttlSeconds = 604800)
    {
        $token = bin2hex(random_bytes(32)); // 64-char hex token
        $expiresAt = date('Y-m-d H:i:s', time() + $ttlSeconds);

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
        $refreshToken = $data['refresh_token'] ?? ($_COOKIE['refresh_token'] ?? null);

        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Refresh token is required'
            ];
        }

        try {
            // Find valid, non-revoked refresh token
            $stmt = $this->db->prepare('
                SELECT rt.id, rt.user_id, rt.expires_at
                FROM refresh_tokens rt
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
            $userLookup = $this->usersApi->get($userId);
            // UsersAPI::get() returns a {success, data} envelope; unwrap it.
            $userData = ($userLookup['success'] ?? false) ? $userLookup['data'] : null;

            if (!$userData || empty($userData['id'])) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            $rolesResult = $this->userRoleManager->getUserRoles($userId);
            $permissionsResult = $this->userPermissionManager
                ->getEffectivePermissions($userId);
            $userData['roles'] = $rolesResult['data'] ?? [];
            $userData['permissions'] =
                $permissionsResult['data'] ?? [];

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

            // Reuse the same proven envelope builder as login so the SPA can
            // rehydrate a full session (user_data / permissions / roles /
            // sidebar / dashboard) from the refresh cookie alone. This is what
            // lets a fresh window / incognito / cleared-cache survive without
            // an immediate logout — web-storage is empty, but the refresh
            // cookie is still valid, so one refresh restores everything.
            $roleIds = [];
            $primaryRole = null;
            foreach ($userData['roles'] ?? [] as $r) {
                $roleId = is_array($r)
                    ? ($r['id'] ?? $r['role_id'] ?? null)
                    : (is_numeric($r) ? $r : null);
                if ($roleId !== null && (int) $roleId > 0) {
                    $roleIds[] = (int) $roleId;
                }
                if ($primaryRole === null && is_array($r)) {
                    $primaryRole = $r['name'] ?? null;
                } elseif (
                    $primaryRole === null &&
                    is_string($r) &&
                    !is_numeric($r)
                ) {
                    $primaryRole = $r;
                }
            }
            $roleIds = array_values(array_unique($roleIds));
            $primaryRoleId = $roleIds[0] ?? null;

            if ($this->useDatabaseConfig) {
                $loginData = $this->buildLoginResponseFromDatabase(
                    $userData,
                    $primaryRoleId,
                    $roleIds,
                    $newToken,
                    false,
                    $refreshToken
                );
            } else {
                $loginData = $this->buildLoginResponseFromFiles(
                    $userData,
                    $primaryRole,
                    $primaryRoleId,
                    $roleIds,
                    $newToken,
                    false,
                    $refreshToken
                );
            }

            return $this->attachTrackedSession(
                $loginData,
                (int) $userData['id'],
                $newToken,
                $result
            );
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
        $refreshToken = $data['refresh_token'] ?? ($_COOKIE['refresh_token'] ?? null);

        if (!$refreshToken) {
            return [
                'success' => false,
                'message' => 'Refresh token is required'
            ];
        }

        try {
            $this->authSessionService->revokeByRefreshToken(
                (string) $refreshToken
            );

            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('refresh_token', '', time() - 3600, '/', '', $secure, true);
            header("Set-Cookie: refresh_token=deleted; Path=/; Max-Age=0; HttpOnly; " . ($secure ? 'Secure; ' : '') . "SameSite=Lax");

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

    /**
     * Attach the access token to the existing canonical refresh session.
     */
    private function attachTrackedSession(
        array $loginData,
        int $userId,
        string $accessToken,
        ?array $refreshRecord = null
    ): array {
        if (
            ($loginData['status'] ?? '') !== 'success' ||
            !isset($loginData['data']) ||
            !is_array($loginData['data'])
        ) {
            return $loginData;
        }

        $refreshToken = (string) (
            $loginData['data']['refresh_token'] ?? ''
        );
        if ($refreshRecord === null && $refreshToken !== '') {
            $stmt = $this->db->prepare(
                'SELECT id, user_id, expires_at
                 FROM refresh_tokens
                 WHERE token = ?
                   AND user_id = ?
                   AND revoked_at IS NULL
                 LIMIT 1'
            );
            $stmt->execute([$refreshToken, $userId]);
            $resolved = $stmt->fetch(\PDO::FETCH_ASSOC);
            $refreshRecord = $resolved ?: null;
        }

        $refreshTokenId = isset($refreshRecord['id'])
            ? (int) $refreshRecord['id']
            : null;
        $expiresAt = (string) (
            $refreshRecord['expires_at']
                ?? date('Y-m-d H:i:s', time() + JWT_EXPIRY)
        );

        $sessionId = $this->authSessionService->upsertAccessSession(
            $userId,
            $accessToken,
            $refreshTokenId,
            $expiresAt
        );
        $loginData['data']['session_id'] = $sessionId;

        return $loginData;
    }

    private function createPasswordSetupInvitation(int $userId): string
    {
        $stmt = $this->db->prepare('
            SELECT u.email, s.id AS staff_id
            FROM users u
            LEFT JOIN staff s ON s.user_id = u.id
            WHERE u.id = ?
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || empty($row['email'])) {
            throw new \RuntimeException('User email is required for password setup.');
        }

        $this->db->prepare("
            UPDATE user_invitations
            SET status = 'revoked', revoked_at = NOW(), updated_at = NOW()
            WHERE user_id = ? AND status = 'pending'
        ")->execute([$userId]);

        $token = bin2hex(random_bytes(32));
        $this->db->prepare("
            INSERT INTO user_invitations
                (user_id, staff_id, email, token_hash, status, expires_at, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 72 HOUR), ?, NOW(), NOW())
        ")->execute([
            $userId,
            $row['staff_id'] ? (int)$row['staff_id'] : null,
            strtolower($row['email']),
            hash('sha256', $token),
            $userId,
        ]);

        return $token;
    }

    private function passwordSetupUrl(string $token): string
    {
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $appBase = preg_replace('#/api$#', '', rtrim($scriptDir, '/'));
            $appBase = ($appBase === '/' || $appBase === '.') ? '' : $appBase;
            $baseUrl = $scheme . '://' . $host . $appBase;
        }

        return $baseUrl . '/reset_default_password.php?token=' . rawurlencode($token);
    }

    private function staffProfileCompletionRequired(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare('
                SELECT
                    COALESCE(sop.profile_completed, 0) AS profile_completed,
                    COALESCE(sop.communication_completed, 0) AS communication_completed
                FROM staff s
                LEFT JOIN staff_onboarding_progress sop ON sop.staff_id = s.id
                WHERE s.user_id = ?
                LIMIT 1
            ');
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return false;
            }

            return (int)$row['profile_completed'] !== 1
                || (int)$row['communication_completed'] !== 1;
        } catch (\Throwable $error) {
            error_log('Staff profile completion check failed: ' . $error->getMessage());
            return false;
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

    public function resetDefaultPassword($data)
    {
        $token = trim($data['token'] ?? '');
        $newPassword = $data['new_password'] ?? $data['password'] ?? null;

        if ($token === '' || !$newPassword) {
            return [
                'success' => false,
                'message' => 'Token and new password are required.'
            ];
        }

        $passwordValidation = ValidationHelper::validatePassword($newPassword);
        if (!$passwordValidation['valid']) {
            return [
                'success' => false,
                'message' => $passwordValidation['error']
            ];
        }

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare('
                SELECT id, user_id
                FROM user_invitations
                WHERE token_hash = ?
                  AND status = "pending"
                  AND expires_at > NOW()
                LIMIT 1
                FOR UPDATE
            ');
            $stmt->execute([hash('sha256', $token)]);
            $invitation = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$invitation) {
                $this->db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Invalid or expired setup link.'
                ];
            }

            $stmt = $this->db->prepare('
                UPDATE users
                SET password = ?,
                    status = "active",
                    password_changed_at = NOW(),
                    force_password_change = 0,
                    updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                (int) $invitation['user_id']
            ]);

            $stmt = $this->db->prepare('
                UPDATE staff_onboarding_progress
                SET password_completed = 1, updated_at = NOW()
                WHERE user_id = ?
            ');
            $stmt->execute([(int) $invitation['user_id']]);

            $stmt = $this->db->prepare('
                UPDATE user_invitations
                SET status = "accepted", accepted_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([(int) $invitation['id']]);

            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Password has been updated. You may now sign in.'
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Default password reset failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Password setup failed. Please request a new setup link.'
            ];
        }
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
     * Check if the sidebar already contains a top-level item pointing to a given route name.
     * Used to avoid duplicating the dashboard entry when DB sidebar menus already include it.
     */
    private function sidebarAlreadyHasRoute(array $items, string $routeName): bool
    {
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = $item['url'] ?? '';
            // Normalize to bare route name for comparison
            if (strpos($url, 'route=') !== false) {
                $pos = strpos($url, 'route=');
                $url = substr($url, $pos + strlen('route='));
                $url = strtok($url, '&');
            }
            if ($url === $routeName) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return the file-driven sidebar for the given role.
     *
     * Delegates to SidebarConfigReader (the single source of truth), which
     * reads config/role_sidebars.php and normalises the shape used everywhere.
     * Returns [] when the role has no entry, so callers can treat a missing
     * config as "no menu" rather than a hard failure.
     */
    private function getHardcodedSidebarItems(int $roleId): ?array
    {
        if ($roleId <= 0) {
            return [];
        }
        $items = \App\API\Services\SidebarConfigReader::forRole($roleId);
        return $items;
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
