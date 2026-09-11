<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use Exception;

/**
 * ChapelController
 *
 * ROUTES:
 * GET /api/chapel/services  → getServices()  query: limit=N, upcoming=1
 */
class ChapelController extends BaseController
{
    private $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = $this->contract('App\API\Modules\system\SystemAPI');
    }

    public function index($id = null, $data = [], $segments = [])
    {
        return $this->getServices($id, $data, $segments);
    }

    /**
     * GET /api/chapel/services?limit=N&upcoming=1
     */
    public function getServices($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        try {
            $limit = min((int)($_GET['limit'] ?? 10), 50);
            $upcoming = !empty($_GET['upcoming']);
            $result = $this->api->listChapelServices($limit, $upcoming);
            return $this->success($result['data'] ?? []);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ChapelController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->success([]);
        }
    }
}
