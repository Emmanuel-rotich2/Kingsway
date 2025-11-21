<?php
namespace App\API\Modules\Activities;

require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * SchedulesManager - Manages activity scheduling and timing
 * Handles timetables, recurring schedules, and venue bookings
 */
class SchedulesManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('activity_schedule');
    }

    /**
     * List schedules with filtering
     * 
     * @param array $params Filter parameters
     * @return array List of schedules
     */
    public function listSchedules($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            // Filter by activity
            if (!empty($params['activity_id'])) {
                $where[] = 'as_tbl.activity_id = ?';
                $bindings[] = $params['activity_id'];
            }

            // Filter by day of week
            if (!empty($params['day_of_week'])) {
                $where[] = 'as_tbl.day_of_week = ?';
                $bindings[] = $params['day_of_week'];
            }

            // Filter by venue
            if (!empty($params['venue'])) {
                $where[] = 'as_tbl.venue LIKE ?';
                $bindings[] = '%' . $params['venue'] . '%';
            }

            // Filter by date
            if (!empty($params['date'])) {
                $dayOfWeek = date('l', strtotime($params['date']));
                $where[] = 'as_tbl.day_of_week = ?';
                $bindings[] = $dayOfWeek;
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    as_tbl.*,
                    a.title as activity_title,
                    a.status as activity_status,
                    ac.name as category_name
                FROM activity_schedule as_tbl
                JOIN activities a ON as_tbl.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE $whereClause
                ORDER BY 
                    FIELD(as_tbl.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    as_tbl.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $schedules
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to list schedules');
            throw $e;
        }
    }

    /**
     * Get schedule details
     * 
     * @param int $id Schedule ID
     * @return array Schedule details
     */
    public function getSchedule($id)
    {
        try {
            $sql = "
                SELECT 
                    as_tbl.*,
                    a.title as activity_title,
                    a.description as activity_description,
                    a.status as activity_status,
                    a.start_date,
                    a.end_date,
                    ac.name as category_name
                FROM activity_schedule as_tbl
                JOIN activities a ON as_tbl.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE as_tbl.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$schedule) {
                throw new Exception('Schedule not found');
            }

            return [
                'success' => true,
                'data' => $schedule
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get schedule $id");
            throw $e;
        }
    }

    /**
     * Create a schedule entry
     * 
     * @param array $data Schedule data
     * @param int $userId User creating the schedule
     * @return array Created schedule ID
     */
    public function createSchedule($data, $userId)
    {
        try {
            // Validate required fields
            $required = ['activity_id', 'day_of_week', 'start_time', 'end_time'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Validate activity exists
            $stmt = $this->db->prepare("SELECT id, title FROM activities WHERE id = ?");
            $stmt->execute([$data['activity_id']]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                throw new Exception('Activity not found');
            }

            // Validate day of week
            $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            if (!in_array($data['day_of_week'], $validDays)) {
                throw new Exception('Invalid day of week. Must be one of: ' . implode(', ', $validDays));
            }

            // Validate time format and logic
            $startTime = strtotime($data['start_time']);
            $endTime = strtotime($data['end_time']);
            if ($endTime <= $startTime) {
                throw new Exception('End time must be after start time');
            }

            // Check for venue conflicts
            if (!empty($data['venue'])) {
                $conflict = $this->checkVenueConflict(
                    $data['venue'],
                    $data['day_of_week'],
                    $data['start_time'],
                    $data['end_time']
                );

                if ($conflict) {
                    throw new Exception("Venue conflict detected with activity: {$conflict['activity_title']}");
                }
            }

            $this->beginTransaction();

            $sql = "
                INSERT INTO activity_schedule (
                    activity_id,
                    day_of_week,
                    start_time,
                    end_time,
                    venue,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['day_of_week'],
                $data['start_time'],
                $data['end_time'],
                $data['venue'] ?? null,
                $data['notes'] ?? null
            ]);

            $scheduleId = $this->db->lastInsertId();

            $this->commit();

            $this->logAction(
                'create',
                $scheduleId,
                "Created schedule for {$activity['title']}: {$data['day_of_week']} {$data['start_time']}-{$data['end_time']}"
            );

            return [
                'success' => true,
                'data' => ['id' => $scheduleId],
                'message' => 'Schedule created successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, 'Failed to create schedule');
            throw $e;
        }
    }

    /**
     * Update a schedule
     * 
     * @param int $id Schedule ID
     * @param array $data Updated data
     * @param int $userId User making the update
     * @return array Update result
     */
    public function updateSchedule($id, $data, $userId)
    {
        try {
            // Check if schedule exists
            $stmt = $this->db->prepare("SELECT id FROM activity_schedule WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Schedule not found');
            }

            // Validate time if both provided
            if (isset($data['start_time']) && isset($data['end_time'])) {
                $startTime = strtotime($data['start_time']);
                $endTime = strtotime($data['end_time']);
                if ($endTime <= $startTime) {
                    throw new Exception('End time must be after start time');
                }
            }

            // Check for venue conflicts if venue or time is being updated
            if (isset($data['venue']) || isset($data['day_of_week']) || isset($data['start_time']) || isset($data['end_time'])) {
                $current = $this->getSchedule($id)['data'];

                $venue = $data['venue'] ?? $current['venue'];
                $dayOfWeek = $data['day_of_week'] ?? $current['day_of_week'];
                $startTime = $data['start_time'] ?? $current['start_time'];
                $endTime = $data['end_time'] ?? $current['end_time'];

                if ($venue) {
                    $conflict = $this->checkVenueConflict($venue, $dayOfWeek, $startTime, $endTime, $id);
                    if ($conflict) {
                        throw new Exception("Venue conflict detected with activity: {$conflict['activity_title']}");
                    }
                }
            }

            $this->beginTransaction();

            $updates = [];
            $params = [];
            $allowedFields = ['activity_id', 'day_of_week', 'start_time', 'end_time', 'venue', 'notes'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE activity_schedule SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();

            $this->logAction('update', $id, "Updated schedule");

            return [
                'success' => true,
                'message' => 'Schedule updated successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to update schedule $id");
            throw $e;
        }
    }

    /**
     * Delete a schedule
     * 
     * @param int $id Schedule ID
     * @param int $userId User performing the deletion
     * @return array Deletion result
     */
    public function deleteSchedule($id, $userId)
    {
        try {
            $stmt = $this->db->prepare("SELECT id FROM activity_schedule WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception('Schedule not found');
            }

            $this->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM activity_schedule WHERE id = ?");
            $stmt->execute([$id]);

            $this->commit();

            $this->logAction('delete', $id, "Deleted schedule");

            return [
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to delete schedule $id");
            throw $e;
        }
    }

    /**
     * Get schedules for an activity
     * 
     * @param int $activityId Activity ID
     * @return array List of schedules
     */
    public function getActivitySchedules($activityId)
    {
        try {
            $sql = "
                SELECT * 
                FROM activity_schedule 
                WHERE activity_id = ?
                ORDER BY 
                    FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$activityId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $schedules
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get schedules for activity $activityId");
            throw $e;
        }
    }

    /**
     * Check for venue conflicts
     * 
     * @param string $venue Venue name
     * @param string $dayOfWeek Day of week
     * @param string $startTime Start time
     * @param string $endTime End time
     * @param int|null $excludeScheduleId Schedule ID to exclude from check
     * @return array|false Conflict details or false if no conflict
     */
    private function checkVenueConflict($venue, $dayOfWeek, $startTime, $endTime, $excludeScheduleId = null)
    {
        try {
            $sql = "
                SELECT 
                    as_tbl.*,
                    a.title as activity_title
                FROM activity_schedule as_tbl
                JOIN activities a ON as_tbl.activity_id = a.id
                WHERE as_tbl.venue = ?
                AND as_tbl.day_of_week = ?
                AND (
                    (as_tbl.start_time < ? AND as_tbl.end_time > ?)
                    OR (as_tbl.start_time < ? AND as_tbl.end_time > ?)
                    OR (as_tbl.start_time >= ? AND as_tbl.end_time <= ?)
                )
                AND a.status IN ('planned', 'ongoing')
            ";

            $params = [
                $venue,
                $dayOfWeek,
                $endTime,
                $startTime,
                $endTime,
                $endTime,
                $startTime,
                $endTime
            ];

            if ($excludeScheduleId) {
                $sql .= " AND as_tbl.id != ?";
                $params[] = $excludeScheduleId;
            }

            $sql .= " LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $conflict = $stmt->fetch(PDO::FETCH_ASSOC);

            return $conflict ?: false;

        } catch (Exception $e) {
            $this->logError($e, 'Failed to check venue conflict');
            return false;
        }
    }

    /**
     * Get weekly timetable
     * 
     * @param array $params Filter parameters
     * @return array Weekly timetable organized by day
     */
    public function getWeeklyTimetable($params = [])
    {
        try {
            $where = ['a.status IN (?, ?)'];
            $bindings = ['planned', 'ongoing'];

            if (!empty($params['category_id'])) {
                $where[] = 'a.category_id = ?';
                $bindings[] = $params['category_id'];
            }

            if (!empty($params['venue'])) {
                $where[] = 'as_tbl.venue = ?';
                $bindings[] = $params['venue'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    as_tbl.*,
                    a.title as activity_title,
                    ac.name as category_name
                FROM activity_schedule as_tbl
                JOIN activities a ON as_tbl.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE $whereClause
                ORDER BY 
                    FIELD(as_tbl.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                    as_tbl.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Organize by day
            $timetable = [
                'Monday' => [],
                'Tuesday' => [],
                'Wednesday' => [],
                'Thursday' => [],
                'Friday' => [],
                'Saturday' => [],
                'Sunday' => []
            ];

            foreach ($schedules as $schedule) {
                $timetable[$schedule['day_of_week']][] = $schedule;
            }

            return [
                'success' => true,
                'data' => $timetable
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to get weekly timetable');
            throw $e;
        }
    }

    /**
     * Get venue availability for a specific day and time
     * 
     * @param string $dayOfWeek Day of week
     * @param string $startTime Start time
     * @param string $endTime End time
     * @return array List of available and occupied venues
     */
    public function getVenueAvailability($dayOfWeek, $startTime, $endTime)
    {
        try {
            $sql = "
                SELECT 
                    as_tbl.venue,
                    a.title as activity_title,
                    as_tbl.start_time,
                    as_tbl.end_time
                FROM activity_schedule as_tbl
                JOIN activities a ON as_tbl.activity_id = a.id
                WHERE as_tbl.day_of_week = ?
                AND a.status IN ('planned', 'ongoing')
                AND (
                    (as_tbl.start_time < ? AND as_tbl.end_time > ?)
                    OR (as_tbl.start_time < ? AND as_tbl.end_time > ?)
                    OR (as_tbl.start_time >= ? AND as_tbl.end_time <= ?)
                )
                ORDER BY as_tbl.venue, as_tbl.start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $dayOfWeek,
                $endTime,
                $startTime,
                $endTime,
                $endTime,
                $startTime,
                $endTime
            ]);
            $occupiedVenues = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'day_of_week' => $dayOfWeek,
                    'time_range' => [
                        'start' => $startTime,
                        'end' => $endTime
                    ],
                    'occupied_venues' => $occupiedVenues
                ]
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to get venue availability');
            throw $e;
        }
    }

    /**
     * Bulk create schedules for an activity (full week)
     * 
     * @param int $activityId Activity ID
     * @param array $schedules Array of schedule data
     * @param int $userId User creating the schedules
     * @return array Bulk creation result
     */
    public function bulkCreateSchedules($activityId, $schedules, $userId)
    {
        try {
            $successful = [];
            $failed = [];

            $this->beginTransaction();

            foreach ($schedules as $scheduleData) {
                try {
                    $scheduleData['activity_id'] = $activityId;
                    $result = $this->createSchedule($scheduleData, $userId);
                    $successful[] = $result['data']['id'];
                } catch (Exception $e) {
                    $failed[] = [
                        'data' => $scheduleData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->commit();

            return [
                'success' => true,
                'data' => [
                    'successful' => count($successful),
                    'failed' => count($failed),
                    'successful_ids' => $successful,
                    'failed_details' => $failed
                ],
                'message' => sprintf(
                    'Created %d schedules successfully, %d failed',
                    count($successful),
                    count($failed)
                )
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, 'Failed to bulk create schedules');
            throw $e;
        }
    }
}
