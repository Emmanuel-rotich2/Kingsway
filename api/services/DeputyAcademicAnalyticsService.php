<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;

class DeputyAcademicAnalyticsService
{
    private HeadteacherAnalyticsService $headteacher;
    private PDO $db;
    private array $period;

    public function __construct()
    {
        $this->headteacher = new HeadteacherAnalyticsService();
        $this->db = Database::getInstance()->getConnection();
        $this->period = DashboardPeriodService::resolve($this->db, []);
    }

    /**
     * Returns a focused academic view for deputy heads.
     * Includes admissions, timetables, assessments, comms, and key charts/tables.
     */
    public function getFullDashboardData(array $filters = []): array
    {
        $this->period = DashboardPeriodService::resolve($this->db, $filters);
        $full = $this->headteacher->getFullDashboardData($filters);

        $cards = $full['cards'] ?? [];
        $charts = $full['charts'] ?? [];
        $tables = $full['tables'] ?? [];

        $lessonPlans = $this->getLessonPlanSummary();
        $assessments = $cards['student_assessments'] ?? [];
        $schedules = $cards['class_schedules'] ?? [];
        $admissions = $cards['pending_admissions'] ?? [];

        return [
            'cards' => [
                'lesson_plans' => $lessonPlans,
                'grading_status' => [
                    'pending' => (int) ($assessments['pending_submission'] ?? 0)
                        + (int) ($assessments['submitted'] ?? 0)
                        + (int) ($assessments['pending_approval'] ?? 0),
                    'overdue' => $this->countOverdueAssessments(),
                ],
                'timetable_coverage' => [
                    'percentage' => !empty($schedules['total_sessions'])
                        ? round(((int) ($schedules['completed'] ?? 0) / (int) $schedules['total_sessions']) * 100, 1)
                        : 0,
                    'total_periods' => (int) ($schedules['total_sessions'] ?? 0),
                ],
                'pending_admissions' => [
                    'pending' => (int) ($admissions['pending_applications'] ?? 0)
                        + (int) ($admissions['documents_verified'] ?? 0)
                        + (int) ($admissions['placement_offered'] ?? 0),
                    'awaiting_placement' => $this->countAtWorkflowStage('class_placement'),
                ],
                'attendance_today' => $cards['attendance_today'] ?? ['percentage' => 0, 'present' => 0],
                'workload_alerts' => $this->getWorkloadAlerts(),
            ],
            'charts' => [
                'lesson_plan_trend' => $this->getLessonPlanTrend(),
                'academic_performance' => $charts['class_performance'] ?? ['labels' => [], 'data' => []],
            ],
            'tables' => [
                'pending_placements' => $this->getPendingPlacements(),
                'incomplete_grades' => $this->getIncompleteGrades(),
            ],
            'timestamp' => $full['timestamp'] ?? date('Y-m-d H:i:s'),
            'meta' => ['scope_label' => 'Whole school academic oversight', 'filters' => $this->period],
        ];
    }

