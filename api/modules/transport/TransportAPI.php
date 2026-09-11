<?php
namespace App\API\Modules\transport;

use App\API\Includes\BaseAPI;
use App\API\Modules\transport\StudentTransportPaymentManager;
use App\API\Modules\transport\StudentTransportStatusManager;
use PDO;
use Exception;


class TransportAPI extends BaseAPI
{
    // Verify student by admission number or phone (for payment/registration)
    public function verifyStudent($admissionNo = null, $phone = null)
    {
        return $this->paymentManager->verifyStudent($admissionNo, $phone);
    }

    // Summary counts for the transport landing view.
    // Live tables: transport_routes / transport_vehicles / student_transport_assignments
    // (legacy `transport_subscriptions` does not exist in the live schema).
    public function getSummary()
    {
        try {
            $routes   = (int) $this->db->query("SELECT COUNT(*) FROM transport_routes WHERE status='active'")->fetchColumn();
            $vehicles = (int) $this->db->query("SELECT COUNT(*) FROM transport_vehicles WHERE status='active'")->fetchColumn();
            $students = (int) $this->db->query("SELECT COUNT(*) FROM student_transport_assignments WHERE status='active'")->fetchColumn();
            return $this->successResponse([
                'routes'   => $routes,
                'vehicles' => $vehicles,
                'active_subscriptions' => $students,
            ]);
        } catch (Exception $e) {
            $this->logError($e, 'TransportAPI::getSummary');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // Mark a batch of students present on a transport trip.
    // Live table: student_transport_attendance (UNIQUE student_id, attendance_date, trip_session).
    // route_id defaults to the authenticated driver's assigned route when not supplied.
    public function recordStudentAttendance($userId, $date, $presentIds, $data = [])
    {
        try {
            $routeId = isset($data['route_id']) ? (int) $data['route_id'] : 0;
            if ($routeId <= 0) {
                $routeStmt = $this->db->prepare(
                    "SELECT tvr.route_id
                     FROM users u
                     INNER JOIN staff s ON s.person_id = u.person_id
                     INNER JOIN transport_vehicles v ON v.driver_id = s.id
                     INNER JOIN transport_vehicle_routes tvr
                             ON tvr.vehicle_id = v.id AND tvr.status = 'active'
                     WHERE u.id = ? AND s.status IN ('active', 'on_leave')
                     LIMIT 1"
                );
                $routeStmt->execute([(int) $userId]);
                $routeId = (int) $routeStmt->fetchColumn();
            }
            if ($routeId <= 0) throw new \InvalidArgumentException('A route is required before transport attendance can be marked.');

            $tripSession = $data['trip_session'] ?? 'morning_pickup';
            $validSessions = ['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip'];
            if (!in_array($tripSession, $validSessions, true)) {
                $tripSession = 'morning_pickup';
            }

            $status = $data['status'] ?? 'picked_up';
            if ($status === 'present') {
                $status = 'picked_up';
            }
            $validStatuses = ['pending', 'picked_up', 'dropped_off', 'absent', 'excused', 'not_riding'];
            if (!in_array($status, $validStatuses, true)) {
                $status = 'picked_up';
            }

            $stmt = $this->db->prepare(
                "INSERT INTO student_transport_attendance
                    (student_id, route_id, attendance_date, trip_session, status, marked_time, marked_by)
                 VALUES (?, ?, ?, ?, ?, CURTIME(), ?)
                 ON DUPLICATE KEY UPDATE route_id = VALUES(route_id), status = VALUES(status),
                     marked_time = CURTIME(), marked_by = VALUES(marked_by)"
            );
            $eligible = $this->db->prepare("SELECT COUNT(*) FROM student_transport_assignments WHERE student_id=? AND route_id=? AND status IN ('active','suspended')");
            $recorded = 0;
            $balances = [];
            $this->db->beginTransaction();
            foreach ($presentIds as $studentId) {
                $eligible->execute([(int)$studentId, $routeId]);
                if (!(int)$eligible->fetchColumn()) throw new \InvalidArgumentException('One or more selected learners are not active passengers on this route.');
                if (in_array($status, ['picked_up', 'dropped_off'], true)) {
                    $balances[(int)$studentId] = $this->entitlementManager->consumeSchoolDay((int)$studentId, $routeId, $date);
                }
                $stmt->execute([(int) $studentId, $routeId, $date, $tripSession, $status, (int) $userId]);
                $recorded++;
            }
            $this->db->commit();
            return $this->successResponse(['recorded' => $recorded, 'date' => $date, 'school_day_balances' => $balances]);
        } catch (\InvalidArgumentException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->errorResponse($e->getMessage(), 400);
        } catch (Exception $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->logError($e, 'TransportAPI::recordStudentAttendance');
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function userHasAssignedRoute(int $userId, int $routeId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM users u JOIN staff s ON s.person_id=u.person_id JOIN transport_vehicles v ON v.driver_id=s.id JOIN transport_vehicle_routes tvr ON tvr.vehicle_id=v.id AND tvr.status='active' WHERE u.id=? AND tvr.route_id=? AND s.status IN ('active','on_leave') LIMIT 1");
        $stmt->execute([$userId, $routeId]);
        return (bool)$stmt->fetchColumn();
    }

    protected $driverManager;
    protected $routeManager;
    protected $stopManager;
    protected $assignmentManager;
    protected $paymentManager;
    protected $statusManager;
    protected $vehicleManager;
    protected $entitlementManager;

    public function __construct()
    {
        parent::__construct('transport');
        $this->driverManager = new DriverManager($this->db);
        $this->routeManager = new RouteManager($this->db);
        $this->stopManager = new StopManager($this->db);
        $this->assignmentManager = new StudentTransportAssignmentManager($this->db);
        $this->paymentManager = new StudentTransportPaymentManager($this->db);
        $this->statusManager = new StudentTransportStatusManager($this->db);
        $this->vehicleManager = new VehicleManager($this->db);
        $this->entitlementManager = $this->contract('App\API\Modules\transport\StudentTransportEntitlementManager', $this->db);
    }

    // Assign a route to a student or entity (stub, adjust as needed)
    public function assignRoute($data)
    {
        // If this is meant to assign a student to a route, delegate to assignmentManager
        if (isset($data['student_id'], $data['route_id'], $data['stop_id'], $data['month'], $data['year'])) {
            return $this->assignmentManager->assignStudent($data['student_id'], $data['route_id'], $data['stop_id'], $data['month'], $data['year']);
        }
        // Otherwise, not implemented
        return ['success' => false, 'error' => 'assignRoute: Not enough data or not implemented.'];
    }

    // Assign driver to route
    public function assignDriverToRoute($driverId, $routeId)
    {
        return $this->driverManager->assignDriverToRoute($driverId, $routeId);
    }

    // Get all routes (alias for getAllRoutes)
    public function getRoutes($data = [])
    {
        return $this->getAllRoutes();
    }

    // Assign vehicle to route
    public function assignVehicle($data)
    {
        if (empty($data['vehicle_id']) || empty($data['route_id'])) {
            throw new \InvalidArgumentException('vehicle_id and route_id are required');
        }
        return $this->vehicleManager->assignVehicleToRoute($data['vehicle_id'], $data['route_id']);
    }

    // Get all vehicles (alias for getAllVehicles)
    public function getVehicles($data = [])
    {
        return $this->getAllVehicles();
    }


    // Get all drivers (alias for getAllDrivers)
    public function getDrivers($data = [])
    {
        return $this->getAllDrivers();
    }

    // Assign driver to route (for POST /drivers/assign)
    public function assignDriver($data)
    {
        if (empty($data['driver_id']) || empty($data['route_id'])) {
            throw new \InvalidArgumentException('driver_id and route_id are required');
        }
        return $this->driverManager->assignDriverToRoute($data['driver_id'], $data['route_id']);
    }

    // Generic update (not implemented, return error)
    public function update($id, $data)
    {
        return ['success' => false, 'error' => 'Generic update not implemented. Use specific update methods.'];
    }

    // Generic delete (not implemented, return error)
    public function delete($id)
    {
        return ['success' => false, 'error' => 'Generic delete not implemented. Use specific delete methods.'];
    }


    // Example: Expose all manager methods via API orchestration (add your routing logic as needed)
    public function getDriver($id)
    {
        return $this->driverManager->getDriver($id);
    }
    public function getAllDrivers()
    {
        return $this->driverManager->getAllDrivers();
    }

    public function createDriver($data)
    {
        return $this->driverManager->createDriver($data);
    }
    public function updateDriver($id, $data)
    {
        return $this->driverManager->updateDriver($id, $data);
    }
    public function deactivateDriver($id)
    {
        return $this->driverManager->deactivateDriver($id);
    }
    public function deleteDriver($id)
    {
        return $this->driverManager->deleteDriver($id);
    }
    public function syncDriverRoutes($driverId, array $routeIds)
    {
        return $this->driverManager->syncDriverRoutes((int)$driverId, $routeIds);
    }

    public function getRoute($id)
    {
        return $this->routeManager->getRoute($id);
    }
    public function getAllRoutes()
    {
        return $this->routeManager->getAllRoutes();
    }
    public function createRoute($data)
    {
        return $this->routeManager->createRoute($data);
    }
    public function updateRoute($id, $data)
    {
        return $this->routeManager->updateRoute($id, $data);
    }
    public function deactivateRoute($id)
    {
        return $this->routeManager->deactivateRoute($id);
    }
    public function deleteRoute($id)
    {
        return $this->routeManager->deleteRoute($id);
    }

    public function getStop($id)
    {
        return $this->stopManager->getStop($id);
    }
    public function getAllStops()
    {
        return $this->stopManager->getAllStops();
    }
    public function syncRouteStops($routeId, array $stops)
    {
        return $this->stopManager->syncRouteStops((int)$routeId, $stops);
    }
    public function createStop($data)
    {
        return $this->stopManager->createStop($data);
    }
    public function updateStop($id, $data)
    {
        return $this->stopManager->updateStop($id, $data);
    }
    public function deactivateStop($id)
    {
        return $this->stopManager->deactivateStop($id);
    }
    public function deleteStop($id)
    {
        return $this->stopManager->deleteStop($id);
    }

    public function assignStudent($studentId, $routeId, $stopId, $month, $year)
    {
        return $this->assignmentManager->assignStudent($studentId, $routeId, $stopId, $month, $year);
    }
    public function withdrawAssignment($studentId, $month, $year)
    {
        return $this->assignmentManager->withdrawAssignment($studentId, $month, $year);
    }
    public function getAssignments($studentId)
    {
        return $this->assignmentManager->getAssignments($studentId);
    }
    public function getStudentsByRoute($routeId, $month = null, $year = null)
    {
        return $this->assignmentManager->getStudentsByRoute($routeId, $month, $year);
    }

    public function recordPayment($studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId, $financialAccountId = null, $userId = 0)
    {
        return $this->paymentManager->recordPayment($studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId, $financialAccountId, $userId);
    }
    public function updatePaymentStatus($paymentId, $status)
    {
        return $this->paymentManager->updatePaymentStatus($paymentId, $status);
    }
    public function getPayments($studentId)
    {
        return $this->paymentManager->getPayments($studentId);
    }
    public function getPaymentSummary($studentId)
    {
        return $this->paymentManager->getPaymentSummary($studentId);
    }
    public function getRoutePaymentSummary($routeId, $month, $year)
    {
        return $this->paymentManager->getRoutePaymentSummary($routeId, $month, $year);
    }
    public function getAllArrearsCredits()
    {
        return $this->paymentManager->getAllArrearsCredits();
    }

    public function checkStatus($studentId, $month, $year)
    {
        return $this->statusManager->checkStatus($studentId, $month, $year);
    }
    public function getCurrentStatus($studentId)
    {
        return $this->statusManager->getCurrentStatus($studentId);
    }
    public function getFullStatus($studentId, $month, $year)
    {
        return $this->statusManager->getFullStatus($studentId, $month, $year);
    }
    public function getRouteManifest($routeId, $month, $year)
    {
        return $this->statusManager->getRouteManifest($routeId, $month, $year);
    }

    public function getDriverManifest(int $userId, string $date, string $tripSession): array
    {
        return $this->statusManager->getDriverManifest($userId, $date, $tripSession);
    }
    public function getStudentSummary($studentId)
    {
        return $this->statusManager->getStudentSummary($studentId);
    }
    public function getRouteSummary($routeId, $month, $year)
    {
        return $this->statusManager->getRouteSummary($routeId, $month, $year);
    }

    public function getVehicle($id)
    {
        return $this->vehicleManager->getVehicle($id);
    }
    public function getAllVehicles()
    {
        return $this->vehicleManager->getAllVehicles();
    }
    public function createVehicle($data)
    {
        return $this->vehicleManager->createVehicle($data);
    }
    public function updateVehicle($id, $data)
    {
        return $this->vehicleManager->updateVehicle($id, $data);
    }
    public function deactivateVehicle($id)
    {
        return $this->vehicleManager->deactivateVehicle($id);
    }
    public function deleteVehicle($id)
    {
        return $this->vehicleManager->deleteVehicle($id);
    }
    public function assignVehicleToRoute($vehicleId, $routeId)
    {
        return $this->vehicleManager->assignVehicleToRoute($vehicleId, $routeId);
    }
    public function syncVehicleRoutes($vehicleId, array $routeIds)
    {
        return $this->vehicleManager->syncVehicleRoutes((int)$vehicleId, $routeIds);
    }
    public function setVehicleStatus($id, $status)
    {
        return $this->vehicleManager->setVehicleStatus($id, $status);
    }
    public function addMaintenanceRecord($vehicleId, $description, $date)
    {
        return $this->vehicleManager->addMaintenanceRecord($vehicleId, $description, $date);
    }

    public function getMaintenanceRecords($vehicleId)
    {
        return $this->vehicleManager->getMaintenanceRecords($vehicleId);
    }


    public function getMyRoute($userId)
    {
        return $this->driverManager->getRouteForUser($userId);
    }

    public function getMyVehicle($userId)
    {
        return $this->driverManager->getVehicleForUser($userId);
    }

}
