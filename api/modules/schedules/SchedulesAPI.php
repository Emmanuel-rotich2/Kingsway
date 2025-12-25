<?php
namespace App\API\Modules\schedules;

use App\API\Includes\BaseAPI;
use App\API\Modules\schedules\SchedulesManager;
use App\API\Modules\schedules\SchedulesWorkflow;
use App\API\Modules\schedules\TermHolidayManager;
use App\API\Modules\schedules\TermHolidayWorkflow;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use PDO;
use Exception;
use DateTime;

class SchedulesAPI extends BaseAPI {
    private SchedulesManager $manager;
    private SchedulesWorkflow $workflow;
    private $termHolidayManager;
    private $termHolidayWorkflow;

    public function __construct() {
        parent::__construct('schedules');
        $this->manager = new SchedulesManager($this->db);
        $this->workflow = new SchedulesWorkflow();
        $this->termHolidayManager = new TermHolidayManager($this->db);
        $this->termHolidayWorkflow = new TermHolidayWorkflow();
        // (Instantiate other workflow handlers as needed)
    }

    // =============================
    // Role-Specific Schedule Coordination Methods
    // =============================

    // TEACHING STAFF: Get timetable for a teacher
    public function getTeacherSchedule($teacherId, $termId = null)
    {
        try {
            $result = $this->manager->getTeacherSchedule($teacherId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // SUBJECT SPECIALIST: Get teaching load for a subject
    public function getSubjectTeachingLoad($subjectId, $termId = null)
    {
        try {
            $result = $this->manager->getSubjectTeachingLoad($subjectId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ACTIVITIES COORDINATOR: Get all activity schedules
    public function getAllActivitySchedules($filters = [])
    {
        try {
            $result = $this->manager->getAllActivitySchedules($filters);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // DRIVER: Get transport schedules for a driver
    public function getDriverSchedule($driverId, $termId = null)
    {
        try {
            $result = $this->manager->getDriverSchedule($driverId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // NON-TEACHING STAFF: Get duty schedules
    public function getStaffDutySchedule($staffId, $termId = null)
    {
        try {
            $result = $this->manager->getStaffDutySchedule($staffId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ADMIN: Get master schedule (all classes, activities, events, transport)
    public function getMasterSchedule($filters = [])
    {
        try {
            $result = $this->manager->getMasterSchedule($filters);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ANALYTICS: Get schedule analytics (utilization, conflicts, compliance)
    public function getScheduleAnalytics($filters = [])
    {
        try {
            $result = $this->manager->getScheduleAnalytics($filters);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
    // STUDENT: Get all schedules relevant to a student (classes, exams, events, holidays)

    public function getStudentSchedules($studentId, $termId = null)
    {
        try {
            $result = $this->termHolidayManager->getStudentSchedules($studentId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getStaffSchedules($staffId, $termId = null)
    {
        try {
            $result = $this->termHolidayManager->getStaffSchedules($staffId, $termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getAdminTermOverview($termId)
    {
        try {
            $result = $this->termHolidayManager->getAdminTermOverview($termId);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // =============================
    // Term & Holiday Workflow Endpoints
    // =============================

    public function defineTermDates($data)
    {
        try {
            $result = $this->termHolidayWorkflow->defineTermDates($data);
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function reviewTermDates($instanceId)
    {
        try {
            $result = $this->termHolidayWorkflow->reviewTermDates($instanceId);
            // (Add similar endpoints for ExamsWorkflow, EventsWorkflow, etc. as needed)
            return successResponse($result);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }


    public function checkResourceAvailability($resourceType, $resourceId, $start, $end)
    {
        try {
            $available = $this->manager->checkResourceAvailability($resourceType, $resourceId, $start, $end);
            return successResponse(['available' => $available]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function findOptimalSchedule($entityType, $entityId, $constraints = [])
    {
        try {
            $slots = $this->manager->findOptimalSchedule($entityType, $entityId, $constraints);
            return successResponse(['slots' => $slots]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function detectScheduleConflicts($entityType, $entityId, $proposedSchedule)
    {
        try {
            $conflicts = $this->manager->detectScheduleConflicts($entityType, $entityId, $proposedSchedule);
            return successResponse(['conflicts' => $conflicts]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function generateMasterSchedule($scope, $filters = [])
    {
        try {
            $schedule = $this->manager->generateMasterSchedule($scope, $filters);
            return successResponse(['schedule' => $schedule]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function validateScheduleCompliance($scheduleId)
    {
        try {
            $compliant = $this->manager->validateScheduleCompliance($scheduleId);
            return successResponse(['compliant' => $compliant]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // =============================
    // Scheduling Workflow Methods
    // =============================

    public function startSchedulingWorkflow($data)
    {
        try {
            // Expecting $data to contain reference_type, reference_id, and optionally initial_data
            if (!isset($data['reference_type']) || !isset($data['reference_id'])) {
                return errorResponse('Missing required workflow parameters: reference_type, reference_id');
            }
            $reference_type = $data['reference_type'];
            $reference_id = $data['reference_id'];
            $initial_data = isset($data['initial_data']) ? $data['initial_data'] : [];
            $result = $this->workflow->startWorkflow($reference_type, $reference_id, $initial_data);
            return successResponse(['workflow' => $result]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function advanceSchedulingWorkflow($workflowId, $action, $data = [])
    {
        try {
            // No advanceWorkflow method in SchedulesWorkflow or WorkflowHandler; return error
            return errorResponse('advanceWorkflow is not implemented for SchedulesWorkflow.');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getSchedulingWorkflowStatus($workflowId)
    {
        try {
            $status = $this->workflow->getWorkflowStatus($workflowId);
            return successResponse(['workflow_status' => $status]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function listSchedulingWorkflows($filters = [])
    {
        try {
            $workflows = $this->workflow->listWorkflows($filters);
            return successResponse(['workflows' => $workflows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function list($params = []) {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE title LIKE ? OR description LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "SELECT COUNT(*) FROM schedules $where";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "SELECT * FROM schedules $where ORDER BY $sort $order LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse([
                'schedules' => $schedules,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function get($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                return errorResponse('Schedule not found', 404);
            }

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function create($data) {
        try {
            $required = ['title', 'start_date', 'end_date', 'type'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO schedules (
                    title,
                    description,
                    start_date,
                    end_date,
                    type,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['type'],
                $data['status'] ?? 'active'
            ]);

            $id = $this->db->lastInsertId();

            return successResponse(['id' => $id, 'message' => 'Schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Schedule not found', 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = ['title', 'description', 'start_date', 'end_date', 'type', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE schedules SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            return successResponse(['message' => 'Schedule updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse(['status' => 'error', 'message' => 'Schedule not found'], 404);
            }

            return successResponse(['message' => 'Schedule deleted successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTimetable($params = []) {
        try {
            $sql = "
                SELECT 
                    t.*,
                    c.name as class_name,
                    la.name as learning_area_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    r.name as room_name
                FROM timetables t
                JOIN classes c ON t.class_id = c.id
                JOIN learning_areas la ON t.learning_area_id = la.id
                JOIN staff s ON t.teacher_id = s.id
                LEFT JOIN rooms r ON t.room_id = r.id
                WHERE t.status = 'active'
                ORDER BY t.day_of_week, t.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($timetable);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createTimetableEntry($data) {
        try {
            $required = ['class_id', 'learning_area_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO timetables (
                    class_id,
                    learning_area_id,
                    teacher_id,
                    room_id,
                    day_of_week,
                    start_time,
                    end_time,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['class_id'],
                $data['learning_area_id'],
                $data['teacher_id'],
                $data['room_id'] ?? null,
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                'active'
            ]);

            $entryId = $this->db->lastInsertId();

            return successResponse(['id' => $entryId, 'message' => 'Timetable entry created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getExamSchedule($params = []) {
        try {
            $sql = "
                SELECT 
                    es.*,
                    e.name as exam_name,
                    la.name as learning_area_name,
                    r.name as room_name
                FROM exam_schedules es
                JOIN exams e ON es.exam_id = e.id
                JOIN learning_areas la ON es.learning_area_id = la.id
                LEFT JOIN rooms r ON es.room_id = r.id
                WHERE es.status = 'active'
                ORDER BY es.exam_date, es.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createExamSchedule($data) {
        try {
            $required = ['exam_id', 'learning_area_id', 'exam_date', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO exam_schedules (
                    exam_id,
                    learning_area_id,
                    room_id,
                    exam_date,
                    start_time,
                    end_time,
                    instructions,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['exam_id'],
                $data['learning_area_id'],
                $data['room_id'] ?? null,
                $data['exam_date'],
                $data['start_time'],
                $data['end_time'],
                $data['instructions'] ?? null,
                'active'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Exam schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getEvents($params = []) {
        try {
            $sql = "
                SELECT 
                    e.*,
                    r.name as room_name,
                    CONCAT(o.first_name, ' ', o.last_name) as organizer_name
                FROM events e
                LEFT JOIN rooms r ON e.room_id = r.id
                LEFT JOIN staff o ON e.organizer_id = o.id
                WHERE e.status = 'active'
                ORDER BY e.start_date, e.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($events);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createEvent($data) {
        try {
            $required = ['name', 'start_date', 'end_date', 'organizer_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO events (
                    name,
                    description,
                    start_date,
                    end_date,
                    start_time,
                    end_time,
                    room_id,
                    organizer_id,
                    type,
                    participants,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['start_date'],
                $data['end_date'],
                $data['start_time'] ?? null,
                $data['end_time'] ?? null,
                $data['room_id'] ?? null,
                $data['organizer_id'],
                $data['type'] ?? 'general',
                json_encode($data['participants'] ?? []),
                'active'
            ]);

            $eventId = $this->db->lastInsertId();

            return successResponse(['id' => $eventId, 'message' => 'Event created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getActivitySchedule($params = []) {
        try {
            $sql = "
                SELECT 
                    a.*,
                    ac.name as activity_name,
                    r.name as room_name,
                    CONCAT(s.first_name, ' ', s.last_name) as supervisor_name
                FROM activity_schedules a
                JOIN activities ac ON a.activity_id = ac.id
                LEFT JOIN rooms r ON a.room_id = r.id
                LEFT JOIN staff s ON a.supervisor_id = s.id
                WHERE a.status = 'active'
                ORDER BY a.day_of_week, a.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createActivitySchedule($data) {
        try {
            $required = ['activity_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO activity_schedules (
                    activity_id,
                    room_id,
                    supervisor_id,
                    day_of_week,
                    start_time,
                    end_time,
                    max_participants,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['room_id'] ?? null,
                $data['supervisor_id'] ?? null,
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['max_participants'] ?? null,
                'active'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Activity schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getRooms($params = []) {
        try {
            $sql = "
                SELECT 
                    r.*,
                    b.name as building_name,
                    COUNT(DISTINCT t.id) as timetable_count,
                    COUNT(DISTINCT e.id) as event_count
                FROM rooms r
                LEFT JOIN buildings b ON r.building_id = b.id
                LEFT JOIN timetables t ON r.id = t.room_id AND t.status = 'active'
                LEFT JOIN events e ON r.id = e.room_id AND e.status = 'active'
                WHERE r.status = 'active'
                GROUP BY r.id
                ORDER BY r.building_id, r.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($rooms);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createRoom($data) {
        try {
            $required = ['name', 'capacity'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO rooms (
                    name,
                    building_id,
                    floor,
                    capacity,
                    type,
                    facilities,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['building_id'] ?? null,
                $data['floor'] ?? null,
                $data['capacity'],
                $data['type'] ?? 'classroom',
                json_encode($data['facilities'] ?? []),
                'active'
            ]);

            $roomId = $this->db->lastInsertId();

            return successResponse(['id' => $roomId, 'message' => 'Room created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getScheduledReports($params = []) {
        try {
            $sql = "
                SELECT 
                    sr.*,
                    CONCAT(s.first_name, ' ', s.last_name) as recipient_name
                FROM scheduled_reports sr
                LEFT JOIN staff s ON sr.recipient_id = s.id
                WHERE sr.status = 'active'
                ORDER BY sr.frequency, sr.next_run
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($reports);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createScheduledReport($data) {
        try {
            $required = ['name', 'report_type', 'frequency', 'recipient_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO scheduled_reports (
                    name,
                    description,
                    report_type,
                    parameters,
                    frequency,
                    next_run,
                    recipient_id,
                    format,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            // Calculate next run based on frequency
            $nextRun = new DateTime();
            switch ($data['frequency']) {
                case 'daily':
                    $nextRun->modify('+1 day');
                    break;
                case 'weekly':
                    $nextRun->modify('next monday');
                    break;
                case 'monthly':
                    $nextRun->modify('first day of next month');
                    break;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['report_type'],
                json_encode($data['parameters'] ?? []),
                $data['frequency'],
                $nextRun->format('Y-m-d H:i:s'),
                $data['recipient_id'],
                $data['format'] ?? 'pdf',
                'active'
            ]);

            $reportId = $this->db->lastInsertId();

            return successResponse(['id' => $reportId, 'message' => 'Scheduled report created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getRouteSchedule($params = []) {
        try {
            $sql = "
                SELECT 
                    rs.*,
                    r.name as route_name,
                    v.registration_number,
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count
                FROM route_schedules rs
                JOIN transport_routes r ON rs.route_id = r.id
                LEFT JOIN vehicles v ON rs.vehicle_id = v.id
                LEFT JOIN staff d ON rs.driver_id = d.id
                LEFT JOIN transport_assignments ta ON r.id = ta.route_id
                WHERE rs.status = 'active'
                GROUP BY rs.id
                ORDER BY rs.day_of_week, rs.pickup_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedules);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createRouteSchedule($data) {
        try {
            $required = ['route_id', 'day_of_week', 'pickup_time', 'dropoff_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO route_schedules (
                    route_id,
                    vehicle_id,
                    driver_id,
                    day_of_week,
                    pickup_time,
                    dropoff_time,
                    notes,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['route_id'],
                $data['vehicle_id'] ?? null,
                $data['driver_id'] ?? null,
                $data['day_of_week'],
                $data['pickup_time'],
                $data['dropoff_time'],
                $data['notes'] ?? null,
                'active'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Route schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}