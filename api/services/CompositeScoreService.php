<?php

declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * CBC composite score engine.
 *
 * Reads the weighting structure from the assessment policy registry
 * (assessment_policy_instruments.weighting_json — data, never code):
 *
 *   KPSEA = 20% SBA (Grade 4) + 20% SBA (Grade 5) + 60% national papers,
 *           and banks 20% of its composite forward into KJSEA.
 *   KJSEA = 20% banked KPSEA + 20% Junior School SBA (Grades 7-8) + 60%
 *           national papers, practicals and projects -> Senior School pathway.
 *
 * Deterministic-first: every component percentage comes from stored school
 * results (assessment_results for school-based SBA, national_exam_results for
 * the national papers). Missing components are reported — never fabricated —
 * so a composite only becomes `complete` when every component has evidence.
 * The Python platform can recompute multi-year cohorts from the same registry
 * rows and the shared JSON buffer; this PHP service owns the edge + snapshot.
 */
final class CompositeScoreService
{
    public function __construct(private PDO $db, private ?AssessmentPolicyService $policy = null)
    {
        $this->policy ??= new AssessmentPolicyService($this->db);
    }

    /**
     * Compute (or recompute) and snapshot one learner's composite.
     *
     * @return array{instrument:string,total:?float,completeness:string,components:array<int,array>,pathway:?string,notes:array<int,string>,stored:bool}
     */
    public function computeForStudent(int $studentId, string $instrumentCode, ?int $userId = null): array
    {
        $instrument = strtoupper(trim($instrumentCode));
        $composite = $this->policy->compositeFor($instrument);
        if (!$composite || empty($composite['weighting'])) {
            throw new RuntimeException('No composite weighting is registered for ' . $instrument, 404);
        }

        // The learner's history: per academic year, mean SBA percentage from
        // school-based summative results, and the national result when present.
        $history = $this->learnerHistory($studentId);

        $components = [];
        $notes = [];
        $weighted = 0.0;
        $weightsCovered = 0;
        $bankedTotal = null;

        foreach ($composite['weighting'] as $specification) {
            $label = (string) ($specification['label'] ?? '');
            $weight = (float) ($specification['weight'] ?? 0);
            $source = (string) ($specification['source'] ?? '');
            $percentage = null;

            if ($source === 'school_based_assessment') {
                $percentage = $this->sbaPercentageForGrades($instrument, $history, $label);
                if ($percentage === null) {
                    $notes[] = "{$label}: no school-based assessment evidence found for this learner.";
                }
            } elseif ($source === 'national_assessment') {
                if ($instrument === 'KJSEA' && str_contains($label, 'KPSEA')) {
                    // Banked component: the learner's stored KPSEA composite.
                    $banked = $this->bankedKpsea($studentId);
                    if ($banked !== null) {
                        $percentage = $banked;
                        $bankedTotal = $banked;
                    } else {
                        $notes[] = "{$label}: no stored KPSEA composite to bank forward.";
                    }
                } else {
                    $national = $this->nationalPercentage($studentId, $instrument, $history);
                    $percentage = $national;
                    if ($national === null) {
                        $notes[] = "{$label}: no national examination result recorded for this learner.";
                    }
                }
            }

            if ($percentage !== null) {
                $weighted += ((float) $percentage) * ($weight / 100.0);
                $weightsCovered += $weight;
            }
            $components[] = [
                'label' => $label,
                'weight' => $weight,
                'source' => $source,
                'percentage' => $percentage,
                'applied' => $percentage !== null,
            ];
        }

        $complete = $weightsCovered >= 99 && count(array_filter($components, fn (array $c): bool => $c['percentage'] === null)) === 0;
        $total = $complete ? round($weighted, 2) : ($weightsCovered > 0 ? round($weighted, 2) : null);
        $completeness = $complete ? 'complete' : ($weightsCovered > 0 ? 'partial' : 'insufficient_data');
        $pathway = null;
        if ($instrument === 'KJSEA' && $complete) {
            $pathway = $this->suggestPathway($total);
            if ($pathway !== null) {
                $notes[] = "Indicative pathway from the stored mapping: {$pathway}. Placement remains a KNEC decision.";
            }
        }

        $stored = $this->snapshot($studentId, $instrument, $components, $total, $pathway, $completeness, $notes, $userId);

        return [
            'instrument' => $instrument,
            'total' => $total,
            'completeness' => $completeness,
            'components' => $components,
            'pathway' => $pathway,
            'banked_kpsea' => $bankedTotal,
            'notes' => $notes,
            'stored' => $stored,
        ];
    }

    /** Mean SBA percentage for the grades a component names (e.g. "Grade 4"). */
    private function sbaPercentageForGrades(string $instrument, array $history, string $label): ?float
    {
        preg_match_all('/Grade\s+(\d+)/', $label, $matches);
        $grades = array_map('intval', $matches[1] ?? []);
        if (!$grades) {
            return null;
        }
        $collected = [];
        foreach ($history['sba_by_grade_year'] ?? [] as $entry) {
            if (in_array((int) $entry['grade'], $grades, true)) {
                $collected[] = (float) $entry['mean_percentage'];
            }
        }
        if (!$collected) {
            return null;
        }
        return round(array_sum($collected) / count($collected), 2);
    }

