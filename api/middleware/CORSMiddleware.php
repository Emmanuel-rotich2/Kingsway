<?php

namespace App\API\Middleware;

class CORSMiddleware
{
    /**
     * Handle CORS headers and preflight requests
     */
    public static function handle()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : [
            'http://localhost',
            'http://127.0.0.1',
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'http://localhost:8081',
            'http://127.0.0.1:8081'
        ];

        // Check if origin is allowed
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400");
        }

        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
