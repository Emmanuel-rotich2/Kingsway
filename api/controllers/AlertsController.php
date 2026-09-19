<?php
namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use Exception;

class AlertsController extends BaseController
{
    private $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = $this->contract('App\API\Modules\system\SystemAPI');
    }

    /**
     * GET /api/alerts - Return active system alerts
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $limit = min(50, (int)($_GET['limit'] ?? 50));
            $result = $this->api->getActiveAlerts($limit);
            if (!empty($result['success'])) {
                return $this->success($result['data']);
            }
            return $this->error('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[AlertsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->error('An internal error occurred.');
        }
    }
}
