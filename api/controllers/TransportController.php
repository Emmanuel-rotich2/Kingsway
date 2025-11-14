<?php
namespace App\API\Controllers;

use App\API\Modules\Transport\TransportAPI;
use Exception;

/**
 * TransportController - REST endpoints for transport management
 * Handles routes, vehicles, drivers, and student transport assignments
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class TransportController extends BaseController
{
    private TransportAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new TransportAPI();
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/transport - List all transport records
     * GET /api/transport/{id} - Get single transport record
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
     * POST /api/transport - Create new transport record
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
     * PUT /api/transport/{id} - Update transport record
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Transport ID is required for update');
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/transport/{id} - Delete transport record
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Transport ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Route Operations
    // ========================================

    /**
     * GET /api/transport/routes/get
     */
    public function getRoutesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRoutes($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/transport/routes/assign
     */
    public function postRoutesAssign($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignRoute($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Vehicle Operations
    // ========================================

    /**
     * GET /api/transport/vehicles/get
     */
    public function getVehiclesGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getVehicles($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/transport/vehicles/assign
     */
    public function postVehiclesAssign($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignVehicle($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Driver Operations
    // ========================================

    /**
     * GET /api/transport/drivers/get
     */
    public function getDriversGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getDrivers($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/transport/drivers/assign
     */
    public function postDriversAssign($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignDriver($data);
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
