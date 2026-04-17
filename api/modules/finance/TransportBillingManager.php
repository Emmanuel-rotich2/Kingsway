<?php
declare(strict_types=1);

namespace App\API\Modules\Finance;

use App\Database\Database;
use Exception;

/**
 * TransportBillingManager
 * Manages monthly transport subscription billing, separate from school fees.
 */
class TransportBillingManager
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Subscribe a student to a transport route from a given month.
     */
    public function subscribe(array $data): array
    {
        $studentId    = (int)($data['student_id'] ?? 0);
        $routeId      = (int)($data['route_id'] ?? 0);
        $startMonth   = $data['start_month'] ?? date('Y-m-01');
        $direction    = $data['direction'] ?? 'both';
        $subscribedBy = $data['subscribed_by'] ?? null;
        $notes        = $data['notes'] ?? null;

        if (!$studentId || !$routeId) {
            throw new \InvalidArgumentException('student_id and route_id are required');
        }

        // Get route fee
        $stmt = $this->db->prepare("SELECT fee, name FROM transport_routes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $routeId]);
        $route = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$route) throw new Exception("Route {$routeId} not found");

        $monthlyFee  = (float)($route['fee'] ?? 0);
        $academicYear = (int)date('Y', strtotime($startMonth));

        $stmt = $this->db->prepare("
            INSERT INTO transport_subscriptions
              (student_id, route_id, academic_year, start_month, monthly_fee, direction, status, subscribed_by, notes)
            VALUES (:sid, :rid, :yr, :sm, :fee, :dir, 'active', :by, :notes)
            ON DUPLICATE KEY UPDATE
              status='active', end_month=NULL, monthly_fee=:fee2, direction=:dir2,
              subscribed_by=:by2, notes=:notes2, updated_at=NOW()
        ");
        $stmt->execute([
            ':sid' => $studentId, ':rid' => $routeId, ':yr' => $academicYear,
            ':sm' => $startMonth, ':fee' => $monthlyFee, ':dir' => $direction,
            ':by' => $subscribedBy, ':notes' => $notes,
            ':fee2' => $monthlyFee, ':dir2' => $direction,
            ':by2' => $subscribedBy, ':notes2' => $notes,
        ]);
        $subId = (int)$this->db->lastInsertId();

        // Generate first month's bill
        $this->generateBillForSubscription($subId ?: $this->getSubscriptionId($studentId, $routeId, $startMonth), $startMonth);

        return ['subscription_id' => $subId, 'monthly_fee' => $monthlyFee, 'route_name' => $route['name']];
    }

    /**
     * Cancel / unsubscribe a student from transport.
     */
    public function unsubscribe(int $subscriptionId, string $endMonth, ?int $userId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE transport_subscriptions SET status='cancelled', end_month=:em, updated_at=NOW() WHERE id=:id"
        );
        $stmt->execute([':em' => $endMonth, ':id' => $subscriptionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate monthly bills for all active subscriptions for a given billing month.
     * @param string $billingMonth 'YYYY-MM-01' format
     */
    public function generateMonthlyBills(string $billingMonth, ?int $generatedBy): array
    {
        // Get all active subscriptions covering this month
        $stmt = $this->db->prepare("
            SELECT ts.*, tr.name AS route_name
            FROM transport_subscriptions ts
            JOIN transport_routes tr ON tr.id = ts.route_id
            WHERE ts.status = 'active'
              AND ts.start_month <= :bm
              AND (ts.end_month IS NULL OR ts.end_month >= :bm2)
        ");
        $stmt->execute([':bm' => $billingMonth, ':bm2' => $billingMonth]);
        $subscriptions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $dueDate   = date('Y-m-t', strtotime($billingMonth)); // last day of month
        $generated = 0;
        $skipped   = 0;

        foreach ($subscriptions as $sub) {
            $ins = $this->db->prepare("
                INSERT IGNORE INTO transport_monthly_bills
                  (student_id, subscription_id, route_id, billing_month, amount_due, payment_status, due_date, generated_by)
                VALUES (:sid, :subid, :rid, :bm, :amt, 'pending', :due, :by)
            ");
            $ins->execute([
                ':sid'   => $sub['student_id'],
                ':subid' => $sub['id'],
                ':rid'   => $sub['route_id'],
                ':bm'    => $billingMonth,
                ':amt'   => $sub['monthly_fee'],
                ':due'   => $dueDate,
                ':by'    => $generatedBy,
            ]);
            if ($ins->rowCount() > 0) $generated++;
            else $skipped++;
        }

        return [
            'billing_month'  => $billingMonth,
            'bills_generated' => $generated,
            'bills_skipped'  => $skipped,
            'total_subscriptions' => count($subscriptions),
        ];
    }

    /**
     * Get monthly bills with optional filters.
     */
    public function getBills(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['billing_month'])) {
            $where[] = 'tmb.billing_month = :bm';
            $params[':bm'] = $filters['billing_month'];
        }
        if (!empty($filters['student_id'])) {
            $where[] = 'tmb.student_id = :sid';
            $params[':sid'] = (int)$filters['student_id'];
        }
        if (!empty($filters['route_id'])) {
            $where[] = 'tmb.route_id = :rid';
            $params[':rid'] = (int)$filters['route_id'];
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'tmb.payment_status = :ps';
            $params[':ps'] = $filters['payment_status'];
        }

        $limit  = min((int)($filters['limit'] ?? 50), 200);
        $offset = ((int)($filters['page'] ?? 1) - 1) * $limit;

        $stmt = $this->db->prepare("
            SELECT tmb.*, s.first_name, s.last_name, s.admission_no,
                   tr.name AS route_name, tr.code AS route_code
            FROM transport_monthly_bills tmb
            JOIN students s ON s.id = tmb.student_id
            JOIN transport_routes tr ON tr.id = tmb.route_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY tmb.billing_month DESC, s.last_name ASC
            LIMIT :lim OFFSET :off
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get monthly billing summary totals.
     */
    public function getMonthlyBillingSummary(string $billingMonth): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS total_bills,
                   SUM(amount_due) AS total_due,
                   SUM(amount_paid) AS total_paid,
                   SUM(balance) AS total_outstanding,
                   SUM(CASE WHEN payment_status='paid' THEN 1 ELSE 0 END) AS paid_count,
                   SUM(CASE WHEN payment_status='pending' THEN 1 ELSE 0 END) AS pending_count
            FROM transport_monthly_bills
            WHERE billing_month = :bm
        ");
        $stmt->execute([':bm' => $billingMonth]);
        $summary = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Per-route breakdown
        $stmt2 = $this->db->prepare("
            SELECT tr.name AS route_name, COUNT(*) AS bills, SUM(tmb.amount_due) AS total_due,
                   SUM(tmb.amount_paid) AS total_paid, SUM(tmb.balance) AS outstanding
            FROM transport_monthly_bills tmb
            JOIN transport_routes tr ON tr.id = tmb.route_id
            WHERE tmb.billing_month = :bm
            GROUP BY tmb.route_id, tr.name
        ");
        $stmt2->execute([':bm' => $billingMonth]);

        return [
            'billing_month' => $billingMonth,
            'summary'       => $summary,
            'by_route'      => $stmt2->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }

    /**
     * Record a payment against a transport bill.
     */
    public function recordTransportPayment(int $billId, array $data): array
    {
        $amountPaid   = (float)($data['amount_paid'] ?? 0);
        $method       = $data['payment_method'] ?? 'cash';
        $receivedBy   = $data['received_by'] ?? null;
        $referenceNo  = $data['reference_no'] ?? null;
        $notes        = $data['notes'] ?? null;

        if ($amountPaid <= 0) throw new \InvalidArgumentException('amount_paid must be positive');

        // Get bill
        $stmt = $this->db->prepare("SELECT * FROM transport_monthly_bills WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $billId]);
        $bill = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$bill) throw new Exception("Bill {$billId} not found");

        $newPaid    = (float)$bill['amount_paid'] + $amountPaid;
        $newBalance = (float)$bill['amount_due']  - $newPaid;
        $newStatus  = $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending');

        $upd = $this->db->prepare(
            "UPDATE transport_monthly_bills SET amount_paid=:ap, payment_status=:ps, updated_at=NOW() WHERE id=:id"
        );
        $upd->execute([':ap' => $newPaid, ':ps' => $newStatus, ':id' => $billId]);

        // Insert into payment_transactions for reconciliation trail
        try {
            $pt = $this->db->prepare("
                INSERT INTO payment_transactions
                  (student_id, academic_year, payment_method, amount_paid, payment_date,
                   reference_no, received_by, status, transport_bill_id, notes)
                VALUES (:sid, :yr, :meth, :amt, NOW(), :ref, :by, 'confirmed', :bid, :notes)
            ");
            $pt->execute([
                ':sid'   => $bill['student_id'],
                ':yr'    => (int)date('Y', strtotime($bill['billing_month'])),
                ':meth'  => $method,
                ':amt'   => $amountPaid,
                ':ref'   => $referenceNo,
                ':by'    => $receivedBy,
                ':bid'   => $billId,
                ':notes' => $notes,
            ]);
        } catch (Exception $e) {
            // payment_transactions may not have transport_bill_id yet — log and continue
            error_log("TransportBilling: could not insert payment_transaction: " . $e->getMessage());
        }

        return [
            'bill_id'        => $billId,
            'amount_paid'    => $newPaid,
            'balance'        => max(0, $newBalance),
            'payment_status' => $newStatus,
        ];
    }

    /**
     * Get subscriptions with optional filters.
     */
    public function getSubscriptions(array $filters = []): array
    {
        $where  = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'ts.student_id=:sid'; $params[':sid'] = (int)$filters['student_id']; }
        if (!empty($filters['route_id']))   { $where[] = 'ts.route_id=:rid';   $params[':rid'] = (int)$filters['route_id']; }
        if (!empty($filters['status']))     { $where[] = 'ts.status=:st';      $params[':st']  = $filters['status']; }

        $stmt = $this->db->prepare("
            SELECT ts.*, s.first_name, s.last_name, s.admission_no,
                   tr.name AS route_name, tr.code AS route_code
            FROM transport_subscriptions ts
            JOIN students s ON s.id = ts.student_id
            JOIN transport_routes tr ON tr.id = ts.route_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.last_name, s.first_name
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function generateBillForSubscription(int $subId, string $billingMonth): void
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_subscriptions WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $subId]);
        $sub = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$sub) return;

        $dueDate = date('Y-m-t', strtotime($billingMonth));
        $ins = $this->db->prepare("
            INSERT IGNORE INTO transport_monthly_bills
              (student_id, subscription_id, route_id, billing_month, amount_due, payment_status, due_date)
            VALUES (:sid, :subid, :rid, :bm, :amt, 'pending', :due)
        ");
        $ins->execute([
            ':sid' => $sub['student_id'], ':subid' => $subId,
            ':rid' => $sub['route_id'],   ':bm' => $billingMonth,
            ':amt' => $sub['monthly_fee'], ':due' => $dueDate,
        ]);
    }

    private function getSubscriptionId(int $studentId, int $routeId, string $startMonth): int
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM transport_subscriptions WHERE student_id=:s AND route_id=:r AND start_month=:m LIMIT 1"
        );
        $stmt->execute([':s' => $studentId, ':r' => $routeId, ':m' => $startMonth]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}
