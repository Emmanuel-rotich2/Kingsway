<?php
namespace App\API\Modules\Transport;

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
        $sql = "INSERT INTO drivers (first_name, last_name, license_no, phone, employment_status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['license_no'],
            $data['phone'],
            $data['employment_status'] ?? 'active'
        ]);
        return $this->db->lastInsertId();
    }
    public function updateDriver($id, $data)
    {
        $sql = "UPDATE drivers SET first_name=?, last_name=?, license_no=?, phone=?, employment_status=? WHERE id=?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['license_no'],
            $data['phone'],
            $data['employment_status'],
            $id
        ]);
        return $stmt->rowCount() > 0;
    }
    public function deactivateDriver($id)
    {
        $stmt = $this->db->prepare("UPDATE drivers SET employment_status='inactive' WHERE id=?");
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
}
