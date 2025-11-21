<?php
namespace App\API\Controllers;

use App\API\Modules\System\SystemAPI;
use Exception;

class SystemController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new SystemAPI();
    }

    // GET /api/system/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        $result = $this->api->readLogs($data);
        return $this->handleResponse($result);
    }

    // POST /api/system/logs/clear
    public function postLogsClear($id = null, $data = [], $segments = [])
    {
        $result = $this->api->clearLogs();
        return $this->handleResponse($result);
    }

    // POST /api/system/logs/archive
    public function postLogsArchive($id = null, $data = [], $segments = [])
    {
        $result = $this->api->archiveLogs();
        return $this->handleResponse($result);
    }

    // GET /api/system/school-config
    public function getSchoolConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getSchoolConfig($id);
        return $this->handleResponse($result);
    }

    // POST /api/system/school-config
    public function postSchoolConfig($id = null, $data = [], $segments = [])
    {
        $result = $this->api->setSchoolConfig($data);
        return $this->handleResponse($result);
    }

    // GET /api/system/health
    public function getHealth($id = null, $data = [], $segments = [])
    {
        $result = $this->api->healthCheck();
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
