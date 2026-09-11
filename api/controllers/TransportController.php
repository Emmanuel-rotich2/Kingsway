<?php
namespace App\API\Controllers;

use App\API\Modules\transport\TransportAPI;
use App\API\Modules\finance\TransportBillingManager;
use App\API\Modules\transport\StudentTransportEntitlementManager;
use App\API\Services\payments\TransportPaymentService;
use App\Database\Database;
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
    private TransportBillingManager $billing;
    private StudentTransportEntitlementManager $entitlements;
    private TransportPaymentService $transportPayments;

    public function __construct() {
        parent::__construct();
        $this->api     = $this->contract('App\API\Modules\transport\TransportAPI');
        $this->billing = $this->contract('App\API\Modules\finance\TransportBillingManager');
        $this->entitlements = $this->contract('App\API\Modules\transport\StudentTransportEntitlementManager', Database::getInstance()->getConnection());
        $this->transportPayments = $this->contract('App\API\Services\payments\TransportPaymentService', Database::getInstance()->getConnection());
    }

    private function guardTransport(): ?array
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }

    private function guardTransportManage(): ?array
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (!$this->userHasAny(['transport_manage','transport_create','transport_edit','transport_delete','transport_assign'], [], ['director','school_administrator','school_accountant','transport_manager','transport_officer','admin'])) {
            return $this->forbidden('Transport management permission is required');
        }
        return null;
    }

    private function guardTransportAttendance(): ?array
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (!$this->userHasAny(['transport_attendance_mark','transport_manage'], [], ['driver','transport_officer','transport_manager','school_administrator','director','admin'])) {
            return $this->forbidden('Transport attendance permission is required');
        }
        return null;
    }

    private function guardTransportFinance(): ?array
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (!$this->userHasAnyRole(['director', 'school_administrator', 'school_accountant', 'accountant', 'admin'])) {
            return $this->forbidden('Only authorized transport finance staff may manage entitlements and payments');
        }
        return null;
    }

    public function index()
    {
        return $this->success(['message' => 'Transport API is running']);
    }

    public function get($id = null, $data = [], $segments = [])
    {
        // GET /api/transport — return summary of routes, vehicles, students
        $result = $this->api->getSummary();
        return $this->handleResponse($result);
    }

    /**
     * POST /api/transport/verify-student
     * Verify student by admission number or phone (for transport payments)
     */
    public function postVerifyStudent($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
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
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getRoute($id);
        return $this->handleResponse($result);
    }
    public function getAllRoutes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getAllRoutes();
        return $this->handleResponse($result);
    }
    public function postTransportRoute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->createRoute($data);
        return $this->handleResponse($result);
    }
    public function putTransportRoute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->updateRoute($id, $data);
        return $this->handleResponse($result);
    }
    public function deleteTransportRoute($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->deleteRoute($id);
        return $this->handleResponse($result);
    }

    // STOP ENDPOINTS
    public function getTransportStop($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getStop($id);
        return $this->handleResponse($result);
    }
    public function getAllStops($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getAllStops();
        return $this->handleResponse($result);
    }
    public function postTransportStop($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->createStop($data);
        return $this->handleResponse($result);
    }
    public function putTransportStop($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->updateStop($id, $data);
        return $this->handleResponse($result);
    }
    public function deleteTransportStop($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->deleteStop($id);
        return $this->handleResponse($result);
    }
    public function putRouteStops($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        if (!$id || !isset($data['stops']) || !is_array($data['stops'])) return $this->badRequest('route_id and stops are required');
        return $this->success($this->api->syncRouteStops((int)$id, $data['stops']), 'Route pickup/drop-off points saved');
    }

    public function getAllVehicles($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        return $this->handleResponse($this->api->getAllVehicles());
    }
    public function postTransportVehicle($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        return $this->handleResponse($this->api->createVehicle($data));
    }
    public function putTransportVehicle($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        return $this->handleResponse($this->api->updateVehicle($id, $data));
    }
    public function deleteTransportVehicle($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        return $this->handleResponse($this->api->deleteVehicle($id));
    }
    public function putVehicleRoutes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        if (!$id || !isset($data['route_ids']) || !is_array($data['route_ids'])) return $this->badRequest('vehicle_id and route_ids are required');
        return $this->success($this->api->syncVehicleRoutes((int)$id, $data['route_ids']), 'Vehicle route allocation saved');
    }

    // VEHICLE ENDPOINTS
    public function getTransportVehicle($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getVehicle($id);
        return $this->handleResponse($result);
    }

    // DRIVER ENDPOINTS
    public function getTransportDriver($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getDriver($id);
        return $this->handleResponse($result);
    }
    public function getAllDrivers($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $result = $this->api->getAllDrivers();
        return $this->handleResponse($result);
    }
    public function postTransportDriver($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->createDriver($data);
        return $this->handleResponse($result);
    }
    public function putTransportDriver($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->updateDriver($id, $data);
        return $this->handleResponse($result);
    }
    public function deleteTransportDriver($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        $result = $this->api->deleteDriver($id);
        return $this->handleResponse($result);
    }
    public function putDriverRoutes($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        if (!$id || !isset($data['route_ids']) || !is_array($data['route_ids'])) return $this->badRequest('driver_id and route_ids are required');
        return $this->success($this->api->syncDriverRoutes((int)$id, $data['route_ids']), 'Driver route schedule saved');
    }
    public function postDriverAssign($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        if (empty($data['driver_id']) || empty($data['route_id'])) {
            return $this->badRequest('driver_id and route_id are required');
        }
        $result = $this->api->assignDriverToRoute($data['driver_id'], $data['route_id']);
        return $this->handleResponse($result);
    }

    // ASSIGNMENT ENDPOINTS
    public function postAssignStudent($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        if (empty($data['student_id']) || empty($data['route_id'])) {
            return $this->badRequest('student_id and route_id are required');
        }
        $result = $this->api->assignStudent($data['student_id'], $data['route_id'], $data['stop_id'] ?? null, $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }
    public function postWithdrawAssignment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportManage()) return $guard;
        if (empty($data['student_id'])) {
            return $this->badRequest('student_id is required');
        }
        $result = $this->api->withdrawAssignment($data['student_id'], $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }
    public function getAssignments($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAssignments($data['student_id'] ?? null);
        return $this->handleResponse($result);
    }
    public function getStudentsByRoute($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStudentsByRoute($data['route_id'] ?? null, $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }

    // PAYMENT ENDPOINTS
    public function postRecordPayment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (empty($data['student_id']) || empty($data['amount']) || empty($data['financial_account_id'])) {
            return $this->badRequest('student_id, amount and financial_account_id are required');
        }
        $result = $this->api->recordPayment($data['student_id'], $data['amount'], $data['month'] ?? null, $data['year'] ?? null, $data['payment_date'] ?? null, $data['payment_method'] ?? null, $data['transaction_id'] ?? null, $data['financial_account_id'], (int)$this->getUserId());
        return $this->handleResponse($result);
    }
    public function putPaymentStatus($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (empty($data['status'])) {
            return $this->badRequest('status is required');
        }
        $result = $this->api->updatePaymentStatus($id, $data['status']);
        return $this->handleResponse($result);
    }
    public function getPayments($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPayments($data['student_id'] ?? null);
        return $this->handleResponse($result);
    }
    public function getPaymentSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPaymentSummary($data['student_id'] ?? null);
        return $this->handleResponse($result);
    }
    public function getRoutePaymentSummary($id = null, $data = [], $segments = [])
    {
        $routeId = $data['route_id'] ?? $id ?? null;
        if (!$routeId) {
            return $this->badRequest('route_id is required');
        }
        $result = $this->api->getRoutePaymentSummary($routeId, $data['month'] ?? null, $data['year'] ?? null);
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
        $studentId = $data['student_id'] ?? $id ?? null;
        if (!$studentId) {
            return $this->badRequest('student_id is required');
        }
        $result = $this->api->checkStatus($studentId, $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }
    public function getCurrentStatus($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getCurrentStatus($data['student_id']);
        return $this->handleResponse($result);
    }
    public function getFullStatus($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        if (!$studentId) {
            return $this->badRequest('student_id is required');
        }
        $result = $this->api->getFullStatus($studentId, $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }
    public function getRouteManifest($id = null, $data = [], $segments = [])
    {
        $routeId = $data['route_id'] ?? $id ?? null;
        if (!$routeId) {
            return $this->badRequest('route_id is required');
        }
        $result = $this->api->getRouteManifest($routeId, $data['month'] ?? null, $data['year'] ?? null);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/transport/driver-manifest
     * Live route manifest for the authenticated driver/device.
     */
    public function getDriverManifest($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportAttendance()) return $guard;
        $userId = $this->getCurrentUserId();
        if (!$userId) return $this->unauthorized('Authentication required');
        $date = (string)($data['date'] ?? date('Y-m-d'));
        $tripSession = (string)($data['trip_session'] ?? 'morning_pickup');
        try {
            return $this->success(
                $this->api->getDriverManifest((int)$userId, $date, $tripSession),
                'Driver manifest retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[TransportController] driver manifest failed: ' . $e->getMessage());
            return $this->serverError('An internal error occurred.');
        }
    }
    public function getStudentSummary($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        if (!$studentId) {
            return $this->badRequest('student_id is required');
        }
        $result = $this->api->getStudentSummary($studentId);
        return $this->handleResponse($result);
    }
    public function getRouteSummary($id = null, $data = [], $segments = [])
    {
        $routeId = $data['route_id'] ?? $id ?? null;
        if (!$routeId) {
            return $this->badRequest('route_id is required');
        }
        $result = $this->api->getRouteSummary($routeId, $data['month'] ?? null, $data['year'] ?? null);
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
        if ($guard = $this->guardTransport()) return $guard;
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
        if ($guard = $this->guardTransport()) return $guard;
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
        if ($guard = $this->guardTransportManage()) return $guard;
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
        if ($guard = $this->guardTransportManage()) return $guard;
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
        if ($guard = $this->guardTransport()) return $guard;
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
     * GET /api/transport/my-route
     * Returns the route assigned to the authenticated driver
     */
    public function getMyRoute($id = null, $data = [], $segments = [])
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return $this->unauthorized('Authentication required');
        }

        try {
            return $this->success(
                $this->api->getMyRoute((int) $userId),
                'Driver route context retrieved'
            );
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * GET /api/transport/my-vehicle
     * Returns the vehicle assigned to the authenticated driver
     */
    public function getMyVehicle($id = null, $data = [], $segments = [])
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return $this->unauthorized('Authentication required');
        }

        try {
            return $this->success(
                $this->api->getMyVehicle((int) $userId),
                'Driver vehicle retrieved'
            );
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * POST /api/transport/attendance
     * Records student attendance for a route
     * Body: { date, present_student_ids: [] }
     */
    public function postAttendance($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportAttendance()) return $guard;
        $userId = $this->getCurrentUserId();
        $date = $data['date'] ?? date('Y-m-d');
        $presentIds = $data['present_student_ids'] ?? [];

        if (empty($presentIds)) {
            return $this->success(['recorded' => 0, 'message' => 'No student IDs provided']);
        }
        $routeId = (int)($data['route_id'] ?? 0);
        $isLeadership = $this->userHasAnyRole(['director','school_administrator','transport_manager','transport_officer','admin']);
        if ($routeId > 0 && !$isLeadership && !$this->api->userHasAssignedRoute((int)$userId, $routeId)) {
            return $this->forbidden('You may only mark attendance for your assigned route');
        }
        $result = $this->api->recordStudentAttendance($userId, $date, $presentIds, $data);
        return $this->handleResponse($result);
    }

    // ================================================================
    // TRANSPORT BILLING ENDPOINTS
    // ================================================================

    /**
     * POST /api/transport/entitlements
     * Create or update date-bounded transport coverage.
     * period_type: day|week|month|term|year|custom
     */
    public function postEntitlements($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportFinance()) return $guard;
        try {
            $userId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
            return $this->created(
                $this->entitlements->createEntitlement($data, $userId),
                'Transport entitlement saved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[TransportController] entitlement create failed: ' . $e->getMessage());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/transport/enrollments */
    public function postEnrollments($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportFinance()) return $guard;
        try {
            $userId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
            return $this->created($this->entitlements->enrollStudent($data, $userId), 'Student transport enrollment saved');
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[TransportController] transport enrollment failed: ' . $e->getMessage());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/transport/entitlements-payment/{entitlementId} */
    public function postEntitlementsPayment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportFinance()) return $guard;
        if (!$id) return $this->badRequest('entitlement_id required');
        try {
            $userId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
            return $this->created(
                $this->entitlements->recordPayment((int)$id, $data, $userId),
                'Transport payment allocated to entitlement'
            );
        } catch (\RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[TransportController] entitlement payment failed: ' . $e->getMessage());
            return $this->serverError('An internal error occurred.');
        }
    }

    /** POST /api/transport/payment-intents */
    public function postPaymentIntents($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportFinance()) return $guard;
        try {
            $userId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
            return $this->created($this->transportPayments->initiate($data, $userId), 'Transport payment request submitted');
        } catch (\RuntimeException $e) { return $this->badRequest($e->getMessage()); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[TransportController] payment intent failed: '.$e->getMessage()); return $this->serverError('An internal error occurred.'); }
    }

    /** POST /api/transport/payment-intents/{id}/confirm */
    public function postPaymentIntentConfirm($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportFinance()) return $guard;
        if (!$id) return $this->badRequest('payment intent id required');
        try {
            $userId = (int)($this->user['user_id'] ?? $this->user['id'] ?? 0);
            return $this->success($this->transportPayments->confirmManual((int)$id, $userId), 'Transport payment confirmed');
        } catch (\RuntimeException $e) { return $this->badRequest($e->getMessage()); }
        catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[TransportController] payment confirmation failed: '.$e->getMessage()); return $this->serverError('An internal error occurred.'); }
    }

    /** GET /api/transport/payment-intents/{id} */
    public function getPaymentIntents($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransportFinance()) return $guard;
        if (!$id) return $this->badRequest('payment intent id required');
        return $this->success($this->transportPayments->getIntent((int)$id));
    }

    /** GET /api/transport/entitlement-access/{studentId} */
    public function getEntitlementAccess($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $studentId = (int)($id ?: ($data['student_id'] ?? 0));
        $routeId = (int)($_GET['route_id'] ?? $data['route_id'] ?? 0);
        $date = (string)($_GET['date'] ?? $data['date'] ?? date('Y-m-d'));
        if (!$studentId || !$routeId) return $this->badRequest('student_id and route_id are required');
        return $this->success($this->entitlements->getAccess($studentId, $routeId, $date));
    }

    /** POST /api/transport/subscriptions — subscribe student to route */
    public function postSubscriptions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        try {
            $data['subscribed_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
            $result = $this->billing->subscribe($data);
            return $this->success($result, 'Student subscribed to transport');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** DELETE /api/transport/subscriptions/{id} — cancel subscription */
    public function deleteSubscriptions($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (!$id) return $this->badRequest('subscription_id required');
        $endMonth = $data['end_month'] ?? date('Y-m-01');
        $userId   = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $ok = $this->billing->unsubscribe((int)$id, $endMonth, $userId);
        return $ok ? $this->success(null, 'Subscription cancelled') : $this->notFound('Subscription not found');
    }

    /** GET /api/transport/subscriptions?student_id=&route_id=&status= */
    public function getSubscriptions($id = null, $data = [], $segments = [])
    {
        $filters = [
            'student_id' => $_GET['student_id'] ?? $data['student_id'] ?? null,
            'route_id'   => $_GET['route_id']   ?? $data['route_id']   ?? null,
            'status'     => $_GET['status']      ?? $data['status']     ?? null,
        ];
        return $this->success($this->billing->getSubscriptions($filters));
    }

    /** POST /api/transport/bills-generate — generate monthly bills */
    public function postBillsGenerate($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $billingMonth = $data['billing_month'] ?? date('Y-m-01');
        $userId       = $this->user['user_id'] ?? $this->user['id'] ?? null;
        try {
            $result = $this->billing->generateMonthlyBills($billingMonth, $userId);
            return $this->success($result, 'Monthly bills generated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /** GET /api/transport/bills?billing_month=&student_id=&route_id=&status= */
    public function getBills($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $filters = [
            'billing_month'  => $_GET['billing_month']  ?? $data['billing_month']  ?? null,
            'student_id'     => $_GET['student_id']     ?? $data['student_id']     ?? null,
            'route_id'       => $_GET['route_id']       ?? $data['route_id']       ?? null,
            'payment_status' => $_GET['payment_status'] ?? $data['payment_status'] ?? null,
            'page'           => (int)($_GET['page']     ?? $data['page']  ?? 1),
            'limit'          => (int)($_GET['limit']    ?? $data['limit'] ?? 50),
        ];
        return $this->success($this->billing->getBills($filters));
    }

    /** GET /api/transport/bills-summary?billing_month=YYYY-MM-01 */
    public function getBillsSummary($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        $billingMonth = $_GET['billing_month'] ?? $data['billing_month'] ?? date('Y-m-01');
        return $this->success($this->billing->getMonthlyBillingSummary($billingMonth));
    }

    /** POST /api/transport/bills-record-payment/{id} */
    public function postBillsRecordPayment($id = null, $data = [], $segments = [])
    {
        if ($guard = $this->guardTransport()) return $guard;
        if (!$id) return $this->badRequest('bill_id required');
        $data['received_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        try {
            $result = $this->billing->recordTransportPayment((int)$id, $data);
            return $this->success($result, 'Payment recorded');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[TransportController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return $this->serverError('An internal error occurred.');
        }
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['user_id'] ?? $this->user['id'] ?? null;
    }
}
