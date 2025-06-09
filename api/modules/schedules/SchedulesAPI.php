<?php
namespace App\API\Modules\schedules;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use DateTime;

class SchedulesAPI extends BaseAPI {
    public function __construct() {
        parent::__construct('schedules');
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

            return $this->response([
                'status' => 'success',
                'data' => [
                    'schedules' => $schedules,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $total,
                        'total_pages' => ceil($total / $limit)
                    ]
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
                return $this->response(['status' => 'error', 'message' => 'Schedule not found'], 404);
            }

            return $this->response(['status' => 'success', 'data' => $schedule]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function create($data) {
        try {
            $required = ['title', 'start_date', 'end_date', 'type'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Schedule created successfully',
                'data' => ['id' => $id]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function update($id, $data) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return $this->response(['status' => 'error', 'message' => 'Schedule not found'], 404);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Schedule updated successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return $this->response(['status' => 'error', 'message' => 'Schedule not found'], 404);
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Schedule deleted successfully'
            ]);
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

            return $this->response(['status' => 'success', 'data' => $timetable]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createTimetableEntry($data) {
        try {
            $required = ['class_id', 'learning_area_id', 'teacher_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Timetable entry created successfully',
                'data' => ['id' => $entryId]
            ], 201);
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

            return $this->response(['status' => 'success', 'data' => $schedule]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createExamSchedule($data) {
        try {
            $required = ['exam_id', 'learning_area_id', 'exam_date', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Exam schedule created successfully',
                'data' => ['id' => $scheduleId]
            ], 201);
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

            return $this->response(['status' => 'success', 'data' => $events]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createEvent($data) {
        try {
            $required = ['name', 'start_date', 'end_date', 'organizer_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Event created successfully',
                'data' => ['id' => $eventId]
            ], 201);
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

            return $this->response(['status' => 'success', 'data' => $schedules]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createActivitySchedule($data) {
        try {
            $required = ['activity_id', 'day_of_week', 'start_time', 'end_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Activity schedule created successfully',
                'data' => ['id' => $scheduleId]
            ], 201);
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

            return $this->response(['status' => 'success', 'data' => $rooms]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createRoom($data) {
        try {
            $required = ['name', 'capacity'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Room created successfully',
                'data' => ['id' => $roomId]
            ], 201);
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

            return $this->response(['status' => 'success', 'data' => $reports]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createScheduledReport($data) {
        try {
            $required = ['name', 'report_type', 'frequency', 'recipient_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Scheduled report created successfully',
                'data' => ['id' => $reportId]
            ], 201);
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

            return $this->response(['status' => 'success', 'data' => $schedules]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createRouteSchedule($data) {
        try {
            $required = ['route_id', 'day_of_week', 'pickup_time', 'dropoff_time'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
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

            return $this->response([
                'status' => 'success',
                'message' => 'Route schedule created successfully',
                'data' => ['id' => $scheduleId]
            ], 201);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}