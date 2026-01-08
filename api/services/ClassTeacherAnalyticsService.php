<?php
namespace App\API\Services;

use App\Database\Database;
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
            $query = "SELECT cs.id as stream_id, cs.class_id, c.name as class_name, cs.stream_name
                      FROM class_streams cs
                      JOIN classes c ON cs.class_id = c.id
                      JOIN teacher_class_assignments tca ON tca.stream_id = cs.id
                      WHERE tca.teacher_id = ? 
                        AND tca.role = 'class_teacher' 
                        AND tca.status = 'active'
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
            error_log("loadAssignedClass error: " . $e->getMessage());
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

            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
                        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female
                      FROM students 
                      WHERE stream_id = ? AND status = 'active'";
            $stmt = $this->db->query($query, [$this->streamId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get class name
            $classQuery = "SELECT 
                            CASE 
                                WHEN c.name = cs.stream_name THEN c.name
                                WHEN cs.stream_name IS NULL THEN c.name
                                ELSE CONCAT(c.name, ' ', cs.stream_name)
                            END as class_name
                          FROM class_streams cs
                          JOIN classes c ON cs.class_id = c.id
                          WHERE cs.id = ?";
            $classStmt = $this->db->query($classQuery, [$this->streamId]);
            $classResult = $classStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total' => (int) ($result['total'] ?? 0),
                'male' => (int) ($result['male'] ?? 0),
                'female' => (int) ($result['female'] ?? 0),
                'class_name' => $classResult['class_name'] ?? 'Unknown'
            ];
        } catch (Exception $e) {
            error_log("getMyStudentsStats error: " . $e->getMessage());
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

            $query = "SELECT 
                        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late,
                        COUNT(*) as total
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      WHERE s.stream_id = ? 
                        AND a.attendance_date = CURDATE()
                        AND s.status = 'active'";
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
            error_log("getTodayAttendanceStats error: " . $e->getMessage());
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
                        SUM(CASE WHEN due_date < CURDATE() THEN 1 ELSE 0 END) as overdue
                      FROM assessments a
                      WHERE a.teacher_id = ? 
                        AND a.status = 'pending'";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get graded this week
            $gradedQuery = "SELECT COUNT(*) as graded
                           FROM assessments 
                           WHERE teacher_id = ? 
                             AND status = 'graded' 
                             AND YEARWEEK(graded_at) = YEARWEEK(CURDATE())";
            $gradedStmt = $this->db->query($gradedQuery, [$this->userId]);
            $gradedResult = $gradedStmt->fetch(PDO::FETCH_ASSOC);

            return [
                'pending' => (int) ($result['pending'] ?? 0),
                'graded_this_week' => (int) ($gradedResult['graded'] ?? 0),
                'overdue' => (int) ($result['overdue'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getPendingAssessmentsStats error: " . $e->getMessage());
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
            error_log("getLessonPlansStats error: " . $e->getMessage());
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
                        SUM(CASE WHEN YEARWEEK(sent_at) = YEARWEEK(CURDATE()) THEN 1 ELSE 0 END) as sent_this_week,
                        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_responses
                      FROM parent_communications 
                      WHERE sender_id = ?";
            $stmt = $this->db->query($query, [$this->userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'total_sent' => (int) ($result['total_sent'] ?? 0),
                'sent_this_week' => (int) ($result['sent_this_week'] ?? 0),
                'unread_responses' => (int) ($result['unread_responses'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getCommunicationsStats error: " . $e->getMessage());
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

            $query = "SELECT 
                        AVG(ar.score) as average_score,
                        SUM(CASE WHEN ar.score >= 75 THEN 1 ELSE 0 END) as high_performers,
                        SUM(CASE WHEN ar.score < 40 THEN 1 ELSE 0 END) as needs_support
                      FROM assessment_results ar
                      JOIN students s ON ar.student_id = s.id
                      WHERE s.stream_id = ? AND s.status = 'active'";
            $stmt = $this->db->query($query, [$this->streamId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'average_score' => round((float) ($result['average_score'] ?? 0), 1),
                'high_performers' => (int) ($result['high_performers'] ?? 0),
                'needs_support' => (int) ($result['needs_support'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getClassPerformanceStats error: " . $e->getMessage());
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

            $query = "SELECT 
                        DATE(a.attendance_date) as date,
                        ROUND(AVG(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) * 100, 1) as percentage
                      FROM attendance a
                      JOIN students s ON a.student_id = s.id
                      WHERE s.stream_id = ?
                        AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
                        AND s.status = 'active'
                      GROUP BY DATE(a.attendance_date)
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
            error_log("getWeeklyAttendanceTrend error: " . $e->getMessage());
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

            $query = "SELECT 
                        CASE 
                            WHEN ar.score >= 80 THEN 'A (80-100)'
                            WHEN ar.score >= 60 THEN 'B (60-79)'
                            WHEN ar.score >= 40 THEN 'C (40-59)'
                            ELSE 'D (<40)'
                        END as grade_band,
                        COUNT(*) as count
                      FROM assessment_results ar
                      JOIN students s ON ar.student_id = s.id
                      WHERE s.stream_id = ? AND s.status = 'active'
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
            error_log("getAssessmentPerformanceChart error: " . $e->getMessage());
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

            $query = "SELECT 
                        ts.start_time as time,
                        sub.name as subject,
                        CONCAT(u.first_name, ' ', u.last_name) as teacher,
                        ts.room as location
                      FROM timetable_slots ts
                      JOIN subjects sub ON ts.subject_id = sub.id
                      JOIN users u ON ts.teacher_id = u.id
                      WHERE ts.stream_id = ?
                        AND ts.day_of_week = DAYNAME(CURDATE())
                      ORDER BY ts.start_time ASC";
            $stmt = $this->db->query($query, [$this->streamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getTodaySchedule error: " . $e->getMessage());
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

            $query = "SELECT 
                        CONCAT(s.first_name, ' ', s.last_name) as student_name,
                        s.admission_no,
                        ROUND(AVG(ar.score), 1) as average_score,
                        COUNT(ar.id) as assessments_taken,
                        CASE 
                            WHEN AVG(ar.score) >= 75 THEN 'Excellent'
                            WHEN AVG(ar.score) >= 50 THEN 'Good'
                            ELSE 'Needs Support'
                        END as status
                      FROM students s
                      LEFT JOIN assessment_results ar ON s.id = ar.student_id
                      WHERE s.stream_id = ? AND s.status = 'active'
                      GROUP BY s.id, s.first_name, s.last_name, s.admission_no
                      ORDER BY s.first_name, s.last_name
                      LIMIT 50";
            $stmt = $this->db->query($query, [$this->streamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getStudentAssessmentStatus error: " . $e->getMessage());
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

            $query = "SELECT 
                        s.id,
                        CONCAT(s.first_name, ' ', s.last_name) as name,
                        s.admission_no,
                        s.gender,
                        CASE 
                            WHEN a.status = 'present' THEN 'Present'
                            WHEN a.status = 'absent' THEN 'Absent'
                            WHEN a.status = 'late' THEN 'Late'
                            ELSE 'Not Marked'
                        END as attendance_today
                      FROM students s
                      LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = CURDATE()
                      WHERE s.stream_id = ? AND s.status = 'active'
                      ORDER BY s.first_name, s.last_name";
            $stmt = $this->db->query($query, [$this->streamId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getStudentRoster error: " . $e->getMessage());
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
                'student_assessment_status' => $this->getStudentAssessmentStatus(),
                'student_roster' => $this->getStudentRoster()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
