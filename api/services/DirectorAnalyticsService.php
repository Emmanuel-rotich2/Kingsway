<?php
namespace App\API\Services;

use App\Database\Database;

class DirectorAnalyticsService
{


    /**
     * Get student enrollment statistics
     */
    public function getEnrollmentStats()
    {
        $query = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male,
                         SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female
                  FROM students WHERE status = 'active'";
        $stmt = $this->db->query($query);
        $row = $stmt->fetch();
        return [
            'total' => $row['total'] ?? 0,
            'male' => $row['male'] ?? 0,
            'female' => $row['female'] ?? 0
        ];
    }

    /**
     * Get staff statistics
     */
    public function getStaffStats()
    {
        $query = "SELECT COUNT(*) as total, 
                         SUM(CASE WHEN staff_type = 'teaching' THEN 1 ELSE 0 END) as teaching,
                         SUM(CASE WHEN staff_type = 'non-teaching' THEN 1 ELSE 0 END) as non_teaching
                  FROM staff WHERE status = 'active'";
        $stmt = $this->db->query($query);
        $row = $stmt->fetch();
        return [
            'total' => $row['total'] ?? 0,
            'teaching' => $row['teaching'] ?? 0,
            'non_teaching' => $row['non_teaching'] ?? 0
        ];
    }

    /**
     * Get financial overview statistics
     */
    public function getFinanceStats()
    {
        $result = [];
        // Total fees collected
        $query = "SELECT SUM(amount_paid) as collected FROM payment_transactions WHERE status = 'confirmed'";
        $stmt = $this->db->query($query);
        $result['collected'] = $stmt->fetch()['collected'] ?? 0;

        // Total outstanding fees
        $query = "SELECT SUM(term_allocation - amount_paid) as outstanding FROM payment_transactions WHERE status = 'confirmed' AND term_allocation > amount_paid";
        $stmt = $this->db->query($query);
        $result['outstanding'] = $stmt->fetch()['outstanding'] ?? 0;

        // Fee collection rate
        $total = $result['collected'] + $result['outstanding'];
        $result['collection_rate'] = $total > 0 ? round(($result['collected'] / $total) * 100, 1) : 0;

        return $result;
    }

    /**
     * Get today's attendance summary
     */
    public function getAttendanceStats()
    {
        $result = [];
        // Student attendance today
        $query = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present FROM student_attendance WHERE date = CURDATE()";
        $stmt = $this->db->query($query);
        $row = $stmt->fetch();
        $result['total'] = $row['total'] ?? 0;
        $result['present'] = $row['present'] ?? 0;
        $result['attendance_rate'] = ($result['total'] > 0) ? round(($result['present'] / $result['total']) * 100, 1) : 0;
        return $result;
    }

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Get latest published announcements for dashboard
     * Returns: announcements array and expiring_notices array
     */
    public function getLatestAnnouncements()
    {
        $result = [
            'announcements' => [],
            'expiring_notices' => []
        ];

        // Get latest announcements
        $query = "
            SELECT 
                id, 
                title, 
                content, 
                announcement_type, 
                priority, 
                published_at, 
                expires_at,
                view_count
            FROM announcements_bulletin 
            WHERE status = 'published' 
              AND (expires_at IS NULL OR expires_at > NOW()) 
            ORDER BY 
                CASE priority 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'normal' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                published_at DESC 
            LIMIT 10
        ";
        $stmt = $this->db->query($query);
        $result['announcements'] = $stmt->fetchAll();

        // Get notices expiring within 7 days
        $query = "
            SELECT 
                id, 
                title, 
                announcement_type, 
                priority,
                expires_at,
                DATEDIFF(expires_at, NOW()) as days_remaining
            FROM announcements_bulletin 
            WHERE status = 'published' 
              AND expires_at IS NOT NULL 
              AND expires_at > NOW()
              AND expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            ORDER BY expires_at ASC
            LIMIT 5
        ";
        $stmt = $this->db->query($query);
        $result['expiring_notices'] = $stmt->fetchAll();

        return $result;
    }

    /**
     * Get monthly payroll summary (total payroll for current month)
     */
    public function getMonthlyPayrollSummary()
    {
        $query = "SELECT SUM(amount) as total_payroll FROM payroll WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
        $stmt = $this->db->query($query);
        $result = $stmt->fetch();
        return $result['total_payroll'] ?? 0;
    }

