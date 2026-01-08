<?php
namespace App\API\Services;

use App\Database\Database;
use Exception;
use PDO;

/**
 * InternTeacherAnalyticsService
 * 
 * TIER 4: Intern/Student Teacher Dashboard Analytics
 * 
 * Purpose: Limited teaching view for supervised observation
 * - Assigned classes under supervision
 * - Lesson observations and feedback
 * - Teaching resources available
 * - Development progress tracking
 * 
 * Role: Intern/Student Teacher (Role ID: 9)
 * Data Isolation: READ-ONLY, sees only assigned classes
 * 
 * @package App\API\Services
 * @since 2025-01-07
 */
class InternTeacherAnalyticsService
{
    private $db;
    private $userId;

    public function __construct($userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }

    // =========================================================================
    // SUMMARY CARDS DATA
    // =========================================================================

    /**
     * Card 1: Assigned Classes
     * Classes under supervision
     */
    public function getAssignedClassesStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(DISTINCT tca.stream_id) as total_classes,
                        COUNT(DISTINCT sub.id) as subjects,
                        COUNT(DISTINCT s.id) as total_students
                      FROM teacher_class_assignments tca
                      LEFT JOIN class_streams cs ON tca.stream_id = cs.id
                      LEFT JOIN students s ON s.stream_id = cs.id AND s.status = 'active'
                      LEFT JOIN subjects sub ON tca.subject_id = sub.id
                      WHERE tca.teacher_id = ? 
                        AND tca.role = 'intern_teacher'
                        AND tca.status = 'active'";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_classes' => (int) ($result['total_classes'] ?? 0),
                'subjects' => (int) ($result['subjects'] ?? 0),
                'total_students' => (int) ($result['total_students'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getAssignedClassesStats error: " . $e->getMessage());
            return ['total_classes' => 0, 'subjects' => 0, 'total_students' => 0];
        }
    }

