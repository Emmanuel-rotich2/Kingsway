<?php

namespace App\API\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    // Static test token for local/dev testing (header: X-Test-Token)
    const TEST_USER = [
        'user_id' => 2,
        'username' => 'accountant',
        'email' => 'accountant@school.com',
        'role_ids' => [10],
        'roles' => [['id' => 10, 'name' => 'School Accountant']],
        'display_name' => 'Test Accountant',
        'permissions' => ['*'],
        'effective_permissions' => ['*', 'finance.view', 'finance.manage', 'finance.reconcile', 'payments.view', 'payments.reconcile']
    ];
    /**
     * Handle JWT validation and attach user info to $_SERVER['auth_user']
     */
    public static function handle()
    {
        $path = strtolower($_SERVER['REQUEST_URI']);

        // Public endpoints that don't require JWT
        $publicEndpoints = [
            'auth/login',
            'auth/register',
            'auth/reset-password',
            'auth/complete-reset',
            'auth/verify-reset-token',
            'users/login',
            'users/register',
            // Payment webhook endpoints (should be public for bank/M-Pesa callbacks)
            'payments/index',
            'payments/mpesa-b2c-callback',
            'payments/mpesa-b2c-timeout',
            'payments/mpesa-c2b-confirmation',
            'payments/kcb-validation',
            'payments/kcb-transfer-callback',
            'payments/kcb-notification',
            'payments/bank-webhook',
            // Parent portal auth endpoints (use their own session tokens, not staff JWT)
            'parent-portal/login',
            'parent-portal/login-otp-request',
            'parent-portal/login-otp-verify',
        ];

        // Check if current request is to a public endpoint
        foreach ($publicEndpoints as $endpoint) {
            if (strpos($path, $endpoint) !== false) {
                return;
            }
        }

        // Parent portal routes bypass staff JWT auth entirely.
        // Authenticated parent-portal endpoints enforce auth via $this->parentId checks
        // in ParentPortalController.
        if (strpos($path, 'parent-portal/') !== false) {
            return;
        }

        // TEST MODE: Accept X-Test-Token header to inject a test user.
        // Only active on localhost/127.0.0.1 — disabled in production automatically.
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isLocalEnv = ($host === 'localhost' || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false);
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if ($isLocalEnv && isset($headers['X-Test-Token']) && $headers['X-Test-Token'] === 'devtest') {
            error_log('AuthMiddleware: DEV test token used from ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $_SERVER['auth_user'] = self::TEST_USER;
            return;
        }

        // Validate JWT token for protected endpoints
        self::validateJWT();
    }

    /**
     * Validate JWT token from Authorization header
     */
    private static function validateJWT()
    {
        // Try multiple methods to get the Authorization header
        // Apache/Nginx may strip it, so we need to check multiple sources
        $authHeader = null;

        // Method 1: getallheaders()
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            }
        }

        // Method 2: $_SERVER['HTTP_AUTHORIZATION']
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }

        // Method 3: Apache-specific header
        if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        // Method 4: Check PHP input headers directly
        if (!$authHeader) {
            foreach ($_SERVER as $key => $value) {
                if (strtolower($key) === 'http_authorization') {
                    $authHeader = $value;
                    break;
                }
            }
        }


        if (!$authHeader) {
            // Debug: Log what headers we actually received
            $receivedHeaders = function_exists('getallheaders') ? array_keys(getallheaders()) : [];
            $serverKeys = array_filter(array_keys($_SERVER), function ($key) {
                return strpos($key, 'HTTP_') === 0 || strpos($key, 'REDIRECT_') === 0;
            });

            error_log('AuthMiddleware: No Authorization header found');
            error_log('Received HTTP headers: ' . json_encode($receivedHeaders));
            error_log('SERVER keys: ' . json_encode(array_values($serverKeys)));

            self::deny(401, 'Missing Authorization header. Please ensure you are logged in and the token is being sent.');
        }

        error_log('AuthMiddleware: Authorization header found: ' . substr($authHeader, 0, 20) . '...');
        $token = str_replace('Bearer ', '', $authHeader);
        try {
            $decoded = JWT::decode(
                $token,
                new Key(JWT_SECRET, 'HS256')
            );

            // Attach user info to $_SERVER for later use
            $_SERVER['auth_user'] = self::normalizeDecodedUser((array) $decoded);

        } catch (\Exception $e) {
            self::deny(401, 'Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Normalize role data so downstream authorization code has stable helpers.
     */
    private static function normalizeDecodedUser(array $user): array
    {
        $roles = $user['roles'] ?? [];
        $roleIds = [];
        $roleNames = [];

        foreach ((array) $roles as $role) {
            if (is_array($role)) {
                if (isset($role['id'])) {
                    $roleIds[] = (int) $role['id'];
                } elseif (isset($role['role_id'])) {
                    $roleIds[] = (int) $role['role_id'];
                }

                if (!empty($role['name'])) {
                    $roleNames[] = strtolower((string) $role['name']);
                }
            } elseif (is_object($role)) {
                if (isset($role->id)) {
                    $roleIds[] = (int) $role->id;
                } elseif (isset($role->role_id)) {
                    $roleIds[] = (int) $role->role_id;
                }

                if (!empty($role->name)) {
                    $roleNames[] = strtolower((string) $role->name);
                }
            } elseif (is_numeric($role)) {
                $roleIds[] = (int) $role;
            } elseif (is_string($role)) {
                $roleNames[] = strtolower($role);
            }
        }

        $user['role_ids'] = array_values(array_unique($roleIds));
        $user['role_names'] = array_values(array_unique($roleNames));

        return $user;
    }

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $payload = json_encode([
            'status'  => 'error',
            'message' => $message,
            'code'    => $code,
        ]);
        echo $payload !== false
            ? $payload
            : '{"status":"error","message":"Internal error","code":500}';
        exit;
    }
}
