<?php
namespace App\API\Modules\activities;

require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;

use PDO;
use Exception;

class ActivitiesAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('activities');
    }

    // List activities with pagination and filtering
    public function list($params = [])
    {
        try {
            [$page, $limit, $offset] = $this->getPaginationParams();
            [$search, $sort, $order] = $this->getSearchParams();

            $where = '';
            $bindings = [];
            if (!empty($search)) {
                $where = "WHERE a.title LIKE ? OR a.description LIKE ? OR c.name LIKE ?";
                $searchTerm = "%$search%";
                $bindings = [$searchTerm, $searchTerm, $searchTerm];
            }

            // Get total count
            $sql = "
                SELECT COUNT(*) 
                FROM activities a
                LEFT JOIN activity_categories c ON a.category_id = c.id
                $where
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    a.*,
                    c.name as category_name,
                    COUNT(DISTINCT p.student_id) as participant_count,
                    COUNT(DISTINCT r.id) as resource_count
                FROM activities a
                LEFT JOIN activity_categories c ON a.category_id = c.id
                LEFT JOIN activity_participants p ON a.id = p.activity_id
                LEFT JOIN activity_resources r ON a.id = r.activity_id
                $where
                GROUP BY a.id
                ORDER BY $sort $order
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'activities' => $activities,
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

    // Get single activity
    public function get($id)
    {
        try {
            // Get activity details
            $sql = "
                SELECT 
                    a.*,
                    c.name as category_name,
                    u.username as created_by_name
                FROM activities a
                LEFT JOIN activity_categories c ON a.category_id = c.id
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                return $this->response(['status' => 'error', 'message' => 'Activity not found'], 404);
            }

            // Get participants
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    p.role,
                    p.status
                FROM activity_participants p
                JOIN students s ON p.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                WHERE p.activity_id = ?
                ORDER BY c.name, cs.stream_name, s.admission_no
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get resources
            $sql = "
                SELECT * FROM activity_resources 
                WHERE activity_id = ?
                ORDER BY type, name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get schedule
            $sql = "
                SELECT * FROM activity_schedule 
                WHERE activity_id = ?
                ORDER BY start_time
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $activity['participants'] = $participants;
            $activity['resources'] = $resources;
            $activity['schedule'] = $schedule;

            return $this->response([
                'status' => 'success',
                'data' => $activity
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Create new activity
    public function create($data)
    {
        try {
            // Validate required fields
            $required = ['title', 'category_id', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->beginTransaction();

            // Insert activity record
            $sql = "
                INSERT INTO activities (
                    title,
                    description,
                    category_id,
                    start_date,
                    end_date,
                    location,
                    max_participants,
                    status,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['title'],
                $data['description'] ?? null,
                $data['category_id'],
                $data['start_date'],
                $data['end_date'],
                $data['location'] ?? null,
                $data['max_participants'] ?? null,
                $data['status'] ?? 'planned',
                $_SESSION['user_id'] ?? null
            ]);

            $activityId = $this->db->lastInsertId();

            // Add schedule if provided
            if (isset($data['schedule']) && is_array($data['schedule'])) {
                $sql = "
                    INSERT INTO activity_schedule (
                        activity_id,
                        day_of_week,
                        start_time,
                        end_time,
                        venue
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['schedule'] as $schedule) {
                    $stmt->execute([
                        $activityId,
                        $schedule['day_of_week'],
                        $schedule['start_time'],
                        $schedule['end_time'],
                        $schedule['venue'] ?? null
                    ]);
                }
            }

            // Add resources if provided
            if (isset($data['resources']) && is_array($data['resources'])) {
                $sql = "
                    INSERT INTO activity_resources (
                        activity_id,
                        name,
                        type,
                        quantity,
                        notes
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['resources'] as $resource) {
                    $stmt->execute([
                        $activityId,
                        $resource['name'],
                        $resource['type'],
                        $resource['quantity'] ?? 1,
                        $resource['notes'] ?? null
                    ]);
                }
            }

            $this->commit();

            // Log the action
            $this->logAction('create', $activityId, "Created new activity: {$data['title']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Activity created successfully',
                'data' => ['id' => $activityId]
            ], 201);

        } catch (Exception $e) {
            $this->rollBack();
            return $this->handleException($e);
        }
    }

    // Update activity
    public function update($id, $data)
    {
        try {
            // Check if activity exists
            $stmt = $this->db->prepare("SELECT id, title FROM activities WHERE id = ?");
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                return $this->response(['status' => 'error', 'message' => 'Activity not found'], 404);
            }

            $this->beginTransaction();

            // Build update query
            $updates = [];
            $params = [];
            $allowedFields = [
                'title',
                'description',
                'category_id',
                'start_date',
                'end_date',
                'location',
                'max_participants',
                'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE activities SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            // Update schedule if provided
            if (isset($data['schedule']) && is_array($data['schedule'])) {
                // Delete existing schedule
                $stmt = $this->db->prepare("DELETE FROM activity_schedule WHERE activity_id = ?");
                $stmt->execute([$id]);

                // Insert new schedule
                $sql = "
                    INSERT INTO activity_schedule (
                        activity_id,
                        day_of_week,
                        start_time,
                        end_time,
                        venue
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['schedule'] as $schedule) {
                    $stmt->execute([
                        $id,
                        $schedule['day_of_week'],
                        $schedule['start_time'],
                        $schedule['end_time'],
                        $schedule['venue'] ?? null
                    ]);
                }
            }

            // Update resources if provided
            if (isset($data['resources']) && is_array($data['resources'])) {
                // Delete existing resources
                $stmt = $this->db->prepare("DELETE FROM activity_resources WHERE activity_id = ?");
                $stmt->execute([$id]);

                // Insert new resources
                $sql = "
                    INSERT INTO activity_resources (
                        activity_id,
                        name,
                        type,
                        quantity,
                        notes
                    ) VALUES (?, ?, ?, ?, ?)
                ";

                $stmt = $this->db->prepare($sql);
                foreach ($data['resources'] as $resource) {
                    $stmt->execute([
                        $id,
                        $resource['name'],
                        $resource['type'],
                        $resource['quantity'] ?? 1,
                        $resource['notes'] ?? null
                    ]);
                }
            }

            $this->commit();

            // Log the action
            $this->logAction('update', $id, "Updated activity: {$activity['title']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Activity updated successfully'
            ]);

        } catch (Exception $e) {
            $this->rollBack();
            return $this->handleException($e);
        }
    }

    // Delete activity
    public function delete($id)
    {
        try {
            // Check if activity exists and has no active participants
            $stmt = $this->db->prepare("
                SELECT a.id, a.title, COUNT(p.id) as participant_count
                FROM activities a
                LEFT JOIN activity_participants p ON a.id = p.activity_id
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                return $this->response(['status' => 'error', 'message' => 'Activity not found'], 404);
            }

            if ($activity['participant_count'] > 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Cannot delete activity with registered participants'
                ], 400);
            }

            $this->beginTransaction();

            // Delete schedule
            $stmt = $this->db->prepare("DELETE FROM activity_schedule WHERE activity_id = ?");
            $stmt->execute([$id]);

            // Delete resources
            $stmt = $this->db->prepare("DELETE FROM activity_resources WHERE activity_id = ?");
            $stmt->execute([$id]);

            // Soft delete the activity
            $stmt = $this->db->prepare("UPDATE activities SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);

            $this->commit();

            // Log the action
            $this->logAction('delete', $id, "Deleted activity: {$activity['title']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Activity deleted successfully'
            ]);

        } catch (Exception $e) {
            $this->rollBack();
            return $this->handleException($e);
        }
    }

    // Register participant
    public function registerParticipant($data)
    {
        try {
            // Validate required fields
            $required = ['activity_id', 'student_id'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Check if activity exists and is active
            $stmt = $this->db->prepare("
                SELECT id, title, max_participants, status 
                FROM activities 
                WHERE id = ?
            ");
            $stmt->execute([$data['activity_id']]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                return $this->response(['status' => 'error', 'message' => 'Activity not found'], 404);
            }

            if ($activity['status'] !== 'active') {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Activity is not active for registration'
                ], 400);
            }

            // Check participant limit
            if ($activity['max_participants']) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM activity_participants 
                    WHERE activity_id = ? AND status = 'active'
                ");
                $stmt->execute([$data['activity_id']]);
                $currentCount = $stmt->fetchColumn();

                if ($currentCount >= $activity['max_participants']) {
                    return $this->response([
                        'status' => 'error',
                        'message' => 'Activity has reached maximum participants'
                    ], 400);
                }
            }

            // Check if student is already registered
            $stmt = $this->db->prepare("
                SELECT id FROM activity_participants 
                WHERE activity_id = ? AND student_id = ? AND status = 'active'
            ");
            $stmt->execute([$data['activity_id'], $data['student_id']]);
            if ($stmt->fetch()) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Student is already registered for this activity'
                ], 400);
            }

            // Register participant
            $sql = "
                INSERT INTO activity_participants (
                    activity_id,
                    student_id,
                    role,
                    status,
                    registered_by,
                    registered_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['student_id'],
                $data['role'] ?? 'participant',
                $data['status'] ?? 'active',
                $_SESSION['user_id'] ?? null
            ]);

            $participantId = $this->db->lastInsertId();

            // Log the action
            $this->logAction('register', $participantId, "Registered participant for activity: {$activity['title']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Participant registered successfully',
                'data' => ['id' => $participantId]
            ], 201);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Update participant status
    public function updateParticipantStatus($id, $data)
    {
        try {
            // Validate required fields
            if (!isset($data['status'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Status is required'
                ], 400);
            }

            // Check if participant exists
            $stmt = $this->db->prepare("
                SELECT 
                    p.id,
                    a.title as activity_title,
                    s.admission_no
                FROM activity_participants p
                JOIN activities a ON p.activity_id = a.id
                JOIN students s ON p.student_id = s.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$participant) {
                return $this->response(['status' => 'error', 'message' => 'Participant not found'], 404);
            }

            // Update status
            $stmt = $this->db->prepare("UPDATE activity_participants SET status = ? WHERE id = ?");
            $stmt->execute([$data['status'], $id]);

            // Log the action
            $this->logAction('update', $id, "Updated participant status for activity: {$participant['activity_title']}");

            return $this->response([
                'status' => 'success',
                'message' => 'Participant status updated successfully'
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get upcoming activities
    public function getUpcoming()
    {
        try {
            $sql = "
                SELECT 
                    a.*,
                    c.name as category_name,
                    COUNT(DISTINCT p.student_id) as participant_count
                FROM activities a
                LEFT JOIN activity_categories c ON a.category_id = c.id
                LEFT JOIN activity_participants p ON a.id = p.activity_id
                WHERE a.start_date >= CURDATE()
                AND a.status = 'active'
                GROUP BY a.id
                ORDER BY a.start_date ASC
                LIMIT 10
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $activities
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Get student activities
    public function getStudentActivities($studentId)
    {
        try {
            $sql = "
                SELECT 
                    a.*,
                    c.name as category_name,
                    p.role,
                    p.status as participation_status
                FROM activities a
                JOIN activity_participants p ON a.id = p.activity_id
                LEFT JOIN activity_categories c ON a.category_id = c.id
                WHERE p.student_id = ?
                AND a.status = 'active'
                ORDER BY a.start_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $activities
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