    /**
     * Get system health status (simple DB check)
     */
    public function getSystemHealthStatus()
    {
        try {
            $this->db->query("SELECT 1");
            return 'Healthy';
        } catch (\Exception $e) {
            return 'Unhealthy';
        }
    }

    /**
     * Get comprehensive CEO summary KPIs
     */
    public function getSummaryKPIs()
    {
        $result = [];

        // Academic Year and Term (using correct columns)
        $yearStmt = $this->db->query("SELECT year_name FROM academic_years WHERE status = 'active' OR is_current = 1 ORDER BY id DESC LIMIT 1");
        $result['academic_year'] = $yearStmt->fetch()['year_name'] ?? date('Y');

        $termStmt = $this->db->query("SELECT name FROM academic_terms WHERE status = 'current' ORDER BY id DESC LIMIT 1");
        $result['current_term'] = $termStmt->fetch()['name'] ?? 'Term 1';

        // Total Students
        $query = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
        $stmt = $this->db->query($query);
        $result['total_students'] = $stmt->fetch()['total'] ?? 0;

        // Student Growth (YoY) - simplified calculation
        $query = "SELECT COUNT(*) as current_year FROM students WHERE YEAR(created_at) = YEAR(CURDATE()) AND status = 'active'";
        $stmt = $this->db->query($query);
        $current_year = $stmt->fetch()['current_year'] ?? 0;

        $query = "SELECT COUNT(*) as last_year FROM students WHERE YEAR(created_at) = YEAR(CURDATE()) - 1 AND status = 'active'";
        $stmt = $this->db->query($query);
        $last_year = $stmt->fetch()['last_year'] ?? 1; // avoid division by zero

        $result['student_growth'] = $last_year > 0 ? round((($current_year - $last_year) / $last_year) * 100, 1) : 0;

        // Total Staff
        $query = "SELECT COUNT(*) as total FROM users WHERE role_id IN (2,3,4,5,6,7,8,9,10,14,16,18,21,24,32,33,34,63)";
        $stmt = $this->db->query($query);
        $result['total_staff'] = $stmt->fetch()['total'] ?? 0;

        // Teacher-Student Ratio
        $teacher_stmt = $this->db->query("SELECT COUNT(*) as count FROM users WHERE role_id IN (7,8,9)");
        $teacher_count = $teacher_stmt->fetch()['count'] ?? 1;
        $result['teacher_student_ratio'] = $teacher_count > 0 ? round($result['total_students'] / $teacher_count, 1) : 0;

        // Fees Collected YTD - Get current academic year date range or fall back to last 12 months
        $academicYearQuery = "
            SELECT start_date, end_date 
            FROM academic_years 
            WHERE status IN ('active', 'registration', 'current') OR is_current = 1 
            ORDER BY start_date DESC LIMIT 1
        ";
        $ayStmt = $this->db->query($academicYearQuery);
        $academicYear = $ayStmt->fetch();

        // Use academic year dates if available, otherwise last 12 months
        if ($academicYear && $academicYear['start_date']) {
            $startDate = $academicYear['start_date'];
            // If academic year hasn't started yet, look at previous year's data
            if (strtotime($startDate) > time()) {
                $query = "SELECT SUM(amount_paid) as total FROM payment_transactions WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND status = 'confirmed'";
            } else {
                $query = "SELECT SUM(amount_paid) as total FROM payment_transactions WHERE payment_date >= ? AND status = 'confirmed'";
            }
        } else {
            $query = "SELECT SUM(amount_paid) as total FROM payment_transactions WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND status = 'confirmed'";
        }

        // Execute with or without parameter
        if (isset($startDate) && strtotime($startDate) <= time()) {
            $stmt = $this->db->query($query, [$startDate]);
        } else {
            $stmt = $this->db->query($query);
        }
        $result['fees_collected_ytd'] = $stmt->fetch()['total'] ?? 0;

        // Fees Outstanding - Calculate from expected fees minus collected
        // First try student_fee_obligations, fall back to fee_structures estimate
        $outstandingQuery = "
            SELECT COALESCE(SUM(sfo.balance), 0) as outstanding
            FROM student_fee_obligations sfo
            WHERE sfo.status IN ('pending', 'partial', 'arrears')
        ";
        $stmt = $this->db->query($outstandingQuery);
        $outstanding = $stmt->fetch()['outstanding'] ?? 0;

        // If no obligations data, estimate from active students × average fee structure
        if ($outstanding == 0 && $result['total_students'] > 0) {
            // Try fee_structures_detailed first, fall back to fee_structures
            $feeQuery = "SELECT AVG(amount) as avg_fee FROM fee_structures_detailed WHERE status = 'active'";
            try {
                $stmt = $this->db->query($feeQuery);
                $avgFee = $stmt->fetch()['avg_fee'] ?? 0;

                // If no active detailed structures, use base fee_structures table
                if (!$avgFee || $avgFee == 0) {
                    $feeQuery = "SELECT AVG(amount) as avg_fee FROM fee_structures";
                    $stmt = $this->db->query($feeQuery);
                    $avgFee = $stmt->fetch()['avg_fee'] ?? 0;
                }

                // Estimate: (students × average annual fee) - collected = outstanding
                if ($avgFee > 0) {
                    $estimatedTotal = $result['total_students'] * $avgFee;
                    $outstanding = max(0, $estimatedTotal - $result['fees_collected_ytd']);
                }
            } catch (\Exception $e) {
                $outstanding = 0;
            }
        }
        $result['fees_outstanding'] = $outstanding;

        // Fee Collection Rate
        $total_fees = $result['fees_collected_ytd'] + $result['fees_outstanding'];
        $result['fee_collection_rate'] = $total_fees > 0 ? round(($result['fees_collected_ytd'] / $total_fees) * 100, 1) : 0;

        // Attendance Today
        $query = "SELECT AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END) as rate FROM student_attendance WHERE date = CURDATE()";
        $stmt = $this->db->query($query);
        $result['attendance_today'] = round($stmt->fetch()['rate'] ?? 0, 1);

        // Staff Attendance Today
        $query = "SELECT AVG(CASE WHEN status = 'present' THEN 100 ELSE 0 END) as rate FROM staff_attendance WHERE date = CURDATE()";
        $stmt = $this->db->query($query);
        $result['staff_attendance_today'] = round($stmt->fetch()['rate'] ?? 0, 1);

        // Pending Approvals
        $query = "SELECT COUNT(*) as total FROM workflow_instances WHERE status IN ('pending', 'in_progress')";
        $stmt = $this->db->query($query);
        $result['pending_approvals'] = $stmt->fetch()['total'] ?? 0;

        // Pending Admissions (simplified)
        $query = "SELECT COUNT(*) as total FROM admission_applications WHERE status = 'pending'";
        $stmt = $this->db->query($query);
        $result['pending_admissions'] = $stmt->fetch()['total'] ?? 0;

        // System Alerts/Risks (count critical issues from system_alerts table)
        $query = "SELECT COUNT(*) as total FROM system_alerts WHERE severity = 'critical' AND resolved = 0";
        try {
            $stmt = $this->db->query($query);
            $result['system_alerts'] = $stmt->fetch()['total'] ?? 0;
        } catch (\Exception $e) {
            $result['system_alerts'] = 0;
        }

        // Students by gender (for pie chart)
        try {
            $stmt = $this->db->query("SELECT gender, COUNT(*) as cnt FROM students WHERE status = 'active' GROUP BY gender");
            $genderRows = $stmt->fetchAll();
            $result['students_by_gender'] = array_map(function ($r) {
                return ['source' => ucfirst($r['gender'] ?? 'Unknown'), 'amount' => (int) $r['cnt']];
            }, $genderRows);
        } catch (\Exception $e) {
            $result['students_by_gender'] = [];
        }

        // Staff by role
        try {
            $stmt = $this->db->query("SELECT r.name as role, COUNT(*) as cnt FROM users u LEFT JOIN roles r ON u.role_id = r.id WHERE u.status = 'active' GROUP BY r.name ORDER BY cnt DESC LIMIT 10");
            $rows = $stmt->fetchAll();
            $result['staff_by_role'] = array_map(function ($r) {
                return ['source' => $r['role'] ?? 'Unknown', 'amount' => (int) $r['cnt']];
            }, $rows);
        } catch (\Exception $e) {
            $result['staff_by_role'] = [];
        }

        // Staff by department (use staff.department_id -> departments mapping)
        try {
            $deptQuery = "
                SELECT d.name as department,
                       COUNT(s.id) as cnt,
                       SUM(CASE WHEN LOWER(COALESCE(st.name, '')) = 'teaching' THEN 1 ELSE 0 END) as teachers,
                       SUM(CASE WHEN LOWER(COALESCE(st.name, '')) != 'teaching' THEN 1 ELSE 0 END) as support_staff
                FROM staff s
                LEFT JOIN staff_types st ON s.staff_type_id = st.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE s.status = 'active'
                GROUP BY d.name
                ORDER BY cnt DESC
                LIMIT 10
            ";
            $stmt = $this->db->query($deptQuery);
            $rows = $stmt->fetchAll();
            $result['staff_by_department'] = array_map(function ($r) {
                return [
                    'class_name' => $r['department'] ?? 'Unassigned',
                    'teachers' => (int) $r['teachers'],
                    'support_staff' => (int) $r['support_staff'],
                    'total' => (int) $r['cnt']
                ];
            }, $rows);
        } catch (\Exception $e) {
            $result['staff_by_department'] = [];
        }

        // Age distribution (students AND staff) - combined with separate counts
        try {
            // Student age distribution (school-age buckets)
            $studentAgeQuery = "SELECT
                CASE
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 10 THEN '0-9'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 10 AND 13 THEN '10-13'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 14 AND 17 THEN '14-17'
                    ELSE '18+'
                END as age_range,
                COUNT(*) as cnt
                FROM students
                WHERE date_of_birth IS NOT NULL AND status = 'active'
                GROUP BY age_range
                ORDER BY FIELD(age_range, '0-9', '10-13', '14-17', '18+')";
            $stmt = $this->db->query($studentAgeQuery);
            $studentRows = $stmt->fetchAll();

            // Staff age distribution (adult buckets)
            $staffAgeQuery = "SELECT
                CASE
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 25 THEN '18-24'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 25 AND 34 THEN '25-34'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 35 AND 44 THEN '35-44'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 45 AND 54 THEN '45-54'
                    ELSE '55+'
                END as age_range,
                COUNT(*) as cnt
                FROM staff
                WHERE date_of_birth IS NOT NULL AND status = 'active'
                GROUP BY age_range
                ORDER BY FIELD(age_range, '18-24', '25-34', '35-44', '45-54', '55+')";
            $stmt2 = $this->db->query($staffAgeQuery);
            $staffRows = $stmt2->fetchAll();

            // Format student age distribution
            $result['age_distribution'] = array_map(function ($r) {
                return ['age_range' => $r['age_range'], 'count' => (int) $r['cnt'], 'type' => 'student'];
            }, $studentRows);

            // Format staff age distribution
            $result['staff_age_distribution'] = array_map(function ($r) {
                return ['age_range' => $r['age_range'], 'count' => (int) $r['cnt'], 'type' => 'staff'];
            }, $staffRows);

            // Combined summary for the chart (both together)
            $result['combined_age_summary'] = [
                'students' => [
                    'total' => array_sum(array_column($studentRows, 'cnt')),
                    'distribution' => $result['age_distribution']
                ],
                'staff' => [
                    'total' => array_sum(array_column($staffRows, 'cnt')),
                    'distribution' => $result['staff_age_distribution']
                ]
            ];
        } catch (\Exception $e) {
            $result['age_distribution'] = [];
            $result['staff_age_distribution'] = [];
            $result['combined_age_summary'] = ['students' => ['total' => 0, 'distribution' => []], 'staff' => ['total' => 0, 'distribution' => []]];
        }

        return $result;
    }

