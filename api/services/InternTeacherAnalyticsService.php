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
    private array $period;

    public function __construct($userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->period = DashboardPeriodService::resolve($this->db->getConnection(), []);
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
            // Map: teacher_class_assignments → academic_year_class_learning_area_teachers
            // Map: class_streams + students with stream_id → academic_year_class_streams + student_academic_enrollments
            $query = "SELECT 
                        COUNT(DISTINCT aycs.id) as total,
                        COUNT(DISTINCT la.id) as subjects,
                        COUNT(DISTINCT sae.student_id) as total_students
                      FROM academic_year_class_learning_area_teachers ayclat
                      JOIN academic_year_class_learning_areas aycla ON ayclat.academic_year_class_learning_area_id = aycla.id
                      JOIN academic_year_classes ayc ON ayc.id = aycla.academic_year_class_id
                      JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = ayc.id
                      LEFT JOIN learning_areas la ON aycla.learning_area_id = la.id
                      LEFT JOIN student_academic_enrollments sae ON aycs.id = sae.academic_year_class_stream_id 
                        AND sae.enrollment_status = 'active'
                      WHERE ayclat.staff_id = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($result['total'] ?? 0),
                'subjects' => (int) ($result['subjects'] ?? 0),
                'total_students' => (int) ($result['total_students'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getAssignedClassesStats error: " . $e->getMessage());
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
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as pending,
                        AVG(rating) as average_rating
                      FROM lesson_observations 
                      WHERE intern_id = ? AND observation_date BETWEEN ? AND ?";
            $stmt = $this->db->query($query, [$this->userId, $this->period['date_from'], $this->period['date_to']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($result['total'] ?? 0),
                'completed' => (int) ($result['completed'] ?? 0),
                'pending' => (int) ($result['pending'] ?? 0),
                'average_rating' => round((float) ($result['average_rating'] ?? 0), 1)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getLessonObservationsStats error: " . $e->getMessage());
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
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as available,
                        SUM(CASE WHEN resource_type = 'lesson_plan' THEN 1 ELSE 0 END) as lesson_plans,
                        SUM(CASE WHEN resource_type = 'teaching_aid' THEN 1 ELSE 0 END) as teaching_aids
                      FROM teaching_materials 
                      WHERE status = 'approved' AND (access_scope IN ('school','public') OR teacher_id = ?)";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($result['total'] ?? 0),
                'available' => (int) ($result['available'] ?? 0),
                'lesson_plans' => (int) ($result['lesson_plans'] ?? 0),
                'teaching_aids' => (int) ($result['teaching_aids'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getTeachingResourcesStats error: " . $e->getMessage());
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
            // Map: teacher_class_assignments → academic_year_class_learning_area_teachers
            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        COUNT(DISTINCT sae.student_id) as students_taught,
                        AVG(ar.marks_obtained) as average_score,
                        SUM(CASE WHEN ar.marks_obtained >= 75 THEN 1 ELSE 0 END) as high_performers,
                        SUM(CASE WHEN ar.marks_obtained < 40 THEN 1 ELSE 0 END) as needs_support
                      FROM student_academic_enrollments sae
                      JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                      JOIN academic_year_classes ayc ON aycs.academic_year_class_id = ayc.id
                      JOIN academic_year_class_learning_areas aycla ON aycla.academic_year_class_id = ayc.id
                      JOIN academic_year_class_learning_area_teachers ayclat ON ayclat.academic_year_class_learning_area_id = aycla.id
                      LEFT JOIN assessment_results ar ON sae.id = ar.student_academic_enrollment_id
                      WHERE ayclat.staff_id = ?
                        AND EXISTS (
                            SELECT 1 FROM assessments a
                            WHERE a.id=ar.assessment_id AND a.assessment_date BETWEEN ? AND ?
                        )
                        AND sae.enrollment_status = 'active'";
            $stmt = $this->db->query($query, [$this->userId, $this->period['date_from'], $this->period['date_to']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'students_taught' => (int) ($result['students_taught'] ?? 0),
                'average_score' => round((float) ($result['average_score'] ?? 0), 1),
                'high_performers' => (int) ($result['high_performers'] ?? 0),
                'needs_support' => (int) ($result['needs_support'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getStudentPerformanceStats error: " . $e->getMessage());
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
                        COUNT(DISTINCT cc.id) as total_competencies,
                        COUNT(DISTINCT lc.competency_id) as completed
                      FROM core_competencies cc
                      LEFT JOIN learner_competencies lc ON lc.competency_id = cc.id AND lc.assessed_by = ?
                      WHERE cc.status = 'active'";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = (int) ($result['total_competencies'] ?? 0);
            $completed = (int) ($result['completed'] ?? 0);
            $inProgress = max(0, $total - $completed);
            $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'total_competencies' => $total,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'not_started' => 0,
                'percentage' => $percentage
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getDevelopmentProgressStats error: " . $e->getMessage());
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
            // Map: teacher_class_assignments → academic_year_class_learning_area_teachers
            // Map: class_streams → academic_year_class_streams
            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        CASE 
                            WHEN c.name = s.name THEN c.name
                            WHEN s.name IS NULL THEN c.name
                            ELSE CONCAT(c.name, ' ', s.name)
                        END as class_name,
                        la.name as subject_name,
                        NULL as mentor_name,
                        NULL as schedule,
                        COUNT(DISTINCT sae.student_id) as students
                      FROM academic_year_class_learning_area_teachers ayclat
                      JOIN academic_year_class_learning_areas aycla ON ayclat.academic_year_class_learning_area_id = aycla.id
                      JOIN academic_year_classes aac ON aac.id = aycla.academic_year_class_id
                      JOIN academic_year_class_streams aycs ON aycs.academic_year_class_id = aac.id
                      JOIN classes c ON aac.class_id = c.id
                      LEFT JOIN streams s ON aycs.stream_id = s.id
                      LEFT JOIN learning_areas la ON aycla.learning_area_id = la.id
                      LEFT JOIN student_academic_enrollments sae ON aycs.id = sae.academic_year_class_stream_id 
                        AND sae.enrollment_status = 'active'
                      WHERE ayclat.staff_id = ?
                      GROUP BY aac.id, aycs.id, c.name, s.name, la.name
                      ORDER BY c.name";
            $stmt = $this->db->query($query, [$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getAssignedClassesTable error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Observation schedule and feedback
     */
    public function getObservationsTable(): array
    {
        try {
            // Map: class_streams → classes + streams
            $query = "SELECT 
                        lo.observation_date as observation_date,
                        CASE 
                            WHEN c.name = s.name THEN c.name
                            ELSE CONCAT(c.name, ' ', COALESCE(s.name, ''))
                        END as class_name,
                        la.name as focus_area,
                        CONCAT(p.first_name, ' ', p.last_name) as observer_name,
                        lo.rating,
                        lo.feedback,
                        lo.status
                      FROM lesson_observations lo
                      LEFT JOIN classes c ON lo.class_id = c.id
                      LEFT JOIN streams s ON lo.stream_id = s.id
                      LEFT JOIN learning_areas la ON lo.learning_area_id = la.id
                      LEFT JOIN staff m ON lo.observer_id = m.id
                      LEFT JOIN persons p ON m.person_id = p.id
                      WHERE lo.intern_id = ? AND lo.observation_date BETWEEN ? AND ?
                      ORDER BY lo.observation_date DESC
                      LIMIT 20";
            $stmt = $this->db->query($query, [$this->userId, $this->period['date_from'], $this->period['date_to']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getObservationsTable error: " . $e->getMessage());
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
                        cc.name as competency,
                        cc.code as category,
                        CASE WHEN lc.id IS NOT NULL THEN 'achieved' ELSE 'not_started' END as status,
                        lc.assessed_date as achieved_date,
                        lc.teacher_notes as notes,
                        CASE WHEN lc.id IS NOT NULL THEN 100 ELSE 0 END as score
                      FROM core_competencies cc
                      LEFT JOIN learner_competencies lc ON lc.competency_id = cc.id AND lc.assessed_by = ?
                      WHERE cc.status = 'active'
                      ORDER BY cc.sort_order, cc.name";
            $stmt = $this->db->query($query, [$this->userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getCompetenciesTable error: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // FULL DASHBOARD DATA
    // =========================================================================

    /**
     * Get full dashboard data in a single call
     */
    public function getFullDashboardData(array $filters = []): array
    {
        $this->period = DashboardPeriodService::resolve($this->db->getConnection(), $filters);
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
            'timestamp' => date('Y-m-d H:i:s'),
            'meta' => ['filters' => $this->period]
        ];
    }
}
