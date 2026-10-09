<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Shared extra-charge resolver. Charge definitions and pricing are stored in
 * normalized relations; admission and enrollment workflows use this service
 * so the amount shown and the amount enforced come from one source.
 */
final class ExtraChargeService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function resolveAdmissionObligations(int $applicationId): array
    {
        $application = $this->application($applicationId);
        if (!$application) {
            throw new RuntimeException('Admission application not found.');
        }

        $yearId = null;
        if (!empty($application['target_term_id'])) {
            $termStmt = $this->db->prepare('SELECT academic_year_id FROM academic_year_terms WHERE id=? LIMIT 1');
            $termStmt->execute([(int) $application['target_term_id']]);
            $yearId = (int) ($termStmt->fetchColumn() ?: 0) ?: null;
        }
        $yearId = $yearId ?: $this->academicYearId((string) $application['academic_year']);
        if (!$yearId) {
            throw new RuntimeException('Academic year for the application was not found.');
        }

        $parentId = (int) ($application['parent_id'] ?? 0);
        $studentId = (int) ($application['enrolled_student_id'] ?? 0);
        $existingParent = false;
        if ($parentId > 0) {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM student_parents WHERE parent_id=? AND (?=0 OR student_id<>?) LIMIT 1'
            );
            $stmt->execute([$parentId, $studentId, $studentId]);
            $existingParent = (bool) $stmt->fetchColumn();
        }

        $charges = $this->db->prepare(
            "SELECT ec.*
             FROM " . ReadReplicaService::qualifiedRef("extra_charges") . "
             JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='admission'
             WHERE ec.academic_year_id=? AND ec.status='active'
               AND ec.target_scope='new_admissions'
               AND ec.billing_model='paid_separately'
               AND ec.billing_frequency='one_time'
             ORDER BY ec.display_order, ec.id"
        );
        $charges->execute([$yearId]);

        $result = [];
        foreach ($charges->fetchAll(PDO::FETCH_ASSOC) as $charge) {
            $conditionCodes = $existingParent
                ? ['existing_parent', 'existing']
                : ['new_parent', 'new'];
            $placeholders = implode(',', array_fill(0, count($conditionCodes), '?'));
            $tierStmt = $this->db->prepare(
                "SELECT id, amount FROM extra_charge_pricing_tiers
                 WHERE extra_charge_id=? AND condition_code IN ($placeholders)
                 ORDER BY sort_order, id LIMIT 1"
            );
            $tierStmt->execute(array_merge([(int) $charge['id']], $conditionCodes));
            $tier = $tierStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            $amount = $tier ? (float) $tier['amount'] : (float) $charge['amount'];

            $existing = $this->db->prepare(
                'SELECT * FROM extra_charge_application_obligations WHERE application_id=? AND extra_charge_id=? FOR UPDATE'
            );
            $existing->execute([$applicationId, (int) $charge['id']]);
            $obligation = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$obligation) {
                $insert = $this->db->prepare(
                    'INSERT INTO extra_charge_application_obligations
                     (application_id,extra_charge_id,pricing_tier_id,amount_due)
                     VALUES (?,?,?,?)'
                );
                $insert->execute([$applicationId, (int) $charge['id'], $tier['id'] ?? null, $amount]);
                $obligation = [
                    'id' => (int) $this->db->lastInsertId(),
                    'application_id' => $applicationId,
                    'extra_charge_id' => (int) $charge['id'],
                    'amount_due' => $amount,
                    'amount_paid' => 0,
                    'status' => 'pending',
                ];
            }
            $obligation['name'] = $charge['name'];
            $obligation['gl_account_id'] = $charge['gl_account_id'];
            $obligation['existing_parent'] = $existingParent ? 1 : 0;
            $result[] = $obligation;
        }

        return $result;
    }

    public function admissionTotalDue(int $applicationId): float
    {
        $obligations = $this->resolveAdmissionObligations($applicationId);
        $total = 0.0;
        $relief = $this->admissionFinancialRelief($applicationId);
        $registrationType = $relief['registration_fee_waiver_type'] ?? 'none';
        $registrationValue = (float) ($relief['registration_fee_waiver_value'] ?? 0);
        $registrationRemaining = $registrationType === 'fixed' ? $registrationValue : 0.0;
        foreach ($obligations as $obligation) {
            $balance = max(0, (float) $obligation['amount_due']
                - (float) ($obligation['amount_paid'] ?? 0)
                - (float) ($obligation['amount_waived'] ?? 0));
            $waiver = $registrationType === 'full'
                ? $balance
                : ($registrationType === 'percentage'
                    ? $balance * $registrationValue / 100
                    : ($registrationType === 'fixed' ? min($balance, max(0, $registrationRemaining)) : 0));
            if ($registrationType === 'fixed') $registrationRemaining = max(0, $registrationRemaining - $waiver);
            $total += max(0, $balance - $waiver);
        }
        return round($total, 2);
    }

    /** Resolve the durable financial decision attached to an admission exception. */
    public function admissionFinancialRelief(int $applicationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT waiver_type, waiver_amount, waiver_details
             FROM admission_stage_skip_audit
             WHERE application_id=? AND waiver_type IS NOT NULL
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$applicationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $details = json_decode((string) ($row['waiver_details'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $type = trim((string) ($row['waiver_type'] ?? ($details['waiver_type'] ?? '')));
        $registrationType = (string) ($details['registration_fee_waiver_type'] ?? 'none');
        $registrationValue = (float) ($details['registration_fee_waiver_value'] ?? 0);
        $registration = !empty($details['registration_fee_waived']);
        $schoolType = (string) ($details['school_fee_waiver_type'] ?? 'none');
        $schoolValue = (float) ($details['school_fee_waiver_value'] ?? 0);

        // Backward-compatible interpretation of previously saved exceptions.
        if ($type === 'registration_fee_waiver') $registration = true;
        if (in_array($type, ['full_waiver', 'sponsored'], true)) {
            $registration = true;
            if ($schoolType === 'none') $schoolType = 'full';
        }
        if ($registration && $registrationType === 'none') $registrationType = 'full';
        if (!in_array($schoolType, ['none', 'full', 'percentage', 'fixed'], true)) $schoolType = 'none';
        if ($schoolType === 'percentage') $schoolValue = min(100, max(0, $schoolValue));
        if ($schoolType === 'fixed') $schoolValue = max(0, $schoolValue);
        if (!in_array($registrationType, ['none', 'full', 'percentage', 'fixed'], true)) $registrationType = 'none';
        if ($registrationType === 'percentage') $registrationValue = min(100, max(0, $registrationValue));
        if ($registrationType === 'fixed') $registrationValue = max(0, $registrationValue);
        $registration = $registrationType !== 'none';

        return [
            'waiver_type' => $type ?: null,
            'registration_fee_waived' => $registration,
            'registration_fee_waiver_type' => $registrationType,
            'registration_fee_waiver_value' => $registrationValue,
            'school_fee_waiver_type' => $schoolType,
            'school_fee_waiver_value' => $schoolValue,
            'reason' => (string) ($details['reason'] ?? ''),
        ];
    }

    /** Shared financial-record projection for an enrolled learner. */
    public function studentFinancialRelief(int $studentId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT aa.id AS application_id, aa.application_no, aa.academic_year,
                    a.waiver_type, a.waiver_amount, a.waiver_details, a.skipped_at, a.skipped_by
               FROM " . ReadReplicaService::qualifiedRef("admission_applications") . "
               JOIN admission_stage_skip_audit a
                 ON a.id = (SELECT a2.id FROM admission_stage_skip_audit a2
                              WHERE a2.application_id = aa.id AND a2.waiver_type IS NOT NULL
                              ORDER BY a2.id DESC LIMIT 1)
              WHERE aa.enrolled_student_id = ? ORDER BY aa.id DESC LIMIT 1"
        );
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $details = json_decode((string) ($row['waiver_details'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $registrationType = (string) ($details['registration_fee_waiver_type'] ?? 'none');
        $schoolType = (string) ($details['school_fee_waiver_type'] ?? 'none');
        if ($row['waiver_type'] === 'registration_fee_waiver') $registrationType = 'full';
        if (in_array($row['waiver_type'], ['full_waiver', 'sponsored'], true)) {
            if ($registrationType === 'none') $registrationType = 'full';
            if ($schoolType === 'none' && $row['waiver_type'] === 'full_waiver') $schoolType = 'full';
        }
        $schoolAmount = $this->db->prepare(
            "SELECT COALESCE(SUM(sponsored_waiver_amount), 0)
               FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations_enrolled") . "
              WHERE student_id = ?"
        );
        $schoolAmount->execute([$studentId]);
        $registrationAmount = $this->db->prepare(
            "SELECT COALESCE(SUM(eao.amount_waived), 0)
               FROM extra_charge_application_obligations eao
              WHERE eao.application_id = ?"
        );
        $registrationAmount->execute([(int) $row['application_id']]);
        $row['registration_fee_waiver_type'] = $registrationType;
        $row['registration_fee_waiver_value'] = (float) ($details['registration_fee_waiver_value'] ?? 0);
        $row['school_fee_waiver_type'] = $schoolType;
        $row['school_fee_waiver_value'] = (float) ($details['school_fee_waiver_value'] ?? 0);
        $row['registration_fee_waived'] = $registrationType !== 'none';
        $row['school_fee_waived'] = $schoolType !== 'none';
        $row['registration_fee_waived_amount'] = (float) ($registrationAmount->fetchColumn() ?: 0);
        $row['school_fee_waived_amount'] = (float) ($schoolAmount->fetchColumn() ?: 0);
        $row['reason_code'] = $details['reason_code'] ?? null;
        $row['reason'] = $details['reason'] ?? null;
        $row['authorized_at'] = $details['authorized_at'] ?? $row['skipped_at'];
        $row['authorized_by'] = $details['authorized_by'] ?? $row['skipped_by'];
        unset($row['waiver_details']);
        return $row;
    }

    /** Apply the stored admission relief to generated learner obligations. */
    public function applyAdmissionFinancialRelief(int $applicationId, ?int $enrollmentId = null): array
    {
        $relief = $this->admissionFinancialRelief($applicationId);
        if (!$relief['registration_fee_waived'] && $relief['school_fee_waiver_type'] === 'none') return $relief;

        $this->resolveAdmissionObligations($applicationId);
        if ($relief['registration_fee_waived']) {
            $registrationRows = $this->db->prepare(
                "SELECT id, amount_due, amount_paid, amount_waived, status
                   FROM extra_charge_application_obligations
                  WHERE application_id=? AND status IN ('pending','partial')
                  ORDER BY id FOR UPDATE"
            );
            $registrationRows->execute([$applicationId]);
            $remaining = ($relief['registration_fee_waiver_type'] ?? 'full') === 'fixed'
                ? (float) ($relief['registration_fee_waiver_value'] ?? 0) : 0.0;
            foreach ($registrationRows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $due = (float) $row['amount_due'];
                $paid = (float) $row['amount_paid'];
                $alreadyWaived = (float) ($row['amount_waived'] ?? 0);
                $balance = max(0, $due - $paid - $alreadyWaived);
                $type = $relief['registration_fee_waiver_type'] ?? 'full';
                $waiver = $type === 'full' ? $balance : ($type === 'percentage' ? $balance * (float) $relief['registration_fee_waiver_value'] / 100 : min($balance, max(0, $remaining)));
                if ($type === 'fixed') $remaining = max(0, $remaining - $waiver);
                $newWaived = min(max(0, $due - $paid), $alreadyWaived + $waiver);
                $status = ($paid + $newWaived) >= $due
                    ? ($paid > 0 ? 'paid' : 'waived')
                    : (($paid + $newWaived) > 0 ? 'partial' : $row['status']);
                $this->db->prepare("UPDATE extra_charge_application_obligations SET amount_waived=?, status=?, updated_at=NOW() WHERE id=?")
                    ->execute([$newWaived, $status, (int) $row['id']]);
            }
        }

        if ($enrollmentId && $relief['school_fee_waiver_type'] !== 'none') {
            $rows = $this->db->prepare(
                "SELECT id, amount_due, sponsored_waiver_amount, status
                 FROM student_fee_obligations
                 WHERE student_academic_enrollment_id=? ORDER BY academic_year_term_id, id FOR UPDATE"
            );
            $rows->execute([$enrollmentId]);
            $remaining = $relief['school_fee_waiver_type'] === 'fixed' ? $relief['school_fee_waiver_value'] : 0.0;
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $due = (float) $row['amount_due'];
                $waiver = $relief['school_fee_waiver_type'] === 'full'
                    ? $due
                    : ($relief['school_fee_waiver_type'] === 'percentage'
                        ? $due * $relief['school_fee_waiver_value'] / 100
                        : min($due, max(0, $remaining)));
                $waiver = round(min($due, max((float) $row['sponsored_waiver_amount'], $waiver)), 2);
                if ($relief['school_fee_waiver_type'] === 'fixed') $remaining = max(0, $remaining - $waiver);
                $status = $waiver >= $due ? 'paid' : ($waiver > 0 && $row['status'] === 'pending' ? 'partial' : $row['status']);
                $this->db->prepare(
                    "UPDATE student_fee_obligations
                     SET is_sponsored=1, sponsored_waiver_amount=?, status=?, updated_at=NOW()
                     WHERE id=?"
                )->execute([$waiver, $status, (int) $row['id']]);
            }
        }
        return $relief;
    }

    public function allocateAdmissionPayment(int $applicationId, int $paymentId, float $amount): void
    {
        $stmt = $this->db->prepare(
            'SELECT id,amount_due,amount_paid FROM extra_charge_application_obligations
             WHERE application_id=? AND status IN (\'pending\',\'partial\') ORDER BY id FOR UPDATE'
        );
        $stmt->execute([$applicationId]);
        $remaining = round($amount, 2);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $obligation) {
            if ($remaining <= 0) break;
            $balance = max(0, (float) $obligation['amount_due'] - (float) $obligation['amount_paid']);
            $allocated = min($remaining, $balance);
            if ($allocated <= 0) continue;
            $this->db->prepare(
                'INSERT INTO admission_payment_allocations(admission_payment_id,application_obligation_id,amount) VALUES(?,?,?)'
            )->execute([$paymentId, (int) $obligation['id'], $allocated]);
            $newPaid = round((float) $obligation['amount_paid'] + $allocated, 2);
            $status = $newPaid >= (float) $obligation['amount_due'] ? 'paid' : 'partial';
            $this->db->prepare(
                'UPDATE extra_charge_application_obligations SET amount_paid=?,status=? WHERE id=?'
            )->execute([$newPaid, $status, (int) $obligation['id']]);
            $remaining = round($remaining - $allocated, 2);
        }
    }

    /** Generate the current billable occurrence for an enrolled learner.
     * Recurring occurrences are regenerated by the scheduled billing command;
     * the unique key makes onboarding and rollover idempotent. */
    public function generateEnrollmentObligations(int $enrollmentId): int
    {
        $context = $this->db->prepare(
            "SELECT sae.student_id, sae.academic_year_id, s.student_type_id,
                    ayc.class_id, s.admission_no
             FROM " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae
             JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id=sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.id=sae.academic_year_class_stream_id
             JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id=aycs.academic_year_class_id
             WHERE sae.id=? LIMIT 1"
        );
        $context->execute([$enrollmentId]);
        $row = $context->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Enrollment not found.');

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO extra_charge_student_obligations
             (student_academic_enrollment_id,schedule_id,academic_year_term_id,due_date,quantity,unit_price,amount_due)
             SELECT ?, sch.id, sch.academic_year_term_id,
                    CASE
                      WHEN sch.frequency='per_term' AND sch.academic_year_term_id IS NOT NULL THEN ayt.opening_date
                      ELSE GREATEST(sch.starts_on, CURDATE())
                    END,
                    1, CASE WHEN ec.calculation_mode='per_unit' THEN ec.unit_price ELSE ec.amount END,
                    CASE WHEN ec.calculation_mode='per_unit' THEN ec.unit_price ELSE ec.amount END
             FROM extra_charge_schedules sch
             JOIN extra_charges ec ON ec.id=sch.extra_charge_id
             JOIN extra_charge_contexts ecc ON ecc.extra_charge_id=ec.id AND ecc.context_code='enrollment'
             LEFT JOIN academic_year_terms ayt ON ayt.id=sch.academic_year_term_id
             LEFT JOIN student_types st ON st.id=?
             WHERE ec.academic_year_id=? AND ec.status='active' AND ec.billing_model='added_to_fees'
               AND sch.status='active' AND sch.starts_on<=COALESCE(sch.ends_on, '9999-12-31')
               AND ec.calculation_mode='fixed'
               AND (ec.target_scope='all_students'
                 OR (ec.target_scope='boarders' AND st.code='BOARD')
                 OR (ec.target_scope='day_students' AND st.code='DAY')
                 OR (ec.target_scope='specific_class' AND EXISTS (SELECT 1 FROM extra_charge_classes xcc WHERE xcc.extra_charge_id=ec.id AND xcc.class_id=?))
                 OR EXISTS (SELECT 1 FROM extra_charge_student_types xst WHERE xst.extra_charge_id=ec.id AND xst.student_type_id=?))"
        );
        $stmt->execute([$enrollmentId, (int) $row['student_type_id'], (int) $row['academic_year_id'], (int) $row['class_id'], (int) $row['student_type_id']]);
        return $stmt->rowCount();
    }

    private function application(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admission_applications WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function academicYearId(string $value): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM academic_years WHERE year_code=? OR YEAR(start_date)=? OR id=? LIMIT 1'
        );
        $numeric = (int) $value;
        $stmt->execute([$value, $numeric, $numeric]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}