    /**
     * Get financial trends data
     */
    public function getFinancialTrends()
    {
        $query = "
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount_paid) as collected,
                YEAR(payment_date) as year
            FROM payment_transactions 
            WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            AND status = 'confirmed'
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), YEAR(payment_date)
            ORDER BY month
        ";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    /**
     * Get revenue sources breakdown
     */
    public function getRevenueSources()
    {
        $query = "
            SELECT 
                'School Fees' as source,
                SUM(amount_paid) as amount
            FROM payment_transactions
            WHERE payment_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')
            AND status = 'confirmed'
        ";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    /**
     * Get academic KPIs
     */
    public function getAcademicKPIs()
    {
        $result = [];

        // Mean score (simplified)
        $query = "SELECT AVG(marks_obtained) as mean_score FROM assessment_results";
        $stmt = $this->db->query($query);
        $result['mean_score'] = round($stmt->fetch()['mean_score'] ?? 0, 1);

        // Pass rate
        $query = "SELECT (SUM(CASE WHEN marks_obtained >= 50 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as pass_rate FROM assessment_results";
        $stmt = $this->db->query($query);
        $result['pass_rate'] = round($stmt->fetch()['pass_rate'] ?? 0, 1);

        // Dropout rate (simplified)
        $query = "SELECT (SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as dropout_rate FROM students";
        $stmt = $this->db->query($query);
        $result['dropout_rate'] = round($stmt->fetch()['dropout_rate'] ?? 0, 1);

        // Transition rate (simplified)
        $result['transition_rate'] = 85.0; // Placeholder

        return $result;
    }

    /**
     * Get performance matrix data
     */
    public function getPerformanceMatrix()
    {
        $query = "
            SELECT 
                'Grade 1' as class_name,
                'Mathematics' as subject,
                AVG(marks_obtained) as avg_score
            FROM assessment_results
            GROUP BY class_name, subject
            LIMIT 10
        ";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    /**
     * Student distribution by class (male/female counts)
     */
    public function getStudentDistribution()
    {
        $query = "
            SELECT
                c.name as class_name,
                SUM(CASE WHEN s.gender = 'male' THEN 1 ELSE 0 END) as male,
                SUM(CASE WHEN s.gender = 'female' THEN 1 ELSE 0 END) as female,
                COUNT(*) as total
            FROM students s
            LEFT JOIN class_streams cs ON s.stream_id = cs.id
            LEFT JOIN classes c ON cs.class_id = c.id
            WHERE s.status = 'active'
            GROUP BY c.name
            ORDER BY c.name
        ";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    /**
     * Staff deployment by department
     */
    public function getStaffDeployment()
    {
        $query = "
            SELECT d.name as department,
                   COUNT(s.id) as total,
                   SUM(CASE WHEN LOWER(COALESCE(st.name, '')) = 'teaching' THEN 1 ELSE 0 END) as teachers,
                   SUM(CASE WHEN LOWER(COALESCE(st.name, '')) != 'teaching' THEN 1 ELSE 0 END) as support_staff
            FROM staff s
            LEFT JOIN staff_types st ON s.staff_type_id = st.id
            LEFT JOIN departments d ON s.department_id = d.id
            WHERE s.status = 'active'
            GROUP BY d.name
            ORDER BY total DESC
            LIMIT 20
        ";
        $stmt = $this->db->query($query);
        $rows = $stmt->fetchAll();
        // Normalize output to match frontend expectations
        return array_map(function ($r) {
            return [
                'department' => $r['department'] ?? 'Unassigned',
                'total' => (int) $r['total'],
                'teachers' => (int) $r['teachers'],
                'support_staff' => (int) $r['support_staff']
            ];
        }, $rows);
    }

    /**
     * Get attendance trends including:
     * - 30-day attendance trend data for charts
     * - Today's absent students
     * - Today's absent staff
     */
    public function getAttendanceTrends()
    {
        $result = [
            'data' => [],
            'absent_students' => [],
            'absent_staff' => [],
            'summary' => []
        ];

        // 1. 30-day attendance trend for charts
        $query = "
            SELECT 
                DATE_FORMAT(date, '%Y-%m-%d') as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
                ROUND((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as attendance_rate
            FROM student_attendance 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY date
            ORDER BY date
        ";
        $stmt = $this->db->query($query);
        $result['data'] = $stmt->fetchAll();

        // 2. Students absent today
        $query = "
            SELECT 
                s.id,
                s.admission_no,
                CONCAT(s.first_name, ' ', s.last_name) as name,
                CASE 
                    WHEN c.name = cs.stream_name THEN c.name
                    WHEN cs.stream_name IS NULL THEN COALESCE(c.name, 'Unknown')
                    ELSE CONCAT(c.name, ' - ', cs.stream_name)
                END as class,
                'Not provided' as reason
            FROM student_attendance sa
            JOIN students s ON sa.student_id = s.id
            LEFT JOIN class_streams cs ON s.stream_id = cs.id
            LEFT JOIN classes c ON cs.class_id = c.id
            WHERE sa.date = CURDATE()
              AND sa.status = 'absent'
            ORDER BY c.name, s.first_name
        ";
        $stmt = $this->db->query($query);
        $result['absent_students'] = $stmt->fetchAll();

        // 3. Staff absent today
        $query = "
            SELECT 
                st.id,
                st.staff_no,
                CONCAT(st.first_name, ' ', st.last_name) as name,
                d.name as department,
                'Not provided' as reason
            FROM staff_attendance sta
            JOIN staff st ON sta.staff_id = st.id
            LEFT JOIN departments d ON st.department_id = d.id
            WHERE sta.date = CURDATE()
              AND sta.status = 'absent'
            ORDER BY d.name, st.first_name
        ";
        $stmt = $this->db->query($query);
        $result['absent_staff'] = $stmt->fetchAll();

        // 4. Summary statistics
        $today = date('Y-m-d');

        // Today's student attendance summary
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM student_attendance
            WHERE date = CURDATE()
        ";
        $stmt = $this->db->query($query);
        $studentSummary = $stmt->fetch();

        // Today's staff attendance summary
        $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM staff_attendance
            WHERE date = CURDATE()
        ";
        $stmt = $this->db->query($query);
        $staffSummary = $stmt->fetch();

        $result['summary'] = [
            'students' => [
                'total_marked' => (int) ($studentSummary['total'] ?? 0),
                'present' => (int) ($studentSummary['present'] ?? 0),
                'absent' => (int) ($studentSummary['absent'] ?? 0),
                'late' => (int) ($studentSummary['late'] ?? 0)
            ],
            'staff' => [
                'total_marked' => (int) ($staffSummary['total'] ?? 0),
                'present' => (int) ($staffSummary['present'] ?? 0),
                'absent' => (int) ($staffSummary['absent'] ?? 0),
                'late' => (int) ($staffSummary['late'] ?? 0)
            ]
        ];

        return $result;
    }

    /**
     * Get fees aggregated by class and term (minimal implementation)
     */
    public function getFeesByClassTerm()
    {
        $query = "
            SELECT
                c.name as class_name,
                DATE_FORMAT(pt.payment_date, '%Y-%m') as term,
                SUM(pt.amount_paid) as collected,
                SUM(CASE WHEN pt.term_allocation > pt.amount_paid THEN (pt.term_allocation - pt.amount_paid) ELSE 0 END) as outstanding
            FROM payment_transactions pt
            LEFT JOIN students s ON pt.student_id = s.id
            LEFT JOIN class_streams cs ON s.stream_id = cs.id
            LEFT JOIN classes c ON cs.class_id = c.id
            WHERE pt.status = 'confirmed'
            GROUP BY c.name, DATE_FORMAT(pt.payment_date, '%Y-%m')
            ORDER BY c.name, term DESC
        ";
        $stmt = $this->db->query($query);
        return $stmt->fetchAll();
    }

    /**
     * Return academic KPIs as table rows
     */
    public function getAcademicKPIsTable()
    {
        $kpis = $this->getAcademicKPIs();
        $rows = [];
        foreach ($kpis as $key => $value) {
            $rows[] = [
                'kpi' => strtoupper(str_replace('_', ' ', $key)),
                'value' => $value,
                'target' => null,
                'status' => 'Good'
            ];
        }
        return $rows;
    }

    /**
     * Get operational risks
     */
    public function getOperationalRisks()
    {
        $result = [];

        // Pending approvals by type
        $query = "
            SELECT 
                wd.name as workflow_type,
                COUNT(*) as count
            FROM workflow_instances wi
            JOIN workflow_definitions wd ON wi.workflow_id = wd.id
            WHERE wi.status IN ('pending', 'in_progress')
            GROUP BY wd.name
        ";
        $stmt = $this->db->query($query);
        $result['pending_approvals'] = $stmt->fetchAll();

        // Audit logs (recent) - include entity and ip_address
        $query = "
            SELECT 
                al.action,
                al.entity,
                al.entity_id,
                al.user_id,
                al.ip_address,
                al.created_at,
                CONCAT(u.first_name, ' ', u.last_name) as user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 20
        ";
        $stmt = $this->db->query($query);
        $result['audit_logs'] = $stmt->fetchAll();

        // Add pending admissions and discipline summary using HeadteacherAnalyticsService where available
        try {
            $ht = new HeadteacherAnalyticsService();
            $pendingAdmissions = $ht->getPendingAdmissions();
            $result['admissions_queue'] = $pendingAdmissions['data'] ?? [];

            $disciplineCases = $ht->getDisciplineCases();
            $result['discipline_summary'] = $disciplineCases['data'] ?? [];
        } catch (\Exception $e) {
            // If HeadteacherAnalyticsService unavailable for any reason, fallback to empty arrays
            $result['admissions_queue'] = [];
            $result['discipline_summary'] = [];
        }

        return $result;
    }
}
?>