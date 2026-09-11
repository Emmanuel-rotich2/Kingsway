<?php
namespace App\API\Modules\schedules;

use App\API\Includes\BaseAPI;
use App\API\Modules\schedules\SchedulesManager;
use App\API\Modules\schedules\SchedulesWorkflow;
use App\API\Modules\schedules\TermHolidayManager;
use App\API\Modules\schedules\TermHolidayWorkflow;
use App\API\Services\CalendarSyncService;
use App\API\Services\NotificationService;
use function App\API\Includes\errorResponse;
use function App\API\Includes\successResponse;
use function App\API\Includes\dayNameToNumber;
use PDO;
use Exception;
use DateTime;

class SchedulesAPI extends BaseAPI {
    private SchedulesManager $manager;
    private SchedulesWorkflow $workflow;
    private $termHolidayManager;
    private $termHolidayWorkflow;
    private CalendarSyncService $calendarSync;

    public function __construct() {
        parent::__construct('schedules');
        $this->manager = new SchedulesManager($this->db);
        $this->workflow = new SchedulesWorkflow();
        $this->termHolidayManager = new TermHolidayManager($this->db);
        $this->termHolidayWorkflow = new TermHolidayWorkflow();
        $this->calendarSync = new CalendarSyncService($this->db);
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

    public function getWeeklyLessonStats()
    {
        try {
            $startDate = new \DateTime('monday this week');
            $endDate = new \DateTime('sunday this week');

            $days = [];
            $counts = [];

            for ($date = clone $startDate; $date <= $endDate; $date->modify('+1 day')) {
                $dayName = $date->format('D');
                $dayNum = (int) $date->format('N');
                $days[] = $dayName;

                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) as total FROM vw_timetable_entries WHERE day_of_week = ? AND status = 'scheduled'"
                );
                $stmt->execute([$dayNum]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $counts[] = $row['total'] ?? 0;
            }

            $totalWeekly = array_sum($counts);
            $dailyAverage = count($counts) > 0 ? round($totalWeekly / count($counts), 1) : 0;

            return successResponse([
                'days' => $days,
                'data' => $counts,
                'total_weekly' => $totalWeekly,
                'daily_average' => $dailyAverage,
                'week_start' => $startDate->format('Y-m-d'),
                'week_end' => $endDate->format('Y-m-d'),
            ]);
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

    public function getTimetable($params = []) {
        try {
            $where = ["cs.status = 'scheduled'"];
            $bindings = [];

            if (!empty($params['class_id'])) {
                $where[] = "cs.class_id = ?";
                $bindings[] = (int) $params['class_id'];
            }
            if (!empty($params['teacher_id'])) {
                $where[] = "cs.teacher_id = ?";
                $bindings[] = (int) $params['teacher_id'];
            }
            if (!empty($params['academic_year_id'])) {
                $where[] = "cs.academic_year_id = ?";
                $bindings[] = (int) $params['academic_year_id'];
            }
            $termId = $params['academic_year_term_id'] ?? $params['term_id'] ?? null;
            if (!empty($termId)) {
                $where[] = "cs.academic_year_term_id = ?";
                $bindings[] = (int) $termId;
            }
            if (!empty($params['academic_year_class_stream_id'])) {
                $where[] = "cs.academic_year_class_stream_id = ?";
                $bindings[] = (int) $params['academic_year_class_stream_id'];
            }
            if (!empty($params['class_stream_ids'])) {
                $streamIds = is_array($params['class_stream_ids'])
                    ? $params['class_stream_ids']
                    : explode(',', (string) $params['class_stream_ids']);
                $streamIds = array_values(array_filter(array_map('intval', $streamIds), static function ($id) { return $id > 0; }));
                if (!$streamIds) return successResponse([]);
                $where[] = 'cs.academic_year_class_stream_id IN (' . implode(',', array_fill(0, count($streamIds), '?')) . ')';
                $bindings = array_merge($bindings, $streamIds);
            }
            if (!empty($params['day_of_week'])) {
                $dayNum = dayNameToNumber($params['day_of_week']);
                $where[] = "cs.day_of_week = ?";
                $bindings[] = $dayNum ?? $params['day_of_week'];
            }
            if (array_key_exists('_scope_stream_ids', $params)) {
                $allowed = array_values(array_filter(array_map('intval', (array)$params['_scope_stream_ids']), static function ($id) { return $id > 0; }));
                if (!$allowed) return successResponse([]);
                $where[] = 'cs.academic_year_class_stream_id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ')';
                $bindings = array_merge($bindings, $allowed);
            }

            $whereSql = implode(' AND ', $where);

            $sql = "
                SELECT *
                FROM vw_timetable_entries cs
                WHERE $whereSql
                ORDER BY cs.day_of_week, cs.start_time
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

            $dayNum = dayNameToNumber($data['day_of_week']);
            if ($dayNum === null) {
                return errorResponse('Invalid day_of_week', 400);
            }

            $classStreamId = $this->resolveClassStreamId(
                (int) $data['class_id'],
                (int) ($data['academic_year_id'] ?? 0)
            );
            if ($classStreamId <= 0) {
                return errorResponse('No active class-stream found for the given class and academic year', 400);
            }

            $termId = $this->resolveAcademicYearTermId(
                (int) ($data['term_id'] ?? 0),
                (int) ($data['academic_year_id'] ?? 0)
            );
            if ($termId <= 0) {
                return errorResponse('No active term found for the given academic year', 400);
            }

            $learningAreaId = $this->resolveLearningAreaId((int) $data['subject_id']);
            if ($learningAreaId <= 0) {
                return errorResponse('Invalid subject', 400);
            }

            $timeSlotId = $this->resolveTimeSlotId(
                $data['time_slot_id'] ?? null,
                $data['start_time'],
                $data['end_time']
            );
            if ($timeSlotId === null) {
                return errorResponse('No time slot matches the given start/end time', 400);
            }

            // Check for teacher conflict
            $conflictSql = "
                SELECT cs.id, cs.class_name, cs.start_time, cs.end_time
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($conflictSql);
            $stmt->execute([(int) $data['teacher_id'], $dayNum, $termId, $data['end_time'], $data['start_time']]);
            $teacherConflict = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teacherConflict) {
                return errorResponse("Teacher already scheduled for {$teacherConflict['class_name']} at {$teacherConflict['start_time']}-{$teacherConflict['end_time']}", 409);
            }

            // Check for class conflict (same class, same time slot)
            $classSql = "
                SELECT cs.id FROM vw_timetable_entries cs
                WHERE cs.academic_year_class_stream_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$classStreamId, $dayNum, $termId, $data['end_time'], $data['start_time']]);
            if ($stmt->fetch()) {
                return errorResponse("This class already has a lesson at this time slot", 409);
            }

            // Check for room conflict using the class stream's assigned room
            $roomStmt = $this->db->prepare("SELECT room_id FROM academic_year_class_streams WHERE id = ?");
            $roomStmt->execute([$classStreamId]);
            $roomId = (int) $roomStmt->fetchColumn();
            if ($roomId > 0) {
                $roomSql = "
                    SELECT cs.id, cs.class_name FROM vw_timetable_entries cs
                    WHERE cs.room_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                    AND cs.academic_year_term_id = ?
                    AND cs.start_time < ? AND cs.end_time > ?
                ";
                $stmt = $this->db->prepare($roomSql);
                $stmt->execute([$roomId, $dayNum, $termId, $data['end_time'], $data['start_time']]);
                $roomConflict = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($roomConflict) {
                    return errorResponse("Room already booked by {$roomConflict['class_name']} at this time", 409);
                }
            }

