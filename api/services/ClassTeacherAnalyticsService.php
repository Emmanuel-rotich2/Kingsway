<?php
namespace App\API\Services;

use App\Database\Database;
use App\API\Services\ReadReplicaService;
use Exception;
use PDO;

/**
 * ClassTeacherAnalyticsService
 * 
 * TIER 4: Class Teacher Dashboard Analytics
 * 
 * Purpose: Class-centric view for assigned class management
 * - My class student count and roster
 * - Class attendance tracking
 * - Student assessments and grades
 * - Lesson planning
 * - Class communications
 * 
 * Role: Class Teacher (Role ID: 7)
 * Data Isolation: ONLY sees data for their assigned class
 * 
 * @package App\API\Services
 * @since 2025-01-07
 */
class ClassTeacherAnalyticsService
{
    private $db;
    private $userId;
    private $classId;
    private $streamId;

    public function __construct($userId)
    {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->loadAssignedClass();
    }

    /**
     * Load the teacher's assigned class
     */
    private function loadAssignedClass(): void
    {
        try {
            // Find the class assigned to this teacher as class_teacher
            $query = "SELECT class_stream_id as stream_id, class_id
                      FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
                      WHERE class_teacher_id = ?
                        AND class_stream_status = 'active'
                      LIMIT 1";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->classId = (int) $result['class_id'];
                $this->streamId = (int) $result['stream_id'];
            } else {
                $this->classId = null;
                $this->streamId = null;
            }
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("loadAssignedClass error: " . $e->getMessage());
            $this->classId = null;
            $this->streamId = null;
        }
    }

    // =========================================================================
    // SUMMARY CARDS DATA
    // =========================================================================

    /**
     * Card 1: My Students
     * Students in my assigned class
     */
    public function getMyStudentsStats(): array
    {
        try {
            if (!$this->streamId) {
                return ['total' => 0, 'male' => 0, 'female' => 0, 'class_name' => 'Not Assigned'];
            }

            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN sae.gender = 'male' THEN 1 ELSE 0 END) as male,
                        SUM(CASE WHEN sae.gender = 'female' THEN 1 ELSE 0 END) as female
                      FROM " . ReadReplicaService::qualifiedRef("student_directory") . " sae
                      WHERE sae.academic_year_class_stream_id = ? 
                        AND sae.enrollment_status = 'active'";
            $stmt = $this->db->query($query, [$this->streamId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get class name from academic_calendar
            $classQuery = "SELECT 
                            CASE 
                                WHEN class_name = stream_name THEN class_name
                                WHEN stream_name IS NULL THEN class_name
                                ELSE CONCAT(class_name, ' ', stream_name)
                            END as class_name
                          FROM " . ReadReplicaService::qualifiedRef('academic_calendar') . "
                          WHERE class_stream_id = ?";
            $classStmt = $this->db->query($classQuery, [$this->streamId]);
            $classResult = $classStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($result['total'] ?? 0),
                'male' => (int) ($result['male'] ?? 0),
                'female' => (int) ($result['female'] ?? 0),
                'class_name' => $classResult['class_name'] ?? 'Unknown'
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getMyStudentsStats error: " . $e->getMessage());
            return ['total' => 0, 'male' => 0, 'female' => 0, 'class_name' => 'Error'];
        }
    }

    /**
     * Card 2: Today's Attendance
     * My class attendance for today
     */
    public function getTodayAttendanceStats(): array
    {
        try {
            if (!$this->streamId) {
                return ['present' => 0, 'absent' => 0, 'late' => 0, 'percentage' => 0];
            }

            // Map: student_attendance student_id → student_academic_enrollment_id
            $query = "SELECT 
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                        COUNT(*) as total
                      FROM " . ReadReplicaService::qualifiedRef("student_attendance_enrollment") . " 
                      WHERE sae.academic_year_class_stream_id = ?
                        AND a.date = CURDATE()
                        AND sae.enrollment_status = 'active'";
            $stmt = $this->db->query($query, [$this->streamId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $total = (int) ($result['total'] ?? 0);
            $present = (int) ($result['present'] ?? 0);
            $percentage = $total > 0 ? round(($present / $total) * 100) : 0;

            return [
                'present' => $present,
                'absent' => (int) ($result['absent'] ?? 0),
                'late' => (int) ($result['late'] ?? 0),
                'percentage' => $percentage
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getTodayAttendanceStats error: " . $e->getMessage());
            return ['present' => 0, 'absent' => 0, 'late' => 0, 'percentage' => 0];
        }
    }

    /**
     * Card 3: Pending Assessments
     * Assessments pending grading for my class
     */
    public function getPendingAssessmentsStats(): array
    {
        try {
            if (!$this->streamId) {
                return ['pending' => 0, 'graded_this_week' => 0, 'overdue' => 0];
            }

            $query = "SELECT 
                        COUNT(*) as pending,
                        SUM(CASE WHEN assessment_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                      FROM assessments a
                      WHERE a.assigned_by = ? 
                        AND a.status IN ('submitted','pending_approval')";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get graded this week (approved results submitted this week)
            $gradedQuery = "SELECT COUNT(DISTINCT ar.assessment_id) as graded
                           FROM " . ReadReplicaService::qualifiedRef("assessment_results") . "
                           JOIN " . ReadReplicaService::qualifiedRef("assessments") . " a ON a.id = ar.assessment_id
                           WHERE a.assigned_by = ?
                             AND ar.is_approved = 1
                             AND YEARWEEK(ar.submitted_at) = YEARWEEK(CURDATE())";
            $gradedStmt = $this->db->query($gradedQuery, [$this->userId]);
            $gradedResult = $gradedStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'pending' => (int) ($result['pending'] ?? 0),
                'graded_this_week' => (int) ($gradedResult['graded'] ?? 0),
                'overdue' => (int) ($result['overdue'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getPendingAssessmentsStats error: " . $e->getMessage());
            return ['pending' => 0, 'graded_this_week' => 0, 'overdue' => 0];
        }
    }

    /**
     * Card 4: Lesson Plans
     * Lesson plans for my class
     */
    public function getLessonPlansStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN YEARWEEK(lesson_date) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as this_week
                      FROM lesson_plans 
                      WHERE teacher_id = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($result['total'] ?? 0),
                'approved' => (int) ($result['approved'] ?? 0),
                'pending' => (int) ($result['pending'] ?? 0),
                'this_week' => (int) ($result['this_week'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getLessonPlansStats error: " . $e->getMessage());
            return ['total' => 0, 'approved' => 0, 'pending' => 0, 'this_week' => 0];
        }
    }

    /**
     * Card 5: Class Communications
     * Messages sent to my class/parents
     */
    public function getCommunicationsStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total_sent,
                        SUM(CASE WHEN YEARWEEK(created_at) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as sent_this_week,
                        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_responses
                      FROM communications 
                      WHERE sender_id = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_sent' => (int) ($result['total_sent'] ?? 0),
                'sent_this_week' => (int) ($result['sent_this_week'] ?? 0),
                'unread_responses' => (int) ($result['unread_responses'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getCommunicationsStats error: " . $e->getMessage());
            return ['total_sent' => 0, 'sent_this_week' => 0, 'unread_responses' => 0];
        }
    }

    /**
     * Card 6: Class Performance
     * Overall academic performance of my class
     */
    public function getClassPerformanceStats(): array
    {
        try {
            if (!$this->streamId) {
                return ['average_score' => 0, 'high_performers' => 0, 'needs_support' => 0];
            }

            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        AVG(v.percentage) as average_score,
                        SUM(CASE WHEN v.percentage >= 75 THEN 1 ELSE 0 END) as high_performers,
                        SUM(CASE WHEN v.percentage < 40 THEN 1 ELSE 0 END) as needs_support
                      FROM vw_assessment_results_detail v
                      WHERE v.academic_year_class_stream_id = ?";
            $stmt = $this->db->query($query, [$this->streamId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'average_score' => round((float) ($result['average_score'] ?? 0), 1),
                'high_performers' => (int) ($result['high_performers'] ?? 0),
                'needs_support' => (int) ($result['needs_support'] ?? 0)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getClassPerformanceStats error: " . $e->getMessage());
            return ['average_score' => 0, 'high_performers' => 0, 'needs_support' => 0];
        }
    }

    // =========================================================================
    // CHARTS DATA
    // =========================================================================

    /**
     * Weekly attendance trend for my class
     */
    public function getWeeklyAttendanceTrend(int $weeks = 4): array
    {
        try {
            if (!$this->streamId) {
                return ['labels' => [], 'data' => []];
            }

            // Map: student_attendance student_id → student_academic_enrollment_id
            $query = "SELECT 
                        DATE(a.date) as date,
                        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as percentage
                      FROM " . ReadReplicaService::qualifiedRef("student_attendance_enrollment") . " 
                      WHERE sae.academic_year_class_stream_id = ?
                        AND a.date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
                        AND sae.enrollment_status = 'active'
                      GROUP BY DATE(a.date)
                      ORDER BY date ASC";
            $stmt = $this->db->query($query, [$this->streamId, $weeks]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = date('M d', strtotime($row['date']));
                $data[] = (float) ($row['percentage'] ?? 0);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getWeeklyAttendanceTrend error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }

    /**
     * Assessment performance distribution for my class
     */
    public function getAssessmentPerformanceChart(): array
    {
        try {
            if (!$this->streamId) {
                return ['labels' => [], 'data' => []];
            }

            // Map: assessment_results uses student_academic_enrollment_id now
            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        CASE 
                            WHEN v.percentage >= 80 THEN 'A (80-100)'
                            WHEN v.percentage >= 60 THEN 'B (60-79)'
                            WHEN v.percentage >= 40 THEN 'C (40-59)'
                            ELSE 'D (<40)'
                        END as grade_band,
                        COUNT(*) as count
                      FROM vw_assessment_results_detail v
                      WHERE v.academic_year_class_stream_id = ?
                      GROUP BY grade_band
                      ORDER BY grade_band";
            $stmt = $this->db->query($query, [$this->streamId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = $row['grade_band'];
                $data[] = (int) ($row['count'] ?? 0);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getAssessmentPerformanceChart error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }

    // =========================================================================
    // TABLES DATA
    // =========================================================================

    /**
     * Today's class schedule
     */
    public function getTodaySchedule(): array
    {
        try {
            if (!$this->streamId) {
                return [];
            }

            $query = "SELECT start_time, end_time, subject_name AS subject,
                             teacher_name AS teacher, room_name, status
                        FROM vw_timetable_entries
                       WHERE academic_year_class_stream_id = ?
                         AND day_of_week = WEEKDAY(CURDATE()) + 1
                         AND status = 'scheduled'
                       ORDER BY start_time";
            $stmt = $this->db->query($query, [$this->streamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getTodaySchedule error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Student assessment status table
     */
    public function getStudentAssessmentStatus(): array
    {
        try {
            if (!$this->streamId) {
                return [];
            }

            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        CONCAT(sae.first_name, ' ', sae.last_name) as student_name,
                        sae.admission_no,
                        ROUND(AVG(v.percentage), 1) as average_score,
                        COUNT(v.result_id) as assessments_taken,
                        CASE 
                            WHEN AVG(v.percentage) >= 75 THEN 'Excellent'
                            WHEN AVG(v.percentage) >= 50 THEN 'Good'
                            ELSE 'Needs Support'
                        END as status
                      FROM " . ReadReplicaService::qualifiedRef("student_directory") . " sae
                      LEFT JOIN vw_assessment_results_detail v ON sae.enrollment_id = v.student_academic_enrollment_id
                      WHERE sae.academic_year_class_stream_id = ? 
                        AND sae.enrollment_status = 'active'
                      GROUP BY sae.enrollment_id, sae.first_name, sae.last_name, sae.admission_no
                      ORDER BY sae.first_name, sae.last_name
                      LIMIT 50";
            $stmt = $this->db->query($query, [$this->streamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getStudentAssessmentStatus error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Student roster for my class
     */
    public function getStudentRoster(): array
    {
        try {
            if (!$this->streamId) {
                return [];
            }

            // Map: students with stream_id → student_academic_enrollments
            $query = "SELECT 
                        sae.student_id as id,
                        CONCAT(sae.first_name, ' ', sae.last_name) as name,
                        sae.admission_no,
                        sae.gender,
                        CASE 
                            WHEN a.status = 'present' THEN 'Present'
                            WHEN a.status = 'absent' THEN 'Absent'
                            WHEN a.status = 'late' THEN 'Late'
                            ELSE 'Not Marked'
                        END as attendance_today
                      FROM " . ReadReplicaService::qualifiedRef("student_directory") . " sae
                      LEFT JOIN " . ReadReplicaService::qualifiedRef("student_attendance") . " a ON a.student_academic_enrollment_id = sae.enrollment_id AND a.date = CURDATE()
                      WHERE sae.academic_year_class_stream_id = ? 
                        AND sae.enrollment_status = 'active'
                      ORDER BY sae.first_name, sae.last_name";
            $stmt = $this->db->query($query, [$this->streamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getStudentRoster error: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // FULL DASHBOARD DATA
    // =========================================================================

    /**
     * Get full dashboard data in a single call
     */
    public function getUpcomingEvents(): array
    {
        try {
            $events = (new CalendarSyncService($this->db->getConnection()))->getUnifiedEvents();
            $today = date('Y-m-d');
            $events = array_values(array_filter($events, static fn(array $event): bool => ($event['start_date'] ?? '') >= $today && ($event['status'] ?? '') !== 'cancelled'));
            usort($events, static fn(array $left, array $right): int => strcmp($left['start_date'] ?? '', $right['start_date'] ?? ''));
            return array_slice($events, 0, 6);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Class teacher events error: ' . $e->getMessage());
            return [];
        }
    }

    public function getFullDashboardData(): array
    {
        return [
            'cards' => [
                'my_students' => $this->getMyStudentsStats(),
                'today_attendance' => $this->getTodayAttendanceStats(),
                'pending_assessments' => $this->getPendingAssessmentsStats(),
                'lesson_plans' => $this->getLessonPlansStats(),
                'communications' => $this->getCommunicationsStats(),
                'class_performance' => $this->getClassPerformanceStats()
            ],
            'charts' => [
                'attendance_trend' => $this->getWeeklyAttendanceTrend(4),
                'assessment_performance' => $this->getAssessmentPerformanceChart()
            ],
            'tables' => [
                'today_schedule' => $this->getTodaySchedule(),
                'upcoming_events' => $this->getUpcomingEvents(),
                'student_assessment_status' => $this->getStudentAssessmentStatus(),
                'student_roster' => $this->getStudentRoster()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
