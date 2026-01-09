<?php
namespace App\API\Services;

use App\Database\Database;
use Exception;

/**
 * SchoolAdminAnalyticsService
 * 
 * TIER 3: School Administrative Officer Dashboard Analytics
 * 
 * Provides data for operational school management:
 * - Student enrollment and attendance
 * - Staff coordination and activities
 * - Communications and announcements
 * - Academic schedules and timetables
 * - Admission pipeline
 * 
 * Role: School Administrative Officer (Role ID: 4)
 * 
 * @package App\API\Services
 * @since 2025-01-07
 */
class SchoolAdminAnalyticsService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // =========================================================================
    // SUMMARY CARDS DATA
    // =========================================================================

    /**
     * Get active students statistics
     * Card 1: Active Students
     */
    public function getActiveStudentsStats(): array
    {
        try {
            // Total active students - use stream_id which references class_streams
            $query = "SELECT 
                        COUNT(*) as total_students,
                        COUNT(DISTINCT stream_id) as active_streams,
                        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
                        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female
                      FROM students 
                      WHERE status = 'active'";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Get total active classes from classes table
            $classQuery = "SELECT COUNT(*) as total FROM classes WHERE status = 'active'";
            $classStmt = $this->db->query($classQuery);
            $classResult = $classStmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'total_students' => (int) ($result['total_students'] ?? 0),
                'active_classes' => (int) ($classResult['total'] ?? $result['active_streams'] ?? 0),
                'male' => (int) ($result['male'] ?? 0),
                'female' => (int) ($result['female'] ?? 0)
            ];
        } catch (Exception $e) {
            error_log("getActiveStudentsStats error: " . $e->getMessage());
            return ['total_students' => 0, 'active_classes' => 0, 'male' => 0, 'female' => 0];
        }
    }

    /**
     * Get teaching staff statistics
     * Card 2: Teaching Staff
     */
    public function getTeachingStaffStats(): array
    {
        try {
            // Total teaching staff
            $query = "SELECT 
                        COUNT(*) as total_teaching,
                        st.name as staff_type
                      FROM staff s
                      LEFT JOIN staff_types st ON s.staff_type_id = st.id
                      WHERE s.status = 'active' 
                        AND (st.name LIKE '%teach%' OR s.position LIKE '%teacher%' OR s.position LIKE '%Head%')
                      GROUP BY st.name";
            $stmt = $this->db->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $totalTeaching = 0;
            foreach ($rows as $row) {
                $totalTeaching += (int) $row['total_teaching'];
            }

            // If no results, get general count
            if ($totalTeaching === 0) {
                $fallbackQuery = "SELECT COUNT(*) as total FROM staff WHERE status = 'active'";
                $fallbackStmt = $this->db->query($fallbackQuery);
                $fallbackResult = $fallbackStmt->fetch(\PDO::FETCH_ASSOC);
                $totalTeaching = (int) ($fallbackResult['total'] ?? 0);
            }

            // Get staff attendance today (present percentage) - use 'date' column
            $attendanceQuery = "SELECT 
                                  COUNT(*) as total,
                                  SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
                                FROM staff_attendance 
                                WHERE date = CURDATE()";
            $attendanceStmt = $this->db->query($attendanceQuery);
            $attendanceResult = $attendanceStmt->fetch(\PDO::FETCH_ASSOC);

            $total = (int) ($attendanceResult['total'] ?? 0);
            $present = (int) ($attendanceResult['present'] ?? 0);
            $presentPercent = $total > 0 ? round(($present / $total) * 100, 1) : 100;

            return [
                'teaching_staff' => $totalTeaching,
                'present_percent' => $presentPercent,
                'present_today' => $present,
                'total_recorded' => $total
            ];
        } catch (Exception $e) {
            return ['teaching_staff' => 0, 'present_percent' => 0, 'present_today' => 0, 'total_recorded' => 0];
        }
    }

    /**
     * Get staff activities and coordination data
     * Card 3: Staff Activities
     */
    public function getStaffActivitiesStats(): array
    {
        try {
            // Staff on approved leave
            $leaveQuery = "SELECT COUNT(*) as on_leave 
                           FROM staff_leaves 
                           WHERE status = 'approved' 
                             AND start_date <= CURDATE() 
                             AND end_date >= CURDATE()";
            $leaveStmt = $this->db->query($leaveQuery);
            $leaveResult = $leaveStmt->fetch(\PDO::FETCH_ASSOC);

            // Pending leave requests
            $pendingLeaveQuery = "SELECT COUNT(*) as pending 
                                  FROM staff_leaves 
                                  WHERE status = 'pending'";
            $pendingLeaveStmt = $this->db->query($pendingLeaveQuery);
            $pendingLeaveResult = $pendingLeaveStmt->fetch(\PDO::FETCH_ASSOC);

            // Pending class assignments (if table exists)
            $assignmentsCount = 0;
            try {
                $assignQuery = "SELECT COUNT(*) as pending 
                                FROM staff_class_assignments 
                                WHERE status = 'pending' OR status IS NULL";
                $assignStmt = $this->db->query($assignQuery);
                $assignResult = $assignStmt->fetch(\PDO::FETCH_ASSOC);
                $assignmentsCount = (int) ($assignResult['pending'] ?? 0);
            } catch (Exception $e) {
                // Table might not exist
            }

            return [
                'on_leave' => (int) ($leaveResult['on_leave'] ?? 0),
                'pending_leaves' => (int) ($pendingLeaveResult['pending'] ?? 0),
                'pending_assignments' => $assignmentsCount,
                'total_activities' => (int) ($leaveResult['on_leave'] ?? 0) + $assignmentsCount
            ];
        } catch (Exception $e) {
            return ['on_leave' => 0, 'pending_leaves' => 0, 'pending_assignments' => 0, 'total_activities' => 0];
        }
    }

    /**
     * Get class timetables statistics
     * Card 4: Class Timetables
     */
    public function getClassTimetablesStats(): array
    {
        try {
            // Active class schedules this week
            $query = "SELECT 
                        COUNT(DISTINCT class_id) as active_timetables,
                        COUNT(*) as total_periods
                      FROM class_schedules 
                      WHERE status = 'active'";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Classes per week
            $weeklyQuery = "SELECT COUNT(*) as classes_this_week 
                            FROM class_schedules 
                            WHERE status = 'active'
                              AND DAYOFWEEK(CURDATE()) BETWEEN 2 AND 6";
            $weeklyStmt = $this->db->query($weeklyQuery);
            $weeklyResult = $weeklyStmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'active_timetables' => (int) ($result['active_timetables'] ?? 0),
                'total_periods' => (int) ($result['total_periods'] ?? 0),
                'classes_per_week' => (int) ($weeklyResult['classes_this_week'] ?? 0) * 5
            ];
        } catch (Exception $e) {
            return ['active_timetables' => 0, 'total_periods' => 0, 'classes_per_week' => 0];
        }
    }

    /**
     * Get daily attendance statistics
     * Card 5: Daily Attendance
     */
    public function getDailyAttendanceStats(): array
    {
        try {
            $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
                      FROM student_attendance 
                      WHERE date = CURDATE()";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $total = (int) ($result['total'] ?? 0);
            $present = (int) ($result['present'] ?? 0);
            $absent = (int) ($result['absent'] ?? 0);
            $late = (int) ($result['late'] ?? 0);

            // If no attendance today, get total active students as baseline
            if ($total === 0) {
                $studentsQuery = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
                $studentsStmt = $this->db->query($studentsQuery);
                $studentsResult = $studentsStmt->fetch(\PDO::FETCH_ASSOC);
                $total = (int) ($studentsResult['total'] ?? 0);
            }

            $percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;

            return [
                'total' => $total,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'percentage' => $percentage,
                'not_recorded' => $total === 0
            ];
        } catch (Exception $e) {
            return ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0, 'percentage' => 0, 'not_recorded' => true];
        }
    }

    /**
     * Get announcements statistics
     * Card 6: Announcements
     */
    public function getAnnouncementsStats(): array
    {
        try {
            // Announcements this week
            $query = "SELECT 
                        COUNT(*) as this_week,
                        SUM(COALESCE(view_count, 0)) as total_views
                      FROM announcements_bulletin 
                      WHERE status = 'published' 
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            // Total active announcements
            $activeQuery = "SELECT COUNT(*) as active 
                            FROM announcements_bulletin 
                            WHERE status = 'published' 
                              AND (expires_at IS NULL OR expires_at > NOW())";
            $activeStmt = $this->db->query($activeQuery);
            $activeResult = $activeStmt->fetch(\PDO::FETCH_ASSOC);

            // Estimate recipients (all active users)
            $recipientsQuery = "SELECT COUNT(*) as total FROM users WHERE status = 'active'";
            $recipientsStmt = $this->db->query($recipientsQuery);
            $recipientsResult = $recipientsStmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'count' => (int) ($result['this_week'] ?? 0),
                'active' => (int) ($activeResult['active'] ?? 0),
                'total_views' => (int) ($result['total_views'] ?? 0),
                'recipients' => (int) ($recipientsResult['total'] ?? 0)
            ];
        } catch (Exception $e) {
            return ['count' => 0, 'active' => 0, 'total_views' => 0, 'recipients' => 0];
        }
    }

    /**
     * Get student admissions statistics
     * Card 7: Student Admissions
     */
    public function getStudentAdmissionsStats(): array
    {
        try {
            $query = "SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                        COUNT(*) as total
                      FROM admission_applications 
                      WHERE YEAR(created_at) = YEAR(CURDATE())";
            $stmt = $this->db->query($query);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'pending' => (int) ($result['pending'] ?? 0),
                'approved' => (int) ($result['approved'] ?? 0),
                'rejected' => (int) ($result['rejected'] ?? 0),
                'total' => (int) ($result['total'] ?? 0)
            ];
        } catch (Exception $e) {
            return ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        }
    }

    /**
     * Get staff leaves statistics
     * Card 8: Staff Leaves
     */
    public function getStaffLeavesStats(): array
    {
        try {
            // Today's leaves
            $todayQuery = "SELECT COUNT(*) as today 
                           FROM staff_leaves 
                           WHERE status = 'approved' 
                             AND start_date <= CURDATE() 
                             AND end_date >= CURDATE()";
            $todayStmt = $this->db->query($todayQuery);
            $todayResult = $todayStmt->fetch(\PDO::FETCH_ASSOC);

            // This week's leaves
            $weekQuery = "SELECT COUNT(*) as this_week 
                          FROM staff_leaves 
                          WHERE status = 'approved' 
                            AND start_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                            AND end_date >= CURDATE()";
            $weekStmt = $this->db->query($weekQuery);
            $weekResult = $weekStmt->fetch(\PDO::FETCH_ASSOC);

            // Pending leave requests
            $pendingQuery = "SELECT COUNT(*) as pending 
                             FROM staff_leaves 
                             WHERE status = 'pending'";
            $pendingStmt = $this->db->query($pendingQuery);
            $pendingResult = $pendingStmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'today' => (int) ($todayResult['today'] ?? 0),
                'this_week' => (int) ($weekResult['this_week'] ?? 0),
                'pending' => (int) ($pendingResult['pending'] ?? 0)
            ];
        } catch (Exception $e) {
            return ['today' => 0, 'this_week' => 0, 'pending' => 0];
        }
    }

    /**
     * Get class distribution statistics
     * Card 9: Class Distribution
     */
    public function getClassDistributionStats(): array
    {
        try {
            // Join students via stream_id → class_streams → classes
            $query = "SELECT 
                        c.id,
                        c.name as class_name,
                        COUNT(st.id) as student_count
                      FROM classes c
                      LEFT JOIN class_streams cs ON cs.class_id = c.id AND cs.status = 'active'
                      LEFT JOIN students st ON st.stream_id = cs.id AND st.status = 'active'
                      WHERE c.status = 'active'
                      GROUP BY c.id, c.name
                      ORDER BY c.name";
            $stmt = $this->db->query($query);
            $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $counts = array_map('intval', array_column($classes, 'student_count'));
            $total = count($counts);
            $sum = array_sum($counts);
            $average = $total > 0 ? round($sum / $total, 1) : 0;
            $max = $total > 0 ? max($counts) : 0;
            $min = $total > 0 ? min($counts) : 0;

            return [
                'total_classes' => $total,
                'average' => $average,
                'max' => $max,
                'min' => $min,
                'distribution' => $classes
            ];
        } catch (Exception $e) {
            error_log("getClassDistributionStats error: " . $e->getMessage());
            return ['total_classes' => 0, 'average' => 0, 'max' => 0, 'min' => 0, 'distribution' => []];
        }
    }

    /**
     * Get system status (limited view for School Admin)
     * Card 10: System Status
     */
    public function getSystemStatus(): array
    {
        try {
            // Check database connectivity
            $dbHealthy = true;
            try {
                $this->db->query("SELECT 1");
            } catch (Exception $e) {
                $dbHealthy = false;
            }

            // Get last error log (if any recent errors)
            $recentErrors = 0;
            try {
                $errorQuery = "SELECT COUNT(*) as errors 
                               FROM system_logs 
                               WHERE log_level IN ('error', 'critical') 
                                 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
                $errorStmt = $this->db->query($errorQuery);
                $errorResult = $errorStmt->fetch(\PDO::FETCH_ASSOC);
                $recentErrors = (int) ($errorResult['errors'] ?? 0);
            } catch (Exception $e) {
                // system_logs table might not exist
            }

            $status = $dbHealthy && $recentErrors < 10 ? 'Operational' : 'Degraded';
            $uptime = $dbHealthy ? '99.9' : '95.0';

            return [
                'status' => $status,
                'uptime' => $uptime,
                'db_healthy' => $dbHealthy,
                'recent_errors' => $recentErrors
            ];
        } catch (Exception $e) {
            return ['status' => 'Unknown', 'uptime' => '0', 'db_healthy' => false, 'recent_errors' => 0];
        }
    }

    // =========================================================================
    // CHARTS DATA
    // =========================================================================

    /**
     * Get weekly attendance trend data for chart
     * @param int $weeks Number of weeks to retrieve (default 4)
     */
    public function getWeeklyAttendanceTrend(int $weeks = 4): array
    {
        try {
            $labels = [];
            $data = [];

            for ($i = $weeks - 1; $i >= 0; $i--) {
                $weekStart = date('Y-m-d', strtotime("-{$i} weeks Monday"));
                $weekEnd = date('Y-m-d', strtotime("-{$i} weeks Friday"));

                $query = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present
                          FROM student_attendance 
                          WHERE date BETWEEN ? AND ?";
                $stmt = $this->db->query($query, [$weekStart, $weekEnd]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                $total = (int) ($result['total'] ?? 0);
                $present = (int) ($result['present'] ?? 0);
                $percentage = $total > 0 ? round(($present / $total) * 100, 1) : 0;

                $labels[] = "Week " . ($weeks - $i);
                $data[] = $percentage > 0 ? $percentage : null;
            }

            return [
                'labels' => $labels,
                'data' => $data,
                'weeks' => $weeks
            ];
        } catch (Exception $e) {
            error_log("getWeeklyAttendanceTrend error: " . $e->getMessage());
            return ['labels' => [], 'data' => [], 'weeks' => $weeks];
        }
    }

    /**
     * Get class distribution data for bar chart
     * @param string $filter Optional filter by form (form1, form2, etc.)
     */
    public function getClassDistributionChart(string $filter = 'all'): array
    {
        try {
            // Join students via stream_id → class_streams → classes
            $query = "SELECT 
                        c.name as class_name,
                        COUNT(st.id) as student_count
                      FROM classes c
                      LEFT JOIN class_streams cs ON cs.class_id = c.id AND cs.status = 'active'
                      LEFT JOIN students st ON st.stream_id = cs.id AND st.status = 'active'
                      WHERE c.status = 'active'";

            $params = [];
            if ($filter !== 'all') {
                $query .= " AND LOWER(c.name) LIKE ?";
                $params[] = "%{$filter}%";
            }

            $query .= " GROUP BY c.id, c.name ORDER BY c.name";

            $stmt = $this->db->query($query, $params);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $labels = array_column($results, 'class_name');
            $data = array_map('intval', array_column($results, 'student_count'));

            return [
                'labels' => $labels,
                'data' => $data,
                'filter' => $filter
            ];
        } catch (Exception $e) {
            error_log("getClassDistributionChart error: " . $e->getMessage());
            return ['labels' => [], 'data' => [], 'filter' => $filter];
        }
    }

    // =========================================================================
    // TABLES DATA
    // =========================================================================

    /**
     * Get pending items for the dashboard table
     */
    public function getPendingItems(): array
    {
        $items = [];

        try {
            // Pending admission applications
            $admissionQuery = "SELECT COUNT(*) as count FROM admission_applications WHERE status = 'pending'";
            $stmt = $this->db->query($admissionQuery);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (($result['count'] ?? 0) > 0) {
                $items[] = [
                    'type' => 'Admission',
                    'icon' => 'fas fa-user-plus',
                    'description' => 'New Admission Applications',
                    'count' => (int) $result['count'],
                    'priority' => 'high',
                    'action_url' => 'manage_admissions',
                    'action_label' => 'Review'
                ];
            }

            // Pending staff leave requests
            $leaveQuery = "SELECT COUNT(*) as count FROM staff_leaves WHERE status = 'pending'";
            $stmt = $this->db->query($leaveQuery);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (($result['count'] ?? 0) > 0) {
                $items[] = [
                    'type' => 'Leave Request',
                    'icon' => 'fas fa-calendar-times',
                    'description' => 'Staff Leave Requests Pending',
                    'count' => (int) $result['count'],
                    'priority' => 'medium',
                    'action_url' => 'manage_staff',
                    'action_label' => 'Review'
                ];
            }

            // Unmarked attendance today
            $activeStudents = $this->getActiveStudentsStats()['total_students'];
            $attendanceQuery = "SELECT COUNT(DISTINCT student_id) as marked FROM student_attendance WHERE date = CURDATE()";
            $stmt = $this->db->query($attendanceQuery);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $marked = (int) ($result['marked'] ?? 0);
            $unmarked = $activeStudents - $marked;
            if ($unmarked > 0) {
                $items[] = [
                    'type' => 'Attendance',
                    'icon' => 'fas fa-clipboard-check',
                    'description' => 'Students Attendance Not Marked',
                    'count' => $unmarked,
                    'priority' => $unmarked > ($activeStudents / 2) ? 'high' : 'medium',
                    'action_url' => 'mark_attendance',
                    'action_label' => 'Mark Now'
                ];
            }

            // Expiring announcements
            $expiringQuery = "SELECT COUNT(*) as count 
                              FROM announcements_bulletin 
                              WHERE status = 'published' 
                                AND expires_at IS NOT NULL 
                                AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY)";
            $stmt = $this->db->query($expiringQuery);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (($result['count'] ?? 0) > 0) {
                $items[] = [
                    'type' => 'Announcement',
                    'icon' => 'fas fa-bullhorn',
                    'description' => 'Announcements Expiring Soon',
                    'count' => (int) $result['count'],
                    'priority' => 'low',
                    'action_url' => 'manage_announcements',
                    'action_label' => 'View'
                ];
            }

        } catch (Exception $e) {
            // Return empty on error
        }

        return $items;
    }

    /**
     * Get today's schedule for the dashboard table
     */
    public function getTodaySchedule(): array
    {
        $schedule = [];

        try {
            // Get today's day of week (1=Monday, 7=Sunday)
            $dayOfWeek = date('N');
            $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
            $today = $dayNames[$dayOfWeek] ?? 'Monday';

            // Class schedules for today (without subjects table)
            $query = "SELECT 
                        cs.start_time,
                        cs.end_time,
                        c.name as class_name,
                        cs.subject_id,
                        CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
                        'Class' as event_type
                      FROM class_schedules cs
                      JOIN classes c ON cs.class_id = c.id
                      LEFT JOIN staff s ON cs.teacher_id = s.id
                      WHERE cs.day_of_week = ?
                        AND cs.status = 'active'
                      ORDER BY cs.start_time
                      LIMIT 15";
            $stmt = $this->db->query($query, [$today]);
            $classes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($classes as $class) {
                $schedule[] = [
                    'time' => $class['start_time'] . ' - ' . $class['end_time'],
                    'event' => 'Class - ' . $class['class_name'],
                    'location' => $class['class_name'],
                    'attendees' => $class['teacher_name'] ?? 'TBA',
                    'status' => 'scheduled',
                    'type' => 'class'
                ];
            }

        } catch (Exception $e) {
            error_log("getTodaySchedule error: " . $e->getMessage());
            // Return empty on error
        }

        // Add default message if no schedule
        if (empty($schedule)) {
            $schedule[] = [
                'time' => '--',
                'event' => 'No classes scheduled for today',
                'location' => '--',
                'attendees' => '--',
                'status' => 'none',
                'type' => 'info'
            ];
        }

        return $schedule;
    }

    /**
     * Get staff directory for the dashboard table
     * @param string $search Optional search term
     */
    public function getStaffDirectory(string $search = ''): array
    {
        try {
            $query = "SELECT 
                        s.id,
                        CONCAT(s.first_name, ' ', s.last_name) as name,
                        s.position,
                        COALESCE(d.name, 'General') as department,
                        u.email as contact,
                        CASE 
                            WHEN EXISTS (
                                SELECT 1 FROM staff_leaves sl 
                                WHERE sl.staff_id = s.id 
                                  AND sl.status = 'approved' 
                                  AND sl.start_date <= CURDATE() 
                                  AND sl.end_date >= CURDATE()
                            ) THEN 'On Leave'
                            WHEN EXISTS (
                                SELECT 1 FROM staff_attendance sa 
                                WHERE sa.staff_id = s.id 
                                  AND sa.date = CURDATE() 
                                  AND sa.status = 'present'
                            ) THEN 'Present'
                            ELSE 'Unknown'
                        END as status
                      FROM staff s
                      LEFT JOIN departments d ON s.department_id = d.id
                      LEFT JOIN users u ON s.user_id = u.id
                      WHERE s.status = 'active'";

            $params = [];
            if (!empty($search)) {
                $query .= " AND (s.first_name LIKE ? 
                             OR s.last_name LIKE ? 
                             OR s.position LIKE ? 
                             OR d.name LIKE ?)";
                $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
            }

            $query .= " ORDER BY s.first_name, s.last_name LIMIT 50";

            $stmt = $this->db->query($query, $params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("getStaffDirectory error: " . $e->getMessage());
            return [];
        }
    }

    // =========================================================================
    // COMBINED DASHBOARD DATA
    // =========================================================================

    /**
     * Get all dashboard data in a single call
     * Optimized for initial dashboard load
     */
    public function getFullDashboardData(): array
    {
        return [
            'cards' => [
                'active_students' => $this->getActiveStudentsStats(),
                'teaching_staff' => $this->getTeachingStaffStats(),
                'staff_activities' => $this->getStaffActivitiesStats(),
                'class_timetables' => $this->getClassTimetablesStats(),
                'daily_attendance' => $this->getDailyAttendanceStats(),
                'announcements' => $this->getAnnouncementsStats(),
                'student_admissions' => $this->getStudentAdmissionsStats(),
                'staff_leaves' => $this->getStaffLeavesStats(),
                'class_distribution' => $this->getClassDistributionStats(),
                'system_status' => $this->getSystemStatus()
            ],
            'charts' => [
                'attendance_trend' => $this->getWeeklyAttendanceTrend(4),
                'class_distribution' => $this->getClassDistributionChart('all')
            ],
            'tables' => [
                'pending_items' => $this->getPendingItems(),
                'today_schedule' => $this->getTodaySchedule(),
                'staff_directory' => $this->getStaffDirectory('')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
