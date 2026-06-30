<?php

namespace App\API\Router;

use App\API\Middleware\CORSMiddleware;
use App\API\Middleware\AuthMiddleware;
use App\API\Middleware\DeviceMiddleware;
use App\API\Middleware\RBACMiddleware;
use App\API\Middleware\RateLimitMiddleware;
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

            // 2. Rate Limiting - Prevent brute force and flooding
            RateLimitMiddleware::handle();

            // 3. Auth (JWT) - Validate JWT token
            AuthMiddleware::handle();

            // 4. RBAC - Resolve user permissions from database
            RBACMiddleware::handle();

            // 5. Route Authorization - Enforce DB route whitelist for registered API routes
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

            // 6. Device - Log device fingerprint and check blacklist
            DeviceMiddleware::handle();

            // ===== DELEGATE TO CONTROLLER ROUTER =====
            // ControllerRouter handles all RESTful routing and controller dispatch
            return $this->controllerRouter->route();

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
                "message" => $e->getMessage(),
                "errors" => [],
                "code" => $code
            ];
        }
    }
}
