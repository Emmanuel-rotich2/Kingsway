<?php

namespace App\API\Services;

use PDO;

/**
 * Rubric consistency detector (deterministic, P3a).
 *
 * Checks the governed assessment results for marking/rubric anomalies:
 *   - assessments where one rubric band dominates suspiciously (a class marks
 *     uniformly above/below expectation) - possible rubric misapplication;
 *   - assessments with very few distinct grade bands (no discrimination).
 *
 * Output is per-assessment aggregate band counts only; no learner identity or
 * individual scores are returned. This flags for human moderation review; it
 * never finalizes, corrects, or publishes marks.
 */
final class RubricConsistencyDetector extends AbstractIntelligenceDetector
{
    /** Minimum assessments to consider a dominance flag statistically meaningful. */
    private const MIN_ASSESSED = 8;

    /** A single band holding >= this share of a cohort raises the flag. */
    private const DOMINANCE_SHARE = 0.85;

    /** Fewer than this many distinct bands present raises the flag. */
    private const MIN_DISTINCT_BANDS = 2;

    public function detect(PDO $pdo, array $options = []): array
    {
        $minAssessed = max(4, min(200, (int) ($options['min_assessed'] ?? self::MIN_ASSESSED)));
        $dominanceShare = max(0.5, min(1.0, (float) ($options['dominance_share'] ?? self::DOMINANCE_SHARE)));
        $minDistinctBands = max(1, min(4, (int) ($options['min_distinct_bands'] ?? self::MIN_DISTINCT_BANDS)));

        $view = $this->masterView('vw_assessment_results_detail');
        $stmt = $pdo->prepare(
            'SELECT class_name, stream_name, term_name, assessment_id,
                    COUNT(*) AS assessed,
                    SUM(CASE WHEN grade_band = \'EE\' THEN 1 ELSE 0 END) AS ee,
                    SUM(CASE WHEN grade_band = \'ME\' THEN 1 ELSE 0 END) AS me,
                    SUM(CASE WHEN grade_band = \'AE\' THEN 1 ELSE 0 END) AS ae,
                    SUM(CASE WHEN grade_band = \'BE\' THEN 1 ELSE 0 END) AS be,
                    COUNT(DISTINCT grade_band) AS distinct_bands
             FROM ' . $view . '
             WHERE grade_band IS NOT NULL AND percentage IS NOT NULL
             GROUP BY class_name, stream_name, term_name, assessment_id'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $flags = [];
        foreach ($rows as $row) {
            $assessed = (int) $row['assessed'];
            if ($assessed < $minAssessed) {
                continue;
            }
            $bands = ['EE' => (int) $row['ee'], 'ME' => (int) $row['me'], 'AE' => (int) $row['ae'], 'BE' => (int) $row['be']];
            arsort($bands);
            $topBand = array_key_first($bands);
            $topCount = (int) reset($bands);
            $share = $topCount / $assessed;
            $distinct = (int) $row['distinct_bands'];

            $id = ($row['class_name'] ?? '') . ' / ' . ($row['term_name'] ?? '') . ' / assessment #' . ($row['assessment_id'] ?? '');
            if ($share >= $dominanceShare) {
                $flags[] = [
                    'assessment_id' => (int) $row['assessment_id'],
                    'class_name' => (string) $row['class_name'],
                    'stream_name' => (string) $row['stream_name'],
                    'term_name' => (string) $row['term_name'],
                    'assessed' => $assessed,
                    'dominant_band' => $topBand,
                    'dominant_share_pct' => round($share * 100, 1),
                ];
            } elseif ($distinct < $minDistinctBands) {
                $flags[] = [
                    'assessment_id' => (int) $row['assessment_id'],
                    'class_name' => (string) $row['class_name'],
                    'stream_name' => (string) $row['stream_name'],
                    'term_name' => (string) $row['term_name'],
                    'assessed' => $assessed,
                    'dominant_band' => $topBand,
                    'distinct_bands' => $distinct,
                ];
            }
        }

        $alerts = [];
        if ($flags !== []) {
            $count = count($flags);
            $alerts[] = [
                'level' => 'warning',
                'code' => 'academics.rubric_consistency',
                'message' => $count . ' assessment(s) flagged for uniform rubric distribution; run moderation review.',
                'target_scopes' => ['academics', 'deputy_academic'],
            ];
        }

        return $this->result('academics', [
            'assessments_reviewed' => count($rows),
            'flagged' => $flags,
            'rules' => [
                'min_assessed' => $minAssessed,
                'dominance_share' => $dominanceShare,
                'min_distinct_bands' => $minDistinctBands,
            ],
        ], $alerts);
    }
}