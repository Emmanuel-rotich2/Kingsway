<?php

namespace App\API\Middleware;

use App\Database\Database;
use PDO;

class RateLimitMiddleware
{
    // Anonymous traffic remains conservative, authenticated users get a larger
    // per-user bucket so normal multi-tab work does not collide on one IP.
    const ANONYMOUS_REQUESTS_LIMIT = 120;
    const AUTHENTICATED_REQUESTS_LIMIT = 600;
    const REFRESH_REQUESTS_LIMIT = 60;
    const TIME_WINDOW = 60; // seconds

    /**
     * Check rate limiting by authenticated user when a bearer token is present,
     * otherwise by IP address. This middleware intentionally does not authorize
     * the JWT; the decoded payload is only used to choose a fair rate bucket.
     */
    public static function handle()
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateKey = self::resolveRateKey($ipAddress);
        $limit = self::resolveLimit($rateKey);
        $now = time();

        try {
            $db = Database::getInstance();

            // Clean old entries (older than time window)
            $db->query(
                "DELETE FROM rate_limit_logs WHERE request_time < ?",
                [$now - self::TIME_WINDOW]
            );

            // Count requests from this IP in current window
            $stmt = $db->query(
                "SELECT COUNT(*) as request_count FROM rate_limit_logs 
                 WHERE ip_address = ? AND request_time > ?",
                [$rateKey, $now - self::TIME_WINDOW]
            );

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $requestCount = $result['request_count'] ?? 0;

            if ($requestCount >= $limit) {
                self::deny(429, 'Too many requests. Rate limit exceeded.');
            }

            // Log this request
            $db->query(
                "INSERT INTO rate_limit_logs (ip_address, request_time) VALUES (?, ?)",
                [$rateKey, $now]
            );

        } catch (\Exception $e) {
            // Log but don't block on database error
            error_log("Rate limit check failed: " . $e->getMessage());
        }
    }

    private static function resolveLimit($rateKey)
    {
        if (self::isRefreshEndpoint()) {
            return self::REFRESH_REQUESTS_LIMIT;
        }

        return strpos($rateKey, 'user:') === 0
            ? self::AUTHENTICATED_REQUESTS_LIMIT
            : self::ANONYMOUS_REQUESTS_LIMIT;
    }

    private static function resolveRateKey($ipAddress)
    {
        $userId = self::bearerUserId();
        if ($userId > 0) {
            return 'user:' . $userId;
        }

        return 'ip:' . $ipAddress;
    }

    private static function bearerUserId()
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return 0;
        }

        $parts = explode('.', trim($matches[1]));
        if (count($parts) !== 3) {
            return 0;
        }

        $payload = self::base64UrlDecode($parts[1]);
        if ($payload === false) {
            return 0;
        }

        $claims = json_decode($payload, true);
        if (!is_array($claims)) {
            return 0;
        }

        foreach (['user_id', 'id', 'sub'] as $key) {
            if (isset($claims[$key]) && is_numeric($claims[$key])) {
                return (int) $claims[$key];
            }
        }

        return 0;
    }

    private static function base64UrlDecode($value)
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($value, '-_', '+/'));
    }

    private static function isRefreshEndpoint()
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/auth/refresh-token') !== false
            || strpos($path, '/auth/refresh-session') !== false;
    }

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        http_response_code($code);
        header('Retry-After: ' . self::TIME_WINDOW);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        $payload = json_encode([
            'status'      => 'error',
            'message'     => $message,
            'code'        => $code,
            'retry_after' => self::TIME_WINDOW,
        ]);
        echo $payload !== false
            ? $payload
            : '{"status":"error","message":"Internal error","code":500}';
        exit;
    }
}
