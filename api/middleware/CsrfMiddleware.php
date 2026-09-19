<?php

namespace App\API\Middleware;

class CsrfMiddleware
{
    private static array $exemptEndpoints = [
        'auth/login',
        'auth/register',
        'auth/forgot-password',
        'auth/reset-password',
        'auth/reset-default-password',
        'auth/complete-reset',
        'auth/verify-reset-token',
        'auth/refresh-token',
        'auth/logout-refresh',
        'auth/session',
        'auth/refresh-session',
        'auth/validate-token',
        '/api/session',
        'session/refresh',
        'session/validate-token',
        'users/login',
        'users/register',
        // Parent login and OTP verification happen before a JWT/CSRF token
        // exists; authenticated portal mutations remain protected.
        'parent-portal/login',
        'parent-portal/login-otp-request',
        'parent-portal/login-otp-verify',
        'twofactor/challenge',
        'twofactor/verify',
        'twofactor/passwordless-options',
        'twofactor/passwordless-verify',
        'payments/index',
        'payments/mpesa-b2c-callback',
        'payments/mpesa-b2c-timeout',
        'payments/c2b-validation',
        'payments/c2b-confirmation',
        'payments/mpesa-c2b-validation',
        'payments/mpesa-c2b-confirmation',
        'payments/mpesa-stk-callback',
        'payments/kcb-mpesa-express-callback',
        'payments/mpesa-result',
        'payments/kcb-validation',
        'payments/kcb-transfer-callback',
        'payments/kcb-notification',
        'payments/kcb-account-notification',
        'payments/kcb-till-notification',
        'payments/bank-webhook',
        'staff-appointments/careers-candidate',
        'academic/resources/download',
        'download/public',
        'download/print',
        'download/generated',
        'website/news',
        'website/events',
        'website/gallery',
        'website/downloads',
        'website/jobs',
        'website/settings',
        'website/content',
        'website/categories',
        'website/leadership',
        'website/programs',
        'website/facilities',
        'website/history',
        'website/values',
        'website/departments',
        'website/benefits',
        'public/job-applications',
        'public/inquiries',
        'public/applications',
        'public/subscribers',
        // Anonymous public FAQ requests have no staff session CSRF token.
        'public/ai-faq',
        'communications/sms-delivery-report',
        'communications/whatsapp-delivery-report',
        'communications/whatsapp-incoming',
        'communications/sms-opt-out-callback',
        'communications/sms-subscription-callback',
        'communications/process-outbox',
        'attendance/gate-event',
        // Internal scheduler callback authenticates with ATTENDANCE_WORKER_SECRET.
        'attendance/process-register-reminders',
        'finance/kcb-reconciliation-worker',
        // Internal cron callbacks authenticate with COMMUNICATION_WORKER_SECRET.
        'realtime/worker',
        'realtime/cleanup',
        'realtime/sync-projection',
        'mcp',
    ];

    public static function handle(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'GET' || $method === 'OPTIONS') {
            return;
        }

        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }

        $path = strtolower($_SERVER['REQUEST_URI'] ?? '');
        foreach (self::$exemptEndpoints as $endpoint) {
            if ($endpoint === 'mcp' && strtolower((string) parse_url($path, PHP_URL_PATH)) !== '/api/mcp') {
                continue;
            }
            if (strpos($path, $endpoint) !== false) {
                return;
            }
        }

        $authUser = $_SERVER['auth_user'] ?? null;
        if (empty($authUser)) {
            return;
        }

        $userId = (int) ($authUser['user_id'] ?? $authUser['id'] ?? 0);
        if ($userId < 1) {
            self::deny(403, 'CSRF validation failed: invalid session');
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$token) {
            self::deny(403, 'CSRF token missing. Send the X-CSRF-Token header.');
        }

        if (!self::validateToken($userId, $token)) {
            self::deny(403, 'CSRF token is invalid or expired');
        }
    }

    private static function validateToken(int $userId, string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return false;
        }

        $parts = explode(':', $decoded);
        if (count($parts) !== 4) {
            return false;
        }

        $tokenUserId = $parts[0];
        $timestamp   = $parts[1];
        $random      = $parts[2];
        $signature   = $parts[3];

        if ((int) $tokenUserId !== $userId) {
            return false;
        }

        $ts = (int) $timestamp;
        if ($ts < 1 || abs(time() - $ts) > 3600) {
            return false;
        }

        $expected = hash_hmac('sha256', $tokenUserId . ':' . $timestamp . ':' . $random, JWT_SECRET);

        return hash_equals($expected, $signature);
    }

    private static function deny(int $code, string $message): void
    {
        \App\API\Includes\SecurityEventNotifier::securityIncident(
            $message,
            ['entity' => 'csrf', 'details' => ['status_code' => $code]]
        );
        http_response_code($code);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false,
            'status'  => 'error',
            'data'    => null,
            'message' => $message,
            'errors'  => [],
            'code'    => $code,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
