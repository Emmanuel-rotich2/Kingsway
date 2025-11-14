<?php

namespace App\API\Controllers;

use App\API\Modules\auth\AuthAPI;
use Exception;

/**
 * AuthController - RESTful Authentication & Authorization
 * 
 * Handles user login, logout, token refresh, and password management.
 * Examples:
 *   POST /api/auth/login                → post with action=login
 *   POST /api/auth/logout               → post with action=logout
 *   POST /api/auth/refresh              → post with action=refresh
 *   POST /api/auth/reset-password       → post with action=reset-password
 *   POST /api/auth/change-password      → post with action=change-password
 *   GET  /api/auth/profile              → get with action=profile
 *   GET  /api/auth/permissions          → get with action=permissions
 */
class AuthController extends BaseController
{
    private AuthAPI $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new AuthAPI();
    }

    /**
     * Handle GET requests for auth endpoints
     * 
     * GET /api/auth?action=profile       - Get current user profile
     * GET /api/auth?action=permissions   - Get current user permissions
     * GET /api/auth?action=verify        - Verify token validity
     * GET /api/auth?action=sessions      - List user sessions
     */
    public function get($id = null, $data = [], $segments = [])
    {
        try {
            $action = $data['action'] ?? '';

            if (empty($action) || $action === 'profile') {
                return $this->getProfile();
            }

            if ($action === 'permissions') {
                return $this->getPermissions();
            }

            if ($action === 'verify') {
                return $this->getVerify();
            }

            if ($action === 'sessions') {
                return $this->getSessions();
            }

            if ($id !== null && $action === 'session') {
                return $this->getSession($id);
            }

            return $this->respondWith(400, 'Invalid GET action', null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * Handle POST requests for auth endpoints
     * 
     * POST /api/auth?action=login              - User login
     * POST /api/auth?action=logout             - User logout
     * POST /api/auth?action=refresh            - Refresh JWT token
     * POST /api/auth?action=reset-password     - Request password reset
     * POST /api/auth?action=change-password    - Change password
     */
    public function post($id = null, $data = [], $segments = [])
    {
        try {
            $action = $data['action'] ?? '';

            if (empty($action) || $action === 'login') {
                return $this->postLogin($data);
            }

            if ($action === 'logout') {
                return $this->postLogout($data);
            }

            if ($action === 'refresh') {
                return $this->postRefresh($data);
            }

            if ($action === 'reset-password') {
                return $this->postResetPassword($data);
            }

            if ($action === 'change-password') {
                return $this->postChangePassword($data);
            }

            return $this->respondWith(400, 'Invalid POST action', null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * Handle PUT requests for auth endpoints
     */
    public function put($id = null, $data = [], $segments = [])
    {
        try {
            if ($id === null) {
                return $this->respondWith(400, 'Invalid PUT request', null);
            }

            $action = $data['action'] ?? '';

            if ($action === 'revoke-session') {
                return $this->putRevokeSession($id);
            }

            return $this->respondWith(400, 'Invalid PUT action', null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * Handle DELETE requests for auth endpoints
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        try {
            if ($id === null) {
                // DELETE /api/auth - Delete current session
                return $this->postLogout([]);
            }

            // DELETE /api/auth/{id} - Delete specific session
            return $this->putRevokeSession($id);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    // ============================================================
    // PRIVATE ENDPOINT METHODS
    // ============================================================

    /**
     * POST /api/auth?action=login - User login
     * Body: { "username": "user", "password": "pass" }
     */
    private function postLogin($data)
    {
        try {
            if (empty($data['username']) || empty($data['password'])) {
                return $this->respondWith(400, 'Username and password required', null);
            }

            $result = $this->api->login($data);

            if ($result['status'] === 'success') {
                return $this->respondWith(200, $result['message'], $result['data']);
            }
            return $this->respondWith(401, $result['message'], null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * POST /api/auth?action=logout - User logout
     */
    private function postLogout($data)
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            // Invalidate current session
            $db = $this->getDb();
            $stmt = $db->prepare("
                UPDATE auth_sessions 
                SET is_active = 0 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$user['id']]);

            return $this->respondWith(200, 'Logged out successfully', null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * POST /api/auth?action=refresh - Refresh JWT token
     */
    private function postRefresh($data)
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            $result = $this->api->refreshToken($data);

            if ($result['status'] === 'success') {
                return $this->respondWith(200, $result['message'], $result['data']);
            }
            return $this->respondWith(401, $result['message'], null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * POST /api/auth?action=reset-password - Request password reset
     * Body: { "email": "user@example.com" }
     */
    private function postResetPassword($data)
    {
        try {
            if (empty($data['email'])) {
                return $this->respondWith(400, 'Email is required', null);
            }

            $result = $this->api->resetPassword($data);

            if ($result['status'] === 'success') {
                return $this->respondWith(200, $result['message'], $result['data'] ?? null);
            }
            return $this->respondWith(400, $result['message'], null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * POST /api/auth?action=change-password - Change password (authenticated)
     * Body: { "current_password": "old", "new_password": "new" }
     */
    private function postChangePassword($data)
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            if (empty($data['current_password']) || empty($data['new_password'])) {
                return $this->respondWith(400, 'Current and new passwords required', null);
            }

            $result = $this->api->changePassword($user['id'], $data);

            if ($result['status'] === 'success') {
                return $this->respondWith(200, $result['message'], null);
            }
            return $this->respondWith(400, $result['message'], null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * GET /api/auth?action=profile - Get current user profile
     */
    private function getProfile()
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            return $this->respondWith(200, 'Profile retrieved', $user);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * GET /api/auth?action=permissions - Get current user permissions
     */
    private function getPermissions()
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            return $this->respondWith(200, 'Permissions retrieved', [
                'permissions' => $user['permissions'] ?? [],
                'roles' => $user['roles'] ?? []
            ]);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * GET /api/auth?action=verify - Verify token is valid
     */
    private function getVerify()
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Token invalid', null);
            }

            return $this->respondWith(200, 'Token valid', [
                'valid' => true,
                'user_id' => $user['id'],
                'username' => $user['username'] ?? null
            ]);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * GET /api/auth?action=sessions - List all user sessions
     */
    private function getSessions()
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            $db = $this->getDb();
            $stmt = $db->prepare("
                SELECT 
                    id,
                    ip_address,
                    user_agent,
                    created_at,
                    last_activity,
                    is_active
                FROM auth_sessions
                WHERE user_id = ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$user['id']]);
            $sessions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return $this->respondWith(200, 'Sessions retrieved', [
                'sessions' => $sessions,
                'count' => count($sessions)
            ]);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * GET /api/auth/{id}?action=session - Get specific session details
     */
    private function getSession($sessionId)
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            $db = $this->getDb();
            $stmt = $db->prepare("
                SELECT * FROM auth_sessions
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$sessionId, $user['id']]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$session) {
                return $this->respondWith(404, 'Session not found', null);
            }

            return $this->respondWith(200, 'Session retrieved', $session);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }

    /**
     * PUT /api/auth/{id}?action=revoke-session - Revoke specific session
     */
    private function putRevokeSession($sessionId)
    {
        try {
            $user = $this->getUser();
            if (!$user) {
                return $this->respondWith(401, 'Not authenticated', null);
            }

            $db = $this->getDb();
            $stmt = $db->prepare("
                UPDATE auth_sessions
                SET is_active = 0
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$sessionId, $user['id']]);

            if ($stmt->rowCount() === 0) {
                return $this->respondWith(404, 'Session not found', null);
            }

            return $this->respondWith(200, 'Session revoked', null);

        } catch (Exception $e) {
            return $this->respondWith(500, $e->getMessage(), null);
        }
    }
}
