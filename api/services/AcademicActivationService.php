<?php

namespace App\API\Services;

use PDO;

/**
 * Date-driven academic activation engine (soft year/term transition).
 *
 * The rollover workflow certifies a target year; this service performs the
 * actual switch when the calendar says it is due. Nothing here is triggered by
 * a button: the scheduled worker calls sweep() and the engine reconciles terms
 * against their dates and activates a certified target year on its start date.
 *
 * Why the outgoing year stays operational until its last day: 104 read sites
 * across 35 files answer "which year are we in?" with `is_current = 1`, and
 * academic_years.current_marker carries a UNIQUE index. Exactly one year can be
 * the operational pointer, so the overlap between two years is expressed by
 * dates and lifecycle statuses — never by two current pointers.
 */
class AcademicActivationService
{
    public const WORKFLOW_CODE = 'academic_year_transition';

    private PDO $db;
    private int $actorId;

    public function __construct(?PDO $db = null, ?int $actorId = null)
    {
        $this->db = $db ?: \App\Database\Database::getInstance()->getConnection();
        $this->actorId = $actorId ?: 1;
    }

    /**
     * Read-only handover picture for the UI and the context API.
     *
     * Reports the operational (outgoing) year, the prepared target year, the
     * date the switch is due, and whether the calendar has drifted from the
     * stored term statuses.
     */
    public function handover(?string $onDate = null): array
    {
        $today = $this->date($onDate);
        $operational = $this->operationalYear();
        $prepared = $this->preparedYear();

        $switchDue = false;
        $daysUntilSwitch = null;
        if ($prepared !== null) {
            $switchDate = (string) ($prepared['switch_date'] ?? '');
            $daysUntilSwitch = $switchDate !== ''
                ? (int) floor(((strtotime($switchDate) - strtotime($today)) / 86400))
                : null;
            // The switch is due once the target year has opened; a certified
            // year is switched, an uncertified one is deferred and reported.
            $switchDue = $switchDate !== '' && $today >= $switchDate;
        }

        return [
            'on_date' => $today,
            'operational_year' => $operational ? [
                'id' => (int) $operational['id'],
                'year_code' => (string) $operational['year_code'],
                'year_name' => (string) $operational['year_name'],
                'start_date' => (string) $operational['start_date'],
                'end_date' => (string) $operational['end_date'],
                'state' => (string) $operational['state'],
                'status' => (string) $operational['status'],
            ] : null,
            'prepared_year' => $prepared ? [
                'id' => (int) $prepared['id'],
                'year_code' => (string) $prepared['year_code'],
                'year_name' => (string) $prepared['year_name'],
                'start_date' => (string) $prepared['start_date'],
                'end_date' => (string) $prepared['end_date'],
                'state' => (string) $prepared['state'],
                'certified' => (bool) $prepared['certified'],
                'certified_at' => $prepared['certified_at'] !== null ? (string) $prepared['certified_at'] : null,
                'instance_id' => $prepared['instance_id'] !== null ? (int) $prepared['instance_id'] : null,
                'failed_checks' => $this->failedChecks($prepared),
            ] : null,
            'switch_date' => $prepared['switch_date'] ?? null,
            'switch_due' => $switchDue,
            'days_until_switch' => $daysUntilSwitch,
            'switch_blocked_reason' => $this->switchBlockedReason($prepared, $switchDue),
            'outgoing_summary' => $operational ? $this->outgoingSummary((int) $operational['id']) : null,
            'terms_out_of_sync' => $this->countOutOfSyncTerms($today),
        ];
    }

