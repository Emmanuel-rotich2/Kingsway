<?php

namespace App\API\Services;

use PDO;

/**
 * Attendance early-warning detector (deterministic, P3a).
 *
 * Flags learner groups at absence risk from the governed attendance
 * analytics view:
 *   - learners on a >=3-day consecutive absence streak within the window;
 *   - learners whose overall attendance rate is below a configurable
 *     threshold over the window.
 *
 * Outputs counts per class stream ONLY - no learner identity is returned and
 * the underlying registers are never modified.
 */
final class AttendanceEarlyWarningDetector extends AbstractIntelligenceDetector
{
    /** Window (days) over which streaks/rates are computed. */
    private const DEFAULT_WINDOW_DAYS = 14;

    /** Minimum consecutive absences that raises an early-warning flag. */
    private const MIN_CONSECUTIVE = 3;

    /** Attendance rate below which a learner group is flagged as at-risk. */
    private const MIN_ATTENDANCE_RATE_PCT = 80.0;

    public function detect(PDO $pdo, array $options = []): array
    {
        $windowDays = max(1, min(90, (int) ($options['window_days'] ?? self::DEFAULT_WINDOW_DAYS)));
        $minConsecutive = max(2, min(10, (int) ($options['min_consecutive'] ?? self::MIN_CONSECUTIVE)));
        $minRate = max(0.0, min(100.0, (float) ($options['min_attendance_rate_pct'] ?? self::MIN_ATTENDANCE_RATE_PCT)));

        $since = gmdate('Y-m-d', strtotime('-' . $windowDays . ' days'));

        // Consecutive absence streaks: sequence per learner/class over the
        // window; a streak of 'absent' status lasting >= threshold is a flag.
        $streakStmt = $pdo->prepare(
            'SELECT class_name, student_id, status FROM ' . $this->view('student_attendance_summary')
            . ' WHERE date >= ? AND status IN (\'absent\',\'present\') ORDER BY student_id, date'
        );
        $streakStmt->execute([$since]);
        $streakRows = $streakStmt->fetchAll(PDO::FETCH_ASSOC);

        $streakFlags = [];
        $cursor = null;
        $run = 0;
        foreach ($streakRows as $row) {
            $student = (int) $row['student_id'];
            $isAbsent = (string) $row['status'] === 'absent';
            if ($cursor !== $student) {
                $cursor = $student;
                $run = 0;
            }
            $run = $isAbsent ? $run + 1 : 0;
            if ($isAbsent && $run === $minConsecutive) {
                $streakFlags[] = [
                    'class_stream' => (string) $row['class_name'],
                    'consecutive_absences' => $run,
                    'flags' => 1,
                ];
            }
        }

        // Attendance-rate distribution per class stream.
        $rateStmt = $pdo->prepare(
            'SELECT class_name, COUNT(*) AS learners,
                    SUM(CASE WHEN attendance_rate_pct < ' . $minRate . ' THEN 1 ELSE 0 END) AS at_risk
             FROM ' . $this->view('student_attendance_analytics')
            . ' WHERE attendance_rate_pct IS NOT NULL GROUP BY class_name'
        );
        $rateStmt->execute();
        $rateRows = $rateStmt->fetchAll(PDO::FETCH_ASSOC);

        $byClass = [];
        foreach ($streakFlags as $flag) {
            $class = $flag['class_stream'];
            $byClass[$class]['class_stream'] = $class;
            $byClass[$class]['consecutive_absence_flags'] = ($byClass[$class]['consecutive_absence_flags'] ?? 0) + 1;
        }
        $learnersAtRisk = 0;
        foreach ($rateRows as $row) {
            $class = (string) $row['class_name'];
            $learners = (int) $row['learners'];
            $atRisk = (int) $row['at_risk'];
            $learnersAtRisk += $atRisk;
            $byClass[$class]['class_stream'] = $class;
            $byClass[$class]['learners'] = $learners;
            $byClass[$class]['learners_below_rate_threshold'] = $atRisk;
        }
        $byClass = array_values($byClass);

        $totalFlags = array_sum(array_column($byClass, 'consecutive_absence_flags'));
        $alerts = [];
        if ($totalFlags > 0) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'attendance.consecutive_absences',
                'message' => $totalFlags . ' learner(s) reached a ' . $minConsecutive
                    . '-day consecutive absence streak in the last ' . $windowDays . ' days.',
                'target_scopes' => ['attendance', 'headteacher'],
            ];
        }
        if ($learnersAtRisk > 0) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'attendance.rate_below_threshold',
                'message' => $learnersAtRisk . ' learner(s) below the ' . $minRate . '% attendance rate.',
                'target_scopes' => ['attendance', 'headteacher'],
            ];
        }

        return $this->result('attendance', [
            'window_days' => $windowDays,
            'min_consecutive_absences' => $minConsecutive,
            'attendance_rate_threshold_pct' => $minRate,
            'consecutive_absence_flags' => $totalFlags,
            'learners_below_rate_threshold' => $learnersAtRisk,
            'by_class_stream' => $byClass,
        ], $alerts);
    }
}