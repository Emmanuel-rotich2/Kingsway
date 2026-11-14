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
        // The current academic year's roll (one row per learner enrolled in
        // it). vw_current_enrollments is "status active", which after
        // promotion is next year's roll, not this year's.
        $stmt = $pdo->prepare(
            "SELECT CONCAT(c.name, ' - ', COALESCE(sn.name, '')) AS class_stream,
                    p.gender, s.status AS student_status, sae.enrolled_on AS enrollment_date
             FROM " . ReadReplicaService::qualifiedRef('student_academic_enrollments') . " sae
             JOIN " . ReadReplicaService::qualifiedRef('academic_years') . " ay ON ay.id = sae.academic_year_id
             JOIN " . ReadReplicaService::qualifiedRef('students') . " s ON s.id = sae.student_id
             JOIN " . ReadReplicaService::qualifiedRef('persons') . " p ON p.id = s.person_id
             JOIN " . ReadReplicaService::qualifiedRef('academic_year_class_streams') . " aycs ON aycs.id = sae.academic_year_class_stream_id
             JOIN " . ReadReplicaService::qualifiedRef('academic_year_classes') . " ayc ON ayc.id = aycs.academic_year_class_id
             JOIN " . ReadReplicaService::qualifiedRef('classes') . " c ON c.id = ayc.class_id
             LEFT JOIN " . ReadReplicaService::qualifiedRef('streams') . " sn ON sn.id = aycs.stream_id
             WHERE ay.is_current = 1
               AND sae.enrollment_status IN ('active', 'completed', 'graduated')"
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