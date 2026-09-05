<?php
namespace App\API\Services;


use App\Database\Database;
use Exception;
use PDO;

class HeadteacherAnalyticsService
{
    protected $db;
    private array $period;

    public function __construct(array $filters = [])
    {
        $this->db = Database::getInstance();
        $this->period = DashboardPeriodService::resolve($this->db->getConnection(), $filters);
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
        // Map: student_attendance uses student_academic_enrollment_id now
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
            COUNT(*) as total
            FROM student_attendance WHERE date BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
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
        // Expand recurring timetable entries across the selected calendar range.
        $currentTime = date('H:i:s');
        $today = date('Y-m-d');
        $totals = ['total_sessions' => 0, 'in_progress' => 0, 'completed' => 0, 'upcoming' => 0];
        $stmt = $this->db->getConnection()->prepare(
            "SELECT COUNT(*) AS total_sessions,
                    SUM(start_time <= ? AND end_time >= ?) AS in_progress,
                    SUM(end_time < ?) AS completed_today,
                    SUM(start_time > ?) AS upcoming_today
             FROM vw_timetable_entries
             WHERE day_of_week = ? AND status = 'scheduled'"
        );
        $cursor = new \DateTimeImmutable($this->period['date_from']);
        $end = new \DateTimeImmutable($this->period['date_to']);
        while ($cursor <= $end) {
            $date = $cursor->format('Y-m-d');
            $stmt->execute([$currentTime, $currentTime, $currentTime, $currentTime, (int) $cursor->format('N')]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $count = (int) ($row['total_sessions'] ?? 0);
            $totals['total_sessions'] += $count;
            if ($date < $today) {
                $totals['completed'] += $count;
            } elseif ($date > $today) {
                $totals['upcoming'] += $count;
            } else {
                $totals['in_progress'] += (int) ($row['in_progress'] ?? 0);
                $totals['completed'] += (int) ($row['completed_today'] ?? 0);
                $totals['upcoming'] += (int) ($row['upcoming_today'] ?? 0);
            }
            $cursor = $cursor->modify('+1 day');
        }
        return [
            'total_sessions' => $totals['total_sessions'],
            'in_progress' => $totals['in_progress'],
            'completed' => $totals['completed'],
            'upcoming' => $totals['upcoming'],
            'total' => $totals['total_sessions'],
            'unassigned' => $this->countUnassignedLessons(),
            'card_type' => 'schedules'
        ];
    }

    private function countUnassignedLessons(): int
    {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM vw_timetable_entries WHERE status = 'scheduled' AND (teacher_id IS NULL OR teacher_id = 0)");
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
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
            WHERE DATE(created_at) BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
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
        // Map: student_discipline → discipline_incidents
        $stmt = $this->db->query("SELECT 
            SUM(CASE WHEN status IN ('pending', 'escalated') THEN 1 ELSE 0 END) as open_cases,
            SUM(CASE WHEN status = 'resolved' AND MONTH(updated_at) = MONTH(NOW()) THEN 1 ELSE 0 END) as resolved_this_month,
            SUM(CASE WHEN severity = 'low' THEN 1 ELSE 0 END) as low_severity,
            SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) as medium_severity,
            SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) as high_severity,
            SUM(CASE WHEN status = 'escalated' THEN 1 ELSE 0 END) as escalated
            FROM discipline_incidents
            WHERE incident_date BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
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
            FROM communications
            WHERE DATE(created_at) BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
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
            FROM assessments
            WHERE assessment_date BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get average score from assessment_results
        $stmtResults = $this->db->query("SELECT 
            AVG(marks_obtained) as average_score,
            COUNT(*) as total_results
            FROM assessment_results ar
            JOIN assessments a ON a.id = ar.assessment_id
            WHERE ar.is_submitted = 1 AND a.assessment_date BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
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
            FROM assessment_results ar
            JOIN assessments a ON a.id = ar.assessment_id
            WHERE ar.is_submitted = 1 AND a.assessment_date BETWEEN ? AND ?", [$this->period['date_from'], $this->period['date_to']]);
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
                CONCAT(pp.first_name, ' ', pp.last_name) as parent_name,
                COALESCE(pp.phone, pp.email, 'N/A') as contact
            FROM admission_applications aa
            LEFT JOIN parents pa ON aa.parent_id = pa.id
            LEFT JOIN persons pp ON pa.person_id = pp.id
            WHERE aa.status IN ('submitted', 'documents_pending', 'documents_verified', 'placement_offered', 'fees_pending')
              AND DATE(aa.created_at) BETWEEN ? AND ?
            ORDER BY aa.created_at ASC
            LIMIT 20
        ";
        $stmt = $this->db->query($query, [$this->period['date_from'], $this->period['date_to']]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'data' => $data,
            'total' => count($data)
        ];
    }

    public function getDisciplineCases()
    {
        // Map: student_discipline → discipline_incidents
        // Map: students with stream_id → student_academic_enrollments
        // Map: class_streams → academic_year_class_streams
        $query = "
            SELECT 
                di.id,
                di.incident_date,
                di.description as violation,
                di.severity,
                di.status,
                di.action_taken,
                CONCAT(p.first_name, ' ', p.last_name) as student_name,
                st.admission_no,
                CASE 
                    WHEN c.name = s.name THEN c.name
                    WHEN s.name IS NULL THEN COALESCE(c.name, 'Unknown')
                    ELSE CONCAT(c.name, ' - ', s.name)
                END as class_name
            FROM discipline_incidents di
            LEFT JOIN student_academic_enrollments sae ON di.student_academic_enrollment_id = sae.id
            LEFT JOIN students st ON sae.student_id = st.id
            LEFT JOIN persons p ON st.person_id = p.id
            LEFT JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
            LEFT JOIN academic_year_classes aac ON aycs.academic_year_class_id = aac.id
            LEFT JOIN classes c ON aac.class_id = c.id
            LEFT JOIN streams s ON aycs.stream_id = s.id
            WHERE di.status IN ('pending', 'escalated')
              AND di.incident_date BETWEEN ? AND ?
            ORDER BY 
                CASE di.severity 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                di.incident_date DESC
            LIMIT 20
        ";
        $stmt = $this->db->query($query, [$this->period['date_from'], $this->period['date_to']]);
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
            // Map: student_attendance uses student_academic_enrollment_id now
            $query = "SELECT 
                        DATE(date) as attendance_date,
                        ROUND(AVG(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100, 1) as percentage
                      FROM student_attendance 
                      WHERE date BETWEEN ? AND ?
                      GROUP BY DATE(date)
                      ORDER BY attendance_date ASC";
            $stmt = $this->db->query($query, [$this->period['date_from'], $this->period['date_to']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = date('M d', strtotime($row['attendance_date']));
                $data[] = (float) ($row['percentage'] ?? 0);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getWeeklyAttendanceTrend error: " . $e->getMessage());
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
            // Map: students with stream_id → student_academic_enrollments
            // Map: class_streams → academic_year_class_streams
            $query = "SELECT 
                        CASE 
                            WHEN c.name = s.name THEN c.name
                            WHEN s.name IS NULL THEN COALESCE(c.name, 'Unknown')
                            ELSE CONCAT(c.name, ' ', s.name)
                        END as class_name,
                        AVG(ar.marks_obtained) as average_score
                      FROM assessment_results ar
                      JOIN assessments a ON a.id = ar.assessment_id
                      JOIN student_academic_enrollments sae ON ar.student_academic_enrollment_id = sae.id
                      JOIN academic_year_class_streams aycs ON sae.academic_year_class_stream_id = aycs.id
                      JOIN academic_year_classes aac ON aycs.academic_year_class_id = aac.id
                      JOIN classes c ON aac.class_id = c.id
                      JOIN streams s ON aycs.stream_id = s.id
                      WHERE ar.is_submitted = 1 AND sae.enrollment_status = 'active'
                        AND a.assessment_date BETWEEN ? AND ?
                      GROUP BY c.id, c.name, s.name
                      ORDER BY average_score DESC
                      LIMIT 10";
            $stmt = $this->db->query($query, [$this->period['date_from'], $this->period['date_to']]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $labels = [];
            $data = [];
            foreach ($rows as $row) {
                $labels[] = $row['class_name'];
                $data[] = round((float) ($row['average_score'] ?? 0), 1);
            }

            return ['labels' => $labels, 'data' => $data];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getClassPerformanceChart error: " . $e->getMessage());
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
            // Reuse the unified calendar feed so every dashboard shows the SAME
            // deduplicated events (one row per logical event spanning its full
            // start -> end range) instead of one row per calendar day.
            $calendarSync = new CalendarSyncService($this->db->getConnection());
            $all = $calendarSync->getUnifiedEvents();
            $dateFrom = $this->period['date_from'];
            $dateTo = $this->period['date_to'];
            $upcoming = array_values(array_filter($all, function ($ev) use ($dateFrom, $dateTo) {
                return ($ev['start_date'] ?? '') <= $dateTo
                    && ($ev['end_date'] ?? $ev['start_date'] ?? '') >= $dateFrom
                    && ($ev['status'] ?? '') !== 'cancelled';
            }));

            usort($upcoming, function ($a, $b) {
                return strcmp($a['start_date'], $b['start_date']);
            });

            $data = array_slice($upcoming, 0, $limit);
            return [
                'data' => $data,
                'total' => count($data)
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("getUpcomingEvents error: " . $e->getMessage());
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * Get full dashboard data in a single call
     * @return array Complete dashboard data structure
     */
    public function getFullDashboardData(array $filters = []): array
    {
        if ($filters) {
            $this->period = DashboardPeriodService::resolve($this->db->getConnection(), $filters);
        }
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
            'timestamp' => date('Y-m-d H:i:s'),
            'meta' => ['filters' => $this->period]
        ];
    }
}
