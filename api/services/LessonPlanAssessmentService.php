<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;

/**
 * Formative assessment is part of the lesson plan.
 *
 * Each lesson plan carries one formative assessment, dated to the lesson and
 * tied to the lesson's class stream, learning area, strand and sub-strand.
 * It is created when the plan is saved or approved, stays scheduled (marking
 * closed) until the subject teacher opens marking after the lesson, and is
 * aggregated by sub-strand in TermResultsService. Printed weekly/termly plans
 * and course outlines read it through forLessonPlans().
 *
 * Simple keyed reads only: the lesson context read-model can lag a plan that
 * was saved a moment ago.
 */
final class LessonPlanAssessmentService
{
    public function __construct(private PDO $db)
    {
    }

    /** Create or refresh the lesson's formative assessment. Returns its id or null. */
    public function ensureFor(int $lessonPlanId, ?int $staffId = null): ?int
    {
        $plan = $this->row('SELECT * FROM lesson_plans WHERE id = ?', [$lessonPlanId]);
        // The assistant drafts, the teacher validates and submits, the academic
        // heads approve: the lesson's assessment exists only from approval on.
        if (!$plan || !in_array((string) ($plan['status'] ?? ''), ['approved', 'published', 'delivered'], true)) return null;

        $streamArea = $this->row(
            'SELECT academic_year_class_stream_id, academic_year_class_learning_area_id
             FROM academic_year_class_stream_learning_areas WHERE id = ?',
            [(int) ($plan['academic_year_class_stream_learning_area_id'] ?? 0)]
        );
        $classAreaId = (int) ($streamArea['academic_year_class_learning_area_id'] ?? $plan['academic_year_class_learning_area_id'] ?? 0);
        $classArea = $classAreaId ? $this->row('SELECT learning_area_id, strand_id, sub_strand_id FROM academic_year_class_learning_areas WHERE id = ?', [$classAreaId]) : null;
        $streamId = (int) ($streamArea['academic_year_class_stream_id'] ?? 0);
        $learningAreaId = (int) ($classArea['learning_area_id'] ?? 0);
        if (!$streamId || !$learningAreaId) return null;

        $day = $plan['academic_year_calendar_day_id']
            ? $this->row('SELECT date, academic_year_calendar_id FROM academic_year_calendar_days WHERE id = ?', [(int) $plan['academic_year_calendar_day_id']])
            : null;
        if ($day) {
            $day['academic_year_term_id'] = $this->value('SELECT academic_year_term_id FROM academic_year_calendar WHERE id = ?', [(int) $day['academic_year_calendar_id']]);
        }
        $termId = (int) ($day['academic_year_term_id'] ?? 0);
        if (!$termId) return null;

        [$strandId, $subStrandId, $title] = $this->curriculumFor($plan, $classArea);
        $toolId = (int) ($this->value('SELECT assessment_tool_id FROM lesson_plan_assessment_tools WHERE lesson_plan_id = ? ORDER BY sort_order LIMIT 1', [$lessonPlanId]) ?: 0);
        $assignedBy = (int) ($plan['teacher_id'] ?? 0) ?: ($staffId ?: null);
        $assessmentTitle = mb_substr('Lesson assessment: ' . ($title !== '' ? $title : 'lesson ' . $lessonPlanId), 0, 255);

        $existing = $this->value('SELECT id FROM assessments WHERE lesson_plan_id = ? AND is_formative = 1 ORDER BY id LIMIT 1', [$lessonPlanId]);
        if ($existing) {
            // Keep it in step with the plan while marking has not started.
            $this->db->prepare(
                "UPDATE assessments SET academic_year_class_stream_id = ?, academic_year_term_id = ?, learning_area_id = ?,
                        strand_id = ?, sub_strand_id = ?, assessment_tool_id = ?, title = ?, assessment_date = ?,
                        academic_year_calendar_day_id = ?
                 WHERE id = ? AND results_opened_at IS NULL"
            )->execute([
                $streamId, $termId, $learningAreaId, $strandId ?: null, $subStrandId ?: null, $toolId ?: null,
                $assessmentTitle, $day['date'] ?? null, (int) $plan['academic_year_calendar_day_id'] ?: null, (int) $existing,
            ]);
            return (int) $existing;
        }

        $this->db->prepare(
            "INSERT INTO assessments
                (academic_year_class_stream_id, academic_year_term_id, academic_year_calendar_day_id, learning_area_id,
                 strand_id, sub_strand_id, scheme_of_work_id, lesson_plan_id, is_formative, assessment_tool_id,
                 title, description, max_marks, assessment_date, assigned_by, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, 4, ?, ?, 'pending_submission')"
        )->execute([
            $streamId, $termId, (int) $plan['academic_year_calendar_day_id'] ?: null, $learningAreaId,
            $strandId ?: null, $subStrandId ?: null, (int) ($plan['scheme_of_work_id'] ?? 0) ?: null, $lessonPlanId,
            $toolId ?: null, $assessmentTitle,
            'Formative assessment for this lesson. Record a rubric level (1 to 4) or marks once marking is opened.',
            $day['date'] ?? null, $assignedBy,
        ]);
        $id = (int) $this->db->lastInsertId();
        if ($subStrandId) {
            $this->db->prepare('INSERT INTO assessment_sub_strands (assessment_id, sub_strand_id, sort_order) VALUES (?, ?, 1)')
                ->execute([$id, $subStrandId]);
        }
        return $id;
    }

