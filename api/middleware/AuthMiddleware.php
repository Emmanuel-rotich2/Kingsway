<?php

namespace App\API\Middleware;

use App\API\Services\AuthSessionService;
use App\API\Services\TestAccountAccessService;
use App\API\Services\DataScopeService;
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
            'auth/reset-default-password',
            'auth/complete-reset',
            'auth/verify-reset-token',
            'auth/refresh-token',
            'auth/logout-refresh',
            'auth/session',
            'auth/refresh-session',
            'auth/validate-token',
            // SessionController routes only. Keep the /api/ boundary so this
            // exemption cannot also match protected /system/active-sessions.
            '/api/session',
            'session/refresh',
            'session/validate-token',
            'users/login',
            'users/register',
            // Payment webhook endpoints (should be public for bank/M-Pesa callbacks)
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
            // Parent portal auth endpoints (use their own session tokens, not staff JWT)
            'parent-portal/login',
            'parent-portal/login-otp-request',
            'parent-portal/login-otp-verify',
            'public/uniform-catalog',
            // 2FA challenge/verify — called during login before JWT is issued
            'twofactor/challenge',
            'twofactor/verify',
            'twofactor/passwordless-options',
            'twofactor/passwordless-verify',
            // Public careers intake for candidates who passed recruitment screening
            'staff-appointments/careers-candidate',
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
            // Public fee structures and academic calendars are generated on
            // demand and return short-lived encrypted download URLs.
            'website/printable-downloads',
            'website/printable-download',
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
            'website/stats',
            // Intake terms + active grades for the public admissions form.
            'website/terms',
            'website/grades',
            // Public website forms (POST) — anonymous submission endpoints. The
            // pages are thin shells; they never touch the DB directly. Each
            // resource is write-only by design, so a bare GET on the controller
            // has no get* handler and 404s instead of leaking data.
            'public/job-applications',
            'public/inquiries',
            'public/applications',
            'public/subscribers',
            // Provider callbacks are authenticated by the webhook secret checked
            // in CommunicationsController, not by a staff JWT.
            'communications/sms-delivery-report',
            'communications/whatsapp-delivery-report',
            'communications/whatsapp-incoming',
            'communications/sms-opt-out-callback',
            'communications/sms-subscription-callback',
            'communications/process-outbox',
            'attendance/gate-event',
            // Protected by ATTENDANCE_WORKER_SECRET rather than staff JWT.
            'attendance/process-register-reminders',
            // Protected by KCB_RECONCILIATION_WORKER_SECRET rather than staff JWT.
            'finance/kcb-reconciliation-worker',
            // Protected by COMMUNICATION_WORKER_SECRET rather than staff JWT.
            'realtime/worker',
            'realtime/cleanup',
        ];

        // Check if current request is to a public endpoint
        foreach ($publicEndpoints as $endpoint) {
            if (strpos($path, $endpoint) !== false) {
                // Public website content is anonymous only when it is being
                // read. Mutations must continue through JWT + RBAC even though
                // they share the same controller/resource URL.
                if (
                    str_starts_with($endpoint, 'website/') &&
                    strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
                ) {
                    continue;
                }
                // 'payments/mpesa-result' (the public C2B result webhook) is a
                // string prefix of 'payments/mpesa-results' (the authenticated
                // results reader). The plural reader must NOT be exempted.
                if (
                    $endpoint === 'payments/mpesa-result' &&
                    strpos($path, 'mpesa-results') !== false
                ) {
                    continue;
                }
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
            \App\API\Services\Logger::legacyError('AuthMiddleware: No Authorization header found');
            self::deny(401, 'Missing Authorization header. Please ensure you are logged in and the token is being sent.');
        }

        // Never log any part of an access token.
        \App\API\Services\Logger::legacyError('AuthMiddleware: Authorization header found');
        $token = str_replace('Bearer ', '', $authHeader);
        try {
            $decoded = JWT::decode(
                $token,
                new Key(JWT_SECRET, 'HS256')
            );

            if (!isset($decoded->iss) || $decoded->iss !== JWT_ISSUER) {
                self::deny(401, 'Invalid token issuer');
            }
            if (!isset($decoded->aud) || $decoded->aud !== JWT_AUDIENCE) {
                self::deny(401, 'Invalid token audience');
            }

            $authUser = self::normalizeDecodedUser((array) $decoded);
            $userId = (int) (
                $authUser['user_id'] ?? $authUser['id'] ?? 0
            );

            try {
                $session = (new AuthSessionService())
                    ->validateAccessToken($token, $userId);
            } catch (\Throwable $error) {
                \App\API\Services\Logger::legacyError(
                    'AuthMiddleware: Session validation failed: ' .
                    $error->getMessage()
                );
                self::deny(
                    503,
                    'Session validation is temporarily unavailable'
                );
            }

            if (!$session) {
                self::deny(
                    401,
                    'This authenticated session is no longer active'
                );
            }

            try {
                $accessContext = (new TestAccountAccessService())
                    ->requireAccess($userId);
            } catch (\DomainException $error) {
                self::deny(403, $error->getMessage());
            }

            $authUser['account_type'] = $accessContext['account_type'];
            $authUser['is_test_user'] = (int) $accessContext['is_test_user'];
            $authUser['data_scope'] = $accessContext['data_scope'];
            $authUser['test_access_expires_at'] = $accessContext['test_access_expires_at'];
            $_SERVER['data_scope'] = $accessContext['data_scope'];

            try {
                DataScopeService::requireReviewedTestRoute(
                    (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
                    (string) ($_SERVER['REQUEST_URI'] ?? '/')
                );
            } catch (\DomainException $error) {
                self::deny(403, $error->getMessage());
            }

            // Attach the verified user and canonical session ID for controller
            // authorization and current-session protection.
            $_SERVER['auth_user'] = $authUser;
            $_SERVER['auth_session_id'] = (int) $session['id'];

        } catch (\Exception $e) {
            self::deny(401, 'Invalid or expired token');
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
        \App\API\Includes\SecurityEventNotifier::unauthorizedAccess(
            $message,
            [
                'entity' => 'auth_token',
                'entity_id' => null,
                'details' => ['status_code' => $code],
            ]
        );
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
