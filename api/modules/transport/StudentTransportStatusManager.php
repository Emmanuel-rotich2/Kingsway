<?php
namespace App\API\Modules\transport;

use PDO;
use Exception;
use App\API\Services\ReadReplicaService;

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
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "
            SELECT tac.assignment_student_id AS student_id,
                   tac.student_first_name AS first_name, tac.student_last_name AS last_name, tac.admission_no,
                   tac.assignment_route_id AS route_id, tac.route_name,
                   tac.assignment_stop_id AS stop_id, st.name AS stop_name,
                   sctx.staff_id AS driver_id, sctx.first_name AS driver_first_name, sctx.last_name AS driver_last_name, sctx.phone AS driver_phone,
                   tac.vehicle_id, tac.registration_number AS vehicle_registration, tac.model AS vehicle_model, tac.vehicle_capacity,
                   tac.month, tac.year, tac.assignment_status,
                   (SELECT COALESCE(SUM(bp.amount), 0) FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = tac.assignment_student_id AND b.route_id = tac.assignment_route_id AND b.billing_month = ?) AS payment_amount,
                   (SELECT b.payment_status FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                     WHERE b.student_id = tac.assignment_student_id AND b.route_id = tac.assignment_route_id AND b.billing_month = ?
                     LIMIT 1) AS payment_status
            FROM " . ReadReplicaService::qualifiedRef("transport_assignment_context") . " tac
            LEFT JOIN " . ReadReplicaService::qualifiedRef("staff_context") . " sctx
                   ON sctx.staff_id = tac.driver_staff_id AND sctx.position = 'Driver'
            JOIN transport_stops st ON st.id = tac.assignment_stop_id
            WHERE tac.assignment_student_id = ? AND tac.month = ? AND tac.year = ?
            LIMIT 1
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $billingMonth, $studentId, $month, $year]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get route manifest (all students, stops, driver, vehicle, payment status)
    public function getRouteManifest($routeId, $month, $year)
    {
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "
            SELECT s.id AS student_id, p.first_name, p.last_name, s.admission_no,
                   a.stop_id, st.name AS stop_name,
                   (SELECT COALESCE(SUM(bp.amount), 0) FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?) AS payment_amount,
                   (SELECT b.payment_status FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                     WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?
                     LIMIT 1) AS payment_status
            FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " a
            JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON a.student_id = s.id
            JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
            JOIN transport_stops st ON a.stop_id = st.id
            WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            ORDER BY st.name, s.admission_no
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $billingMonth, $routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Return the authenticated driver's live manifest for a trip.
     * The route is resolved from the driver's active vehicle assignment; a
     * driver cannot request another route's passenger list.
     */
    public function getDriverManifest(int $userId, string $date, string $tripSession): array
    {
        $validSessions = ['morning_pickup', 'evening_dropoff', 'midday_trip', 'special_trip'];
        if (!in_array($tripSession, $validSessions, true)) {
            throw new \InvalidArgumentException('Invalid transport trip session');
        }

        $dateObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Invalid attendance date');
        }

        $routeStmt = $this->db->prepare(
            "SELECT DISTINCT tr.id, tr.name, tr.code, tr.start_point, tr.end_point,
                    tr.morning_departure, tr.afternoon_departure,
                    v.id AS vehicle_id, v.registration_number AS vehicle_registration
             FROM " . ReadReplicaService::qualifiedRef("person_directory") . " 
             WHERE u.id = ? AND ds.status IN ('active','on_leave')
             ORDER BY tr.name"
        );
        $routeStmt->execute([$userId]);
        $routes = $routeStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$routes) {
            return ['date' => $date, 'trip_session' => $tripSession, 'routes' => [], 'students' => [], 'summary' => ['expected' => 0, 'boarded' => 0, 'remaining' => 0, 'rejected' => 0]];
        }

        $routeIds = array_map(static function ($route) { return (int)$route['id']; }, $routes);
        $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
        $month = (int)$dateObj->format('n');
        $year = (int)$dateObj->format('Y');
        $sql = "SELECT s.id AS student_id, s.admission_no, p.first_name, p.last_name,
                       a.route_id, a.stop_id, COALESCE(a.pickup_stop_id, a.stop_id) AS pickup_stop_id,
                       COALESCE(ps.name, ss.name) AS stop_name,
                       ps.sequence AS stop_sequence, ps.arrival_time, ps.departure_time,
                       sta.id AS attendance_id, sta.status AS attendance_status,
                       sta.marked_time, sta.marked_by, sta.vehicle_id AS attendance_vehicle_id
                FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . "
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = a.student_id
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
                LEFT JOIN transport_stops ps ON ps.id = a.pickup_stop_id
                LEFT JOIN transport_stops ss ON ss.id = a.stop_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("student_transport_attendance") . " sta
                  ON sta.student_id = a.student_id
                 AND sta.route_id = a.route_id
                 AND sta.attendance_date = ?
                 AND sta.trip_session = ?
                WHERE a.route_id IN ($placeholders)
                  AND a.month = ? AND a.year = ?
                  AND a.status IN ('active','suspended')
                ORDER BY a.route_id, COALESCE(ps.sequence, 9999), p.last_name, p.first_name";
        $params = array_merge([$date, $tripSession], $routeIds, [$month, $year]);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($students as &$student) {
            $status = (string)($student['attendance_status'] ?? '');
            $student['boarding_status'] = $status === 'picked_up' ? 'boarded'
                : ($status === 'dropped_off' ? 'dropped_off' : 'not_boarded');
            $student['boarded'] = $status === 'picked_up';
            $student['attendance_id'] = $student['attendance_id'] ? (int)$student['attendance_id'] : null;
            $student['student_id'] = (int)$student['student_id'];
            $student['route_id'] = (int)$student['route_id'];
        }
        unset($student);

        $expected = count($students);
        $boarded = count(array_filter($students, static function ($student) { return !empty($student['boarded']); }));
        return [
            'date' => $date,
            'trip_session' => $tripSession,
            'routes' => $routes,
            'students' => $students,
            'summary' => ['expected' => $expected, 'boarded' => $boarded, 'remaining' => max(0, $expected - $boarded), 'rejected' => 0],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    // Get payment/arrears/credit summary for a student
    public function getStudentSummary($studentId)
    {
        $sql = "
            SELECT COALESCE(SUM(p.amount), 0) AS total_paid,
                   (SELECT COALESCE(SUM(expected_amount), 0)
                      FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " student_id = ? AND status = 'active') AS total_expected,
                   COALESCE(SUM(p.amount), 0) - (SELECT COALESCE(SUM(expected_amount), 0)
                      FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " WHERE student_id = ? AND status = 'active') AS balance
            FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
            JOIN transport_bill_payments p ON p.bill_id = b.id
            WHERE b.student_id = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $studentId, $studentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get summary for all students on a route
    public function getRouteSummary($routeId, $month, $year)
    {
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "
            SELECT a.student_id, p.first_name, p.last_name, s.admission_no,
                   (SELECT COALESCE(SUM(bp.amount), 0) FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?) AS total_paid,
                   a.expected_amount,
                   (a.expected_amount - (SELECT COALESCE(SUM(bp.amount), 0) FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                     JOIN transport_bill_payments bp ON bp.bill_id = b.id
                    WHERE b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?)) AS balance
            FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " a
            JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON a.student_id = s.id
            JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
            WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
            ORDER BY s.admission_no
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $billingMonth, $routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
