<?php
namespace App\API\Modules\Transport;

use PDO;

class StopManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for stops
    public function createStop($data)
    {
        $sql = "INSERT INTO transport_stops (name, route_id, stop_order, latitude, longitude, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['route_id'],
            $data['stop_order'],
            $data['latitude'],
            $data['longitude'],
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateStop($id, $data)
    {
        $sql = "UPDATE transport_stops SET name=?, route_id=?, stop_order=?, latitude=?, longitude=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['route_id'],
            $data['stop_order'],
            $data['latitude'],
            $data['longitude'],
            $data['status'],
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateStop($id)
    {
        $stmt = $this->db->prepare("UPDATE transport_stops SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteStop($id)
    {
        $stmt = $this->db->prepare("DELETE FROM transport_stops WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getStop($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_stops WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllStops()
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_stops");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
