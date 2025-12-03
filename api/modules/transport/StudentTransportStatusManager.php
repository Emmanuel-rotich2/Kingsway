<?php
namespace App\API\Modules\transport;

use PDO;
use Exception;

class StudentTransportStatusManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    // Check assignment and payment status for a student for a given month/year
    public function checkStatus($studentId, $month, $year)
    {
        $stmt = $this->db->prepare("CALL sp_check_student_transport_status(?, ?, ?)");
        $stmt->execute([$studentId, $month, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // For QR scan: get current status (student, route, stop, driver, vehicle, payment)
    public function getCurrentStatus($studentId)
    {
        $now = new \DateTime();
        $month = (int) $now->format('n');
        $year = (int) $now->format('Y');
        return $this->getFullStatus($studentId, $month, $year);
    }

    // Get full status for QR or manifest (student, route, stop, driver, vehicle, payment)
    public function getFullStatus($studentId, $month, $year)
    {
        $sql = "
            SELECT s.id AS student_id, s.first_name, s.last_name, s.admission_no,
                   a.route_id, r.name AS route_name, a.stop_id, st.name AS stop_name,
                   r.driver_id, d.first_name AS driver_first_name, d.last_name AS driver_last_name, d.phone AS driver_phone,
                   r.vehicle_id, v.registration_no AS vehicle_registration, v.model AS vehicle_model, v.capacity AS vehicle_capacity,
                   a.month, a.year, a.status AS assignment_status,
                   p.amount AS payment_amount, p.status AS payment_status
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
            JOIN transport_routes r ON a.route_id = r.id
            JOIN transport_stops st ON a.stop_id = st.id
            LEFT JOIN drivers d ON r.driver_id = d.id
            LEFT JOIN vehicles v ON r.vehicle_id = v.id
            LEFT JOIN student_transport_payments p
                ON a.student_id = p.student_id AND a.month = p.month AND a.year = p.year AND p.status = 'confirmed'
            WHERE a.student_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $month, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get route manifest (all students, stops, driver, vehicle, payment status)
    public function getRouteManifest($routeId, $month, $year)
    {
        $sql = "
            SELECT s.id AS student_id, s.first_name, s.last_name, s.admission_no,
                   a.stop_id, st.name AS stop_name,
                   p.amount AS payment_amount, p.status AS payment_status
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
            JOIN transport_stops st ON a.stop_id = st.id
            LEFT JOIN student_transport_payments p
                ON a.student_id = p.student_id AND a.month = p.month AND a.year = p.year AND p.status = 'confirmed'
            WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            ORDER BY st.name, s.admission_no
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get payment/arrears/credit summary for a student
    public function getStudentSummary($studentId)
    {
        $sql = "
            SELECT SUM(p.amount) AS total_paid, SUM(a.expected_amount) AS total_expected,
                   (SUM(p.amount) - SUM(a.expected_amount)) AS balance
            FROM student_transport_assignments a
            LEFT JOIN student_transport_payments p
                ON a.student_id = p.student_id AND a.month = p.month AND a.year = p.year AND p.status = 'confirmed'
            WHERE a.student_id = ? AND a.status = 'active'
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get summary for all students on a route
    public function getRouteSummary($routeId, $month, $year)
    {
        $sql = "
            SELECT a.student_id, s.first_name, s.last_name, s.admission_no,
                   SUM(p.amount) AS total_paid, a.expected_amount,
                   (SUM(p.amount) - a.expected_amount) AS balance
            FROM student_transport_assignments a
            JOIN students s ON a.student_id = s.id
            LEFT JOIN student_transport_payments p
                ON a.student_id = p.student_id AND a.month = p.month AND a.year = p.year AND p.status = 'confirmed'
            WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            GROUP BY a.student_id, a.expected_amount, s.first_name, s.last_name, s.admission_no
            ORDER BY s.admission_no
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
