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

    // CRUD for drivers — drivers are staff rows with position='Driver', names/phone via persons,
    // license_number stored in staff_qualifications.
    public function createDriver($data)
    {
        if (empty($data['first_name'])) {
            throw new \InvalidArgumentException('first_name is required');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO persons (first_name, middle_name, last_name, phone)
                 VALUES (?, ?, NULL, ?)"
            )->execute([$data['first_name'], $data['last_name'] ?? null, $data['phone'] ?? null]);
            $personId = (int) $this->db->lastInsertId();

            $staffNo = $data['staff_no'] ?? $this->nextStaffNumber();
            $this->db->prepare(
                "INSERT INTO staff (person_id, staff_no, position, employment_date, status)
                 VALUES (?, ?, 'Driver', CURDATE(), ?)"
            )->execute([$personId, $staffNo, $data['status'] ?? 'active']);
            $staffId = (int) $this->db->lastInsertId();

            if (!empty($data['license_number'])) {
                $this->saveLicense($staffId, $data['license_number']);
            }
            $this->db->commit();
            return $staffId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    public function updateDriver($id, $data)
    {
        if (empty($data['first_name'])) {
            throw new \InvalidArgumentException('first_name is required');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE persons p
                 JOIN staff s ON s.person_id = p.id
                 SET p.first_name = ?, p.last_name = ?, p.phone = ?, s.status = ?
                 WHERE s.id = ?"
            )->execute([
                $data['first_name'],
                $data['last_name'] ?? null,
                $data['phone'] ?? null,
                $data['status'] ?? 'active',
                $id
            ]);
            if (isset($data['license_number'])) {
                $this->saveLicense($id, $data['license_number']);
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    public function deactivateDriver($id)
    {
        $stmt = $this->db->prepare("UPDATE staff SET status='inactive' WHERE id=? AND position='Driver'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function deleteDriver($id)
    {
        $stmt = $this->db->prepare("DELETE FROM staff WHERE id=? AND position='Driver'");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
    public function getDriver($id)
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.staff_no, s.position, s.status,
                    p.first_name, p.last_name, p.phone,
                    (SELECT q.description FROM staff_qualifications q
                      WHERE q.staff_id = s.id AND q.title = 'Driving License'
                      ORDER BY q.id DESC LIMIT 1) AS license_number,
                    (SELECT GROUP_CONCAT(DISTINCT sch.route_id ORDER BY sch.route_id)
                       FROM transport_schedules sch WHERE sch.driver_id=s.id AND sch.date IS NULL AND sch.status='active') AS route_ids,
                    (SELECT GROUP_CONCAT(DISTINCT tr.name ORDER BY tr.name SEPARATOR ', ')
                       FROM transport_schedules sch JOIN transport_routes tr ON tr.id=sch.route_id
                      WHERE sch.driver_id=s.id AND sch.date IS NULL AND sch.status='active') AS route_names
             FROM staff s
             JOIN persons p ON p.id = s.person_id
             WHERE s.id = ? AND s.position = 'Driver'"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function getAllDrivers()
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.staff_no, s.position, s.status,
                    p.first_name, p.last_name, p.phone,
                    (SELECT q.description FROM staff_qualifications q
                      WHERE q.staff_id = s.id AND q.title = 'Driving License'
                      ORDER BY q.id DESC LIMIT 1) AS license_number
             FROM staff s
             JOIN persons p ON p.id = s.person_id
             WHERE s.position = 'Driver'
             ORDER BY p.last_name, p.first_name"
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncDriverRoutes(int $driverId, array $routeIds): array
    {
        $routeIds = array_values(array_unique(array_filter(array_map('intval', $routeIds), fn($id) => $id > 0)));
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM transport_schedules WHERE driver_id=? AND date IS NULL")->execute([$driverId]);
            $insert = $this->db->prepare("INSERT INTO transport_schedules (driver_id,route_id,date,status) VALUES (?,?,NULL,'active')");
            foreach ($routeIds as $routeId) $insert->execute([$driverId, $routeId]);
            $this->db->commit();
            return ['driver_id' => $driverId, 'route_ids' => $routeIds];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
    // Assign driver to the vehicle(s) serving a route
    public function assignDriverToRoute($driverId, $routeId)
    {
        $stmt = $this->db->prepare(
            "UPDATE transport_vehicles v
             JOIN transport_vehicle_routes tvr ON tvr.vehicle_id = v.id
             SET v.driver_id = ?
             WHERE tvr.route_id = ? AND tvr.status = 'active'"
        );
        $stmt->execute([$driverId, $routeId]);
        return $stmt->rowCount() > 0;
    }
    // Attendance tracking (basic) — staff_attendance rows
    public function recordAttendance($driverId, $date, $status)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO staff_attendance (staff_id, date, status, created_at)
             VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute([$driverId, $date, $status]);
        return (int) $this->db->lastInsertId();
    }
    public function getAttendance($driverId)
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM staff_attendance WHERE staff_id=? ORDER BY date DESC"
        );
        $stmt->execute([$driverId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private function saveLicense($staffId, $licenseNumber)
    {
        $this->db->prepare("DELETE FROM staff_qualifications WHERE staff_id=? AND title='Driving License'")
            ->execute([$staffId]);
        $this->db->prepare(
            "INSERT INTO staff_qualifications (staff_id, qualification_type, title, institution, year_obtained, description)
             VALUES (?, 'certificate', 'Driving License', 'N/A', YEAR(CURDATE()), ?)"
        )->execute([$staffId, $licenseNumber]);
    }
    private function nextStaffNumber(): string
    {
        $service = new \App\API\Services\StaffNumberService($this->db);
        return $service->generate();
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
             INNER JOIN users u ON u.person_id = s.person_id
             WHERE u.id = ?
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
            "SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) AS driver_name,
                    s.staff_no, p.phone AS driver_phone
             FROM staff s
             INNER JOIN transport_vehicles v ON v.driver_id = s.id
             INNER JOIN persons p ON p.id = s.person_id
             INNER JOIN users u ON u.person_id = s.person_id
             WHERE u.id = ?
               AND s.status IN ('active', 'on_leave')
             ORDER BY v.id DESC
             LIMIT 1"
        );
        $stmt->execute([(int) $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

}
