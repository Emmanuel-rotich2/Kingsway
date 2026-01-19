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
        $sql = "SELECT COUNT(DISTINCT c.id) as total_classes, 
                       COUNT(DISTINCT s.id) as total_students,
                       IFNULL(ROUND(COUNT(DISTINCT s.id)/NULLIF(COUNT(DISTINCT c.id),0),0),0) as average_class_size
                FROM classes c
                JOIN class_assignments ca ON ca.class_id = c.id
                -- Students are associated via streams; join class_streams then students by stream_id
                LEFT JOIN class_streams cs2 ON cs2.class_id = c.id
                LEFT JOIN students s ON s.stream_id = cs2.id AND s.status = 'active'
                WHERE ca.user_id = ? AND ca.role = 'subject_teacher'";
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
        $sql = "SELECT COUNT(DISTINCT cs.id) as total_sections, 
                       GROUP_CONCAT(DISTINCT c.name) as forms_taught, 
                       COUNT(DISTINCT cs.id) as streams_count
                FROM class_assignments ca
                JOIN class_streams cs ON cs.id = ca.stream
                JOIN classes c ON cs.class_id = c.id
                WHERE ca.user_id = ? AND ca.role = 'subject_teacher'";
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
        $sql = "SELECT COUNT(*) as pending_assessments,
                       SUM(CASE WHEN a.assessment_date >= CURDATE() AND a.assessment_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) as due_soon,
                       SUM(CASE WHEN a.assessment_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                FROM assessments a
                JOIN class_assignments ca ON ca.class_id = a.class_id AND (ca.subject_id IS NULL OR ca.subject_id = a.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
                WHERE a.status = 'pending'";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();

        // Count distinct students covered by these pending assessments (based on assessment_results)
        $studentCountSql = "SELECT COUNT(DISTINCT ar.student_id) as total_students_assessed
                            FROM assessment_results ar
                            JOIN assessments a ON ar.assessment_id = a.id
                            JOIN class_assignments ca ON ca.class_id = a.class_id AND (ca.subject_id IS NULL OR ca.subject_id = a.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
                            WHERE a.status = 'pending'";
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
        $sql = "SELECT COUNT(*) as graded_this_week,
                       IFNULL(AVG(marks_obtained),0) as average_score,
                       SUM(CASE WHEN marks_obtained >= 70 THEN 1 ELSE 0 END) as high_performers,
                       SUM(CASE WHEN marks_obtained < 40 THEN 1 ELSE 0 END) as low_performers
                FROM assessment_results ar
                JOIN assessments a ON ar.assessment_id = a.id
                JOIN class_assignments ca ON ca.class_id = a.class_id AND (ca.subject_id IS NULL OR ca.subject_id = a.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
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
        $sql = "SELECT COUNT(*) as scheduled_exams,
                       MIN(DATEDIFF(es.exam_date, CURDATE())) as next_exam_days,
                       COUNT(DISTINCT c.id) as forms_with_exams,
                       COUNT(*) as total_exam_sessions
                FROM exam_schedules es
                JOIN class_assignments ca ON ca.class_id = es.class_id AND (ca.subject_id IS NULL OR ca.subject_id = es.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
                JOIN classes c ON es.class_id = c.id
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
        $sql = "SELECT a.id, a.class_id as class, a.title, a.assessment_date as due_date
                FROM assessments a
                JOIN class_assignments ca ON ca.class_id = a.class_id AND (ca.subject_id IS NULL OR ca.subject_id = a.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
                WHERE a.status = 'pending'";
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
        $sql = "SELECT es.id, es.class_id as class, es.exam_date as date, es.start_time as time, es.room_id as room
                FROM exam_schedules es
                JOIN class_assignments ca ON ca.class_id = es.class_id AND (ca.subject_id IS NULL OR ca.subject_id = es.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
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
            $sql = "SELECT 
                        CASE 
                            WHEN c.name = cs.stream_name THEN c.name
                            ELSE CONCAT(c.name, ' ', COALESCE(cs.stream_name, ''))
                        END as class_name,
                        AVG(ar.marks_obtained) as average_score
                    FROM assessment_results ar
                    JOIN assessments a ON ar.assessment_id = a.id
                    JOIN students s ON ar.student_id = s.id
                    JOIN class_streams cs ON s.stream_id = cs.id
                    JOIN classes c ON cs.class_id = c.id
                    JOIN class_assignments ca ON ca.class_id = c.id AND (ca.subject_id IS NULL OR ca.subject_id = a.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
                    GROUP BY c.id, c.name, cs.stream_name
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
            error_log("getSubjectPerformanceChart error: " . $e->getMessage());
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
                    FROM assessment_results ar
                    JOIN assessments a ON ar.assessment_id = a.id
                    JOIN class_assignments ca ON ca.class_id = a.class_id AND (ca.subject_id IS NULL OR ca.subject_id = a.subject_id) AND ca.role = 'subject_teacher' AND ca.user_id = ?
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
            error_log("getAssessmentTrendsChart error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
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
                'pending_assessments' => $this->getPendingAssessments(),
                'exam_schedule' => $this->getExamSchedule()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
