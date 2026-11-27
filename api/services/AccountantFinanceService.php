<?php
namespace App\API\Services;

use App\Database\Database;

/**
 * Read-only finance summaries for the school accountant dashboard.
 *
 * Kept in a service so all SQL stays out of controllers and page templates.
 * Every figure is scoped to live/posted records; nothing here writes money.
 */
class AccountantFinanceService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Cash position from posted journal lines on registered financial accounts.
     *
     * A kind with no ledger-linked account is reported separately as unknown:
     * returning zero would falsely tell an accountant that there is no cash or
     * M-Pesa float when the school simply has not posted it to the ledger.
     *
     * @return array
     */
    public function getCashPosition(): array
    {
        $stmt = $this->db->query(
            "SELECT k.code AS kind,
                    COALESCE(SUM(CASE WHEN b.status = 'posted'
                        THEN l.debit_amount - l.credit_amount ELSE 0 END), 0) AS balance,
                    COUNT(DISTINCT a.id) AS account_count
             FROM " . ReadReplicaService::qualifiedRef('school_financial_accounts') . " a
             JOIN financial_account_kinds k ON k.id = a.account_kind_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('chart_of_accounts') . " c
               ON c.id = a.ledger_account_id
             LEFT JOIN accounting_journal_lines l ON l.chart_account_id = c.id
             LEFT JOIN accounting_journal_batches b ON b.id = l.journal_batch_id
             WHERE a.status = 'active'
               AND k.code IN ('bank', 'cash', 'mobile_money', 'clearing')
             GROUP BY k.code"
        );
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $balances = ['bank' => 0.0, 'cash' => 0.0, 'mpesa' => 0.0];
        $accounts = ['bank' => 0, 'cash' => 0, 'mpesa' => 0];
        foreach ($rows as $row) {
            // Mobile-money and clearing accounts are the two M-Pesa settlement
            // kinds in the account registry; neither is a Bank account.
            $kind = in_array($row['kind'], ['mobile_money', 'clearing'], true)
                ? 'mpesa'
                : $row['kind'];
            if (!array_key_exists($kind, $balances)) {
                continue;
            }
            $balances[$kind] += (float) $row['balance'];
            $accounts[$kind] += (int) $row['account_count'];
        }

        $missing = $this->db->query(
            "SELECT k.code AS kind, COUNT(*) AS account_count
             FROM " . ReadReplicaService::qualifiedRef('school_financial_accounts') . " a
             JOIN financial_account_kinds k ON k.id = a.account_kind_id
             WHERE a.status = 'active'
               AND a.ledger_account_id IS NULL
               AND k.code IN ('bank', 'cash', 'mobile_money', 'clearing')
             GROUP BY k.code"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $unknown = [];
        foreach ($missing as $row) {
            $kind = in_array($row['kind'], ['mobile_money', 'clearing'], true)
                ? 'mpesa'
                : $row['kind'];
            $unknown[$kind] = (int) ($unknown[$kind] ?? 0) + (int) $row['account_count'];
        }

        return [
            'bank_balance' => round($balances['bank'], 2),
            'cash_on_hand' => round($balances['cash'], 2),
            'mpesa_float' => round($balances['mpesa'], 2),
            'total' => round(array_sum($balances), 2),
            'account_counts' => $accounts,
            'unposted_accounts' => $unknown,
            'basis' => 'Posted journal lines on active ledger-linked accounts; mobile-money and clearing accounts are grouped as M-Pesa',
            'as_of' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Current-month payroll snapshot for the accountant's operations row.
     *
     * @return array
     */
    public function getPayrollSnapshot(): array
    {
        $stmt = $this->db->query(
            "SELECT
                COALESCE(SUM(CASE WHEN payslip_status IN ('approved', 'paid') THEN net_salary ELSE 0 END), 0) AS net_payroll,
                COALESCE(SUM(gross_salary), 0) AS gross_payroll,
                COALESCE(SUM(paye_tax), 0) AS paye,
                COALESCE(SUM(nssf_contribution), 0) AS nssf,
                COALESCE(SUM(nhif_contribution + shif_contribution), 0) AS nhif_shif,
                COALESCE(SUM(CASE WHEN payslip_status = 'draft' THEN 1 ELSE 0 END), 0) AS pending_approval,
                COALESCE(SUM(CASE WHEN payslip_status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count,
                COUNT(*) AS payslip_count
             FROM " . ReadReplicaService::qualifiedRef('payslips') . "
             WHERE payroll_month = MONTH(CURDATE())
               AND payroll_year = YEAR(CURDATE())
               AND data_scope = 'live'
               AND payslip_status <> 'cancelled'"
        );
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return [
            'month' => (int) date('m'),
            'year' => (int) date('Y'),
            'net_payroll' => round((float) ($row['net_payroll'] ?? 0), 2),
            'gross_payroll' => round((float) ($row['gross_payroll'] ?? 0), 2),
            'paye' => round((float) ($row['paye'] ?? 0), 2),
            'nssf' => round((float) ($row['nssf'] ?? 0), 2),
            'nhif_shif' => round((float) ($row['nhif_shif'] ?? 0), 2),
            'pending_approval' => (int) ($row['pending_approval'] ?? 0),
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'payslip_count' => (int) ($row['payslip_count'] ?? 0),
            'as_of' => date('Y-m-d H:i:s'),
        ];
    }
}
