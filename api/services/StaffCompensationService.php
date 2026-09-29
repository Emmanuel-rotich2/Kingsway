<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Salary defaults, individual exceptions, and month-scoped payroll awards. */
final class StaffCompensationService
{
    public function __construct(private PDO $db)
    {
    }

    /** Return normalized setup data for the payroll controls. */
    public function setup(): array
    {
        $roles = $this->db->query(
            "SELECT r.id, r.name, rate.gross_salary, rate.effective_from, rate.effective_to
             FROM roles r
             LEFT JOIN staff_role_salary_rates rate ON rate.id = (
                 SELECT latest.id FROM staff_role_salary_rates latest
                 WHERE latest.role_id = r.id ORDER BY latest.effective_from DESC, latest.id DESC LIMIT 1
             )
             WHERE r.is_active = 1 ORDER BY r.name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $staff = $this->db->query(
            "SELECT s.id, s.staff_no, CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                    COALESCE(d.name, '') AS department, primary_role.id AS primary_role_id,
                    primary_role.name AS primary_role,
                    CASE WHEN override.id IS NULL THEN 0 ELSE 1 END AS salary_override,
                    override.gross_salary AS individual_salary,
                    role_rate.gross_salary AS role_gross_salary
             FROM staff s JOIN persons p ON p.id = s.person_id
             LEFT JOIN staff_employment_profiles sep ON sep.staff_id = s.id
             LEFT JOIN departments d ON d.id = sep.department_id
             LEFT JOIN users u ON u.person_id = p.id
             LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.is_primary = 1
             LEFT JOIN roles primary_role ON primary_role.id = ur.role_id
             LEFT JOIN staff_salary_overrides override ON override.id = (
                 SELECT latest.id FROM staff_salary_overrides latest
                 WHERE latest.staff_id = s.id AND latest.effective_from <= CURDATE()
                   AND (latest.effective_to IS NULL OR latest.effective_to >= CURDATE())
                 ORDER BY latest.effective_from DESC, latest.id DESC LIMIT 1
             )
             LEFT JOIN staff_role_salary_rates role_rate ON role_rate.id = (
                 SELECT latest.id FROM staff_role_salary_rates latest
                 WHERE latest.role_id = primary_role.id AND latest.effective_from <= CURDATE()
                   AND (latest.effective_to IS NULL OR latest.effective_to >= CURDATE())
                 ORDER BY latest.effective_from DESC, latest.id DESC LIMIT 1
             )
             WHERE s.status = 'active' AND s.data_scope = 'live' ORDER BY p.first_name, p.last_name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $departments = $this->db->query(
            "SELECT id, name FROM departments WHERE status = 'active' ORDER BY name"
        )->fetchAll(PDO::FETCH_ASSOC);
        $salaryOverrides = $this->db->query(
            "SELECT so.id, so.staff_id, so.gross_salary, so.effective_from, so.effective_to,
                    CONCAT_WS(' ', p.first_name, p.middle_name, p.last_name) AS full_name,
                    s.staff_no
             FROM staff_salary_overrides so JOIN staff s ON s.id=so.staff_id
             JOIN persons p ON p.id=s.person_id
             ORDER BY so.effective_from DESC, so.id DESC LIMIT 250"
        )->fetchAll(PDO::FETCH_ASSOC);
        $awardBatches = $this->db->query(
            "SELECT b.id,b.award_kind,b.award_name,b.award_type,b.amount_per_month,b.selection_mode,
                    b.status,b.created_at,d.name AS department,
                    (SELECT COUNT(*) FROM staff_payroll_award_recipients r WHERE r.batch_id=b.id) AS recipient_count,
                    (SELECT GROUP_CONCAT(CONCAT(LPAD(p.payroll_month,2,'0'),'/',p.payroll_year)
                         ORDER BY p.payroll_year,p.payroll_month SEPARATOR ', ')
                     FROM staff_payroll_award_periods p WHERE p.batch_id=b.id) AS periods
             FROM staff_payroll_award_batches b
             LEFT JOIN departments d ON d.id=b.department_id
             ORDER BY b.created_at DESC,b.id DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        return compact('roles', 'staff', 'departments', 'salaryOverrides', 'awardBatches');
    }

    /** Add a dated rate for one system role, rejecting overlapping periods. */
    public function saveRoleRate(array $data, ?int $actorId): array
    {
        $roleId = (int) ($data['role_id'] ?? 0);
        $amount = filter_var($data['gross_salary'] ?? null, FILTER_VALIDATE_FLOAT);
        $from = trim((string) ($data['effective_from'] ?? ''));
        $to = trim((string) ($data['effective_to'] ?? '')) ?: null;
        if ($roleId < 1 || $amount === false || $amount <= 0 || !$this->validDate($from)) {
            throw new RuntimeException('Choose a role, enter a positive monthly gross salary, and enter a valid effective date.');
        }
        if (date('d', strtotime($from)) !== '01') {
            throw new RuntimeException('Role salary rates take effect from the first day of a payroll month.');
        }
        if ($to !== null && (!$this->validDate($to) || $to < $from)) {
            throw new RuntimeException('The end date must be on or after the effective date.');
        }
        if ($to !== null && date('Y-m-t', strtotime($to)) !== $to) {
            throw new RuntimeException('End a role salary rate on the last day of a payroll month.');
        }

        $this->db->beginTransaction();
        try {
            $role = $this->db->prepare('SELECT id FROM roles WHERE id = ? AND is_active = 1');
            $role->execute([$roleId]);
            if (!$role->fetchColumn()) {
                throw new RuntimeException('The selected role is unavailable.');
            }
            $rates = $this->db->prepare(
                'SELECT id, effective_from, effective_to FROM staff_role_salary_rates
                 WHERE role_id = ? ORDER BY effective_from FOR UPDATE'
            );
            $rates->execute([$roleId]);
            $existingRates = $rates->fetchAll(PDO::FETCH_ASSOC);
            foreach ($existingRates as $rate) {
                if ((string)$rate['effective_from'] === $from) {
                    $this->db->prepare('UPDATE staff_role_salary_rates SET gross_salary=?,effective_to=?,created_by=? WHERE id=?')
                        ->execute([$amount,$to,$actorId,(int)$rate['id']]);
                    $this->db->commit();
                    return ['id'=>(int)$rate['id']];
                }
                $newEnd = $to ?? '9999-12-31';
                $oldEnd = $rate['effective_to'] ?: '9999-12-31';
                $overlaps = (string) $rate['effective_from'] <= $newEnd && $oldEnd >= $from;
                if ($overlaps && (string) $rate['effective_from'] < $from && $rate['effective_to'] === null) {
                    $this->db->prepare(
                        'UPDATE staff_role_salary_rates SET effective_to = DATE_SUB(?, INTERVAL 1 DAY) WHERE id = ?'
                    )->execute([$from, (int) $rate['id']]);
                    continue;
                }
                if ($overlaps) {
                    throw new RuntimeException('This effective period overlaps an existing salary rate for the role.');
                }
            }
            $insert = $this->db->prepare(
                'INSERT INTO staff_role_salary_rates (role_id, gross_salary, effective_from, effective_to, created_by)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insert->execute([$roleId, $amount, $from, $to, $actorId]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return ['id' => $id];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Set or clear an explicit individual salary exception. */
    public function saveIndividualSalary(array $data, ?int $actorId): void
    {
        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId < 1) throw new RuntimeException('Choose a staff member.');
        $staff = $this->db->prepare("SELECT id FROM staff WHERE id = ? AND status = 'active' AND data_scope = 'live'");
        $staff->execute([$staffId]);
        if (!$staff->fetchColumn()) throw new RuntimeException('The selected staff member is unavailable.');
        $effectiveFrom = trim((string) ($data['effective_from'] ?? ''));
        if (!$this->validDate($effectiveFrom)) {
            throw new RuntimeException('Choose a valid effective date for the individual salary change.');
        }
        if (date('d', strtotime($effectiveFrom)) !== '01') {
            throw new RuntimeException('Individual salary changes take effect from the first day of a payroll month.');
        }

        if (!empty($data['clear_override'])) {
            $this->db->beginTransaction();
            try {
                $future = $this->db->prepare('SELECT id FROM staff_salary_overrides WHERE staff_id=? AND effective_from>? FOR UPDATE');
                $future->execute([$staffId,$effectiveFrom]);
                if ($future->fetchColumn()) throw new RuntimeException('A later salary override is already scheduled; change that date first.');
                $this->db->prepare('DELETE FROM staff_salary_overrides WHERE staff_id=? AND effective_from=?')->execute([$staffId,$effectiveFrom]);
                $this->db->prepare(
                    'UPDATE staff_salary_overrides SET effective_to = DATE_SUB(?, INTERVAL 1 DAY)
                     WHERE staff_id = ? AND effective_from < ? AND (effective_to IS NULL OR effective_to >= ?)'
                )->execute([$effectiveFrom, $staffId, $effectiveFrom, $effectiveFrom]);
                $this->db->commit();
            } catch (\Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
            return;
        }
        $amount = filter_var($data['gross_salary'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($amount === false || $amount <= 0) throw new RuntimeException('Enter an individual monthly gross salary above zero.');
        $this->db->beginTransaction();
        try {
            $future = $this->db->prepare('SELECT id FROM staff_salary_overrides WHERE staff_id = ? AND effective_from >= ? FOR UPDATE');
            $future->execute([$staffId, $effectiveFrom]);
            $sameStart=$this->db->prepare('SELECT id FROM staff_salary_overrides WHERE staff_id=? AND effective_from=? FOR UPDATE');
            $sameStart->execute([$staffId,$effectiveFrom]);
            $sameId=$sameStart->fetchColumn();
            if ($sameId) {
                $this->db->prepare('UPDATE staff_salary_overrides SET gross_salary=?,created_by=? WHERE id=?')
                    ->execute([$amount,$actorId,(int)$sameId]);
                $this->db->commit();
                return;
            }
            if ($future->fetchColumn()) throw new RuntimeException('A salary override already exists from this date or later.');
            $this->db->prepare(
                'UPDATE staff_salary_overrides SET effective_to = DATE_SUB(?, INTERVAL 1 DAY)
                 WHERE staff_id = ? AND effective_from < ? AND (effective_to IS NULL OR effective_to >= ?)'
            )->execute([$effectiveFrom, $staffId, $effectiveFrom, $effectiveFrom]);
            $this->db->prepare(
                'INSERT INTO staff_salary_overrides (staff_id, gross_salary, effective_from, created_by)
                 VALUES (?, ?, ?, ?)'
            )->execute([$staffId, $amount, $effectiveFrom, $actorId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Snapshot the selected staff and periods into independent relations. */
    public function createAward(array $data, ?int $actorId): array
    {
        $kind = (string) ($data['award_kind'] ?? '');
        $name = trim((string) ($data['award_name'] ?? ''));
        $type = trim((string) ($data['award_type'] ?? 'other')) ?: 'other';
        $amount = filter_var($data['amount_per_month'] ?? null, FILTER_VALIDATE_FLOAT);
        $mode = (string) ($data['selection_mode'] ?? '');
        $periods = $this->normalizePeriods($data['periods'] ?? []);
        $staffIds = array_values(array_unique(array_filter(array_map('intval', (array) ($data['staff_ids'] ?? [])))));
        $departmentId = (int) ($data['department_id'] ?? 0);
        if (!in_array($kind, ['allowance', 'deduction'], true) || $name === '' || $amount === false || $amount <= 0) {
            throw new RuntimeException('Enter an award action, description, and positive amount per person per month.');
        }
        if (!in_array($mode, ['department_all', 'selected_staff'], true)) {
            throw new RuntimeException('Choose all members of a department or specific staff members.');
        }
        if (!$periods) throw new RuntimeException('Select at least one affected payroll month.');

        if ($mode === 'department_all') {
            if ($departmentId < 1) throw new RuntimeException('Choose a department.');
            $staffQuery = $this->db->prepare(
                "SELECT s.id FROM staff s
                 JOIN staff_employment_profiles sep ON sep.staff_id = s.id
                 JOIN departments d ON d.id = sep.department_id AND d.status = 'active'
                 WHERE s.status = 'active' AND s.data_scope = 'live' AND sep.department_id = ?"
            );
            $staffQuery->execute([$departmentId]);
            $staffIds = array_map('intval', $staffQuery->fetchAll(PDO::FETCH_COLUMN));
        } else {
            if (!$staffIds) throw new RuntimeException('Select one or more staff members.');
            $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
            $staffQuery = $this->db->prepare(
                "SELECT id FROM staff WHERE status = 'active' AND data_scope = 'live' AND id IN ($placeholders)"
            );
            $staffQuery->execute($staffIds);
            $validIds = array_map('intval', $staffQuery->fetchAll(PDO::FETCH_COLUMN));
            sort($validIds);
            $requestedIds = $staffIds;
            sort($requestedIds);
            if ($validIds !== $requestedIds) {
                throw new RuntimeException('One or more selected staff members are inactive or unavailable. Refresh the list and try again.');
            }
        }
        if (!$staffIds) throw new RuntimeException('No active staff match the selected recipients.');

        $this->db->beginTransaction();
        try {
            $batch = $this->db->prepare(
                'INSERT INTO staff_payroll_award_batches
                    (award_kind, award_name, award_type, amount_per_month, department_id, selection_mode, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $batch->execute([$kind, $name, $type, $amount, $mode === 'department_all' ? $departmentId : null, $mode, $actorId]);
            $batchId = (int) $this->db->lastInsertId();
            $recipient = $this->db->prepare('INSERT INTO staff_payroll_award_recipients (batch_id, staff_id) VALUES (?, ?)');
            foreach ($staffIds as $staffId) $recipient->execute([$batchId, $staffId]);
            $period = $this->db->prepare('INSERT INTO staff_payroll_award_periods (batch_id, payroll_month, payroll_year) VALUES (?, ?, ?)');
            foreach ($periods as $selectedPeriod) $period->execute([$batchId, $selectedPeriod['month'], $selectedPeriod['year']]);
            $this->db->commit();
            return ['batch_id' => $batchId, 'recipient_count' => count($staffIds), 'period_count' => count($periods), 'line_count' => count($staffIds) * count($periods)];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Cancel a scheduled batch only before it has entered a payslip. */
    public function cancelAward(int $batchId): void
    {
        if ($batchId < 1) throw new RuntimeException('A valid award batch is required.');
        $this->db->beginTransaction();
        try {
            $batch = $this->db->prepare("SELECT id,status FROM staff_payroll_award_batches WHERE id=? FOR UPDATE");
            $batch->execute([$batchId]);
            $row = $batch->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'active') throw new RuntimeException('This award batch is unavailable or already cancelled.');
            $used = $this->db->prepare(
                'SELECT 1 FROM staff_payroll_award_recipients r
                 JOIN staff_payroll_award_periods p ON p.batch_id=r.batch_id
                 JOIN payslips ps ON ps.staff_id=r.staff_id AND ps.payroll_month=p.payroll_month AND ps.payroll_year=p.payroll_year
                 WHERE r.batch_id=? LIMIT 1'
            );
            $used->execute([$batchId]);
            if ($used->fetchColumn()) throw new RuntimeException('This award has already been included in payroll. Use a payroll adjustment for a processed month.');
            $this->db->prepare("UPDATE staff_payroll_award_batches SET status='cancelled' WHERE id=?")->execute([$batchId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<int,array{month:int,year:int}> */
    private function normalizePeriods($input): array
    {
        if (!is_array($input)) throw new RuntimeException('Affected payroll months must be a list.');
        $periods = [];
        foreach ($input as $value) {
            if (!is_array($value)) throw new RuntimeException('An affected payroll month is invalid.');
            $month = (int) ($value['month'] ?? 0);
            $year = (int) ($value['year'] ?? 0);
            if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) throw new RuntimeException('An affected payroll month is invalid.');
            $periods[sprintf('%04d-%02d', $year, $month)] = ['month' => $month, 'year' => $year];
        }
        ksort($periods);
        return array_values($periods);
    }

    private function validDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
