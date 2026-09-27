<?php

namespace App\API\Middleware;

class CORSMiddleware
{
    public static function handle()
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $configuredOrigins = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : ($_ENV['ALLOWED_ORIGINS'] ?? '');
        if (is_string($configuredOrigins)) {
            $configuredOrigins = preg_split('/\s*,\s*/', trim($configuredOrigins), -1, PREG_SPLIT_NO_EMPTY);
        }
        $allowedOrigins = is_array($configuredOrigins) ? array_values(array_filter(array_map('trim', $configuredOrigins))) : [];
        if (!$allowedOrigins && defined('BASE_URL')) {
            $parts = parse_url((string) BASE_URL);
            if (!empty($parts['scheme']) && !empty($parts['host'])) {
                $allowedOrigins[] = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
            }
        }
        if (!$allowedOrigins && $origin !== '') {
            // Development and reverse-proxy deployments can still use the
            // current configured browser origin when no allowlist is supplied.
            $allowedOrigins[] = $origin;
        }

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400");
        }

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}
