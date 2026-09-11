<?php

namespace App\API\Modules\academic;

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
            $where = $academicYearId > 0 ? 'WHERE ayt.academic_year_id = ?' : '';
            $args = $academicYearId > 0 ? [$academicYearId] : [];

            $stmt = $this->db->prepare(
                "SELECT ayt.id, ayt.academic_year_id, ayt.term_id, ayt.opening_date AS start_date,
                        ayt.closing_date AS end_date, ayt.status, t.name, t.code
                 FROM academic_year_terms ayt
                 JOIN terms t ON t.id = ayt.term_id
                 {$where}
                 ORDER BY ayt.id"
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

            return formatResponse(true, [
                'terms' => $terms,
                'current_term' => $current,
                'next_term' => $next,
            ], 'Term transition context retrieved');
        } catch (Exception $e) {
            return formatResponse(false, null, $e->getMessage());
        }
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
