<?php
namespace App\API\Controllers;

use App\API\Modules\Users\UsersAPI;
use Exception;

/**
 * UsersController - REST endpoints for user management
 * Handles user accounts, roles, permissions, and authentication
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class UsersController extends BaseController
{
    private UsersAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new UsersAPI();
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/users - List all users
     * GET /api/users/{id} - Get single user
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/users - Create new user
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/users/{id} - Update user
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required for update');
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPut($resource, $id, $data, $segments);
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/users/{id} - Delete user
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Profile & Authentication
    // ========================================

    /**
     * GET /api/users/profile/get
     */
    public function getProfileGet($id = null, $data = [], $segments = [])
    {
        $userId = $id ?? $this->getCurrentUserId();
        $result = $this->api->getProfile($userId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/users/login
     */
    public function postLogin($id = null, $data = [], $segments = [])
    {
        $result = $this->api->login($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/users/password/change
     */
    public function putPasswordChange($id = null, $data = [], $segments = [])
    {
        $userId = $id ?? $this->getCurrentUserId();
        $result = $this->api->changePassword($userId, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/users/password/reset
     */
    public function postPasswordReset($id = null, $data = [], $segments = [])
    {
        $result = $this->api->resetPassword($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Roles & Permissions
    // ========================================

    /**
     * GET /api/users/roles/get
     */
    public function getRolesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRoles();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/users/permissions/get
     */
    public function getPermissionsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPermissions();
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/users/{id}/permissions/update
     */
    public function putPermissionsUpdate($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required');
        }
        
        $result = $this->api->updatePermissions($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/users/{id}/role/assign
     */
    public function postRoleAssign($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required');
        }
        
        $result = $this->api->assignRole($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/users/{id}/permission/assign
     */
    public function postPermissionAssign($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required');
        }
        
        $result = $this->api->assignPermission($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/users/{id}/role/main
     */
    public function getRoleMain($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required');
        }
        
        $result = $this->api->getMainRole($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/users/{id}/role/extra
     */
    public function getRoleExtra($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('User ID is required');
        }
        
        $result = $this->api->getExtraRoles($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Sidebar & UI
    // ========================================

    /**
     * GET /api/users/sidebar/items
     */
    public function getSidebarItems($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSidebarItems($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }

        return $this->success($result);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
