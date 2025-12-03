<?php
namespace App\API\Modules\transport;

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
            SELECT a.*, r.name AS route_name, r.vehicle_id, r.driver_id, s.name AS stop_name,
                   v.registration_no AS vehicle_registration, v.model AS vehicle_model, v.capacity AS vehicle_capacity,
                   d.first_name AS driver_first_name, d.last_name AS driver_last_name, d.phone AS driver_phone
            FROM student_transport_assignments a
            JOIN transport_routes r ON a.route_id = r.id
            JOIN transport_stops s ON a.stop_id = s.id
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            LEFT JOIN drivers d ON r.driver_id = d.id
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
            SELECT a.*, s.first_name, s.last_name, s.admission_no, st.name AS stop_name
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
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
            SELECT r.*, v.registration_no AS vehicle_registration, v.model AS vehicle_model, v.capacity AS vehicle_capacity,
                   d.first_name AS driver_first_name, d.last_name AS driver_last_name, d.phone AS driver_phone
            FROM transport_routes r
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            LEFT JOIN drivers d ON r.driver_id = d.id
            ORDER BY r.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all stops for a route
    public function getStopsByRoute($routeId)
    {
        $sql = "SELECT * FROM transport_stops WHERE route_id = ? ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Assign/unassign driver or vehicle to a route
    public function updateRouteDriverVehicle($routeId, $driverId, $vehicleId)
    {
        $sql = "UPDATE transport_routes SET driver_id = ?, vehicle_id = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$driverId, $vehicleId, $routeId]);
        return $stmt->rowCount() > 0;
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

    // Get all drivers
    public function getAllDrivers()
    {
        $sql = "SELECT * FROM drivers ORDER BY first_name, last_name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all vehicles
    public function getAllVehicles()
    {
        $sql = "SELECT * FROM vehicles ORDER BY registration_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
