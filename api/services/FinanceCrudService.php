<?php
namespace App\API\Services;

use Exception;
use PDO;

/**
 * FinanceCrudService — extracted from FinanceController lines 1490-2246.
 * Encapsulates raw SQL for expenses, petty cash, budgets, adjustments,
 * waivers, credit notes, salary advances, and payment matching.
 */
final class FinanceCrudService
{
    private PDO $db;

    /** Trial balance rows (posted journals grouped by account). */
    public function trialBalance(): array
    {
        return $this->db->query("SELECT c.account_code,c.account_name,t.code AS account_type,
                ROUND(COALESCE(SUM(CASE WHEN j.status='posted' THEN l.debit_amount-l.credit_amount ELSE 0 END),0),2) AS balance
                FROM " . ReadReplicaService::qualifiedRef("chart_of_accounts") . " c JOIN accounting_account_types t ON t.id=c.account_type_id
                LEFT JOIN accounting_journal_lines l ON l.chart_account_id=c.id LEFT JOIN accounting_journal_batches j ON j.id=l.journal_batch_id
                WHERE t.code IN ('asset','liability','equity') GROUP BY c.id,c.account_code,c.account_name,t.code ORDER BY c.account_code")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** Ledger report for a group ('balance'|'cashflow'|'performance'). */
    public function accountingReport(string $type): array
    {
        $where = $type === 'balance' ? "t.code IN ('asset','liability','equity')" : ($type === 'cashflow' ? "t.code='asset' AND c.account_code LIKE '110%'" : "t.code IN ('revenue','expense')");
        $sql = "SELECT c.account_code,c.account_name,t.code AS account_type,
                ROUND(COALESCE(SUM(CASE WHEN j.status='posted' THEN l.debit_amount-l.credit_amount ELSE 0 END),0),2) AS balance
                FROM " . ReadReplicaService::qualifiedRef("chart_of_accounts") . " c JOIN accounting_account_types t ON t.id=c.account_type_id
                LEFT JOIN accounting_journal_lines l ON l.chart_account_id=c.id LEFT JOIN accounting_journal_batches j ON j.id=l.journal_batch_id
                WHERE {$where} GROUP BY c.id,c.account_code,c.account_name,t.code ORDER BY c.account_code";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /** School financial accounts directory (account + routes + purposes). */
    public function financialAccountsDetail(): array
    {
        return $this->db->query("SELECT a.*,k.code account_kind,p.code provider_code,c.account_code ledger_code,
                sa.account_name settlement_account_name, sa.account_identifier settlement_account_identifier,
                GROUP_CONCAT(DISTINCT r.collection_product ORDER BY r.collection_product SEPARATOR ',') collection_products,
                GROUP_CONCAT(DISTINCT r.reference_policy ORDER BY r.reference_policy SEPARATOR ',') reference_policies,
                GROUP_CONCAT(DISTINCT fp.code ORDER BY fp.code SEPARATOR ',') purposes
                FROM " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " a
                JOIN financial_account_kinds k ON k.id=a.account_kind_id
                LEFT JOIN payment_providers p ON p.id=a.provider_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("chart_of_accounts") . " c ON c.id=a.ledger_account_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " sa ON sa.id=a.settlement_financial_account_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("payment_collection_routes") . " r ON r.financial_account_id=a.id AND r.active=1
                LEFT JOIN school_financial_account_purposes ap ON ap.financial_account_id=a.id
                LEFT JOIN financial_account_purposes fp ON fp.id=ap.purpose_id
                GROUP BY a.id ORDER BY a.account_name")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ==================== EXPENSES ====================

    public function createExpense(array $d, int $userId): array
    {
        $expNo = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $this->db->prepare(
            "INSERT INTO expenses (expense_number, category_id, description, amount, expense_date,
                payment_method, reference_number, vendor_id, receipt_number,
                budget_line_item_id, department_id, academic_year, term, notes, attachment_path,
                status, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,NOW())"
        )->execute([
            $expNo, $d['category_id'] ?? null, $d['description'], $d['amount'], $d['expense_date'],
            $d['payment_method'] ?? 'cash', $d['reference_number'] ?? null, $d['vendor_id'] ?? null,
            $d['receipt_number'] ?? null, $d['budget_line_item_id'] ?? null,
            $d['department_id'] ?? null, $d['academic_year'] ?? date('Y'), $d['term'] ?? null,
            $d['notes'] ?? null, $d['attachment_path'] ?? null, $userId,
        ]);
        return ['id' => $this->db->lastInsertId(), 'expense_number' => $expNo];
    }

    public function updateExpense(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        $allowed = ['category_id','description','amount','expense_date','payment_method',
                    'reference_number','vendor_id','receipt_number',
                    'budget_line_item_id','department_id','academic_year','term','notes'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $params[] = $data[$f]; }
        }
        $fields[] = 'updated_at=NOW()';
        $params[] = $id;
        $this->db->prepare("UPDATE expenses SET " . implode(',', $fields) . " WHERE id=?")->execute($params);
    }

    public function setExpenseStatus(int $id, string $status): void
    {
        $this->db->prepare("UPDATE expenses SET status=?, updated_at=NOW() WHERE id=?")->execute([$status, $id]);
    }

    public function softDeleteExpense(int $id): void
    {
        $this->db->prepare("UPDATE expenses SET deleted_at=NOW() WHERE id=?")->execute([$id]);
    }

    public function listExpenseCategories(): array
    {
        return $this->db->query("SELECT * FROM expense_categories WHERE status='active' ORDER BY type, name")->fetchAll() ?: [];
    }

    // ==================== PETTY CASH ====================

    public function getPettyCashFund(int $fundId): ?array
    {
        $r = $this->db->prepare(
            "SELECT f.*,
                    f.opening_balance
                        + COALESCE((SELECT SUM(t.amount) FROM petty_cash_transactions t
                                    WHERE t.fund_id = f.id AND t.type = 'top_up'), 0)
                        - COALESCE((SELECT SUM(t.amount) FROM petty_cash_transactions t
                                    WHERE t.fund_id = f.id AND t.type = 'expense'), 0)
                        AS current_balance
             FROM petty_cash_funds f WHERE f.id=?"
        );
        $r->execute([$fundId]);
        return $r->fetch() ?: null;
    }

    public function listPettyCashTransactions(int $fundId, array $filters): array
    {
        $where = ['fund_id = ?'];
        $params = [$fundId];
        if (!empty($filters['type']))        { $where[] = 'type=?';              $params[] = $filters['type']; }
        if (!empty($filters['date_from']))   { $where[] = 'transaction_date>=?'; $params[] = $filters['date_from']; }
        if (!empty($filters['date_to']))     { $where[] = 'transaction_date<=?'; $params[] = $filters['date_to']; }
        if (!empty($filters['category_id'])) { $where[] = 'category_id=?';       $params[] = $filters['category_id']; }

        $txns = $this->db->prepare(
            "SELECT t.*, ec.name AS category_name, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS recorded_by_name
             FROM petty_cash_transactions t
             LEFT JOIN expense_categories ec ON ec.id = t.category_id
             LEFT JOIN users u ON u.id = t.recorded_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             WHERE " . implode(' AND ', $where) . " ORDER BY transaction_date DESC, id DESC LIMIT 200"
        );
        $txns->execute($params);

        $stats = $this->db->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type='expense' AND MONTH(transaction_date)=MONTH(CURDATE()) THEN amount END),0) AS expenses_this_month,
                    COALESCE(SUM(CASE WHEN type='top_up' AND MONTH(transaction_date)=MONTH(CURDATE()) THEN amount END),0) AS topups_this_month
             FROM petty_cash_transactions WHERE fund_id=?"
        );
        $stats->execute([$fundId]);

        return ['transactions' => $txns->fetchAll() ?: [], 'stats' => $stats->fetch() ?: []];
    }