    /**
     * Formative assessments for a set of lesson plans, keyed by plan id, for
     * printed weekly/termly plans and course outlines.
     *
     * @param list<int> $lessonPlanIds
     * @return array<int,array<string,mixed>>
     */
    public function forLessonPlans(array $lessonPlanIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $lessonPlanIds))));
        if (!$ids) return [];
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, lesson_plan_id, title, assessment_date, max_marks, results_opened_at, status, assessment_tool_id, strand_id, sub_strand_id
             FROM assessments WHERE is_formative = 1 AND lesson_plan_id IN ({$in})"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $toolIds = array_values(array_unique(array_filter(array_map(static fn ($r) => (int) $r['assessment_tool_id'], $rows))));
        $tools = [];
        if ($toolIds) {
            $tin = implode(',', array_fill(0, count($toolIds), '?'));
            $t = $this->db->prepare("SELECT id, tool_name FROM assessment_tools WHERE id IN ({$tin})");
            $t->execute($toolIds);
            foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $tool) $tools[(int) $tool['id']] = (string) $tool['tool_name'];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['lesson_plan_id']] = [
                'assessment_id' => (int) $r['id'],
                'title' => (string) $r['title'],
                'date' => $r['assessment_date'],
                'tool' => $tools[(int) $r['assessment_tool_id']] ?? null,
                'marking' => $r['results_opened_at'] ? ($r['status'] === 'approved' ? 'approved' : 'open') : 'scheduled',
            ];
        }
        return $out;
    }

    /** @return array{0:int,1:int,2:string} strand, sub-strand, lesson title */
    private function curriculumFor(array $plan, ?array $classArea): array
    {
        $strandId = 0; $subStrandId = 0; $title = '';
        if (!empty($plan['lesson_template_id'])) {
            $tpl = $this->row('SELECT strand_id, sub_strand_id, title FROM lesson_templates WHERE id = ?', [(int) $plan['lesson_template_id']]);
            if ($tpl) { $strandId = (int) $tpl['strand_id']; $subStrandId = (int) $tpl['sub_strand_id']; $title = (string) $tpl['title']; }
        }
        if (!$subStrandId) {
            $outcomeId = (int) ($this->value('SELECT learning_outcome_id FROM lesson_plan_outcomes WHERE lesson_plan_id = ? ORDER BY sort_order LIMIT 1', [(int) $plan['id']]) ?: 0);
            $outcome = $outcomeId ? $this->row('SELECT strand_id, sub_strand_id FROM learning_outcomes WHERE id = ?', [$outcomeId]) : null;
            if ($outcome) { $subStrandId = (int) $outcome['sub_strand_id']; $strandId = $strandId ?: (int) $outcome['strand_id']; }
        }
        if (!$subStrandId && $classArea) { $subStrandId = (int) ($classArea['sub_strand_id'] ?? 0); $strandId = $strandId ?: (int) ($classArea['strand_id'] ?? 0); }
        if ($subStrandId && !$strandId) {
            $strandId = (int) ($this->value('SELECT strand_id FROM sub_strands WHERE id = ?', [$subStrandId]) ?: 0);
        }
        if ($title === '' && $subStrandId) {
            $title = (string) ($this->value('SELECT name FROM sub_strands WHERE id = ?', [$subStrandId]) ?: '');
        }
        return [$strandId, $subStrandId, $title];
    }

    private function row(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function value(string $sql, array $params): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
