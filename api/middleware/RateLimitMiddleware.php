<?php

namespace App\API\Middleware;

use PDO;

class RateLimitMiddleware
{
    // Anonymous traffic only. Authenticated staff are never throttled (see
    // handle()): the buckets below exist to stop unauthenticated abuse, and the
    // tight ones are keyed per browser session rather than per IP, because the
    // whole school shares one address behind its NAT.
    const ANONYMOUS_REQUESTS_LIMIT = 600;
    const LOGIN_REQUESTS_LIMIT = 60;
    // Public AI is provider-backed and anonymous; keep its cost/abuse bucket
    // separate from ordinary public website traffic.
    const PUBLIC_AI_REQUESTS_LIMIT = 200;
    // Handoff leads are low-volume but write to the DB and send email.
    const PUBLIC_LEADS_REQUESTS_LIMIT = 5;
    // Each call to these can send a PAID SMS/WhatsApp message, so they get
    // their own bucket rather than the anonymous default. Keyed by the browser
    // session so one person's retries do not spend a colleague's allowance on
    // the same NAT'd network. The per-account OTP budget in TwoFactorService
    // remains the real cost control.
    const VERIFICATION_REQUESTS_LIMIT = 30;
    const TIME_WINDOW = 60; // seconds

    // Login endpoints that get a stricter rate limit.
    const LOGIN_PATHS = [
        '/auth/login',
        '/users/login',
    ];

    const VERIFICATION_PATHS = [
        '/twofactor/challenge',
        '/twofactor/verify',
    ];

    /**
     * Check rate limiting by authenticated user when a bearer token is present,
     * otherwise by IP address. This middleware intentionally does not authorize
     * the JWT; the decoded payload is only used to choose a fair rate bucket.
     *
     * Authenticated callers are deliberately NOT throttled. The buckets exist to
     * stop unauthenticated abuse — credential stuffing against /auth/login,
     * paid-SMS floods on the two-factor paths, and anonymous scraping. A signed-in
     * staff member driving a real workflow exceeds 60 requests/min on a single
     * page (table loads, filters, reference data, realtime streams) and a school
     * network is NAT'd behind one IP, so throttling them cost legitimate work
     * without stopping any attack the login/OTP limits do not already prevent.
     * Cost control for signed-in callers belongs to the specific expensive
     * operation (TwoFactorService's per-account OTP budget), not to a blanket
     * request counter.
     */
    public static function handle()
    {
        $ipAddress = self::clientIp();
        $rateKey = self::resolveRateKey($ipAddress);

        if (strpos($rateKey, 'user:') === 0) {
            return; // signed in — see above
        }

        $limit = self::resolveLimit();
        $now = time();

        // Counters are JSON-lines files under logs/<env>/ratelimit, matching
        // Hard Rule 2 (no database log tables). The previous rate_limit_logs
        // table never existed, so this middleware silently failed open for
        // every request; the file limiter is always available and always
        // counts, so rotating IPs does not bypass it.
        $remaining = \App\API\Services\FileRateLimiter::consume(
            $rateKey,
            $limit,
            self::TIME_WINDOW
        );
        if ($remaining < 0) {
            self::deny(429, 'Too many requests. Rate limit exceeded.');
        }
    }

    /**
     * Budget for an unauthenticated request. Called only for callers that
     * reached here, i.e. anonymous ones.
     */
    private static function resolveLimit()
    {
        if (self::isPublicAiEndpoint()) {
            return self::PUBLIC_AI_REQUESTS_LIMIT;
        }
        if (self::isPublicLeadsEndpoint()) {
            return self::PUBLIC_LEADS_REQUESTS_LIMIT;
        }
        if (self::isLoginEndpoint()) {
            return self::LOGIN_REQUESTS_LIMIT;
        }
        if (self::isVerificationEndpoint()) {
            return self::VERIFICATION_REQUESTS_LIMIT;
        }

        return self::ANONYMOUS_REQUESTS_LIMIT;
    }

    private static function isVerificationEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        foreach (self::VERIFICATION_PATHS as $verificationPath) {
            if (strpos($path, $verificationPath) !== false) return true;
        }
        return false;
    }

    private static function isPublicAiEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/public/ai-faq') !== false || strpos($path, '/public/ai-feedback') !== false;
    }

    private static function isPublicLeadsEndpoint(): bool
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        return strpos($path, '/public/leads') !== false;
    }

    private static function isLoginEndpoint()
    {
        $path = strtolower((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
        foreach (self::LOGIN_PATHS as $loginPath) {
            if (strpos($path, $loginPath) !== false) {
                return true;
            }
        }
        return false;
    }

    private static function resolveRateKey($ipAddress)
    {
        $userId = self::bearerUserId();
        if ($userId > 0) {
            return 'user:' . $userId;
        }

        // Auth endpoints are per browser tab/session, not per IP. The browser
        // already sends X-Browser-Session-Id on every request; using it keeps
        // one person's retries from spending a colleague's allowance on the
        // same NAT'd school network. The header is only a fairness key — the
        // per-account challenge/OTP limits remain the brute-force control.
        if (self::isLoginEndpoint() || self::isVerificationEndpoint()) {
            $browserSession = (string) ($_SERVER['browser_session_id']
                ?? $_SERVER['HTTP_X_BROWSER_SESSION_ID']
                ?? '');
            if ($browserSession !== ''
                && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $browserSession)) {
                return 'browser:' . $browserSession;
            }
        }

        // Device key before IP fallback: the SPA sends a persistent
        // localStorage-backed X-Device-Id, so an attacker rotating IPs
        // (VPN, mobile data, proxy pool) still lands in the same bucket.
        // IP remains the last resort for clients that send no device id
        // (scripts, curl, old browsers).
        $deviceId = (string) ($_SERVER['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? '');
        if ($deviceId !== '' && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $deviceId)) {
            return 'device:' . $deviceId;
        }

        return 'ip:' . $ipAddress;
    }

    /**
     * Real client address behind the nginx -> Apache load balancer.
     *
     * Apache listens on loopback only and nginx sets X-Forwarded-For. Without
     * this, every request reached PHP as 127.0.0.1 and the whole school shared
     * a single anonymous bucket. Forwarded headers are trusted only when the
     * direct peer is loopback, so a remote client cannot spoof the key.
     */
    private static function clientIp(): string
    {
        $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($remote !== '' && !in_array($remote, ['127.0.0.1', '::1'], true)) {
            return $remote;
        }

        // nginx always overwrites X-Real-IP with $remote_addr, so prefer it.
        $realIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if (filter_var($realIp, FILTER_VALIDATE_IP)) {
            return $realIp;
        }

        $forwarded = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded !== '') {
            // With one trusted proxy the last entry is the address nginx saw.
            $parts = array_values(array_filter(array_map('trim', explode(',', $forwarded)), 'strlen'));
            $last = $parts !== [] ? end($parts) : '';
            if (filter_var($last, FILTER_VALIDATE_IP)) {
                return $last;
            }
        }

        return $remote !== '' ? $remote : 'unknown';
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

    /**
     * Deny request and exit with error response
     */
    private static function deny($code, $message)
    {
        \App\API\Includes\SecurityEventNotifier::securityIncident(
            $message,
            ['entity' => 'rate_limit', 'details' => ['status_code' => $code]]
        );
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
