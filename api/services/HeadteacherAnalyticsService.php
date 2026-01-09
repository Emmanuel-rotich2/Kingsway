<?php
namespace App\API\Services;


use App\Database\Database;
use Exception;
use PDO;

class HeadteacherAnalyticsService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getOverview()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total_students FROM students WHERE status = 'active'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_students' => (int) ($row['total_students'] ?? 0),
            'card_type' => 'total_students'
        ];
    }

    public function getAttendanceToday()
    {
        // Uses student_attendance table with status enum: 'present', 'absent', 'late'
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            COUNT(*) as total
            FROM student_attendance WHERE date = CURDATE()");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $present = (int) ($row['present'] ?? 0);
        $absent = (int) ($row['absent'] ?? 0);
        $late = (int) ($row['late'] ?? 0);
        $total = (int) ($row['total'] ?? 0);
        $percentage = $total > 0 ? round(($present / $total) * 100) : 0;
        return [
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'percentage' => $percentage,
            'card_type' => 'attendance_today'
        ];
    }

    public function getSchedules()
    {
        // Uses class_schedules table - count today's sessions based on day_of_week
        $today = date('l'); // Get today's day name (Monday, Tuesday, etc.)
        $currentTime = date('H:i:s');

        $stmt = $this->db->query("SELECT 
            COUNT(*) as total_sessions,
            SUM(CASE WHEN start_time <= ? AND end_time >= ? THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN end_time < ? THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN start_time > ? THEN 1 ELSE 0 END) as upcoming
            FROM class_schedules 
            WHERE day_of_week = ? AND status = 'active'",
            [$currentTime, $currentTime, $currentTime, $currentTime, $today]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'total_sessions' => (int) ($row['total_sessions'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'upcoming' => (int) ($row['upcoming'] ?? 0),
            'card_type' => 'schedules'
        ];
    }

    public function getAdmissionsStats()
    {
        // Uses admission_applications table with correct status enum:
        // 'submitted', 'documents_pending', 'documents_verified', 'placement_offered', 'fees_pending', 'enrolled', 'cancelled'
        // Shows all pending admissions (not filtered by year) for complete visibility
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status IN ('submitted', 'documents_pending') THEN 1 ELSE 0 END) as pending_applications,
            SUM(CASE WHEN status = 'documents_verified' THEN 1 ELSE 0 END) as documents_verified,
            SUM(CASE WHEN status IN ('placement_offered', 'fees_pending') THEN 1 ELSE 0 END) as placement_offered,
            SUM(CASE WHEN status = 'enrolled' AND YEAR(created_at) = YEAR(NOW()) THEN 1 ELSE 0 END) as enrolled_this_year,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            COUNT(*) as total
            FROM admission_applications 
            WHERE status NOT IN ('enrolled', 'cancelled') 
               OR (status IN ('enrolled', 'cancelled') AND YEAR(created_at) = YEAR(NOW()))");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'pending_applications' => (int) ($row['pending_applications'] ?? 0),
            'documents_verified' => (int) ($row['documents_verified'] ?? 0),
            'placement_offered' => (int) ($row['placement_offered'] ?? 0),
            'enrolled_this_year' => (int) ($row['enrolled_this_year'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
            'card_type' => 'admissions'
        ];
    }

    public function getDisciplineStats()
    {
        // Uses student_discipline table with status enum: 'pending', 'resolved', 'escalated'
        // and severity enum: 'low', 'medium', 'high'
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status IN ('pending', 'escalated') THEN 1 ELSE 0 END) as open_cases,
            SUM(CASE WHEN status = 'resolved' AND MONTH(resolution_date) = MONTH(NOW()) THEN 1 ELSE 0 END) as resolved_this_month,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_severity,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_severity,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_severity,
            SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
            FROM student_discipline");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'open_cases' => (int) ($row['open_cases'] ?? 0),
            'resolved_this_month' => (int) ($row['resolved_this_month'] ?? 0),
            'low_severity' => (int) ($row['low_severity'] ?? 0),
            'medium_severity' => (int) ($row['medium_severity'] ?? 0),
            'high_severity' => (int) ($row['high_severity'] ?? 0),
            'escalated' => (int) ($row['escalated'] ?? 0),
            'card_type' => 'discipline'
        ];
    }

    public function getCommunicationsStats()
    {
        // Uses communications table with type enum: 'email', 'sms', 'notification', 'internal'
        // and status enum: 'draft', 'sent', 'scheduled', 'failed'
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN type = 'email' THEN 1 ELSE 0 END) as emails,
            SUM(CASE WHEN type = 'sms' THEN 1 ELSE 0 END) as sms,
            SUM(CASE WHEN type = 'notification' THEN 1 ELSE 0 END) as notifications,
            SUM(CASE WHEN type = 'internal' THEN 1 ELSE 0 END) as internal,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
            SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN status = 'sent' AND WEEK(created_at) = WEEK(NOW()) THEN 1 ELSE 0 END) as sent_this_week,
            COUNT(*) as total
            FROM communications");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'sent_this_week' => (int) ($row['sent_this_week'] ?? 0),
            'drafts' => (int) ($row['drafts'] ?? 0),
            'scheduled' => (int) ($row['scheduled'] ?? 0),
            'emails' => (int) ($row['emails'] ?? 0),
            'sms' => (int) ($row['sms'] ?? 0),
            'notifications' => (int) ($row['notifications'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
            'card_type' => 'communications'
        ];
    }

    public function getAssessmentsStats()
    {
        // Uses assessments table with status enum: 'pending_submission', 'submitted', 'pending_approval', 'approved'
        // and assessment_results table for actual student scores
        $stmt = $this->db->query("SELECT 
            COUNT(*) as total_assessments,
            SUM(CASE WHEN status = 'pending_submission' THEN 1 ELSE 0 END) as pending_submission,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) as pending_approval,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
            FROM assessments");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get average score from assessment_results
        $stmtResults = $this->db->query("SELECT 
            AVG(marks_obtained) as average_score,
            COUNT(*) as total_results
            FROM assessment_results WHERE is_submitted = 1");
        $resultsRow = $stmtResults->fetch(PDO::FETCH_ASSOC);

        return [
            'total_assessments' => (int) ($row['total_assessments'] ?? 0),
            'pending_submission' => (int) ($row['pending_submission'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
            'pending_approval' => (int) ($row['pending_approval'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'average_score' => round($resultsRow['average_score'] ?? 0, 2),
            'total_results' => (int) ($resultsRow['total_results'] ?? 0),
            'card_type' => 'assessments'
        ];
    }

    public function getPerformanceStats()
    {
        // Uses assessment_results table for student performance metrics
        $stmt = $this->db->query("SELECT 
            AVG(marks_obtained) as average_performance,
            SUM(CASE WHEN marks_obtained >= 75 THEN 1 ELSE 0 END) as high_performers,
            SUM(CASE WHEN marks_obtained BETWEEN 50 AND 74 THEN 1 ELSE 0 END) as average_performers,
            SUM(CASE WHEN marks_obtained < 50 THEN 1 ELSE 0 END) as low_performers
            FROM assessment_results WHERE is_submitted = 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return [
            'average_performance' => round($row['average_performance'] ?? 0, 2),
            'high_performers' => (int) ($row['high_performers'] ?? 0),
            'average_performers' => (int) ($row['average_performers'] ?? 0),
            'low_performers' => (int) ($row['low_performers'] ?? 0),
            'card_type' => 'performance',
            'chart_data' => []
        ];
    }

    public function getPendingAdmissions()
    {
        $query = "
            SELECT 
                aa.id,
                aa.application_no,
                aa.applicant_name as student_name,
                aa.grade_applying_for as class_applied,
                aa.status,
                aa.created_at as submitted_at,
                DATEDIFF(NOW(), aa.created_at) as days_pending,
                CONCAT(p.first_name, ' ', p.last_name) as parent_name,
                COALESCE(p.phone_1, p.phone_2, 'N/A') as contact
            FROM admission_applications aa
            LEFT JOIN parents p ON aa.parent_id = p.id
            WHERE aa.status IN ('submitted', 'documents_pending', 'documents_verified', 'placement_offered', 'fees_pending')
            ORDER BY aa.created_at ASC
            LIMIT 20
        ";
        $stmt = $this->db->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'total' => count($data)
        ];
    }

    public function getDisciplineCases()
    {
        $query = "
            SELECT 
                sd.id,
                sd.incident_date,
                sd.description as violation,
                sd.severity,
                sd.status,
                sd.action_taken,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.admission_no,
                CASE 
                    WHEN c.name = cs.stream_name THEN c.name
                    WHEN cs.stream_name IS NULL THEN COALESCE(c.name, 'Unknown')
                    ELSE CONCAT(c.name, ' - ', cs.stream_name)
                END as class_name
            FROM student_discipline sd
            LEFT JOIN students s ON sd.student_id = s.id
            LEFT JOIN class_streams cs ON s.stream_id = cs.id
            LEFT JOIN classes c ON cs.class_id = c.id
            WHERE sd.status IN ('pending', 'escalated')
            ORDER BY 
                CASE sd.severity 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                sd.incident_date DESC
            LIMIT 20
        ";
        $stmt = $this->db->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'total' => count($data)
        ];
    }

    /**
     * Get weekly attendance trend data for charts
     * @param int $weeks Number of weeks to include
     * @return array Chart data with labels and values
     */
    public function getWeeklyAttendanceTrend(int $weeks = 4): array
    {
        try {
            // Uses student_attendance table with 'date' column
            $query = "SELECT 
                        DATE(date) as attendance_date,
                        ROUND(AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100, 1) as percentage
                      FROM student_attendance 
                      WHERE date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
                      GROUP BY DATE(date)
                      ORDER BY attendance_date ASC";
            $stmt = $this->db->query($query, [$weeks]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = date('M d', strtotime($row['attendance_date']));
                $data[] = (float) ($row['percentage'] ?? 0);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (Exception $e) {
            error_log("getWeeklyAttendanceTrend error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }

    /**
     * Get class performance chart data
     * @return array Chart data with labels and values
     */
    public function getClassPerformanceChart(): array
    {
        try {
            // Uses assessment_results and assessments tables for class performance
            $query = "SELECT 
                        CASE 
                            WHEN c.name = cs.stream_name THEN c.name
                            WHEN cs.stream_name IS NULL THEN COALESCE(c.name, 'Unknown')
                            ELSE CONCAT(c.name, ' ', cs.stream_name)
                        END as class_name,
                        AVG(ar.marks_obtained) as average_score
                      FROM assessment_results ar
                      JOIN assessments a ON ar.assessment_id = a.id
                      JOIN students s ON ar.student_id = s.id
                      LEFT JOIN class_streams cs ON s.stream_id = cs.id
                      LEFT JOIN classes c ON cs.class_id = c.id
                      WHERE ar.is_submitted = 1 AND s.status = 'active'
                      GROUP BY c.id, c.name, cs.stream_name
                      ORDER BY average_score DESC
                      LIMIT 10";
            $stmt = $this->db->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = $row['class_name'];
                $data[] = round((float) ($row['average_score'] ?? 0), 1);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (Exception $e) {
            error_log("getClassPerformanceChart error: " . $e->getMessage());
            return ['labels' => [], 'data' => []];
        }
    }

    /**
     * Get upcoming school events from the calendar
     * @param int $limit Number of events to return
     * @return array Upcoming events
     */
    public function getUpcomingEvents(int $limit = 10): array
    {
        try {
            // Uses school_calendar table for upcoming events
            $query = "SELECT 
                        id,
                        date as event_date,
                        title,
                        description,
                        day_type as type,
                        requires_attendance
                      FROM school_calendar 
                      WHERE date >= CURDATE()
                        AND day_type IN ('special_event', 'exam_day', 'half_day', 'public_holiday', 'school_holiday')
                      ORDER BY date ASC
                      LIMIT ?";
            $stmt = $this->db->query($query, [$limit]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format the type labels for display
            foreach ($data as &$row) {
                $row['type'] = ucwords(str_replace('_', ' ', $row['type']));
            }

            return [
                'data' => $data,
                'total' => count($data)
            ];
        } catch (Exception $e) {
            error_log("getUpcomingEvents error: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * Get full dashboard data in a single call
     * @return array Complete dashboard data structure
     */
    public function getFullDashboardData(): array
    {
        return [
            'cards' => [
                'total_students' => $this->getOverview(),
                'attendance_today' => $this->getAttendanceToday(),
                'class_schedules' => $this->getSchedules(),
                'pending_admissions' => $this->getAdmissionsStats(),
                'discipline_cases' => $this->getDisciplineStats(),
                'parent_communications' => $this->getCommunicationsStats(),
                'student_assessments' => $this->getAssessmentsStats(),
                'class_performance' => $this->getPerformanceStats()
            ],
            'charts' => [
                'attendance_trend' => $this->getWeeklyAttendanceTrend(4),
                'class_performance' => $this->getClassPerformanceChart()
            ],
            'tables' => [
                'pending_admissions' => $this->getPendingAdmissions(),
                'discipline_cases' => $this->getDisciplineCases(),
                'upcoming_events' => $this->getUpcomingEvents()
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
