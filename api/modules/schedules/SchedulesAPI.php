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
            $where = ["cs.status = 'active'"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $where[] = "cs.class_id = ?";
                $bindings[] = $params['class_id'];
            }
            if (!empty($params['teacher_id'])) {
                $where[] = "cs.teacher_id = ?";
                $bindings[] = $params['teacher_id'];
            }
            if (!empty($params['academic_year_id'])) {
                $where[] = "cs.academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }
            if (!empty($params['term_id'])) {
                $where[] = "cs.term_id = ?";
                $bindings[] = $params['term_id'];
            }
            if (!empty($params['day_of_week'])) {
                $where[] = "cs.day_of_week = ?";
                $bindings[] = $params['day_of_week'];
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT 
                    cs.id,
                    cs.class_id,
                    cs.day_of_week,
                    cs.day_of_week as day,
                    cs.start_time,
                    cs.end_time,
                    cs.subject_id,
                    cs.teacher_id,
                    cs.room_id,
                    cs.academic_year_id,
                    cs.term_id,
                    cs.period_number,
                    cs.status,
                    c.name as class_name,
                    COALESCE(cu.name, la.name) as subject_name,
                    la.name as learning_area_name,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    r.name as room_name,
                    r.code as room_code
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN curriculum_units cu ON cs.subject_id = cu.id
                LEFT JOIN learning_areas la ON cu.learning_area_id = la.id
                LEFT JOIN staff s ON cs.teacher_id = s.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE $whereSql
                ORDER BY FIELD(cs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), cs.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $timetable = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($timetable);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createTimetableEntry($data) {
        try {
            $required = ['class_id', 'subject_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            // Map 'day' shorthand to 'day_of_week' if needed
            if (empty($data['day_of_week']) && !empty($data['day'])) {
                $data['day_of_week'] = $data['day'];
            }

            // Check for teacher conflict
            $conflictSql = "
                SELECT cs.id, c.name as class_name, cs.start_time, cs.end_time
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.status = 'active'
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($conflictSql);
            $stmt->execute([$data['teacher_id'], $data['day_of_week'], $data['end_time'], $data['start_time']]);
            $teacherConflict = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teacherConflict) {
                return errorResponse("Teacher already scheduled for {$teacherConflict['class_name']} at {$teacherConflict['start_time']}-{$teacherConflict['end_time']}", 409);
            }

            // Check for class conflict (same class, same time slot)
            $classSql = "
                SELECT cs.id FROM class_schedules cs
                WHERE cs.class_id = ? AND cs.day_of_week = ? AND cs.status = 'active'
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$data['class_id'], $data['day_of_week'], $data['end_time'], $data['start_time']]);
            if ($stmt->fetch()) {
                return errorResponse("This class already has a lesson at this time slot", 409);
            }

            // Check for room conflict if room_id provided
            if (!empty($data['room_id'])) {
                $roomSql = "
                    SELECT cs.id, c.name as class_name FROM class_schedules cs
                    JOIN classes c ON cs.class_id = c.id
                    WHERE cs.room_id = ? AND cs.day_of_week = ? AND cs.status = 'active'
                    AND cs.start_time < ? AND cs.end_time > ?
                ";
                $stmt = $this->db->prepare($roomSql);
                $stmt->execute([$data['room_id'], $data['day_of_week'], $data['end_time'], $data['start_time']]);
                $roomConflict = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($roomConflict) {
                    return errorResponse("Room already booked by {$roomConflict['class_name']} at this time", 409);
                }
            }

            $sql = "
                INSERT INTO class_schedules (
                    class_id, subject_id, teacher_id, room_id,
                    day_of_week, start_time, end_time,
                    academic_year_id, term_id, period_number, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['class_id'],
                $data['subject_id'],
                $data['teacher_id'],
                $data['room_id'] ?? null,
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['academic_year_id'] ?? null,
                $data['term_id'] ?? null,
                $data['period_number'] ?? null
            ]);

            $entryId = $this->db->lastInsertId();

            return successResponse(['id' => $entryId, 'message' => 'Timetable entry created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateTimetableEntry($id, $data) {
        try {
            if (!$id) {
                return errorResponse('Timetable entry ID is required', 400);
            }

            // Check entry exists
            $stmt = $this->db->prepare("SELECT id FROM class_schedules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Timetable entry not found', 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = ['class_id', 'subject_id', 'teacher_id', 'room_id', 'day_of_week',
                              'start_time', 'end_time', 'academic_year_id', 'term_id', 'period_number', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return errorResponse('No fields to update', 400);
            }

            $params[] = $id;
            $sql = "UPDATE class_schedules SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return successResponse(['message' => 'Timetable entry updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteTimetableEntry($id = null, $data = []) {
        try {
            // Support delete by ID or by day/time/class combo
            if ($id) {
                $stmt = $this->db->prepare("DELETE FROM class_schedules WHERE id = ?");
                $stmt->execute([$id]);
            } elseif (!empty($data['class_id']) && !empty($data['day']) && !empty($data['start_time'])) {
                $day = $data['day_of_week'] ?? $data['day'];
                $stmt = $this->db->prepare(
                    "DELETE FROM class_schedules WHERE class_id = ? AND day_of_week = ? AND start_time = ?"
                );
                $stmt->execute([$data['class_id'], $day, $data['start_time']]);
            } else {
                return errorResponse('Entry ID or class_id + day + start_time required', 400);
            }

            if ($stmt->rowCount() === 0) {
                return errorResponse('Timetable entry not found', 404);
            }

            return successResponse(['message' => 'Timetable entry deleted successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function checkTimetableConflicts($params = []) {
        try {
            $conflicts = [];

            // Check teacher double-booking
            $sql = "
                SELECT 
                    cs1.id as schedule_id_1, cs2.id as schedule_id_2,
                    cs1.day_of_week, cs1.start_time, cs1.end_time,
                    CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                    c1.name as class_1, c2.name as class_2,
                    'teacher_overlap' as conflict_type
                FROM class_schedules cs1
                JOIN class_schedules cs2 ON cs1.teacher_id = cs2.teacher_id 
                    AND cs1.day_of_week = cs2.day_of_week
                    AND cs1.id < cs2.id
                    AND cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time
                JOIN staff s ON cs1.teacher_id = s.id
                JOIN classes c1 ON cs1.class_id = c1.id
                JOIN classes c2 ON cs2.class_id = c2.id
                WHERE cs1.status = 'active' AND cs2.status = 'active'
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $teacherConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($teacherConflicts as $c) {
                $c['description'] = "{$c['teacher_name']} is double-booked: {$c['class_1']} and {$c['class_2']} on {$c['day_of_week']} {$c['start_time']}-{$c['end_time']}";
                $conflicts[] = $c;
            }

            // Check room double-booking
            $sql = "
                SELECT 
                    cs1.id as schedule_id_1, cs2.id as schedule_id_2,
                    cs1.day_of_week, cs1.start_time, cs1.end_time,
                    r.name as room_name,
                    c1.name as class_1, c2.name as class_2,
                    'room_overlap' as conflict_type
                FROM class_schedules cs1
                JOIN class_schedules cs2 ON cs1.room_id = cs2.room_id 
                    AND cs1.day_of_week = cs2.day_of_week
                    AND cs1.id < cs2.id
                    AND cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time
                JOIN rooms r ON cs1.room_id = r.id
                JOIN classes c1 ON cs1.class_id = c1.id
                JOIN classes c2 ON cs2.class_id = c2.id
                WHERE cs1.status = 'active' AND cs2.status = 'active'
                AND cs1.room_id IS NOT NULL
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $roomConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($roomConflicts as $c) {
                $c['description'] = "{$c['room_name']} is double-booked: {$c['class_1']} and {$c['class_2']} on {$c['day_of_week']} {$c['start_time']}-{$c['end_time']}";
                $conflicts[] = $c;
            }

            return successResponse([
                'conflicts' => $conflicts,
                'total' => count($conflicts),
                'has_conflicts' => count($conflicts) > 0
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function reportTimetableConflict($data) {
        try {
            $required = ['description'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Please describe the conflict'], 400);
            }

            $sql = "
                INSERT INTO timetable_conflicts (
                    reported_by, conflict_type, description,
                    day_of_week, time_slot, schedule_id_1, schedule_id_2, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'reported')
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['reported_by'] ?? 0,
                $data['conflict_type'] ?? 'other',
                $data['description'],
                $data['day_of_week'] ?? null,
                $data['time_slot'] ?? null,
                $data['schedule_id_1'] ?? null,
                $data['schedule_id_2'] ?? null
            ]);

            return successResponse(['id' => $this->db->lastInsertId(), 'message' => 'Conflict reported successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getTimeSlots() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM time_slots WHERE is_active = 1 ORDER BY period_number ASC");
            $stmt->execute();
            $slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return successResponse($slots);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getExamSchedule($params = []) {
        try {
            $conditions = [];
            $bindings = [];

            // Filter by term
            if (!empty($params['term_id'])) {
                $conditions[] = "es.term_id = ?";
                $bindings[] = $params['term_id'];
            }

            // Filter by academic year
            if (!empty($params['academic_year_id'])) {
                $conditions[] = "es.academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }

            // Filter by class
            if (!empty($params['class_id'])) {
                $conditions[] = "es.class_id = ?";
                $bindings[] = $params['class_id'];
            }

            // Filter by status
            if (!empty($params['status'])) {
                $conditions[] = "es.status = ?";
                $bindings[] = $params['status'];
            } else {
                $conditions[] = "es.status NOT IN ('cancelled')";
            }

            // Filter by exam type
            if (!empty($params['exam_type'])) {
                $conditions[] = "es.exam_type = ?";
                $bindings[] = $params['exam_type'];
            }

            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            $sql = "
                SELECT 
                    es.id,
                    es.term_id,
                    es.academic_year_id,
                    es.class_id,
                    c.name AS class_name,
                    es.subject_id,
                    COALESCE(cu.name, '') AS subject_name,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.duration_minutes,
                    es.room_id,
                    r.name AS room_name,
                    es.venue,
                    es.invigilator_id,
                    CONCAT(inv.first_name, ' ', inv.last_name) AS invigilator_name,
                    es.supervisor_id,
                    CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
                    es.notes,
                    es.status,
                    es.created_at,
                    es.updated_at,
                    at2.term_number AS term_number,
                    ay.year_code AS academic_year
                FROM exam_schedules es
                JOIN classes c ON es.class_id = c.id
                LEFT JOIN curriculum_units cu ON es.subject_id = cu.id
                LEFT JOIN rooms r ON es.room_id = r.id
                LEFT JOIN staff inv ON es.invigilator_id = inv.id
                LEFT JOIN staff sup ON es.supervisor_id = sup.id
                LEFT JOIN academic_terms at2 ON es.term_id = at2.id
                LEFT JOIN academic_years ay ON es.academic_year_id = ay.id
                {$whereClause}
                ORDER BY es.exam_date ASC, es.start_time ASC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getExamScheduleById($id) {
        try {
            $sql = "
                SELECT 
                    es.id,
                    es.term_id,
                    es.academic_year_id,
                    es.class_id,
                    c.name AS class_name,
                    es.subject_id,
                    COALESCE(cu.name, '') AS subject_name,
                    es.exam_name,
                    es.exam_type,
                    es.exam_date,
                    es.start_time,
                    es.end_time,
                    es.duration_minutes,
                    es.room_id,
                    r.name AS room_name,
                    es.venue,
                    es.invigilator_id,
                    CONCAT(inv.first_name, ' ', inv.last_name) AS invigilator_name,
                    es.supervisor_id,
                    CONCAT(sup.first_name, ' ', sup.last_name) AS supervisor_name,
                    es.notes,
                    es.status,
                    es.created_at,
                    es.updated_at
                FROM exam_schedules es
                JOIN classes c ON es.class_id = c.id
                LEFT JOIN curriculum_units cu ON es.subject_id = cu.id
                LEFT JOIN rooms r ON es.room_id = r.id
                LEFT JOIN staff inv ON es.invigilator_id = inv.id
                LEFT JOIN staff sup ON es.supervisor_id = sup.id
                WHERE es.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                return errorResponse(['message' => 'Exam schedule not found'], 404);
            }

            return successResponse($schedule);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createExamSchedule($data) {
        try {
            $required = ['class_id', 'subject_id', 'exam_date', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO exam_schedules (
                    term_id,
                    academic_year_id,
                    class_id,
                    subject_id,
                    exam_name,
                    exam_type,
                    exam_date,
                    start_time,
                    end_time,
                    duration_minutes,
                    room_id,
                    venue,
                    invigilator_id,
                    supervisor_id,
                    notes,
                    created_by,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['term_id'] ?? null,
                $data['academic_year_id'] ?? null,
                $data['class_id'],
                $data['subject_id'],
                $data['exam_name'] ?? null,
                $data['exam_type'] ?? null,
                $data['exam_date'],
                $data['start_time'],
                $data['end_time'],
                $data['duration_minutes'] ?? null,
                $data['room_id'] ?? null,
                $data['venue'] ?? null,
                $data['invigilator_id'] ?? null,
                $data['supervisor_id'] ?? null,
                $data['notes'] ?? null,
                $data['created_by'] ?? null,
                $data['status'] ?? 'scheduled'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Exam schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateExamSchedule($id, $data) {
        try {
            // Build dynamic UPDATE
            $fields = [];
            $values = [];

            $allowedFields = [
                'term_id', 'academic_year_id', 'class_id', 'subject_id',
                'exam_name', 'exam_type', 'exam_date', 'start_time', 'end_time',
                'duration_minutes', 'room_id', 'venue', 'invigilator_id',
                'supervisor_id', 'notes', 'status'
            ];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "{$field} = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return errorResponse(['message' => 'No valid fields to update'], 400);
            }

            $values[] = $id;
            $sql = "UPDATE exam_schedules SET " . implode(', ', $fields) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            if ($stmt->rowCount() === 0) {
                return errorResponse(['message' => 'Exam schedule not found or no changes made'], 404);
            }

            return successResponse(['id' => $id, 'message' => 'Exam schedule updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteExamSchedule($id) {
        try {
            // Soft delete - set status to cancelled
            $stmt = $this->db->prepare("UPDATE exam_schedules SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return errorResponse(['message' => 'Exam schedule not found'], 404);
            }

            return successResponse(['message' => 'Exam schedule cancelled successfully']);
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
                    COUNT(DISTINCT cs.id) as timetable_count,
                    COUNT(DISTINCT e.id) as event_count
                FROM rooms r
                LEFT JOIN class_schedules cs ON r.id = cs.room_id AND cs.status = 'active'
                LEFT JOIN events e ON r.id = e.room_id AND e.status = 'active'
                WHERE r.status = 'active'
                GROUP BY r.id
                ORDER BY r.building, r.name
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
                    code,
                    building,
                    floor,
                    capacity,
                    type,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['code'] ?? null,
                $data['building'] ?? null,
                $data['floor'] ?? null,
                $data['capacity'],
                $data['type'] ?? 'classroom',
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