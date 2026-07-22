<?php

namespace App\API\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    /**
     * Handle JWT validation and attach user info to $_SERVER['auth_user']
     *
     * FIX: Removed hardcoded test credentials. Test credentials must be managed
     * through separate test/staging environment with dedicated credentials.
     * Production code must never contain plaintext test credentials.
     */
    public static function handle()
    {
        $path = strtolower($_SERVER['REQUEST_URI']);

        // Public endpoints that don't require JWT
        $publicEndpoints = [
            'auth/login',
            'auth/register',
            'auth/forgot-password',
            'auth/reset-password',
            'auth/complete-reset',
            'auth/verify-reset-token',
            'auth/refresh-token',
            'auth/logout-refresh',
            'auth/session',
            'auth/refresh-session',
            'auth/validate-token',
            // SessionController (no AuthController exists; routes resolve to /api/session/*)
            'session',
            'session/refresh',
            'session/validate-token',
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
            // Parent portal auth endpoints (use their own session tokens, not staff JWT)
            'parent-portal/login',
            'parent-portal/login-otp-request',
            'parent-portal/login-otp-verify',
            // Public careers intake for candidates who passed recruitment screening
            'staff-appointments/careers-candidate',
            // Client telemetry/error ingestion (reporter sends a periodic fire-and-forget
            // batch that may fire while the access token is mid-refresh; keep it public
            // so it never gets stuck in a 401/retry loop).
            'telemetry',
            'telemetry/data',
            'telemetry/errors',
            // Resource file downloads (teaching materials / past papers). The list
            // (GET /api/academic/resources) and upload (POST) stay authenticated; only
            // the file-serving GET is public because the frontend opens it via
            // window.location.href (a top-level navigation carries no Authorization
            // header). Materials are a shared, non-sensitive library.
            'academic/resources/download',
            // Opaque generated-file and school-document delivery.
            // The encrypted token is the authorization credential because direct
            // browser navigation and <iframe>/<a> requests do not attach bearer JWTs.
            'download/public',
            'download/print',
            'download/generated',
            // Public website content showcase (read-only). These resources are
            // rendered unauthenticated on the static public site via kw_*()
            // helpers, so anonymous JS cache hydration (PublicCache) must fetch
            // them too. Only GET is allowed through the JWT gate — every write
            // (POST/PUT/DELETE) still hits website_*_manage in WebsiteController,
            // which rejects a null user with 403. Order matters: more specific
            // slugs are listed so this block never opens staff-only routes.
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
            'website/steps',
            'website/benefits',
        ];

        // Check if current request is to a public endpoint
        foreach ($publicEndpoints as $endpoint) {
            if (strpos($path, $endpoint) !== false) {
                return;
            }
        }

        // Parent portal routes bypass staff JWT auth entirely.
        // Login/OTP endpoints are public; every other parent-portal endpoint enforces
        // auth via ParentAuthMiddleware, which sets $_SERVER['parent_auth'] for the
        // controller (ParentPortalController reads $this->parentId from it).
        // NOTE: ParentAuthMiddleware::handle() must be invoked here — the router
        // pipeline does not call it, so without this line every authed portal
        // endpoint returns 401 (parentId is never populated).
        if (strpos($path, 'parent-portal/') !== false) {
            $publicPortal = [
                'parent-portal/login',
                'parent-portal/login-otp-request',
                'parent-portal/login-otp-verify',
            ];
            $isPublic = false;
            foreach ($publicPortal as $ep) {
                if (strpos($path, $ep) !== false) {
                    $isPublic = true;
                    break;
                }
            }
            if (!$isPublic) {
                \App\API\Middleware\ParentAuthMiddleware::handle();
            }
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
        // Resolve the Authorization header across all the places PHP may expose it.
        // Header-key casing in getallheaders()/$_SERVER varies by SAPI: Apache upper-cases the
        // key, but a front-end proxy (nginx -> Apache) often delivers it lower-case ("authorization").
        // If we match an exact literal we break in one of those environments, so we search
        // case-insensitively across every source.
        $authHeader = null;

        // Method 1: getallheaders() (most reliable behind a proxy; case-insensitive lookup)
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $authHeader = $value;
                    break;
                }
            }
        }

        // Method 2: $_SERVER['HTTP_AUTHORIZATION'] (may be null behind a proxy)
        if (!$authHeader) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        }

        // Method 3: Apache-specific redirect-injected header
        if (!$authHeader) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        }

        // Method 4: Direct case-insensitive sweep of $_SERVER for a HTTP_AUTHORIZATION var
        if (!$authHeader) {
            foreach ($_SERVER as $key => $value) {
                if (strcasecmp($key, 'HTTP_AUTHORIZATION') === 0) {
                    $authHeader = $value;
                    break;
                }
            }
        }

        if (!$authHeader) {
            error_log('AuthMiddleware: No Authorization header found');
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
            $_SERVER['auth_user'] = self::normalizeDecodedUser((array) $decoded);

        } catch (\Exception $e) {
            self::deny(401, 'Invalid or expired token: ' . $e->getMessage());
        }
    }

    /**
     * Normalize role data so downstream authorization code has stable helpers.
     */
    private static function normalizeDecodedUser(array $user): array
    {
        $roles = $user['roles'] ?? [];
        $roleIds = [];
        $roleNames = [];

        foreach ((array) $roles as $role) {
            if (is_array($role)) {
                if (isset($role['id'])) {
                    $roleIds[] = (int) $role['id'];
                } elseif (isset($role['role_id'])) {
                    $roleIds[] = (int) $role['role_id'];
                }

                if (!empty($role['name'])) {
                    $roleNames[] = strtolower((string) $role['name']);
                }
            } elseif (is_object($role)) {
                if (isset($role->id)) {
                    $roleIds[] = (int) $role->id;
                } elseif (isset($role->role_id)) {
                    $roleIds[] = (int) $role->role_id;
                }

                if (!empty($role->name)) {
                    $roleNames[] = strtolower((string) $role->name);
                }
            } elseif (is_numeric($role)) {
                $roleIds[] = (int) $role;
            } elseif (is_string($role)) {
                $roleNames[] = strtolower($role);
            }
        }

        $user['role_ids'] = array_values(array_unique($roleIds));
        $user['role_names'] = array_values(array_unique($roleNames));

        return $user;
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
            'success' => false,
            'status' => 'error',
            'data' => null,
            'message' => $message,
            'errors' => [],
            'code' => $code,
        ]);
        echo $payload !== false
            ? $payload
            : '{"status":"error","message":"Internal error","code":500}';
        exit;
    }
}
