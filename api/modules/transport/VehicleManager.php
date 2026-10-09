<?php
namespace App\API\Modules\transport;

use PDO;
use App\API\Services\ReadReplicaService;

class VehicleManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for vehicles
    public function createVehicle($data)
    {
        $registration = strtoupper(trim((string)($data['registration_number'] ?? $data['registration_no'] ?? $data['plate_number'] ?? '')));
        $capacity = (int)($data['capacity'] ?? 0);
        if ($registration === '' || $capacity < 1) throw new \InvalidArgumentException('registration_number and a capacity of at least 1 are required');
        $exists = $this->db->prepare('SELECT id FROM transport_vehicles WHERE registration_number=? LIMIT 1'); $exists->execute([$registration]);
        if ($exists->fetchColumn()) throw new \InvalidArgumentException('A vehicle with this registration already exists');
        $sql = "INSERT INTO transport_vehicles (registration_number, type, model, make, year, capacity, insurance_expiry, service_due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $registration, $data['type'] ?? 'School Bus', $data['model'] ?? null, $data['make'] ?? null,
            $data['year'] ?? null, $capacity, $data['insurance_expiry'] ?? null, $data['service_due_date'] ?? null,
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateVehicle($id, $data)
    {
        $registration = strtoupper(trim((string)($data['registration_number'] ?? $data['registration_no'] ?? '')));
        $capacity = (int)($data['capacity'] ?? 0);
        if ($registration === '' || $capacity < 1) throw new \InvalidArgumentException('registration_number and a capacity of at least 1 are required');
        $exists = $this->db->prepare('SELECT id FROM transport_vehicles WHERE registration_number=? AND id<>? LIMIT 1'); $exists->execute([$registration, $id]);
        if ($exists->fetchColumn()) throw new \InvalidArgumentException('A vehicle with this registration already exists');
        $sql = "UPDATE transport_vehicles SET registration_number=?, type=?, model=?, make=?, year=?, capacity=?, insurance_expiry=?, service_due_date=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $registration, $data['type'] ?? 'School Bus', $data['model'] ?? null, $data['make'] ?? null,
            $data['year'] ?? null, $capacity, $data['insurance_expiry'] ?? null, $data['service_due_date'] ?? null,
            $data['status'] ?? 'active',
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateVehicle($id)
    {
        $stmt = $this->db->prepare("UPDATE transport_vehicles SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteVehicle($id)
    {
        $stmt = $this->db->prepare("DELETE FROM transport_vehicles WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getVehicle($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_vehicles WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllVehicles()
    {
        $stmt = $this->db->prepare("SELECT v.*,
                    (SELECT GROUP_CONCAT(DISTINCT tvr.route_id ORDER BY tvr.route_id) FROM " . ReadReplicaService::qualifiedRef("transport_vehicle_routes") . " WHERE tvr.vehicle_id=v.id AND tvr.status='active') AS route_ids,
                    (SELECT GROUP_CONCAT(DISTINCT tr.name ORDER BY tr.name SEPARATOR ', ') FROM " . ReadReplicaService::qualifiedRef("transport_vehicle_routes") . " tvr JOIN " . ReadReplicaService::qualifiedRef("transport_routes") . " tr ON tr.id=tvr.route_id WHERE tvr.vehicle_id=v.id AND tvr.status='active') AS route_names
                FROM transport_vehicles v ORDER BY v.registration_number");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function syncVehicleRoutes(int $vehicleId, array $routeIds): array
    {
        $routeIds = array_values(array_unique(array_filter(array_map('intval', $routeIds), fn($id) => $id > 0)));
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE transport_vehicle_routes SET status='inactive' WHERE vehicle_id=?")->execute([$vehicleId]);
            $stmt = $this->db->prepare("INSERT INTO transport_vehicle_routes (vehicle_id,route_id,direction,status) VALUES (?,?,'pickup','active') ON DUPLICATE KEY UPDATE status='active'");
            foreach ($routeIds as $routeId) $stmt->execute([$vehicleId, $routeId]);
            $this->db->commit();
            return ['vehicle_id' => $vehicleId, 'route_ids' => $routeIds];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
    // Assign vehicle to route (link stored in transport_vehicle_routes)
    public function assignVehicleToRoute($vehicleId, $routeId)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO transport_vehicle_routes (vehicle_id, route_id, direction, status)
             VALUES (?, ?, 'pickup', 'active')
             ON DUPLICATE KEY UPDATE status = 'active'"
        );
        $stmt->execute([$vehicleId, $routeId]);
        return $stmt->rowCount() > 0;
    }
    // Track vehicle status
    public function setVehicleStatus($id, $status)
    {
        $stmt = $this->db->prepare("UPDATE transport_vehicles SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }
    // Maintenance records (basic)
    public function addMaintenanceRecord($vehicleId, $description, $date)
    {
        $sql = "INSERT INTO vehicle_maintenance (vehicle_id, description, maintenance_date) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$vehicleId, $description, $date]);
        return $this->db->lastInsertId();
    }
    public function getMaintenanceRecords($vehicleId)
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicle_maintenance WHERE vehicle_id=? ORDER BY maintenance_date DESC");
        $stmt->execute([$vehicleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
