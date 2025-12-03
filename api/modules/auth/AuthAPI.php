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
            $token = $this->generateToken([
                'user_id' => $result['data']['id'],
                'username' => $result['data']['username'],
                'email' => $result['data']['email'],
                'roles' => $result['data']['roles'] ?? [],
                'display_name' => $result['data']['first_name'] . ' ' . $result['data']['last_name'],
                'permissions' => $result['data']['permissions'] ?? []
            ]);
            // Optionally: create session, log, etc.
            return [
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user' => $result['data']
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
        return $this->communicationsApi->sendResetEmail(
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
