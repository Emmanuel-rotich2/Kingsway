<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * The single CBC term-score engine (term_subject_scores, term_strand_scores,
 * student_term_rankings). Report cards, rankings and analysis all read what
 * this writes.
 *
 * - Weights and grading system come from assessment_term_profiles (the
 *   grading page), resolved term -> academic year -> school default. A
 *   term/class/learning-area grading binding wins over the profile's system.
 * - academic_result_policies only adds ranking and release rules; a year
 *   without one uses defaults instead of refusing to compute.
 * - Formative percentage: evidence (formative_scores, rubric levels stored as
 *   level/4) grouped by sub-strand; each sub-strand counts once, so a
 *   sub-strand assessed five times does not outweigh one assessed once.
 * - Strand results: the same sub-strand groups rolled up per strand, saved
 *   to term_strand_scores for the report book.
 * - Formative evidence: every saved formative mark. Marks can only be saved
 *   while the teacher has marking open, and formative needs no sign-off.
 * - Summative percentage: approved, submitted exam marks (marks-weighted).
 */
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
        $policy = $this->policy((int) $context['academic_year_id'], $academicYearTermId);
        $roster = $this->roster($classStreamId);
        if (!$roster) throw new RuntimeException('No active learners are enrolled in this class stream', 409);
        $areas = $this->learningAreas($classStreamId, $learningAreaId);
        if (!$areas) throw new RuntimeException('No active learning areas are assigned to this class stream', 409);

        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            foreach (['term_subject_scores' => 'subject_id', 'term_strand_scores' => 'learning_area_id'] as $table => $areaColumn) {
                $deleteSql = "DELETE FROM {$table} WHERE academic_year_term_id = ? AND academic_year_class_stream_id = ?";
                $deleteParams = [$academicYearTermId, $classStreamId];
                if ($learningAreaId) {
                    $deleteSql .= " AND {$areaColumn} = ?";
                    $deleteParams[] = $learningAreaId;
                }
                $this->db->prepare($deleteSql)->execute($deleteParams);
            }

            $evidence = $this->evidence($classStreamId, $academicYearTermId, $learningAreaId);
            [$subStrandToStrand, $strandNames] = $this->strandIndex($evidence);

            $grouped = self::groupEvidence($evidence, $subStrandToStrand);

            $insert = $this->db->prepare(
                'INSERT INTO term_subject_scores
                    (student_id, term_id, academic_year_term_id, academic_year_class_stream_id, subject_id,
                     formative_total, formative_max, formative_percentage, formative_grade, formative_count,
                     summative_total, summative_max, summative_percentage, summative_grade, summative_count,
                     overall_score, overall_percentage, overall_grade, overall_points, assessment_count, calculated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $insertStrand = $this->db->prepare(
                'INSERT INTO term_strand_scores
                    (student_id, academic_year_term_id, academic_year_class_stream_id, learning_area_id, strand_id,
                     strand_name, sub_strand_count, evidence_count, percentage, grade_code, calculated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );

            $computed = 0;
            $strandRows = 0;
            foreach ($roster as $studentId => $enrollmentId) {
                foreach ($areas as $subjectId) {
                    $key = $studentId . ':' . $subjectId;
                    $aggregate = $grouped[$key] ?? [
                        'summative_total' => 0.0, 'summative_max' => 0.0, 'summative_count' => 0,
                        'formative_count' => 0, 'formative_total' => 0.0, 'formative_max' => 0.0, 'groups' => [],
                    ];
                    $systemId = $this->gradingSystemFor($academicYearTermId, (int) $context['class_id'], $subjectId, $policy);

                    $formativePercentage = self::formativePercentage($aggregate['groups']);
                    $summativePercentage = $aggregate['summative_max'] > 0
                        ? round($aggregate['summative_total'] * 100 / $aggregate['summative_max'], 2)
                        : null;
                    $overallPercentage = $this->weightedPercentage($formativePercentage, $summativePercentage, $policy);
                    $formativeGrade = $this->gradeFor($formativePercentage, $systemId);
                    $summativeGrade = $this->gradeFor($summativePercentage, $systemId);
                    $overallGrade = $this->gradeFor($overallPercentage, $systemId);
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

                    $byStrand = self::groupsByStrand($aggregate['groups']);
                    foreach ($byStrand as $strandId => $groups) {
                        $pct = round(array_sum(array_map(static fn (array $g): float => $g['score'] * 100 / $g['max'], $groups)) / count($groups), 2);
                        $grade = $this->gradeFor($pct, $systemId);
                        $insertStrand->execute([
                            $studentId, $academicYearTermId, $classStreamId, $subjectId, $strandId,
                            mb_substr((string) ($strandNames[$strandId] ?? ''), 0, 255),
                            count(array_filter($groups, static fn (array $g): bool => $g['sub_strand_id'] > 0)),
                            array_sum(array_column($groups, 'n')),
                            $pct, $grade['grade_code'] ?? null,
                        ]);
                        $strandRows++;
                    }
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
                'strand_results' => $strandRows,
                'learners' => count($roster),
                'learning_areas' => count($areas),
                'ranked' => $ranked,
                'policy' => [
                    'formative_weight' => (float) $policy['formative_weight'],
                    'summative_weight' => (float) $policy['summative_weight'],
                    'grading_system_id' => $policy['grading_system_id'],
                    'weights_from' => $policy['weights_from'],
                    'ranking_method' => $policy['ranking_method'],
                ],
            ];
        } catch (\Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Group evidence rows per learner:learning area. Each group is one
     * sub-strand (or a strand, or 'none' for formative evidence with no
     * curriculum link).
     *
     * @param list<array<string,mixed>> $evidence
     * @param array<int,int> $subStrandToStrand
     * @return array<string,array<string,mixed>>
     */
    public static function groupEvidence(array $evidence, array $subStrandToStrand): array
    {
        $grouped = [];
        foreach ($evidence as $row) {
            $key = (int) $row['student_id'] . ':' . (int) $row['subject_id'];
            $grouped[$key] ??= [
                'summative_total' => 0.0, 'summative_max' => 0.0, 'summative_count' => 0,
                'formative_count' => 0, 'formative_total' => 0.0, 'formative_max' => 0.0,
                'groups' => [],
            ];
            $bucket = (string) $row['bucket'] === 'summative' ? 'summative' : 'formative';
            $grouped[$key][$bucket . '_count']++;
            $present = ($row['entry_status'] ?? 'present') === 'present' && $row['score'] !== null && (float) $row['max_score'] > 0;
            if (!$present) continue;
            $grouped[$key][$bucket . '_total'] += (float) $row['score'];
            $grouped[$key][$bucket . '_max'] += (float) $row['max_score'];
            // Strand groups use formative evidence and any summative paper
            // tied to a sub-strand or strand.
            $subStrandId = (int) ($row['sub_strand_id'] ?? 0);
            $strandId = (int) ($row['strand_id'] ?? 0) ?: (int) ($subStrandToStrand[$subStrandId] ?? 0);
            if ($bucket === 'formative' || $subStrandId || $strandId) {
                $groupKey = $subStrandId ? 's' . $subStrandId : ($strandId ? 't' . $strandId : 'none');
                $grouped[$key]['groups'][$groupKey] ??= ['strand_id' => $strandId, 'sub_strand_id' => $subStrandId, 'score' => 0.0, 'max' => 0.0, 'n' => 0, 'formative' => false];
                $grouped[$key]['groups'][$groupKey]['score'] += (float) $row['score'];
                $grouped[$key]['groups'][$groupKey]['max'] += (float) $row['max_score'];
                $grouped[$key]['groups'][$groupKey]['n']++;
                if ($bucket === 'formative') $grouped[$key]['groups'][$groupKey]['formative'] = true;
            }
        }
        return $grouped;
    }

    /** Mean of formative sub-strand percentages; each sub-strand counts once. */
    public static function formativePercentage(array $groups): ?float
    {
        $formative = array_filter($groups, static fn (array $g): bool => $g['formative'] && $g['max'] > 0);
        if (!$formative) return null;
        return round(array_sum(array_map(static fn (array $g): float => $g['score'] * 100 / $g['max'], $formative)) / count($formative), 2);
    }

    /** @return array<int,list<array<string,mixed>>> strand id => its sub-strand groups */
    public static function groupsByStrand(array $groups): array
    {
        $byStrand = [];
        foreach ($groups as $g) {
            if ($g['max'] <= 0 || !$g['strand_id']) continue;
            $byStrand[(int) $g['strand_id']][] = $g;
        }
        return $byStrand;
    }

    /** Grade a percentage on the resolved system; null when there is no score. */
    private function gradeFor(?float $percentage, ?int $systemId): ?array
    {
        if ($percentage === null) return null;
        $percentage = max(0.0, min(100.0, $percentage));
        try {
            return $this->grading->gradeForSystem($percentage, 100, $systemId);
        } catch (\Throwable $e) {
            return $systemId !== null ? $this->grading->grade($percentage, 100) : null;
        }
    }

    /** Binding for term/class/learning area, else the weight profile's system. */
    private function gradingSystemFor(int $termId, int $classId, int $learningAreaId, array $policy): ?int
    {
        static $cache = [];
        $key = "$termId:$classId:$learningAreaId";
        if (!array_key_exists($key, $cache)) {
            $resolved = null;
            try {
                $binding = (new GradingScopeService($this->db))->resolve($termId, $classId, $learningAreaId);
                $resolved = isset($binding['system']['id']) ? (int) $binding['system']['id'] : null;
            } catch (\Throwable $e) {
                $resolved = null;
            }
            $profileSystem = $policy['grading_system_id'] !== null ? (int) $policy['grading_system_id'] : null;
            // 4-level settings resolve to the 8-level scale (one CBC scale).
            $cache[$key] = (new GradingScopeService($this->db))->canonicalSystemId($resolved ?? $profileSystem);
        }
        return $cache[$key];
    }

    private function context(int $streamId, int $termId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ayt.academic_year_id, ayt.term_id, aycs.academic_year_class_id, ayc.class_id
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

    /**
     * Weights + grading system from the grading page's profiles; ranking and
     * release rules from the year's result policy when one exists.
     */
    private function policy(int $academicYearId, int $academicYearTermId): array
    {
        $profile = null;
        try {
            $profile = (new AssessmentAggregationService($this->db))->resolveProfile(null, $academicYearTermId, $academicYearId);
        } catch (\Throwable $e) {
            $profile = null;
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM academic_result_policies WHERE academic_year_id = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$academicYearId]);
        $rules = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($profile) {
            // Profiles store whole percentages (40/60); either scale works
            // in weightedPercentage(), which divides by the weight sum.
            $formative = (float) $profile['formative_weight'];
            $summative = (float) $profile['summative_weight'];
            $from = 'grading_profile:' . ($profile['resolved_scope'] ?? 'school_default');
            $systemId = (int) $profile['grading_system_id'];
        } elseif ($rules) {
            $formative = (float) $rules['formative_weight'];
            $summative = (float) $rules['summative_weight'];
            $from = 'result_policy';
            $systemId = null;
        } else {
            $formative = 40.0;
            $summative = 60.0;
            $from = 'default';
            $systemId = null;
        }
        return [
            'formative_weight' => $formative,
            'summative_weight' => $summative,
            'grading_system_id' => $systemId,
            'weights_from' => $from,
            'ranking_method' => (string) ($rules['ranking_method'] ?? 'dense_rank'),
            'publish_rank_to_guardians' => (int) ($rules['publish_rank_to_guardians'] ?? 1),
            'require_formative_for_release' => (int) ($rules['require_formative_for_release'] ?? 0),
            'require_summative_for_release' => (int) ($rules['require_summative_for_release'] ?? 0),
        ];
    }

    /** The active weights and rules for a term, for screens and report books. */
    public function policyForTerm(int $academicYearTermId): array
    {
        $stmt = $this->db->prepare('SELECT academic_year_id FROM ' . ReadReplicaService::qualifiedRef('academic_year_terms') . ' WHERE id = ? LIMIT 1');
        $stmt->execute([$academicYearTermId]);
        $yearId = (int) $stmt->fetchColumn();
        return $this->policy($yearId, $academicYearTermId);
    }

    private function roster(int $streamId): array
    {
        $stmt = $this->db->prepare(
            // Every learner who was in this stream in its year. Promotion
            // marks a finished year's enrollments completed/graduated, and
            // limiting this to 'active' made a closed year's terms impossible
            // to recompute ("No active learners are enrolled").
            "SELECT student_id, id FROM student_academic_enrollments
             WHERE academic_year_class_stream_id = ?
               AND enrollment_status IN ('pending','active','completed','graduated','transferred','withdrawn')"
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
                    fs.score, fs.max_score, 'present' AS entry_status, a.strand_id, a.sub_strand_id
             FROM formative_scores fs
             JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = fs.assessment_id
             WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id = ?
               AND a.is_formative = 1{$areaFilter}
             UNION ALL
             SELECT sae.student_id, a.learning_area_id, 'summative', ar.marks_obtained,
                    a.max_marks, ar.entry_status, a.strand_id, a.sub_strand_id
             FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
             JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = ar.assessment_id
             JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sae.id = ar.student_academic_enrollment_id
             WHERE a.academic_year_class_stream_id = ? AND a.academic_year_term_id = ?
               AND a.is_formative = 0 AND a.status = 'approved' AND ar.deleted_at IS NULL
               AND ar.is_submitted = 1 AND ar.is_approved = 1{$areaFilter}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Sub-strand -> strand map and strand names for the evidence rows
     * (simple keyed reads; names are copied into term_strand_scores).
     *
     * @return array{0: array<int,int>, 1: array<int,string>}
     */
    private function strandIndex(array $evidence): array
    {
        $subIds = array_values(array_unique(array_filter(array_map(static fn ($r) => (int) ($r['sub_strand_id'] ?? 0), $evidence))));
        $map = [];
        if ($subIds) {
            $in = implode(',', array_fill(0, count($subIds), '?'));
            $stmt = $this->db->prepare("SELECT id, strand_id FROM sub_strands WHERE id IN ({$in})");
            $stmt->execute($subIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $map[(int) $r['id']] = (int) $r['strand_id'];
        }
        $strandIds = array_values(array_unique(array_filter(array_merge(
            array_map(static fn ($r) => (int) ($r['strand_id'] ?? 0), $evidence),
            array_values($map)
        ))));
        $names = [];
        if ($strandIds) {
            $in = implode(',', array_fill(0, count($strandIds), '?'));
            $stmt = $this->db->prepare("SELECT id, name FROM strands WHERE id IN ({$in})");
            $stmt->execute($strandIds);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $names[(int) $r['id']] = (string) $r['name'];
        }
        return [$map, $names];
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
