<?php

namespace App\API\Modules\academic;

use App\API\Services\ReadReplicaService;
use Exception;
use PDO;
use function App\API\Includes\formatResponse;

/**
 * Atomic term transition service for normalized academic_year_terms.
 *
 * Explicit IDs are required for from/to terms and academic year; the service
 * does not infer context from CURDATE().
 */
class TermTransitionService
{
    private PDO $db;
    private ?int $userId;

    public function __construct(PDO $db, ?int $userId = null)
    {
        $this->db = $db;
        $this->userId = $userId;
    }

    public function getContext(array $params = []): array
    {
        try {
            $academicYearId = (int) ($params['academic_year_id'] ?? 0);
            $where = $academicYearId > 0 ? 'WHERE academic_year_id = ?' : '';
            $args = $academicYearId > 0 ? [$academicYearId] : [];

            $stmt = $this->db->prepare(
                "SELECT academic_year_term_id AS id, academic_year_id, term_id,
                        opening_date AS start_date, closing_date AS end_date,
                        term_period_status AS status, term_name AS name, term_code AS code
                 FROM " . ReadReplicaService::qualifiedRef('academic_term') . "
                 {$where}
                 ORDER BY academic_year_term_id"
            );
            $stmt->execute($args);
            $terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $current = null;
            $next = null;
            foreach ($terms as $term) {
                if (($term['status'] ?? '') === 'current') {
                    $current = $term;
                }
            }
            if ($current) {
                foreach ($terms as $term) {
                    if ((int) $term['id'] > (int) $current['id'] && ($term['status'] ?? '') === 'upcoming') {
                        $next = $term;
                        break;
                    }
                }
            }

            $readiness = $current ? $this->termResultReadiness((int) $current['id']) : null;
            $lessonPlanSummary = $current ? $this->lessonPlanSummary((int) $current['id']) : null;

            return formatResponse(true, [
                'terms' => $terms,
                'current_term' => $current,
                'next_term' => $next,
                'result_readiness' => $readiness,
                'lesson_plan_summary' => $lessonPlanSummary,
            ], 'Term transition context retrieved');
        } catch (Exception $e) {
            return formatResponse(false, null, $e->getMessage());
        }
    }

