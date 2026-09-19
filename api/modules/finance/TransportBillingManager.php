<?php
declare(strict_types=1);

namespace App\API\Modules\finance;

use App\Database\Database;
use App\API\Modules\transport\StudentTransportEntitlementManager;
use App\API\Services\ServiceContractBroker;
use Exception;

/**
 * TransportBillingManager
 * Manages monthly transport subscription billing, separate from school fees.
 */
class TransportBillingManager
{
    private \PDO $db;
    private StudentTransportEntitlementManager $entitlements;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->entitlements = ServiceContractBroker::contract('App\API\Modules\transport\StudentTransportEntitlementManager', [], $this->db);
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
            INSERT INTO student_transport_assignments
              (student_id, route_id, month, year, expected_amount, assignment_date, status, assigned_by, notes)
            VALUES (:sid, :rid, :m, :y, :fee, :sm, 'active', :by, :notes)
            ON DUPLICATE KEY UPDATE
              status='active', expected_amount=:fee2, notes=:notes2, updated_at=NOW()
        ");
        $stmt->execute([
            ':sid' => $studentId, ':rid' => $routeId,
            ':m' => (int)date('n', strtotime($startMonth)),
            ':y' => (int)date('Y', strtotime($startMonth)),
            ':fee' => $monthlyFee, ':sm' => $startMonth,
            ':by' => $subscribedBy, ':notes' => $notes,
            ':fee2' => $monthlyFee, ':notes2' => $notes,
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
            "UPDATE student_transport_assignments SET status='withdrawn' WHERE id=:id"
        );
        $stmt->execute([':id' => $subscriptionId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Generate monthly bills for all active subscriptions for a given billing month.
     * @param string $billingMonth 'YYYY-MM-01' format
     */
    public function generateMonthlyBills(string $billingMonth, ?int $generatedBy): array
    {
        // Get all active transport assignments for the billing month
        $billingMonthNum = (int)date('n', strtotime($billingMonth));
        $billingYear = (int)date('Y', strtotime($billingMonth));
        $stmt = $this->db->prepare("
            SELECT sta.*, tr.name AS route_name, tr.fee AS route_fee
            FROM student_transport_assignments sta
            JOIN transport_routes tr ON tr.id = sta.route_id
            WHERE sta.status = 'active'
              AND sta.month <= :bm
              AND sta.year = :by
        ");
        $stmt->execute([':bm' => $billingMonthNum, ':by' => $billingYear]);
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
            $amount = (float)($sub['expected_amount'] ?? 0) ?: (float)($sub['route_fee'] ?? 0);
            $ins->execute([
                ':sid'   => $sub['student_id'],
                ':subid' => $sub['id'],
                ':rid'   => $sub['route_id'],
                ':bm'    => $billingMonth,
                ':amt'   => $amount,
                ':due'   => $dueDate,
                ':by'    => $generatedBy,
            ]);
            if ($ins->rowCount() > 0) $generated++;
            else $skipped++;
            $this->entitlements->ensureMonthlyEntitlement(
                (int)$sub['student_id'], (int)$sub['id'], (int)$sub['route_id'],
                $billingMonth, $amount, $generatedBy ? (int)$generatedBy : null
            );
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
            SELECT tmb.*, p.first_name, p.last_name, s.admission_no,
                   tr.name AS route_name, tr.code AS route_code,
                   COALESCE(tbp_sum.amount_paid, 0) AS amount_paid,
                   tmb.amount_due - COALESCE(tbp_sum.amount_paid, 0) AS balance
            FROM transport_monthly_bills tmb
            JOIN students s ON s.id = tmb.student_id
            JOIN persons p ON p.id = s.person_id
            JOIN transport_routes tr ON tr.id = tmb.route_id
            LEFT JOIN (
                SELECT bill_id, SUM(amount) AS amount_paid
                FROM transport_bill_payments
                GROUP BY bill_id
            ) tbp_sum ON tbp_sum.bill_id = tmb.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY tmb.billing_month DESC, p.last_name ASC
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
                   COALESCE(SUM(tmb.amount_due), 0) AS total_due,
                   COALESCE(SUM(COALESCE(tbp_sum.amount_paid, 0)), 0) AS total_paid,
                   COALESCE(SUM(tmb.amount_due - COALESCE(tbp_sum.amount_paid, 0)), 0) AS total_outstanding,
                   COALESCE(SUM(CASE WHEN tmb.payment_status='paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                   COALESCE(SUM(CASE WHEN tmb.payment_status='pending' THEN 1 ELSE 0 END), 0) AS pending_count
            FROM transport_monthly_bills tmb
            LEFT JOIN (
                SELECT bill_id, SUM(amount) AS amount_paid
                FROM transport_bill_payments
                GROUP BY bill_id
            ) tbp_sum ON tbp_sum.bill_id = tmb.id
            WHERE tmb.billing_month = :bm
        ");
        $stmt->execute([':bm' => $billingMonth]);
        $summary = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Per-route breakdown
        $stmt2 = $this->db->prepare("
            SELECT tr.name AS route_name, COUNT(*) AS bills, SUM(tmb.amount_due) AS total_due,
                   SUM(COALESCE(tbp_sum.amount_paid, 0)) AS total_paid,
                   SUM(tmb.amount_due - COALESCE(tbp_sum.amount_paid, 0)) AS outstanding
            FROM transport_monthly_bills tmb
            JOIN transport_routes tr ON tr.id = tmb.route_id
            LEFT JOIN (
                SELECT bill_id, SUM(amount) AS amount_paid
                FROM transport_bill_payments
                GROUP BY bill_id
            ) tbp_sum ON tbp_sum.bill_id = tmb.id
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

        // Derive current paid from transport_bill_payments
        $paidStmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM transport_bill_payments WHERE bill_id = :id");
        $paidStmt->execute([':id' => $billId]);
        $currentPaid = (float)$paidStmt->fetchColumn();

        $newPaid    = $currentPaid + $amountPaid;
        $newBalance = (float)$bill['amount_due'] - $newPaid;
        $newStatus  = $newBalance <= 0 ? 'paid' : ($newPaid > 0 ? 'partial' : 'pending');

        $upd = $this->db->prepare(
            "UPDATE transport_monthly_bills SET payment_status=:ps, updated_at=NOW() WHERE id=:id"
        );
        $upd->execute([':ps' => $newStatus, ':id' => $billId]);

        // Insert into transport_bill_payments for reconciliation trail
        try {
            $pt = $this->db->prepare("
                INSERT INTO transport_bill_payments
                  (bill_id, amount, payment_method, transaction_id, received_by, payment_date, notes)
                VALUES (:bid, :amt, :meth, :ref, :by, :pdate, :notes)
            ");
            $pt->execute([
                ':bid'   => $billId,
                ':amt'   => $amountPaid,
                ':meth'  => $method,
                ':ref'   => $referenceNo,
                ':by'    => $receivedBy,
                ':pdate' => date('Y-m-d'),
                ':notes' => $notes,
            ]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("TransportBilling: could not insert bill_payment: " . $e->getMessage());
        }

        // Mirror bank/cheque transport payments into bank_transactions so they
        // surface on the bank transactions screen. status stays 'pending' so
        // trg_bank_payment_processed never fires for these rows.
        $bankMethods = ['bank', 'bank_transfer', 'cheque'];
        if (in_array(strtolower((string)$method), $bankMethods, true) && !empty($bill['student_id'])) {
            try {
                $bt = $this->db->prepare("
                    INSERT INTO bank_transactions
                      (student_id, transaction_ref, amount, transaction_date, narration, source_type, status, reconciled, created_at)
                    VALUES (?, ?, ?, NOW(), ?, 'manual_entry', 'pending', 0, NOW())
                ");
                $bt->execute([
                    $bill['student_id'],
                    $referenceNo ?: 'TRN-' . date('YmdHis'),
                    $amountPaid,
                    'Transport payment' . ($referenceNo ? " ({$referenceNo})" : ''),
                ]);
            } catch (Exception $e) {
                \App\API\Services\Logger::legacyError("TransportBilling: could not mirror bank_transaction: " . $e->getMessage());
            }
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
            SELECT sta.*, p.first_name, p.last_name, s.admission_no,
                   tr.name AS route_name, tr.code AS route_code
            FROM student_transport_assignments sta
            JOIN students s ON s.id = sta.student_id
            JOIN persons p ON p.id = s.person_id
            JOIN transport_routes tr ON tr.id = sta.route_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.last_name, p.first_name
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
        $stmt = $this->db->prepare("SELECT * FROM student_transport_assignments WHERE id=:id LIMIT 1");
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
            ':amt' => $sub['expected_amount'] ?? 0, ':due' => $dueDate,
        ]);
        $this->entitlements->ensureMonthlyEntitlement(
            (int)$sub['student_id'], (int)$sub['id'], (int)$sub['route_id'],
            $billingMonth, (float)($sub['expected_amount'] ?? 0), null
        );
    }

    private function getSubscriptionId(int $studentId, int $routeId, string $startMonth): int
    {
        $month = (int)date('n', strtotime($startMonth));
        $year = (int)date('Y', strtotime($startMonth));
        $stmt = $this->db->prepare(
            "SELECT id FROM student_transport_assignments WHERE student_id=:s AND route_id=:r AND month=:m AND year=:y LIMIT 1"
        );
        $stmt->execute([':s' => $studentId, ':r' => $routeId, ':m' => $month, ':y' => $year]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}
