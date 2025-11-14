<?php
namespace App\API\Modules\auth;

use App\API\Includes\BaseAPI;
use App\API\Modules\communications\CommunicationsAPI;
use App\Config\Database;
use Firebase\JWT\JWT;
use PDO;
use Exception;
use RuntimeException;

class AuthAPI extends BaseAPI
{
    private $communicationsApi;

    public function __construct()
    {
        parent::__construct('auth');
        $this->communicationsApi = new CommunicationsAPI();
    }

    // Login user
    public function login($data)
    {
        try {
            $required = ['username', 'password'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return [
                    'status' => 'error',
                    'message' => 'Missing credentials',
                    'fields' => $missing
                ];
            }

            // Get user data with all roles
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    u.username,
                    u.email,
                    u.password,
                    u.status,
                    u.role_id,
                    r.name as role_name,
                    r.permissions as role_permissions,
                    GROUP_CONCAT(DISTINCT r2.name) as roles,
                    GROUP_CONCAT(DISTINCT r2.permissions) as role_permissions_all,
                    CASE 
                        WHEN s.id IS NOT NULL THEN CONCAT(s.first_name, ' ', s.last_name)
                        WHEN st.id IS NOT NULL THEN CONCAT(st.first_name, ' ', st.last_name)
                        ELSE u.username
                    END as display_name
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                LEFT JOIN user_roles ur ON ur.user_id = u.id
                LEFT JOIN roles r2 ON r2.id = ur.role_id
                LEFT JOIN staff s ON s.user_id = u.id
                LEFT JOIN students st ON st.user_id = u.id
                WHERE u.username = :username OR u.email = :email
                GROUP BY u.id
            ");
            $stmt->execute([
                'username' => $data['username'],
                'email' => $data['username']
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'status' => 'error',
                    'message' => 'Invalid username or password'
                ];
            }

            // Parse roles and permissions
            $roles = $user['roles'] ? explode(',', $user['roles']) : [];
            
            // Parse and merge permissions from all roles
            $permissions = [];
            if ($user['role_permissions']) {
                $rolePermissions = explode(',', $user['role_permissions']);
                foreach ($rolePermissions as $permSet) {
                    $perms = json_decode($permSet, true);
                    if (is_array($perms)) {
                        $permissions = array_merge($permissions, array_keys(array_filter($perms)));
                    }
                }
                $permissions = array_values(array_unique($permissions));
            }

            // Generate JWT token
            $token = $this->generateToken([
                'user_id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'roles' => $roles,
                'display_name' => $user['display_name'],
                'permissions' => $permissions
            ]);

            // Create auth session
            $stmt = $this->db->prepare("
                INSERT INTO auth_sessions (
                    user_id,
                    token,
                    ip_address,
                    user_agent,
                    expires_at
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['id'],
                $token,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                date('Y-m-d H:i:s', strtotime('+24 hours'))
            ]);

            // Update last login
            $stmt = $this->db->prepare("
                UPDATE users 
                SET last_login = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$user['id']]);

            // Log action
            $this->logAction('login', $user['id'], "User logged in: {$user['username']}");

            return [
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'roles' => $roles,
                        'display_name' => $user['display_name'],
                        'permissions' => $permissions
                    ]
                ]
            ];

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Reset password
    public function resetPassword($data)
    {
        try {
            if (empty($data['email'])) {
                return [
                    'status' => 'error',
                    'message' => 'Email is required'
                ];
            }

            $db = Database::getInstance()->getConnection();
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'status' => 'error',
                    'message' => 'Email not found'
                ];
            }

            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save reset token
            $stmt = $db->prepare("
                INSERT INTO password_resets (
                    email,
                    token,
                    expires_at
                ) VALUES (?, ?, ?)
            ");
            $stmt->execute([$data['email'], $token, $expiresAt]);

            // Get base URL from config
            $baseUrl = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
            
            // Generate reset link
            $resetLink = sprintf(
                '%s/reset_password.php?token=%s',
                $baseUrl,
                $token
            );

            // Send reset email
            $this->sendResetEmail($data['email'], $user['username'], $resetLink);

            $this->logAction('reset_request', $user['id'], "Password reset requested for: {$data['email']}");

            return [
                'status' => 'success',
                'message' => 'Password reset instructions sent to your email'
            ];

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Change password
    public function changePassword($data)
    {
        try {
            // Validate required fields
            $required = ['current_password', 'new_password', 'user_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return [
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ];
            }

            $db = Database::getInstance()->getConnection();
            
            // Get current user
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

                return [
                    'status' => 'error',
                    'message' => 'User not found'
                ];
            }

            // Verify current password
                return [
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ];
            }

            // Update password
            $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $data['user_id']]);

            $this->logAction('change_password', $data['user_id'], "Password changed successfully");

            return [
                'status' => 'success',
                'message' => 'Password changed successfully'
            ];

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Verify reset token
    public function verifyResetToken($token)
    {
        try {
            if (empty($token)) {
                return [
                    'status' => 'error',
                    'message' => 'Token is required'
                ];
            }

            $db = Database::getInstance()->getConnection();
            
            // Get reset request
            $stmt = $db->prepare("
                SELECT 
                    pr.*,
                    u.username,
                    u.email
                FROM password_resets pr
                JOIN users u ON pr.email = u.email
                WHERE pr.token = ?
                  AND pr.used = 0
                  AND pr.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ];
            }

            return [
                'status' => 'success',
                'data' => [
                    'username' => $reset['username'],
                    'email' => $reset['email']
                ]
            ];

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Complete password reset
    public function completeReset($data)
    {
        try {
            // Validate required fields
            $required = ['token', 'new_password'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return [
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ];
            }

            $db = Database::getInstance()->getConnection();
            
            // Get reset request
            $stmt = $db->prepare("
                SELECT 
                    pr.email,
                    u.id as user_id
                FROM password_resets pr
                JOIN users u ON pr.email = u.email
                WHERE pr.token = ?
                  AND pr.used = 0
                  AND pr.expires_at > NOW()
                FOR UPDATE
            ");
            $stmt->execute([$data['token']]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                return [
                    'status' => 'error',
                    'message' => 'Invalid or expired token'
                ];
            }

            // Update password
            $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $reset['user_id']]);

            // Mark token as used
            $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$data['token']]);

            $this->logAction('reset_complete', $reset['user_id'], "Password reset completed");

            return [
                'status' => 'success',
                'message' => 'Password has been reset successfully'
            ];

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }
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

        return $this->communicationsApi->sendNotification([
            'email' => $email,
            'subject' => 'Password Reset Request',
            'message' => $this->parseTemplate($template, [
                'username' => $username,
                'resetLink' => $resetLink
            ]),
            'send_email' => true,
            'send_sms' => false
        ]);
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
