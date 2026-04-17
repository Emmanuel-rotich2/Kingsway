<?php
namespace App\API\Modules\Finance;

use App\Database\Database;
use Exception;

class TransportBillingManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function subscribe(array $data): array
    {
        $studentId = (int)($data['student_id'] ?? 0);
        $routeId   = (int)($data['route_id'] ?? 0);
        if (!$studentId || !$routeId) throw new \InvalidArgumentException('student_id and route_id are required');

        $startMonth = $data['start_month'] ?? date('Y-m-01');
        $subscribedBy = $data['subscribed_by'] ?? null;

        // Cancel any existing active subscription for this student/route
        $this->db->query(
            "UPDATE transport_subscriptions SET status='cancelled', end_month=:em, updated_at=NOW()
             WHERE student_id=:sid AND route_id=:rid AND status='active'",
            [':em' => date('Y-m-01'), ':sid' => $studentId, ':rid' => $routeId]
        );

        $this->db->query(
            "INSERT INTO transport_subscriptions (student_id, route_id, start_month, status, subscribed_by)
             VALUES (:sid, :rid, :sm, 'active', :by)",
            [':sid' => $studentId, ':rid' => $routeId, ':sm' => $startMonth, ':by' => $subscribedBy]
        );
        return ['subscription_id' => (int)$this->db->lastInsertId()];
    }

    public function unsubscribe(int $id, string $endMonth, $userId): bool
    {
        $this->db->query(
            "UPDATE transport_subscriptions SET status='cancelled', end_month=:em, cancelled_by=:by, updated_at=NOW()
             WHERE id=:id",
            [':em' => $endMonth, ':by' => $userId, ':id' => $id]
        );
        return true;
    }

    public function getSubscriptions(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'ts.student_id=:sid'; $params[':sid'] = (int)$filters['student_id']; }
        if (!empty($filters['route_id']))   { $where[] = 'ts.route_id=:rid';   $params[':rid'] = (int)$filters['route_id']; }
        if (!empty($filters['status']))     { $where[] = 'ts.status=:status';  $params[':status'] = $filters['status']; }

        $stmt = $this->db->query(
            "SELECT ts.*,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                    r.name AS route_name, r.fee AS monthly_fee
             FROM transport_subscriptions ts
             JOIN students s ON s.id = ts.student_id
             LEFT JOIN transport_routes r ON r.id = ts.route_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY ts.created_at DESC",
            $params
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function generateMonthlyBills(string $billingMonth, $userId): array
    {
        // Find all active subscriptions
        $stmt = $this->db->query(
            "SELECT ts.id AS subscription_id, ts.student_id, ts.route_id,
                    COALESCE(r.fee AS monthly_fee, 0) AS amount
             FROM transport_subscriptions ts
             LEFT JOIN transport_routes r ON r.id = ts.route_id
             WHERE ts.status='active'
               AND (ts.end_month IS NULL OR ts.end_month >= :bm)
               AND ts.start_month <= :bm2",
            [':bm' => $billingMonth, ':bm2' => $billingMonth]
        );
        $subs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $ins = $this->db->getConnection()->prepare(
            "INSERT IGNORE INTO transport_bills
                (subscription_id, student_id, route_id, billing_month, amount, generated_by)
             VALUES (:sub, :sid, :rid, :bm, :amt, :by)"
        );
        $count = 0;
        foreach ($subs as $sub) {
            $ins->execute([
                ':sub' => $sub['subscription_id'],
                ':sid' => $sub['student_id'],
                ':rid' => $sub['route_id'],
                ':bm'  => $billingMonth,
                ':amt' => $sub['amount'],
                ':by'  => $userId,
            ]);
            $count++;
        }
        return ['generated' => $count, 'billing_month' => $billingMonth];
    }

    public function getBills(array $filters): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['billing_month']))  { $where[] = 'tb.billing_month=:bm';    $params[':bm'] = $filters['billing_month']; }
        if (!empty($filters['student_id']))     { $where[] = 'tb.student_id=:sid';       $params[':sid'] = (int)$filters['student_id']; }
        if (!empty($filters['route_id']))       { $where[] = 'tb.route_id=:rid';         $params[':rid'] = (int)$filters['route_id']; }
        if (!empty($filters['payment_status'])) { $where[] = 'tb.payment_status=:ps';   $params[':ps'] = $filters['payment_status']; }

        $page   = max(1, (int)($filters['page'] ?? 1));
        $limit  = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $total = (int)$this->db->query(
            "SELECT COUNT(*) FROM transport_bills tb WHERE " . implode(' AND ', $where),
            $params
        )->fetchColumn();

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;
        $stmt = $this->db->query(
            "SELECT tb.*,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                    r.name AS route_name
             FROM transport_bills tb
             JOIN students s ON s.id = tb.student_id
             LEFT JOIN transport_routes r ON r.id = tb.route_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY tb.billing_month DESC, s.last_name
             LIMIT :limit OFFSET :offset",
            $params
        );
        return [
            'bills' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total],
        ];
    }

    public function getMonthlyBillingSummary(string $billingMonth): array
    {
        $stmt = $this->db->query(
            "SELECT
                COUNT(*) AS total_bills,
                SUM(amount) AS total_billed,
                SUM(amount_paid) AS total_collected,
                SUM(amount - amount_paid) AS total_outstanding,
                SUM(payment_status='paid') AS fully_paid,
                SUM(payment_status='unpaid') AS unpaid,
                SUM(payment_status='partial') AS partial
             FROM transport_bills
             WHERE billing_month=:bm",
            [':bm' => $billingMonth]
        );
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    public function recordTransportPayment(int $billId, array $data): array
    {
        $amount = (float)($data['amount'] ?? 0);
        if ($amount <= 0) throw new \InvalidArgumentException('amount must be positive');

        $stmt = $this->db->query("SELECT * FROM transport_bills WHERE id=:id LIMIT 1", [':id' => $billId]);
        $bill = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$bill) throw new \InvalidArgumentException('Bill not found');

        $this->db->query(
            "INSERT INTO transport_bill_payments (bill_id, amount, payment_method, transaction_id, received_by, payment_date, notes)
             VALUES (:bid, :amt, :method, :txn, :by, :dt, :notes)",
            [
                ':bid'    => $billId,
                ':amt'    => $amount,
                ':method' => $data['payment_method'] ?? 'cash',
                ':txn'    => $data['transaction_id'] ?? null,
                ':by'     => $data['received_by'] ?? null,
                ':dt'     => $data['payment_date'] ?? date('Y-m-d'),
                ':notes'  => $data['notes'] ?? null,
            ]
        );

        $newPaid   = (float)$bill['amount_paid'] + $amount;
        $newStatus = $newPaid >= (float)$bill['amount'] ? 'paid' : 'partial';
        $this->db->query(
            "UPDATE transport_bills SET amount_paid=:paid, payment_status=:ps, paid_at=IF(:ps2='paid',NOW(),paid_at), updated_at=NOW() WHERE id=:id",
            [':paid' => $newPaid, ':ps' => $newStatus, ':ps2' => $newStatus, ':id' => $billId]
        );
        return ['bill_id' => $billId, 'amount_paid' => $newPaid, 'payment_status' => $newStatus];
    }
}
