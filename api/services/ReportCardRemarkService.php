<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Resolves the remark sections of a report card from data the school already
 * records.
 *
 * The report-card artwork (uploads/templates/report_cards/*.php) renders
 * $comments['teacher'|'headteacher'|'next_term_focus'|'pathway_suggestion'] and a
 * per-learning-area $scores[].teacher_comment. Before this resolver those keys
 * were never populated on the official release path, so every PDF fell back to
 * the template default sentences, and the two existing remark banks
 * (grading_comments, performance_levels_cbc.feedback_template) were unreferenced
 * by any code.
 *
 * Resolution order for each remark is: an explicitly supplied value (the
 * on-demand print path passes real authored remarks), then the school's own
 * recorded text, then the grade-bank sentence, then null so the artwork keeps
 * its own wording. Nothing is fabricated where the school recorded nothing.
 */
final class ReportCardRemarkService
{
    private PDO $db;

    /** @var array<string,string>|null grade_code => comment */
    private ?array $gradeBank = null;

    /** @var list<array{code:string,feedback_template:string,mark_range_min:float,mark_range_max:float}>|null */
    private ?array $levelBank = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Fill the comment and per-subject remark sections of a compiled report
     * card. Mutates $reportData in place.
     *
     * @param array<string,mixed> $reportData Payload from AcademicManager::getReportCardData()
     */
    public function hydrate(array &$reportData): void
    {
        $studentId = (int) ($reportData['student']['id'] ?? 0);
        $termId = (int) ($reportData['term']['id'] ?? 0);

        $existing = is_array($reportData['comments'] ?? null) ? $reportData['comments'] : [];
        $reportData['comments'] = $this->resolveComments($existing, $studentId, $termId, $reportData);
        $this->resolveSubjectComments($reportData, $studentId, $termId);
    }

    /**
     * @param array<string,mixed> $supplied
     * @param array<string,mixed> $reportData
     * @return array<string,mixed>
     */
    private function resolveComments(array $supplied, int $studentId, int $termId, array $reportData): array
    {
        $bank = $this->bankForOverall($reportData);

        $teacher = $this->firstText($supplied['teacher'] ?? null, $this->classTeacherNotes($studentId, $termId), $bank);
        $headteacher = $this->firstText($supplied['headteacher'] ?? null, $bank);

        $focus = $this->resolveFocus($supplied['next_term_focus'] ?? null, $reportData);
        $pathway = trim((string) ($supplied['pathway_suggestion'] ?? ''));

        $comments = [];
        if ($teacher !== '') {
            $comments['teacher'] = $teacher;
        }
        if ($headteacher !== '') {
            $comments['headteacher'] = $headteacher;
        }
        if ($focus !== null) {
            $comments['next_term_focus'] = $focus;
        }
        if ($pathway !== '') {
            $comments['pathway_suggestion'] = $pathway;
        }

        return $comments;
    }

    /**
     * @param array<string,mixed> $reportData
     */
    private function bankForOverall(array $reportData): ?string
    {
        $grade = $reportData['ranking']['overall_grade'] ?? null;
        $grade = is_string($grade) ? trim($grade) : '';
        if ($grade !== '') {
            $bank = $this->gradeBank();
            // The KJSEA bank is written EE1/ME1/...; the CBC scale as EE/ME/...
            foreach ([$grade, $grade . '1'] as $code) {
                if (isset($bank[$code])) {
                    return $bank[$code];
                }
            }
        }

        return $this->bandForPercentage($reportData['ranking']['overall_percentage'] ?? null);
    }

    /**
     * Sentence from performance_levels_cbc for the band the overall percentage
     * falls in. That bank is the CBC four-level scale, so it applies whenever
     * the overall percentage is known.
     */
    private function bandForPercentage(mixed $percentage): ?string
    {
        if ($percentage === null || !is_numeric($percentage)) {
            return null;
        }
        $value = (float) $percentage;
        foreach ($this->levelBank() as $band) {
            $min = (float) $band['mark_range_min'];
            $max = (float) $band['mark_range_max'];
            if ($value >= $min && $value <= $max) {
                return $band['feedback_template'] !== '' ? $band['feedback_template'] : null;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $reportData
     * @return list<string>|null
     */
    private function resolveFocus(mixed $supplied, array $reportData): ?array
    {
        if (is_array($supplied)) {
            $names = array_values(array_filter(array_map(
                static fn($item): string => is_scalar($item) ? trim((string) $item) : '',
                $supplied
            ), static fn(string $name): bool => $name !== ''));
            if ($names) {
                return $names;
            }
        } else {
            $text = trim((string) (is_scalar($supplied) ? $supplied : ''));
            if ($text !== '') {
                return [$text];
            }
        }

        return $this->weakAreaFocus($reportData);
    }

    /**
     * The weakest learning areas of the term, as next-term focus areas.
     *
     * @param array<string,mixed> $reportData
     * @return list<string>|null
     */
    private function weakAreaFocus(array $reportData): ?array
    {
        $scores = is_array($reportData['scores'] ?? null) ? $reportData['scores'] : [];
        $ranked = [];
        foreach ($scores as $score) {
            $percentage = $score['overall_percentage'] ?? null;
            if ($percentage === null || !is_numeric($percentage)) {
                continue;
            }
            $name = (string) ($score['subject_name'] ?? '');
            if ($name === '') {
                continue;
            }
            $ranked[] = ['name' => $name, 'pct' => (float) $percentage];
        }
        if (!$ranked) {
            return null;
        }
        usort($ranked, static fn(array $a, array $b): int => $a['pct'] <=> $b['pct']);

        return array_values(array_map(
            static fn(array $row): string => $row['name'],
            array_slice($ranked, 0, 3)
        ));
    }

    /**
     * Class-teacher notes the school already captured this term.
     */
    private function classTeacherNotes(int $studentId, int $termId): ?string
    {
        if ($studentId < 1) {
            return null;
        }
        $competency = ReadReplicaService::qualifiedRef('learner_competency');
        $stmt = $this->db->prepare(
            "SELECT teacher_notes
             FROM {$competency}
             WHERE student_id = :sid
               AND (:term_id IS NULL OR term_id = :term_id)
               AND teacher_notes IS NOT NULL
               AND TRIM(teacher_notes) <> ''
             ORDER BY CHAR_LENGTH(teacher_notes) DESC, learner_competency_id DESC
             LIMIT 1"
        );
        $stmt->execute([':sid' => $studentId, ':term_id' => $termId > 0 ? $termId : null]);
        $notes = $stmt->fetch(PDO::FETCH_COLUMN);

        return is_string($notes) && trim($notes) !== '' ? trim($notes) : null;
    }

    /**
     * Per-learning-area remarks recorded during mark entry.
     *
     * @param array<string,mixed> $reportData
     */
    private function resolveSubjectComments(array &$reportData, int $studentId, int $termId): void
    {
        if ($studentId < 1 || !is_array($reportData['scores'] ?? null)) {
            return;
        }

        $remarks = $this->subjectRemarks($studentId, $termId);
        if (!$remarks) {
            return;
        }

        foreach ($reportData['scores'] as &$score) {
            $areaId = (int) ($score['subject_id'] ?? 0);
            if ($areaId > 0 && isset($remarks[$areaId]) && trim((string) ($score['teacher_comment'] ?? '')) === '') {
                $score['teacher_comment'] = $remarks[$areaId];
            }
        }
        unset($score);
    }

    /**
     * Most recent remark per learning area for this learner in this term.
     *
     * @return array<int,string>
     */
    private function subjectRemarks(int $studentId, int $academicYearTermId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.learning_area_id, ar.remarks
             FROM assessment_results ar
             JOIN assessments a ON a.id = ar.assessment_id
             WHERE ar.student_academic_enrollment_id IN (
                     SELECT sae.id FROM student_academic_enrollments sae WHERE sae.student_id = :sid
                 )
               AND (:term_id IS NULL OR a.academic_year_term_id = :term_id)
               AND ar.deleted_at IS NULL
               AND ar.remarks IS NOT NULL
               AND TRIM(ar.remarks) <> \'\'
             ORDER BY ar.id DESC'
        );
        $stmt->execute([':sid' => $studentId, ':term_id' => $academicYearTermId > 0 ? $academicYearTermId : null]);

        $remarks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $areaId = (int) $row['learning_area_id'];
            // First row wins per area: the most recently written remark.
            if ($areaId > 0 && !isset($remarks[$areaId])) {
                $remarks[$areaId] = trim((string) $row['remarks']);
            }
        }

        return $remarks;
    }

    /**
     * @return array<string,string>
     */
    private function gradeBank(): array
    {
        if ($this->gradeBank !== null) {
            return $this->gradeBank;
        }

        $this->gradeBank = [];
        try {
            $stmt = $this->db->query('SELECT grade_code, comment FROM grading_comments');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $code = (string) ($row['grade_code'] ?? '');
                $comment = (string) ($row['comment'] ?? '');
                if ($code === '' || $comment === '') {
                    continue;
                }
                $this->gradeBank[$code] = $comment;
            }
        } catch (\PDOException $e) {
            // A missing remark bank must never block a report card; the artwork
            // carries its own default wording.
        }

        return $this->gradeBank;
    }

    /**
     * @return list<array{code:string,feedback_template:string,mark_range_min:float,mark_range_max:float}>
     */
    private function levelBank(): array
    {
        if ($this->levelBank !== null) {
            return $this->levelBank;
        }

        try {
            $stmt = $this->db->query(
                'SELECT code, feedback_template, mark_range_min, mark_range_max
                 FROM performance_levels_cbc
                 WHERE feedback_template IS NOT NULL AND feedback_template <> \'\'
                 ORDER BY mark_range_min'
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $rows = [];
        }

        $bands = [];
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            $feedback = (string) ($row['feedback_template'] ?? '');
            if ($code === '' || $feedback === '') {
                continue;
            }
            $bands[] = [
                'code' => $code,
                'feedback_template' => $feedback,
                'mark_range_min' => (float) ($row['mark_range_min'] ?? 0),
                'mark_range_max' => (float) ($row['mark_range_max'] ?? 0),
            ];
        }

        return $this->levelBank = $bands;
    }

    /**
     * First non-empty text among the supplied candidates.
     */
    private function firstText(mixed ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }
            $text = trim((string) $candidate);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }
}
