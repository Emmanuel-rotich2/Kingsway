<?php
namespace App\API\Modules\Transport;

use PDO;

class RouteManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for routes
    public function createRoute($data)
    {
        $sql = "INSERT INTO transport_routes (name, description, start_point, end_point, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['start_point'],
            $data['end_point'],
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateRoute($id, $data)
    {
        $sql = "UPDATE transport_routes SET name=?, description=?, start_point=?, end_point=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['name'],
            $data['description'],
            $data['start_point'],
            $data['end_point'],
            $data['status'],
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateRoute($id)
    {
        $stmt = $this->db->prepare("UPDATE transport_routes SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteRoute($id)
    {
        $stmt = $this->db->prepare("DELETE FROM transport_routes WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getRoute($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_routes WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllRoutes()
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_routes");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Assign stops to route, set order, geolocation
    public function assignStopToRoute($routeId, $stopId, $order, $lat, $lng)
    {
        $sql = "UPDATE transport_stops SET route_id=?, stop_order=?, latitude=?, longitude=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId, $order, $lat, $lng, $stopId]);
        return $stmt->rowCount() > 0;
    }
    // Get stops for a route (ordered)
    public function getStopsForRoute($routeId)
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_stops WHERE route_id=? ORDER BY stop_order ASC");
        $stmt->execute([$routeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
