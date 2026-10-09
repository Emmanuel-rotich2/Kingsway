<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Rebuilds year-safe CBC term scores and deterministic class/cohort ranks. */
final class TermResultsService
{
    private PDO $db;
    private CbcGradingService $grading;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->grading = new CbcGradingService($db);
    }

    public function compute(int $classStreamId, int $academicYearTermId, ?int $learningAreaId = null): array
    {
        $context = $this->context($classStreamId, $academicYearTermId);
        $policy = $this->policy((int) $context['academic_year_id']);
        $roster = $this->roster($classStreamId);
        if (!$roster) throw new RuntimeException('No active learners are enrolled in this class stream', 409);
        $areas = $this->learningAreas($classStreamId, $learningAreaId);
        if (!$areas) throw new RuntimeException('No active learning areas are assigned to this class stream', 409);

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            $deleteSql = 'DELETE FROM term_subject_scores
                          WHERE academic_year_term_id = ? AND academic_year_class_stream_id = ?';
            $deleteParams = [$academicYearTermId, $classStreamId];
            if ($learningAreaId) {
                $deleteSql .= ' AND subject_id = ?';
                $deleteParams[] = $learningAreaId;
            }
            $this->db->prepare($deleteSql)->execute($deleteParams);

            $evidence = $this->evidence($classStreamId, $academicYearTermId, $learningAreaId);
            $grouped = [];
            foreach ($evidence as $row) {
                $key = (int) $row['student_id'] . ':' . (int) $row['subject_id'];
                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'formative_total' => 0.0, 'formative_max' => 0.0, 'formative_count' => 0,
                        'summative_total' => 0.0, 'summative_max' => 0.0, 'summative_count' => 0,
                    ];
                }
                $bucket = (string) $row['bucket'];
                $grouped[$key][$bucket . '_count']++;
                if (($row['entry_status'] ?? 'present') === 'present' && $row['score'] !== null) {
                    $grouped[$key][$bucket . '_total'] += (float) $row['score'];
                    $grouped[$key][$bucket . '_max'] += (float) $row['max_score'];
                }
            }

            $insert = $this->db->prepare(
                'INSERT INTO term_subject_scores
                    (student_id, term_id, academic_year_term_id, academic_year_class_stream_id, subject_id,
                     formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                     summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                     overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );

            $computed = 0;
            foreach ($roster as $studentId => $enrollmentId) {
                foreach ($areas as $subjectId) {
                    $key = $studentId . ':' . $subjectId;
                    $aggregate = $grouped[$key] ?? [
                        'formative_total' => 0.0, 'formative_max' => 0.0, 'formative_count' => 0,
                        'summative_total' => 0.0, 'summative_max' => 0.0, 'summative_count' => 0,
                    ];
                    $formativePercentage = $aggregate['formative_max'] > 0
                        ? round($aggregate['formative_total'] * 100 / $aggregate['formative_max'], 2)
                        : null;
                    $summativePercentage = $aggregate['summative_max'] > 0
                        ? round($aggregate['summative_total'] * 100 / $aggregate['summative_max'], 2)
                        : null;
                    $overallPercentage = $this->weightedPercentage($formativePercentage, $summativePercentage, $policy);
                    $formativeGrade = $formativePercentage === null ? null : $this->grading->grade($formativePercentage, 100);
                    $summativeGrade = $summativePercentage === null ? null : $this->grading->grade($summativePercentage, 100);
                    $overallGrade = $overallPercentage === null ? null : $this->grading->grade($overallPercentage, 100);
                    $insert->execute([
                        $studentId, (int) $context['term_id'], $academicYearTermId, $classStreamId, $subjectId,
                        $aggregate['formative_total'], $aggregate['formative_max'], $formativePercentage,
                        $formativeGrade['grade_code'] ?? null, $aggregate['formative_count'],
                        $aggregate['summative_total'], $aggregate['summative_max'], $summativePercentage,
                        $summativeGrade['grade_code'] ?? null, $aggregate['summative_count'],
                        $overallPercentage, $overallPercentage, $overallGrade['grade_code'] ?? null,
                        $overallGrade['points'] ?? null,
                        $aggregate['formative_count'] + $aggregate['summative_count'],
                    ]);
                    $computed++;
                }
            }

            $ranked = $this->rebuildRankings(
                $academicYearTermId,
                (int) $context['academic_year_class_id'],
                (string) $policy['ranking_method']
            );
            if ($ownsTransaction) $this->db->commit();
            return [
                'computed' => $computed,
                'learners' => count($roster),
                'learning_areas' => count($areas),
                'ranked' => $ranked,
                'policy' => [
                    'formative_weight' => (float) $policy['formative_weight'],
                    'summative_weight' => (float) $policy['summative_weight'],
                    'ranking_method' => $policy['ranking_method'],
                ],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function context(int $streamId, int $termId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ayt.academic_year_id, ayt.term_id, aycs.academic_year_class_id
             FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . ' ayt
             JOIN ' . ReadReplicaService::qualifiedRef('academic_year_class_streams') . ' aycs ON aycs.id = ?
             JOIN ' . ReadReplicaService::qualifiedRef('academic_year_classes') . ' ayc ON ayc.id = aycs.academic_year_class_id
                                      AND ayc.academic_year_id = ayt.academic_year_id
             WHERE ayt.id = ? LIMIT 1'
        );
        $stmt->execute([$streamId, $termId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('The class stream and term do not belong to the same academic year', 422);
        return $row;
    }

    private function policy(int $academicYearId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM academic_result_policies WHERE academic_year_id = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$academicYearId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$policy) throw new RuntimeException('No active academic result policy is configured for this year', 409);
        return $policy;
    }

    private function roster(int $streamId): array
    {
        $stmt = $this->db->prepare(
            "SELECT student_id, id FROM student_academic_enrollments
             WHERE academic_year_class_stream_id = ? AND enrollment_status IN ('pending','active')"
        );
        $stmt->execute([$streamId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $result[(int) $row['student_id']] = (int) $row['id'];
        return $result;
    }

    private function learningAreas(int $streamId, ?int $areaId): array
    {
        $sql = "SELECT DISTINCT cla.learning_area_id
                FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_areas") . " sla
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " cla ON cla.id = sla.academic_year_class_learning_area_id
                WHERE sla.academic_year_class_stream_id = ?
                  AND sla.status IN ('planned','active','in_progress','covered')";
        $params = [$streamId];
        if ($areaId) { $sql .= ' AND cla.learning_area_id = ?'; $params[] = $areaId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function evidence(int $streamId, int $termId, ?int $areaId): array
    {
        $areaFilter = $areaId ? ' AND a.learning_area_id = ?' : '';
        $params = [$streamId, $termId];
        if ($areaId) $params[] = $areaId;
        $params = array_merge($params, [$streamId, $termId]);
        if ($areaId) $params[] = $areaId;
        $stmt = $this->db->prepare(
            "SELECT fs.student_id, a.learning_area_id AS subject_id, 'formative' AS bucket,
                    fs.score, fs.max_score, 'present' AS entry_status
             FROM formative_scores fs
             JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = fs.assessment_id
             JOIN assessment_type_classifications atc ON atc.id = a.assessment_type_classification_id AND a.is_formative = 1
             WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id = ?
               AND a.status = 'approved'{$areaFilter}
             UNION ALL
             SELECT sae.student_id, a.learning_area_id, 'summative', ar.marks_obtained,
                    a.max_marks, ar.entry_status
             FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
             JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = ar.assessment_id
             JOIN assessment_type_classifications atc ON atc.id = a.assessment_type_classification_id AND a.is_formative = 0 = 1
             JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = ar.student_academic_enrollment_id
             WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id = ?
               AND a.status = 'approved' AND ar.deleted_at IS NULL
               AND ar.is_submitted = 1 AND ar.is_approved = 1{$areaFilter}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function weightedPercentage(?float $formative, ?float $summative, array $policy): ?float
    {
        if ($formative === null && $summative === null) return null;
        if ($formative === null && (int) $policy['require_formative_for_release'] === 1) return null;
        if ($summative === null && (int) $policy['require_summative_for_release'] === 1) return null;
        $weighted = 0.0;
        $weight = 0.0;
        if ($formative !== null) { $weighted += $formative * (float) $policy['formative_weight']; $weight += (float) $policy['formative_weight']; }
        if ($summative !== null) { $weighted += $summative * (float) $policy['summative_weight']; $weight += (float) $policy['summative_weight']; }
        return $weight > 0 ? round($weighted / $weight, 2) : null;
    }

    private function rebuildRankings(int $termId, int $academicYearClassId, string $method): int
    {
        $streams = $this->db->prepare('SELECT id FROM academic_year_class_streams WHERE academic_year_class_id = ?');
        $streams->execute([$academicYearClassId]);
        $streamIds = array_map('intval', $streams->fetchAll(PDO::FETCH_COLUMN));
        if (!$streamIds) return 0;
        $placeholders = implode(',', array_fill(0, count($streamIds), '?'));
        $this->db->prepare("DELETE FROM student_term_rankings WHERE academic_year_term_id = ? AND academic_year_class_stream_id IN ({$placeholders})")
            ->execute(array_merge([$termId], $streamIds));

        $stmt = $this->db->prepare(
            "SELECT tss.student_id, tss.academic_year_class_stream_id,
                    COUNT(*) AS subject_count, AVG(overall_percentage) AS overall_percentage,
                    AVG(overall_points) AS overall_points,
                    SUM(CASE WHEN overall_percentage IS NULL THEN 1 ELSE 0 END) AS incomplete_count,
                    expected.expected_count
             FROM " . ReadReplicaService::qualifiedRef("term_subject_scores") . " tss
             JOIN (
                SELECT sla.academic_year_class_stream_id, COUNT(DISTINCT cla.learning_area_id) AS expected_count
                FROM " . ReadReplicaService::qualifiedRef("academic_year_class_stream_learning_areas") . " sla
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " cla ON cla.id=sla.academic_year_class_learning_area_id
                WHERE sla.status IN ('planned','active','in_progress','covered')
                GROUP BY sla.academic_year_class_stream_id
             ) expected ON expected.academic_year_class_stream_id=tss.academic_year_class_stream_id
             WHERE tss.academic_year_term_id = ? AND tss.academic_year_class_stream_id IN ({$placeholders})
             GROUP BY tss.student_id, tss.academic_year_class_stream_id, expected.expected_count"
        );
        $stmt->execute(array_merge([$termId], $streamIds));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $byStream = [];
        foreach ($rows as $row) $byStream[(int) $row['academic_year_class_stream_id']][] = $row;
        $cohort = array_values(array_filter($rows, static function ($row) {
            return (int) $row['incomplete_count'] === 0 && (int) $row['subject_count'] === (int) $row['expected_count'];
        }));
        $cohortRanks = $this->ranks($cohort, $method);
        $insert = $this->db->prepare(
            'INSERT INTO student_term_rankings
                (student_id, academic_year_term_id, academic_year_class_stream_id, subject_count,
                 overall_percentage, overall_points, class_position, class_population,
                 cohort_position, cohort_population, ranking_method, calculated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $count = 0;
        foreach ($byStream as $streamId => $streamRows) {
            $eligible = array_values(array_filter($streamRows, static function ($row) {
                return (int) $row['incomplete_count'] === 0 && (int) $row['subject_count'] === (int) $row['expected_count'];
            }));
            $classRanks = $this->ranks($eligible, $method);
            foreach ($streamRows as $row) {
                $studentId = (int) $row['student_id'];
                $complete = (int) $row['incomplete_count'] === 0
                    && (int) $row['subject_count'] === (int) $row['expected_count'];
                $insert->execute([
                    $studentId, $termId, $streamId, (int) $row['subject_count'],
                    $complete ? (float) $row['overall_percentage'] : null,
                    $complete ? (float) $row['overall_points'] : null,
                    $method === 'none' || !$complete ? null : ($classRanks[$studentId] ?? null), count($streamRows),
                    $method === 'none' || !$complete ? null : ($cohortRanks[$studentId] ?? null), count($rows), $method,
                ]);
                $count++;
            }
        }
        return $count;
    }

    private function ranks(array $rows, string $method): array
    {
        usort($rows, static function (array $a, array $b): int {
            $percentage = (float) $b['overall_percentage'] <=> (float) $a['overall_percentage'];
            return $percentage !== 0 ? $percentage : ((float) $b['overall_points'] <=> (float) $a['overall_points']);
        });
        $result = [];
        $previous = null;
        $rank = 0;
        $dense = 0;
        foreach ($rows as $index => $row) {
            $key = number_format((float) $row['overall_percentage'], 2, '.', '') . ':'
                . number_format((float) $row['overall_points'], 2, '.', '');
            if ($key !== $previous) {
                $dense++;
                $rank = $method === 'competition_rank' ? $index + 1 : $dense;
                $previous = $key;
            }
            $result[(int) $row['student_id']] = $rank;
        }
        return $result;
    }
}
