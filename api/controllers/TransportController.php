<?php
namespace App\API\Controllers;

use App\API\Modules\transport\TransportAPI;
use App\API\Modules\Finance\TransportBillingManager;
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

    public function __construct() {
        parent::__construct();
        $this->api     = new TransportAPI();
        $this->billing = new TransportBillingManager();
    }

    public function index()
    {
        return $this->success(['message' => 'Transport API is running']);
    }

    public function get($id = null, $data = [], $segments = [])
    {
        // GET /api/transport — return summary of routes, vehicles, students
        try {
            $routes   = (int)$this->db->query("SELECT COUNT(*) FROM transport_routes WHERE status='active'")->fetchColumn();
            $vehicles = (int)$this->db->query("SELECT COUNT(*) FROM transport_vehicles WHERE status='active'")->fetchColumn();
            $students = (int)$this->db->query("SELECT COUNT(*) FROM transport_subscriptions WHERE status='active'")->fetchColumn();
            return $this->success(['routes' => $routes, 'vehicles' => $vehicles, 'active_subscriptions' => $students]);
        } catch (Exception $e) {
            return $this->serverError($e->getMessage());
        }
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
     * GET /api/transport/my-route
     * Returns the route assigned to the authenticated driver
     */
    public function getMyRoute($id = null, $data = [], $segments = [])
    {
        $userId = $this->getCurrentUserId();
        if (!$userId) {
            return $this->success(['route' => null, 'message' => 'No user context']);
        }
        try {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("
                SELECT r.* FROM transport_routes r
                INNER JOIN route_drivers rd ON rd.route_id = r.id
                WHERE rd.driver_id = :uid
                ORDER BY r.id DESC LIMIT 1
            ");
            $stmt->execute([':uid' => $userId]);
            $route = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->success($route ?: null);
        } catch (\Exception $e) {
            return $this->success(null);
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
            return $this->success(['vehicle' => null, 'message' => 'No user context']);
        }
        try {
            $db = \App\Database\Database::getInstance();
            $stmt = $db->prepare("
                SELECT v.* FROM vehicles v
                INNER JOIN driver_vehicles dv ON dv.vehicle_id = v.id
                WHERE dv.driver_id = :uid
                ORDER BY v.id DESC LIMIT 1
            ");
            $stmt->execute([':uid' => $userId]);
            $vehicle = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $this->success($vehicle ?: null);
        } catch (\Exception $e) {
            return $this->success(null);
        }
    }

    /**
     * POST /api/transport/attendance
     * Records student attendance for a route
     * Body: { date, present_student_ids: [] }
     */
    public function postAttendance($id = null, $data = [], $segments = [])
    {
        $userId = $this->getCurrentUserId();
        $date = $data['date'] ?? date('Y-m-d');
        $presentIds = $data['present_student_ids'] ?? [];

        if (empty($presentIds)) {
            return $this->success(['recorded' => 0, 'message' => 'No student IDs provided']);
        }
        try {
            $db = \App\Database\Database::getInstance();
            $recorded = 0;
            foreach ($presentIds as $studentId) {
                $stmt = $db->prepare("
                    INSERT INTO transport_attendance (driver_id, student_id, date, status, created_at)
                    VALUES (:did, :sid, :date, 'present', NOW())
                    ON DUPLICATE KEY UPDATE status = 'present', updated_at = NOW()
                ");
                $stmt->execute([
                    ':did'  => $userId,
                    ':sid'  => (int) $studentId,
                    ':date' => $date,
                ]);
                $recorded++;
            }
            return $this->success(['recorded' => $recorded, 'date' => $date]);
        } catch (\Exception $e) {
            return $this->success(['recorded' => 0, 'message' => 'Table not available']);
        }
    }

    // ================================================================
    // TRANSPORT BILLING ENDPOINTS
    // ================================================================

    /** POST /api/transport/subscriptions — subscribe student to route */
    public function postSubscriptions($id = null, $data = [], $segments = [])
    {
        try {
            $data['subscribed_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
            $result = $this->billing->subscribe($data);
            return $this->success($result, 'Student subscribed to transport');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            return $this->serverError('Subscription failed: ' . $e->getMessage());
        }
    }

    /** DELETE /api/transport/subscriptions/{id} — cancel subscription */
    public function deleteSubscriptions($id = null, $data = [], $segments = [])
    {
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
        $billingMonth = $data['billing_month'] ?? date('Y-m-01');
        $userId       = $this->user['user_id'] ?? $this->user['id'] ?? null;
        try {
            $result = $this->billing->generateMonthlyBills($billingMonth, $userId);
            return $this->success($result, 'Monthly bills generated');
        } catch (Exception $e) {
            return $this->serverError('Bill generation failed: ' . $e->getMessage());
        }
    }

    /** GET /api/transport/bills?billing_month=&student_id=&route_id=&status= */
    public function getBills($id = null, $data = [], $segments = [])
    {
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
        $billingMonth = $_GET['billing_month'] ?? $data['billing_month'] ?? date('Y-m-01');
        return $this->success($this->billing->getMonthlyBillingSummary($billingMonth));
    }

    /** POST /api/transport/bills-record-payment/{id} */
    public function postBillsRecordPayment($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('bill_id required');
        $data['received_by'] = $this->user['user_id'] ?? $this->user['id'] ?? null;
        try {
            $result = $this->billing->recordTransportPayment((int)$id, $data);
            return $this->success($result, 'Payment recorded');
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            return $this->serverError('Payment recording failed: ' . $e->getMessage());
        }
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
