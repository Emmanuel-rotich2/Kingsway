<?php
namespace App\API\Modules\schedules;

use Exception;

class SchedulesManager
{
    private $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = require_once __DIR__ . '/../../database/Database.php';
            $db = $db->getInstance();
        }

        if (!$db) {
            throw new Exception("Database connection required for SchedulesManager");
        }

        $this->db = $db;
    }

    // Central manager for all scheduling operations
    // Will coordinate with other managers (class, exam, activity, event, room, staff, transport)

    // TEACHING STAFF: Get timetable for a teacher (all classes, rooms, periods)
    public function getTeacherSchedule($teacherId, $termId = null)
    {
        $sql = "SELECT cs.*, c.name as class_name, s.name as subject_name, r.name as room_name
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.teacher_id = :teacher_id";
        $params = ['teacher_id' => $teacherId];
        if ($termId) {
            $sql .= " AND cs.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $sql .= " ORDER BY cs.day_of_week, cs.start_time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // SUBJECT SPECIALIST: Get teaching load for a subject (all teachers, classes, periods)
    public function getSubjectTeachingLoad($subjectId, $termId = null)
    {
        $sql = "SELECT cs.*, t.name as teacher_name, c.name as class_name, r.name as room_name
                FROM class_schedules cs
                JOIN staff t ON cs.teacher_id = t.id
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE cs.subject_id = :subject_id";
        $params = ['subject_id' => $subjectId];
        if ($termId) {
            $sql .= " AND cs.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $sql .= " ORDER BY cs.day_of_week, cs.start_time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ACTIVITIES COORDINATOR: Get all activity schedules
    public function getAllActivitySchedules($filters = [])
    {
        $sql = "SELECT a.*, r.name as room_name, t.name as teacher_name
                FROM activity_schedules a
                LEFT JOIN rooms r ON a.room_id = r.id
                LEFT JOIN staff t ON a.coordinator_id = t.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['term_id'])) {
            $sql .= " AND a.term_id = :term_id";
            $params['term_id'] = $filters['term_id'];
        }
        if (!empty($filters['coordinator_id'])) {
            $sql .= " AND a.coordinator_id = :coordinator_id";
            $params['coordinator_id'] = $filters['coordinator_id'];
        }
        $sql .= " ORDER BY a.date, a.start_time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // DRIVER: Get transport schedules for a driver
    public function getDriverSchedule($driverId, $termId = null)
    {
        $sql = "SELECT ts.*, v.plate_number, r.route_name
                FROM transport_schedules ts
                JOIN vehicles v ON ts.vehicle_id = v.id
                JOIN routes r ON ts.route_id = r.id
                WHERE ts.driver_id = :driver_id";
        $params = ['driver_id' => $driverId];
        if ($termId) {
            $sql .= " AND ts.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $sql .= " ORDER BY ts.date, ts.pickup_time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // NON-TEACHING STAFF: Get duty schedules (cleaning, maintenance, kitchen, etc.)
    public function getStaffDutySchedule($staffId, $termId = null)
    {
        $sql = "SELECT ds.*, d.name as department_name, r.name as room_name
                FROM duty_schedules ds
                LEFT JOIN departments d ON ds.department_id = d.id
                LEFT JOIN rooms r ON ds.room_id = r.id
                WHERE ds.staff_id = :staff_id";
        $params = ['staff_id' => $staffId];
        if ($termId) {
            $sql .= " AND ds.term_id = :term_id";
            $params['term_id'] = $termId;
        }
        $sql .= " ORDER BY ds.date, ds.start_time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ADMIN: Get master schedule (all classes, activities, events, transport)
    public function getMasterSchedule($filters = [])
    {
        // Combine class, activity, and transport schedules for a full view
        $result = [];
        $result['classes'] = $this->generateMasterSchedule('class', $filters);
        $result['activities'] = $this->getAllActivitySchedules($filters);
        // Optionally add transport and duty schedules if needed
        if (!empty($filters['driver_id'])) {
            $result['transport'] = $this->getDriverSchedule($filters['driver_id'], $filters['term_id'] ?? null);
        }
        if (!empty($filters['staff_id'])) {
            $result['duties'] = $this->getStaffDutySchedule($filters['staff_id'], $filters['term_id'] ?? null);
        }
        return $result;
    }

    // ANALYTICS: Get schedule analytics (utilization, conflicts, compliance)
    public function getScheduleAnalytics($filters = [])
    {
        // Example: count total classes, activities, conflicts, etc.
        $analytics = [];
        $sql = "SELECT COUNT(*) as total_classes FROM class_schedules WHERE status IN ('planned','approved','published')";
        $stmt = $this->db->query($sql);
        $analytics['total_classes'] = $stmt->fetchColumn();
        $sql = "SELECT COUNT(*) as total_activities FROM activity_schedules WHERE status = 'active'";
        $stmt = $this->db->query($sql);
        $analytics['total_activities'] = $stmt->fetchColumn();
        $sql = "SELECT COUNT(*) as total_conflicts FROM schedule_conflicts WHERE resolved = 0";
        if ($this->db->query("SHOW TABLES LIKE 'schedule_conflicts'")) {
            $stmt = $this->db->query($sql);
            $analytics['total_conflicts'] = $stmt->fetchColumn();
        } else {
            $analytics['total_conflicts'] = null;
        }
        // Add more analytics as needed
        return $analytics;
    }

    // Check if a resource (room, staff, class, vehicle, etc.) is available in a given time window
    public function checkResourceAvailability($resourceType, $resourceId, $start, $end)
    {
        $conflicts = [];
        switch ($resourceType) {
            case 'room':
                $sql = "SELECT * FROM class_schedules WHERE room_id = :id AND ((start_time < :end AND end_time > :start)) AND status IN ('planned','approved','published')";
                break;
            case 'staff':
                $sql = "SELECT * FROM class_schedules WHERE teacher_id = :id AND ((start_time < :end AND end_time > :start)) AND status IN ('planned','approved','published')";
                break;
            case 'class':
                $sql = "SELECT * FROM class_schedules WHERE class_id = :id AND ((start_time < :end AND end_time > :start)) AND status IN ('planned','approved','published')";
                break;
            case 'vehicle':
                $sql = "SELECT * FROM route_schedules WHERE vehicle_id = :id AND ((pickup_time < :end AND dropoff_time > :start)) AND status = 'active'";
                break;
            default:
                throw new Exception('Unknown resource type');
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $resourceId, 'start' => $start, 'end' => $end]);
        $conflicts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return count($conflicts) === 0;
    }

    // Suggest optimal time slots for an entity (class, exam, activity, event)
    public function findOptimalSchedule($entityType, $entityId, $constraints = [])
    {
        // Example: Suggest free slots for a class based on constraints and existing schedules
        // This is a simplified version; real implementation would be more advanced
        $slots = [];
        $days = $constraints['days'] ?? ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $startHour = $constraints['start_hour'] ?? 8;
        $endHour = $constraints['end_hour'] ?? 16;
        $duration = $constraints['duration'] ?? 1; // in hours
        foreach ($days as $day) {
            for ($hour = $startHour; $hour <= $endHour - $duration; $hour++) {
                $slotStart = sprintf('%02d:00:00', $hour);
                $slotEnd = sprintf('%02d:00:00', $hour + $duration);
                // Check for conflicts for this slot
                $sql = "SELECT * FROM class_schedules WHERE class_id = :id AND day_of_week = :day AND ((start_time < :end AND end_time > :start)) AND status IN ('planned','approved','published')";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id' => $entityId,
                    'day' => $day,
                    'start' => $slotStart,
                    'end' => $slotEnd
                ]);
                if ($stmt->rowCount() === 0) {
                    $slots[] = [
                        'day' => $day,
                        'start_time' => $slotStart,
                        'end_time' => $slotEnd
                    ];
                }
            }
        }
        return $slots;
    }

    // Detect conflicts for a proposed schedule (double-booked rooms, staff, students, etc.)
    public function detectScheduleConflicts($entityType, $entityId, $proposedSchedule)
    {
        $conflicts = [];
        // Example: Check for room and teacher conflicts for a class schedule
        foreach ($proposedSchedule as $entry) {
            // Room conflict
            $sql = "SELECT * FROM class_schedules WHERE room_id = :room_id AND day_of_week = :day AND ((start_time < :end AND end_time > :start)) AND status IN ('planned','approved','published')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'room_id' => $entry['room_id'],
                'day' => $entry['day_of_week'],
                'start' => $entry['start_time'],
                'end' => $entry['end_time']
            ]);
            if ($stmt->rowCount() > 0) {
                $conflicts[] = [
                    'type' => 'room',
                    'entry' => $entry,
                    'conflict' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
                ];
            }
            // Teacher conflict
            $sql = "SELECT * FROM class_schedules WHERE teacher_id = :teacher_id AND day_of_week = :day AND ((start_time < :end AND end_time > :start)) AND status IN ('planned','approved','published')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'teacher_id' => $entry['teacher_id'],
                'day' => $entry['day_of_week'],
                'start' => $entry['start_time'],
                'end' => $entry['end_time']
            ]);
            if ($stmt->rowCount() > 0) {
                $conflicts[] = [
                    'type' => 'teacher',
                    'entry' => $entry,
                    'conflict' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
                ];
            }
        }
        return $conflicts;
    }

    // Generate a master schedule for the school, class, staff, or room
    public function generateMasterSchedule($scope, $filters = [])
    {
        // Example: Return all class schedules, optionally filtered by class, staff, or room
        $sql = "SELECT cs.*, c.name as class_name, s.name as subject_name, t.name as teacher_name, r.name as room_name
                FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.id
                JOIN subjects s ON cs.subject_id = s.id
                LEFT JOIN staff t ON cs.teacher_id = t.id
                LEFT JOIN rooms r ON cs.room_id = r.id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['class_id'])) {
            $sql .= " AND cs.class_id = :class_id";
            $params['class_id'] = $filters['class_id'];
        }
        if (!empty($filters['teacher_id'])) {
            $sql .= " AND cs.teacher_id = :teacher_id";
            $params['teacher_id'] = $filters['teacher_id'];
        }
        if (!empty($filters['room_id'])) {
            $sql .= " AND cs.room_id = :room_id";
            $params['room_id'] = $filters['room_id'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Validate that a schedule complies with school policies (e.g., no overlaps, max hours, etc.)
    public function validateScheduleCompliance($scheduleId)
    {
        // Example: Check for overlaps in class_schedules for the same class
        $sql = "SELECT * FROM class_schedules WHERE class_id = (SELECT class_id FROM class_schedules WHERE id = :id) AND id != :id AND ((start_time < (SELECT end_time FROM class_schedules WHERE id = :id) AND end_time > (SELECT start_time FROM class_schedules WHERE id = :id))) AND status IN ('planned','approved','published')";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $scheduleId]);
        $overlaps = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return count($overlaps) === 0;
    }
}
