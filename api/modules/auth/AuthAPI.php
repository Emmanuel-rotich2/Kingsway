<?php
namespace App\API\Modules\auth;

use App\API\Includes\BaseAPI;
use App\API\Modules\users\UsersAPI;
use App\API\Modules\users\RoleManager;
use App\API\Modules\users\PermissionManager;
use App\API\Modules\users\UserRoleManager;
use App\API\Modules\users\UserPermissionManager;
use App\API\Modules\communications\CommunicationsAPI;
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

    public function __construct()
    {
        parent::__construct('auth');
        $this->usersApi = new UsersAPI();
        $this->roleManager = new RoleManager($this->db);
        $this->permissionManager = new PermissionManager($this->db);
        $this->userRoleManager = new UserRoleManager($this->db);
        $this->userPermissionManager = new UserPermissionManager($this->db);
        $this->communicationsApi = new CommunicationsAPI();
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

            // Generate sidebar menu items based on user's roles and permissions
            $dashboardManager = new \DashboardManager();
            $dashboardManager->setUser($userData);

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

            // Initialize dashboard manager
            $dashboardManager = new \DashboardManager();
            $dashboardManager->setUser($userData);

            // Get filtered menu items for user's dashboard
            $sidebarItems = [];
            $defaultDashboard = null;
            $dashboardKey = null;

            // Get dashboard key using DashboardRouter (returns dashboard file key like 'system_administrator_dashboard')
            if ($primaryRole) {
                $dashboardKey = \DashboardRouter::getDashboardForRole($primaryRole);

                // Try to get menu items using role ID as key (dashboards.php is keyed by role ID)
                if ($primaryRoleId) {
                    $sidebarItems = $dashboardManager->getMenuItems($primaryRoleId);
                    $defaultDashboard = $dashboardManager->getDashboard($primaryRoleId);
                } else {
                    // Fallback to normalized role name if no role ID
                    $normalizedRole = strtolower(str_replace(['/', ' ', '-'], '_', $primaryRole));
                    $sidebarItems = $dashboardManager->getMenuItems($normalizedRole);
                    $defaultDashboard = $dashboardManager->getDashboard($normalizedRole);
                }

                // Log for debugging
                error_log("Login: Role=$primaryRole (ID: $primaryRoleId), DashboardKey=$dashboardKey, MenuItems=" . count($sidebarItems));
            }

            // If no sidebar items found, try to get first accessible dashboard
            if (empty($sidebarItems)) {
                $defaultDashboard = $dashboardManager->getDefaultDashboard();
                if ($defaultDashboard) {
                    // Support both 'menu_items' and 'menus' keys
                    $sidebarItems = $defaultDashboard['menu_items'] ?? $defaultDashboard['menus'] ?? [];
                }
            }

            // Generate refresh token for token rotation
            $refreshToken = $this->generateRefreshToken($userData['id']);

            // Return comprehensive login response
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
                    ]
                ]
            ];
        }
        // If not successful, return error
        return [
            'status' => 'error',
            'message' => $result['message'] ?? 'Login failed'
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
