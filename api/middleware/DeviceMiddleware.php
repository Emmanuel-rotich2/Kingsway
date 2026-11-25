<?php

namespace App\API\Middleware;


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

        // Generate device fingerprint from multiple attributes.
        // Deliberately excludes REMOTE_ADDR: client IPs rotate (DHCP, mobile
        // networks, VPNs), so including it would give the same physical
        // device a new fingerprint on every address change and defeat the
        // purpose of fingerprinting. The persistent X-Device-Id sent by the
        // SPA (localStorage-backed) is the stable anchor; UA/platform/
        // language/timezone are corroborating signals only.
        $deviceFingerprint = self::generateFingerprint();
        $deviceId = (string) ($_SERVER['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? '');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown';

        // Check if device is blacklisted
        if (self::isDeviceBlacklisted($userId, $deviceFingerprint)) {
            self::deny(403, 'Device access denied (blacklisted)');
        }

        // Log device activity
        self::logDeviceActivity($userId, $deviceFingerprint, $deviceId, $ipAddress, $userAgent, $acceptLanguage);
    }

    /**
     * Generate a device fingerprint hash from multiple sources
     */
    private static function generateFingerprint()
    {
        $fingerprintData = [
            $_SERVER['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? 'no-device-id',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'unknown',
            $_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? $_SERVER['HTTP_X_DEVICE_RESOLUTION'] ?? 'unknown',
            $_SERVER['HTTP_X_DEVICE_TIMEZONE'] ?? date('P'),
            self::parsePlatform($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ];

        return hash('sha256', implode('|', $fingerprintData));
    }

    private static function parsePlatform(string $userAgent): string
    {
        if (stripos($userAgent, 'Windows') !== false) return 'Windows';
        if (stripos($userAgent, 'Mac') !== false) return 'macOS';
        if (stripos($userAgent, 'Linux') !== false) return 'Linux';
        if (stripos($userAgent, 'Android') !== false) return 'Android';
        if (stripos($userAgent, 'iOS') !== false || stripos($userAgent, 'iPhone') !== false) return 'iOS';
        return 'unknown';
    }

    /**
     * Check if the request User-Agent matches a blocked device pattern
     */
    private static function isDeviceBlacklisted($userId, $fingerprint)
    {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if ($userAgent === '') {
                return false;
            }

            return \App\API\Services\DeviceBlocklistService::isUserAgentBlocked($userAgent);
        } catch (\Exception $e) {
            // Log but don't block on database error
            \App\API\Services\Logger::legacyError("Device blacklist check failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log device activity to audit trail
     */
    private static function logDeviceActivity($userId, $fingerprint, $deviceId, $ipAddress, $userAgent, $acceptLanguage)
    {
        try {
            \App\API\Includes\FileLogger::write('device', [
                'type' => 'device_login',
                'action' => 'device_login',
                'entity' => 'device',
                'entity_id' => (int) $userId,
                'user_id' => (int) $userId,
                'ip' => $ipAddress,
                'user_agent' => $userAgent,
                'details' => [
                    'device_fingerprint' => $fingerprint,
                    'device_id'          => $deviceId !== '' ? $deviceId : null,
                    'accept_language'    => $acceptLanguage,
                ],
                'status' => 'success',
            ]);
        } catch (\Exception $e) {
            // Log but don't block on database error
            \App\API\Services\Logger::legacyError("Device logging failed: " . $e->getMessage());
        }
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
