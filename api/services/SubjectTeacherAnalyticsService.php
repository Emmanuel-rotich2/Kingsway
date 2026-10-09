<?php
namespace App\API\Services;

use App\Database\Database;
use Exception;

class SubjectTeacherAnalyticsService
{
    protected $db;
    protected $userId;

    public function __construct($userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }

    public function getClassesStats()
    {
        // Query DB for classes assigned to this subject teacher
        // Map: class_assignments → academic_year_class_learning_area_teachers
        // Map: class_streams + students with stream_id → student_academic_enrollments
        $sql = "SELECT COUNT(DISTINCT aac.id) as total_classes, 
                       COUNT(DISTINCT sae.student_id) as total_students,
                       IFNULL(ROUND(COUNT(DISTINCT sae.student_id)/NULLIF(COUNT(DISTINCT aac.id),0),0),0) as average_class_size
                FROM " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " ayclat
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON ayclat.academic_year_class_learning_area_id = aycla.id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " aac ON aac.id = aycla.academic_year_class_id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = aac.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON aycs.id = sae.academic_year_class_stream_id 
                  AND sae.enrollment_status = 'active'
                WHERE ayclat.staff_id = ?";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'total_classes' => (int) ($row['total_classes'] ?? 0),
            'total_students' => (int) ($row['total_students'] ?? 0),
            'average_class_size' => (int) ($row['average_class_size'] ?? 0),
            'card_type' => 'classes'
        ];
    }

    public function getSectionsStats()
    {
        // Query DB for sections/streams taught by this subject teacher
        // Map: class_assignments + class_streams → academic_year_class_learning_area_teachers + academic_year_class_streams
        $sql = "SELECT COUNT(DISTINCT aycs.id) as total_sections, 
                       GROUP_CONCAT(DISTINCT c.name) as forms_taught, 
                       COUNT(DISTINCT aycs.id) as streams_count
                FROM " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " ayclat
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON ayclat.academic_year_class_learning_area_id = aycla.id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " aac ON aac.id = aycla.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = aac.id
                JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON aac.class_id = c.id
                WHERE ayclat.staff_id = ?";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        $forms = isset($row['forms_taught']) ? explode(',', $row['forms_taught']) : [];
        return [
            'total_sections' => (int) ($row['total_sections'] ?? 0),
            'forms_taught' => $forms,
            'streams_count' => (int) ($row['streams_count'] ?? 0),
            'card_type' => 'sections'
        ];
    }

    public function getAssessmentsDueStats()
    {
        // Query DB for pending assessments that belong to classes/subjects assigned to this teacher
        $sql = "SELECT COUNT(DISTINCT a.id) as pending_assessments,
                       SUM(CASE WHEN a.assessment_date >= CURDATE() AND a.assessment_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) as due_soon,
                       SUM(CASE WHEN a.assessment_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                FROM " . ReadReplicaService::qualifiedRef("assessments") . " a
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                  AND aycla.learning_area_id = a.learning_area_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                  AND aycs.id = a.academic_year_class_stream_id
                WHERE a.status IN ('submitted','pending_approval')";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();

        // Count distinct students covered by these pending assessments (based on assessment_results)
        $studentCountSql = "SELECT COUNT(DISTINCT ar.student_academic_enrollment_id) as total_students_assessed
                            FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
                            JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON ar.assessment_id = a.id
                            JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                            JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                              AND aycla.learning_area_id = a.learning_area_id
                            JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                            JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                              AND aycs.id = a.academic_year_class_stream_id
                            WHERE a.status IN ('submitted','pending_approval')";
        $scStmt = $this->db->query($studentCountSql, [$this->userId]);
        $scRow = $scStmt->fetch();

        return [
            'pending_assessments' => (int) ($row['pending_assessments'] ?? 0),
            'due_soon' => (int) ($row['due_soon'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'total_students_assessed' => (int) ($scRow['total_students_assessed'] ?? 0),
            'card_type' => 'assessments_due'
        ];
    }

    public function getGradedStats()
    {
        // Query DB for assessments graded this week
        $sql = "SELECT COUNT(DISTINCT ar.id) as graded_this_week,
                       IFNULL(AVG(ar.marks_obtained),0) as average_score,
                       SUM(CASE WHEN ar.marks_obtained >= 70 THEN 1 ELSE 0 END) as high_performers,
                       SUM(CASE WHEN ar.marks_obtained < 40 THEN 1 ELSE 0 END) as low_performers
                FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
                JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON ar.assessment_id = a.id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                  AND aycla.learning_area_id = a.learning_area_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                  AND aycs.id = a.academic_year_class_stream_id
                WHERE WEEK(ar.submitted_at) = WEEK(CURDATE()) AND ar.is_submitted = 1";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'graded_this_week' => (int) ($row['graded_this_week'] ?? 0),
            'average_score' => round((float) ($row['average_score'] ?? 0), 2),
            'high_performers' => (int) ($row['high_performers'] ?? 0),
            'low_performers' => (int) ($row['low_performers'] ?? 0),
            'card_type' => 'graded'
        ];
    }

    public function getExamsStats()
    {
        // Query DB for upcoming exams scoped to this teacher via assignments
        $sql = "SELECT COUNT(DISTINCT es.id) as scheduled_exams,
                       MIN(DATEDIFF(es.exam_date, CURDATE())) as next_exam_days,
                       COUNT(DISTINCT c.id) as forms_with_exams,
                       COUNT(DISTINCT es.id) as total_exam_sessions
                FROM " . ReadReplicaService::qualifiedRef("exam_schedules") . " es
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                  AND aycla.learning_area_id = es.learning_area_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                  AND aycs.id = es.academic_year_class_stream_id
                JOIN " . ReadReplicaService::qualifiedRef("classes") . " c ON ayc.class_id = c.id
                WHERE es.exam_date >= CURDATE()";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'scheduled_exams' => (int) ($row['scheduled_exams'] ?? 0),
            'next_exam_days' => (int) ($row['next_exam_days'] ?? 0),
            'forms_with_exams' => (int) ($row['forms_with_exams'] ?? 0),
            'total_exam_sessions' => (int) ($row['total_exam_sessions'] ?? 0),
            'card_type' => 'exams'
        ];
    }

    public function getLessonPlansStats()
    {
        // Query DB for lesson plans created by this teacher
        $sql = "SELECT COUNT(*) as total_lesson_plans,
                       SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as created_this_month,
                       SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_review,
                       SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
                FROM lesson_plans
                WHERE teacher_id = ?";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'total_lesson_plans' => (int) ($row['total_lesson_plans'] ?? 0),
            'created_this_month' => (int) ($row['created_this_month'] ?? 0),
            'pending_review' => (int) ($row['pending_review'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'card_type' => 'lesson_plans'
        ];
    }

    public function getPendingAssessments()
    {
        // Query DB for pending assessments list
        $sql = "SELECT a.id, a.academic_year_class_stream_id as class, a.title, a.assessment_date as due_date
                FROM " . ReadReplicaService::qualifiedRef("assessments") . " a
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                  AND aycla.learning_area_id = a.learning_area_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                  AND aycs.id = a.academic_year_class_stream_id
                WHERE a.status IN ('submitted','pending_approval')";
        $stmt = $this->db->query($sql, [$this->userId]);
        $data = $stmt->fetchAll();
        $total = count($data);
        return [
            'data' => $data,
            'total' => $total
        ];
    }

    public function getExamSchedule()
    {
        // Query DB for upcoming exam schedule scoped to this teacher via assignments
        $sql = "SELECT es.id, es.academic_year_class_stream_id as class, es.exam_date as date, es.start_time as time, es.room_id as room
                FROM " . ReadReplicaService::qualifiedRef("exam_schedules") . " es
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                  AND aycla.learning_area_id = es.learning_area_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                  AND aycs.id = es.academic_year_class_stream_id
                WHERE es.exam_date >= CURDATE()
                ORDER BY es.exam_date, es.start_time";
        $stmt = $this->db->query($sql, [$this->userId]);
        $data = $stmt->fetchAll();
        $total = count($data);
        return [
            'data' => $data,
            'total' => $total
        ];
    }

    /**
     * Get subject performance by class chart data
     */
    public function getSubjectPerformanceChart(): array
    {
        try {
            // Map: class_streams + students with stream_id → academic_year_class_streams + student_academic_enrollments
            $sql = "SELECT 
                        CASE 
                            WHEN v.class_name = v.stream_name THEN v.class_name
                            ELSE CONCAT(v.class_name, ' ', COALESCE(v.stream_name, ''))
                        END as class_name,
                        AVG(v.percentage) as average_score
                    FROM vw_assessment_results_detail v
                    JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON v.assessment_id = a.id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                      AND aycla.learning_area_id = a.learning_area_id
                    GROUP BY v.class_name, v.stream_name
                    ORDER BY average_score DESC
                    LIMIT 10";
            $stmt = $this->db->query($sql, [$this->userId]);
            $rows = $stmt->fetchAll();

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = $row['class_name'] ?? 'Unknown';
                $data[] = round((float) ($row['average_score'] ?? 0), 1);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("getSubjectPerformanceChart error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }

    /**
     * Get assessment trends over time
     */
    public function getAssessmentTrendsChart(): array
    {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(ar.submitted_at, '%Y-%m') as month,
                        AVG(ar.marks_obtained) as average_score,
                        COUNT(DISTINCT a.id) as assessments_count
                    FROM " . ReadReplicaService::qualifiedRef("assessment_results") . " ar
                    JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON ar.assessment_id = a.id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_area_teachers") . " aclat ON aclat.staff_id = ?
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_learning_areas") . " aycla ON aycla.id = aclat.academic_year_class_learning_area_id
                      AND aycla.learning_area_id = a.learning_area_id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_classes") . " ayc ON ayc.id = aycla.academic_year_class_id
                    JOIN " . ReadReplicaService::qualifiedRef("academic_year_class_streams") . " aycs ON aycs.academic_year_class_id = ayc.id
                      AND aycs.id = a.academic_year_class_stream_id
                    WHERE ar.is_submitted = 1 
                        AND ar.submitted_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(ar.submitted_at, '%Y-%m')
                    ORDER BY month ASC";
            $stmt = $this->db->query($sql, [$this->userId]);
            $rows = $stmt->fetchAll();

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = date('M Y', strtotime($row['month'] . '-01'));
                $data[] = round((float) ($row['average_score'] ?? 0), 1);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (\Exception $e) {
            \App\API\Services\Logger::legacyError("getAssessmentTrendsChart error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }

    public function getTodaySchedule(): array
    {
        try {
            $stmt = $this->db->query(
                "SELECT start_time, end_time, subject_name, class_name, stream_name,
                        room_name, status
                   FROM vw_timetable_entries
                  WHERE teacher_id = ? AND day_of_week = WEEKDAY(CURDATE()) + 1
                    AND status = 'scheduled'
                  ORDER BY start_time",
                [$this->userId]
            );
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Subject teacher schedule error: ' . $e->getMessage());
            return [];
        }
    }

    public function getUpcomingEvents(): array
    {
        try {
            $events = (new CalendarSyncService($this->db->getConnection()))->getUnifiedEvents();
            $today = date('Y-m-d');
            $events = array_values(array_filter($events, static fn(array $event): bool => ($event['start_date'] ?? '') >= $today && ($event['status'] ?? '') !== 'cancelled'));
            usort($events, static fn(array $left, array $right): int => strcmp($left['start_date'] ?? '', $right['start_date'] ?? ''));
            return array_slice($events, 0, 6);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Subject teacher events error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get full dashboard data in a single call
     */
    public function getFullDashboardData(): array
    {
        return [
            'cards' => [
                'classes' => $this->getClassesStats(),
                'sections' => $this->getSectionsStats(),
                'assessments_due' => $this->getAssessmentsDueStats(),
                'graded' => $this->getGradedStats(),
                'exams' => $this->getExamsStats(),
                'lesson_plans' => $this->getLessonPlansStats()
            ],
            'charts' => [
                'subject_performance' => $this->getSubjectPerformanceChart(),
                'assessment_trends' => $this->getAssessmentTrendsChart()
            ],
            'tables' => [
                'today_schedule' => $this->getTodaySchedule(),
                'upcoming_events' => $this->getUpcomingEvents(),
                'pending_assessments' => $this->getPendingAssessments(),
                'exam_schedule' => $this->getExamSchedule()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
