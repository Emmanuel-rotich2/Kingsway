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

        // One CBC scale for the whole school: 8 levels, with the 4-level
        // group derived from the same band (see GradingScopeService).
        $scope = new GradingScopeService($this->db);
        $gradingSystemId = $scope->canonicalSystemId($gradingSystemId);

        if ($gradingSystemId !== null) {
            $band = $scope->bandForPercentage($gradingSystemId, $percentage);
            $grade = [
                'percentage' => $percentage,
                'grade_code' => (string) $band['band_code'],
                'grade_name' => (string) $band['band_name'],
                'performance_level' => (string) $band['performance_level'],
                'points' => (float) $band['points'],
                'description' => (string) ($band['description'] ?? ''),
                'grading_system_id' => $gradingSystemId,
            ];
            // Grouped view from the band's declared group (any system), else
            // the CBC convention (ME1 -> ME).
            $group = !empty($band['group_code']) ? (string) $band['group_code'] : GradingScopeService::level4($grade['grade_code']);
            if ($group !== null) {
                $names = $scope->groupNames($gradingSystemId);
                $grade['level4_code'] = $group;
                $grade['level4_name'] = $names[$group] ?? (GradingScopeService::LEVEL4_NAMES[$group] ?? null);
                return $grade;
            }
            return self::withLevel4($grade);
        }

        $rule = self::matchRule($percentage, $this->rules());
        if ($rule === null) {
            throw new RuntimeException('The active grading scale does not cover this score', 409);
        }

        return self::withLevel4([
            'percentage' => $percentage,
            'grade_code' => (string) $rule['grade_code'],
            'grade_name' => (string) $rule['grade_name'],
            'performance_level' => (string) $rule['performance_level'],
            'points' => (float) $rule['grade_points'],
            'description' => (string) ($rule['description'] ?? ''),
        ]);
    }

    /**
     * Grade code of a percentage on the term's scale (the shared grader), or
     * null when there is no percentage. Used wherever a stored score is shown.
     */
    public function codeForPercentage($percentage, ?int $termId = null): ?string
    {
        if ($percentage === null || $percentage === '' || !is_numeric($percentage)) return null;
        try {
            $system = (new GradingScopeService($this->db))->systemForTerm($termId);
            return $this->gradeForSystem(max(0.0, min(100.0, (float) $percentage)), 100, $system)['grade_code'];
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Adds the 4-level group (EE/ME/AE/BE) of an 8-level grade. */
    public static function withLevel4(array $grade): array
    {
        $group = GradingScopeService::level4($grade['grade_code'] ?? null);
        $grade['level4_code'] = $group;
        $grade['level4_name'] = $group ? GradingScopeService::LEVEL4_NAMES[$group] : null;
        return $grade;
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
             FROM " . ReadReplicaService::qualifiedRef("grading_scales") . " gs
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

