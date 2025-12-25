<?php
namespace App\API\Modules\maintenance;

use App\API\Includes\BaseAPI;
use PDO;

/**
 * Equipment Manager - Handles equipment maintenance CRUD operations
 */
class EquipmentManager extends BaseAPI
{
    private $table = 'equipment_maintenance';

    public function __construct()
    {
        parent::__construct('maintenance');
    }

    /**
     * List all equipment maintenance records
     */
    public function listEquipment($filters = [])
    {
        try {
            $sql = "SELECT em.*, 
                           eamt.name as maintenance_type_name,
                           CASE 
                               WHEN em.status = 'overdue' THEN 'Overdue'
                               WHEN em.status = 'pending' THEN 'Pending'
                               WHEN em.status = 'scheduled' THEN 'Scheduled'
                               WHEN em.status = 'in_progress' THEN 'In Progress'
                               WHEN em.status = 'completed' THEN 'Completed'
                               ELSE em.status
                           END as status_label
                    FROM {$this->table} em
                    LEFT JOIN equipment_maintenance_types eamt ON em.maintenance_type_id = eamt.id
                    WHERE 1=1";

            $params = [];

            // Apply filters
            if (!empty($filters['status'])) {
                $sql .= " AND em.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['equipment_id'])) {
                $sql .= " AND em.equipment_id = ?";
                $params[] = $filters['equipment_id'];
            }

            if (!empty($filters['overdue_only'])) {
                $sql .= " AND em.status = 'overdue'";
            }

            $sql .= " ORDER BY em.next_maintenance_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $records,
                'count' => count($records)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to list equipment maintenance records: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get a single equipment maintenance record
     */
    public function getEquipment($id)
    {
        try {
            $sql = "SELECT em.*, 
                           eamt.name as maintenance_type_name
                    FROM {$this->table} em
                    LEFT JOIN equipment_maintenance_types eamt ON em.maintenance_type_id = eamt.id
                    WHERE em.id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'Equipment maintenance record not found'
                ];
            }

            return [
                'success' => true,
                'data' => $record
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get equipment maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create new equipment maintenance record
     */
    public function createEquipment($data)
    {
        try {
            // Validate required fields
            $required = ['equipment_id', 'maintenance_type_id', 'status'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Missing required field: $field"
                    ];
                }
            }

            // Build dynamic field list based on provided data
            $insertFields = [];
            $placeholders = [];
            $values = [];

            $allowedFields = ['equipment_id', 'maintenance_type_id', 'last_maintenance_date', 'next_maintenance_date', 'status', 'notes'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $insertFields[] = $field;
                    $placeholders[] = '?';
                    $values[] = $data[$field];
                }
            }

            // Ensure required fields are in the list
            if (!in_array('status', $insertFields)) {
                $insertFields[] = 'status';
                $placeholders[] = '?';
                $values[] = $data['status'];
            }

            $fieldsStr = implode(', ', $insertFields);
            $sql = "INSERT INTO {$this->table} ($fieldsStr) VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($values);

            if ($success) {
                $id = $this->db->lastInsertId();
                return [
                    'success' => true,
                    'message' => 'Equipment maintenance record created',
                    'id' => $id,
                    'data' => ['id' => $id]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create equipment maintenance record'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating equipment maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update equipment maintenance record
     */
    public function updateEquipment($id, $data)
    {
        try {
            // Check if record exists
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Equipment maintenance record not found'
                ];
            }

            $fields = ['equipment_id', 'maintenance_type_id', 'last_maintenance_date', 'next_maintenance_date', 'status', 'notes'];
            $updates = [];
            $values = [];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return [
                    'success' => false,
                    'message' => 'No fields to update'
                ];
            }

            $values[] = $id;
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($values);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Equipment maintenance record updated'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update equipment maintenance record'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating equipment maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete equipment maintenance record
     */
    public function deleteEquipment($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Equipment maintenance record deleted'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete equipment maintenance record'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting equipment maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get overdue maintenance records
     */
    public function getOverdueEquipment()
    {
        try {
            $sql = "SELECT em.*, eamt.name as maintenance_type_name
                    FROM {$this->table} em
                    LEFT JOIN equipment_maintenance_types eamt ON em.maintenance_type_id = eamt.id
                    WHERE em.status = 'overdue'
                    ORDER BY em.next_maintenance_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $records,
                'count' => count($records)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching overdue equipment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update equipment maintenance status
     */
    public function updateStatus($id, $status)
    {
        $validStatuses = ['pending', 'scheduled', 'in_progress', 'completed', 'cancelled', 'overdue'];

        if (!in_array($status, $validStatuses)) {
            return [
                'success' => false,
                'message' => 'Invalid status: ' . $status
            ];
        }

        try {
            $sql = "UPDATE {$this->table} SET status = ? WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([$status, $id]);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Status updated to: ' . $status
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update status'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ];
        }
    }
}
