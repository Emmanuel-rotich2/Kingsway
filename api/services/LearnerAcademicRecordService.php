<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * A learner's academic record from the year they joined to now.
 *
 * One section per academic year the learner was enrolled in, each in the
 * class and stream of THAT year. Within a year: every learning area taught
 * to that class that year, with Terms 1-3 side by side:
 *
 *   - term result: the computed term score and grade (term_subject_scores),
 *     only where evidence exists; otherwise "not assessed";
 *   - summative: each approved exam/test result;
 *   - formative: approved formative evidence grouped by strand and
 *     sub-strand.
 *
 * Scoping rules (the same as every year-aware report):
 *   - a year is academic_years.id; a term is academic_year_terms.id (the
 *     term number 1/2/3 repeats every year and is never used on its own);
 *   - a learner's results in a year come through that year's enrollment
 *     (summative) or that year's class stream (formative), so a result can
 *     never appear under another year or class;
 *   - only approved results count: draft marks on an assessment that is not
 *     approved are counted as "awaiting approval", never shown as scores.
 */
class LearnerAcademicRecordService
{
    private const RECORD_STATUSES = "('pending','active','completed','graduated','transferred','withdrawn')";

    private PDO $db;

    /** @var callable(string):string projection name -> table reference */
    private $ref;

    /**
     * @param callable|null $ref resolves a projection to a table reference;
     *        defaults to the governed read replica. Tests inject the master
     *        resolver so uncommitted rows are visible.
     */
    public function __construct(?PDO $db = null, ?callable $ref = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->ref = $ref ?? static fn (string $projection): string => ReadReplicaService::qualifiedRef($projection);
    }

    private function ref(string $projection): string
    {
        return ($this->ref)($projection);
    }

    public function forStudent(int $studentId): array
    {
        $student = $this->row(
            "SELECT s.id, s.admission_no, s.status, s.admission_date, p.first_name, p.middle_name, p.last_name, p.gender
             FROM " . $this->ref('students') . " s
             JOIN " . $this->ref('persons') . " p ON p.id = s.person_id
             WHERE s.id = ?",
            [$studentId]
        );
        if (!$student) {
            throw new RuntimeException('Learner not found.');
        }

        $years = $this->rows(
            "SELECT sae.id AS enrollment_id, sae.enrollment_status, sae.academic_year_class_stream_id AS stream_ref,
                    ay.id AS academic_year_id, ay.year_code, ay.start_date, ay.is_current,
                    ayc.id AS year_class_id, c.id AS class_id, c.name AS class_name, st.name AS stream_name
             FROM " . $this->ref('student_academic_enrollments') . " sae
             JOIN " . $this->ref('academic_years') . " ay ON ay.id = sae.academic_year_id
             LEFT JOIN " . $this->ref('academic_year_class_streams') . " aycs ON aycs.id = sae.academic_year_class_stream_id
             LEFT JOIN " . $this->ref('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
             LEFT JOIN " . $this->ref('classes') . " c ON c.id = ayc.class_id
             LEFT JOIN " . $this->ref('streams') . " st ON st.id = aycs.stream_id
             WHERE sae.student_id = ? AND sae.enrollment_status IN " . self::RECORD_STATUSES . "
             ORDER BY ay.start_date, ay.id",
            [$studentId]
        );

        $sections = [];
        $trend = [];
        foreach ($years as $year) {
            $section = $this->yearSection($studentId, $year);
            $sections[] = $section;
            foreach ($section['learning_areas'] as $area) {
                foreach ($area['terms'] as $term) {
                    if ($term['term_result'] === null) {
                        continue;
                    }
                    $trend[$area['name']][] = [
                        'academic_year' => $year['year_code'],
                        'class_name' => $year['class_name'],
                        'term' => $term['term'],
                        'overall_percentage' => $term['term_result']['overall_percentage'],
                        'grade' => $term['term_result']['grade'],
                    ];
                }
            }
        }
        ksort($trend);

        $first = $years[0] ?? null;
        return [
            'student' => [
                'id' => (int) $student['id'],
                'admission_no' => $student['admission_no'],
                'name' => trim(implode(' ', array_filter([$student['first_name'], $student['middle_name'], $student['last_name']]))),
                'gender' => $student['gender'],
                'status' => $student['status'],
                'admission_date' => $student['admission_date'],
                'joined' => $first ? ['academic_year' => $first['year_code'], 'class_name' => $first['class_name']] : null,
            ],
            'years' => $sections,
            'learning_area_trends' => array_map(
                static fn (string $name, array $points) => ['learning_area' => $name, 'points' => $points],
                array_keys($trend),
                array_values($trend)
            ),
            'rules' => 'Results are shown in the class and year they were earned. Only approved results are shown; marks awaiting approval are counted but not scored.',
        ];
    }

