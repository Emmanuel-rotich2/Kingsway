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

            // Also verify role_sidebar_menus has data
            $stmt = $this->db->query("SELECT COUNT(*) as cnt FROM role_sidebar_menus");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ($result['cnt'] ?? 0) > 0;
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
        // If not successful, return error
        return [
            'status' => 'error',
            'message' => $result['message'] ?? 'Login failed'
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
        $userPermissions = array_column($userData['permissions'] ?? [], 'code');

        try {
            // Build sidebar from database using MenuBuilderService
            if (count($roleIds) > 1) {
                // Multi-role: combine menus from all roles
                $sidebarItems = $this->getMenuBuilder()->buildSidebarForMultipleRoles(
                    $userId,
                    $roleIds,
                    $userPermissions
                );
            } else {
                // Single role: get menu for that role
                $sidebarItems = $this->getMenuBuilder()->buildSidebarForUser(
                    $userId,
                    $primaryRoleId ?? 0,
                    $userPermissions
                );
            }

            // Get default route for the role from database
            $defaultRoute = $this->getDefaultRouteForRole($primaryRoleId ?? 0);

            // Get dashboard info from database (simple, no widgets - frontend handles display)
            $dashboardInfo = $this->getConfigService()->getDashboardForRole($primaryRoleId ?? 0);

            // Generate refresh token
            $refreshToken = $this->generateRefreshToken($userId);

            return [
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'refresh_token' => $refreshToken,
                    'token_expires_in' => JWT_EXPIRY,
                    'user' => $userData,
                    'sidebar_items' => $sidebarItems,
                    'dashboard' => [
                        'key' => $dashboardInfo['name'] ?? 'home',
                        'url' => $defaultRoute,
                        'label' => $dashboardInfo['title'] ?? 'Dashboard'
                    ],
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

        // Get dashboard key using DashboardRouter
        if ($primaryRole) {
            $dashboardKey = \DashboardRouter::getDashboardForRole($primaryRole);

            // Try to get menu items using role ID as key
            if ($primaryRoleId) {
                $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
                $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
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
        }

        // If no sidebar items found, try to get first accessible dashboard
        if (empty($sidebarItems)) {
            $defaultDashboard = $dashboardManager->getDefaultDashboard();
            if ($defaultDashboard) {
                $sidebarItems = $defaultDashboard['menu_items'] ?? $defaultDashboard['menus'] ?? [];
            }
        }

        // Generate refresh token
        $refreshToken = $this->generateRefreshToken($userData['id']);

        return [
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'refresh_token' => $refreshToken,
                'token_expires_in' => JWT_EXPIRY,
                'user' => $userData,
                'sidebar_items' => $sidebarItems,
                'dashboard' => [
                    'key' => $dashboardKey ?? 'home',
                    'url' => $dashboardKey ?? 'home',
                    'label' => $defaultDashboard['label'] ?? ucwords(str_replace('_', ' ', $primaryRole ?? 'Dashboard'))
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
}
