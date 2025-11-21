<?php
namespace App\API\Modules\Activities;

require_once __DIR__ . '/../../includes/BaseAPI.php';
use App\API\Includes\BaseAPI;
use PDO;
use Exception;

/**
 * ResourcesManager - Manages equipment, venues, and materials for activities
 * Handles resource allocation, tracking, and availability
 */
class ResourcesManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('activity_resources');
    }

    /**
     * List resources with filtering
     * 
     * @param array $params Filter parameters
     * @return array List of resources
     */
    public function listResources($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            // Filter by activity
            if (!empty($params['activity_id'])) {
                $where[] = 'ar.activity_id = ?';
                $bindings[] = $params['activity_id'];
            }

            // Filter by type
            if (!empty($params['type'])) {
                $where[] = 'ar.type = ?';
                $bindings[] = $params['type'];
            }

            // Filter by availability
            if (isset($params['available_only']) && $params['available_only']) {
                $where[] = 'ar.status = ?';
                $bindings[] = 'available';
            }

            // Search by name
            if (!empty($params['search'])) {
                $where[] = '(ar.name LIKE ? OR ar.notes LIKE ?)';
                $searchTerm = '%' . $params['search'] . '%';
                $bindings[] = $searchTerm;
                $bindings[] = $searchTerm;
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    ar.*,
                    a.title as activity_title,
                    a.start_date,
                    a.end_date,
                    ac.name as category_name
                FROM activity_resources ar
                LEFT JOIN activities a ON ar.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE $whereClause
                ORDER BY ar.type, ar.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $resources
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to list resources');
            throw $e;
        }
    }

    /**
     * Get resource details
     * 
     * @param int $id Resource ID
     * @return array Resource details
     */
    public function getResource($id)
    {
        try {
            $sql = "
                SELECT 
                    ar.*,
                    a.title as activity_title,
                    a.start_date,
                    a.end_date,
                    a.status as activity_status,
                    ac.name as category_name
                FROM activity_resources ar
                LEFT JOIN activities a ON ar.activity_id = a.id
                LEFT JOIN activity_categories ac ON a.category_id = ac.id
                WHERE ar.id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) {
                throw new Exception('Resource not found');
            }

            return [
                'success' => true,
                'data' => $resource
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get resource $id");
            throw $e;
        }
    }

    /**
     * Add a resource to an activity
     * 
     * @param array $data Resource data
     * @param int $userId User adding the resource
     * @return array Created resource ID
     */
    public function addResource($data, $userId)
    {
        try {
            // Validate required fields
            $required = ['activity_id', 'name', 'type'];
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

            // Validate resource type
            $validTypes = ['equipment', 'venue', 'material', 'transport', 'other'];
            if (!in_array($data['type'], $validTypes)) {
                throw new Exception('Invalid resource type. Must be one of: ' . implode(', ', $validTypes));
            }

            $this->beginTransaction();

            $sql = "
                INSERT INTO activity_resources (
                    activity_id,
                    name,
                    type,
                    quantity,
                    status,
                    cost,
                    notes,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['activity_id'],
                $data['name'],
                $data['type'],
                $data['quantity'] ?? 1,
                $data['status'] ?? 'available',
                $data['cost'] ?? null,
                $data['notes'] ?? null
            ]);

            $resourceId = $this->db->lastInsertId();

            $this->commit();

            $this->logAction(
                'create',
                $resourceId,
                "Added resource '{$data['name']}' to activity: {$activity['title']}"
            );

            return [
                'success' => true,
                'data' => ['id' => $resourceId],
                'message' => 'Resource added successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, 'Failed to add resource');
            throw $e;
        }
    }

    /**
     * Update a resource
     * 
     * @param int $id Resource ID
     * @param array $data Updated data
     * @param int $userId User making the update
     * @return array Update result
     */
    public function updateResource($id, $data, $userId)
    {
        try {
            // Check if resource exists
            $stmt = $this->db->prepare("SELECT id, name FROM activity_resources WHERE id = ?");
            $stmt->execute([$id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) {
                throw new Exception('Resource not found');
            }

            $this->beginTransaction();

            $updates = [];
            $params = [];
            $allowedFields = ['name', 'type', 'quantity', 'status', 'cost', 'notes'];

            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (!empty($updates)) {
                $params[] = $id;
                $sql = "UPDATE activity_resources SET " . implode(', ', $updates) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }

            $this->commit();

            $this->logAction('update', $id, "Updated resource: {$resource['name']}");

            return [
                'success' => true,
                'message' => 'Resource updated successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to update resource $id");
            throw $e;
        }
    }

    /**
     * Delete a resource
     * 
     * @param int $id Resource ID
     * @param int $userId User performing the deletion
     * @return array Deletion result
     */
    public function deleteResource($id, $userId)
    {
        try {
            $stmt = $this->db->prepare("SELECT id, name FROM activity_resources WHERE id = ?");
            $stmt->execute([$id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) {
                throw new Exception('Resource not found');
            }

            $this->beginTransaction();

            $stmt = $this->db->prepare("DELETE FROM activity_resources WHERE id = ?");
            $stmt->execute([$id]);

            $this->commit();

            $this->logAction('delete', $id, "Deleted resource: {$resource['name']}");

            return [
                'success' => true,
                'message' => 'Resource deleted successfully'
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to delete resource $id");
            throw $e;
        }
    }

    /**
     * Get resources for an activity
     * 
     * @param int $activityId Activity ID
     * @return array List of resources for the activity
     */
    public function getActivityResources($activityId)
    {
        try {
            $sql = "
                SELECT * 
                FROM activity_resources 
                WHERE activity_id = ?
                ORDER BY type, name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$activityId]);
            $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $totals = [
                'total_items' => count($resources),
                'total_quantity' => array_sum(array_column($resources, 'quantity')),
                'total_cost' => array_sum(array_column($resources, 'cost')),
                'by_type' => []
            ];

            // Group by type
            foreach ($resources as $resource) {
                $type = $resource['type'];
                if (!isset($totals['by_type'][$type])) {
                    $totals['by_type'][$type] = [
                        'count' => 0,
                        'quantity' => 0,
                        'cost' => 0
                    ];
                }
                $totals['by_type'][$type]['count']++;
                $totals['by_type'][$type]['quantity'] += $resource['quantity'];
                $totals['by_type'][$type]['cost'] += $resource['cost'] ?? 0;
            }

            return [
                'success' => true,
                'data' => [
                    'resources' => $resources,
                    'summary' => $totals
                ]
            ];

        } catch (Exception $e) {
            $this->logError($e, "Failed to get resources for activity $activityId");
            throw $e;
        }
    }

    /**
     * Check resource availability for a date range
     * 
     * @param string $resourceType Resource type
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Available resources
     */
    public function checkResourceAvailability($resourceType, $startDate, $endDate)
    {
        try {
            $sql = "
                SELECT 
                    ar.*,
                    a.title as activity_title,
                    a.start_date,
                    a.end_date
                FROM activity_resources ar
                JOIN activities a ON ar.activity_id = a.id
                WHERE ar.type = ?
                AND ar.status = 'available'
                AND (
                    (a.start_date <= ? AND a.end_date >= ?)
                    OR (a.start_date <= ? AND a.end_date >= ?)
                    OR (a.start_date >= ? AND a.end_date <= ?)
                )
                ORDER BY ar.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $resourceType,
                $endDate,
                $endDate,
                $startDate,
                $startDate,
                $startDate,
                $endDate
            ]);
            $conflictingResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get all resources of this type
            $sql = "
                SELECT DISTINCT name, type, SUM(quantity) as total_available
                FROM activity_resources
                WHERE type = ? AND status = 'available'
                GROUP BY name, type
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$resourceType]);
            $allResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => [
                    'all_resources' => $allResources,
                    'conflicting_bookings' => $conflictingResources,
                    'date_range' => [
                        'start' => $startDate,
                        'end' => $endDate
                    ]
                ]
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to check resource availability');
            throw $e;
        }
    }

    /**
     * Get resource utilization statistics
     * 
     * @param array $params Filter parameters
     * @return array Resource statistics
     */
    public function getResourceStatistics($params = [])
    {
        try {
            $where = ['1=1'];
            $bindings = [];

            if (!empty($params['type'])) {
                $where[] = 'ar.type = ?';
                $bindings[] = $params['type'];
            }

            if (!empty($params['start_date'])) {
                $where[] = 'a.start_date >= ?';
                $bindings[] = $params['start_date'];
            }

            if (!empty($params['end_date'])) {
                $where[] = 'a.end_date <= ?';
                $bindings[] = $params['end_date'];
            }

            $whereClause = implode(' AND ', $where);

            $sql = "
                SELECT 
                    ar.type,
                    COUNT(DISTINCT ar.id) as resource_count,
                    SUM(ar.quantity) as total_quantity,
                    SUM(ar.cost) as total_cost,
                    COUNT(DISTINCT ar.activity_id) as activities_count,
                    AVG(ar.cost) as avg_cost_per_resource
                FROM activity_resources ar
                JOIN activities a ON ar.activity_id = a.id
                WHERE $whereClause
                GROUP BY ar.type
                ORDER BY total_cost DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $stats
            ];

        } catch (Exception $e) {
            $this->logError($e, 'Failed to get resource statistics');
            throw $e;
        }
    }

    /**
     * Update resource status
     * 
     * @param int $id Resource ID
     * @param string $status New status
     * @param int $userId User making the update
     * @return array Update result
     */
    public function updateResourceStatus($id, $status, $userId)
    {
        try {
            $validStatuses = ['available', 'in_use', 'maintenance', 'damaged', 'lost'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
            }

            $stmt = $this->db->prepare("SELECT id, name FROM activity_resources WHERE id = ?");
            $stmt->execute([$id]);
            $resource = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$resource) {
                throw new Exception('Resource not found');
            }

            $this->beginTransaction();

            $stmt = $this->db->prepare("UPDATE activity_resources SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            $this->commit();

            $this->logAction('update', $id, "Updated resource status to '$status': {$resource['name']}");

            return [
                'success' => true,
                'message' => 'Resource status updated successfully',
                'data' => ['status' => $status]
            ];

        } catch (Exception $e) {
            $this->rollBack();
            $this->logError($e, "Failed to update resource status $id");
            throw $e;
        }
    }
}
