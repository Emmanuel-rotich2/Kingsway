<?php
namespace App\API\Controllers;
use Illuminate\Http\Request;

use Exception;

use App\API\Modules\System\SystemAPI;

class MaintenanceController extends BaseController
{
    private $api;
    private $systemApi;

    public function __construct() {
        parent::__construct();
        $this->systemApi = new SystemAPI();
    }

    // GET /api/maintenance
    public function get($id = null, $data = [], $segments = []) {
        // Not implemented in SystemAPI, return error
        return $this->badRequest('Not supported');
    }

    // POST /api/maintenance
    public function post($id = null, $data = [], $segments = [])
    {
        // Not implemented in SystemAPI, return error
        return $this->badRequest('Not supported');
    }

    // PUT /api/maintenance/{id}
    public function put($id = null, $data = [], $segments = [])
    {
        // Not implemented in SystemAPI, return error
        return $this->badRequest('Not supported');
    }

    // DELETE /api/maintenance/{id}
    public function delete($id = null, $data = [], $segments = [])
    {
        // Not implemented in SystemAPI, return error
        return $this->badRequest('Not supported');
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
