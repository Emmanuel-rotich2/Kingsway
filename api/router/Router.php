<?php

namespace App\API\Router;

use App\API\Middleware\CORSMiddleware;
use App\API\Middleware\AuthMiddleware;
use App\API\Middleware\CsrfMiddleware;
use App\API\Middleware\DeviceMiddleware;
use App\API\Middleware\IpAccessControlMiddleware;
use App\API\Middleware\RBACMiddleware;
use App\API\Middleware\RateLimitMiddleware;
use App\API\Middleware\ResponseCacheMiddleware;
use App\API\Middleware\RouteAuthorization;
use Exception;

class Router
{
    private ControllerRouter $controllerRouter;

    public function __construct()
    {
        $this->controllerRouter = new ControllerRouter();
    }

    public function handle()
    {
        try {
            // ===== MIDDLEWARE PIPELINE =====
            // 1. CORS - Check origin and handle preflight
            CORSMiddleware::handle();

            // 2. IP access control - Enforce active allow/deny CIDR rules
            IpAccessControlMiddleware::handle();

            // 3. Rate Limiting - Prevent brute force and flooding
            RateLimitMiddleware::handle();

            // 4. Auth (JWT) - Validate JWT token
            AuthMiddleware::handle();

            // 5. CSRF - Validate CSRF token on state-changing requests
            CsrfMiddleware::handle();

            // 6. RBAC - Resolve user permissions from database
            RBACMiddleware::handle();

            // 7. Route Authorization - Enforce DB route whitelist for registered API routes
            $routeAuth = RouteAuthorization::enforceCurrentRequest();
            if (!$routeAuth['success']) {
                http_response_code($routeAuth['http_code']);
                return [
                    'success' => false,
                    'status' => 'error',
                    'data' => null,
                    'message' => $routeAuth['message'],
                    'errors' => [],
                    'code' => $routeAuth['http_code']
                ];
            }

            // 8. Device - Log device fingerprint and check blacklist
            DeviceMiddleware::handle();

            // 9. Response cache (g4) - sits AFTER auth so entries are keyed on
            // the authenticated user. All guards above still run on a cache HIT;
            // only the controller dispatch below is skipped. Non-GET and
            // non-allowlisted paths pass straight through with zero overhead.
            return ResponseCacheMiddleware::handle(
                function (): array {
                    return $this->controllerRouter->route();
                }
            );

        } catch (Exception $e) {
            $code = (int) $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            http_response_code($code);
            return [
                "success" => false,
                "status" => "error",
                "data" => null,
                "message" => 'An internal error occurred.',
                "errors" => [],
                "code" => $code
            ];
        }
    }
}
