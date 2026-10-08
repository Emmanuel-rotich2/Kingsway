<?php
namespace App\API\Modules\schedules;

use Exception;
use PDO;
use function App\API\Includes\dayNameToNumber;
use App\API\Services\ReadReplicaService;

class SchedulesManager
{
    private $db;

    public function __construct($db = null)
    {
        if ($db === null) {
            $db = \App\Database\Database::getInstance()->getConnection();
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
        $sql = "SELECT * FROM vw_timetable_entries cs
                WHERE cs.teacher_id = :teacher_id
                  AND cs.status = 'scheduled'";
        $params = ['teacher_id' => $teacherId];
        if ($termId) {
            $sql .= " AND cs.academic_year_term_id = :term_id";
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
        $sql = "SELECT * FROM vw_timetable_entries cs
                WHERE cs.status = 'scheduled'
                  AND (cs.subject_id = :subject_id
                       OR cs.subject_id = (SELECT st.learning_area_id FROM " . ReadReplicaService::qualifiedRef("strands") . " st WHERE st.id = :subject_id2))";
        $params = ['subject_id' => $subjectId, 'subject_id2' => $subjectId];
        if ($termId) {
            $sql .= " AND cs.academic_year_term_id = :term_id";
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
        $sql = "SELECT
                    asch.*,
                    a.title as activity_title,
                    a.description as activity_description,
                    a.status as activity_status,
                    CONCAT(COALESCE(sp.first_name, ''), ' ', COALESCE(sp.last_name, '')) as coordinator_name
                FROM " . ReadReplicaService::qualifiedRef("activity_schedule") . " asch
                JOIN activities a ON asch.activity_id = a.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " st ON a.started_by = st.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " sp ON sp.id = st.person_id
                WHERE 1=1";
        $params = [];
        if (!empty($filters['term_id'])) {
            $sql .= " AND EXISTS (
                        SELECT 1 FROM academic_year_terms ayt
                        WHERE ayt.id = :term_id
                          AND asch.schedule_date BETWEEN ayt.opening_date AND ayt.closing_date
                     )";
            $params['term_id'] = $filters['term_id'];
        }
        if (!empty($filters['coordinator_id'])) {
            $sql .= " AND a.started_by = :coordinator_id";
            $params['coordinator_id'] = $filters['coordinator_id'];
        }
        $sql .= " ORDER BY asch.schedule_date, asch.start_time";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // DRIVER: Get transport schedules for a driver
    public function getDriverSchedule($driverId, $termId = null)
    {
        $sql = "SELECT ts.*, v.registration_number, r.name as route_name
                FROM transport_schedules ts
                JOIN transport_vehicles v ON ts.vehicle_id = v.id
                JOIN " . ReadReplicaService::qualifiedRef("transport_routes") . " r ON ts.route_id = r.id
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
        $sql = "SELECT dsr.*, dt.name as department_name, NULL as room_name
                FROM " . ReadReplicaService::qualifiedRef("staff_duty_roster") . "
                LEFT JOIN staff_duty_types dt ON dt.id = dsr.duty_type_id
                WHERE dsr.staff_id = :staff_id";
        $params = ['staff_id' => $staffId];
        if ($termId) {
            $sql .= " AND dsr.date BETWEEN (SELECT opening_date FROM academic_year_terms WHERE id = :term_id)
                                       AND (SELECT closing_date FROM academic_year_terms WHERE id = :term_id)";
            $params['term_id'] = $termId;
        }
        $sql .= " ORDER BY dsr.date, dsr.start_time";
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
        $sql = "SELECT COUNT(*) as total_classes FROM vw_timetable_entries WHERE status = 'scheduled'";
        $stmt = $this->db->query($sql);
        $analytics['total_classes'] = $stmt->fetchColumn();
        $sql = "SELECT COUNT(*) as total_activities FROM activity_schedule";
        $stmt = $this->db->query($sql);
        $analytics['total_activities'] = $stmt->fetchColumn();
        $sql = "SELECT COUNT(*) as total_conflicts FROM timetable_conflicts WHERE status IN ('reported', 'acknowledged')";
        if ($this->db->query("SHOW TABLES LIKE 'timetable_conflicts'")) {
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
                $sql = "SELECT * FROM vw_timetable_entries WHERE room_id = :id AND ((start_time < :end AND end_time > :start)) AND status = 'scheduled'";
                break;
            case 'staff':
                $sql = "SELECT * FROM vw_timetable_entries WHERE teacher_id = :id AND ((start_time < :end AND end_time > :start)) AND status = 'scheduled'";
                break;
            case 'class':
                $sql = "SELECT * FROM vw_timetable_entries WHERE class_id = :id AND ((start_time < :end AND end_time > :start)) AND status = 'scheduled'";
                break;
            case 'vehicle':
                $sql = "SELECT * FROM route_schedules WHERE vehicle_id = :id AND ((pickup_time < :end AND dropoff_time > :start)) AND status = 'active'";
                break;
            default:
                throw new \InvalidArgumentException('Unknown resource type');
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
            $dayNum = dayNameToNumber($day);
            if ($dayNum === null) {
                continue;
            }
            for ($hour = $startHour; $hour <= $endHour - $duration; $hour++) {
                $slotStart = sprintf('%02d:00:00', $hour);
                $slotEnd = sprintf('%02d:00:00', $hour + $duration);
                // Check for conflicts for this slot
                $sql = "SELECT * FROM vw_timetable_entries WHERE class_id = :id AND day_of_week = :day AND ((start_time < :end AND end_time > :start)) AND status = 'scheduled'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id' => $entityId,
                    'day' => $dayNum,
                    'start' => $slotStart,
                    'end' => $slotEnd
                ]);
                if ($stmt->rowCount() === 0) {
                    $slots[] = [
                        'day' => $day,
                        'day_of_week' => $dayNum,
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
            $roomId = $entry['room_id'] ?? null;
            if ($roomId) {
                $sql = "SELECT * FROM vw_timetable_entries WHERE room_id = :room_id AND day_of_week = :day AND ((start_time < :end AND end_time > :start)) AND status = 'scheduled'";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'room_id' => $roomId,
                    'day' => dayNameToNumber($entry['day_of_week'] ?? '') ?? 0,
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
            }
            // Teacher conflict
            $sql = "SELECT * FROM vw_timetable_entries WHERE teacher_id = :teacher_id AND day_of_week = :day AND ((start_time < :end AND end_time > :start)) AND status = 'scheduled'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'teacher_id' => $entry['teacher_id'],
                'day' => dayNameToNumber($entry['day_of_week'] ?? '') ?? 0,
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
        $sql = "SELECT * FROM vw_timetable_entries cs
                WHERE cs.status = 'scheduled'";
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
        // Example: Check for overlaps in timetable_entries for the same class
        $sql = "SELECT * FROM vw_timetable_entries
                WHERE class_id = (SELECT class_id FROM vw_timetable_entries WHERE id = :id)
                  AND id != :id
                  AND ((start_time < (SELECT end_time FROM vw_timetable_entries WHERE id = :id)
                        AND end_time > (SELECT start_time FROM vw_timetable_entries WHERE id = :id)))
                  AND status = 'scheduled'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $scheduleId]);
        $overlaps = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return count($overlaps) === 0;
    }
    /**
     * Count draft entries the given teacher scope covers. Centralized so the
     * controller performs no SQL (SQL placement rule).
     */
    public static function countDraftEntriesForScope(PDO $db, int $draftId, array $scope): int
    {
        if ($scope === []) {
            return 0;
        }
        $in = implode(',', array_fill(0, count($scope), '?'));
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM timetable_draft_entries WHERE draft_id = ? AND academic_year_class_stream_id IN (" . $in . ")"
        );
        $stmt->execute(array_merge([$draftId], $scope));
        return (int) $stmt->fetchColumn();
    }

}
