<?php
namespace App\API\Modules\Activities;

require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * ActivitiesManager - Core CRUD operations for activities
 * Handles creation, reading, updating, and deletion of activities
 */
class ActivitiesManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('activities');
    }

    /**
     * List all activities with advanced filtering
     * 
     * @param array $params Filter parameters
     * @return array List of activities with pagination
     */
    public function listActivities($params = [])
    {
        try {
            $page = isset($params['page']) ? (int) $params['page'] : 1;
            $limit = isset($params['limit']) ? (int) $params['limit'] : 20;
            $offset = ($page - 1) * $limit;

            $where = ['1=1'];
            $bindings = [];

            // Filter by category
            if (!empty($params['category_id'])) {
                $where[] = 'a.category_id = ?';
                $bindings[] = $params['category_id'];
            }

            // Filter by status
            if (!empty($params['status'])) {
                $where[] = 'a.status = ?';
                $bindings[] = $params['status'];
            }

            // Filter by date range
            if (!empty($params['start_date'])) {
                $where[] = 'a.start_date >= ?';
                $bindings[] = $params['start_date'];
            }

            if (!empty($params['end_date'])) {
                $where[] = 'a.end_date <= ?';
                $bindings[] = $params['end_date'];
            }

            // Search by title or description
            if (!empty($params['search'])) {
                $where[] = '(a.title LIKE ? OR a.description LIKE ?)';
                $searchTerm = '%' . $params['search'] . '%';
                $bindings[] = $searchTerm;
                $bindings[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $sql = "
                SELECT COUNT(DISTINCT a.id) 
                FROM activities a
                WHERE $whereClause
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $total = $stmt->fetchColumn();

            // Get paginated results
            $sql = "
                SELECT 
                    a.*,
                    ac.name as category_name,
                    ac.description as category_description,
                    u.username as created_by_name,
                    COUNT(DISTINCT ap.id) as participant_count,
                    COUNT(DISTINCT ar.id) as resource_count,
                    SUM(CASE WHEN ap.status = 'active' THEN 1 ELSE 0 END) as active_participants
                FROM activities a
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON a.created_by = u.id
                LEFT JOIN activity_participants ap ON a.id = ap.activity_id
                LEFT JOIN activity_resources ar ON a.id = ar.activity_id
                WHERE $whereClause
                GROUP BY a.id
                ORDER BY a.start_date DESC, a.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($bindings, [$limit, $offset]));
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $activities,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to list activities');
            throw $e;
        }
    }

    /**
     * Get detailed information about a single activity
     * 
     * @param int $id Activity ID
     * @return array Activity details with all related data
     */
    public function getActivity($id)
    {
        try {
            $sql = "
                SELECT 
                    a.*,
                    ac.name as category_name,
                    ac.description as category_description,
                    u.username as created_by_name,
                    u.email as created_by_email
                FROM activities a
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                LEFT JOIN users u ON a.created_by = u.id
                WHERE a.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                throw new Exception('Activity not found');
            }

            // Get participants summary
            $sql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'withdrawn' THEN 1 ELSE 0 END) as withdrawn
                FROM activity_participants
                WHERE activity_id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $activity['participants_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get resources summary
            $sql = "
                SELECT 
                    COUNT(*) as total,
                    SUM(quantity) as total_quantity
                FROM activity_resources
                WHERE activity_id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $activity['resources_summary'] = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $activity
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get activity $id");
            throw $e;
        }
    }

    /**
     * Create a new activity
     * 
     * @param array $data Activity data
     * @param int $userId User creating the activity
     * @return array Created activity ID and details
     */
    public function createActivity($data, $userId)
    {
        try {
            // Validate required fields
            $required = ['title', 'category_id', 'start_date', 'end_date'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }

            // Validate dates
            $startDate = strtotime($data['start_date']);
            $endDate = strtotime($data['end_date']);
            if ($endDate < $startDate) {
                throw new Exception('End date must be after start date');
            }

            // Validate category exists
            $stmt = $this->db->prepare("SELECT id FROM activity_categories WHERE id = ?");
            $stmt->execute([$data['category_id']]);
            if (!$stmt->fetch()) {
                throw new Exception('Invalid category ID');
            }

            $this->beginTransaction();

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
                $userId
            ]);

            $activityId = $this->db->lastInsertId();

            $this->commit();

            $this->logAction('create', $activityId, "Created activity: {$data['title']}");

            return [
                'success' => true,
                'data' => [
                    'id' => $activityId,
                    'title' => $data['title']
                ],
                'message' => 'Activity created successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, 'Failed to create activity');
            throw $e;
        }
    }

    /**
     * Update an existing activity
     * 
     * @param int $id Activity ID
     * @param array $data Updated data
     * @param int $userId User making the update
     * @return array Update result
     */
    public function updateActivity($id, $data, $userId)
    {
        try {
            // Check if activity exists
            $stmt = $this->db->prepare("SELECT id, title, status FROM activities WHERE id = ?");
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                throw new Exception('Activity not found');
            }

            // Don't allow updates to completed or cancelled activities without explicit override
            if (in_array($activity['status'], ['completed', 'cancelled']) && empty($data['force_update'])) {
                throw new Exception("Cannot update {$activity['status']} activity without force_update flag");
            }

            // Validate dates if provided
            if (isset($data['start_date']) && isset($data['end_date'])) {
                $startDate = strtotime($data['start_date']);
                $endDate = strtotime($data['end_date']);
                if ($endDate < $startDate) {
                    throw new Exception('End date must be after start date');
                }
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
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE activities SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();

            $this->logAction('update', $id, "Updated activity: {$activity['title']}");

            return [
                'success' => true,
                'message' => 'Activity updated successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to update activity $id");
            throw $e;
        }
    }

    /**
     * Delete an activity (soft delete by setting status to cancelled)
     * 
     * @param int $id Activity ID
     * @param int $userId User performing the deletion
     * @return array Deletion result
     */
    public function deleteActivity($id, $userId)
    {
        try {
            // Check if activity exists
            $stmt = $this->db->prepare("
                SELECT 
                    a.id, 
                    a.title, 
                    a.status,
                    COUNT(ap.id) as participant_count
                FROM activities a
                LEFT JOIN activity_participants ap ON a.id = ap.activity_id AND ap.status = 'active'
                WHERE a.id = ?
                GROUP BY a.id
            ");
            $stmt->execute([$id]);
            $activity = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$activity) {
                throw new Exception('Activity not found');
            }

            if ($activity['participant_count'] > 0) {
                throw new Exception('Cannot delete activity with active participants. Please withdraw all participants first.');
            }

            $this->beginTransaction();

            // Soft delete - update status to cancelled
            $stmt = $this->db->prepare("
                UPDATE activities 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            $this->commit();

            $this->logAction('delete', $id, "Deleted activity: {$activity['title']}");

            return [
                'success' => true,
                'message' => 'Activity deleted successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to delete activity $id");
            throw $e;
        }
    }

    /**
     * Get upcoming activities
     * 
     * @param int $limit Number of activities to return
     * @return array List of upcoming activities
     */
    public function getUpcomingActivities($limit = 10)
    {
        try {
            $sql = "
                SELECT 
                    a.*,
                    ac.name as category_name,
                    COUNT(DISTINCT ap.id) as participant_count,
                    DATEDIFF(a.start_date, CURDATE()) as days_until_start
                FROM activities a
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                LEFT JOIN activity_participants ap ON a.id = ap.activity_id AND ap.status = 'active'
                WHERE a.start_date >= CURDATE()
                AND a.status IN ('planned', 'ongoing')
                GROUP BY a.id
                ORDER BY a.start_date ASC
                LIMIT ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $activities
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to get upcoming activities');
            throw $e;
        }
    }

    /**
     * Get activity statistics
     * 
     * @param array $params Filter parameters
     * @return array Activity statistics
     */
    public function getActivityStatistics($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['category_id'])) {
                $where[] = 'category_id = ?';
                $bindings[] = $params['category_id'];
            }

            if (!empty($params['start_date'])) {
                $where[] = 'start_date >= ?';
                $bindings[] = $params['start_date'];
            }

            if (!empty($params['end_date'])) {
                $where[] = 'end_date <= ?';
                $bindings[] = $params['end_date'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    COUNT(*) as total_activities,
                    SUM(CASE WHEN status = 'planned' THEN 1 ELSE 0 END) as planned,
                    SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END) as ongoing,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM activities
                WHERE $whereClause
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $stats
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to get activity statistics');
            throw $e;
        }
    }
}