    private function getLessonPlanSummary(): array
    {
        $stmt = $this->db->prepare("SELECT
                SUM(status = 'draft') AS pending,
                SUM(status IN ('approved','delivered')) AS approved
            FROM lesson_plans WHERE DATE(created_at) BETWEEN ? AND ?");
        $stmt->execute([$this->period['date_from'], $this->period['date_to']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return ['pending' => (int) ($row['pending'] ?? 0), 'approved' => (int) ($row['approved'] ?? 0)];
    }

    private function countOverdueAssessments(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM assessments
            WHERE assessment_date BETWEEN ? AND ?
              AND assessment_date < CURDATE() AND status <> 'approved'");
        $stmt->execute([$this->period['date_from'], $this->period['date_to']]);
        return (int) $stmt->fetchColumn();
    }

    private function countAtWorkflowStage(string $stage): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM workflow_instances wi
            JOIN admission_applications aa ON aa.id=wi.reference_id
            WHERE wi.reference_type='admission_application' AND wi.current_stage=?
              AND aa.status NOT IN ('cancelled','enrolled')
              AND DATE(aa.created_at) BETWEEN ? AND ?");
        $stmt->execute([$stage, $this->period['date_from'], $this->period['date_to']]);
        return (int) $stmt->fetchColumn();
    }

    private function getWorkloadAlerts(): array
    {
        $row = $this->db->query("SELECT
                SUM(classes_assigned > 8) AS alert_count,
                SUM(classes_assigned > 12) AS critical_count
            FROM vw_staff_workload")->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['count' => (int) ($row['alert_count'] ?? 0), 'critical' => (int) ($row['critical_count'] ?? 0)];
    }

    private function getLessonPlanTrend(): array
    {
        $stmt = $this->db->prepare("SELECT DATE(created_at) plan_date, COUNT(*) total
            FROM lesson_plans
            WHERE DATE(created_at) BETWEEN ? AND ?
            GROUP BY DATE(created_at) ORDER BY plan_date");
        $stmt->execute([$this->period['date_from'], $this->period['date_to']]);
        $labels = [];
        $data = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $labels[] = date('M d', strtotime((string) $row['plan_date']));
            $data[] = (int) $row['total'];
        }
        return ['labels' => $labels, 'data' => $data];
    }

    private function getPendingPlacements(): array
    {
        $stmt = $this->db->prepare("SELECT aa.applicant_name AS student_name,
                aa.grade_applying_for AS applied_class,
                COALESCE(apt.percentage, 0) AS test_score,
                wi.current_stage AS status
            FROM admission_applications aa
            JOIN workflow_instances wi ON wi.reference_type='admission_application' AND wi.reference_id=aa.id
            LEFT JOIN admission_placement_tests apt ON apt.id=(
                SELECT apt2.id FROM admission_placement_tests apt2
                WHERE apt2.application_id=aa.id ORDER BY apt2.id DESC LIMIT 1
            )
            WHERE wi.current_stage='class_placement'
              AND aa.status NOT IN ('cancelled','enrolled')
              AND DATE(aa.created_at) BETWEEN ? AND ?
            ORDER BY aa.created_at LIMIT 20");
        $stmt->execute([$this->period['date_from'], $this->period['date_to']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getIncompleteGrades(): array
    {
        $stmt = $this->db->prepare("SELECT
                TRIM(CONCAT(COALESCE(p.first_name,''),' ',COALESCE(p.last_name,''))) AS teacher_name,
                CONCAT(c.name, CASE WHEN s.name IS NULL OR s.name=c.name THEN '' ELSE CONCAT(' ',s.name) END) AS class_name,
                ROUND(100 * (COUNT(DISTINCT sae.id) - COUNT(DISTINCT CASE WHEN ar.is_submitted=1 THEN sae.id END))
                    / NULLIF(COUNT(DISTINCT sae.id),0), 1) AS percent_pending,
                CASE WHEN a.assessment_date < CURDATE() THEN 'overdue' ELSE 'at_risk' END AS status
            FROM assessments a
            JOIN academic_year_class_streams aycs ON aycs.id=a.academic_year_class_stream_id
            JOIN academic_year_classes ayc ON ayc.id=aycs.academic_year_class_id
            JOIN classes c ON c.id=ayc.class_id
            LEFT JOIN streams s ON s.id=aycs.stream_id
            LEFT JOIN staff st ON st.id=a.assigned_by
            LEFT JOIN persons p ON p.id=st.person_id
            LEFT JOIN student_academic_enrollments sae ON sae.academic_year_class_stream_id=aycs.id AND sae.enrollment_status='active'
            LEFT JOIN assessment_results ar ON ar.assessment_id=a.id AND ar.student_academic_enrollment_id=sae.id
            WHERE a.status <> 'approved'
              AND a.assessment_date BETWEEN ? AND ?
            GROUP BY a.id,p.first_name,p.last_name,c.name,s.name,a.assessment_date
            HAVING percent_pending > 0
            ORDER BY a.assessment_date LIMIT 20");
        $stmt->execute([$this->period['date_from'], $this->period['date_to']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
