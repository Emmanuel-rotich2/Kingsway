<?php
namespace App\API\Modules\maintenance;

use App\API\Includes\BaseAPI;
use Exception;

/**
 * Maintenance API - Central Coordinator
 * Coordinates all maintenance operations (equipment and vehicle) through specialized managers
 */
class MaintenanceAPI extends BaseAPI
{
    private $equipmentManager;
    private $vehicleManager;

    public function __construct()
    {
        parent::__construct('maintenance');
        $this->equipmentManager = new EquipmentManager();
        $this->vehicleManager = new VehicleManager();
    }

    // ==================== EQUIPMENT MAINTENANCE ====================

    /**
     * List all equipment maintenance records
     */
    public function listEquipment($filters = [])
    {
        return $this->equipmentManager->listEquipment($filters);
    }

    /**
     * Get a single equipment maintenance record
     */
    public function getEquipment($id)
    {
        return $this->equipmentManager->getEquipment($id);
    }

    /**
     * Create new equipment maintenance record
     */
    public function createEquipment($data)
    {
        return $this->equipmentManager->createEquipment($data);
    }

    /**
     * Update equipment maintenance record
     */
    public function updateEquipment($id, $data)
    {
        return $this->equipmentManager->updateEquipment($id, $data);
    }

    /**
     * Delete equipment maintenance record
     */
    public function deleteEquipment($id)
    {
        return $this->equipmentManager->deleteEquipment($id);
    }

    /**
     * Get overdue equipment maintenance
     */
    public function getOverdueEquipment()
    {
        return $this->equipmentManager->getOverdueEquipment();
    }

    /**
     * Update equipment status
     */
    public function updateEquipmentStatus($id, $status)
    {
        return $this->equipmentManager->updateStatus($id, $status);
    }

    // ==================== VEHICLE MAINTENANCE ====================

    /**
     * List all vehicle maintenance records
     */
    public function listVehicles($filters = [])
    {
        return $this->vehicleManager->listVehicles($filters);
    }

    /**
     * Get a single vehicle maintenance record
     */
    public function getVehicle($id)
    {
        return $this->vehicleManager->getVehicle($id);
    }

    /**
     * Create new vehicle maintenance record
     */
    public function createVehicle($data)
    {
        return $this->vehicleManager->createVehicle($data);
    }

    /**
     * Update vehicle maintenance record
     */
    public function updateVehicle($id, $data)
    {
        return $this->vehicleManager->updateVehicle($id, $data);
    }

    /**
     * Delete vehicle maintenance record
     */
    public function deleteVehicle($id)
    {
        return $this->vehicleManager->deleteVehicle($id);
    }

    /**
     * Get vehicle maintenance cost summary
     */
    public function getVehicleCostSummary($filters = [])
    {
        return $this->vehicleManager->getCostSummary($filters);
    }

    /**
     * Get upcoming vehicle maintenance schedule
     */
    public function getUpcomingVehicleSchedule($daysAhead = 30)
    {
        return $this->vehicleManager->getUpcomingSchedule($daysAhead);
    }

    // ==================== LOGS ====================

    /**
     * Read maintenance logs (delegated to SystemAPI)
     */
    public function readLogs($data = [])
    {
        // This will be called from MaintenanceController
        // Actual implementation is in SystemAPI
        $systemAPI = new \App\API\Modules\system\SystemAPI();
        return $systemAPI->readLogs($data);
    }

    /**
     * Clear maintenance logs (delegated to SystemAPI)
     */
    public function clearLogs()
    {
        // This will be called from MaintenanceController
        // Actual implementation is in SystemAPI
        $systemAPI = new \App\API\Modules\system\SystemAPI();
        return $systemAPI->clearLogs();
    }

    /**
     * Archive maintenance logs (delegated to SystemAPI)
     */
    public function archiveLogs()
    {
        // This will be called from MaintenanceController
        // Actual implementation is in SystemAPI
        $systemAPI = new \App\API\Modules\system\SystemAPI();
        return $systemAPI->archiveLogs();
    }

    // ==================== CONFIGURATION ====================

    /**
     * Get school configuration (delegated to SystemAPI)
     */
    public function getConfig($id = null)
    {
        // This will be called from MaintenanceController
        // Actual implementation is in SystemAPI
        $systemAPI = new \App\API\Modules\system\SystemAPI();
        return $systemAPI->getSchoolConfig($id);
    }

    /**
     * Set school configuration (delegated to SystemAPI)
     * This is for maintenance-related configuration settings
     */
    public function setConfig($data)
    {
        // This will be called from MaintenanceController
        // Actual implementation is in SystemAPI
        $systemAPI = new \App\API\Modules\system\SystemAPI();
        return $systemAPI->setSchoolConfig($data);
    }

    /**
     * Dashboard summary - Get maintenance overview
     */
    public function getDashboardSummary()
    {
        try {
            $equipmentRes = $this->equipmentManager->getOverdueEquipment();
            $vehicleRes = $this->vehicleManager->getUpcomingVehicleSchedule();

            return [
                'success' => true,
                'data' => [
                    'overdue_equipment' => $equipmentRes['data'] ?? [],
                    'upcoming_vehicle_maintenance' => $vehicleRes['data'] ?? [],
                    'overdue_count' => count($equipmentRes['data'] ?? []),
                    'upcoming_count' => count($vehicleRes['data'] ?? [])
                ]
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error generating dashboard summary: ' . $e->getMessage()
            ];
        }
    }
}