    /**
     * The engine: reconcile term statuses against the calendar, then activate a
     * certified target year when its start date has arrived.
     *
     * @return array{on_date:string,terms_reconciled:int,term_changes:array[],cutover:array|null,deferred:array|null}
     */
    public function sweep(?string $onDate = null, bool $dryRun = false): array
    {
        $today = $this->date($onDate);

        $termChanges = $this->reconcileTerms($today, $dryRun);

        $result = [
            'on_date' => $today,
            'terms_reconciled' => count($termChanges),
            'term_changes' => $termChanges,
            'cutover' => null,
            'deferred' => null,
        ];

        if ($dryRun) {
            $result['dry_run'] = true;
        }

        $prepared = $this->preparedYear();
        if ($prepared === null) {
            return $result;
        }

        $switchDate = (string) ($prepared['switch_date'] ?? '');
        if ($switchDate === '' || $today < $switchDate) {
            return $result;
        }

        if (empty($prepared['certified'])) {
            $result['deferred'] = [
                'reason' => 'target_year_not_certified',
                'target_year_id' => (int) $prepared['id'],
                'target_year_code' => (string) $prepared['year_code'],
                'failed_checks' => $this->failedChecks($prepared),
                'message' => 'The target academic year has opened but its rollover readiness checks have not all passed. '
                    . 'The outgoing year remains operational until the target is certified.',
            ];
            \App\API\Services\Logger::warning(
                'academic_activation',
                'Target academic year reached its switch date but is not certified; switch deferred',
                [
                    'target_year_id' => (int) $prepared['id'],
                    'target_year_code' => (string) $prepared['year_code'],
                    'failed_checks' => $this->failedChecks($prepared),
                ]
            );
            return $result;
        }

        $result['cutover'] = $this->performCutover($prepared, $today, $dryRun);

        return $result;
    }

    // ── internals ───────────────────────────────────────────────────────────

    private function date(?string $onDate): string
    {
        if ($onDate === null || $onDate === '') {
            return date('Y-m-d');
        }
        $ts = strtotime($onDate);
        return $ts ? date('Y-m-d', $ts) : date('Y-m-d');
    }

    private function operationalYear(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, year_code, year_name, start_date, end_date, state, status
             FROM academic_years WHERE is_current = 1 ORDER BY start_date DESC, id DESC LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * The year being prepared: the nearest upcoming year that already exists.
     * A year whose start date has passed is still "prepared" until the switch
     * runs, so the UI can show a pending handover.
     */
    private function preparedYear(): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, year_code, year_name, start_date, end_date, state, status
             FROM academic_years
             WHERE state = 'upcoming'
             ORDER BY start_date ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute();
        $year = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$year) {
            return null;
        }

        $instance = $this->findRolloverInstance((int) $year['id']);
        $instanceData = $instance['data'] ?? [];

