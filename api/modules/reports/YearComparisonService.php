<?php

namespace App\API\Modules\reports;

use App\API\Services\ReadReplicaService;
use App\Database\Database;
use PDO;

/**
 * Year-on-year and term-on-term comparison for the dashboard Reports tab.
 *
 * The periods are never chosen by the user; they follow the school calendar:
 *
 *   Years  - during a transition (the next academic year already has
 *            enrollments, i.e. promotion has run) compare the outgoing year
 *            with the incoming year; otherwise the previous year with the
 *            current year.
 *   Terms  - between terms compare the term that closed last with the next
 *            term to open; while a term is in session compare the previous
 *            term with the current term.
 *
 * Every measure is computed for ONE academic year (academic_years.id) or ONE
 * academic-year term (academic_year_terms.id), from the normalized tables:
 * learners are counted once (DISTINCT) per period, in that period's class
 * and stream. Term numbers (1/2/3) repeat every year and are never used as a
 * scope on their own.
 *
 * Roll rule: a learner is on a year's roll when their enrollment for that
 * year is active, completed (promoted) or graduated. Transferred and
 * withdrawn learners left during the year; pending are not yet admitted.
 */
class YearComparisonService
{
    private const ROLL_STATUSES = "('active', 'completed', 'graduated')";

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function compare(): array
    {
        $years = $this->resolveYears();
        $terms = $this->resolveTerms();

        $fromYear = $years['from'];
        $toYear = $years['to'];
        $fromTerm = $terms['from'];
        $toTerm = $terms['to'];

        $sections = [];
        if ($toYear) {
            $sections[] = $this->section('learners', 'Learners', $fromYear, $toYear, fn (array $y) => $this->learnerMeasures((int) $y['id']));
            $sections[] = $this->movementSection($fromYear, $toYear);
            $sections[] = $this->section('finance_year', 'School fees (whole year)', $fromYear, $toYear, fn (array $y) => $this->financeMeasures('academic_year_id', (int) $y['id']));
            $sections[] = $this->section('performance_year', 'Learner performance (whole year)', $fromYear, $toYear, fn (array $y) => $this->performanceMeasures('academic_year_id', (int) $y['id']));
            $sections[] = $this->section('staffing', 'Staffing', $fromYear, $toYear, fn (array $y) => $this->staffMeasures((int) $y['id']));
        }
        if ($toTerm) {
            $sections[] = $this->section('finance_term', 'School fees (term)', $fromTerm, $toTerm, fn (array $t) => $this->financeMeasures('academic_year_term_id', (int) $t['id']), 'term');
            $sections[] = $this->section('performance_term', 'Learner performance (term)', $fromTerm, $toTerm, fn (array $t) => $this->performanceMeasures('academic_year_term_id', (int) $t['id']), 'term');
            $sections[] = $this->section('attendance_term', 'Attendance (term)', $fromTerm, $toTerm, fn (array $t) => $this->attendanceMeasures($t), 'term');
        }

        return [
            'year_mode' => $years['mode'],
            'term_mode' => $terms['mode'],
            'years' => ['from' => $this->publicYear($fromYear), 'to' => $this->publicYear($toYear)],
            'terms' => ['from' => $this->publicTerm($fromTerm), 'to' => $this->publicTerm($toTerm)],
            'sections' => $sections,
            'by_class' => $toYear ? $this->rollByClass($fromYear, $toYear) : [],
            'counting_rule' => 'Each learner is counted once per academic year, in that year\'s class. The roll counts enrollments that are active, completed (promoted) or graduated; transferred and withdrawn learners are listed separately.',
            'generated_at' => date('c'),
        ];
    }

    // ---- Period resolution --------------------------------------------------

