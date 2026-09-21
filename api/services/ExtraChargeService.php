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
             FROM extra_charges ec
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
        foreach ($obligations as $obligation) {
            $total += max(0, (float) $obligation['amount_due'] - (float) ($obligation['amount_paid'] ?? 0));
        }
        return round($total, 2);
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
             FROM student_academic_enrollments sae
             JOIN students s ON s.id=sae.student_id
             JOIN academic_year_class_streams aycs ON aycs.id=sae.academic_year_class_stream_id
             JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
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
                 OR (ec.target_scope='boarders' AND st.code IN ('BOARD','WEEKLY'))
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
