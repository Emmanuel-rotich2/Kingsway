<?php
namespace App\API\Modules\Transport;

use PDO;

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
        $sql = "INSERT INTO vehicles (registration_no, model, capacity, insurance_expiry, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['registration_no'],
            $data['model'],
            $data['capacity'],
            $data['insurance_expiry'],
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateVehicle($id, $data)
    {
        $sql = "UPDATE vehicles SET registration_no=?, model=?, capacity=?, insurance_expiry=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['registration_no'],
            $data['model'],
            $data['capacity'],
            $data['insurance_expiry'],
            $data['status'],
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateVehicle($id)
    {
        $stmt = $this->db->prepare("UPDATE vehicles SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteVehicle($id)
    {
        $stmt = $this->db->prepare("DELETE FROM vehicles WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getVehicle($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicles WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllVehicles()
    {
        $stmt = $this->db->prepare("SELECT * FROM vehicles");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Assign vehicle to route
    public function assignVehicleToRoute($vehicleId, $routeId)
    {
        $stmt = $this->db->prepare("UPDATE transport_routes SET vehicle_id=? WHERE id=?");
        $stmt->execute([$vehicleId, $routeId]);
        return $stmt->rowCount() > 0;
    }
    // Track vehicle status
    public function setVehicleStatus($id, $status)
    {
        $stmt = $this->db->prepare("UPDATE vehicles SET status=? WHERE id=?");
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
