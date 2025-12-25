<?php
namespace App\API\Modules\maintenance;

use App\API\Includes\BaseAPI;
use PDO;

/**
 * Vehicle Manager - Handles vehicle maintenance CRUD operations
 */
class VehicleManager extends BaseAPI
{
    private $table = 'vehicle_maintenance';

    public function __construct()
    {
        parent::__construct('maintenance');
    }

    /**
     * List all vehicle maintenance records
     */
    public function listVehicles($filters = [])
    {
        try {
            $sql = "SELECT vm.*,
                           CASE 
                               WHEN vm.maintenance_type = 'routine' THEN 'Routine'
                               WHEN vm.maintenance_type = 'repair' THEN 'Repair'
                               WHEN vm.maintenance_type = 'inspection' THEN 'Inspection'
                               WHEN vm.maintenance_type = 'emergency' THEN 'Emergency'
                               ELSE vm.maintenance_type
                           END as maintenance_type_label
                    FROM {$this->table} vm
                    WHERE 1=1";

            $params = [];

            // Apply filters
            if (!empty($filters['vehicle_id'])) {
                $sql .= " AND vm.vehicle_id = ?";
                $params[] = $filters['vehicle_id'];
            }

            if (!empty($filters['maintenance_type'])) {
                $sql .= " AND vm.maintenance_type = ?";
                $params[] = $filters['maintenance_type'];
            }

            if (!empty($filters['from_date'])) {
                $sql .= " AND vm.maintenance_date >= ?";
                $params[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $sql .= " AND vm.maintenance_date <= ?";
                $params[] = $filters['to_date'];
            }

            $sql .= " ORDER BY vm.maintenance_date DESC";

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
                'message' => 'Failed to list vehicle maintenance records: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get a single vehicle maintenance record
     */
    public function getVehicle($id)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'Vehicle maintenance record not found'
                ];
            }

            return [
                'success' => true,
                'data' => $record
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to get vehicle maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create new vehicle maintenance record
     */
    public function createVehicle($data)
    {
        try {
            // Validate required fields
            $required = ['vehicle_id', 'maintenance_date', 'description', 'maintenance_type'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    return [
                        'success' => false,
                        'message' => "Missing required field: $field"
                    ];
                }
            }

            $fields = [
                'vehicle_id',
                'maintenance_date',
                'description',
                'cost',
                'maintenance_type',
                'odometer_reading',
                'next_maintenance_date',
                'next_maintenance_reading',
                'parts_replaced',
                'mechanic_details',
                'documents_folder'
            ];

            $placeholders = [];
            $values = [];

            foreach ($fields as $field) {
                if (isset($data[$field])) {
                    $placeholders[] = '?';
                    $values[] = $data[$field] ?? null;
                }
            }

            $fieldsStr = implode(', ', array_filter($fields, fn($f) => isset($data[$f])));
            $sql = "INSERT INTO {$this->table} ($fieldsStr) VALUES (" . implode(', ', $placeholders) . ")";

            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute($values);

            if ($success) {
                $id = $this->db->lastInsertId();
                return [
                    'success' => true,
                    'message' => 'Vehicle maintenance record created',
                    'id' => $id,
                    'data' => ['id' => $id]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create vehicle maintenance record'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error creating vehicle maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update vehicle maintenance record
     */
    public function updateVehicle($id, $data)
    {
        try {
            // Check if record exists
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Vehicle maintenance record not found'
                ];
            }

            $fields = [
                'vehicle_id',
                'maintenance_date',
                'description',
                'cost',
                'maintenance_type',
                'odometer_reading',
                'next_maintenance_date',
                'next_maintenance_reading',
                'parts_replaced',
                'mechanic_details',
                'documents_folder'
            ];

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
                    'message' => 'Vehicle maintenance record updated'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update vehicle maintenance record'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating vehicle maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete vehicle maintenance record
     */
    public function deleteVehicle($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success) {
                return [
                    'success' => true,
                    'message' => 'Vehicle maintenance record deleted'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete vehicle maintenance record'
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting vehicle maintenance record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get maintenance cost summary by type
     */
    public function getCostSummary($filters = [])
    {
        try {
            $sql = "SELECT maintenance_type, COUNT(*) as count, SUM(cost) as total_cost, AVG(cost) as avg_cost
                    FROM {$this->table}
                    WHERE cost IS NOT NULL";

            $params = [];

            if (!empty($filters['vehicle_id'])) {
                $sql .= " AND vehicle_id = ?";
                $params[] = $filters['vehicle_id'];
            }

            $sql .= " GROUP BY maintenance_type";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $records
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching cost summary: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get upcoming maintenance schedule
     */
    public function getUpcomingSchedule($daysAhead = 30)
    {
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE next_maintenance_date IS NOT NULL
                    AND next_maintenance_date <= DATE_ADD(NOW(), INTERVAL ? DAY)
                    AND next_maintenance_date >= CURDATE()
                    ORDER BY next_maintenance_date ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$daysAhead]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'data' => $records,
                'count' => count($records)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching upcoming schedule: ' . $e->getMessage()
            ];
        }
    }
}
