<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * StaffStatutoryService — owns all statutory payroll persistence and reads
 * (remittances, registers, rule versions, retention, certificates of service).
 * Extracted from StaffController under the strict SQL placement rule.
 */
final class StaffStatutoryService
{
    public function __construct(private PDO $pdo)
    {
    }

    /* ------------------------------ remittances ------------------------------ */

    public function remittances(int $year, ?string $agency, ?string $status): array
    {
        $sql = "SELECT * FROM statutory_remittances WHERE period_year = ?";
        $params = [$year];
        if ($agency) { $sql .= " AND agency = ?"; $params[] = $agency; }
        if ($status) { $sql .= " AND status = ?"; $params[] = $status; }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $remittances = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ruleStmt = $this->pdo->prepare("SELECT deadline_day,deadline_basis FROM statutory_rule_versions
            WHERE agency=? AND active=1 AND effective_from <= STR_TO_DATE(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'), '%Y-%m-%d')
            AND (effective_to IS NULL OR effective_to >= STR_TO_DATE(CONCAT(?, '-', LPAD(?, 2, '0'), '-01'), '%Y-%m-%d'))
            ORDER BY effective_from DESC, id DESC LIMIT 1");
        foreach ($remittances as &$remittance) {
            if (!empty($remittance['due_date'])) continue;
            $ruleStmt->execute([$remittance['agency'], $remittance['period_year'], $remittance['period_month'], $remittance['period_year'], $remittance['period_month']]);
            $rule = $ruleStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $day = (int) ($rule['deadline_day'] ?? 0);
            if ($day < 1 || $day > 31) continue;
            $due = new \DateTime(sprintf('%04d-%02d-01', (int) $remittance['period_year'], (int) $remittance['period_month']));
            $due->modify('+1 month')->setDate((int) $due->format('Y'), (int) $due->format('m'), $day);
            if (($rule['deadline_basis'] ?? '') === 'working_day_of_following_month') {
                while (in_array((int) $due->format('N'), [6, 7], true)) {
                    $due->modify('+1 day');
                }
            }
            $remittance['due_date'] = $due->format('Y-m-d');
        }
        unset($remittance);

        $breakdown = [];
        foreach (range(1, 12) as $m) {
            $row = ['period_month' => $m, 'kra' => 0, 'shif' => 0, 'nssf' => 0, 'housing_levy' => 0];
            foreach (['KRA', 'SHIF', 'NSSF', 'Housing Levy'] as $a) {
                $key = strtolower(str_replace(' ', '_', str_replace('/', '_', $a)));
                foreach ($remittances as $r) {
                    if ((int) $r['period_month'] === $m && $r['agency'] === $a) {
                        $row[$key === 'kra_(paye)' ? 'kra' : $key] = (float) $r['total_deducted'];
                    }
                }
            }
            $breakdown[] = $row;
        }

        $totalDeducted = array_sum(array_column($remittances, 'total_deducted'));
        $totalRemitted = array_sum(array_column($remittances, 'amount_remitted'));
        return [
            'remittances' => $remittances,
            'breakdown' => $breakdown,
            'summary' => [
                'total_deducted' => $totalDeducted,
                'total_remitted' => $totalRemitted,
                'outstanding' => $totalDeducted - $totalRemitted,
                'overdue_count' => count(array_filter($remittances, fn($r) => $r['status'] === 'overdue')),
            ],
        ];
    }

    public function saveRemittance(array $data, int $filedBy): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO statutory_remittances (agency, period_month, period_year, total_deducted, amount_remitted, status, due_date, remittance_date, filing_reference, notes, filed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['agency'] ?? null, (int) ($data['period_month'] ?? 0), (int) ($data['period_year'] ?? 0),
            $data['total_deducted'] ?? 0, $data['amount_remitted'] ?? 0, $data['status'] ?? 'pending',
            $data['due_date'] ?? null, $data['remittance_date'] ?? null, $data['filing_reference'] ?? null,
            $data['notes'] ?? null, $filedBy,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateRemittance(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare("UPDATE statutory_remittances SET amount_remitted = ?, status = ?, remittance_date = ?, filing_reference = ?, notes = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([
            $data['amount_remitted'] ?? 0, $data['status'] ?? 'pending',
            $data['remittance_date'] ?? null, $data['filing_reference'] ?? null,
            $data['notes'] ?? null, $id,
        ]);
    }

    public function agencyAccounts(string $agency): array
    {
        $stmt = $this->pdo->prepare("SELECT id, agency, account_name, account_number, bank_name, bank_code, payment_reference_rule FROM statutory_agency_accounts WHERE agency = ? AND active = 1 ORDER BY account_name, id");
        $stmt->execute([$agency]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function calcDeductions(string $agency, int $month, int $year): array
    {
        $expressions = [
            'KRA' => 'p.paye_tax',
            'SHIF' => 'p.shif_contribution',
            'NSSF' => '(p.nssf_contribution + p.employer_nssf_contribution)',
            'Housing Levy' => '(p.housing_levy + p.employer_housing_levy)',
        ];
        $amountExpression = $expressions[$agency] ?? null;
        if ($amountExpression === null) {
            throw new RuntimeException('Unknown agency', 400);
        }
        $sql = "SELECT p.staff_id, s.staff_no, CONCAT(ps.first_name, ' ', ps.last_name) AS staff_name,
                {$amountExpression} AS amount
                FROM " . ReadReplicaService::qualifiedRef("payslips") . " p JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id = p.staff_id JOIN " . ReadReplicaService::qualifiedRef("persons") . " ps ON ps.id = s.person_id
                WHERE p.payroll_month = ? AND p.payroll_year = ?
                AND p.payslip_status IN ('approved','paid') AND {$amountExpression} > 0
                ORDER BY ps.last_name, ps.first_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$month, $year]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return ['total' => array_sum(array_column($rows, 'amount')), 'staff' => $rows];
    }

    /* ------------------------------ compliance ------------------------------ */

    public function complianceBundle(int $year): array
    {
        $registers = $this->pdo->prepare('SELECT * FROM statutory_payroll_registers WHERE period_year = ? ORDER BY period_year DESC, period_month DESC');
        $registers->execute([$year]);
        $certificates = $this->pdo->query("SELECT c.*, s.staff_no, CONCAT(p.first_name, ' ', p.last_name) staff_name
            FROM staff_certificates_of_service c
            JOIN " . ReadReplicaService::qualifiedRef("staff") . " s ON s.id=c.staff_id
            JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id=s.person_id
            ORDER BY c.issued_date DESC, c.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $rules = $this->pdo->query('SELECT id,agency,rule_code,version,effective_from,effective_to,calculation_method,
            employee_rate,employer_rate,lower_earnings_limit,upper_earnings_limit,cap_amount,personal_relief,
            deadline_day,deadline_basis,source_name,source_url,active
            FROM statutory_rule_versions WHERE active=1 ORDER BY agency,effective_from DESC')->fetchAll(PDO::FETCH_ASSOC);
        $bands = $this->pdo->query('SELECT rule_version_id,band_order,lower_bound,upper_bound,tax_rate FROM statutory_tax_bands ORDER BY rule_version_id,band_order')->fetchAll(PDO::FETCH_ASSOC);
        $bandsByRule = [];
        foreach ($bands as $band) {
            $bandsByRule[(int) $band['rule_version_id']][] = [
                'up_to' => $band['upper_bound'] === null ? null : (float) $band['upper_bound'],
                'rate' => (float) $band['tax_rate'],
            ];
        }
        foreach ($rules as &$rule) {
            $rule['rules'] = [
                'calculation' => $rule['calculation_method'],
                'employee_rate' => $rule['employee_rate'] === null ? null : (float) $rule['employee_rate'],
                'employer_rate' => $rule['employer_rate'] === null ? null : (float) $rule['employer_rate'],
                'lower_earnings_limit' => $rule['lower_earnings_limit'] === null ? null : (float) $rule['lower_earnings_limit'],
                'upper_earnings_limit' => $rule['upper_earnings_limit'] === null ? null : (float) $rule['upper_earnings_limit'],
                'cap_amount' => $rule['cap_amount'] === null ? null : (float) $rule['cap_amount'],
                'personal_relief' => $rule['personal_relief'] === null ? null : (float) $rule['personal_relief'],
                'deadline_day' => $rule['deadline_day'],
                'deadline_basis' => $rule['deadline_basis'],
                'bands' => $bandsByRule[(int) $rule['id']] ?? [],
            ];
        }
        unset($rule);
        $retention = $this->pdo->query("SELECT COUNT(*) FROM statutory_record_retention WHERE status='active' AND retain_until <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)")->fetchColumn();

        return [
            'registers' => $registers->fetchAll(PDO::FETCH_ASSOC),
            'certificates' => $certificates,
            'rules' => $rules,
            'retention_due_90_days' => (int) $retention,
        ];
    }

    /**
     * Append an effective-dated rule version; existing payroll snapshots are
     * never rewritten. Returns the new version id.
     */
    public function addRuleVersion(array $data, int $actorId): int
    {
        $rules = $data['rules'] ?? null;
        if (!is_array($rules)) {
            throw new RuntimeException('Agency, rule code, version, effective date and rule values are required.', 400);
        }
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO statutory_rule_versions
                (agency,rule_code,version,effective_from,effective_to,calculation_method,employee_rate,employer_rate,
                 lower_earnings_limit,upper_earnings_limit,cap_amount,personal_relief,deadline_day,deadline_basis,
                 source_name,source_url,active,created_by)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?)");
            $stmt->execute([
                trim((string) $data['agency']), trim((string) $data['rule_code']), trim((string) $data['version']),
                $data['effective_from'], $data['effective_to'] ?? null,
                $rules['calculation'] ?? 'percentage_of_gross', $rules['employee_rate'] ?? null, $rules['employer_rate'] ?? null,
                $rules['lower_earnings_limit'] ?? null, $rules['upper_earnings_limit'] ?? null, $rules['cap_amount'] ?? null,
                $rules['personal_relief'] ?? null, $rules['deadline_day'] ?? null, $rules['deadline_basis'] ?? null,
                $data['source_name'] ?? null, $data['source_url'] ?? null, $actorId,
            ]);
            $id = (int) $this->pdo->lastInsertId();
            if (!empty($rules['bands']) && is_array($rules['bands'])) {
                $band = $this->pdo->prepare('INSERT INTO statutory_tax_bands(rule_version_id,band_order,lower_bound,upper_bound,tax_rate) VALUES(?,?,?,?,?)');
                foreach (array_values($rules['bands']) as $index => $item) {
                    $upper = array_key_exists('up_to', $item) && $item['up_to'] !== null ? $item['up_to'] : null;
                    $lower = $index === 0 ? 0 : ($rules['bands'][$index - 1]['up_to'] ?? 0);
                    $band->execute([$id, $index + 1, $lower, $upper, $item['rate'] ?? 0]);
                }
            }
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Generate the per-period statutory payroll register. Returns id + counts. */
    public function generatePayrollRegister(int $month, int $year, int $actorId): array
    {
        $this->pdo->beginTransaction();
        try {
            $q = $this->pdo->prepare("SELECT p.*, pr.id payroll_run_id FROM " . ReadReplicaService::qualifiedRef("payslips") . "
                LEFT JOIN payroll_runs pr ON pr.month=p.payroll_month AND pr.year=p.payroll_year
                WHERE p.payroll_month=? AND p.payroll_year=? AND p.payslip_status IN ('approved','paid')
                ORDER BY p.staff_id");
            $q->execute([$month, $year]);
            $payslips = $q->fetchAll(PDO::FETCH_ASSOC);
            if (!$payslips) throw new RuntimeException('No approved or paid payslips exist for this period.', 422);
            $gross = $employee = $employer = 0.0;
            foreach ($payslips as $p) {
                $employee += (float) $p['paye_tax'] + (float) $p['shif_contribution'] + (float) $p['nssf_contribution'] + (float) $p['housing_levy'];
                $employer += (float) $p['employer_nssf_contribution'] + (float) $p['employer_housing_levy'];
                $gross += (float) $p['gross_salary'];
            }
            $retentionUntil = sprintf('%04d-%02d-01', $year + 5, $month);
            $runId = $payslips[0]['payroll_run_id'] ?: null;
            $upsert = $this->pdo->prepare("INSERT INTO statutory_payroll_registers
                (payroll_run_id,period_month,period_year,employee_count,gross_total,employee_deductions_total,employer_contributions_total,status,retention_until,created_by)
                VALUES(?,?,?,?,?,?,?,'draft',?,?)
                ON DUPLICATE KEY UPDATE payroll_run_id=VALUES(payroll_run_id),employee_count=VALUES(employee_count),
                gross_total=VALUES(gross_total),employee_deductions_total=VALUES(employee_deductions_total),
                employer_contributions_total=VALUES(employer_contributions_total),retention_until=VALUES(retention_until),updated_at=NOW()");
            $upsert->execute([$runId, $month, $year, count($payslips), $gross, $employee, $employer, $retentionUntil, $actorId]);
            $registerId = (int) $this->pdo->lastInsertId();
            if (!$registerId) {
                $find = $this->pdo->prepare('SELECT id FROM statutory_payroll_registers WHERE period_month=? AND period_year=?');
                $find->execute([$month, $year]);
                $registerId = (int) $find->fetchColumn();
            }
            $item = $this->pdo->prepare("INSERT INTO statutory_payroll_register_items
                (register_id,payslip_id,staff_id,gross_amount,paye_amount,shif_employee_amount,nssf_employee_amount,
                 housing_employee_amount,nssf_employer_amount,housing_employer_amount,rule_snapshot)
                VALUES(?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE gross_amount=VALUES(gross_amount),paye_amount=VALUES(paye_amount),
                shif_employee_amount=VALUES(shif_employee_amount),nssf_employee_amount=VALUES(nssf_employee_amount),
                housing_employee_amount=VALUES(housing_employee_amount),nssf_employer_amount=VALUES(nssf_employer_amount),
                housing_employer_amount=VALUES(housing_employer_amount),rule_snapshot=VALUES(rule_snapshot)");
            foreach ($payslips as $p) {
                $item->execute([$registerId, $p['id'], $p['staff_id'], $p['gross_salary'], $p['paye_tax'], $p['shif_contribution'],
                    $p['nssf_contribution'], $p['housing_levy'], $p['employer_nssf_contribution'], $p['employer_housing_levy'],
                    json_encode(['source' => 'payslip', 'payroll_month' => $month, 'payroll_year' => $year])]);
        }
            $ret = $this->pdo->prepare("INSERT INTO statutory_record_retention(record_type,record_id,period_start,period_end,retain_until)
                VALUES('payroll_register',?,?,LAST_DAY(?),?) ON DUPLICATE KEY UPDATE retain_until=VALUES(retain_until)");
            $period = sprintf('%04d-%02d-01', $year, $month);
            $ret->execute([$registerId, $period, $period, $retentionUntil]);
            $this->pdo->prepare("INSERT INTO statutory_audit_log(actor_user_id,action,entity_type,entity_id,after_json) VALUES(?,?,?,?,?)")
                ->execute([$actorId, 'generated', 'statutory_payroll_register', $registerId, json_encode(['month' => $month, 'year' => $year, 'payslips' => count($payslips)])]);
            $this->pdo->commit();
            return ['register_id' => $registerId, 'employee_count' => count($payslips)];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Record a certificate of service (+ retention marker). Returns the new id. */
    public function recordServiceCertificate(int $staffId, array $data, int $actorId): array
    {
        $staff = $this->pdo->prepare('SELECT s.staff_no, s.position, s.employment_date FROM staff s WHERE s.id=?');
        $staff->execute([$staffId]);
        $row = $staff->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Staff member not found.', 404);

        $certificateNo = trim((string) ($data['certificate_number'] ?? ('COS-' . date('YmdHis') . '-' . $row['staff_no'])));
        $issued = $data['issued_date'] ?? date('Y-m-d');
        $retention = date('Y-m-d', strtotime($issued . ' +5 years'));

        $stmt = $this->pdo->prepare("INSERT INTO staff_certificates_of_service
            (staff_id,certificate_number,employment_start_date,employment_end_date,designation,department,reason_for_leaving,issued_date,status,retention_until,issued_by)
            VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $staffId, $certificateNo, $data['employment_start_date'] ?? null, $data['employment_end_date'] ?? null,
            $data['designation'] ?? $row['position'], $data['department'] ?? null, $data['reason_for_leaving'] ?? null,
            $issued, $data['status'] ?? 'draft', $retention, $actorId,
        ]);
        $certificateId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO statutory_record_retention(record_type,record_id,period_start,period_end,retain_until) VALUES('certificate_of_service',?,?,?,?)")
            ->execute([$certificateId, $data['employment_start_date'] ?? null, $issued, $retention]);
        return ['id' => $certificateId, 'certificate_number' => $certificateNo];
    }
}
