<?php

namespace App\API\Middleware;

use App\Config\Database;
use PDO;

class DeviceMiddleware
{
    /**
     * Log device fingerprint, MAC, IP, User-Agent
     * Check if device is blacklisted
     */
    public static function handle()
    {
        // Only log device info if user is authenticated
        if (!isset($_SERVER['auth_user'])) {
            return;
        }

        $userId = $_SERVER['auth_user']['user_id'] ?? $_SERVER['auth_user']['sub'] ?? null;
        if (!$userId) {
            return;
        }

        // Generate device fingerprint from multiple attributes
        $deviceFingerprint = self::generateFingerprint();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';

        // Check if device is blacklisted
        if (self::isDeviceBlacklisted($userId, $deviceFingerprint)) {
            self::deny(403, 'Device access denied (blacklisted)');
        }

        // Log device activity
        self::logDeviceActivity($userId, $deviceFingerprint, $ipAddress, $userAgent, $acceptLanguage);
    }

    /**
     * Generate a device fingerprint hash from multiple sources
     */
    private static function generateFingerprint()
    {
        $fingerprintData = [
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
        ];

        return hash('sha256', implode('|', $fingerprintData));
    }

    /**
     * Check if device is blacklisted for this user
     */
    private static function isDeviceBlacklisted($userId, $fingerprint)
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->query(
                "SELECT id FROM device_blacklist WHERE user_id = ? AND device_fingerprint = ? AND is_active = 1",
                [$userId, $fingerprint]
            );

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return !empty($result);
        } catch (\Exception $e) {
            // Log but don't block on database error
            error_log("Device blacklist check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log device activity to database
     */
    private static function logDeviceActivity($userId, $fingerprint, $ipAddress, $userAgent, $acceptLanguage)
    {
        try {
            $db = Database::getInstance();
            $db->query(
                "INSERT INTO device_logs (user_id, device_fingerprint, ip_address, user_agent, accept_language, logged_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$userId, $fingerprint, $ipAddress, $userAgent, $acceptLanguage]
            );
        } catch (\Exception $e) {
            // Log but don't block on database error
            error_log("Device logging failed: " . $e->getMessage());
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
