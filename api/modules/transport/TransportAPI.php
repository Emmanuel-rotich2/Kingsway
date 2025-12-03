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

    protected $driverManager;
    protected $routeManager;
    protected $stopManager;
    protected $assignmentManager;
    protected $paymentManager;
    protected $statusManager;
    protected $vehicleManager;

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

    public function recordPayment($studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId)
    {
        return $this->paymentManager->recordPayment($studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId);
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


}
