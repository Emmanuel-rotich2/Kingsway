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
                LEFT JOIN students s ON s.class_id = c.id AND s.status = 'active'
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
        $sql = "SELECT COUNT(DISTINCT section) as total_sections, 
                       GROUP_CONCAT(DISTINCT form) as forms_taught, 
                       COUNT(DISTINCT stream) as streams_count
                FROM class_assignments
                WHERE user_id = ? AND role = 'subject_teacher'";
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
        // Query DB for pending assessments to mark
        $sql = "SELECT COUNT(*) as pending_assessments,
                       SUM(CASE WHEN due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END) as due_soon,
                       SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) as overdue,
                       SUM(students_count) as total_students_assessed
                FROM assessments
                WHERE teacher_id = ? AND status = 'pending'";
        $stmt = $this->db->query($sql, [$this->userId]);
        $row = $stmt->fetch();
        return [
            'pending_assessments' => (int) ($row['pending_assessments'] ?? 0),
            'due_soon' => (int) ($row['due_soon'] ?? 0),
            'overdue' => (int) ($row['overdue'] ?? 0),
            'total_students_assessed' => (int) ($row['total_students_assessed'] ?? 0),
            'card_type' => 'assessments_due'
        ];
    }

    public function getGradedStats()
    {
        // Query DB for assessments graded this week
        $sql = "SELECT COUNT(*) as graded_this_week,
                       IFNULL(AVG(score),0) as average_score,
                       SUM(CASE WHEN score >= 70 THEN 1 ELSE 0 END) as high_performers,
                       SUM(CASE WHEN score < 40 THEN 1 ELSE 0 END) as low_performers
                FROM assessment_results
                WHERE teacher_id = ? AND WEEK(graded_at) = WEEK(CURDATE())";
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
        // Query DB for upcoming exams
        $sql = "SELECT COUNT(*) as scheduled_exams,
                       MIN(DATEDIFF(exam_date, CURDATE())) as next_exam_days,
                       COUNT(DISTINCT form) as forms_with_exams,
                       COUNT(*) as total_exam_sessions
                FROM exams
                WHERE teacher_id = ? AND exam_date >= CURDATE()";
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
        $sql = "SELECT id, class, title, students, due_date
                FROM assessments
                WHERE teacher_id = ? AND status = 'pending'";
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
        // Query DB for upcoming exam schedule
        $sql = "SELECT id, class, date, time, room
                FROM exams
                WHERE teacher_id = ? AND date >= CURDATE()";
        $stmt = $this->db->query($sql, [$this->userId]);
        $data = $stmt->fetchAll();
        $total = count($data);
        return [
            'data' => $data,
            'total' => $total
        ];
    }
}