    private function yearSection(int $studentId, array $year): array
    {
        $yearId = (int) $year['academic_year_id'];
        $enrollmentId = (int) $year['enrollment_id'];
        $streamId = (int) ($year['stream_ref'] ?? 0);
        $yearClassId = (int) ($year['year_class_id'] ?? 0);

        $terms = $this->rows(
            "SELECT ayt.id, t.name AS term_name
             FROM " . $this->ref('academic_year_terms') . " ayt
             JOIN terms t ON t.id = ayt.term_id
             WHERE ayt.academic_year_id = ?
             ORDER BY ayt.opening_date, ayt.id",
            [$yearId]
        );
        $termIds = array_map(static fn ($t) => (int) $t['id'], $terms);
        $termIn = implode(',', $termIds ?: [0]);

        // Learning areas taught to this class this year (class-level and
        // stream-level), plus any area the learner was assessed in, so no
        // result is ever hidden.
        $areaRows = $this->rows(
            "SELECT DISTINCT la.id, la.name
             FROM " . $this->ref('academic_year_class_learning_areas') . " cla
             JOIN " . $this->ref('learning_areas') . " la ON la.id = cla.learning_area_id
             WHERE cla.academic_year_class_id = ?
             UNION
             SELECT DISTINCT la.id, la.name
             FROM " . $this->ref('assessments') . " a
             JOIN " . $this->ref('learning_areas') . " la ON la.id = a.learning_area_id
             WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id IN ({$termIn})",
            [$yearClassId, $streamId]
        );
        $areas = [];
        foreach ($areaRows as $row) {
            $areas[(int) $row['id']] = $row['name'];
        }
        asort($areas);

        // Term results, only rows that carry evidence.
        $termResults = [];
        foreach ($this->rows(
            "SELECT subject_id, academic_year_term_id, formative_percentage, summative_percentage,
                    overall_percentage, overall_grade, assessment_count
             FROM " . $this->ref('term_subject_scores') . "
             WHERE student_id = ? AND academic_year_term_id IN ({$termIn})
               AND assessment_count > 0 AND overall_percentage IS NOT NULL",
            [$studentId]
        ) as $row) {
            $termResults[(int) $row['subject_id']][(int) $row['academic_year_term_id']] = [
                'formative_percentage' => $row['formative_percentage'] !== null ? (float) $row['formative_percentage'] : null,
                'summative_percentage' => $row['summative_percentage'] !== null ? (float) $row['summative_percentage'] : null,
                'overall_percentage' => (float) $row['overall_percentage'],
                'grade' => $row['overall_grade'],
                'assessments' => (int) $row['assessment_count'],
            ];
        }

        // Summative and formative results recorded against this year's
        // enrollment; approved ones are results, the rest await approval.
        $results = $this->rows(
            "SELECT a.id AS assessment_id, a.title, a.assessment_date, a.max_marks, a.is_formative,
                    a.learning_area_id, a.academic_year_term_id, a.status AS assessment_status,
                    a.strand_id, a.sub_strand_id, s.name AS strand_name, ss.name AS sub_strand_name,
                    ar.marks_obtained, ar.entry_status, ar.grade, ar.remarks,
                    (a.status = 'approved' AND ar.is_submitted = 1 AND ar.is_approved = 1) AS approved
             FROM " . $this->ref('assessment_results') . " ar
             JOIN " . $this->ref('assessments') . " a ON a.id = ar.assessment_id
             LEFT JOIN " . $this->ref('strands') . " s ON s.id = a.strand_id
             LEFT JOIN " . $this->ref('sub_strands') . " ss ON ss.id = a.sub_strand_id
             WHERE ar.student_academic_enrollment_id = ? AND ar.deleted_at IS NULL
               AND a.academic_year_term_id IN ({$termIn})
             ORDER BY a.assessment_date, a.id",
            [$enrollmentId]
        );

        // Formative scores are stored per learner; the assessment's class
        // stream and term place them in this year.
        $formative = $this->rows(
            "SELECT a.id AS assessment_id, a.title, a.assessment_date, a.learning_area_id, a.academic_year_term_id,
                    a.status AS assessment_status, a.strand_id, a.sub_strand_id,
                    s.name AS strand_name, ss.name AS sub_strand_name,
                    fs.score, fs.max_score, fs.percentage, fs.cbc_grade, fs.remarks,
                    (a.status = 'approved') AS approved
             FROM formative_scores fs
             JOIN " . $this->ref('assessments') . " a ON a.id = fs.assessment_id
             LEFT JOIN " . $this->ref('strands') . " s ON s.id = a.strand_id
             LEFT JOIN " . $this->ref('sub_strands') . " ss ON ss.id = a.sub_strand_id
             WHERE fs.student_id = ? AND a.academic_year_class_stream_id = ? AND a.academic_year_term_id IN ({$termIn})
             ORDER BY a.assessment_date, a.id",
            [$studentId, $streamId]
        );

        $byArea = [];
        $awaiting = 0;
        foreach ($results as $row) {
            $areaId = (int) $row['learning_area_id'];
            $areas[$areaId] ??= 'Learning area ' . $areaId;
            if (!(int) $row['approved']) {
                $awaiting++;
                $byArea[$areaId][(int) $row['academic_year_term_id']]['awaiting'] =
                    ($byArea[$areaId][(int) $row['academic_year_term_id']]['awaiting'] ?? 0) + 1;
                continue;
            }
            $entry = [
                'title' => $row['title'],
                'date' => $row['assessment_date'],
                'score' => $row['marks_obtained'] !== null ? (float) $row['marks_obtained'] : null,
                'out_of' => (float) $row['max_marks'],
                'percentage' => ($row['marks_obtained'] !== null && (float) $row['max_marks'] > 0)
                    ? round((float) $row['marks_obtained'] * 100 / (float) $row['max_marks'], 1) : null,
                'grade' => $row['grade'],
                'entry_status' => $row['entry_status'],
                'remarks' => $row['remarks'],
            ];
            if ((int) $row['is_formative']) {
                $this->addFormative($byArea, $areaId, (int) $row['academic_year_term_id'], $row, $entry);
            } else {
                $byArea[$areaId][(int) $row['academic_year_term_id']]['summative'][] = $entry;
            }
        }
        foreach ($formative as $row) {
            $areaId = (int) $row['learning_area_id'];
            $areas[$areaId] ??= 'Learning area ' . $areaId;
            if (!(int) $row['approved']) {
                $awaiting++;
                $byArea[$areaId][(int) $row['academic_year_term_id']]['awaiting'] =
                    ($byArea[$areaId][(int) $row['academic_year_term_id']]['awaiting'] ?? 0) + 1;
                continue;
            }
            $this->addFormative($byArea, $areaId, (int) $row['academic_year_term_id'], $row, [
                'title' => $row['title'],
                'date' => $row['assessment_date'],
                'score' => $row['score'] !== null ? (float) $row['score'] : null,
                'out_of' => $row['max_score'] !== null ? (float) $row['max_score'] : null,
                'percentage' => $row['percentage'] !== null ? round((float) $row['percentage'], 1) : null,
                'grade' => $row['cbc_grade'],
                'entry_status' => 'present',
                'remarks' => $row['remarks'],
            ]);
        }

        $learningAreas = [];
        foreach ($areas as $areaId => $name) {
            $termRows = [];
            foreach ($terms as $term) {
                $tid = (int) $term['id'];
                $bucket = $byArea[$areaId][$tid] ?? [];
                $strands = array_values($bucket['formative'] ?? []);
                foreach ($strands as &$strand) {
                    $strand['sub_strands'] = array_values($strand['sub_strands']);
                }
                unset($strand);
                $result = $termResults[$areaId][$tid] ?? null;
                $termRows[] = [
                    'academic_year_term_id' => $tid,
                    'term' => $term['term_name'],
                    'term_result' => $result,
                    'summative' => $bucket['summative'] ?? [],
                    'formative_strands' => $strands,
                    'awaiting_approval' => (int) ($bucket['awaiting'] ?? 0),
                    'status' => $result !== null || !empty($bucket['summative']) || $strands
                        ? 'assessed'
                        : (($bucket['awaiting'] ?? 0) > 0 ? 'awaiting_approval' : 'not_assessed'),
                ];
            }
            $learningAreas[] = ['learning_area_id' => $areaId, 'name' => $name, 'terms' => $termRows];
        }

        return [
            'academic_year_id' => $yearId,
            'academic_year' => $year['year_code'],
            'is_current' => (int) $year['is_current'] === 1,
            'class_name' => $year['class_name'],
            'stream_name' => $year['stream_name'],
            'enrollment_status' => $year['enrollment_status'],
            'terms' => array_map(static fn ($t) => ['academic_year_term_id' => (int) $t['id'], 'term' => $t['term_name']], $terms),
            'learning_areas' => $learningAreas,
            'results_awaiting_approval' => $awaiting,
        ];
    }

    private function addFormative(array &$byArea, int $areaId, int $termId, array $row, array $entry): void
    {
        $strandKey = (string) ($row['strand_id'] ?? 0);
        $subKey = (string) ($row['sub_strand_id'] ?? 0);
        $strand = &$byArea[$areaId][$termId]['formative'][$strandKey];
        $strand['strand'] ??= $row['strand_name'] ?: 'General';
        $sub = &$strand['sub_strands'][$subKey];
        $sub['sub_strand'] ??= $row['sub_strand_name'] ?: 'General';
        $sub['evidence'][] = $entry;
        unset($strand, $sub);
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function row(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
