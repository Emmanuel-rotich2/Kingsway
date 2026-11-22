<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * The results window of an assessment.
 *
 * Assessments and exams may be scheduled for any future date or term, with
 * timetables, papers and other arrangements prepared early. Their marks are
 * not due, and nothing reminds anyone or counts them as pending, until the
 * results window is opened:
 *  - summative exams: the School Administrator opens results on the exam
 *    period (openForExamPeriod, called by ExamPeriodService::openResults);
 *  - formative assessments: the subject teacher opens marking once the
 *    lesson or task has happened (openAssessment).
 *
 * assessments.results_opened_at is the single flag every check reads.
 */
final class ResultsWindowService
{
    /** SQL condition for "results are expected" on alias a. */
    public const OPEN_SQL = 'a.results_opened_at IS NOT NULL';

    public function __construct(private PDO $db)
    {
    }

    public function isOpen(int $assessmentId): bool
    {
        $stmt = $this->db->prepare('SELECT results_opened_at FROM assessments WHERE id = ? LIMIT 1');
        $stmt->execute([$assessmentId]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null;
    }

    /** Throws a clear message when marks are not open yet. */
    public function assertOpen(int $assessmentId): void
    {
        if ($this->isOpen($assessmentId)) return;
        $stmt = $this->db->prepare('SELECT is_formative, assessment_date FROM assessments WHERE id = ? LIMIT 1');
        $stmt->execute([$assessmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Assessment not found', 404);
        $when = !empty($row['assessment_date']) ? ' It is scheduled for ' . date('j F Y', strtotime((string) $row['assessment_date'])) . '.' : '';
        throw new RuntimeException(
            ((int) $row['is_formative'] === 1
                ? 'Marking is not open for this assessment yet. The subject teacher opens it once the lesson has taken place.'
                : 'Result entry is not open for this exam yet. The School Administrator opens it on the exam period.') . $when,
            409
        );
    }

    /**
     * Open marking for one formative assessment (or a standalone summative
     * one). Idempotent.
     */
    public function openAssessment(int $assessmentId, int $userId): array
    {
        $stmt = $this->db->prepare(
            'UPDATE assessments SET results_opened_at = COALESCE(results_opened_at, NOW()),
                    results_opened_by = COALESCE(results_opened_by, ?)
             WHERE id = ?'
        );
        $stmt->execute([$userId ?: null, $assessmentId]);
        if ($stmt->rowCount() === 0 && !$this->exists($assessmentId)) {
            throw new RuntimeException('Assessment not found', 404);
        }
        return ['assessment_id' => $assessmentId, 'results_open' => true];
    }

    /** Close an assessment again while no marks have been keyed. */
    public function closeAssessment(int $assessmentId): array
    {
        $marks = $this->db->prepare(
            'SELECT (SELECT COUNT(*) FROM assessment_results WHERE assessment_id = ? AND deleted_at IS NULL)
                  + (SELECT COUNT(*) FROM formative_scores WHERE assessment_id = ?)'
        );
        $marks->execute([$assessmentId, $assessmentId]);
        if ((int) $marks->fetchColumn() > 0) {
            throw new RuntimeException('Marks have already been entered for this assessment, so marking stays open.', 409);
        }
        $this->db->prepare('UPDATE assessments SET results_opened_at = NULL, results_opened_by = NULL WHERE id = ?')
            ->execute([$assessmentId]);
        return ['assessment_id' => $assessmentId, 'results_open' => false];
    }

    /** Open every assessment of an exam period (summative results window). */
    public function openForExamPeriod(int $examPeriodId, int $userId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE assessments a
             JOIN exam_schedule_assessments esa ON esa.assessment_id = a.id
             JOIN exam_period_timetable_entries ept ON ept.exam_schedule_id = esa.exam_schedule_id
             JOIN exam_period_class_learning_areas epcla ON epcla.id = ept.exam_period_class_learning_area_id
             JOIN exam_period_classes epc ON epc.id = epcla.exam_period_class_id
             SET a.results_opened_at = COALESCE(a.results_opened_at, NOW()),
                 a.results_opened_by = COALESCE(a.results_opened_by, ?)
             WHERE epc.exam_period_id = ?'
        );
        $stmt->execute([$userId ?: null, $examPeriodId]);
        return $stmt->rowCount();
    }

    private function exists(int $assessmentId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM assessments WHERE id = ? LIMIT 1');
        $stmt->execute([$assessmentId]);
        return (bool) $stmt->fetchColumn();
    }
}