    /** Return checklist counts without exposing lesson-plan rows to this page. */
    private function lessonPlanSummary(int $termId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN lp.status = 'approved' THEN 1 ELSE 0 END) AS approved,
                SUM(CASE WHEN lp.status IN ('draft', 'submitted') THEN 1 ELSE 0 END) AS pending
             FROM " . ReadReplicaService::qualifiedRef('lesson_plans') . " lp
             LEFT JOIN " . ReadReplicaService::qualifiedRef('academic_year_calendar_days') . " aycd
                ON aycd.id = lp.academic_year_calendar_day_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('academic_year_calendar') . " ayc
                ON ayc.id = aycd.academic_year_calendar_id
             WHERE ayc.academic_year_term_id = ?"
        );
        $stmt->execute([$termId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'approved' => (int) ($row['approved'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
        ];
    }

    public function execute(array $payload): array
    {
        $fromTermId = (int) ($payload['from_term_id'] ?? 0);
        $toTermId = (int) ($payload['to_term_id'] ?? 0);
        $academicYearId = (int) ($payload['academic_year_id'] ?? 0);
        $rolloverTimetable = (bool) ($payload['rollover_timetable'] ?? false);
        $keepTeachers = (bool) ($payload['keep_teachers'] ?? true);
        $keepRooms = (bool) ($payload['keep_rooms'] ?? true);
        $dates = $payload['dates'] ?? [];

        if ($fromTermId <= 0 || $toTermId <= 0 || $academicYearId <= 0) {
            return formatResponse(false, null, 'from_term_id, to_term_id and academic_year_id are required');
        }

        $logId = 0;
        try {
            $fromTerm = $this->getYearTerm($fromTermId, $academicYearId);
            $toTerm = $this->getYearTerm($toTermId, $academicYearId);
            if (!$fromTerm || !$toTerm) {
                return formatResponse(false, null, 'Both terms must belong to the selected academic year');
            }
            if ($fromTermId === $toTermId) {
                return formatResponse(false, null, 'from_term_id and to_term_id must differ');
            }
            if (($fromTerm['status'] ?? '') !== 'current') {
                return formatResponse(false, null, 'Only the current term can be closed');
            }

            // A term may not be closed while its assessment cycle is still open.
            // TermResultsService only consumes approved evidence, so closing a
            // term with unpublished registers would leave the official record
            // permanently incomplete for the learners whose marks never landed.
            // A named override exists for the case where the school genuinely
            // holds no marks for a term, but it must be declared and journalled.
            $readiness = $this->termResultReadiness($fromTermId);
            if (!$readiness['ready']) {
                $overrideReason = trim((string) ($payload['force_reason'] ?? ''));
                if ($overrideReason === '') {
                    return formatResponse(false, $readiness, $readiness['message']);
                }
                $this->logAction($fromTermId, $toTermId, $academicYearId, 'results_readiness_overridden', 'completed', $readiness + ['reason' => $overrideReason]);
            }
            if (($toTerm['status'] ?? '') !== 'upcoming' || (int) $toTerm['term_id'] !== (int) $fromTerm['term_id'] + 1) {
                return formatResponse(false, null, 'The target must be the next upcoming term in the same academic year');
            }
            if (!empty($dates['start_date']) && !empty($dates['end_date']) && $dates['start_date'] >= $dates['end_date']) {
                return formatResponse(false, null, 'Next-term opening date must be before its closing date');
            }

            $this->db->beginTransaction();
            $logId = $this->logAction($fromTermId, $toTermId, $academicYearId, 'full_transition', 'in_progress', [
                'rollover_timetable' => $rolloverTimetable,
                'keep_teachers' => $keepTeachers,
                'keep_rooms' => $keepRooms,
            ]);

            $this->closeTerm($fromTermId, $academicYearId);

            $timetableCopied = 0;
            if ($rolloverTimetable) {
                $timetableCopied = $this->rolloverTimetable($fromTermId, $toTermId, $academicYearId, $keepTeachers, $keepRooms);
            }

            $this->updateTargetDates($toTermId, $academicYearId, $dates);
            $this->activateTerm($toTermId, $academicYearId);

            $this->updateLog($logId, 'completed', [
                'timetable_copied' => $timetableCopied,
                'from_term_id' => $fromTermId,
                'to_term_id' => $toTermId,
            ]);
            $this->recordSystemEvent($fromTermId, $toTermId, $academicYearId, $timetableCopied);
            $this->db->commit();

            return formatResponse(true, [
                'log_id' => $logId,
                'timetable_copied' => $timetableCopied,
                'from_term_id' => $fromTermId,
                'to_term_id' => $toTermId,
                'academic_year_id' => $academicYearId,
            ], 'Term transition completed successfully');
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($logId > 0) {
                $this->updateLog($logId, 'failed', ['error' => $e->getMessage()], $e->getMessage());
            }
            return formatResponse(false, null, $e->getMessage());
        }
    }

    private function getYearTerm(int $termId, int $academicYearId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, academic_year_id, term_id, status, opening_date, closing_date
             FROM academic_year_terms
             WHERE id = ? AND academic_year_id = ?
             LIMIT 1"
        );
        $stmt->execute([$termId, $academicYearId]);
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        return $term ?: null;
    }

    private function closeTerm(int $fromTermId, int $academicYearId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE academic_year_terms
             SET status = 'completed'
             WHERE id = ? AND academic_year_id = ?"
        );
        $stmt->execute([$fromTermId, $academicYearId]);
        $this->logAction($fromTermId, null, $academicYearId, 'close_term', 'completed', ['from_term_id' => $fromTermId]);
    }

    private function rolloverTimetable(int $fromTermId, int $toTermId, int $academicYearId, bool $keepTeachers, bool $keepRooms): int
    {
        // timetable_entries is the live schedule table in the normalized schema
        // (one row per stream/day/time-slot). Entries reference the year's class
        // stream and term directly, so a rollover copies them to the target term
        // on the same streams.
        $source = $this->db->prepare(
            "SELECT academic_year_class_stream_id, day_of_week, time_slot_id,
                    learning_area_id, teacher_id, status
             FROM timetable_entries
             WHERE academic_year_term_id = ? AND status = 'scheduled'"
        );
        $source->execute([$fromTermId]);
        $entries = $source->fetchAll(PDO::FETCH_ASSOC);

        // Idempotency: never duplicate a slot already present on the target term.
        $exists = $this->db->prepare(
            "SELECT COUNT(*) FROM timetable_entries
             WHERE academic_year_class_stream_id = ? AND academic_year_term_id = ?
               AND day_of_week = ? AND time_slot_id = ?"
        );

        $insert = $this->db->prepare(
            "INSERT INTO timetable_entries (
                academic_year_class_stream_id, academic_year_term_id,
                day_of_week, time_slot_id, learning_area_id, teacher_id, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $copied = 0;
        foreach ($entries as $entry) {
            $exists->execute([
                (int) $entry['academic_year_class_stream_id'],
                $toTermId,
                (int) $entry['day_of_week'],
                (int) $entry['time_slot_id'],
            ]);
            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }

            $insert->execute([
                (int) $entry['academic_year_class_stream_id'],
                $toTermId,
                (int) $entry['day_of_week'],
                (int) $entry['time_slot_id'],
                $entry['learning_area_id'] !== null ? (int) $entry['learning_area_id'] : null,
                $keepTeachers && $entry['teacher_id'] !== null ? (int) $entry['teacher_id'] : null,
                'scheduled',
            ]);
            $copied++;
        }

        $this->logAction($fromTermId, $toTermId, $academicYearId, 'rollover_timetable', 'completed', [
            'copied' => $copied,
            'keep_teachers' => $keepTeachers,
            'keep_rooms' => $keepRooms,
        ]);

        return $copied;
    }

    private function updateTargetDates(int $toTermId, int $academicYearId, array $dates): void
    {
        $startDate = $dates['start_date'] ?? null;
        $endDate = $dates['end_date'] ?? null;
        $halfTermStart = $dates['half_term_start'] ?? null;
        $halfTermEnd = $dates['half_term_end'] ?? null;

        // Normalize blank half-term fields to null so an empty value clears
        // them ("no half-term for this term").
        $halfTermStart = ($halfTermStart !== null && $halfTermStart !== '') ? $halfTermStart : null;
        $halfTermEnd = ($halfTermEnd !== null && $halfTermEnd !== '') ? $halfTermEnd : null;

        if ($startDate === null && $endDate === null && $halfTermStart === null && $halfTermEnd === null) {
            return;
        }

        // Opening/closing dates use COALESCE (never cleared through this path);
        // half-term dates are set directly so they can be removed.
        $stmt = $this->db->prepare(
            "UPDATE academic_year_terms
             SET opening_date = COALESCE(?, opening_date),
                 closing_date = COALESCE(?, closing_date),
                 half_term_start = ?,
                 half_term_end = ?
             WHERE id = ? AND academic_year_id = ?"
        );
        $stmt->execute([
            $startDate ?: null,
            $endDate ?: null,
            $halfTermStart,
            $halfTermEnd,
            $toTermId,
            $academicYearId,
        ]);
    }

    private function activateTerm(int $toTermId, int $academicYearId): void
    {
        $this->db->prepare(
            "UPDATE academic_year_terms
             SET status = 'upcoming'
             WHERE academic_year_id = ? AND status = 'current' AND id <> ?"
        )->execute([$academicYearId, $toTermId]);

        $this->db->prepare(
            "UPDATE academic_year_terms
             SET status = 'current'
             WHERE id = ? AND academic_year_id = ?"
        )->execute([$toTermId, $academicYearId]);

        $this->logAction(null, $toTermId, $academicYearId, 'activate_term', 'completed', ['to_term_id' => $toTermId]);
    }

    /**
     * Whether the term's assessment cycle is closed enough to move on.
     *
     * The blocker is registers, not learners: a register (class-stream x
     * learning area) that is still pending submission, submitted, or awaiting
     * approval holds marks that TermResultsService will never fold into the
     * official term results. Enrollments are per academic year and carry no
     * term, so learner coverage is measured through the registers themselves.
     *
     * @return array{ready:bool,message:string,pending_registers:int,pending_learners:int}
     */
    public function termResultReadiness(int $termId): array
    {
        $registerStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM assessments
             WHERE academic_year_term_id = ?
               AND status IN ('pending_submission','submitted','pending_approval')"
        );
        $registerStmt->execute([$termId]);
        $pendingRegisters = (int) $registerStmt->fetchColumn();

        $learnerStmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT ar.student_academic_enrollment_id)
             FROM assessment_results ar
             JOIN assessments a ON a.id = ar.assessment_id
             WHERE a.academic_year_term_id = ?
               AND ar.deleted_at IS NULL
               AND ar.is_approved = 0"
        );
        $learnerStmt->execute([$termId]);
        $pendingLearners = (int) $learnerStmt->fetchColumn();

        if ($pendingRegisters === 0 && $pendingLearners === 0) {
            return [
                'ready' => true,
                'message' => 'All examination registers for this term are approved.',
                'pending_registers' => 0,
                'pending_learners' => 0,
            ];
        }

        $parts = [];
        if ($pendingRegisters > 0) {
            $parts[] = $pendingRegisters . ' learning-area register(s) are not yet approved';
        }
        if ($pendingLearners > 0) {
            $parts[] = $pendingLearners . ' learner result(s) are not yet approved';
        }

        return [
            'ready' => false,
            'message' => 'Close the assessment cycle before ending this term: ' . implode(' and ', $parts)
                . '. Publish the results, or send the registers back to the subject teachers.',
            'pending_registers' => $pendingRegisters,
            'pending_learners' => $pendingLearners,
        ];
    }

    private function logAction(?int $fromTermId, ?int $toTermId, int $academicYearId, string $action, string $status, array $details = [], ?string $error = null): int
    {
        \App\API\Includes\FileLogger::write('term_transition', [
            'type' => 'term_transition',
            'from_term_id' => $fromTermId,
            'to_term_id' => $toTermId,
            'academic_year_id' => $academicYearId,
            'action' => $action,
            'status' => $status,
            'details' => $details,
            'error_message' => $error,
            'performed_by' => $this->userId,
        ]);
        return (int) (microtime(true) * 1000);
    }

    private function updateLog(int $logId, string $status, array $details = [], ?string $error = null): void
    {
        \App\API\Includes\FileLogger::write('term_transition', [
            'type' => 'term_transition_update',
            'log_id' => $logId,
            'status' => $status,
            'details' => $details,
            'error_message' => $error,
        ]);
    }

    /**
     * Write a system_events entry so the context/audit layer knows the school
     * has moved into a new term.
     */
    private function recordSystemEvent(int $fromTermId, int $toTermId, int $academicYearId, int $timetableCopied): void
    {
        \App\API\Includes\FileLogger::write('events', [
            'type' => 'system_event',
            'event' => 'term_transitioned',
            'data' => [
                'from_academic_year_term_id' => $fromTermId,
                'to_academic_year_term_id' => $toTermId,
                'academic_year_id' => $academicYearId,
                'timetable_copied' => $timetableCopied,
                'performed_by' => $this->userId,
            ],
        ]);
    }
}
