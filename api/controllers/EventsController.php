<?php
declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use Exception;

/**
 * EventsController
 *
 * ROUTES:
 * GET /api/events          → getEvents()   query params: upcoming=1, limit=N
 */
class EventsController extends BaseController
{
    private $api;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->api = $this->contract('App\API\Modules\system\SystemAPI');
    }

    public function index($id = null, $data = [], $segments = [])
    {
        return $this->getEvents($id, $data, $segments);
    }

    /**
     * GET /api/events
     * Returns school/calendar events.
     * Query params: upcoming=1, limit=N
     */
    public function getEvents($id = null, $data = [], $segments = [])
    {
        $upcoming = !empty($_GET['upcoming']);
        $limit = min((int)($_GET['limit'] ?? 20), 100);

        try {
            $result = $this->api->listSchoolEvents(null, $upcoming, $limit);
            return $this->success($result['data'] ?? []);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[EventsController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->success([]);
        }
    }
}