    /** The learner's national percentage for the instrument (KNEC result). */
    private function nationalPercentage(int $studentId, string $instrument, array $history): ?float
    {
        $type = ['KPSEA' => 'KPSEA_G6', 'KJSEA' => 'KJSEA_G9'][$instrument] ?? null;
        if ($type === null) {
            return null;
        }
        foreach ($history['national'] ?? [] as $row) {
            if (($row['exam_type'] ?? '') === $type && $row['percentage'] !== null) {
                return (float) $row['percentage'];
            }
        }
        return null;
    }

    /** The learner's stored KPSEA composite (the banked 20% into KJSEA). */
    private function bankedKpsea(int $studentId): ?float
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT total_percentage FROM learner_composite_scores
                 WHERE student_id = ? AND instrument_code = 'KPSEA' AND completeness IN ('complete','partial')
                 ORDER BY academic_year DESC, computed_at DESC LIMIT 1"
            );
            $stmt->execute([$studentId]);
            $total = $stmt->fetchColumn();
            return $total !== false && $total !== null ? (float) $total : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Senior School pathway indication from the registry pathways list, using
     * the stored national result pathway when KNEC has released it.
     */
    private function suggestPathway(?float $total): ?string
    {
        if ($total === null) {
            return null;
        }
        // Prefer the pathway KNEC actually recorded with the national result.
        try {
            $stmt = $this->db->prepare("SELECT pathway FROM national_exam_results WHERE exam_type='KJSEA_G9' ORDER BY id DESC LIMIT 1");
            $stmt->execute();
            $pathway = $stmt->fetchColumn();
            if ($pathway !== false && $pathway !== null && $pathway !== '') {
                return (string) $pathway;
            }
        } catch (Throwable) {
            // fall through to the composite band
        }
        return $total >= 60 ? 'STEM' : ($total >= 40 ? 'Social Sciences' : 'Arts & Sports Science');
    }

    /**
     * One query pass over the learner's stored evidence: SBA means per
     * grade-year and any national results. Read-only, replica-friendly.
     */
private function learnerHistory(int $studentId): array
    {
        $sba = [];
        try {
            $ec = ReadReplicaService::qualifiedRef('exam_context');
            $stmt = $this->db->prepare(
                "SELECT ec.class_name AS grade, ec.academic_year_id AS academic_year,
                        AVG(ec.marks_obtained / ec.assessment_max_marks * 100) AS mean_percentage
                     FROM {$ec} ec
                     WHERE ec.student_academic_enrollment_id IN (
                           SELECT id FROM student_academic_enrollments WHERE student_id = ?
                     )
                       AND ec.assessment_status = 'approved'
                       AND ec.exam_status = 'completed'
                       AND ec.period_status = 'completed'
                       AND ec.period_assessment_kind = 'school_based'
                       AND ec.marks_obtained IS NOT NULL
                     GROUP BY ec.class_name, ec.academic_year_id
                     ORDER BY ec.academic_year_id, ec.class_name"
            );
            $stmt->execute([$studentId]);
            $sba = array_map(static fn (array $row): array => [
                'grade' => self::gradeNumber((string) $row['grade']),
                'academic_year' => (string) $row['academic_year'],
                'mean_percentage' => round((float) $row['mean_percentage'], 2),
            ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (Throwable) {
            $sba = [];
        }

        $national = [];
        try {
            $stmt = $this->db->prepare(
                'SELECT exam_type, percentage, pathway FROM national_exam_results WHERE student_id = ? ORDER BY exam_year'
            );
            $stmt->execute([$studentId]);
            $national = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $national = [];
        }

        return ['sba_by_grade_year' => $sba, 'national' => $national];
    }

    private static function gradeNumber(string $grade): int
    {
        return (int) (preg_match('/(\d+)/', $grade, $m) === 1 ? $m[1] : 0);
    }

    /** Upsert the snapshot; recomputation overwrites, history lives in events. */
    private function snapshot(int $studentId, string $instrument, array $components, ?float $total, ?string $pathway, string $completeness, array $notes, ?int $userId): bool
    {
        try {
            $upsert = $this->db->prepare(
                'INSERT INTO learner_composite_scores
                    (student_id, instrument_code, academic_year, components_json, total_percentage, pathway, completeness, notes, computed_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    components_json = VALUES(components_json), total_percentage = VALUES(total_percentage),
                    pathway = VALUES(pathway), completeness = VALUES(completeness), notes = VALUES(notes),
                    computed_by = VALUES(computed_by), computed_at = CURRENT_TIMESTAMP'
            );
            $upsert->execute([
                $studentId,
                $instrument,
                (int) (date('Y')),
                json_encode(['components' => $components], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $total,
                $pathway,
                $completeness,
                $notes ? json_encode($notes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                $userId,
            ]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
