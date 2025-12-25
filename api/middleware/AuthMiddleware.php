<?php

namespace App\API\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    // Static test token for local/dev testing (header: X-Test-Token)
    const TEST_USER = [
        'user_id' => 1,
        'username' => 'testuser',
        'email' => 'test@example.com',
        'roles' => ['admin'],
        'display_name' => 'Test User',
        'permissions' => ['*']
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
        ];

        // Check if current request is to a public endpoint
        foreach ($publicEndpoints as $endpoint) {
            if (strpos($path, $endpoint) !== false) {
                return;
            }
        }

        // TEST MODE: Accept X-Test-Token header to inject test user
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        if (isset($headers['X-Test-Token']) && $headers['X-Test-Token'] === 'devtest') {
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
            $_SERVER['auth_user'] = (array) $decoded;

        } catch (\Exception $e) {
            self::deny(401, 'Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ]);
        exit;
    }
}
