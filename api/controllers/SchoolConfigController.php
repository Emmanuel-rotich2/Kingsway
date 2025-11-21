<?php
namespace App\API\Controllers;

use Exception;

use App\API\Modules\System\SystemAPI;

class SchoolConfigController extends BaseController
{

    private $systemApi;

    public function __construct() {
        parent::__construct();
        $this->systemApi = new SystemAPI();
    }

    // GET /api/school-config
    public function get($id = null, $data = [], $segments = []) {
        $result = $this->systemApi->getSchoolConfig($id);
        return $this->handleResponse($result);
    }

    // POST /api/school-config
    public function post($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->setSchoolConfig($data);
        return $this->handleResponse($result);
    }

    // PUT /api/school-config/{id}
    public function put($id = null, $data = [], $segments = [])
    {
        $data['id'] = $id;
        $result = $this->systemApi->setSchoolConfig($data);
        return $this->handleResponse($result);
    }

    // DELETE /api/school-config/{id}
    public function delete($id = null, $data = [], $segments = [])
    {
        // Not implemented in SystemAPI, return error
        return $this->badRequest('Delete not supported');
    }

    // GET /api/school-config/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->readLogs($data);
        return $this->handleResponse($result);
    }

    // POST /api/school-config/logs/clear
    public function postLogsClear($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->clearLogs();
        return $this->handleResponse($result);
    }

    // POST /api/school-config/logs/archive
    public function postLogsArchive($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->archiveLogs();
        return $this->handleResponse($result);
    }

    // GET /api/school-config/health
    public function getHealth($id = null, $data = [], $segments = [])
    {
        $result = $this->systemApi->healthCheck();
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
