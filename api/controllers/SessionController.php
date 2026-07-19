<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Modules\users\UsersAPI;
use App\API\Modules\users\PermissionManager;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Session Controller
 * 
 * Provides session management endpoints for the centralized SessionManager.
 * Handles session validation, refresh, and CSRF token generation.
 */
class SessionController extends BaseAPI
{
    private $usersApi;
    private $permissionManager;

    public function __construct()
    {
        parent::__construct('session');
        $this->usersApi = new UsersAPI();
        $this->permissionManager = new PermissionManager($this->db);
    }

    /**
     * Get current session information
     * GET /api/auth/session
     */
    public function getSession()
    {
        try {
            // Check if user is authenticated via JWT
            $authUser = $_SERVER['auth_user'] ?? null;
            
            if (!$authUser) {
                return [
                    'success' => true,
                    'data' => [
                        'authenticated' => false,
                        'user' => null,
                        'roles' => [],
                        'permissions' => [],
                        'session_expires_at' => null,
                        'csrf_token' => null
                    ]
                ];
            }

            // Get user permissions
            $userId = $authUser['user_id'];
            $permissions = $this->permissionManager->getUserEffectivePermissions($userId);
            
            // Extract permission codes
            $permissionCodes = [];
            foreach ($permissions as $perm) {
                if (isset($perm['permission_code'])) {
                    $permissionCodes[] = $perm['permission_code'];
                }
            }

            // Get user roles
            $roles = $authUser['roles'] ?? [];
            
            // Generate CSRF token
            $csrfToken = $this->generateCsrfToken($userId);
            
            // Calculate session expiry (from JWT token)
            $sessionExpiresAt = null;
            if (isset($authUser['exp'])) {
                $sessionExpiresAt = date('Y-m-d H:i:s', $authUser['exp']);
            }

            return [
                'success' => true,
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => $authUser['user_id'],
                        'username' => $authUser['username'],
                        'email' => $authUser['email'],
                        'first_name' => $authUser['display_name'] ?? $authUser['username'],
                        'last_name' => '',
                        'roles' => $roles
                    ],
                    'roles' => $roles,
                    'permissions' => $permissionCodes,
                    'session_id' => session_id(),
                    'session_expires_at' => $sessionExpiresAt,
                    'csrf_token' => $csrfToken
                ]
            ];

        } catch (\Exception $e) {
            error_log('Session check error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to check session'
            ];
        }
    }

    /**
     * Refresh session
     * POST /api/session/refresh
     *
     * Method name follows the router convention (HTTP verb + CamelCase resource),
     * so POST /api/session/refresh resolves to postRefresh() rather than 404-ing.
     */
    public function postRefresh()
    {
        try {
            $authUser = $_SERVER['auth_user'] ?? null;
            
            if (!$authUser) {
                return [
                    'success' => false,
                    'message' => 'No active session to refresh'
                ];
            }

            // Validate CSRF token
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
            if (!$csrfToken || !$this->validateCsrfToken($authUser['user_id'], $csrfToken)) {
                return [
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ];
            }

            // Get user permissions
            $userId = $authUser['user_id'];
            $permissions = $this->permissionManager->getUserEffectivePermissions($userId);
            
            // Extract permission codes
            $permissionCodes = [];
            foreach ($permissions as $perm) {
                if (isset($perm['permission_code'])) {
                    $permissionCodes[] = $perm['permission_code'];
                }
            }

            // Get user roles
            $roles = $authUser['roles'] ?? [];
            
            // Generate new CSRF token
            $newCsrfToken = $this->generateCsrfToken($userId);
            
            // Calculate session expiry
            $sessionExpiresAt = null;
            if (isset($authUser['exp'])) {
                $sessionExpiresAt = date('Y-m-d H:i:s', $authUser['exp']);
            }

            return [
                'success' => true,
                'message' => 'Session refreshed successfully',
                'data' => [
                    'authenticated' => true,
                    'user' => [
                        'id' => $authUser['user_id'],
                        'username' => $authUser['username'],
                        'email' => $authUser['email'],
                        'first_name' => $authUser['display_name'] ?? $authUser['username'],
                        'last_name' => '',
                        'roles' => $roles
                    ],
                    'roles' => $roles,
                    'permissions' => $permissionCodes,
                    'session_id' => session_id(),
                    'session_expires_at' => $sessionExpiresAt,
                    'csrf_token' => $newCsrfToken
                ]
            ];

        } catch (\Exception $e) {
            error_log('Session refresh error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to refresh session'
            ];
        }
    }

    /**
     * Validate legacy token during migration
     * POST /api/session/validate-token
     *
     * Method name follows the router convention (HTTP verb + CamelCase resource).
     */
    public function postValidateToken()
    {
        try {
            $authHeader = null;
            
            // Try multiple methods to get Authorization header
            if (function_exists('getallheaders')) {
                $headers = getallheaders();
                if (isset($headers['Authorization'])) {
                    $authHeader = $headers['Authorization'];
                }
            }
            
            if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            }
            
            if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            }
            
            if (!$authHeader) {
                return [
                    'success' => false,
                    'message' => 'No Authorization header found'
                ];
            }

            $token = str_replace('Bearer ', '', $authHeader);
            
            // Validate JWT
            try {
                $decoded = JWT::decode(
                    $token,
                    new Key(JWT_SECRET, 'HS256')
                );
                
                $userData = (array) $decoded;
                
                // Get user permissions
                $userId = $userData['user_id'];
                $permissions = $this->permissionManager->getUserEffectivePermissions($userId);
                
                // Extract permission codes
                $permissionCodes = [];
                foreach ($permissions as $perm) {
                    if (isset($perm['permission_code'])) {
                        $permissionCodes[] = $perm['permission_code'];
                    }
                }

                // Generate CSRF token
                $csrfToken = $this->generateCsrfToken($userId);
                
                // Calculate session expiry
                $sessionExpiresAt = null;
                if (isset($userData['exp'])) {
                    $sessionExpiresAt = date('Y-m-d H:i:s', $userData['exp']);
                }

                return [
                    'success' => true,
                    'data' => [
                        'authenticated' => true,
                        'user' => [
                            'id' => $userData['user_id'],
                            'username' => $userData['username'],
                            'email' => $userData['email'],
                            'first_name' => $userData['display_name'] ?? $userData['username'],
                            'last_name' => '',
                            'roles' => $userData['roles'] ?? []
                        ],
                        'roles' => $userData['roles'] ?? [],
                        'permissions' => $permissionCodes,
                        'session_id' => session_id(),
                        'session_expires_at' => $sessionExpiresAt,
                        'csrf_token' => $csrfToken,
                        'refresh_token' => $_COOKIE['refresh_token'] ?? null
                    ]
                ];
                
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Invalid token: ' . $e->getMessage()
                ];
            }

        } catch (\Exception $e) {
            error_log('Token validation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to validate token'
            ];
        }
    }

    /**
     * Generate CSRF token
     */
    private function generateCsrfToken($userId)
    {
        $tokenData = [
            'user_id' => $userId,
            'timestamp' => time(),
            'random' => bin2hex(random_bytes(16))
        ];
        
        return hash_hmac('sha256', json_encode($tokenData), JWT_SECRET);
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrfToken($userId, $token)
    {
        // For now, accept any non-empty token during transition
        // TODO: Implement proper CSRF token validation with timestamp check
        return !empty($token);
    }
}