    private function resolveYears(): array
    {
        $years = $this->rows('SELECT id, year_code, year_name, start_date, end_date, is_current
                              FROM ' . ReadReplicaService::qualifiedRef('academic_years') . '
                              ORDER BY start_date ASC, id ASC');
        $currentIndex = null;
        foreach ($years as $index => $year) {
            if ((int) $year['is_current'] === 1) {
                $currentIndex = $index;
            }
        }
        if ($currentIndex === null) {
            return ['mode' => 'none', 'from' => null, 'to' => null];
        }
        $current = $years[$currentIndex];
        $next = $years[$currentIndex + 1] ?? null;
        $previous = $years[$currentIndex - 1] ?? null;

        if ($next && $this->enrollmentCount((int) $next['id']) > 0) {
            return ['mode' => 'transition', 'from' => $current, 'to' => $next];
        }
        return ['mode' => 'in_session', 'from' => $previous, 'to' => $current];
    }

    private function resolveTerms(): array
    {
        $terms = $this->rows('SELECT ayt.id, ayt.academic_year_id, ayt.term_id, ayt.opening_date, ayt.closing_date,
                                     ay.year_code
                              FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . ' ayt
                              JOIN ' . ReadReplicaService::qualifiedRef('academic_years') . ' ay ON ay.id = ayt.academic_year_id
                              WHERE ayt.opening_date IS NOT NULL AND ayt.closing_date IS NOT NULL
                              ORDER BY ayt.opening_date ASC, ayt.id ASC');
        if (!$terms) {
            return ['mode' => 'none', 'from' => null, 'to' => null];
        }
        $today = date('Y-m-d');
        foreach ($terms as $index => $term) {
            if ($term['opening_date'] <= $today && $today <= $term['closing_date']) {
                return ['mode' => 'in_session', 'from' => $terms[$index - 1] ?? null, 'to' => $term];
            }
        }
        $closed = array_values(array_filter($terms, static fn ($t) => $t['closing_date'] < $today));
        $upcoming = array_values(array_filter($terms, static fn ($t) => $t['opening_date'] > $today));
        if ($upcoming) {
            return ['mode' => 'transition', 'from' => $closed ? end($closed) : null, 'to' => $upcoming[0]];
        }
        // No future term scheduled: the last two terms that have run.
        $count = count($closed);
        return ['mode' => 'in_session', 'from' => $closed[$count - 2] ?? null, 'to' => $closed[$count - 1] ?? null];
    }

    // ---- Measures -----------------------------------------------------------

    private function learnerMeasures(int $yearId): array
    {
        $row = $this->row(
            "SELECT
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " THEN sae.student_id END) AS roll,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " AND p.gender = 'male' THEN sae.student_id END) AS boys,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " AND p.gender = 'female' THEN sae.student_id END) AS girls,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " AND st.code = 'DAY' THEN sae.student_id END) AS day_scholars,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " AND st.code IN ('BOARD', 'WEEKLY') THEN sae.student_id END) AS boarders,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " AND c.grade_level = 'Grade 9' THEN sae.student_id END) AS grade9,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status = 'graduated' THEN sae.student_id END) AS graduated,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN ('transferred', 'withdrawn') THEN sae.student_id END) AS left_during_year,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status = 'pending' THEN sae.student_id END) AS pending,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " THEN ayc.id END) AS classes,
                COUNT(DISTINCT CASE WHEN sae.enrollment_status IN " . self::ROLL_STATUSES . " THEN aycs.id END) AS streams
             FROM " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae
             JOIN " . ReadReplicaService::qualifiedRef('students') . " s ON s.id = sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef('persons') . " p ON p.id = s.person_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('student_types') . " st ON st.id = s.student_type_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('academic_year_class_streams') . " aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('classes') . " c ON c.id = ayc.class_id
             WHERE sae.academic_year_id = ?",
            [$yearId]
        );

        return [
            ['key' => 'roll', 'label' => 'Learners on the roll', 'value' => (int) $row['roll'], 'kind' => 'count'],
            ['key' => 'boys', 'label' => 'Boys', 'value' => (int) $row['boys'], 'kind' => 'count'],
            ['key' => 'girls', 'label' => 'Girls', 'value' => (int) $row['girls'], 'kind' => 'count'],
            ['key' => 'day_scholars', 'label' => 'Day scholars', 'value' => (int) $row['day_scholars'], 'kind' => 'count'],
            ['key' => 'boarders', 'label' => 'Boarders', 'value' => (int) $row['boarders'], 'kind' => 'count'],
            ['key' => 'grade9', 'label' => 'Grade 9 (final-year) learners', 'value' => (int) $row['grade9'], 'kind' => 'count'],
            ['key' => 'graduated', 'label' => 'Graduated at year end', 'value' => (int) $row['graduated'], 'kind' => 'count'],
            ['key' => 'left_during_year', 'label' => 'Transferred or withdrawn', 'value' => (int) $row['left_during_year'], 'kind' => 'count'],
            ['key' => 'pending', 'label' => 'Admissions pending', 'value' => (int) $row['pending'], 'kind' => 'count'],
            ['key' => 'classes', 'label' => 'Classes in use', 'value' => (int) $row['classes'], 'kind' => 'count'],
            ['key' => 'streams', 'label' => 'Streams in use', 'value' => (int) $row['streams'], 'kind' => 'count'],
        ];
    }

    /** How learners moved from the earlier year to the later one. */
    private function movementSection(?array $fromYear, array $toYear): array
    {
        $measures = [];
        if ($fromYear) {
            $sae = ReadReplicaService::qualifiedRef('student_academic_enrollments');
            $students = ReadReplicaService::qualifiedRef('students');
            $roll = self::ROLL_STATUSES;
            $row = $this->row(
                "SELECT
                    (SELECT COUNT(DISTINCT a.student_id) FROM {$sae} a JOIN {$students} s ON s.id = a.student_id
                      WHERE a.academic_year_id = ? AND a.enrollment_status IN {$roll}
                        AND EXISTS (SELECT 1 FROM {$sae} b WHERE b.student_id = a.student_id AND b.academic_year_id = ? AND b.enrollment_status IN {$roll})) AS continuing,
                    (SELECT COUNT(DISTINCT a.student_id) FROM {$sae} a JOIN {$students} s ON s.id = a.student_id
                      WHERE a.academic_year_id = ? AND a.enrollment_status = 'graduated') AS graduated,
                    (SELECT COUNT(DISTINCT a.student_id) FROM {$sae} a JOIN {$students} s ON s.id = a.student_id
                      WHERE a.academic_year_id = ? AND a.enrollment_status IN {$roll} AND a.enrollment_status <> 'graduated'
                        AND NOT EXISTS (SELECT 1 FROM {$sae} b WHERE b.student_id = a.student_id AND b.academic_year_id = ?)) AS not_continuing,
                    (SELECT COUNT(DISTINCT b.student_id) FROM {$sae} b JOIN {$students} s ON s.id = b.student_id
                      WHERE b.academic_year_id = ? AND b.enrollment_status IN {$roll}
                        AND NOT EXISTS (SELECT 1 FROM {$sae} a WHERE a.student_id = b.student_id AND a.academic_year_id = ?)) AS new_learners",
                [
                    (int) $fromYear['id'], (int) $toYear['id'],
                    (int) $fromYear['id'],
                    (int) $fromYear['id'], (int) $toYear['id'],
                    (int) $toYear['id'], (int) $fromYear['id'],
                ]
            );
            $measures = [
                ['key' => 'continuing', 'label' => 'Carried forward to ' . $toYear['year_code'], 'value' => (int) $row['continuing'], 'kind' => 'count'],
                ['key' => 'graduated', 'label' => 'Graduated from ' . $fromYear['year_code'], 'value' => (int) $row['graduated'], 'kind' => 'count'],
                ['key' => 'not_continuing', 'label' => 'Not carried forward (not graduated)', 'value' => (int) $row['not_continuing'], 'kind' => 'count'],
                ['key' => 'new_learners', 'label' => 'New learners in ' . $toYear['year_code'], 'value' => (int) $row['new_learners'], 'kind' => 'count'],
            ];
        }
        return [
            'key' => 'movement',
            'title' => 'Learner movement between the years',
            'level' => 'movement',
            'from' => $this->publicYear($fromYear),
            'to' => $this->publicYear($toYear),
            'measures' => $measures,
        ];
    }

    /**
     * Fees for one year or one year-term. Balances brought forward from an
     * earlier period are part of the later period's amount due, so they are
     * shown separately and the new billing excludes them.
     */
    private function financeMeasures(string $column, int $id): array
    {
        $balances = $this->row(
            "SELECT COUNT(DISTINCT student_id) AS learners_billed,
                    COALESCE(SUM(amount_due), 0) AS amount_due,
                    COALESCE(SUM(amount_waived), 0) AS waived,
                    COALESCE(SUM(amount_paid), 0) AS paid,
                    COALESCE(SUM(amount_credited), 0) AS credited,
                    COALESCE(SUM(balance), 0) AS outstanding
             FROM " . ReadReplicaService::qualifiedRef('student_fee_balances') . "
             WHERE {$column} = ?",
            [$id]
        );
        $broughtForward = (float) ($this->value(
            "SELECT COALESCE(SUM(opening_arrears_amount), 0)
             FROM " . ReadReplicaService::qualifiedRef('student_fee_obligations') . "
             WHERE {$column} = ?",
            [$id]
        ) ?: 0);
        $due = (float) $balances['amount_due'];
        $paid = (float) $balances['paid'];

        return [
            ['key' => 'learners_billed', 'label' => 'Learners billed', 'value' => (int) $balances['learners_billed'], 'kind' => 'count'],
            ['key' => 'new_billing', 'label' => 'Fees billed (new)', 'value' => round($due - $broughtForward, 2), 'kind' => 'money'],
            ['key' => 'brought_forward', 'label' => 'Balance brought forward', 'value' => round($broughtForward, 2), 'kind' => 'money'],
            ['key' => 'waived', 'label' => 'Waived or discounted', 'value' => round((float) $balances['waived'], 2), 'kind' => 'money'],
            ['key' => 'paid', 'label' => 'Paid', 'value' => round($paid, 2), 'kind' => 'money'],
            ['key' => 'outstanding', 'label' => 'Outstanding', 'value' => round((float) $balances['outstanding'], 2), 'kind' => 'money'],
            ['key' => 'collection_rate', 'label' => 'Collection rate', 'value' => $due > 0 ? round($paid * 100 / $due, 1) : null, 'kind' => 'percent'],
        ];
    }

    /**
     * Computed from approved results (assessment_results on approved
     * assessments, submitted and approved): draft marks are not results.
     * The term roll-up holds a row per learner and learning area even when
     * nothing was assessed, so it is not counted from.
     */
    private function performanceMeasures(string $column, int $id): array
    {
        $termScope = $column === 'academic_year_term_id'
            ? 'a.academic_year_term_id = ?'
            : 'a.academic_year_term_id IN (SELECT ayt.id FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . ' ayt WHERE ayt.academic_year_id = ?)';
        $row = $this->row(
            "SELECT COUNT(DISTINCT sae.student_id) AS learners,
                    COUNT(*) AS results,
                    ROUND(AVG(ar.marks_obtained * 100 / a.max_marks), 1) AS average,
                    SUM(ar.marks_obtained * 100 / a.max_marks >= 80) AS ee,
                    SUM(ar.marks_obtained * 100 / a.max_marks >= 50 AND ar.marks_obtained * 100 / a.max_marks < 80) AS me,
                    SUM(ar.marks_obtained * 100 / a.max_marks >= 25 AND ar.marks_obtained * 100 / a.max_marks < 50) AS ae,
                    SUM(ar.marks_obtained * 100 / a.max_marks < 25) AS be
             FROM " . ReadReplicaService::qualifiedRef('assessment_results') . " ar
             JOIN " . ReadReplicaService::qualifiedRef('assessments') . " a ON a.id = ar.assessment_id
             JOIN " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae ON sae.id = ar.student_academic_enrollment_id
             WHERE {$termScope} AND ar.marks_obtained IS NOT NULL AND a.max_marks > 0
              AND a.status = 'approved' AND ar.is_submitted = 1 AND ar.is_approved = 1 AND ar.deleted_at IS NULL",
            [$id]
        );
        $results = (int) $row['results'];
        $share = static fn ($n) => $results > 0 ? round(((int) $n) * 100 / $results, 1) : null;

        return [
            ['key' => 'learners_assessed', 'label' => 'Learners with approved results', 'value' => (int) $row['learners'], 'kind' => 'count'],
            ['key' => 'results', 'label' => 'Approved results', 'value' => $results, 'kind' => 'count'],
            ['key' => 'average', 'label' => 'Average score', 'value' => $row['average'] !== null ? (float) $row['average'] : null, 'kind' => 'percent'],
            ['key' => 'ee', 'label' => 'Results at EE (80% and above)', 'value' => $share($row['ee']), 'kind' => 'percent'],
            ['key' => 'me', 'label' => 'Results at ME (50–79%)', 'value' => $share($row['me']), 'kind' => 'percent'],
            ['key' => 'ae', 'label' => 'Results at AE (25–49%)', 'value' => $share($row['ae']), 'kind' => 'percent'],
            ['key' => 'be', 'label' => 'Results at BE (below 25%)', 'value' => $share($row['be']), 'kind' => 'percent'],
        ];
    }

    private function attendanceMeasures(array $term): array
    {
        $row = $this->row(
            "SELECT COUNT(DISTINCT student_id) AS learners,
                    COALESCE(SUM(days_marked), 0) AS marked,
                    COALESCE(SUM(present_marks), 0) AS present,
                    COALESCE(SUM(late_marks), 0) AS late,
                    COALESCE(SUM(unexcused_marks), 0) AS unexcused
             FROM " . ReadReplicaService::qualifiedRef('student_attendance_analytics') . "
             WHERE academic_year = ? AND term_number = ?",
            [(string) $term['year_code'], (int) $term['term_id']]
        );
        $marked = (int) $row['marked'];

        return [
            ['key' => 'learners_marked', 'label' => 'Learners with attendance marked', 'value' => (int) $row['learners'], 'kind' => 'count'],
            ['key' => 'register_marks', 'label' => 'Register marks taken', 'value' => $marked, 'kind' => 'count'],
            ['key' => 'attendance_rate', 'label' => 'Attendance rate', 'value' => $marked > 0 ? round(((int) $row['present']) * 100 / $marked, 1) : null, 'kind' => 'percent'],
            ['key' => 'late', 'label' => 'Late marks', 'value' => (int) $row['late'], 'kind' => 'count'],
            ['key' => 'unexcused', 'label' => 'Unexcused absences', 'value' => (int) $row['unexcused'], 'kind' => 'count'],
        ];
    }

    private function staffMeasures(int $yearId): array
    {
        $aycs = ReadReplicaService::qualifiedRef('academic_year_class_streams');
        $ayc = ReadReplicaService::qualifiedRef('academic_year_classes');
        $row = $this->row(
            "SELECT COUNT(DISTINCT aycs.id) AS streams,
                    COUNT(DISTINCT aycs.class_teacher_id) AS class_teachers,
                    SUM(aycs.class_teacher_id IS NULL) AS streams_without_teacher
             FROM {$aycs} aycs
             JOIN {$ayc} ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND aycs.status = 'active'",
            [$yearId]
        );
        $subjectTeachers = (int) ($this->value(
            "SELECT COUNT(DISTINCT t.staff_id)
             FROM " . ReadReplicaService::qualifiedRef('academic_year_class_stream_learning_area_teachers') . " t
             JOIN {$aycs} aycs ON aycs.id = t.academic_year_class_stream_id
             JOIN {$ayc} ayc ON ayc.id = aycs.academic_year_class_id
             WHERE ayc.academic_year_id = ? AND t.status = 'active'",
            [$yearId]
        ) ?: 0);

        return [
            ['key' => 'class_teachers', 'label' => 'Class teachers assigned', 'value' => (int) $row['class_teachers'], 'kind' => 'count'],
            ['key' => 'streams_without_teacher', 'label' => 'Streams without a class teacher', 'value' => (int) $row['streams_without_teacher'], 'kind' => 'count'],
            ['key' => 'subject_teachers', 'label' => 'Learning-area teachers assigned', 'value' => $subjectTeachers, 'kind' => 'count'],
        ];
    }

    /** Roll per class for both years (each learner in that year's class). */
    private function rollByClass(?array $fromYear, array $toYear): array
    {
        $ids = array_values(array_filter([$fromYear['id'] ?? null, $toYear['id']]));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $this->rows(
            "SELECT c.id AS class_id, c.name AS class_name, c.grade_level, sae.academic_year_id,
                    COUNT(DISTINCT sae.student_id) AS learners
             FROM " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae
             JOIN " . ReadReplicaService::qualifiedRef('students') . " s ON s.id = sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef('academic_year_class_streams') . " aycs ON aycs.id = sae.academic_year_class_stream_id
             JOIN " . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
             JOIN " . ReadReplicaService::qualifiedRef('classes') . " c ON c.id = ayc.class_id
             WHERE sae.academic_year_id IN ({$placeholders}) AND sae.enrollment_status IN " . self::ROLL_STATUSES . "
             GROUP BY c.id, c.name, c.grade_level, sae.academic_year_id",
            array_map('intval', $ids)
        );
        $order = ['Playgroup', 'PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9'];
        $byClass = [];
        foreach ($rows as $row) {
            $key = (string) $row['class_id'];
            $byClass[$key] ??= ['class' => $row['class_name'], 'grade_level' => $row['grade_level'], 'from' => $fromYear ? 0 : null, 'to' => 0];
            $side = ($fromYear && (int) $row['academic_year_id'] === (int) $fromYear['id']) ? 'from' : 'to';
            $byClass[$key][$side] = (int) $row['learners'];
        }
        $list = array_values($byClass);
        usort($list, static function ($a, $b) use ($order) {
            $ia = array_search($a['grade_level'], $order, true);
            $ib = array_search($b['grade_level'], $order, true);
            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strcmp($a['class'], $b['class']);
        });
        return array_map(static fn ($r) => ['class' => $r['class'], 'from' => $r['from'], 'to' => $r['to']], $list);
    }

    // ---- Helpers ------------------------------------------------------------

    private function section(string $key, string $title, ?array $from, ?array $to, callable $measure, string $level = 'year'): array
    {
        $fromMeasures = $from ? $measure($from) : [];
        $toMeasures = $to ? $measure($to) : [];
        $fromByKey = array_column($fromMeasures, 'value', 'key');
        $measures = array_map(static function (array $m) use ($from, $fromByKey) {
            return [
                'key' => $m['key'],
                'label' => $m['label'],
                'kind' => $m['kind'],
                'from' => $from ? ($fromByKey[$m['key']] ?? null) : null,
                'to' => $m['value'],
            ];
        }, $toMeasures);

        return [
            'key' => $key,
            'title' => $title,
            'level' => $level,
            'from' => $level === 'term' ? $this->publicTerm($from) : $this->publicYear($from),
            'to' => $level === 'term' ? $this->publicTerm($to) : $this->publicYear($to),
            'measures' => $measures,
        ];
    }

    private function enrollmentCount(int $yearId): int
    {
        return (int) ($this->value(
            'SELECT COUNT(*) FROM ' . ReadReplicaService::qualifiedRef('student_academic_enrollments') . ' WHERE academic_year_id = ?',
            [$yearId]
        ) ?: 0);
    }

    private function publicYear(?array $year): ?array
    {
        return $year ? ['id' => (int) $year['id'], 'label' => (string) ($year['year_name'] ?: $year['year_code'])] : null;
    }

    private function publicTerm(?array $term): ?array
    {
        return $term ? [
            'id' => (int) $term['id'],
            'label' => 'Term ' . (int) $term['term_id'] . ' ' . $term['year_code'],
            'opening_date' => $term['opening_date'],
            'closing_date' => $term['closing_date'],
        ] : null;
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function row(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function value(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
}
