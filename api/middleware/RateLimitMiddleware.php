<?php

namespace App\API\Middleware;

use App\Config\Database;
use PDO;

class RateLimitMiddleware
{
    // Rate limit: 100 requests per minute per IP
    const REQUESTS_LIMIT = 100;
    const TIME_WINDOW = 60; // seconds

    /**
     * Check rate limiting per IP address
     */
    public static function handle()
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
                [$ipAddress, $now - self::TIME_WINDOW]
            );

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $requestCount = $result['request_count'] ?? 0;

            if ($requestCount >= self::REQUESTS_LIMIT) {
                self::deny(429, 'Too many requests. Rate limit exceeded.');
            }

            // Log this request
            $db->query(
                "INSERT INTO rate_limit_logs (ip_address, request_time) VALUES (?, ?)",
                [$ipAddress, $now]
            );

        } catch (\Exception $e) {
            // Log but don't block on database error
            error_log("Rate limit check failed: " . $e->getMessage());
        }
    }

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        http_response_code($code);
        header('Retry-After: ' . self::TIME_WINDOW);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'code' => $code,
            'retry_after' => self::TIME_WINDOW
        ]);
        exit;
    }
}