    public function createPettyCashTransaction(array $d, int $fundId, int $userId): float
    {
        $fund = $this->getPettyCashFund($fundId);
        $balanceAfter = ($d['type'] === 'expense')
            ? $fund['current_balance'] - $d['amount']
            : $fund['current_balance'] + $d['amount'];

        $this->db->prepare(
            "INSERT INTO petty_cash_transactions (fund_id,type,category_id,description,amount,balance_after,
              transaction_date,receipt_number,vendor_id,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        )->execute([
            $fundId, $d['type'], $d['category_id'] ?? null, $d['description'],
            $d['amount'], $balanceAfter,
            $d['transaction_date'] ?? date('Y-m-d'),
            $d['receipt_number'] ?? null, $d['vendor_id'] ?? null,
            $d['notes'] ?? null, $userId
        ]);
        return $balanceAfter;
    }

    // ==================== CASH RECONCILIATION ====================

    public function getCashReconciliationByDate(string $date): ?array
    {
        $r = $this->db->prepare(
            "SELECT s.*, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS cashier_name,
                    COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), a.username) AS approved_by_name
             FROM " . ReadReplicaService::qualifiedRef("cash_reconciliation_sessions") . "
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             LEFT JOIN users a ON a.id = s.approved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " ap ON ap.person_id = a.person_id
             WHERE s.reconciliation_date=?"
        );
        $r->execute([$date]);
        return $r->fetch() ?: null;
    }

    public function getCashReconciliationById(int $id): ?array
    {
        $r = $this->db->prepare(
            "SELECT s.*, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS cashier_name,
                    COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), a.username) AS approved_by_name
             FROM " . ReadReplicaService::qualifiedRef("cash_reconciliation_sessions") . "
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             LEFT JOIN users a ON a.id = s.approved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " ap ON ap.person_id = a.person_id
             WHERE s.id=?"
        );
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    public function listCashReconciliationSessions(): array
    {
        return $this->db->query(
            "SELECT s.*, COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS cashier_name
             FROM " . ReadReplicaService::qualifiedRef("cash_reconciliation_sessions") . "
             LEFT JOIN users u ON u.id = s.cashier_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             ORDER BY s.reconciliation_date DESC LIMIT 60"
        )->fetchAll() ?: [];
    }

    public function upsertCashReconciliation(array $d, int $userId): array
    {
        $r = $this->db->prepare("SELECT id FROM cash_reconciliation_sessions WHERE reconciliation_date=? AND cashier_id=?");
        $r->execute([$d['reconciliation_date'], $userId]);
        $existing = $r->fetch();

        if ($existing) {
            $this->db->prepare(
                "UPDATE cash_reconciliation_sessions SET physical_cash_count=?, variance_reason=?, notes=?, status='draft' WHERE id=?"
            )->execute([$d['physical_cash_count'], $d['variance_reason'] ?? null, $d['notes'] ?? null, $existing['id']]);
            return ['id' => $existing['id']];
        }

        $this->db->prepare(
            "INSERT INTO cash_reconciliation_sessions (reconciliation_date,system_cash_total,physical_cash_count,variance_reason,cashier_id,notes,status)
             VALUES (?,?,?,?,?,?,'draft')"
        )->execute([$d['reconciliation_date'], $d['system_cash_total'], $d['physical_cash_count'], $d['variance_reason'] ?? null, $userId, $d['notes'] ?? null]);
        return ['id' => $this->db->lastInsertId()];
    }

    // ==================== ADJUSTMENTS ====================

    public function listAdjustments(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'fcn.student_id=?'; $params[] = $filters['student_id']; }

        $rows = $this->db->prepare(
            "SELECT fcn.id, fcn.credit_number AS reference, fcn.credit_number AS adjustment_number,
                    fcn.credit_reason AS type, fcn.credit_reason AS adjustment_type,
                    fcn.credit_amount AS amount, fcn.notes, fcn.academic_year, fcn.term_id,
                    fcn.applied_amount, fcn.remaining_amount, fcn.applied_at, fcn.expiry_date,
                    fcn.status AS credit_status, fcn.created_at, fcn.updated_at,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), 'General Ledger') AS student_name,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS requested_by,
                    COALESCE(CONCAT(ap.first_name,' ',ap.last_name), a.username) AS approved_by
             FROM " . ReadReplicaService::qualifiedRef("fee_credit_notes") . " fcn
             LEFT JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = fcn.student_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN users u ON u.id = fcn.created_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             LEFT JOIN users a ON a.id = fcn.approved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " ap ON ap.person_id = a.person_id
             WHERE " . implode(' AND ', $where) . " ORDER BY fcn.created_at DESC LIMIT 200"
        );
        $rows->execute($params);

        $items = array_map([$this, 'decodeAdjustmentStatus'], $rows->fetchAll() ?: []);

        if (!empty($filters['status'])) {
            $items = array_values(array_filter($items, function ($r) use ($filters) {
                return strcasecmp($r['status'], $filters['status']) === 0;
            }));
        }

        $pending  = array_values(array_filter($items, fn($r) => $r['status'] === 'pending'));
        $approved = array_values(array_filter($items, fn($r) => $r['status'] === 'approved'));
        $rejected = array_values(array_filter($items, fn($r) => $r['status'] === 'rejected'));
        $applied  = array_values(array_filter($items, fn($r) => $r['status'] === 'applied'));

        $stats = [
            'pending_count'   => count($pending),
            'pending_amount'  => (float) array_sum(array_column($pending, 'amount')),
            'approved_this_month' => count(array_filter($approved, function ($r) {
                return isset($r['updated_at']) && substr($r['updated_at'], 0, 7) === date('Y-m');
            })),
            'total_applied'   => (float) array_sum(array_column($applied, 'amount')),
            'rejected_count'  => count($rejected),
        ];

        return ['adjustments' => $items, 'stats' => $stats];
    }

    public function createAdjustment(array $d, int $userId): array
    {
        $adjNo = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $reason = trim((string)($d['reason'] ?? ''));
        $this->db->prepare(
            "INSERT INTO fee_credit_notes (credit_number, student_id, academic_year, term_id,
              source_transaction_id, credit_amount, credit_reason, expiry_date, notes, created_by)
             VALUES (?,?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL 2 YEAR),?,?)"
        )->execute([
            $adjNo,
            !empty($d['student_id']) ? (int) $d['student_id'] : 0,
            $d['academic_year'] ?? date('Y'),
            $d['term'] ?? $d['term_id'] ?? null,
            $d['reference_payment_id'] ?? null,
            abs((float) ($d['amount'] ?? 0)),
            $this->mapAdjustmentTypeToCreditReason($d['adjustment_type'] ?? $d['type'] ?? 'correction'),
            '[status:pending] ' . $reason,
            $userId
        ]);
        return ['id' => $this->db->lastInsertId(), 'adjustment_number' => $adjNo];
    }

    public function setAdjustmentStatus(int $id, string $status, int $userId, ?string $rejectionReason = null): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) return;

        $current = $this->db->prepare("SELECT notes FROM fee_credit_notes WHERE id=?");
        $current->execute([$id]);
        $row = $current->fetch();
        if (!$row) return;

        $notes = $this->stripAdjustmentMarker((string) ($row['notes'] ?? ''));
        if ($status === 'approved') {
            $this->db->prepare(
                "UPDATE fee_credit_notes SET status='available', approved_by=?, notes=?, updated_at=NOW() WHERE id=?"
            )->execute([$userId, '[status:approved] ' . $notes, $id]);
        } else {
            $notes = ($rejectionReason !== null && $rejectionReason !== '') ? $rejectionReason : $notes;
            $this->db->prepare(
                "UPDATE fee_credit_notes SET status='cancelled', notes=?, updated_at=NOW() WHERE id=?"
            )->execute(['[status:rejected] ' . $notes, $id]);
        }
    }

    private function mapAdjustmentTypeToCreditReason(?string $type): string
    {
        $map = [
            'fee_waiver'         => 'waiver_excess',
            'discount'           => 'fee_reduction',
            'correction'         => 'error_correction',
            'overpayment_refund' => 'overpayment',
            'write_off'          => 'fee_reduction',
            'refund'             => 'refund',
            'fee_reduction'      => 'fee_reduction',
            'sponsorship_adjustment' => 'sponsorship_adjustment',
            'error_correction'   => 'error_correction',
            'waiver_excess'      => 'waiver_excess',
            'overpayment'        => 'overpayment',
        ];
        return $map[strtolower((string) $type)] ?? 'fee_reduction';
    }

    private function decodeAdjustmentStatus(array $row): array
    {
        $notes = (string) ($row['notes'] ?? '');
        if (preg_match('/^\[status:(pending|approved|rejected|applied)\](.*)$/s', $notes, $m)) {
            $row['status'] = $m[1];
            $row['reason'] = trim($m[2]);
            $row['notes']  = trim($m[2]);
        } elseif (in_array($row['credit_status'] ?? null, ['partially_applied', 'fully_applied'], true) || !empty($row['applied_at'])) {
            $row['status'] = 'applied';
            $row['reason'] = $notes;
        } elseif (!empty($row['approved_by'])) {
            $row['status'] = 'approved';
            $row['reason'] = $notes;
        } else {
            $row['status'] = 'pending';
            $row['reason'] = $notes;
        }
        return $row;
    }

    private function stripAdjustmentMarker(string $notes): string
    {
        return trim(preg_replace('/^\[status:(pending|approved|rejected|applied)\]\s*/', '', $notes) ?? $notes);
    }

    // ==================== EXCEPTION REPORTS ====================

    public function listExceptionReports(array $filters): array
    {
        $rows = $this->db->prepare(
            "SELECT fcn.id, fcn.credit_number AS reference, fcn.credit_reason AS exception_type,
                    fcn.credit_amount AS amount, fcn.notes, fcn.credit_reason AS type,
                    fcn.created_at AS detected_at, fcn.updated_at,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), 'General Ledger') AS affected_party,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS resolved_by_name
             FROM " . ReadReplicaService::qualifiedRef("fee_credit_notes") . " fcn
             LEFT JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = fcn.student_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN users u ON u.id = fcn.approved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             WHERE fcn.notes LIKE '[status:pending]%' OR fcn.notes LIKE '[status:rejected]%'
             ORDER BY fcn.created_at DESC LIMIT 200"
        );
        $rows->execute();

        $exceptions = [];
        foreach ($rows->fetchAll() ?: [] as $row) {
            $notes = (string) ($row['notes'] ?? '');
            $pending = strpos($notes, '[status:pending]') === 0;
            $amount = (float) $row['amount'];
            $exceptions[] = [
                'id'            => $row['id'],
                'reference'     => $row['reference'],
                'exception_type'=> ucwords(str_replace('_', ' ', $row['exception_type'])) . ' Adjustment',
                'type'          => $row['type'],
                'severity'      => $pending ? ($amount >= 50000 ? 'high' : 'medium') : 'low',
                'description'   => $this->stripAdjustmentMarker($notes),
                'amount'        => $amount,
                'affected_party'=> $row['affected_party'],
                'detected_at'   => $row['detected_at'],
                'status'        => $pending ? 'open' : 'dismissed',
                'resolved_by_name' => $row['resolved_by_name'],
            ];
        }

        if (!empty($filters['status'])) {
            $exceptions = array_values(array_filter($exceptions, function ($e) use ($filters) {
                return strcasecmp($e['status'], $filters['status']) === 0;
            }));
        }

        $stats = [
            'total'         => count($exceptions),
            'open_count'    => count(array_filter($exceptions, fn($e) => $e['status'] === 'open')),
            'critical_count'=> count(array_filter($exceptions, fn($e) => $e['severity'] === 'critical')),
            'high_count'    => count(array_filter($exceptions, fn($e) => $e['severity'] === 'high')),
        ];

        return ['exceptions' => $exceptions, 'stats' => $stats];
    }

    public function updateExceptionStatus(int $id, string $status, int $userId, ?string $notes = null): void
    {
        $status = strtolower($status);
        if ($status === 'resolved') {
            $this->setAdjustmentStatus($id, 'approved', $userId);
        } elseif ($status === 'dismissed') {
            $this->setAdjustmentStatus($id, 'rejected', $userId, $notes);
        }
    }

    // ==================== BUDGETS ====================

    public function listBudgets(): array
    {
        return $this->db->query(
            "SELECT v.budget_id AS id, v.budget_name AS name, v.academic_year, v.term,
                    v.total_amount, v.budget_status AS status,
                    COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS created_by_name,
                    v.total_spent, v.total_allocated, v.total_committed, v.utilization_pct
             FROM " . ReadReplicaService::qualifiedRef('budget_utilization') . " v
             LEFT JOIN " . ReadReplicaService::qualifiedRef("budgets") . " b ON b.id = v.budget_id
             LEFT JOIN users u ON u.id = b.created_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             ORDER BY v.academic_year DESC, v.term"
        )->fetchAll() ?: [];
    }

    public function getBudget(int $id): ?array
    {
        $budget = $this->db->prepare("SELECT * FROM budgets WHERE id=?");
        $budget->execute([$id]);
        $b = $budget->fetch();
        if (!$b) return null;

        $lines = $this->db->prepare(
            "SELECT bl.*, ec.name AS category_name FROM " . ReadReplicaService::qualifiedRef("budget_line_items") . "
             LEFT JOIN expense_categories ec ON ec.id = bl.category_id WHERE bl.budget_id=?"
        );
        $lines->execute([$id]);
        return ['budget' => $b, 'line_items' => $lines->fetchAll() ?: []];
    }

    public function createBudget(array $d, int $userId): int
    {
        $this->db->prepare(
            "INSERT INTO budgets (name, academic_year, term, total_amount, description, status, created_by)
             VALUES (?,?,?,?,?,'draft',?)"
        )->execute([$d['name'], $d['academic_year'], $d['term'] ?? null, $d['total_amount'] ?? 0, $d['description'] ?? null, $userId]);
        $budgetId = $this->db->lastInsertId();

        if (!empty($d['line_items']) && is_array($d['line_items'])) {
            $li = $this->db->prepare("INSERT INTO budget_line_items (budget_id, category_id, description, allocated_amount) VALUES (?,?,?,?)");
            foreach ($d['line_items'] as $item) {
                $li->execute([$budgetId, $item['category_id'] ?? null, $item['description'] ?? null, $item['allocated_amount'] ?? 0]);
            }
        }
        return $budgetId;
    }

    public function updateBudgetStatus(int $id, string $status, int $userId): void
    {
        $extra = '';
        $extraParams = [];
        if ($status === 'submitted') { $extra = ', submitted_by=?, submitted_at=NOW()'; $extraParams = [$userId]; }
        if ($status === 'approved')  { $extra = ', approved_by=?, approved_at=NOW()';   $extraParams = [$userId]; }
        if ($status === 'active')    { $extra = ', activated_at=NOW()'; }
        $this->db->prepare("UPDATE budgets SET status=?$extra, updated_at=NOW() WHERE id=?")->execute(array_merge([$status], $extraParams, [$id]));
    }

    public function updateBudget(int $id, array $d): void
    {
        $this->db->prepare("UPDATE budgets SET name=?, total_amount=?, description=?, updated_at=NOW() WHERE id=?")
            ->execute([$d['name'] ?? '', $d['total_amount'] ?? 0, $d['description'] ?? null, $id]);
    }

    // ==================== FEE WAIVERS ====================

    public function listFeeWaivers(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id']))    { $where[] = 'fdw.student_id=?';    $params[] = $filters['student_id']; }
        if (!empty($filters['status']))        { $where[] = 'fdw.status=?';        $params[] = $filters['status']; }
        if (!empty($filters['academic_year'])) { $where[] = 'fdw.academic_year=?'; $params[] = $filters['academic_year']; }

        $rows = $this->db->prepare(
            "SELECT fdw.*, COALESCE(CONCAT(sp.first_name,' ',sp.last_name), '—') AS student_name,
                    s.admission_no, c.name AS class_name,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS approved_by_name
             FROM fee_discounts_waivers fdw
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = fdw.student_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN student_academic_enrollments sae ON sae.student_id = fdw.student_id AND sae.enrollment_status = 'active'
             LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON c.id = ayc.class_id
             LEFT JOIN users u ON u.id = fdw.approved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             WHERE " . implode(' AND ', $where) . " ORDER BY fdw.created_at DESC"
        );
        $rows->execute($params);

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total, COUNT(CASE WHEN status='active' THEN 1 END) AS active_count,
                    COALESCE(SUM(CASE WHEN status='active' THEN discount_value END),0) AS total_waived
             FROM fee_discounts_waivers"
        )->fetch();

        return ['waivers' => $rows->fetchAll() ?: [], 'stats' => $stats ?: []];
    }

    public function createFeeWaiver(array $d, int $userId): int
    {
        $obligationId = !empty($d['obligation_id']) ? (int)$d['obligation_id'] : null;
        $studentId = (int)($d['student_id'] ?? 0);
        // The UI uses the academic-year code (for example 2026/2027), while
        // fee_discounts_waivers.academic_year is a MySQL YEAR(4) column.
        // Keep both representations explicit: year code for joins/lookups,
        // numeric opening year for persistence.
        $yearCode = (string)($d['academic_year'] ?? date('Y'));
        $yearValue = (int) preg_replace('/[^0-9].*$/', '', $yearCode);
        if ($yearValue < 1900 || $yearValue > 2200) $yearValue = (int) date('Y');
        $discountType = (string)($d['discount_type'] ?? '');
        $requestedAmount = (float)($d['discount_value'] ?? 0);
        if (!$studentId || !in_array($discountType, ['full_waiver', 'fixed_amount', 'percentage'], true) || $requestedAmount < 0) {
            throw new InvalidArgumentException('student_id, waiver type and a valid waiver value are required');
        }
        if ($discountType === 'percentage' && $requestedAmount > 100) {
            throw new InvalidArgumentException('Fee-waiver percentage must be between 0 and 100');
        }

        // A student balance spans multiple term obligations. Distribute a
        // full or fixed waiver across the outstanding term balances so the
        // selected learner is actually relieved correctly, rather than
        // attaching the whole amount to the first obligation only.
        if (!$obligationId && empty($d['term_id'])) {
            $terms = $this->db->prepare(
                "SELECT v.academic_year_term_id, v.term_id, v.balance,
                        (SELECT MIN(sfo.id) FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations") . "
                         JOIN student_academic_enrollments sae ON sae.id=sfo.student_academic_enrollment_id
                         WHERE sae.student_id=? AND sfo.academic_year_term_id=v.academic_year_term_id) AS obligation_id
                 FROM " . ReadReplicaService::qualifiedRef('student_fee_balances') . " v
                 WHERE v.student_id=? AND v.academic_year=? AND v.balance>0
                 ORDER BY v.academic_year_term_id"
            );
            $terms->execute([$studentId, $studentId, $yearCode]);
            $termRows = $terms->fetchAll() ?: [];
            if ($termRows) {
                $remaining = $requestedAmount;
                $this->db->beginTransaction();
                try {
                    $insert = $this->db->prepare(
                        "INSERT INTO fee_discounts_waivers
                         (student_id, student_fee_obligation_id, discount_type, discount_value,
                          discount_percentage, reason, academic_year, term_id, approved_by, approved_date, status, valid_until)
                         VALUES (?,?,?,?,?,?,?,?,?,NOW(),'active',?)"
                    );
                    $firstId = 0;
                    foreach ($termRows as $termRow) {
                        $termBalance = (float)$termRow['balance'];
                        $amount = $discountType === 'full_waiver'
                            ? $termBalance
                            : ($discountType === 'percentage'
                                ? round($termBalance * ($requestedAmount / 100), 2)
                                : min($termBalance, max(0, $remaining)));
                        if ($amount <= 0) continue;
                        $insert->execute([$studentId, (int)$termRow['obligation_id'], $d['discount_type'], $amount,
                        $discountType === 'percentage' ? $requestedAmount : null, $d['reason'], $yearValue, (int)$termRow['term_id'],
                        $userId, null]);
                        if (!$firstId) $firstId = (int)$this->db->lastInsertId();
                        $remaining -= $amount;
                        if ($discountType === 'fixed_amount' && $remaining <= 0) break;
                    }
                    $this->db->commit();
                    if ($firstId) return $firstId;
                } catch (\Throwable $e) {
                    if ($this->db->inTransaction()) $this->db->rollBack();
                    throw $e;
                }
            }
        }
        if (!$obligationId) {
            $termId = !empty($d['term_id']) ? (int)$d['term_id'] : null;
            $sql = "SELECT sfo.id
                    FROM student_fee_obligations sfo
                    JOIN student_academic_enrollments sae ON sae.id=sfo.student_academic_enrollment_id
                    JOIN academic_years ay ON ay.id=sfo.academic_year_id
                    WHERE sae.student_id=? AND ay.year_code LIKE ? AND sfo.status <> 'paid'";
            $params = [$studentId, $yearCode . '%'];
            if ($termId) { $sql .= ' AND sfo.academic_year_term_id=?'; $params[] = $termId; }
            $sql .= ' ORDER BY sfo.id LIMIT 1';
            $lookup = $this->db->prepare($sql);
            $lookup->execute($params);
            $obligationId = (int)($lookup->fetchColumn() ?: 0) ?: null;
        }
        $balanceStmt = $this->db->prepare(
            "SELECT GREATEST(0, sfo.amount_due - COALESCE(sfo.sponsored_waiver_amount,0)
                    - COALESCE((SELECT SUM(fdw.discount_value) FROM fee_discounts_waivers fdw
                                WHERE fdw.student_fee_obligation_id=sfo.id AND fdw.status='active'),0)
                    - COALESCE((SELECT SUM(p.amount) FROM payments p
                                WHERE p.student_id=? AND p.status='confirmed' AND p.payment_purpose='fees'),0))
             FROM student_fee_obligations sfo WHERE sfo.id=?"
        );
        $balanceStmt->execute([$studentId, $obligationId]);
        $obligationBalance = (float)($balanceStmt->fetchColumn() ?: 0);
        $actualAmount = $discountType === 'full_waiver'
            ? $obligationBalance
            : ($discountType === 'percentage'
                ? round($obligationBalance * ($requestedAmount / 100), 2)
                : min($obligationBalance, $requestedAmount));
        if ($actualAmount <= 0) throw new InvalidArgumentException('No outstanding balance is available for this waiver');
        $this->db->prepare(
            "INSERT INTO fee_discounts_waivers (student_id, student_fee_obligation_id, discount_type, discount_value,
              discount_percentage, reason, academic_year, term_id, approved_by, approved_date, status, valid_until)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),'active',?)"
        )->execute([
            $d['student_id'], $obligationId,
            $discountType, $actualAmount,
            $discountType === 'percentage' ? $requestedAmount : null, $d['reason'],
            $yearValue, $d['term_id'] ?? null,
            $userId, null
        ]);
        return $this->db->lastInsertId();
    }

    // ==================== SPONSORED STUDENTS ====================

    public function listSponsoredStudents(): array
    {
        return $this->db->query(
            "SELECT v.id, v.admission_no AS admission_number, v.student_name, v.class_name,
                    v.is_sponsored, v.sponsor_name, v.sponsor_type, v.sponsor_waiver_percentage,
                    v.total_fees_due AS total_fees, v.total_paid, v.current_balance AS outstanding_balance,
                    v.total_waived
             FROM vw_sponsored_students_status v
             ORDER BY v.sponsor_waiver_percentage DESC"
        )->fetchAll() ?: [];
    }

    // ==================== ANNUAL SCHOLARSHIPS ====================

    public function listScholarshipPrograms(): array
    {
        return $this->db->query(
            "SELECT id, code, name, coverage_type, default_percentage,
                    default_amount, description
             FROM scholarship_programs WHERE is_active = 1 ORDER BY name"
        )->fetchAll() ?: [];
    }

    public function listStudentScholarships(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'ssa.student_id = ?'; $params[] = (int)$filters['student_id']; }
        if (!empty($filters['academic_year_id'])) { $where[] = 'ssa.academic_year_id = ?'; $params[] = (int)$filters['academic_year_id']; }
        $stmt = $this->db->prepare(
            "SELECT ssa.*, sp.name AS programme_name, sp.code AS programme_code,
                    ay.year_code, s.admission_no,
                    COALESCE(CONCAT(p.first_name,' ',p.last_name), s.admission_no) AS student_name
             FROM " . ReadReplicaService::qualifiedRef("student_scholarship_awards") . " ssa
             JOIN scholarship_programs sp ON sp.id = ssa.scholarship_program_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_years") . " ay ON ay.id = ssa.academic_year_id
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = ssa.student_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id = s.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY ssa.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function createStudentScholarship(array $d, int $userId): int
    {
        $studentId = (int)($d['student_id'] ?? 0);
        $programId = (int)($d['scholarship_program_id'] ?? 0);
        $yearId = (int)($d['academic_year_id'] ?? 0);
        if (!$studentId || !$programId || !$yearId || empty($d['reason'])) {
            throw new InvalidArgumentException('student_id, scholarship_program_id, academic_year_id and reason are required');
        }
        $p = $this->db->prepare("SELECT coverage_type, default_percentage, default_amount FROM scholarship_programs WHERE id=? AND is_active=1");
        $p->execute([$programId]);
        $program = $p->fetch();
        if (!$program) throw new InvalidArgumentException('Scholarship programme was not found or is inactive');

        $type = $d['coverage_type'] ?? $program['coverage_type'];
        $percentage = $type === 'percentage' ? ($d['coverage_percentage'] ?? $program['default_percentage']) : null;
        $amount = $type === 'fixed_amount' ? ($d['coverage_amount'] ?? $program['default_amount']) : null;
        if ($type === 'percentage' && ($percentage === null || $percentage < 0 || $percentage > 100)) {
            throw new InvalidArgumentException('Percentage coverage must be between 0 and 100');
        }
        if ($type === 'fixed_amount' && ($amount === null || $amount < 0)) {
            throw new InvalidArgumentException('Fixed coverage amount is required');
        }

        $periodType = (string)($d['period_type'] ?? 'academic_year');
        if (!in_array($periodType, ['term', 'academic_year', 'custom'], true)) {
            throw new InvalidArgumentException('Invalid sponsorship period');
        }
        if ($periodType === 'term') {
            $termStmt = $this->db->prepare('SELECT opening_date, closing_date FROM academic_year_terms WHERE id=? AND academic_year_id=?');
            $termStmt->execute([(int)($d['academic_year_term_id'] ?? 0), $yearId]);
            $term = $termStmt->fetch();
            if (!$term) throw new InvalidArgumentException('The selected sponsorship term is invalid');
            $startsOn = $term['opening_date'];
            $endsOn = $term['closing_date'];
        } elseif ($periodType === 'academic_year') {
            $yearStmt = $this->db->prepare('SELECT start_date, end_date FROM academic_years WHERE id=?');
            $yearStmt->execute([$yearId]);
            $year = $yearStmt->fetch();
            if (!$year) throw new InvalidArgumentException('The selected academic year is invalid');
            $startsOn = $year['start_date'];
            $endsOn = $year['end_date'];
        } else {
            $startsOn = $d['starts_on'] ?? null;
            $endsOn = $d['ends_on'] ?? null;
            if (!$startsOn || !$endsOn || $startsOn > $endsOn) {
                throw new InvalidArgumentException('Custom sponsorship period must have valid start and end dates');
            }
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO student_scholarship_awards
                    (student_id, scholarship_program_id, academic_year_id, period_type, coverage_type,
                     coverage_percentage, coverage_amount, reason, starts_on, ends_on,
                     status, awarded_by, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?, 'active',?,?)
                 ON DUPLICATE KEY UPDATE
                    scholarship_program_id=VALUES(scholarship_program_id), period_type=VALUES(period_type),
                    coverage_type=VALUES(coverage_type), coverage_percentage=VALUES(coverage_percentage),
                    coverage_amount=VALUES(coverage_amount), reason=VALUES(reason),
                    starts_on=VALUES(starts_on), ends_on=VALUES(ends_on), status='active',
                    awarded_by=VALUES(awarded_by), revoked_by=NULL, revoked_at=NULL, notes=VALUES(notes), updated_at=NOW()"
            );
            $stmt->execute([$studentId, $programId, $yearId, $periodType, $type, $percentage, $amount,
                $d['reason'], $startsOn, $endsOn, $userId, $d['notes'] ?? null]);
            $idStmt = $this->db->prepare("SELECT id FROM student_scholarship_awards WHERE student_id=? AND academic_year_id=?");
            $idStmt->execute([$studentId, $yearId]);
            $id = (int)$idStmt->fetchColumn();
            $this->applyScholarshipToObligations($id);
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function revokeStudentScholarship(int $id, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE student_scholarship_awards SET status='revoked', revoked_by=?, revoked_at=NOW(), updated_at=NOW() WHERE id=? AND status='active'");
            $stmt->execute([$userId, $id]);
            $this->db->prepare(
                "UPDATE student_fee_obligations sfo
                 JOIN student_academic_enrollments sae ON sae.id=sfo.student_academic_enrollment_id
                 JOIN student_scholarship_awards ssa ON ssa.student_id=sae.student_id AND ssa.academic_year_id=sfo.academic_year_id
                 JOIN academic_year_terms ayt ON ayt.id=sfo.academic_year_term_id
                 SET sfo.is_sponsored=0, sfo.sponsored_waiver_amount=0,
                     sfo.status=CASE
                       WHEN COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id=sae.student_id AND p.status='confirmed' AND p.payment_purpose='fees'),0) >= sfo.amount_due THEN 'paid'
                       WHEN COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.student_id=sae.student_id AND p.status='confirmed' AND p.payment_purpose='fees'),0) > 0 THEN 'partial'
                       ELSE 'pending' END,
                     sfo.updated_at=NOW()
                 WHERE ssa.id=? AND ssa.status='revoked'
                   AND (ssa.starts_on IS NULL OR ayt.closing_date >= ssa.starts_on)
                   AND (ssa.ends_on IS NULL OR ayt.opening_date <= ssa.ends_on)"
            )->execute([$id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function applyScholarshipToObligations(int $awardId): void
    {
        $stmt = $this->db->prepare("SELECT * FROM student_scholarship_awards WHERE id=? AND status='active'");
        $stmt->execute([$awardId]);
        $award = $stmt->fetch();
        if (!$award) return;
        $waiver = $this->db->prepare(
             "UPDATE student_fee_obligations sfo
             JOIN student_academic_enrollments sae ON sae.id=sfo.student_academic_enrollment_id
             JOIN student_scholarship_awards ssa ON ssa.student_id=sae.student_id
                 AND ssa.academic_year_id=sfo.academic_year_id AND ssa.id=?
             JOIN academic_year_terms ayt ON ayt.id=sfo.academic_year_term_id
                 SET sfo.is_sponsored=1,
                 sfo.sponsored_waiver_amount=LEAST(sfo.amount_due, CASE
                    WHEN ?='full' THEN sfo.amount_due
                    WHEN ?='percentage' THEN sfo.amount_due * ? / 100
                    ELSE ? END),
                 sfo.status=CASE WHEN LEAST(sfo.amount_due, CASE
                    WHEN ?='full' THEN sfo.amount_due
                    WHEN ?='percentage' THEN sfo.amount_due * ? / 100
                    ELSE ? END) >= sfo.amount_due THEN 'paid' ELSE sfo.status END,
                 sfo.updated_at=NOW()
             WHERE sae.student_id=? AND sfo.academic_year_id=? AND sfo.status <> 'paid'
               AND (ssa.starts_on IS NULL OR ayt.closing_date >= ssa.starts_on)
               AND (ssa.ends_on IS NULL OR ayt.opening_date <= ssa.ends_on)"
        );
        $waiver->execute([
            $awardId,
            $award['coverage_type'], $award['coverage_type'], (float)$award['coverage_percentage'], (float)$award['coverage_amount'],
            $award['coverage_type'], $award['coverage_type'], (float)$award['coverage_percentage'], (float)$award['coverage_amount'],
            (int)$award['student_id'], (int)$award['academic_year_id']
        ]);
    }

    // ==================== FEE CREDIT NOTES ====================

    public function listFeeCredits(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['student_id'])) { $where[] = 'fcn.student_id = ?'; $params[] = $filters['student_id']; }
        if (!empty($filters['status']))     { $where[] = 'fcn.status = ?';     $params[] = $filters['status']; }

        $rows = $this->db->prepare(
            "SELECT fcn.id, fcn.credit_number, fcn.academic_year,
                    fcn.credit_amount, fcn.applied_amount, fcn.remaining_amount,
                    fcn.credit_reason, fcn.status, fcn.expiry_date, fcn.created_at,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), 'General Ledger') AS student_name, s.admission_no,
                    t.name AS term_name,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS created_by_name
             FROM " . ReadReplicaService::qualifiedRef("fee_credit_notes") . " fcn
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id = fcn.student_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN terms t ON t.id = fcn.term_id
             LEFT JOIN users u ON u.id = fcn.created_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY fcn.created_at DESC"
        );
        $rows->execute($params);
        $all = $rows->fetchAll() ?: [];

        $stats = [
            'total_credits'   => array_sum(array_column($all, 'credit_amount')),
            'total_available' => array_sum(array_column(array_filter($all, fn($r) => in_array($r['status'], ['available','partially_applied'])), 'remaining_amount')),
            'total_applied'   => array_sum(array_column($all, 'applied_amount')),
        ];
        return ['credits' => $all, 'stats' => $stats];
    }

    public function createFeeCredit(array $d, int $userId): array
    {
        $creditNum = 'CRD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $this->db->prepare(
            "INSERT INTO fee_credit_notes
             (credit_number, student_id, academic_year, term_id, source_transaction_id,
              credit_amount, credit_reason, expiry_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), ?, ?)"
        )->execute([
            $creditNum, $d['student_id'], $d['academic_year'] ?? date('Y'),
            $d['term_id'] ?? null, $d['source_transaction_id'] ?? null,
            $d['credit_amount'], $d['credit_reason'] ?? 'overpayment',
            $d['notes'] ?? null, $userId
        ]);
        return ['credit_number' => $creditNum, 'id' => $this->db->lastInsertId()];
    }

    public function applyFeeCredit(int $id, float $applyAmount, array $d, ?int $userId = null): float
    {
        if ($this->db->inTransaction()) {
            throw new \RuntimeException('Fee credit application must run outside an existing transaction');
        }
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT * FROM fee_credit_notes WHERE id=? FOR UPDATE");
            $lock->execute([$id]);
            $credit = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$credit || !in_array($credit['status'], ['available','partially_applied'], true)) {
                throw new \InvalidArgumentException('Fee credit is not available to apply');
            }
            $remaining = max(0, round((float)$credit['credit_amount'] - (float)$credit['applied_amount'], 2));
            $applyAmount = min(round($applyAmount, 2), $remaining);
            if ($applyAmount <= 0) throw new \InvalidArgumentException('Fee credit has no remaining amount');

            $yearCode = trim((string)($d['to_year'] ?? ''));
            if ($yearCode === '') throw new \InvalidArgumentException('to_year is required');
            $year = $this->db->prepare("SELECT id FROM academic_years WHERE year_code=? LIMIT 1");
            $year->execute([$yearCode]);
            $yearId = (int)$year->fetchColumn();
            if (!$yearId) throw new \InvalidArgumentException('Target academic year was not found');

            $termFilter = '';
            $params = [(int)$credit['student_id'], $yearId];
            if (!empty($d['to_term_id'])) {
                $termFilter = ' AND sfo.academic_year_term_id=?';
                $params[] = (int)$d['to_term_id'];
            }
            $obligations = $this->db->prepare(
                "SELECT MIN(sfo.id) AS id, GREATEST(COALESCE(vfb.balance,0),0) AS outstanding,
                        sfo.academic_year_term_id
                 FROM student_fee_obligations sfo
                 JOIN student_academic_enrollments sae ON sae.id=sfo.student_academic_enrollment_id
                 LEFT JOIN vw_student_fee_balances vfb ON vfb.student_academic_enrollment_id=sae.id
                    AND vfb.academic_year_term_id=sfo.academic_year_term_id
                 WHERE sae.student_id=? AND sae.academic_year_id=? AND sae.enrollment_status='active' {$termFilter}
                 GROUP BY sfo.academic_year_term_id,vfb.balance
                 ORDER BY MIN(sfo.due_date),MIN(sfo.id)"
            );
            $obligations->execute($params);
            $allocated = 0.0;
            $insert = $this->db->prepare(
                "INSERT INTO fee_credit_applications (fee_credit_note_id,student_fee_obligation_id,applied_amount,applied_by)
                 VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE applied_amount=applied_amount+VALUES(applied_amount), applied_by=VALUES(applied_by)"
            );
            foreach ($obligations->fetchAll(PDO::FETCH_ASSOC) as $obligation) {
                $left = round($applyAmount - $allocated, 2);
                $piece = min($left, max(0, (float)$obligation['outstanding']));
                if ($piece <= 0) continue;
                $insert->execute([$id, (int)$obligation['id'], $piece, $userId]);
                $allocated = round($allocated + $piece, 2);
                if ($allocated >= $applyAmount) break;
            }
            if ($allocated <= 0) throw new \InvalidArgumentException('There are no outstanding target-year fees to reduce');
            $this->db->prepare(
                "UPDATE fee_credit_notes SET applied_amount=applied_amount+?, applied_to_year=?, applied_to_term_id=?,
                 applied_at=NOW(), status=CASE WHEN applied_amount+?>=credit_amount THEN 'fully_applied' ELSE 'partially_applied' END
                 WHERE id=?"
            )->execute([$allocated, (int)substr($yearCode,0,4), $d['to_term_id'] ?? null, $allocated, $id]);
            $this->db->commit();
            return $allocated;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function refundFeeCredit(int $id): void
    {
        $this->db->prepare("UPDATE fee_credit_notes SET status = 'refunded' WHERE id = ?")->execute([$id]);
    }

    public function getFeeCredit(int $id): ?array
    {
        $r = $this->db->prepare("SELECT * FROM fee_credit_notes WHERE id = ?");
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    // ==================== SALARY ADVANCES ====================

    public function listSalaryAdvances(array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['staff_id'])) { $where[] = 'sa.staff_id = ?'; $params[] = $filters['staff_id']; }
        if (!empty($filters['status']))   { $where[] = 'sa.status = ?';   $params[] = $filters['status']; }

        $rows = $this->db->prepare(
            "SELECT sa.id, sa.advance_number, sa.requested_amount, sa.approved_amount,
                    sa.request_date, sa.deduction_schedule, sa.deduction_start_month,
                    sa.amount_per_deduction, sa.amount_deducted, sa.balance_remaining,
                    sa.status, sa.approval_date, sa.reason,
                    COALESCE(CONCAT(sp.first_name,' ',sp.last_name), '—') AS staff_name, s.staff_no AS employee_number,
                    COALESCE(CONCAT(up.first_name,' ',up.last_name), u.username) AS approved_by_name
             FROM staff_salary_advances sa
             JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = sa.staff_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " sp ON sp.person_id = s.person_id
             LEFT JOIN users u ON u.id = sa.approved_by
             LEFT JOIN " . ReadReplicaService::qualifiedRef("person_directory") . " up ON up.person_id = u.person_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sa.request_date DESC"
        );
        $rows->execute($params);
        $all = $rows->fetchAll() ?: [];

        $stats = [
            'total_advances'    => count($all),
            'total_issued'      => array_sum(array_column(array_filter($all, fn($r) => $r['approved_amount']), 'approved_amount')),
            'total_outstanding' => array_sum(array_column(array_filter($all, fn($r) => $r['status'] === 'active'), 'balance_remaining')),
            'pending_approval'  => count(array_filter($all, fn($r) => $r['status'] === 'pending')),
        ];
        return ['advances' => $all, 'stats' => $stats];
    }

    public function getActiveAdvanceBalance(int $staffId): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(balance_remaining),0) FROM staff_salary_advances WHERE staff_id = ? AND status = 'active'"
        );
        $stmt->execute([$staffId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (float) $value : 0.0;
    }

    public function createSalaryAdvance(array $d): int
    {
        $advNum = 'ADV-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $this->db->prepare(
            "INSERT INTO staff_salary_advances
             (advance_number, staff_id, requested_amount, request_date, reason, deduction_schedule, status)
             VALUES (?, ?, ?, CURDATE(), ?, ?, 'pending')"
        )->execute([$advNum, $d['staff_id'], $d['requested_amount'], $d['reason'] ?? null, $d['deduction_schedule'] ?? 'single_month']);
        return $this->db->lastInsertId();
    }

    public function approveSalaryAdvance(int $id, float $approved, float $perDed, string $start, int $userId): void
    {
        $advance = $this->getSalaryAdvance($id);
        $this->db->prepare(
            "UPDATE staff_salary_advances
             SET status = 'active', approved_amount = ?, amount_per_deduction = ?,
                 deduction_start_month = ?, balance_remaining = ?, approved_by = ?, approval_date = NOW()
             WHERE id = ?"
        )->execute([$approved, $perDed, $start, $approved, $userId, $id]);
        $this->notifyAdvanceStatus($advance, 'approved', $userId, null);
    }

    public function rejectSalaryAdvance(int $id, ?string $reason): void
    {
        $advance = $this->getSalaryAdvance($id);
        $this->db->prepare("UPDATE staff_salary_advances SET status = 'rejected', rejection_reason = ? WHERE id = ?")->execute([$reason, $id]);
        $this->notifyAdvanceStatus($advance, 'rejected', 0, $reason);
    }

    public function recordSalaryAdvanceDeduction(int $id, float $amt, float $newBalance, string $newStatus): void
    {
        $this->db->prepare(
            "UPDATE staff_salary_advances SET amount_deducted = amount_deducted + ?, balance_remaining = ?, status = ? WHERE id = ?"
        )->execute([$amt, $newBalance, $newStatus, $id]);
    }

    public function getSalaryAdvance(int $id): ?array
    {
        $r = $this->db->prepare("SELECT * FROM staff_salary_advances WHERE id = ?");
        $r->execute([$id]);
        return $r->fetch() ?: null;
    }

    /**
     * Notify the staff member whose salary advance was approved/rejected.
     */
    private function notifyAdvanceStatus(?array $advance, string $decision, int $actorUserId, ?string $reason): void
    {
        if (!$advance || empty($advance['staff_id'])) {
            return;
        }
        try {
            $service = new NotificationService($this->db);
            $recipients = $service->userIdsForStaff([(int) $advance['staff_id']]);
            if (empty($recipients)) {
                return;
            }
            $actor = $actorUserId > 0 ? ($service->userName($actorUserId) ?: 'the approver') : 'the approver';
            $num = $advance['advance_number'] ?? ('#' . (int) $advance['id']);
            $label = 'salary advance ' . $num;
            $title = $decision === 'approved' ? 'Salary advance approved' : 'Salary advance declined';
            $message = $decision === 'approved'
                ? NotificationService::approvedText($label, $actor)
                : NotificationService::deniedText($label, $actor, (string) ($reason ?? ''));
            $service->push($recipients, 'salary_advance', $title, $message, 'medium');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[FinanceCrudService] Notification push failed: ' . $e->getMessage());
        }
    }

    public function getStaffBasicSalary(int $staffId): float
    {
        $periodStart=date('Y-m-01');
        $stmt = $this->db->prepare("SELECT COALESCE((
                SELECT so.gross_salary FROM staff_salary_overrides so WHERE so.staff_id=s.id
                  AND so.effective_from<=? AND (so.effective_to IS NULL OR so.effective_to>=?)
                ORDER BY so.effective_from DESC,so.id DESC LIMIT 1), (
                SELECT rs.gross_salary FROM users u JOIN user_roles ur ON ur.user_id=u.id AND ur.is_primary=1
                JOIN staff_role_salary_rates rs ON rs.role_id=ur.role_id
                WHERE u.person_id=s.person_id AND rs.effective_from<=? AND (rs.effective_to IS NULL OR rs.effective_to>=?)
                ORDER BY rs.effective_from DESC,rs.id DESC LIMIT 1),0)
            FROM " . ReadReplicaService::qualifiedRef("staff") . " s WHERE s.id = ?");
        $stmt->execute([$periodStart,$periodStart,$periodStart,$periodStart,$staffId]);
        $value = $stmt->fetchColumn();
        return $value !== false ? (float) $value : 0.0;
    }

    // ==================== UNMATCHED PAYMENTS ====================

    public function listUnmatchedPayments(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        $rows = $this->db->prepare(
            "SELECT mt.id AS transaction_id, mt.mpesa_code AS reference,
                    mt.amount, mt.transaction_date, 'mpesa' AS source,
                    TRIM(CONCAT(COALESCE(mt.first_name,''), ' ', COALESCE(mt.last_name,''))) AS payer_name,
                    mt.status
                FROM " . ReadReplicaService::qualifiedRef("mpesa_transactions_payments") . " mt
                WHERE mt.payment_id IS NULL
                  AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'processed'))
                ORDER BY mt.transaction_date DESC
                LIMIT ? OFFSET ?"
        );
        $rows->execute([$limit, $offset]);

        $total = (int) $this->db->query(
            "SELECT COUNT(*)
             FROM " . ReadReplicaService::qualifiedRef("mpesa_transactions_payments") . " mt
             WHERE mt.payment_id IS NULL
               AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'processed'))"
        )->fetchColumn();

        return ['data' => $rows->fetchAll() ?: [], 'total' => $total];
    }

    public function matchPayment(int $paymentId, ?int $studentId, ?int $obligationId): void
    {
        $this->db->prepare(
            "UPDATE mpesa_transactions SET status = 'reconciled', reconciled_at = NOW(), student_id = ? WHERE id = ?"
        )->execute([$studentId, $paymentId]);
    }
}