    /**
     * Card 2: Lesson Observations
     * Observations and feedback from mentor
     */
    public function getLessonObservationsStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total_observations,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as upcoming,
                        AVG(rating) as average_rating
                      FROM lesson_observations 
                      WHERE intern_id = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_observations' => (int) ($result['total_observations'] ?? 0),
                'completed' => (int) ($result['completed'] ?? 0),
                'upcoming' => (int) ($result['upcoming'] ?? 0),
                'average_rating' => round((float) ($result['average_rating'] ?? 0), 1)
            ];
        } catch (Exception $e) {
            error_log("getLessonObservationsStats error: " . $e->getMessage());
            return ['total_observations' => 0, 'completed' => 0, 'upcoming' => 0, 'average_rating' => 0];
        }
    }

    /**
     * Card 3: Teaching Resources
     * Available materials and resources
     */
    public function getTeachingResourcesStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total_resources,
                        SUM(CASE WHEN type = 'lesson_plan' THEN 1 ELSE 0 END) as lesson_plans,
                        SUM(CASE WHEN type = 'teaching_aid' THEN 1 ELSE 0 END) as teaching_aids,
                        SUM(CASE WHEN accessed_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as accessed_this_week
                      FROM teaching_resources 
                      WHERE available_to_interns = 1 OR assigned_to = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_resources' => (int) ($result['total_resources'] ?? 0),
                'lesson_plans' => (int) ($result['lesson_plans'] ?? 0),
                'teaching_aids' => (int) ($result['teaching_aids'] ?? 0),
                'accessed_this_week' => (int) ($result['accessed_this_week'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getTeachingResourcesStats error: " . $e->getMessage());
            return ['total_resources' => 0, 'lesson_plans' => 0, 'teaching_aids' => 0, 'accessed_this_week' => 0];
        }
    }

    /**
     * Card 4: Student Performance
     * Performance in classes I'm teaching
     */
    public function getStudentPerformanceStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(DISTINCT s.id) as students_taught,
                        AVG(ar.score) as average_score,
                        SUM(CASE WHEN ar.score >= 75 THEN 1 ELSE 0 END) as high_performers,
                        SUM(CASE WHEN ar.score < 40 THEN 1 ELSE 0 END) as needs_support
                      FROM students s
                      JOIN teacher_class_assignments tca ON s.stream_id = tca.stream_id
                      LEFT JOIN assessment_results ar ON s.id = ar.student_id
                      WHERE tca.teacher_id = ? 
                        AND tca.role = 'intern_teacher'
                        AND tca.status = 'active'
                        AND s.status = 'active'";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'students_taught' => (int) ($result['students_taught'] ?? 0),
                'average_score' => round((float) ($result['average_score'] ?? 0), 1),
                'high_performers' => (int) ($result['high_performers'] ?? 0),
                'needs_support' => (int) ($result['needs_support'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getStudentPerformanceStats error: " . $e->getMessage());
            return ['students_taught' => 0, 'average_score' => 0, 'high_performers' => 0, 'needs_support' => 0];
        }
    }

    /**
     * Card 5: Development Progress
     * Competency checklist progress
     */
    public function getDevelopmentProgressStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total_competencies,
                        SUM(CASE WHEN status = 'achieved' THEN 1 ELSE 0 END) as achieved,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'not_started' THEN 1 ELSE 0 END) as not_started
                      FROM intern_competencies 
                      WHERE intern_id = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = (int) ($result['total_competencies'] ?? 0);
            $achieved = (int) ($result['achieved'] ?? 0);
            $percentage = $total > 0 ? round(($achieved / $total) * 100) : 0;

            return [
                'total_competencies' => $total,
                'achieved' => $achieved,
                'in_progress' => (int) ($result['in_progress'] ?? 0),
                'not_started' => (int) ($result['not_started'] ?? 0),
                'completion_percentage' => $percentage
            ];
        } catch (Exception $e) {
            error_log("getDevelopmentProgressStats error: " . $e->getMessage());
            return ['total_competencies' => 0, 'achieved' => 0, 'in_progress' => 0, 'not_started' => 0, 'completion_percentage' => 0];
        }
    }

    // =========================================================================
    // TABLES DATA
    // =========================================================================

    /**
     * Assigned classes table
     */
    public function getAssignedClassesTable(): array
    {
        try {
            $query = "SELECT 
                        CASE 
                            WHEN c.name = cs.stream_name THEN c.name
                            WHEN cs.stream_name IS NULL THEN c.name
                            ELSE CONCAT(c.name, ' ', cs.stream_name)
                        END as class_name,
                        sub.name as subject,
                        CONCAT(m.first_name, ' ', m.last_name) as mentor,
                        COUNT(s.id) as students,
                        tca.schedule_day as day
                      FROM teacher_class_assignments tca
                      JOIN class_streams cs ON tca.stream_id = cs.id
                      JOIN classes c ON cs.class_id = c.id
                      LEFT JOIN subjects sub ON tca.subject_id = sub.id
                      LEFT JOIN users m ON tca.mentor_id = m.id
                      LEFT JOIN students s ON s.stream_id = cs.id AND s.status = 'active'
                      WHERE tca.teacher_id = ? 
                        AND tca.role = 'intern_teacher'
                        AND tca.status = 'active'
                      GROUP BY tca.id, c.name, cs.stream_name, sub.name, m.first_name, m.last_name, tca.schedule_day
                      ORDER BY c.name";
            $stmt = $this->db->query($query, [$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getAssignedClassesTable error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Observation schedule and feedback
     */
    public function getObservationsTable(): array
    {
        try {
            $query = "SELECT 
                        lo.observation_date as date,
                        CASE 
                            WHEN c.name = cs.stream_name THEN c.name
                            ELSE CONCAT(c.name, ' ', COALESCE(cs.stream_name, ''))
                        END as class_name,
                        sub.name as subject,
                        CONCAT(m.first_name, ' ', m.last_name) as observer,
                        lo.rating,
                        lo.feedback,
                        lo.status
                      FROM lesson_observations lo
                      LEFT JOIN class_streams cs ON lo.stream_id = cs.id
                      LEFT JOIN classes c ON cs.class_id = c.id
                      LEFT JOIN subjects sub ON lo.subject_id = sub.id
                      LEFT JOIN users m ON lo.observer_id = m.id
                      WHERE lo.intern_id = ?
                      ORDER BY lo.observation_date DESC
                      LIMIT 20";
            $stmt = $this->db->query($query, [$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getObservationsTable error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Development competencies progress
     */
    public function getCompetenciesTable(): array
    {
        try {
            $query = "SELECT 
                        c.name as competency,
                        c.category,
                        ic.status,
                        ic.achieved_date,
                        ic.notes
                      FROM intern_competencies ic
                      JOIN competencies c ON ic.competency_id = c.id
                      WHERE ic.intern_id = ?
                      ORDER BY c.category, c.name";
            $stmt = $this->db->query($query, [$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getCompetenciesTable error: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // FULL DASHBOARD DATA
    // =========================================================================

    /**
     * Get full dashboard data in a single call
     */
    public function getFullDashboardData(): array
    {
        return [
            'cards' => [
                'assigned_classes' => $this->getAssignedClassesStats(),
                'lesson_observations' => $this->getLessonObservationsStats(),
                'teaching_resources' => $this->getTeachingResourcesStats(),
                'student_performance' => $this->getStudentPerformanceStats(),
                'development_progress' => $this->getDevelopmentProgressStats()
            ],
            'charts' => [],  // Interns have limited charts
            'tables' => [
                'assigned_classes' => $this->getAssignedClassesTable(),
                'observations' => $this->getObservationsTable(),
                'competencies' => $this->getCompetenciesTable()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