            $sql = "
                INSERT INTO timetable_entries (
                    academic_year_class_stream_id, academic_year_term_id, day_of_week,
                    time_slot_id, learning_area_id, teacher_id, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $classStreamId,
                $termId,
                $dayNum,
                $timeSlotId,
                $learningAreaId,
                (int) $data['teacher_id']
            ]);

            $entryId = (int) $this->db->lastInsertId();

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
            $stmt = $this->db->prepare("SELECT id FROM timetable_entries WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse('Timetable entry not found', 404);
            }

            $updates = [];
            $params = [];

            if (isset($data['class_id']) || isset($data['academic_year_class_stream_id'])) {
                $classStreamId = $this->resolveClassStreamId(
                    (int) ($data['class_id'] ?? 0),
                    (int) ($data['academic_year_id'] ?? 0)
                );
                if ($classStreamId <= 0) {
                    return errorResponse('No active class-stream found for the given class and academic year', 400);
                }
                $updates[] = "academic_year_class_stream_id = ?";
                $params[] = $classStreamId;
            }

            if (isset($data['subject_id']) || isset($data['learning_area_id'])) {
                $learningAreaId = $this->resolveLearningAreaId((int) ($data['subject_id'] ?? $data['learning_area_id']));
                if ($learningAreaId <= 0) {
                    return errorResponse('Invalid subject', 400);
                }
                $updates[] = "learning_area_id = ?";
                $params[] = $learningAreaId;
            }

            if (isset($data['teacher_id'])) {
                $updates[] = "teacher_id = ?";
                $params[] = (int) $data['teacher_id'];
            }

            if (isset($data['day_of_week']) || isset($data['day'])) {
                $dayNum = dayNameToNumber($data['day_of_week'] ?? $data['day']);
                if ($dayNum === null) {
                    return errorResponse('Invalid day_of_week', 400);
                }
                $updates[] = "day_of_week = ?";
                $params[] = $dayNum;
            }

            if (isset($data['start_time']) || isset($data['end_time']) || isset($data['time_slot_id'])) {
                $timeSlotId = $this->resolveTimeSlotId(
                    $data['time_slot_id'] ?? null,
                    $data['start_time'] ?? null,
                    $data['end_time'] ?? null
                );
                if ($timeSlotId === null) {
                    return errorResponse('No time slot matches the given start/end time', 400);
                }
                $updates[] = "time_slot_id = ?";
                $params[] = $timeSlotId;
            }

            if (isset($data['term_id']) || isset($data['academic_year_term_id'])) {
                $termId = $this->resolveAcademicYearTermId(
                    (int) ($data['term_id'] ?? $data['academic_year_term_id']),
                    (int) ($data['academic_year_id'] ?? 0)
                );
                if ($termId <= 0) {
                    return errorResponse('No active term found for the given academic year', 400);
                }
                $updates[] = "academic_year_term_id = ?";
                $params[] = $termId;
            }

            if (isset($data['status'])) {
                $status = $data['status'];
                if (!in_array($status, ['scheduled', 'cancelled', 'rescheduled'], true)) {
                    return errorResponse('Invalid status', 400);
                }
                $updates[] = "status = ?";
                $params[] = $status;
            }

            if (empty($updates)) {
                return errorResponse('No fields to update', 400);
            }

            // Build the effective post-update row so overlap checks can run against it
            $stmt = $this->db->prepare("SELECT * FROM timetable_entries WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);

            $newStream = (int) ($current['academic_year_class_stream_id'] ?? 0);
            $newTerm = (int) ($current['academic_year_term_id'] ?? 0);
            $newDay = (int) ($current['day_of_week'] ?? 0);
            $newSlot = (int) ($current['time_slot_id'] ?? 0);
            $newTeacher = (int) ($current['teacher_id'] ?? 0);

            foreach ($updates as $i => $field) {
                switch ($field) {
                    case "academic_year_class_stream_id = ?":
                        $newStream = (int) $params[$i];
                        break;
                    case "academic_year_term_id = ?":
                        $newTerm = (int) $params[$i];
                        break;
                    case "day_of_week = ?":
                        $newDay = (int) $params[$i];
                        break;
                    case "time_slot_id = ?":
                        $newSlot = (int) $params[$i];
                        break;
                    case "teacher_id = ?":
                        $newTeacher = (int) $params[$i];
                        break;
                }
            }

            $times = $this->db->prepare("SELECT start_time, end_time FROM time_slots WHERE id = ?");
            $times->execute([$newSlot]);
            $timeRange = $times->fetch(PDO::FETCH_ASSOC);
            if (!$timeRange) {
                return errorResponse('Time slot not found', 400);
            }

            $conflictSql = "
                SELECT cs.id, cs.class_name, cs.start_time, cs.end_time
                FROM vw_timetable_entries cs
                WHERE cs.teacher_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ? AND cs.id <> ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($conflictSql);
            $stmt->execute([$newTeacher, $newDay, $newTerm, (int) $id, $timeRange['end_time'], $timeRange['start_time']]);
            $teacherConflict = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($teacherConflict) {
                return errorResponse("Teacher already scheduled for {$teacherConflict['class_name']} at {$teacherConflict['start_time']}-{$teacherConflict['end_time']}", 409);
            }

            $classSql = "
                SELECT cs.id FROM vw_timetable_entries cs
                WHERE cs.academic_year_class_stream_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                AND cs.academic_year_term_id = ? AND cs.id <> ?
                AND cs.start_time < ? AND cs.end_time > ?
            ";
            $stmt = $this->db->prepare($classSql);
            $stmt->execute([$newStream, $newDay, $newTerm, (int) $id, $timeRange['end_time'], $timeRange['start_time']]);
            if ($stmt->fetch()) {
                return errorResponse("This class already has a lesson at this time slot", 409);
            }

            $roomStmt = $this->db->prepare("SELECT room_id FROM academic_year_class_streams WHERE id = ?");
            $roomStmt->execute([$newStream]);
            $roomId = (int) $roomStmt->fetchColumn();
            if ($roomId > 0) {
                $roomSql = "
                    SELECT cs.id FROM vw_timetable_entries cs
                    WHERE cs.room_id = ? AND cs.day_of_week = ? AND cs.status = 'scheduled'
                    AND cs.academic_year_term_id = ? AND cs.id <> ?
                    AND cs.start_time < ? AND cs.end_time > ?
                ";
                $stmt = $this->db->prepare($roomSql);
                $stmt->execute([$roomId, $newDay, $newTerm, (int) $id, $timeRange['end_time'], $timeRange['start_time']]);
                if ($stmt->fetch()) {
                    return errorResponse("Room already booked at this time", 409);
                }
            }

            $params[] = $id;
            $sql = "UPDATE timetable_entries SET " . implode(', ', $updates) . " WHERE id = ?";
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
                $stmt = $this->db->prepare("DELETE FROM timetable_entries WHERE id = ?");
                $stmt->execute([$id]);
            } elseif (!empty($data['class_id']) && !empty($data['day']) && !empty($data['start_time'])) {
                $day = $data['day_of_week'] ?? $data['day'];
                $dayNum = dayNameToNumber($day);
                if ($dayNum === null) {
                    return errorResponse('Invalid day_of_week', 400);
                }
                $classStreamId = $this->resolveClassStreamId(
                    (int) $data['class_id'],
                    (int) ($data['academic_year_id'] ?? 0)
                );
                $stmt = $this->db->prepare(
                    "DELETE FROM timetable_entries
                     WHERE academic_year_class_stream_id = ? AND day_of_week = ? AND time_slot_id = (
                         SELECT id FROM time_slots WHERE start_time = ? ORDER BY period_number LIMIT 1
                     )"
                );
                $stmt->execute([$classStreamId, $dayNum, $data['start_time']]);
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
                    cs1.teacher_name,
                    cs1.class_name as class_1, cs2.class_name as class_2,
                    'teacher_overlap' as conflict_type
                FROM vw_timetable_entries cs1
                JOIN vw_timetable_entries cs2 ON cs1.teacher_id = cs2.teacher_id
                    AND cs1.day_of_week = cs2.day_of_week
                    AND cs1.academic_year_term_id = cs2.academic_year_term_id
                    AND cs1.id < cs2.id
                    AND cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time
                WHERE cs1.status = 'scheduled' AND cs2.status = 'scheduled'
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $teacherConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($teacherConflicts as $c) {
                $c['description'] = "{$c['teacher_name']} is double-booked: {$c['class_1']} and {$c['class_2']} on day {$c['day_of_week']} {$c['start_time']}-{$c['end_time']}";
                $conflicts[] = $c;
            }

            // Check room double-booking
            $sql = "
                SELECT
                    cs1.id as schedule_id_1, cs2.id as schedule_id_2,
                    cs1.day_of_week, cs1.start_time, cs1.end_time,
                    cs1.room_name,
                    cs1.class_name as class_1, cs2.class_name as class_2,
                    'room_overlap' as conflict_type
                FROM vw_timetable_entries cs1
                JOIN vw_timetable_entries cs2 ON cs1.room_id = cs2.room_id
                    AND cs1.day_of_week = cs2.day_of_week
                    AND cs1.academic_year_term_id = cs2.academic_year_term_id
                    AND cs1.id < cs2.id
                    AND cs1.start_time < cs2.end_time AND cs1.end_time > cs2.start_time
                WHERE cs1.status = 'scheduled' AND cs2.status = 'scheduled'
                AND cs1.room_id IS NOT NULL
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $roomConflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($roomConflicts as $c) {
                $c['description'] = "{$c['room_name']} is double-booked: {$c['class_1']} and {$c['class_2']} on day {$c['day_of_week']} {$c['start_time']}-{$c['end_time']}";
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

            // Filter by term (academic_year_terms.id)
            if (!empty($params['term_id'])) {
                $conditions[] = "term_id = ?";
                $bindings[] = $params['term_id'];
            }

            // Filter by academic year
            if (!empty($params['academic_year_id'])) {
                $conditions[] = "academic_year_id = ?";
                $bindings[] = $params['academic_year_id'];
            }

            // Filter by class
            if (!empty($params['class_id'])) {
                $conditions[] = "class_id = ?";
                $bindings[] = $params['class_id'];
            }

            // Filter by status
            if (!empty($params['status'])) {
                $conditions[] = "status = ?";
                $bindings[] = $params['status'];
            }

            // Filter by exam type
            if (!empty($params['exam_type'])) {
                $conditions[] = "exam_type = ?";
                $bindings[] = $params['exam_type'];
            }

            $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

            // vw_upcoming_exam_schedules joins exam_schedules -> academic_year_class_streams
            // -> academic_year_classes -> classes, learning_areas, rooms and staff->persons,
            // exposing the legacy-friendly aliases term_id/academic_year_id/class_id/subject_id.
            $sql = "
                SELECT *
                FROM vw_upcoming_exam_schedules
                {$whereClause}
                ORDER BY exam_date ASC, start_time ASC
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
                SELECT *
                FROM vw_upcoming_exam_schedules
                WHERE id = ?
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

            $academicYearId = (int) ($data['academic_year_id'] ?? $this->getCurrentAcademicYearId());
            $sql = "
                INSERT INTO exam_schedules (
                    academic_year_class_stream_id,
                    academic_year_term_id,
                    learning_area_id,
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
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $this->resolveClassStreamId((int) ($data['class_id'] ?? 0), $academicYearId),
                $this->resolveAcademicYearTermId((int) ($data['term_id'] ?? 0), $academicYearId),
                $this->resolveLearningAreaId((int) ($data['subject_id'] ?? 0)),
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
                $data['created_by'] ?? $this->user_id,
                $data['status'] ?? 'scheduled'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Exam schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Bulk-generate exam schedules for every active class stream x active learning
     * area in a term. Delegates to sp_create_exam_schedule.
     */
    public function bulkGenerateExamSchedule($data) {
        try {
            $required = ['term_id', 'exam_type', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $stmt = $this->db->prepare('CALL sp_create_exam_schedule(:term_id, :exam_type, :start_date, :end_date, :created_by)');
            $stmt->execute([
                'term_id' => $data['term_id'],
                'exam_type' => $data['exam_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'created_by' => $data['created_by'] ?? $this->user_id
            ]);

            return successResponse(['message' => 'Exam schedules generated successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateExamSchedule($id, $data) {
        try {
            // Build dynamic UPDATE mapped to live columns
            $fields = [];
            $values = [];

            $columnMap = [
                'term_id' => 'academic_year_term_id',
                'class_id' => 'academic_year_class_stream_id',
                'subject_id' => 'learning_area_id',
                'exam_name' => 'exam_name',
                'exam_type' => 'exam_type',
                'exam_date' => 'exam_date',
                'start_time' => 'start_time',
                'end_time' => 'end_time',
                'duration_minutes' => 'duration_minutes',
                'room_id' => 'room_id',
                'venue' => 'venue',
                'invigilator_id' => 'invigilator_id',
                'supervisor_id' => 'supervisor_id',
                'notes' => 'notes',
                'status' => 'status'
            ];

            $academicYearId = (int) ($data['academic_year_id'] ?? $this->getCurrentAcademicYearId());

            foreach ($columnMap as $inputField => $column) {
                if (!array_key_exists($inputField, $data) || $data[$inputField] === null) {
                    continue;
                }
                $value = $data[$inputField];
                if ($inputField === 'class_id') {
                    $value = $this->resolveClassStreamId((int) $value, $academicYearId);
                } elseif ($inputField === 'subject_id') {
                    $value = $this->resolveLearningAreaId((int) $value);
                } elseif ($inputField === 'term_id') {
                    $value = $this->resolveAcademicYearTermId((int) $value, $academicYearId);
                }
                $fields[] = "{$column} = ?";
                $values[] = $value;
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
            $events = $this->calendarSync->getUnifiedEvents();

            if (!empty($params['type'])) {
                $type = strtolower($params['type']);
                $events = array_values(array_filter($events, function ($e) use ($type) {
                    return strtolower($e['type']) === $type;
                }));
            }

            return successResponse($events);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createEvent($data) {
        try {
            $title = isset($data['name']) && $data['name'] !== '' ? $data['name'] : ($data['title'] ?? null);
            $missing = [];
            if ($title === null || $title === '') {
                $missing[] = 'name';
            }
            if (empty($data['start_date'])) {
                $missing[] = 'start_date';
            }
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $startAt = $data['start_date'] . ' ' . ($data['start_time'] ?? '00:00:00');
            $endAt = !empty($data['end_date'])
                ? $data['end_date'] . ' ' . ($data['end_time'] ?? '23:59:59')
                : $startAt;

            $sql = "
                INSERT INTO school_events (
                    title,
                    description,
                    start_at,
                    end_at,
                    type,
                    location,
                    status,
                    source
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'manual')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $title,
                $data['description'] ?? null,
                $startAt,
                $endAt,
                $data['type'] ?? 'general',
                $data['location'] ?? null,
                $data['status'] ?? 'upcoming'
            ]);

            $eventId = (int) $this->db->lastInsertId();

            $link = $this->calendarSync->applyEventToCalendar($eventId, $data);

            return successResponse([
                'id' => $eventId,
                'calendar_day_id' => $link['calendar_day'],
                'message' => 'Event created successfully'
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateEvent($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM school_events WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return errorResponse(['message' => 'Event not found'], 404);
            }

            $fields = [];
            $values = [];

            $columnMap = [
                'name' => 'title',
                'title' => 'title',
                'description' => 'description',
                'type' => 'type',
                'location' => 'location',
                'status' => 'status'
            ];

            foreach ($columnMap as $inputField => $column) {
                if (array_key_exists($inputField, $data) && $data[$inputField] !== null) {
                    $fields[] = "{$column} = ?";
                    $values[] = $data[$inputField];
                }
            }

            if (array_key_exists('start_date', $data) && $data['start_date'] !== null) {
                $fields[] = "start_at = ?";
                $values[] = $data['start_date'] . ' ' . ($data['start_time'] ?? '00:00:00');
            } elseif (array_key_exists('start_time', $data) && $data['start_time'] !== null) {
                $fields[] = "start_at = CONCAT(DATE_FORMAT(start_at, '%Y-%m-%d '), ?)";
                $values[] = $data['start_time'];
            }

            if (array_key_exists('end_date', $data) && $data['end_date'] !== null) {
                $fields[] = "end_at = ?";
                $values[] = $data['end_date'] . ' ' . ($data['end_time'] ?? '23:59:59');
            } elseif (array_key_exists('end_time', $data) && $data['end_time'] !== null) {
                $fields[] = "end_at = CONCAT(DATE_FORMAT(end_at, '%Y-%m-%d '), ?)";
                $values[] = $data['end_time'];
            }

            if (empty($fields)) {
                return errorResponse(['message' => 'No valid fields to update'], 400);
            }

            $values[] = $id;
            $sql = "UPDATE school_events SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            $link = $this->calendarSync->applyEventToCalendar((int) $id, $data);

            return successResponse(['id' => $id, 'calendar_day_id' => $link['calendar_day'], 'message' => 'Event updated successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteEvent($id) {
        try {
            $result = $this->calendarSync->handleEventDelete((int) $id);

            if ($result['calendar_day'] === null) {
                $stmt = $this->db->prepare("UPDATE school_events SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$id]);

                if ($stmt->rowCount() === 0) {
                    return errorResponse(['message' => 'Event not found'], 404);
                }
            }

            return successResponse(['message' => 'Event deleted successfully']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Full calendar <-> events reconciliation (called on events page load).
     */
    public function syncEvents($params = []) {
        try {
            $synced = $this->calendarSync->syncAcademicYear(null);
            $normalized = $this->calendarSync->normalizeStatuses();

            return successResponse([
                'status' => 'success',
                'message' => 'Calendar and events are now in sync',
                'synced' => $synced,
                'normalized' => $normalized
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Mark the whole Mon-Fri week containing the given date as exam week.
     */
    public function markExamWeek($data) {
        try {
            $anchor = $data['date'] ?? $data['start_date'] ?? null;
            if (!$anchor) {
                return errorResponse(['message' => 'A date within the exam week is required'], 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
                return errorResponse(['message' => 'Invalid date format'], 400);
            }

            $result = $this->calendarSync->markExamWeek($anchor);

            return successResponse([
                'status' => 'success',
                'message' => $result['message'],
                'result' => $result
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // =============================
    // Holiday Registry (UI-managed)
    // school_holidays is the single master list of ALL holidays (national,
    // religious - Idd moves with the moon, inter-term April/August/December,
    // school). sp_generate_year_calendar reads it and materializes holidays
    // onto academic_year_calendar_days, so editing a holiday here and clicking
    // "Apply to Calendar" updates the whole year calendar + events.
    // =============================

    public function getHolidays($params = []) {
        try {
            $where = '1=1';
            $bindings = [];
            if (!empty($params['year'])) {
                $year = (int) $params['year'];
                $where .= ' AND (YEAR(start_date) = ? OR YEAR(end_date) = ?)';
                $bindings = [$year, $year];
            }
            if (isset($params['active']) && $params['active'] !== '') {
                $where .= ' AND is_active = ?';
                $bindings[] = (int) $params['active'];
            }
            if (!empty($params['holiday_type'])) {
                $where .= ' AND holiday_type = ?';
                $bindings[] = $params['holiday_type'];
            }

            $stmt = $this->db->prepare(
                "SELECT id, name, holiday_type, start_date, end_date, description, is_active, created_at, updated_at
                 FROM school_holidays WHERE $where ORDER BY start_date, name"
            );
            $stmt->execute($bindings);

            return successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createHoliday($data) {
        try {
            $name = trim((string) ($data['name'] ?? ''));
            $start = $data['start_date'] ?? null;
            $end = $data['end_date'] ?? $start;
            if ($name === '' || empty($start)) {
                return errorResponse('Holiday name and start date are required', 400);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $start)
                || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $end)) {
                return errorResponse('Invalid date format (expected YYYY-MM-DD)', 400);
            }
            if (strcmp((string) $end, (string) $start) < 0) {
                return errorResponse('End date cannot be before start date', 400);
            }
            $type = $data['holiday_type'] ?? 'school';
            if (!in_array($type, ['national', 'religious', 'inter_term', 'school'], true)) {
                return errorResponse('Invalid holiday type', 400);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO school_holidays (name, holiday_type, start_date, end_date, description, is_active)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $name,
                $type,
                $start,
                $end,
                $data['description'] ?? null,
                isset($data['is_active']) ? (int) $data['is_active'] : 1,
            ]);

            $id = (int) $this->db->lastInsertId();

            return successResponse(['id' => $id, 'message' => 'Holiday created'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateHoliday($id, $data) {
        try {
            $id = (int) $id;
            $fields = [
                'name' => 'name',
                'holiday_type' => 'holiday_type',
                'start_date' => 'start_date',
                'end_date' => 'end_date',
                'description' => 'description',
                'is_active' => 'is_active',
            ];
            $sets = [];
            $values = [];
            foreach ($fields as $from => $col) {
                if (array_key_exists($from, $data) && $data[$from] !== null) {
                    $sets[] = "$col = ?";
                    $values[] = $data[$from];
                }
            }
            if (empty($sets)) {
                return errorResponse('No fields to update', 400);
            }
            if (array_key_exists('start_date', $data) || array_key_exists('end_date', $data)) {
                $row = $this->db->prepare("SELECT start_date, end_date FROM school_holidays WHERE id = ?");
                $row->execute([$id]);
                $cur = $row->fetch(PDO::FETCH_ASSOC);
                if (!$cur) {
                    return errorResponse('Holiday not found', 404);
                }
                $start = $data['start_date'] ?? $cur['start_date'];
                $end = $data['end_date'] ?? $cur['end_date'];
                if (strcmp((string) $end, (string) $start) < 0) {
                    return errorResponse('End date cannot be before start date', 400);
                }
            }

            $values[] = $id;
            $stmt = $this->db->prepare("UPDATE school_holidays SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($values);
            if ($stmt->rowCount() === 0) {
                // rowCount is 0 both when the row is missing and when no column
                // changed; treat a missing row as an error.
                $check = $this->db->prepare("SELECT id FROM school_holidays WHERE id = ?");
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    return errorResponse('Holiday not found', 404);
                }
            }

            return successResponse(['id' => $id, 'message' => 'Holiday updated']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function deleteHoliday($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM school_holidays WHERE id = ?");
            $stmt->execute([(int) $id]);
            if ($stmt->rowCount() === 0) {
                return errorResponse('Holiday not found', 404);
            }
            return successResponse(['message' => 'Holiday deleted']);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Re-apply the holiday registry to the year calendar (regenerates the
     * calendar and re-syncs events). Called after holiday edits.
     */
    public function applyHolidays($params = []) {
        try {
            $yearId = (int) ($params['academic_year_id'] ?? 0);
            if (!$yearId) {
                $yearId = (int) $this->db->query(
                    "SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1"
                )->fetchColumn();
            }
            if (!$yearId) {
                return errorResponse('No academic year found', 404);
            }

            $calendarService = $this->contract('App\API\Modules\academic\AcademicCalendarService', $this->db);
            $result = $calendarService->generateYearCalendar($yearId);
            $this->calendarSync->syncAcademicYear($yearId);

            return successResponse([
                'message' => 'Holidays applied to the calendar',
                'calendar' => $result['data'] ?? null,
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getActivitySchedule($params = []) {
        try {
            $sql = "
                SELECT
                    a.id,
                    a.activity_id,
                    ac.title AS activity_name,
                    a.day_of_week,
                    a.schedule_date,
                    a.start_time,
                    a.end_time,
                    a.venue
                FROM activity_schedule a
                JOIN activities ac ON a.activity_id = ac.id
                ORDER BY a.schedule_date, a.start_time
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
            $required = ['activity_id', 'schedule_date', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return errorResponse(['fields' => $missing, 'message' => 'Missing required fields'], 400);
            }

            $sql = "
                INSERT INTO activity_schedule (
                    activity_id,
                    day_of_week,
                    schedule_date,
                    start_time,
                    end_time,
                    venue
                ) VALUES (?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['day_of_week'] ?? date('l', strtotime($data['schedule_date'])),
                $data['schedule_date'],
                $data['start_time'],
                $data['end_time'],
                $data['venue'] ?? null
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
                    COUNT(DISTINCT vte.id) as timetable_count,
                    COUNT(DISTINCT es.id) as exam_count
                FROM rooms r
                LEFT JOIN vw_timetable_entries vte ON r.id = vte.room_id AND vte.status = 'scheduled'
                LEFT JOIN exam_schedules es ON r.id = es.room_id
                    AND es.status IN ('scheduled', 'upcoming', 'in_progress')
                WHERE r.status IN ('active', 'maintenance')
                GROUP BY r.id
                ORDER BY r.building, r.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rooms as &$room) {
                $room['active_bookings'] = (int) ($room['timetable_count'] ?? 0) + (int) ($room['exam_count'] ?? 0);
            }
            unset($room);

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

    public function getRouteSchedule($params = []) {
        try {
            $sql = "
                SELECT 
                    rs.*,
                    r.name as route_name,
                    v.registration_number,
                    CONCAT(dp.first_name, ' ', dp.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count
                FROM route_schedules rs
                JOIN transport_routes r ON rs.route_id = r.id
                LEFT JOIN transport_vehicles v ON rs.vehicle_id = v.id
                LEFT JOIN staff d ON rs.driver_id = d.id
                LEFT JOIN persons dp ON dp.id = d.person_id
                LEFT JOIN student_transport_assignments ta ON r.id = ta.route_id AND ta.status = 'active'
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
                    direction,
                    departure_time,
                    pickup_time,
                    dropoff_time,
                    notes,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['route_id'],
                $data['vehicle_id'] ?? null,
                $data['driver_id'] ?? null,
                $data['day_of_week'],
                $data['direction'] ?? 'pickup',
                $data['departure_time'] ?? $data['pickup_time'],
                $data['pickup_time'],
                $data['dropoff_time'],
                $data['notes'] ?? null,
                $data['status'] ?? 'active'
            ]);

            $scheduleId = $this->db->lastInsertId();

            return successResponse(['id' => $scheduleId, 'message' => 'Route schedule created successfully'], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Resolve the current academic year id (fallback when one is not provided).
     */
    private function getCurrentAcademicYearId(): int
    {
        $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE is_current = 1 LIMIT 1")->fetchColumn();
        if ($yearId <= 0) {
            $yearId = (int) $this->db->query("SELECT id FROM academic_years WHERE status = 'active' ORDER BY id DESC LIMIT 1")->fetchColumn();
        }
        return $yearId;
    }

    /**
     * Resolve a legacy `class_id` to the live `academic_year_class_streams.id`.
     */
    private function resolveClassStreamId(int $classId, int $academicYearId = 0): int
    {
        if ($classId <= 0) {
            return 0;
        }
        if ($academicYearId <= 0) {
            $academicYearId = $this->getCurrentAcademicYearId();
        }
        if ($academicYearId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare(
            "SELECT aycs.id
             FROM academic_year_class_streams aycs
             JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND ayc.class_id = ?
             ORDER BY aycs.id LIMIT 1"
        );
        $stmt->execute([$academicYearId, $classId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a legacy `term_id` (academic_year_terms.id or terms.id) to
     * academic_year_terms.id, defaulting to the current term of the year.
     */
    private function resolveAcademicYearTermId(int $termId, int $academicYearId = 0): int
    {
        if ($academicYearId <= 0) {
            $academicYearId = $this->getCurrentAcademicYearId();
        }
        if ($academicYearId <= 0) {
            return 0;
        }
        if ($termId > 0) {
            $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE id = ? AND academic_year_id = ? LIMIT 1");
            $stmt->execute([$termId, $academicYearId]);
            $found = (int) $stmt->fetchColumn();
            if ($found > 0) {
                return $found;
            }
            $stmt = $this->db->prepare("SELECT ayt.id FROM academic_year_terms ayt WHERE ayt.academic_year_id = ? AND ayt.term_id = ? LIMIT 1");
            $stmt->execute([$academicYearId, $termId]);
            $found = (int) $stmt->fetchColumn();
            if ($found > 0) {
                return $found;
            }
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'current' LIMIT 1");
        $stmt->execute([$academicYearId]);
        $found = (int) $stmt->fetchColumn();
        if ($found > 0) {
            return $found;
        }
        $stmt = $this->db->prepare("SELECT id FROM academic_year_terms WHERE academic_year_id = ? AND status = 'upcoming' ORDER BY opening_date LIMIT 1");
        $stmt->execute([$academicYearId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Resolve a legacy `subject_id` (strand.id or learning_areas.id) to learning_areas.id.
     */
    private function resolveLearningAreaId(int $subjectId): int
    {
        if ($subjectId <= 0) {
            return 0;
        }
        $stmt = $this->db->prepare("SELECT learning_area_id FROM strands WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        $learningAreaId = (int) $stmt->fetchColumn();
        if ($learningAreaId > 0) {
            return $learningAreaId;
        }
        $stmt = $this->db->prepare("SELECT id FROM learning_areas WHERE id = ? LIMIT 1");
        $stmt->execute([$subjectId]);
        return (int) $stmt->fetchColumn();
    }

    /** List resumable timetable bundles, including their current workflow state. */
    public function listTimetableDrafts(array $filters = []): array
    {
        $sql = "SELECT d.*, COUNT(e.id) AS entry_count
                FROM timetable_drafts d
                LEFT JOIN timetable_draft_entries e ON e.draft_id = d.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['academic_year_term_id'])) { $sql .= " AND d.academic_year_term_id = ?"; $params[] = (int) $filters['academic_year_term_id']; }
        if (!empty($filters['status'])) { $sql .= " AND d.status = ?"; $params[] = $filters['status']; }
        if (array_key_exists('_scope_stream_ids', $filters)) {
            $allowed = array_values(array_filter(array_map('intval', (array)$filters['_scope_stream_ids']), static function ($id) { return $id > 0; }));
            if (!$allowed) return successResponse([]);
            $sql .= ' AND EXISTS (SELECT 1 FROM timetable_draft_entries scope_e WHERE scope_e.draft_id = d.id AND scope_e.academic_year_class_stream_id IN (' . implode(',', array_fill(0, count($allowed), '?')) . '))';
            $params = array_merge($params, $allowed);
        }
        $sql .= " GROUP BY d.id ORDER BY d.updated_at DESC";
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        return successResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function listTimetableStreams(array $filters = []): array
    {
        $sql = "SELECT aycs.id AS academic_year_class_stream_id, aycs.class_teacher_id, c.id AS class_id, c.name AS class_name, c.grade_level,
                       COALESCE(st.name, 'A') AS stream_name,
                       sla.id AS stream_learning_area_id, aycla.id AS class_learning_area_id, aycla.learning_area_id,
                       la.name AS learning_area_name
                FROM academic_year_class_streams aycs
                JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                JOIN classes c ON c.id = ayc.class_id
                LEFT JOIN streams st ON st.id = aycs.stream_id
                LEFT JOIN academic_year_class_learning_areas aycla
                    ON aycla.academic_year_class_id = ayc.id
                   AND aycla.status IN ('planned','active')
                LEFT JOIN academic_year_class_stream_learning_areas sla
                    ON sla.academic_year_class_stream_id = aycs.id
                   AND sla.academic_year_class_learning_area_id = aycla.id
                   AND sla.status IN ('planned','active','in_progress','covered')
                LEFT JOIN learning_areas la ON la.id = aycla.learning_area_id
                WHERE ayc.academic_year_id = ? AND aycs.status IN ('planning','active')
                ORDER BY c.id, stream_name, la.name";
        $params = [(int)($filters['academic_year_id'] ?? 0)];
        if (array_key_exists('_scope_stream_ids', $filters)) {
            $allowed = array_values(array_filter(array_map('intval', (array)$filters['_scope_stream_ids']), static function ($id) { return $id > 0; }));
            if (!$allowed) return successResponse([]);
            $sql = str_replace('ORDER BY c.id, stream_name, la.name', 'AND aycs.id IN (' . implode(',', array_fill(0, count($allowed), '?')) . ') ORDER BY c.id, stream_name, la.name', $sql);
            $params = array_merge($params, $allowed);
        }
        $stmt = $this->db->prepare($sql); $stmt->execute($params);
        $streams = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['academic_year_class_stream_id'];
            if (!isset($streams[$id])) {
                $streams[$id] = [
                    'academic_year_class_stream_id' => $id,
                    'class_id' => (int)$row['class_id'],
                    'class_teacher_id' => (int)($row['class_teacher_id'] ?? 0),
                    'class_name' => $row['class_name'],
                    'stream_name' => $row['stream_name'],
                    'learning_areas' => [],
                ];
            }
            if (!empty($row['learning_area_id'])) {
                $streams[$id]['learning_areas'][] = [
                    'id' => (int)$row['learning_area_id'],
                    'name' => $row['learning_area_name'],
                    'class_learning_area_id' => (int)$row['class_learning_area_id'],
                    'stream_learning_area_id' => (int)($row['stream_learning_area_id'] ?? 0),
                ];
            }
        }
        return successResponse(array_values($streams));
    }

    public function saveDutyRosterDraft(array $data): array
    {
        foreach (['academic_year_id','academic_year_term_id','title','start_date','end_date','created_by'] as $f) if (($data[$f] ?? '') === '') return errorResponse("Missing required field: {$f}", 400);
        $id=(int)($data['id']??0); $entries=is_array($data['entries']??null)?$data['entries']:[];
        try { $this->db->beginTransaction();
            if($id){$q=$this->db->prepare("SELECT status FROM duty_roster_drafts WHERE id=? FOR UPDATE");$q->execute([$id]);$st=$q->fetchColumn();if(!$st)throw new Exception('Duty roster draft not found');if(in_array($st,['approved','published','cancelled'],true))throw new Exception('Duty roster draft is locked');$this->db->prepare("UPDATE duty_roster_drafts SET title=?,start_date=?,end_date=?,status='draft' WHERE id=?")->execute([$data['title'],$data['start_date'],$data['end_date'],$id]);$this->db->prepare("DELETE FROM duty_roster_draft_entries WHERE draft_id=?")->execute([$id]);}
            else{$q=$this->db->prepare("INSERT INTO duty_roster_drafts(academic_year_id,academic_year_term_id,title,start_date,end_date,created_by) VALUES(?,?,?,?,?,?)");$q->execute([(int)$data['academic_year_id'],(int)$data['academic_year_term_id'],$data['title'],$data['start_date'],$data['end_date'],(int)$data['created_by']]);$id=(int)$this->db->lastInsertId();}
            $ins=$this->db->prepare("INSERT INTO duty_roster_draft_entries(draft_id,staff_id,date,duty_type_id,shift,start_time,end_time,location,notes,swapped_with_id) VALUES(?,?,?,?,?,?,?,?,?,NULLIF(?,0))");
            $seen=[]; foreach($entries as $e){if(empty($e['staff_id'])||empty($e['date'])||empty($e['duty_type_id']))continue;$key=$e['staff_id'].'|'.$e['date'].'|'.($e['shift']??'full_day');if(isset($seen[$key]))throw new Exception('A staff member has duplicate duty assignments for the same date and shift.');$seen[$key]=1;$ins->execute([$id,(int)$e['staff_id'],$e['date'],(int)$e['duty_type_id'],$e['shift']??'full_day',$e['start_time']??null,$e['end_time']??null,$e['location']??null,$e['notes']??null,(int)($e['swapped_with_id']??0)]);}
            $this->db->commit();return successResponse(['id'=>$id,'status'=>'draft','entry_count'=>count($entries)],'Duty roster draft saved');
        }catch(Exception $e){if($this->db->inTransaction())$this->db->rollBack();return errorResponse($e->getMessage(),400);}
    }

    public function listDutyRosterDrafts(array $filters=[]): array { $q=$this->db->prepare("SELECT d.*,COUNT(e.id) entry_count FROM duty_roster_drafts d LEFT JOIN duty_roster_draft_entries e ON e.draft_id=d.id GROUP BY d.id ORDER BY d.updated_at DESC");$q->execute();return successResponse($q->fetchAll(PDO::FETCH_ASSOC)); }
    public function getDutyRosterDraft($id): array { $q=$this->db->prepare("SELECT * FROM duty_roster_drafts WHERE id=?");$q->execute([(int)$id]);$d=$q->fetch(PDO::FETCH_ASSOC);if(!$d)return errorResponse('Duty roster draft not found',404);$q=$this->db->prepare("SELECT * FROM duty_roster_draft_entries WHERE draft_id=? ORDER BY date,start_time");$q->execute([(int)$id]);$d['entries']=$q->fetchAll(PDO::FETCH_ASSOC);return successResponse($d); }

    public function saveExamTimetableDraft(array $data): array
    {
        foreach (['academic_year_id', 'academic_year_term_id', 'title', 'created_by'] as $field) {
            if (($data[$field] ?? '') === '') return errorResponse("Missing required field: {$field}", 400);
        }
        $id = (int) ($data['id'] ?? 0);
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
        if (!$entries) return errorResponse('Add at least one examination paper to the timetable', 422);

        try {
            $this->db->beginTransaction();
            $term = $this->db->prepare(
                'SELECT opening_date, closing_date FROM academic_year_terms WHERE id = ? AND academic_year_id = ? LIMIT 1'
            );
            $term->execute([(int) $data['academic_year_term_id'], (int) $data['academic_year_id']]);
            $termDates = $term->fetch(PDO::FETCH_ASSOC);
            if (!$termDates) throw new \InvalidArgumentException('The selected term does not belong to the selected academic year');

            if ($id) {
                $query = $this->db->prepare('SELECT status FROM exam_timetable_drafts WHERE id = ? FOR UPDATE');
                $query->execute([$id]);
                $status = $query->fetchColumn();
                if (!$status) throw new \InvalidArgumentException('Exam timetable draft not found');
                if (in_array($status, ['approved', 'published', 'cancelled'], true)) {
                    throw new \InvalidArgumentException('This exam timetable draft is locked');
                }
                $this->db->prepare(
                    "UPDATE exam_timetable_drafts SET title = ?, status = 'draft' WHERE id = ?"
                )->execute([trim((string) $data['title']), $id]);
                $this->db->prepare('DELETE FROM exam_timetable_draft_entries WHERE draft_id = ?')->execute([$id]);
            } else {
                $query = $this->db->prepare(
                    'INSERT INTO exam_timetable_drafts
                        (academic_year_id, academic_year_term_id, title, created_by)
                     VALUES (?, ?, ?, ?)'
                );
                $query->execute([
                    (int) $data['academic_year_id'],
                    (int) $data['academic_year_term_id'],
                    trim((string) $data['title']),
                    (int) $data['created_by'],
                ]);
                $id = (int) $this->db->lastInsertId();
            }

            $streamAreaCheck = $this->db->prepare(
                "SELECT sla.id
                 FROM academic_year_class_stream_learning_areas sla
                 JOIN academic_year_class_learning_areas cla
                   ON cla.id = sla.academic_year_class_learning_area_id
                 JOIN academic_year_class_streams aycs ON aycs.id = sla.academic_year_class_stream_id
                 JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                 WHERE sla.academic_year_class_stream_id = ? AND cla.learning_area_id = ?
                   AND ayc.academic_year_id = ?
                   AND sla.status IN ('planned','active','in_progress','covered')
                 LIMIT 1"
            );
            $typeCheck = $this->db->prepare(
                "SELECT name FROM assessment_types
                 WHERE id = ? AND is_summative = 1 AND status = 'active' LIMIT 1"
            );
            $insert = $this->db->prepare(
                'INSERT INTO exam_timetable_draft_entries
                    (draft_id, academic_year_class_stream_id, learning_area_id, exam_name, exam_type,
                     assessment_type_id, max_marks, exam_date, start_time, end_time, duration_minutes,
                     room_id, venue, invigilator_id, supervisor_id, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, 0), ?, NULLIF(?, 0), NULLIF(?, 0), ?)'
            );

            $seen = [];
            foreach ($entries as $index => $entry) {
                $number = $index + 1;
                $streamId = (int) ($entry['academic_year_class_stream_id'] ?? 0);
                $learningAreaId = (int) ($entry['learning_area_id'] ?? 0);
                $assessmentTypeId = (int) ($entry['assessment_type_id'] ?? 0);
                $maxMarks = (float) ($entry['max_marks'] ?? 0);
                $examName = trim((string) ($entry['exam_name'] ?? ''));
                $date = (string) ($entry['exam_date'] ?? '');
                $start = (string) ($entry['start_time'] ?? '');
                $end = (string) ($entry['end_time'] ?? '');
                if (!$streamId || !$learningAreaId || !$assessmentTypeId || $examName === '' || $date === '' || $start === '' || $end === '') {
                    throw new \InvalidArgumentException("Exam row {$number} is incomplete");
                }
                if ($maxMarks <= 0) throw new \InvalidArgumentException("Exam row {$number} requires maximum marks greater than zero");
                if ($end <= $start) throw new \InvalidArgumentException("Exam row {$number} must end after it starts");
                if ((!empty($termDates['opening_date']) && $date < $termDates['opening_date'])
                    || (!empty($termDates['closing_date']) && $date > $termDates['closing_date'])) {
                    throw new \InvalidArgumentException("Exam row {$number} falls outside the selected term");
                }

                $streamAreaCheck->execute([$streamId, $learningAreaId, (int) $data['academic_year_id']]);
                if (!$streamAreaCheck->fetchColumn()) {
                    throw new \InvalidArgumentException("Exam row {$number} uses a learning area not assigned to that class stream");
                }
                $typeCheck->execute([$assessmentTypeId]);
                $typeName = $typeCheck->fetchColumn();
                if (!$typeName) throw new \InvalidArgumentException("Exam row {$number} must use an active summative assessment type");

                foreach ($seen as $old) {
                    $overlap = $old['date'] === $date && $old['start'] < $end && $old['end'] > $start;
                    $resourceConflict = $old['stream'] === $streamId
                        || (!empty($entry['room_id']) && $old['room'] === (int) $entry['room_id'])
                        || (!empty($entry['invigilator_id']) && $old['invigilator'] === (int) $entry['invigilator_id']);
                    if ($overlap && $resourceConflict) {
                        throw new \InvalidArgumentException("Exam row {$number} conflicts with another class, room, or invigilator allocation");
                    }
                }
                $seen[] = [
                    'date' => $date,
                    'start' => $start,
                    'end' => $end,
                    'stream' => $streamId,
                    'room' => (int) ($entry['room_id'] ?? 0),
                    'invigilator' => (int) ($entry['invigilator_id'] ?? 0),
                ];

                $duration = (int) ($entry['duration_minutes'] ?? 0);
                if ($duration <= 0) {
                    $duration = (int) round((strtotime($end) - strtotime($start)) / 60);
                }
                $insert->execute([
                    $id, $streamId, $learningAreaId, $examName, (string) ($entry['exam_type'] ?? $typeName),
                    $assessmentTypeId, $maxMarks, $date, $start, $end, $duration,
                    (int) ($entry['room_id'] ?? 0), trim((string) ($entry['venue'] ?? '')) ?: null,
                    (int) ($entry['invigilator_id'] ?? 0), (int) ($entry['supervisor_id'] ?? 0),
                    trim((string) ($entry['notes'] ?? '')) ?: null,
                ]);
            }

            $this->db->commit();
            return successResponse(['id' => $id, 'status' => 'draft', 'entry_count' => count($entries)], 'Exam timetable draft saved');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return errorResponse($e->getMessage(), $e instanceof \InvalidArgumentException ? 422 : 400);
        }
    }

    public function listExamTimetableDrafts(array $filters = []): array
    {
        $query = $this->db->query(
            'SELECT d.*, COUNT(e.id) AS entry_count
             FROM exam_timetable_drafts d
             LEFT JOIN exam_timetable_draft_entries e ON e.draft_id = d.id
             GROUP BY d.id ORDER BY d.updated_at DESC'
        );
        return successResponse($query->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getExamTimetableDraft($id): array
    {
        $query = $this->db->prepare('SELECT * FROM exam_timetable_drafts WHERE id = ?');
        $query->execute([(int) $id]);
        $draft = $query->fetch(PDO::FETCH_ASSOC);
        if (!$draft) return errorResponse('Exam timetable draft not found', 404);
        $query = $this->db->prepare(
            'SELECT e.*, at.name AS assessment_type_name
             FROM exam_timetable_draft_entries e
             LEFT JOIN assessment_types at ON at.id = e.assessment_type_id
             WHERE e.draft_id = ? ORDER BY e.exam_date, e.start_time'
        );
        $query->execute([(int) $id]);
        $draft['entries'] = $query->fetchAll(PDO::FETCH_ASSOC);
        return successResponse($draft);
    }

    public function transitionDutyRosterDraft(array $data): array { return $this->transitionScheduleDraft('duty',(int)($data['id']??0),$data['action']??'',(int)($data['actor_id']??0),$data['comments']??null); }
    public function transitionExamTimetableDraft(array $data): array
    {
        return $this->transitionScheduleDraft(
            'exam',
            (int) ($data['id'] ?? 0),
            (string) ($data['action'] ?? ''),
            (int) ($data['actor_id'] ?? 0),
            $data['comments'] ?? null
        );
    }
    private function transitionScheduleDraft(string $type,int $id,string $action,int $actor,$comments): array {
        $table=$type==='duty'?'duty_roster_drafts':'exam_timetable_drafts';$entryTable=$type==='duty'?'duty_roster_draft_entries':'exam_timetable_draft_entries';
        $allowed=['submit'=>['draft','changes_requested'],'review'=>['submitted'],'request_changes'=>['submitted'],'approve'=>['submitted'],'publish'=>['approved']];if(!$id||!isset($allowed[$action]))return errorResponse('Draft id and valid action are required',400);
        $to=['submit'=>'submitted','review'=>'submitted','request_changes'=>'changes_requested','approve'=>'approved','publish'=>'published'][$action];
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare("SELECT status FROM {$table} WHERE id = ? FOR UPDATE");
            $q->execute([$id]);
            $from = $q->fetchColumn();
            if (!$from || !in_array($from, $allowed[$action], true)) {
                throw new \RuntimeException('Invalid workflow transition', 409);
            }
            $count = $this->db->prepare("SELECT COUNT(*) FROM {$entryTable} WHERE draft_id = ?");
            $count->execute([$id]);
            if ((int) $count->fetchColumn() === 0) throw new \RuntimeException('An empty schedule cannot be submitted or published', 422);
            if ($action === 'request_changes' && trim((string) $comments) === '') {
                throw new \RuntimeException('Explain the changes required', 422);
            }
            if ($action === 'publish') $this->publishScheduleDraft($type, $id, $entryTable, $actor);
            $this->db->prepare(
                "UPDATE {$table}
                 SET status = ?, submitted_at = IF(? = 'submitted', NOW(), submitted_at),
                     approved_at = IF(? = 'approved', NOW(), approved_at),
                     published_at = IF(? = 'published', NOW(), published_at)
                 WHERE id = ?"
            )->execute([$to, $to, $to, $to, $id]);
            $reviewAction=['submit'=>'submitted','review'=>'reviewed','request_changes'=>'changes_requested','approve'=>'approved','publish'=>'published'][$action];
            $this->db->prepare(
                'INSERT INTO schedule_draft_reviews(document_type,draft_id,reviewer_id,action,comments) VALUES(?,?,?,?,?)'
            )->execute([$type, $id, $actor, $reviewAction, $comments]);
            $this->db->commit();
            $this->notifyScheduleTransition($type, $id, $to);
            return successResponse(['id' => $id, 'status' => $to], "{$type} schedule draft {$to}");
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $code = $e->getCode() >= 400 && $e->getCode() <= 599 ? $e->getCode() : 400;
            return errorResponse($e->getMessage(), $code);
        }
    }
    private function notifyScheduleTransition(string $type,int $id,string $status): void { try{$n=new NotificationService($this->db);$label=$type==='duty'?'duty roster':'exam timetable';$recipients=$status==='submitted'?['role:headteacher','role:deputy_head_academic','role:school_admin']:'all_staff';$n->push($recipients,'schedule_workflow',ucfirst($label).' '.$status,"The {$label} draft #{$id} is now {$status}.",'medium',['reference_type'=>$type.'_schedule_draft','reference_id'=>$id,'action_url'=>'home.php?route='.($type==='duty'?'duty_roster':'exam_timetable_drafts')]);}catch(\Throwable $e){\App\API\Services\Logger::legacyError('[SchedulesAPI] notification failed: '.$e->getMessage());} }
    private function publishScheduleDraft(string $type,int $id,string $entryTable,int $actor): void {
        if($type==='duty'){$q=$this->db->prepare("SELECT * FROM {$entryTable} WHERE draft_id=?");$q->execute([$id]);$ins=$this->db->prepare("INSERT INTO staff_duty_roster(staff_id,date,duty_type_id,shift,start_time,end_time,location,notes,is_swap,swapped_with_id) VALUES(?,?,?,?,?,?,?,?,?,?)");foreach($q as $e)$ins->execute([$e['staff_id'],$e['date'],$e['duty_type_id'],$e['shift'],$e['start_time'],$e['end_time'],$e['location'],$e['notes'],empty($e['swapped_with_id'])?0:1,$e['swapped_with_id']]);}
        else {
            $query = $this->db->prepare(
                "SELECT d.academic_year_term_id, e.*
                 FROM exam_timetable_drafts d JOIN {$entryTable} e ON e.draft_id = d.id
                 WHERE d.id = ? ORDER BY e.exam_date, e.start_time"
            );
            $query->execute([$id]);
            $entries = $query->fetchAll(PDO::FETCH_ASSOC);
            $marker = $this->db->prepare(
                "SELECT staff_id FROM academic_year_class_stream_learning_area_teachers
                 WHERE academic_year_class_stream_id = ? AND academic_year_term_id = ?
                   AND learning_area_id = ? AND status = 'active'
                 ORDER BY FIELD(role, 'subject_teacher', 'hod', 'assistant'), id LIMIT 1"
            );
            $fallbackMarker = $this->db->prepare(
                "SELECT teacher.staff_id
                 FROM academic_year_class_streams stream
                 JOIN academic_year_class_learning_areas class_area
                   ON class_area.academic_year_class_id = stream.academic_year_class_id
                  AND class_area.learning_area_id = ?
                 JOIN academic_year_class_learning_area_teachers teacher
                   ON teacher.academic_year_class_learning_area_id = class_area.id
                  AND teacher.academic_year_term_id = ?
                 WHERE stream.id = ?
                 ORDER BY FIELD(teacher.role, 'subject_teacher', 'hod', 'assistant'), teacher.id LIMIT 1"
            );
            $classTeacher = $this->db->prepare('SELECT class_teacher_id FROM academic_year_class_streams WHERE id = ?');
            $conflict = $this->db->prepare(
                "SELECT id FROM exam_schedules
                 WHERE exam_date = ? AND start_time < ? AND end_time > ? AND status <> 'cancelled'
                   AND (academic_year_class_stream_id = ?
                        OR (? > 0 AND room_id = ?)
                        OR (? > 0 AND invigilator_id = ?))
                 LIMIT 1"
            );
            $assessmentInsert = $this->db->prepare(
                "INSERT INTO assessments
                    (academic_year_class_stream_id, academic_year_term_id, learning_area_id,
                     assessment_type_id, title, max_marks, assessment_date, assigned_by, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_submission')"
            );
            $scheduleInsert = $this->db->prepare(
                "INSERT INTO exam_schedules
                    (exam_timetable_draft_id, exam_timetable_draft_entry_id,
                     academic_year_class_stream_id, academic_year_term_id, learning_area_id, assessment_id,
                     exam_name, exam_type, exam_date, start_time, end_time, duration_minutes,
                     room_id, venue, invigilator_id, supervisor_id, notes, created_by,
                     published_by, published_at, status, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?,0), ?, NULLIF(?,0), NULLIF(?,0), ?, ?, ?, NOW(), 'scheduled', 'manual')"
            );

            foreach ($entries as $entry) {
                $roomId = (int) ($entry['room_id'] ?? 0);
                $invigilatorId = (int) ($entry['invigilator_id'] ?? 0);
                $conflict->execute([
                    $entry['exam_date'], $entry['end_time'], $entry['start_time'],
                    (int) $entry['academic_year_class_stream_id'], $roomId, $roomId,
                    $invigilatorId, $invigilatorId,
                ]);
                if ($conflict->fetchColumn()) {
                    throw new \RuntimeException('A published exam now conflicts with a class, room, or invigilator allocation', 409);
                }

                $marker->execute([
                    (int) $entry['academic_year_class_stream_id'],
                    (int) $entry['academic_year_term_id'],
                    (int) $entry['learning_area_id'],
                ]);
                $markerId = (int) ($marker->fetchColumn() ?: 0);
                if (!$markerId) {
                    $fallbackMarker->execute([
                        (int) $entry['learning_area_id'],
                        (int) $entry['academic_year_term_id'],
                        (int) $entry['academic_year_class_stream_id'],
                    ]);
                    $markerId = (int) ($fallbackMarker->fetchColumn() ?: 0);
                }
                if (!$markerId) {
                    $classTeacher->execute([(int) $entry['academic_year_class_stream_id']]);
                    $markerId = (int) ($classTeacher->fetchColumn() ?: 0);
                }
                if (!$markerId) {
                    throw new \RuntimeException('Every published exam requires an assigned marker for its class stream and learning area', 409);
                }

                $assessmentInsert->execute([
                    (int) $entry['academic_year_class_stream_id'],
                    (int) $entry['academic_year_term_id'],
                    (int) $entry['learning_area_id'],
                    (int) $entry['assessment_type_id'],
                    $entry['exam_name'],
                    (float) $entry['max_marks'],
                    $entry['exam_date'],
                    $markerId,
                ]);
                $assessmentId = (int) $this->db->lastInsertId();
                $scheduleInsert->execute([
                    $id, (int) $entry['id'],
                    (int) $entry['academic_year_class_stream_id'], (int) $entry['academic_year_term_id'],
                    (int) $entry['learning_area_id'], $assessmentId,
                    $entry['exam_name'], $entry['exam_type'], $entry['exam_date'],
                    $entry['start_time'], $entry['end_time'], (int) $entry['duration_minutes'],
                    $roomId, $entry['venue'], $invigilatorId, (int) ($entry['supervisor_id'] ?? 0),
                    $entry['notes'], $actor, $actor,
                ]);
            }
        }
    }

    /** Create or update a draft matrix. Empty cells are deliberately omitted. */
    public function saveTimetableDraft(array $data): array
    {
        $required = ['academic_year_id', 'academic_year_term_id', 'scope', 'title', 'created_by'];
        foreach ($required as $field) if (!isset($data[$field]) || $data[$field] === '') return errorResponse("Missing required field: {$field}", 400);
        if (!in_array($data['scope'], ['lower_primary', 'upper_primary', 'whole_school'], true)) return errorResponse('Invalid timetable scope', 400);
        $id = !empty($data['id']) ? (int) $data['id'] : 0;
        $entries = is_array($data['entries'] ?? null) ? $data['entries'] : [];
        try {
            $this->db->beginTransaction();
            if ($id) {
                $stmt = $this->db->prepare("SELECT status FROM timetable_drafts WHERE id = ? FOR UPDATE"); $stmt->execute([$id]);
                $status = $stmt->fetchColumn();
                if (!$status) throw new Exception('Timetable draft not found');
                if (in_array($status, ['approved','published','cancelled'], true)) throw new Exception('This timetable draft is locked');
                $this->db->prepare("UPDATE timetable_drafts SET title = ?, status = 'draft' WHERE id = ?")->execute([$data['title'], $id]);
                $this->db->prepare("DELETE FROM timetable_draft_entries WHERE draft_id = ?")->execute([$id]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO timetable_drafts (academic_year_id, academic_year_term_id, scope, title, created_by) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([(int)$data['academic_year_id'], (int)$data['academic_year_term_id'], $data['scope'], $data['title'], (int)$data['created_by']]);
                $id = (int) $this->db->lastInsertId();
            }
            $streamCheck = $this->db->prepare("SELECT ayc.id, aycs.class_teacher_id, c.name AS class_name FROM academic_year_class_streams aycs JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id JOIN classes c ON c.id = ayc.class_id WHERE aycs.id = ? AND ayc.academic_year_id = ? AND aycs.status IN ('planning','active') LIMIT 1");
            $slotCheck = $this->db->prepare("SELECT id FROM time_slots WHERE id = ? AND is_active = 1 LIMIT 1");
            $areaCheck = $this->db->prepare("SELECT sla.id, cla.learning_area_id FROM academic_year_class_stream_learning_areas sla JOIN academic_year_class_learning_areas cla ON cla.id = sla.academic_year_class_learning_area_id WHERE sla.academic_year_class_stream_id = ? AND cla.learning_area_id = ? AND sla.status IN ('planned','active','in_progress','covered') LIMIT 1");
            $teacherCheck = $this->db->prepare("SELECT ayclt.id FROM academic_year_class_learning_area_teachers ayclt WHERE ayclt.academic_year_class_learning_area_id = ? AND ayclt.academic_year_term_id = ? AND ayclt.staff_id = ? LIMIT 1");
            $streamTeacherCheck = $this->db->prepare("SELECT id FROM academic_year_class_stream_learning_area_teachers WHERE academic_year_class_stream_learning_area_id = ? AND academic_year_term_id = ? AND staff_id = ? AND status = 'active' LIMIT 1");
            $contextTeacherCount = $this->db->prepare("SELECT COUNT(*) FROM academic_year_class_stream_learning_area_teachers WHERE academic_year_class_stream_learning_area_id = ? AND academic_year_term_id = ? AND status = 'active'");
            $staffCheck = $this->db->prepare("SELECT id FROM staff WHERE id = ? AND status = 'active' LIMIT 1");
            $insert = $this->db->prepare("INSERT INTO timetable_draft_entries (draft_id, academic_year_class_stream_id, academic_year_class_stream_learning_area_id, day_of_week, time_slot_id, learning_area_id, teacher_id, room_id, notes) VALUES (?, ?, ?, ?, ?, NULLIF(?, 0), NULLIF(?, 0), NULLIF(?, 0), ?)");
            $seenCells = []; $seenTeachers = [];
            foreach ($entries as $entry) {
                $stream = (int)($entry['academic_year_class_stream_id'] ?? 0); $day = (int)($entry['day_of_week'] ?? 0); $slot = (int)($entry['time_slot_id'] ?? 0);
                if ($stream <= 0 || $day < 1 || $day > 7 || $slot <= 0) continue;
                $area = (int)($entry['learning_area_id'] ?? 0); $teacher = (int)($entry['teacher_id'] ?? 0);
                if ($area <= 0 && $teacher <= 0) continue;
                if ($area <= 0 || $teacher <= 0) throw new \InvalidArgumentException('Every lesson allocation must have both a learning area and an assigned teacher.');
                $cellKey = $stream . ':' . $day . ':' . $slot;
                if (isset($seenCells[$cellKey])) throw new \InvalidArgumentException('A class stream has more than one allocation in the same time slot.');
                $seenCells[$cellKey] = true;
                // A lower-primary class teacher may coordinate more than one
                // stream. Those streams are drafted independently in the
                // class-teacher workflow, so a same-time cell in two owned
                // streams is not rejected here. Master/subject timetables
                // retain the normal teacher-overlap protection.
                if (empty($data['_class_teacher_mode']) || ($data['scope'] ?? '') !== 'lower_primary') {
                    $teacherKey = $teacher . ':' . $day . ':' . $slot;
                    if (isset($seenTeachers[$teacherKey])) throw new \InvalidArgumentException('A teacher cannot be assigned to two class streams at the same time.');
                    $seenTeachers[$teacherKey] = true;
                }
                $streamCheck->execute([$stream, (int)$data['academic_year_id']]); $streamRow = $streamCheck->fetch(PDO::FETCH_ASSOC); $classId = (int)($streamRow['id'] ?? 0);
                if (!$classId) throw new \InvalidArgumentException('The selected class stream does not belong to the selected academic year.');
                if (!empty($data['_actor_staff_id'])) {
                    $allowed = array_map('intval', (array)($data['_scope_stream_ids'] ?? []));
                    if ($allowed && !in_array($stream, $allowed, true)) throw new \InvalidArgumentException('You may only timetable your assigned class streams.');
                    if (!empty($data['_class_teacher_mode'])) {
                        $scopeStmt = $this->db->prepare("SELECT 1 FROM vw_teacher_effective_stream_learning_areas WHERE staff_id = ? AND academic_year_class_stream_id = ? AND scope_type = 'class_teacher' LIMIT 1");
                        $scopeStmt->execute([(int) $data['_actor_staff_id'], $stream]);
                        if (!$scopeStmt->fetchColumn()) throw new \InvalidArgumentException('You may only timetable a stream assigned to you.');
                        if (preg_match('/playgroup|pre|grade [1-3]/i', (string)$streamRow['class_name']) && $teacher !== (int)$data['_actor_staff_id']) {
                            throw new \InvalidArgumentException('A lower-primary class teacher must remain the responsible teacher for this stream.');
                        }
                    }
                }
                $slotCheck->execute([$slot]); if (!$slotCheck->fetchColumn()) throw new \InvalidArgumentException('The selected timetable period is not active.');
                $areaCheck->execute([$stream, $area]); $areaRow = $areaCheck->fetch(PDO::FETCH_ASSOC); $streamLearningAreaId = (int)($areaRow['id'] ?? 0); $classAreaId = (int)($areaRow['learning_area_id'] ?? 0);
                if (!$streamLearningAreaId) throw new \InvalidArgumentException('The selected learning area is not configured for this stream.');
                $teacherCheck->execute([$classAreaId, (int)$data['academic_year_term_id'], $teacher]);
                $assigned = (bool)$teacherCheck->fetchColumn();
                $contextTeacherCount->execute([$streamLearningAreaId, (int)$data['academic_year_term_id']]);
                $hasCanonicalAssignment = (int)$contextTeacherCount->fetchColumn() > 0;
                $streamTeacherCheck->execute([$streamLearningAreaId, (int)$data['academic_year_term_id'], $teacher]);
                $canonicalAssigned = (bool)$streamTeacherCheck->fetchColumn();
                // Once a stream-learning-area has an explicit assignment, the
                // broad class-level table cannot grant access to another user.
                $assigned = $hasCanonicalAssignment ? $canonicalAssigned : $assigned;
                if (!empty($data['_academic_leadership_mode'])) {
                    $staffCheck->execute([$teacher]);
                    if (!$staffCheck->fetchColumn()) throw new \InvalidArgumentException('The selected teacher is not an active staff member.');
                } elseif (!$assigned && empty($data['_class_teacher_mode'])) {
                    throw new \InvalidArgumentException('The selected teacher is not assigned to this learning area and class for the selected term.');
                }
                $insert->execute([$id, $stream, $streamLearningAreaId, $day, $slot, $area, $teacher, (int)($entry['room_id'] ?? 0), $entry['notes'] ?? null]);
            }
            $this->db->commit();
            return successResponse(['id' => $id, 'status' => 'draft', 'entry_count' => count($entries)], 'Timetable draft saved');
        } catch (\InvalidArgumentException $e) { if ($this->db->inTransaction()) $this->db->rollBack(); return errorResponse($e->getMessage(), 400); }
          catch (Exception $e) { if ($this->db->inTransaction()) $this->db->rollBack(); \App\API\Services\Logger::legacyError('[SchedulesAPI] timetable draft save failed: ' . $e->getMessage()); return errorResponse('Timetable draft could not be saved', 400); }
    }

    public function getTimetableDraft($id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM timetable_drafts WHERE id = ?"); $stmt->execute([(int)$id]); $draft = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$draft) return errorResponse('Timetable draft not found', 404);
        $stmt = $this->db->prepare("SELECT * FROM timetable_draft_entries WHERE draft_id = ? ORDER BY day_of_week, time_slot_id, academic_year_class_stream_id"); $stmt->execute([(int)$id]);
        $draft['entries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return successResponse($draft);
    }

    public function transitionTimetableDraft(array $data): array
    {
        $id = (int)($data['id'] ?? 0); $action = $data['action'] ?? ''; $actor = (int)($data['actor_id'] ?? 0);
        $allowed = ['submit' => ['draft','changes_requested'], 'review' => ['submitted'], 'request_changes' => ['submitted'], 'approve' => ['submitted'], 'publish' => ['approved']];
        if (!$id || !isset($allowed[$action])) return errorResponse('Draft id and valid action are required', 400);
        $stmt = $this->db->prepare("SELECT status FROM timetable_drafts WHERE id = ? FOR UPDATE"); $stmt->execute([$id]); $from = $stmt->fetchColumn();
        if (!$from || !in_array($from, $allowed[$action], true)) return errorResponse("Cannot {$action} a draft in its current state", 409);
        $to = ['submit'=>'submitted','review'=>'submitted','request_changes'=>'changes_requested','approve'=>'approved','publish'=>'published'][$action];
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE timetable_drafts SET status = ?, submitted_at = IF(?='submitted', NOW(), submitted_at), approved_at = IF(?='approved', NOW(), approved_at), published_at = IF(?='published', NOW(), published_at) WHERE id = ?")->execute([$to,$to,$to,$to,$id]);
            $reviewAction = ['submit'=>'submitted','review'=>'reviewed','request_changes'=>'changes_requested','approve'=>'approved','publish'=>'published'][$action];
            $this->db->prepare("INSERT INTO timetable_draft_reviews (draft_id, reviewer_id, action, comments) VALUES (?, ?, ?, ?)")->execute([$id,$actor,$reviewAction,$data['comments'] ?? null]);
            if ($action === 'publish') {
                $this->publishDraftEntries($id);
            }
            $this->db->commit(); return successResponse(['id'=>$id,'status'=>$to], "Timetable draft {$to}");
        } catch (Exception $e) { if ($this->db->inTransaction()) $this->db->rollBack(); return errorResponse($e->getMessage(), 400); }
    }

    private function publishDraftEntries(int $draftId): void
    {
        $stmt = $this->db->prepare("SELECT academic_year_term_id FROM timetable_drafts WHERE id = ?"); $stmt->execute([$draftId]); $term = (int)$stmt->fetchColumn();
        $this->db->prepare("DELETE te FROM timetable_entries te JOIN timetable_draft_entries de ON de.academic_year_class_stream_id = te.academic_year_class_stream_id AND de.day_of_week = te.day_of_week AND de.time_slot_id = te.time_slot_id WHERE de.draft_id = ? AND te.academic_year_term_id = ?")->execute([$draftId,$term]);
        $rows = $this->db->prepare("SELECT * FROM timetable_draft_entries WHERE draft_id = ?"); $rows->execute([$draftId]);
        $insert = $this->db->prepare("INSERT INTO timetable_entries (academic_year_class_stream_id, academic_year_class_stream_learning_area_id, academic_year_term_id, day_of_week, time_slot_id, learning_area_id, teacher_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
        foreach ($rows as $row) $insert->execute([$row['academic_year_class_stream_id'],$row['academic_year_class_stream_learning_area_id'],$term,$row['day_of_week'],$row['time_slot_id'],$row['learning_area_id'],$row['teacher_id']]);
    }

    /**
     * Resolve a time_slot by explicit id, or by matching start/end time strings.
     */
    private function resolveTimeSlotId($timeSlotId, $startTime = null, $endTime = null): ?int
    {
        if (!empty($timeSlotId) && is_numeric($timeSlotId)) {
            $stmt = $this->db->prepare("SELECT id FROM time_slots WHERE id = ? AND is_active = 1 LIMIT 1");
            $stmt->execute([(int) $timeSlotId]);
            if ($stmt->fetchColumn()) {
                return (int) $timeSlotId;
            }
        }
        if ($startTime !== null && $startTime !== '') {
            $sql = "SELECT id FROM time_slots WHERE is_active = 1 AND start_time = ?";
            $params = [$startTime];
            if ($endTime !== null && $endTime !== '') {
                $sql .= " AND end_time = ?";
                $params[] = $endTime;
            }
            $sql .= " ORDER BY period_number LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $id = (int) $stmt->fetchColumn();
            if ($id > 0) {
                return $id;
            }
        }
        return null;
    }
}
