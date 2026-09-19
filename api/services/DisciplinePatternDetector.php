<?php

namespace App\API\Services;

use PDO;

/**
 * Discipline pattern detector (deterministic, P3a).
 *
 * Monitors discipline-zone repeat-offender and escalation patterns from the
 * discipline_incidents registry:
 *   - learners with >= N incidents (repeat offenders);
 *   - incident escalations requiring follow-up.
 *
 * Output is aggregate counts per class stream and severity mix - no learner
 * identity, incident descriptions, or disciplinary notes. This flags for
 * human review; it never imposes action, labels, or excludes any learner.
 */
final class DisciplinePatternDetector extends AbstractIntelligenceDetector
{
    /** Repeat-offender threshold: minimum incidents per learner. */
    private const MIN_REPEAT = 2;

    public function detect(PDO $pdo, array $options = []): array
    {
        $repeatThreshold = max(2, min(10, (int) ($options['min_repeat'] ?? self::MIN_REPEAT)));

        $table = $this->table('discipline_incidents');
        $enrollments = $this->masterView('vw_current_enrollments');

        $stmt = $pdo->prepare(
            'SELECT e.class_stream, COUNT(DISTINCT d.student_academic_enrollment_id) AS repeat_offenders
             FROM ' . $table . ' d
             LEFT JOIN ' . $enrollments . ' e
                    ON e.enrollment_id = d.student_academic_enrollment_id
                   AND e.is_current = 1
             GROUP BY d.student_academic_enrollment_id, e.class_stream
             HAVING COUNT(*) >= ' . $repeatThreshold
        );
        $stmt->execute();
        $repeatRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byClass = [];
        $totalFlags = 0;
        foreach ($repeatRows as $row) {
            $count = (int) $row['repeat_offenders'];
            $totalFlags += $count;
            $class = trim((string) $row['class_stream']);
            if ($class !== '') {
                $byClass[$class] = ($byClass[$class] ?? 0) + $count;
            }
        }
        $byClassList = [];
        foreach ($byClass as $class => $count) {
            $byClassList[] = ['class_stream' => $class, 'repeat_offenders' => $count];
        }

        $sevStmt = $pdo->prepare(
            'SELECT severity, COUNT(*) AS count
             FROM ' . $table . '
             GROUP BY severity'
        );
        $sevStmt->execute();
        $sevRows = $sevStmt->fetchAll(PDO::FETCH_ASSOC);
        $bySeverity = [];
        foreach ($sevRows as $row) {
            $severity = (string) $row['severity'];
            $bySeverity[$severity] = (int) $row['count'];
        }

        $alerts = [];
        if ($totalFlags > 0) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'discipline.repeat_incidents',
                'message' => $totalFlags . ' learner(s) reached ' . $repeatThreshold
                    . '+ incidents; schedule restorative follow-up.',
                'target_scopes' => ['discipline', 'headteacher'],
            ];
        }
        $escalated = (int) ($bySeverity['escalated'] ?? 0);
        if ($escalated > 0) {
            $alerts[] = [
                'level' => 'warning',
                'code' => 'discipline.escalations_pending',
                'message' => $escalated . ' escalated incident(s) awaiting resolution.',
                'target_scopes' => ['discipline', 'headteacher'],
            ];
        }

        return $this->result('discipline', [
            'repeat_offender_threshold' => $repeatThreshold,
            'repeat_offenders' => $totalFlags,
            'by_class_stream' => $byClassList,
            'severity_mix' => $bySeverity,
        ], $alerts);
    }
}