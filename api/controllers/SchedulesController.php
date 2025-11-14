<?php
namespace App\API\Controllers;

use App\API\Modules\Schedules\SchedulesAPI;
use Exception;

/**
 * SchedulesController - REST endpoints for all scheduling operations
 * Handles timetables, exam schedules, events, activity schedules, rooms, and route schedules
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */
class SchedulesController extends BaseController
{
    private SchedulesAPI $api;

    public function __construct() {
        parent::__construct();
        $this->api = new SchedulesAPI();
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/schedules - List all schedules
     * GET /api/schedules/{id} - Get single schedule
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules - Create new schedule
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/schedules/{id} - Update schedule
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Schedule ID is required for update');
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/schedules/{id} - Delete schedule
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Schedule ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Timetable Operations
    // ========================================

    /**
     * GET /api/schedules/timetable/get
     */
    public function getTimetableGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getTimetable($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/timetable/create
     */
    public function postTimetableCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createTimetableEntry($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Exam Schedules
    // ========================================

    /**
     * GET /api/schedules/exam/get
     */
    public function getExamGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getExamSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/exam/create
     */
    public function postExamCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createExamSchedule($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Events
    // ========================================

    /**
     * GET /api/schedules/events/get
     */
    public function getEventsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getEvents($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/events/create
     */
    public function postEventsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createEvent($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Activity Schedules
    // ========================================

    /**
     * GET /api/schedules/activity/get
     */
    public function getActivityGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getActivitySchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/activity/create
     */
    public function postActivityCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createActivitySchedule($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 6: Rooms Management
    // ========================================

    /**
     * GET /api/schedules/rooms/get
     */
    public function getRoomsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRooms($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/rooms/create
     */
    public function postRoomsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createRoom($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Scheduled Reports
    // ========================================

    /**
     * GET /api/schedules/reports/get
     */
    public function getReportsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getScheduledReports($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/reports/create
     */
    public function postReportsCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createScheduledReport($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 8: Route Schedules (Transport)
    // ========================================

    /**
     * GET /api/schedules/route/get
     */
    public function getRouteGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getRouteSchedule($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/schedules/route/create
     */
    public function postRouteCreate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createRouteSchedule($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Helper Methods
    // ========================================

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
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

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }
}
