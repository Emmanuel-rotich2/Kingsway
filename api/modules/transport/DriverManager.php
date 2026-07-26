<?php
namespace App\API\Modules\transport;

use PDO;

class DriverManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // CRUD for drivers
    public function createDriver($data)
    {
        $sql = "INSERT INTO drivers (first_name, last_name, license_number, phone, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['license_number'],
            $data['phone'],
            $data['status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateDriver($id, $data)
    {
        $sql = "UPDATE drivers SET first_name=?, last_name=?, license_number=?, phone=?, status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['license_number'],
            $data['phone'],
            $data['status'],
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateDriver($id)
    {
        $stmt = $this->db->prepare("UPDATE drivers SET status='inactive' WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteDriver($id)
    {
        $stmt = $this->db->prepare("DELETE FROM drivers WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getDriver($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM drivers WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllDrivers()
    {
        $stmt = $this->db->prepare("SELECT * FROM drivers");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Assign driver to vehicle/route
    public function assignDriverToRoute($driverId, $routeId)
    {
        $stmt = $this->db->prepare("UPDATE transport_routes SET driver_id=? WHERE id=?");
        $stmt->execute([$driverId, $routeId]);
        return $stmt->rowCount() > 0;
    }
    // Attendance tracking (basic)
    public function recordAttendance($driverId, $date, $status)
    {
        $sql = "INSERT INTO driver_attendance (driver_id, attendance_date, status) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$driverId, $date, $status]);
        return $this->db->lastInsertId();
    }
    public function getAttendance($driverId)
    {
        $stmt = $this->db->prepare("SELECT * FROM driver_attendance WHERE driver_id=? ORDER BY attendance_date DESC");
        $stmt->execute([$driverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Resolve the route context assigned to the authenticated staff driver.
     * Canonical relation: users → staff → transport_vehicles → vehicle_routes.
     */
    public function getRouteForUser($userId)
    {
        $stmt = $this->db->prepare(
            "SELECT r.*, tvr.direction, v.id AS vehicle_id,
                    v.registration_number, v.capacity AS vehicle_capacity,
                    v.status AS vehicle_status,
                    (SELECT COUNT(*)
                       FROM student_transport_assignments sta
                      WHERE sta.route_id = r.id
                        AND sta.status = 'active'
                        AND sta.month = MONTH(CURDATE())
                        AND sta.year = YEAR(CURDATE())) AS passenger_count
             FROM staff s
             INNER JOIN transport_vehicles v ON v.driver_id = s.id
             INNER JOIN transport_vehicle_routes tvr
                     ON tvr.vehicle_id = v.id AND tvr.status = 'active'
             INNER JOIN transport_routes r
                     ON r.id = tvr.route_id AND r.status = 'active'
             WHERE s.user_id = ?
               AND s.status IN ('active', 'on_leave')
             ORDER BY tvr.id DESC
             LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        $route = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$route) {
            return null;
        }

        $stopStmt = $this->db->prepare(
            "SELECT id, route_id, name, sequence, arrival_time,
                    departure_time, location, status
             FROM transport_stops
             WHERE route_id = ? AND status = 'active'
             ORDER BY sequence"
        );
        $stopStmt->execute([(int) $route['id']]);
        $route['stops'] = $stopStmt->fetchAll(PDO::FETCH_ASSOC);

        $scheduleStmt = $this->db->prepare(
            "SELECT id, vehicle_id, route_id, driver_id, date,
                    pickup_time, term_id, status
             FROM transport_schedules
             WHERE route_id = ?
               AND status = 'active'
               AND (date IS NULL OR date >= CURDATE())
             ORDER BY date IS NULL, date, pickup_time
             LIMIT 14"
        );
        $scheduleStmt->execute([(int) $route['id']]);
        $route['schedules'] = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);

        $incidentStmt = $this->db->prepare(
            "SELECT id, student_id, route_id, vehicle_id, incident_datetime,
                    incident_type, description, action_taken, escalated,
                    created_at
             FROM student_transport_incidents
             WHERE route_id = ? OR vehicle_id = ?
             ORDER BY incident_datetime DESC
             LIMIT 10"
        );
        $incidentStmt->execute([(int) $route['id'], (int) $route['vehicle_id']]);
        $route['recent_incidents'] = $incidentStmt->fetchAll(PDO::FETCH_ASSOC);

        return $route;
    }

    public function getVehicleForUser($userId)
    {
        $stmt = $this->db->prepare(
            "SELECT v.*, CONCAT(s.first_name, ' ', s.last_name) AS driver_name,
                    s.staff_no, s.phone AS driver_phone
             FROM staff s
             INNER JOIN transport_vehicles v ON v.driver_id = s.id
             WHERE s.user_id = ?
               AND s.status IN ('active', 'on_leave')
             ORDER BY v.id DESC
             LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

}
