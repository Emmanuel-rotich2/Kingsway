<?php
namespace App\API\Modules\transport;

use App\API\Services\ReadReplicaService;
use PDO;
use App\API\Services\FinancialPostingCoordinator;

class StudentTransportPaymentManager
{
    private $db;
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // Record a transport payment via paybill (M-Pesa C2B) — bills model
    public function recordPaybillPayment($studentId, $amount, $paybillReference, $paymentMethod = 'mpesa', $status = 'pending')
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM transport_monthly_bills
             WHERE student_id = ? AND billing_month = ?
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$studentId, date('Y-m-01')]);
        $billId = $stmt->fetchColumn();
        if (!$billId) {
            return false;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO transport_bill_payments (bill_id, amount, payment_method, transaction_id, payment_date, notes)
             VALUES (?, ?, ?, ?, CURDATE(), ?)"
        );
        $stmt->execute([$billId, $amount, $paymentMethod, $paybillReference, 'Paybill ' . $paybillReference]);
        return $this->db->lastInsertId();
    }

    // Confirm a transport payment (after verification) — recompute the bill status
    public function confirmPaybillPayment($paymentId)
    {
        $stmt = $this->db->prepare("SELECT bill_id FROM transport_bill_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $billId = $stmt->fetchColumn();
        if (!$billId) {
            return false;
        }
        return $this->recomputeBillStatus($billId);
    }

    // Verify student by admission number or phone
    public function verifyStudent($admissionNo = null, $phone = null)
    {
        $sql = "SELECT s.student_id AS id, s.first_name, s.last_name, s.admission_no, s.phone
                FROM " . ReadReplicaService::qualifiedRef('person_directory') . " s
                WHERE s.student_id IS NOT NULL";
        $params = [];
        if ($admissionNo) {
            $sql .= " AND s.admission_no = ?";
            $params[] = $admissionNo;
        }
        if ($phone) {
            $sql .= " AND s.phone = ?";
            $params[] = $phone;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Record payment for a student for a given month/year
    public function recordPayment($studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId, $financialAccountId = null, $userId = 0)
    {
        if ((int)$financialAccountId <= 0) throw new \RuntimeException('A transport receiving financial account is required.');
        $stmt = $this->db->prepare("CALL sp_record_transport_payment(?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$studentId, $amount, $month, $year, $paymentDate, $paymentMethod, $transactionId]);
        $lookup = $this->db->prepare('SELECT id FROM transport_bill_payments WHERE transaction_id=? ORDER BY id DESC LIMIT 1');
        $lookup->execute([$transactionId]);
        $paymentId = (int)$lookup->fetchColumn();
        if ($paymentId) (new FinancialPostingCoordinator($this->db))->postIncoming('transport_bill_payment', $paymentId, (int)$financialAccountId, 'transport', (string)$amount, (int)$userId, (string)$transactionId);
        return $stmt->rowCount() > 0;
    }

    // Update payment status (e.g., mark as reversed) — reversal removes the payment row
    public function updatePaymentStatus($paymentId, $status)
    {
        if (!in_array($status, ['reversed', 'cancelled', 'void'], true)) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT bill_id FROM transport_bill_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $billId = $stmt->fetchColumn();
        if (!$billId) {
            return false;
        }
        $stmt = $this->db->prepare("DELETE FROM transport_bill_payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        return $this->recomputeBillStatus($billId);
    }

    // Get all payments for a student (bills with their payments)
    public function getPayments($studentId)
    {
        $stmt = $this->db->prepare(
            "SELECT b.id AS bill_id, b.billing_month, b.amount_due, b.payment_status,
                    p.id AS payment_id, p.amount AS amount_paid, p.payment_method,
                    p.transaction_id, p.payment_date, p.notes
             FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . "
             LEFT JOIN transport_bill_payments p ON p.bill_id = b.id
             WHERE b.student_id = ?
             ORDER BY b.billing_month DESC, p.payment_date DESC"
        );
        $stmt->execute([$studentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get payment summary (total paid, arrears, credit) for a student
    public function getPaymentSummary($studentId)
    {
        $sql = "SELECT COALESCE(SUM(p.amount), 0) AS total_paid
                FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . "
                JOIN transport_bill_payments p ON p.bill_id = b.id
                WHERE b.student_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        $totalPaid = floatval($stmt->fetchColumn());

        $sql2 = "SELECT COALESCE(SUM(amount_due), 0) AS total_expected
                 FROM transport_monthly_bills WHERE student_id = ?";
        $stmt2 = $this->db->prepare($sql2);
        $stmt2->execute([$studentId]);
        $totalExpected = floatval($stmt2->fetchColumn());

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
        $billingMonth = sprintf('%04d-%02d-01', (int) $year, (int) $month);
        $sql = "SELECT a.student_id, p.first_name, p.last_name, s.admission_no,
                       b.amount_due AS expected_amount,
                       COALESCE(SUM(bp.amount), 0) AS total_paid,
                       (b.amount_due - COALESCE(SUM(bp.amount), 0)) AS balance
                FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " a
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON a.student_id = s.id
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b
                       ON b.student_id = a.student_id AND b.route_id = a.route_id AND b.billing_month = ?
                LEFT JOIN transport_bill_payments bp ON bp.bill_id = b.id
                WHERE a.route_id = ? AND a.month = ? AND a.year = ? AND a.status = 'active'
                GROUP BY a.student_id, p.first_name, p.last_name, s.admission_no, b.id, b.amount_due
                ORDER BY s.admission_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$billingMonth, $routeId, $month, $year]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get all arrears/credits for all students
    public function getAllArrearsCredits()
    {
        $sql = "SELECT a.student_id, p.first_name, p.last_name, s.admission_no,
                       COALESCE(SUM(bp.amount), 0) AS total_paid,
                       COALESCE(SUM(b.amount_due), 0) AS total_expected,
                       (COALESCE(SUM(b.amount_due), 0) - COALESCE(SUM(bp.amount), 0)) AS balance
                FROM " . ReadReplicaService::qualifiedRef("student_transport_assignments") . " a
                JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON a.student_id = s.id
                JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . " b ON b.student_id = a.student_id AND b.route_id = a.route_id
                LEFT JOIN transport_bill_payments bp ON bp.bill_id = b.id
                WHERE a.status = 'active'
                GROUP BY a.student_id, p.first_name, p.last_name, s.admission_no
                ORDER BY s.admission_no";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function recomputeBillStatus($billId)
    {
        $stmt = $this->db->prepare(
            "SELECT b.amount_due, COALESCE(SUM(p.amount), 0) AS paid
             FROM " . ReadReplicaService::qualifiedRef("transport_monthly_bills") . "
             LEFT JOIN transport_bill_payments p ON p.bill_id = b.id
             WHERE b.id = ?
             GROUP BY b.id"
        );
        $stmt->execute([$billId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }
        if ($row['paid'] >= $row['amount_due']) {
            $status = 'paid';
        } elseif ($row['paid'] > 0) {
            $status = 'partial';
        } else {
            $status = 'pending';
        }
        $stmt = $this->db->prepare("UPDATE transport_monthly_bills SET payment_status = ? WHERE id = ?");
        $stmt->execute([$status, $billId]);
        return true;
    }
}
