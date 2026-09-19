<?php

namespace App\API\Services;

use PDO;

/**
 * Enrollment trend detector (deterministic, P3a).
 *
 * Extrapolates enrollment from the governed current-enrollments view:
 * headcount by class stream, month-over-month intake trend, and a forward
 * projection for the current year. Aggregates only - no learner identity.
 */
final class EnrollmentTrendDetector extends AbstractIntelligenceDetector
{
    public function detect(PDO $pdo, array $options = []): array
    {
        $view = $this->masterView('vw_current_enrollments');
        $stmt = $pdo->prepare(
            'SELECT class_stream, gender, student_status, enrollment_date
             FROM ' . $view
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byClass = [];
        $genderCounts = ['male' => 0, 'female' => 0, 'other' => 0];
        $monthlyIntake = [];
        $statusCounts = [];
        foreach ($rows as $row) {
            $class = (string) $row['class_stream'];
            $gender = (string) $row['gender'];
            $status = (string) $row['student_status'];
            if ($row['enrollment_date'] !== null) {
                $month = substr((string) $row['enrollment_date'], 0, 7);
                $monthlyIntake[$month] = ($monthlyIntake[$month] ?? 0) + 1;
            }
            $byClass[$class] = ($byClass[$class] ?? 0) + 1;
            if (isset($genderCounts[$gender])) {
                $genderCounts[$gender]++;
            }
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }

        ksort($monthlyIntake);
        $intake = [];
        foreach ($monthlyIntake as $month => $count) {
            $intake[] = ['month' => $month, 'new_enrollments' => $count];
        }

        // Forward projection: average monthly intake x remaining term months.
        $totalActive = (int) ($statusCounts['active'] ?? 0);
        $recentIntake = array_values(array_slice($monthlyIntake, -3));
        $avgMonthly = count($recentIntake) > 0 ? array_sum($recentIntake) / count($recentIntake) : 0.0;
        $forecastTotal = $totalActive + round($avgMonthly * 3);

        $alerts = [];
        if ($totalActive > 0 && $avgMonthly > 0) {
            $current = max(1, (int) $totalActive);
            $growthRatio = $avgMonthly * 3 / $current;
            if ($growthRatio >= 0.15) {
                $alerts[] = [
                    'level' => 'info',
                    'code' => 'enrollment.growth_above_trend',
                    'message' => 'Enrollment intake is trending above historical pace; review class capacity.',
                    'target_scopes' => ['director', 'school_admin'],
                ];
            }
        }

        return $this->result('enrollment', [
            'total_active_enrollments' => $totalActive,
            'by_class_stream' => $byClass,
            'by_gender' => $genderCounts,
            'monthly_intake' => $intake,
            'monthly_intake_avg' => round($avgMonthly, 1),
            'forward_projection_3_months' => (int) $forecastTotal,
        ], $alerts);
    }
}