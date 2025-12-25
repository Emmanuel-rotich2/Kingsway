<?php
namespace App\API\Controllers;
use Illuminate\Http\Request;

use Exception;

use App\API\Modules\system\SystemAPI;
use App\API\Modules\maintenance\MaintenanceAPI;

class MaintenanceController extends BaseController
{
    private $api;
    private $systemApi;

    public function __construct() {
        parent::__construct();
        $this->api = new MaintenanceAPI();
        $this->systemApi = new SystemAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Maintenance API is running']);
    }

    // GET /api/maintenance - List all maintenance records (equipment by default)
    public function getMaintenance($id = null, $data = [], $segments = [])
    {
        // If ID provided, get specific record
        if ($id) {
            $result = $this->api->getEquipment($id);
        } else {
            // List all equipment maintenance with optional filters
            $filters = $data;
            $result = $this->api->listEquipment($filters);
        }
        return $this->handleResponse($result);
    }

    // POST /api/maintenance - Create new maintenance record (equipment by default)
    public function postMaintenance($id = null, $data = [], $segments = [])
    {
        // Determine type: equipment or vehicle
        $type = $data['type'] ?? 'equipment';

        if ($type === 'vehicle') {
            $result = $this->api->createVehicle($data);
        } else {
            $result = $this->api->createEquipment($data);
        }
        return $this->handleResponse($result);
    }

    // PUT /api/maintenance/{id} - Update maintenance record
    public function putMaintenance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('ID is required for update');
        }

        // Determine type: equipment or vehicle
        $type = $data['type'] ?? 'equipment';

        if ($type === 'vehicle') {
            $result = $this->api->updateVehicle($id, $data);
        } else {
            $result = $this->api->updateEquipment($id, $data);
        }
        return $this->handleResponse($result);
    }

    // DELETE /api/maintenance/{id} - Delete maintenance record
    public function deleteMaintenance($id = null, $data = [], $segments = [])
    {
        if (!$id) {
            return $this->badRequest('ID is required for deletion');
        }

        // Determine type: equipment or vehicle
        $type = $data['type'] ?? 'equipment';

        if ($type === 'vehicle') {
            $result = $this->api->deleteVehicle($id);
        } else {
            $result = $this->api->deleteEquipment($id);
        }
        return $this->handleResponse($result);
    }

    // GET /api/maintenance/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->readLogs($data);
        return $this->handleResponse($result);
    }

    // POST /api/maintenance/logs/clear
    public function postLogsClear($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->clearLogs();
        return $this->handleResponse($result);
    }

    // POST /api/maintenance/logs/archive
    public function postLogsArchive($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->archiveLogs();
        return $this->handleResponse($result);
    }

    // GET /api/maintenance/config
    public function getConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->getSchoolConfig($id);
        return $this->handleResponse($result);
    }

    // POST /api/maintenance/config
    public function postConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->setSchoolConfig($data);
        return $this->handleResponse($result);
    }

    /**
     * Unified API response handler (matches StudentsController)
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
}
