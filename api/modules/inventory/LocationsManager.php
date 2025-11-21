<?php
namespace App\API\Modules\Inventory;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Locations Manager
 * 
 * Manages inventory storage locations
 */
class LocationsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('inventory');
    }

    public function listLocations($params = [])
    {
        try {
            $sql = "
                SELECT 
                    l.*,
                    COUNT(DISTINCT i.id) as item_count,
                    SUM(i.quantity_on_hand * i.unit_cost) as total_value
                FROM inventory_locations l
                LEFT JOIN inventory_items i ON l.id = i.location_id
                WHERE l.status = 'active'
                GROUP BY l.id
                ORDER BY l.location_name
            ";
            $stmt = $this->db->query($sql);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['locations' => $locations]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function getLocation($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    l.*,
                    COUNT(DISTINCT i.id) as item_count,
                    SUM(i.quantity_on_hand * i.unit_cost) as total_value
                FROM inventory_locations l
                LEFT JOIN inventory_items i ON l.id = i.location_id
                WHERE l.id = ?
                GROUP BY l.id
            ");
            $stmt->execute([$id]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                return formatResponse(false, null, 'Location not found', 404);
            }

            return formatResponse(true, $location);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function createLocation($data)
    {
        try {
            if (empty($data['location_name'])) {
                return formatResponse(false, null, 'Location name is required');
            }

            $sql = "
                INSERT INTO inventory_locations (
                    location_name, location_type, description, status, created_at
                ) VALUES (?, ?, ?, 'active', NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['location_name'],
                $data['location_type'] ?? 'warehouse',
                $data['description'] ?? null
            ]);

            $locationId = $this->db->lastInsertId();
            $this->logAction('create', $locationId, "Created location: {$data['location_name']}");

            return formatResponse(true, ['id' => $locationId], 'Location created successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    public function updateLocation($id, $data)
    {
        try {
            $stmt = $this->db->prepare("SELECT location_name FROM inventory_locations WHERE id = ?");
            $stmt->execute([$id]);
            $location = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$location) {
                return formatResponse(false, null, 'Location not found', 404);
            }

            $updates = [];
            $params = [];
            $allowedFields = ['location_name', 'location_type', 'description', 'status'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                return formatResponse(false, null, 'No fields to update');
            }

            $params[] = $id;
            $sql = "UPDATE inventory_locations SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->logAction('update', $id, "Updated location: {$location['location_name']}");

            return formatResponse(true, null, 'Location updated successfully');

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
