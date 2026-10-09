<?php
namespace App\API\Modules\transport;
use App\API\Services\ReadReplicaService;

use PDO;
use Exception;

class StudentTransportAssignmentManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // Assign or update student transport assignment
    public function assignStudent($studentId, $routeId, $stopId, $month, $year)
    {
        $stmt = $this->db->prepare("CALL sp_assign_student_transport(?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $routeId, $stopId, $month, $year]);
        return $stmt->rowCount() > 0;
    }

    // Withdraw assignment
    public function withdrawAssignment($studentId, $month, $year)
    {
        $stmt = $this->db->prepare("UPDATE student_transport_assignments SET status='withdrawn' WHERE student_id=? AND month=? AND year=?");
        $stmt->execute([$studentId, $month, $year]);
        return $stmt->rowCount() > 0;
    }

    // Get all assignments for a student (with route, driver, vehicle, stops)
    public function getAssignments($studentId)
    {
        $sql = "
            SELECT a.*, r.name AS route_name,
                   s.name AS stop_name,
                   v.registration_number AS vehicle_registration, v.model AS vehicle_model, v.capacity AS vehicle_capacity,
                   p.first_name AS driver_first_name, p.last_name AS driver_last_name, p.phone AS driver_phone
            FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " a
            JOIN " . ReadReplicaService::qualifiedRef("transport_routes") . " r ON a.route_id = r.id
            JOIN transport_stops s ON a.stop_id = s.id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_vehicle_routes") . " tvr ON tvr.route_id = r.id AND tvr.status = 'active'
            LEFT JOIN transport_vehicles v ON v.id = tvr.vehicle_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " d ON d.id = v.driver_id AND d.position = 'Driver'
            LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = d.person_id
            WHERE a.student_id = ?
            ORDER BY a.year DESC, a.month DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all students assigned to a route (optionally for a specific month/year)
    public function getStudentsByRoute($routeId, $month = null, $year = null)
    {
        $sql = "
            SELECT a.*, p.first_name, p.last_name, s.admission_no, st.name AS stop_name
            FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " a
            JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON a.student_id = s.id
            JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
            JOIN transport_stops st ON a.stop_id = st.id
            WHERE a.route_id = ? AND a.status = 'active'"
            . ($month ? " AND a.month = " . intval($month) : "")
            . ($year ? " AND a.year = " . intval($year) : "")
            . " ORDER BY st.name, s.admission_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all routes, with driver and vehicle info
    public function getAllRoutes()
    {
        $sql = "
            SELECT r.*, v.registration_number AS vehicle_registration, v.model AS vehicle_model, v.capacity AS vehicle_capacity,
                   p.first_name AS driver_first_name, p.last_name AS driver_last_name, p.phone AS driver_phone
            FROM " . ReadReplicaService::qualifiedRef("transport_routes") . " r
            LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_vehicle_routes") . " tvr ON tvr.route_id = r.id AND tvr.status = 'active'
            LEFT JOIN transport_vehicles v ON v.id = tvr.vehicle_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("staff") . " d ON d.id = v.driver_id AND d.position = 'Driver'
            LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = d.person_id
            ORDER BY r.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all stops for a route
    public function getStopsByRoute($routeId)
    {
        $sql = "SELECT * FROM transport_stops WHERE route_id = ? ORDER BY sequence, name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Assign/unassign driver or vehicle to a route
    public function updateRouteDriverVehicle($routeId, $driverId, $vehicleId)
    {
        $ok = false;
        if ($vehicleId) {
            $stmt = $this->db->prepare(
                "INSERT INTO transport_vehicle_routes (vehicle_id, route_id, direction, status)
                 VALUES (?, ?, 'pickup', 'active')
                 ON DUPLICATE KEY UPDATE status = 'active'"
            );
            $stmt->execute([$vehicleId, $routeId]);
            $ok = true;
        }
        if ($driverId) {
            $stmt = $this->db->prepare(
                "UPDATE transport_vehicles v
                 JOIN transport_vehicle_routes tvr ON tvr.vehicle_id = v.id
                 SET v.driver_id = ?
                 WHERE tvr.route_id = ? AND tvr.status = 'active'"
            );
            $stmt->execute([$driverId, $routeId]);
            $ok = true;
        }
        return $ok;
    }

    // Bulk assign students to a route/stop for a month/year
    public function bulkAssignStudents($studentIds, $routeId, $stopId, $month, $year)
    {
        $success = 0;
        foreach ($studentIds as $studentId) {
            if ($this->assignStudent($studentId, $routeId, $stopId, $month, $year)) {
                $success++;
            }
        }
        return $success;
    }

    // Get assignment history for a student
    public function getAssignmentHistory($studentId)
    {
        $sql = "SELECT * FROM student_transport_assignments WHERE student_id = ? ORDER BY year DESC, month DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all drivers (staff with position='Driver')
    public function getAllDrivers()
    {
        $sql = "SELECT s.staff_id AS id, s.staff_no, s.position, s.staff_status AS status,
                       s.first_name, s.last_name, s.phone
                FROM " . ReadReplicaService::qualifiedRef("staff_directory") . " s
                WHERE s.position = 'Driver'
                ORDER BY s.first_name, s.last_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all vehicles
    public function getAllVehicles()
    {
        $sql = "SELECT * FROM transport_vehicles ORDER BY registration_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
