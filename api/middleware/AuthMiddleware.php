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
        $headers = getallheaders();
        if (!isset($headers['Authorization'])) {
            self::deny(401, 'Missing Authorization header');
        }

        $authHeader = $headers['Authorization'];
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
