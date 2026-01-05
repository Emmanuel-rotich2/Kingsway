<?php
namespace App\API\Controllers;

use App\API\Modules\schedules\SchedulesAPI;
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


    public function __construct()
    {
        parent::__construct();
        $this->api = new SchedulesAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Schedules API is running']);
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


    // =============================
    // ADVANCED SCHEDULE/WORKFLOW ENDPOINTS
    // =============================

    // TEACHING STAFF: Get timetable for a teacher
    public function getTeacherSchedule($id = null, $data = [], $segments = [])
    {
        $teacherId = $id ?? ($data['teacher_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getTeacherSchedule($teacherId, $termId);
        return $this->handleResponse($result);
    }

    // SUBJECT SPECIALIST: Get teaching load for a subject
    public function getSubjectTeachingLoad($id = null, $data = [], $segments = [])
    {
        $subjectId = $id ?? ($data['subject_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getSubjectTeachingLoad($subjectId, $termId);
        return $this->handleResponse($result);
    }

    // ACTIVITIES COORDINATOR: Get all activity schedules
    public function getAllActivitySchedules($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getAllActivitySchedules($data);
        return $this->handleResponse($result);
    }

    // DRIVER: Get transport schedules for a driver
    public function getDriverSchedule($id = null, $data = [], $segments = [])
    {
        $driverId = $id ?? ($data['driver_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getDriverSchedule($driverId, $termId);
        return $this->handleResponse($result);
    }

    // NON-TEACHING STAFF: Get duty schedules
    public function getStaffDutySchedule($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($data['staff_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getStaffDutySchedule($staffId, $termId);
        return $this->handleResponse($result);
    }

    // ADMIN: Get master schedule (all classes, activities, events, transport)
    public function getMasterSchedule($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getMasterSchedule($data);
        return $this->handleResponse($result);
    }

    // ANALYTICS: Get schedule analytics (utilization, conflicts, compliance)
    public function getScheduleAnalytics($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getScheduleAnalytics($data);
        return $this->handleResponse($result);
    }

    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)
    public function getStudentSchedules($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? ($data['student_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getStudentSchedules($studentId, $termId);
        return $this->handleResponse($result);
    }

    public function getStaffSchedules($id = null, $data = [], $segments = [])
    {
        $staffId = $id ?? ($data['staff_id'] ?? null);
        $termId = $data['term_id'] ?? null;
        $result = $this->api->getStaffSchedules($staffId, $termId);
        return $this->handleResponse($result);
    }

    public function getAdminTermOverview($id = null, $data = [], $segments = [])
    {
        $termId = $id ?? ($data['term_id'] ?? null);
        $result = $this->api->getAdminTermOverview($termId);
        return $this->handleResponse($result);
    }

    // Term & Holiday Workflow Endpoints
    public function postDefineTermDates($id = null, $data = [], $segments = [])
    {
        $result = $this->api->defineTermDates($data);
        return $this->handleResponse($result);
    }
    public function getReviewTermDates($id = null, $data = [], $segments = [])
    {
        $instanceId = $id ?? ($data['instance_id'] ?? null);
        $result = $this->api->reviewTermDates($instanceId);
        return $this->handleResponse($result);
    }

    // Resource/Slot/Conflict/Compliance/Workflow
    public function getCheckResourceAvailability($id = null, $data = [], $segments = [])
    {
        $resourceType = $data['resource_type'] ?? null;
        $resourceId = $data['resource_id'] ?? null;
        $start = $data['start'] ?? null;
        $end = $data['end'] ?? null;
        $result = $this->api->checkResourceAvailability($resourceType, $resourceId, $start, $end);
        return $this->handleResponse($result);
    }
    public function getFindOptimalSchedule($id = null, $data = [], $segments = [])
    {
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $constraints = $data['constraints'] ?? [];
        $result = $this->api->findOptimalSchedule($entityType, $entityId, $constraints);
        return $this->handleResponse($result);
    }
    public function postDetectScheduleConflicts($id = null, $data = [], $segments = [])
    {
        $entityType = $data['entity_type'] ?? null;
        $entityId = $data['entity_id'] ?? null;
        $proposedSchedule = $data['proposed_schedule'] ?? [];
        $result = $this->api->detectScheduleConflicts($entityType, $entityId, $proposedSchedule);
        return $this->handleResponse($result);
    }
    public function getGenerateMasterSchedule($id = null, $data = [], $segments = [])
    {
        $scope = $data['scope'] ?? null;
        $filters = $data['filters'] ?? [];
        $result = $this->api->generateMasterSchedule($scope, $filters);
        return $this->handleResponse($result);
    }
    public function getValidateScheduleCompliance($id = null, $data = [], $segments = [])
    {
        $scheduleId = $id ?? ($data['schedule_id'] ?? null);
        $result = $this->api->validateScheduleCompliance($scheduleId);
        return $this->handleResponse($result);
    }

    // Scheduling Workflow Methods
    public function postStartSchedulingWorkflow($id = null, $data = [], $segments = [])
    {
        $result = $this->api->startSchedulingWorkflow($data);
        return $this->handleResponse($result);
    }
    public function postAdvanceSchedulingWorkflow($id = null, $data = [], $segments = [])
    {
        $workflowId = $data['workflow_id'] ?? null;
        $action = $data['action'] ?? null;
        $payload = $data['data'] ?? [];
        $result = $this->api->advanceSchedulingWorkflow($workflowId, $action, $payload);
        return $this->handleResponse($result);
    }
    public function getSchedulingWorkflowStatus($id = null, $data = [], $segments = [])
    {
        $workflowId = $id ?? ($data['workflow_id'] ?? null);
        $result = $this->api->getSchedulingWorkflowStatus($workflowId);
        return $this->handleResponse($result);
    }
    public function getListSchedulingWorkflows($id = null, $data = [], $segments = [])
    {
        $filters = $data['filters'] ?? [];
        $result = $this->api->listSchedulingWorkflows($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/schedules/weekly - Get weekly lessons statistics for dashboard
     * Returns: days, data, total_weekly, daily_average
     */
    public function getWeekly($id = null, $data = [], $segments = [])
    {
        try {
            $startDate = new \DateTime('monday this week');
            $endDate = new \DateTime('sunday this week');

            $days = [];
            $counts = [];

            // Get lessons count for each day of the week
            for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                $dayName = $date->format('D');
                // Convert short day name to full day name for database query
                $dayMap = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];
                $fullDayName = $dayMap[$dayName] ?? $dayName;
                $dateStr = $date->format('Y-m-d');
                $days[] = $dayName;

                $result = $this->db->query(
                    "SELECT COUNT(*) as total FROM class_schedules WHERE day_of_week = ? AND status = 'active'",
                    [$fullDayName]
                );
                $row = $result->fetch();
                $counts[] = $row['total'] ?? 0;
            }

            $totalWeekly = array_sum($counts);
            $dailyAverage = count($counts) > 0 ? round($totalWeekly / count($counts), 1) : 0;

            return $this->success([
                'days' => $days,
                'data' => $counts,
                'total_weekly' => $totalWeekly,
                'daily_average' => $dailyAverage,
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $endDate->format('Y-m-d')
            ], 'Weekly lessons statistics retrieved');
        } catch (Exception $e) {
            return $this->error('Failed to retrieve weekly lessons: ' . $e->getMessage());
        }
    }

}
