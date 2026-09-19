<?php
namespace App\API\Services;

/** Pure, deterministic policy used by APIs, queues and timetable planning. */
final class TeacherSpecializationPolicy
{
    public static function classBand(string $className, ?string $gradeLevel = null): string
    {
        $value = strtolower(trim($gradeLevel ?: $className));
        if (preg_match('/(?:grade|g)\s*([1-9])\b/', $value, $m)) {
            return ((int)$m[1] <= 3) ? 'lower_primary' : 'specialist';
        }
        if (preg_match('/\b(?:pp1|pp2|pre[- ]primary|playgroup)\b/', $value)) return 'lower_primary';
        return 'specialist';
    }

    public static function requiredLevelBand(string $className, ?string $gradeLevel = null): string
    {
        $value = strtolower(trim($gradeLevel ?: $className));
        if (preg_match('/(?:grade|g)\s*([1-9])\b/', $value, $m)) {
            $grade = (int)$m[1];
            return $grade <= 3 ? 'lower_primary' : ($grade <= 6 ? 'upper_primary' : 'junior_secondary');
        }
        if (preg_match('/\b(?:pp1|pp2|pre[- ]primary)\b/', $value)) return 'pp';
        return 'playgroup';
    }

    /**
     * Order candidates without assigning anyone. A class teacher is preferred
     * for early years/lower primary; approved specialists are mandatory for
     * Grade 4-9 unless an authorised user explicitly records an exception.
     */
    public static function rankCandidates(array $candidates, string $className, ?string $gradeLevel = null): array
    {
        $band = self::classBand($className, $gradeLevel);
        foreach ($candidates as &$candidate) {
            $role = strtolower((string)($candidate['assignment_role'] ?? $candidate['teaching_role'] ?? $candidate['role'] ?? ''));
            $candidate['eligibility_reason'] = $band === 'lower_primary' && $role === 'class_teacher'
                ? 'Class teacher preferred for early years/lower primary'
                : ($role === 'learning_area_specialist' || $role === 'subject_specialist'
                    ? 'Approved learning-area specialist'
                    : 'Approved teaching candidate');
            $candidate['_rank'] = ($band === 'lower_primary' && $role === 'class_teacher') ? 0 :
                (($role === 'learning_area_specialist' || $role === 'subject_specialist') ? 1 : 2);
        }
        unset($candidate);
        usort($candidates, static function (array $a, array $b): int {
            return [$a['_rank'], (int)($a['is_primary'] ?? 0) * -1, (string)($a['teacher_name'] ?? '')]
                <=> [$b['_rank'], (int)($b['is_primary'] ?? 0) * -1, (string)($b['teacher_name'] ?? '')];
        });
        foreach ($candidates as &$candidate) unset($candidate['_rank']);
        return $candidates;
    }
}
