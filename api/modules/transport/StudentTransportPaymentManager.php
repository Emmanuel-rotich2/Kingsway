<?php
namespace App\API\Modules\transport;

use PDO;
use Exception;

class StudentTransportPaymentManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Record a transport payment via paybill (M-Pesa C2B)
    public function recordPaybillPayment($studentId, $amount, $paybillReference, $paymentMethod = 'mpesa', $status = 'pending')
    {
        $sql = "INSERT INTO transport_payments (student_id, amount, payment_method, paybill_reference, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId, $amount, $paymentMethod, $paybillReference, $status]);
        return $this->db->lastInsertId();
    }

    // Confirm a transport payment (after verification)
    public function confirmPaybillPayment($paymentId)
    {
        $sql = "UPDATE transport_payments SET status = 'confirmed', paid_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$paymentId]);
        return $stmt->rowCount() > 0;
    }

    // Verify student by admission number or phone
    public function verifyStudent($admissionNo = null, $phone = null)
    {
        $sql = "SELECT id, first_name, last_name, admission_no, phone FROM students WHERE 1=1";
        $params = [];
        if ($admissionNo) {
            $sql .= " AND admission_no = ?";
            $params[] = $admissionNo;
        }
        if ($phone) {
            $sql .= " AND phone = ?";
            $params[] = $phone;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Record payment for a student for a given month/year
    public function recordPayment($studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId)
    {
        $stmt = $this->db->prepare("CALL sp_record_transport_payment(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId]);
        return $stmt->rowCount() > 0;
    }

    // Update payment status (e.g., mark as reversed)
    public function updatePaymentStatus($paymentId, $status)
    {
        $stmt = $this->db->prepare("UPDATE student_transport_payments SET status=? WHERE id=?");
        $stmt->execute([$status, $paymentId]);
        return $stmt->rowCount() > 0;
    }

    // Get all payments for a student
    public function getPayments($studentId)
    {
        $stmt = $this->db->prepare("SELECT * FROM student_transport_payments WHERE student_id=? ORDER BY year DESC, month DESC, payment_date DESC");
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get payment summary (total paid, arrears, credit) for a student
    public function getPaymentSummary($studentId)
    {
        $sql = "SELECT SUM(amount) AS total_paid, SUM(CASE WHEN status = 'reversed' THEN amount ELSE 0 END) AS total_reversed FROM student_transport_payments WHERE student_id = ? AND status = 'confirmed'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalPaid = floatval($row['total_paid'] ?? 0);
        // $totalReversed is not used

        // Get total expected (from assignments)
        $sql2 = "SELECT SUM(expected_amount) AS total_expected FROM student_transport_assignments WHERE student_id = ? AND status = 'active'";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$studentId]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        $totalExpected = floatval($row2['total_expected'] ?? 0);

        $credit = max(0, $totalPaid - $totalExpected);
        $arrears = max(0, $totalExpected - $totalPaid);
        return [
            'total_paid' => $totalPaid,
            'total_expected' => $totalExpected,
            'arrears' => $arrears,
            'credit' => $credit
        ];
    }

    // Get payment summary for all students on a route/month/year
    public function getRoutePaymentSummary($routeId, $month, $year)
    {
        $sql = "SELECT a.student_id, s.first_name, s.last_name, s.admission_no, SUM(p.amount) AS total_paid, a.expected_amount, (SUM(p.amount) - a.expected_amount) AS balance FROM student_transport_assignments a JOIN students s ON a.student_id = s.id LEFT JOIN student_transport_payments p ON a.student_id = p.student_id AND p.month = a.month AND p.year = a.year AND p.status = 'confirmed' WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active' GROUP BY a.student_id, a.expected_amount, s.first_name, s.last_name, s.admission_no ORDER BY s.admission_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all arrears/credits for all students
    public function getAllArrearsCredits()
    {
        $sql = "SELECT a.student_id, s.first_name, s.last_name, s.admission_no, SUM(p.amount) AS total_paid, SUM(a.expected_amount) AS total_expected, (SUM(p.amount) - SUM(a.expected_amount)) AS balance FROM student_transport_assignments a JOIN students s ON a.student_id = s.id LEFT JOIN student_transport_payments p ON a.student_id = p.student_id AND a.month = p.month AND a.year = p.year AND p.status = 'confirmed' WHERE a.status = 'active' GROUP BY a.student_id, s.first_name, s.last_name, s.admission_no ORDER BY s.admission_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