        return [
            'id' => (int) $year['id'],
            'year_code' => (string) $year['year_code'],
            'year_name' => (string) $year['year_name'],
            'start_date' => (string) $year['start_date'],
            'end_date' => (string) $year['end_date'],
            'state' => (string) $year['state'],
            'status' => (string) $year['status'],
            // Always report the date the switch is due, certified or not: the
            // handover view has to show the operator when the year will open.
            'switch_date' => (string) $year['start_date'],
            'certified' => $instance['certified'],
            'certified_at' => $instance['certified_at'],
            'instance_id' => $instance['instance_id'],
            // Kept even when uncertified so the handover panel can show the
            // operator exactly which readiness checks are still blocking.
            'validation_results' => $instanceData['validation_results'] ?? null,
        ];
    }

    /**
     * The rollover workflow instance for a target year, whether or not it has
     * been certified. validateReadiness() records its checks on the instance, so
     * this is the authoritative "what is still blocking" source.
     */
    private function findRolloverInstance(int $yearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT wi.id, wi.status, wi.started_by, wi.data_json
             FROM workflow_instances wi
             JOIN workflow_definitions wd ON wd.id = wi.workflow_id
             WHERE wd.code = ?
               AND CAST(JSON_EXTRACT(wi.data_json, '$.academic_year_id') AS UNSIGNED) = ?
             ORDER BY wi.id DESC
             LIMIT 1"
        );
        $stmt->execute([self::WORKFLOW_CODE, $yearId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return ['instance_id' => null, 'certified' => false, 'certified_at' => null, 'data' => []];
        }

        $data = json_decode((string) $row['data_json'], true) ?: [];
        $certifiedAt = $data['certified_at'] ?? null;
        $isCertified = is_string($certifiedAt) && trim($certifiedAt) !== '' && strtolower($certifiedAt) !== 'null';

        return [
            'instance_id' => (int) $row['id'],
            'instance_status' => (string) $row['status'],
            'started_by' => (int) $row['started_by'],
            'certified' => $isCertified,
            'certified_at' => $isCertified ? $certifiedAt : null,
            'data' => $data,
        ];
    }

    /** @return array<int,string> Check keys that are currently failing. */
    private function failedChecks(array $prepared): array
    {
        $results = is_array($prepared['validation_results'] ?? null) ? $prepared['validation_results'] : [];
        $failed = [];
        foreach ($results as $key => $check) {
            if (is_array($check) && ($check['status'] ?? '') === 'fail') {
                $failed[] = (string) $key;
            }
        }

        return $failed;
    }

    private function switchBlockedReason(?array $prepared, bool $switchDue): ?string
    {
        if ($prepared === null || !$switchDue) {
            return null;
        }
        if (empty($prepared['certified'])) {
            $failed = $this->failedChecks($prepared);
            return $failed === []
                ? 'The target year has not been certified yet.'
                : 'The target year is not ready: ' . implode(', ', $failed) . '.';
        }

        return null;
    }

    /**
     * Term status is a pure function of the calendar: the term that contains
     * today is current, earlier terms are completed, later terms upcoming.
     * Running this every sweep is what stops the statuses drifting out of sync
     * with the dates (the pre-existing data had completed terms in a year that
     * had never opened, and a term still marked current weeks after it closed).
     */
    private function reconcileTerms(string $today, bool $dryRun): array
    {
        $stmt = $this->db->prepare(
            "SELECT ayt.id, ayt.academic_year_id, ayt.term_id, ayt.opening_date, ayt.closing_date, ayt.status,
                    ay.year_code
             FROM academic_year_terms ayt
             JOIN academic_years ay ON ay.id = ayt.academic_year_id
             WHERE ayt.opening_date IS NOT NULL AND ayt.closing_date IS NOT NULL
             ORDER BY ayt.academic_year_id, ayt.term_id"
        );
        $stmt->execute();
        $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $changes = [];
        foreach ($terms as $term) {
            $desired = $this->desiredTermStatus($term, $today);
            $actual = (string) $term['status'];
            if ($desired === $actual) {
                continue;
            }

            $changes[] = [
                'term_id' => (int) $term['id'],
                'academic_year_id' => (int) $term['academic_year_id'],
                'year_code' => (string) $term['year_code'],
                'term_number' => (int) $term['term_id'],
                'from' => $actual,
                'to' => $desired,
            ];

            if ($dryRun) {
                continue;
            }

            $update = $this->db->prepare("UPDATE academic_year_terms SET status = ? WHERE id = ?");
            $update->execute([$desired, (int) $term['id']]);
        }

        return $changes;
    }

    private function desiredTermStatus(array $term, string $today): string
    {
        if ($today < (string) $term['opening_date']) {
            return 'upcoming';
        }
        if ($today > (string) $term['closing_date']) {
            return 'completed';
        }

        return 'current';
    }

    private function countOutOfSyncTerms(string $today): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM academic_year_terms
             WHERE opening_date IS NOT NULL AND closing_date IS NOT NULL
               AND status <> CASE
                   WHEN ? < opening_date THEN 'upcoming'
                   WHEN ? > closing_date THEN 'completed'
                   ELSE 'current'
               END"
        );
        $stmt->execute([$today, $today]);

        return (int) $stmt->fetchColumn();
    }

    private function performCutover(array $prepared, string $today, bool $dryRun): array
    {
        $targetId = (int) $prepared['id'];
        $operational = $this->operationalYear();
        $outgoingId = $operational ? (int) $operational['id'] : 0;
        $actorId = (int) ($prepared['instance_id'] !== null ? ($this->actorFor((int) $prepared['instance_id']) ?? $this->actorId) : $this->actorId);

        $plan = [
            'target_year_id' => $targetId,
            'target_year_code' => (string) $prepared['year_code'],
            'outgoing_year_id' => $outgoingId ?: null,
            'outgoing_year_code' => $operational ? (string) $operational['year_code'] : null,
            'activated_on' => $today,
            'instance_id' => $prepared['instance_id'] !== null ? (int) $prepared['instance_id'] : null,
        ];

        if ($dryRun) {
            $plan['dry_run'] = true;
            return $plan;
        }

        // The unique index on academic_years.current_marker allows exactly
        // one is_current row, so the outgoing pointer is cleared before the
        // target is set, inside this transaction. Only open one when the
        // caller has not already opened it.
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }
        try {
            // The unique index on academic_years.current_marker allows exactly
            // one is_current row, so the outgoing pointer is cleared before the
            // target is set, inside this transaction.
            if ($outgoingId > 0 && $outgoingId !== $targetId) {
                $this->db->prepare(
                    "UPDATE academic_years
                        SET state = 'completed',
                            status = 'active',
                            is_current = 0,
                            completed_at = NOW(),
                            completed_by = ?
                      WHERE id = ?"
                )->execute([$actorId, $outgoingId]);
            }

            $this->db->prepare("UPDATE academic_years SET is_current = 0 WHERE id <> ?")->execute([$targetId]);

            $this->db->prepare(
                "UPDATE academic_years SET state = 'current', status = 'active', is_current = 1 WHERE id = ?"
            )->execute([$targetId]);

            // Learners who graduated at the end of the outgoing year leave the
            // school when that year closes. Promotion marks only their
            // enrollment; until the cutover they are still on the outgoing
            // year's roll, so their learner record changes here, not earlier.
            // Only learners with no enrollment in a later year are affected.
            $graduates = 0;
            if ($outgoingId > 0 && $outgoingId !== $targetId) {
                $graduation = $this->db->prepare(
                    "UPDATE students s
                        JOIN student_academic_enrollments sae
                          ON sae.student_id = s.id AND sae.academic_year_id = ? AND sae.enrollment_status = 'graduated'
                        SET s.status = 'graduated', s.updated_at = NOW()
                      WHERE s.status = 'active'
                        AND NOT EXISTS (
                            SELECT 1 FROM student_academic_enrollments later
                              JOIN academic_years lay ON lay.id = later.academic_year_id
                              JOIN academic_years oay ON oay.id = sae.academic_year_id
                             WHERE later.student_id = s.id AND lay.start_date > oay.start_date)"
                );
                $graduation->execute([$outgoingId]);
                $graduates = $graduation->rowCount();
            }
            $plan['learners_graduated'] = $graduates;

            if ($prepared['instance_id'] !== null) {
                $this->db->prepare(
                    "UPDATE workflow_instances
                        SET status = 'completed',
                            completed_at = NOW(),
                            data_json = JSON_SET(COALESCE(data_json, '{}'), '$.cutover_completed_at', ?)
                      WHERE id = ?"
                )->execute([date('Y-m-d H:i:s'), (int) $prepared['instance_id']]);
            }

            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        \App\API\Services\Logger::audit(
            'academic_year_cutover',
            'academic_years',
            $targetId,
            'Automatic academic year cutover executed',
            [
                'target_year_id' => $targetId,
                'target_year_code' => (string) $prepared['year_code'],
                'outgoing_year_id' => $outgoingId ?: null,
                'outgoing_year_code' => $operational ? (string) $operational['year_code'] : null,
                'activated_on' => $today,
                'instance_id' => $prepared['instance_id'],
                'learners_graduated' => $plan['learners_graduated'] ?? 0,
            ]
        );

        return $plan;
    }

    private function actorFor(int $instanceId): ?int
    {
        $stmt = $this->db->prepare("SELECT started_by FROM workflow_instances WHERE id = ? LIMIT 1");
        $stmt->execute([$instanceId]);
        $value = $stmt->fetchColumn();

        return $value ? (int) $value : null;
    }

    /** Small, safe summary of the year that is still operational. */
    private function outgoingSummary(int $yearId): array
    {
        $summary = [
            'enrollments_total' => 0,
            'enrollments_active' => 0,
            'enrollments_completed' => 0,
            'current_term' => null,
            'term_end_date' => null,
        ];

        try {
            $enrol = $this->db->prepare(
                "SELECT enrollment_status, COUNT(*) AS total
                 FROM student_academic_enrollments
                 WHERE academic_year_id = ?
                 GROUP BY enrollment_status"
            );
            $enrol->execute([$yearId]);
            foreach ($enrol->fetchAll(PDO::FETCH_KEY_PAIR) as $status => $count) {
                $summary['enrollments_total'] += (int) $count;
                if ($status === 'active') {
                    $summary['enrollments_active'] = (int) $count;
                } elseif ($status === 'completed') {
                    $summary['enrollments_completed'] = (int) $count;
                }
            }
        } catch (\Throwable $e) {
            // A summary must never break the handover view.
        }

        try {
            $term = $this->db->prepare(
                "SELECT term_id, opening_date, closing_date, status
                 FROM academic_year_terms
                 WHERE academic_year_id = ?
                 ORDER BY CASE WHEN status = 'current' THEN 0 ELSE 1 END, closing_date
                 LIMIT 1"
            );
            $term->execute([$yearId]);
            $row = $term->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $summary['current_term'] = 'Term ' . (int) $row['term_id'];
                $summary['term_end_date'] = (string) $row['closing_date'];
                $summary['term_status'] = (string) $row['status'];
            }
        } catch (\Throwable $e) {
            // ignored: summary only
        }

        return $summary;
    }
}
