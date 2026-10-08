<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/** Resolves all CBC grades from the active database scale. */
final class CbcGradingService
{
    private PDO $db;
    private ?array $rules = null;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function grade(float $score, float $maxMarks): array
    {
        return $this->gradeForSystem($score, $maxMarks, null);
    }

    /**
     * Grade against a specific versioned grading system when one is resolved
     * (per-term / per-class binding or an exam-period snapshot). Null keeps
     * the legacy global-active-scale behaviour.
     */
    public function gradeForSystem(float $score, float $maxMarks, ?int $gradingSystemId): array
    {
        if ($maxMarks <= 0) {
            throw new RuntimeException('Assessment maximum marks must be greater than zero', 422);
        }
        if ($score < 0 || $score > $maxMarks) {
            throw new RuntimeException('Marks must be between zero and the assessment maximum', 422);
        }

        $percentage = round(($score / $maxMarks) * 100, 2);

        if ($gradingSystemId !== null) {
            $band = (new GradingScopeService($this->db))->bandForPercentage($gradingSystemId, $percentage);
            return [
                'percentage' => $percentage,
                'grade_code' => (string) $band['band_code'],
                'grade_name' => (string) $band['band_name'],
                'performance_level' => (string) $band['performance_level'],
                'points' => (float) $band['points'],
                'description' => (string) ($band['description'] ?? ''),
                'grading_system_id' => $gradingSystemId,
            ];
        }

        $rule = self::matchRule($percentage, $this->rules());
        if ($rule === null) {
            throw new RuntimeException('The active grading scale does not cover this score', 409);
        }

        return [
            'percentage' => $percentage,
            'grade_code' => (string) $rule['grade_code'],
            'grade_name' => (string) $rule['grade_name'],
            'performance_level' => (string) $rule['performance_level'],
            'points' => (float) $rule['grade_points'],
            'description' => (string) ($rule['description'] ?? ''),
        ];
    }

    public static function matchRule(float $percentage, array $rules): ?array
    {
        foreach ($rules as $rule) {
            if ($percentage >= (float) $rule['min_mark'] && $percentage <= (float) $rule['max_mark']) {
                return $rule;
            }
        }
        return null;
    }

    public static function band(string $gradeCode): string
    {
        return (string) preg_replace('/[0-9]+$/', '', strtoupper(trim($gradeCode)));
    }

    private function rules(): array
    {
        if ($this->rules !== null) {
            return $this->rules;
        }

        $stmt = $this->db->query(
            "SELECT gr.grade_code, gr.grade_name, gr.min_mark, gr.max_mark,
                    gr.grade_points, gr.performance_level, gr.description
             FROM " . ReadReplicaService::qualifiedRef("grading_scales") . "
             JOIN " . ReadReplicaService::qualifiedRef("grade_rules") . " gr ON gr.scale_id = gs.id
             WHERE gs.status = 'active'
             ORDER BY gs.id, gr.sort_order, gr.min_mark DESC"
        );
        $this->rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$this->rules) {
            throw new RuntimeException('No active CBC grading rules are configured', 409);
        }
        return $this->rules;
    }
}

