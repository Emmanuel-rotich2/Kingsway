<?php
namespace App\API\Controllers;

use App\API\Modules\transport\TransportAPI;
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

    public function index()
    {
        return $this->success(['message' => 'Transport API is running']);
    }

    /**
     * POST /api/transport/verify-student
     * Verify student by admission number or phone (for transport payments)
     */
    public function postVerifyStudent($id = null, $data = [], $segments = [])
    {
        $admissionNo = $data['admission_no'] ?? null;
        $phone = $data['phone'] ?? null;
        if (!$admissionNo && !$phone) {
            return $this->badRequest('admission_no or phone is required');
        }
        $result = $this->api->verifyStudent($admissionNo, $phone);
        return $this->handleResponse($result);
    }


    // ========================================
    // SECTION 6: Exported TransportAPI Methods
    // ========================================

    // ROUTE ENDPOINTS
    public function getTransportRoute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRoute($id);
        return $this->handleResponse($result);
    }
    public function getAllRoutes($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllRoutes();
        return $this->handleResponse($result);
    }
    public function postTransportRoute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createRoute($data);
        return $this->handleResponse($result);
    }
    public function putTransportRoute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateRoute($id, $data);
        return $this->handleResponse($result);
    }
    public function deleteTransportRoute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteRoute($id);
        return $this->handleResponse($result);
    }

    // STOP ENDPOINTS
    public function getTransportStop($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStop($id);
        return $this->handleResponse($result);
    }
    public function getAllStops($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllStops();
        return $this->handleResponse($result);
    }
    public function postTransportStop($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createStop($data);
        return $this->handleResponse($result);
    }
    public function putTransportStop($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateStop($id, $data);
        return $this->handleResponse($result);
    }
    public function deleteTransportStop($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteStop($id);
        return $this->handleResponse($result);
    }

    // VEHICLE ENDPOINTS
    public function getTransportVehicle($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getVehicle($id);
        return $this->handleResponse($result);
    }

    // DRIVER ENDPOINTS
    public function getTransportDriver($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getDriver($id);
        return $this->handleResponse($result);
    }
    public function getAllDrivers($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllDrivers();
        return $this->handleResponse($result);
    }
    public function postTransportDriver($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createDriver($data);
        return $this->handleResponse($result);
    }
    public function putTransportDriver($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateDriver($id, $data);
        return $this->handleResponse($result);
    }
    public function deleteTransportDriver($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteDriver($id);
        return $this->handleResponse($result);
    }
    public function postDriverAssign($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignDriverToRoute($data['driver_id'], $data['route_id']);
        return $this->handleResponse($result);
    }

    // ASSIGNMENT ENDPOINTS
    public function postAssignStudent($id = null, $data = [], $segments = [])
    {
        $result = $this->api->assignStudent($data['student_id'], $data['route_id'], $data['stop_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }
    public function postWithdrawAssignment($id = null, $data = [], $segments = [])
    {
        $result = $this->api->withdrawAssignment($data['student_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }
    public function getAssignments($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAssignments($data['student_id']);
        return $this->handleResponse($result);
    }
    public function getStudentsByRoute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStudentsByRoute($data['route_id'], $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }

    // PAYMENT ENDPOINTS
    public function postRecordPayment($id = null, $data = [], $segments = [])
    {
        $result = $this->api->recordPayment($data['student_id'], $data['amount'], $data['month'], $data['year'], $data['payment_date'], $data['payment_method'], $data['transaction_id']);
        return $this->handleResponse($result);
    }
    public function putPaymentStatus($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updatePaymentStatus($id, $data['status']);
        return $this->handleResponse($result);
    }
    public function getPayments($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPayments($data['student_id']);
        return $this->handleResponse($result);
    }
    public function getPaymentSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPaymentSummary($data['student_id']);
        return $this->handleResponse($result);
    }
    public function getRoutePaymentSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRoutePaymentSummary($data['route_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }
    public function getAllArrearsCredits($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllArrearsCredits();
        return $this->handleResponse($result);
    }

    // STATUS & MANIFEST ENDPOINTS
    public function getCheckStatus($id = null, $data = [], $segments = [])
    {
        $result = $this->api->checkStatus($data['student_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }
    public function getCurrentStatus($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurrentStatus($data['student_id']);
        return $this->handleResponse($result);
    }
    public function getFullStatus($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getFullStatus($data['student_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }
    public function getRouteManifest($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRouteManifest($data['route_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }
    public function getStudentSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStudentSummary($data['student_id']);
        return $this->handleResponse($result);
    }
    public function getRouteSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRouteSummary($data['route_id'], $data['month'], $data['year']);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/transport - Create new transport record
     */
    // public function post($id = null, $data = [], $segments = [])
    // {
    //     if (!empty($segments)) {
    //         $resource = array_shift($segments);
    //         return $this->routeNestedPost($resource, $id, $data, $segments);
    //     }
    //     $result = $this->api->create($data);
    //     return $this->handleResponse($result);
    // }

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
