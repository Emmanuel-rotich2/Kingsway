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

        // Pending Admissions (active pipeline states from SQL workflow)
        $query = "SELECT COUNT(*) as total
                  FROM admission_applications
                  WHERE status IN ('submitted', 'documents_pending', 'documents_verified', 'placement_offered', 'fees_pending')";
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
                SUM(CASE WHEN term_allocation > amount_paid THEN (term_allocation - amount_paid) ELSE 0 END) as outstanding,
                YEAR(payment_date) as year
            FROM payment_transactions
            WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              AND status = 'confirmed'
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), YEAR(payment_date)
            ORDER BY month
        ";
        try {
            $stmt = $this->db->query($query);
            $rows = $stmt->fetchAll();
            return array_map(function ($r) {
                return [
                    'month' => $r['month'],
                    'collected' => (float) ($r['collected'] ?? 0),
                    'outstanding' => (float) ($r['outstanding'] ?? 0),
                ];
            }, $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get revenue sources breakdown
     * Returns: [{ source, amount, percentage }]
     */
    public function getRevenueSources()
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    COALESCE(pt.payment_method, 'Other') as source,
                    SUM(pt.amount_paid) as amount
                FROM payment_transactions pt
                WHERE pt.payment_date >= DATE_FORMAT(CURDATE(), '%Y-01-01')
                  AND pt.status = 'confirmed'
                GROUP BY pt.payment_method
                ORDER BY amount DESC
            ");
            $rows  = $stmt->fetchAll();
            $total = array_sum(array_column($rows, 'amount'));
            return array_map(function ($r) use ($total) {
                return [
                    'source'     => $r['source'] ?? 'Other',
                    'amount'     => (float) ($r['amount'] ?? 0),
                    'percentage' => $total > 0 ? round($r['amount'] / $total * 100, 1) : 0,
                ];
            }, $rows);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get academic KPIs
     * Returns: active_classes, total_students, overall_avg_percent, pass_rate_percent,
     *          mean_score (alias), pass_rate (alias), dropout_rate, transition_rate
     */
    public function getAcademicKPIs()
    {
        $result = [
            'active_classes'      => 0,
            'total_students'      => 0,
            'overall_avg_percent' => 0.0,
            'mean_score'          => 0.0,
            'pass_rate_percent'   => 0.0,
            'pass_rate'           => 0.0,
            'dropout_rate'        => 0.0,
            'transition_rate'     => 85.0,
        ];

        try {
            $row = $this->db->query("SELECT COUNT(*) as cnt FROM classes WHERE status = 'active'")->fetch();
            $result['active_classes'] = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {}

        try {
            $row = $this->db->query("SELECT COUNT(*) as cnt FROM students WHERE status = 'active'")->fetch();
            $result['total_students'] = (int) ($row['cnt'] ?? 0);
        } catch (\Exception $e) {}

        // Merge avg and pass-rate into one scan of assessment_results JOIN assessments
        try {
            $row = $this->db->query("
                SELECT
                    AVG(
                        CASE WHEN a.max_marks > 0
                             THEN (ar.marks_obtained / a.max_marks) * 100
                             ELSE ar.marks_obtained END
                    ) as avg_pct,
                    SUM(CASE WHEN a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 50 THEN 1 ELSE 0 END) as passed,
                    COUNT(*) as total
                FROM assessment_results ar
                JOIN assessments a ON ar.assessment_id = a.id
            ")->fetch();

            $avg = round((float) ($row['avg_pct'] ?? 0), 1);
            $result['overall_avg_percent'] = $avg;
            $result['mean_score']          = $avg;

            $total  = (int) ($row['total'] ?? 0);
            $passed = (int) ($row['passed'] ?? 0);
            $rate   = $total > 0 ? round($passed / $total * 100, 1) : 0.0;
            $result['pass_rate_percent'] = $rate;
            $result['pass_rate']         = $rate;
        } catch (\Exception $e) {}

        try {
            $row = $this->db->query(
                "SELECT (SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100 as dropout_rate FROM students"
            )->fetch();
            $result['dropout_rate'] = round((float) ($row['dropout_rate'] ?? 0), 1);
        } catch (\Exception $e) {}

        return $result;
    }

    /**
     * Get performance matrix data — per-class avg score and student count
     */
    public function getPerformanceMatrix()
    {
        $query = "
            SELECT
                c.name as class_name,
                COUNT(DISTINCT ar.student_id) as student_count,
                ROUND(AVG(
                    CASE WHEN a.max_marks > 0
                         THEN (ar.marks_obtained / a.max_marks) * 100
                         ELSE ar.marks_obtained
                    END
                ), 1) as avg_score,
                ROUND(
                    SUM(CASE WHEN a.max_marks > 0 AND (ar.marks_obtained / a.max_marks) * 100 >= 50 THEN 1 ELSE 0 END)
                    / NULLIF(COUNT(*), 0) * 100
                , 1) as pass_rate
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            JOIN classes c ON a.class_id = c.id
            WHERE c.status = 'active'
            GROUP BY c.id, c.name
            ORDER BY c.name
        ";
        try {
            $stmt = $this->db->query($query);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Exception $e) {
            // fall through to class-only fallback
        }

        // Fallback: return classes with zero scores if no assessment data exists
        $fallbackQuery = "
            SELECT
                c.name as class_name,
                (SELECT COUNT(*) FROM students s
                 JOIN class_streams cs ON s.stream_id = cs.id
                 WHERE cs.class_id = c.id AND s.status = 'active') as student_count,
                0.0 as avg_score,
                0.0 as pass_rate
            FROM classes c
            WHERE c.status = 'active'
            ORDER BY c.name
        ";
        try {
            $stmt = $this->db->query($fallbackQuery);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            return [];
        }
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
     * Return academic KPIs as table rows for the DataTable
     * Returns: [{ kpi, value, target, status }]
     */
    public function getAcademicKPIsTable()
    {
        $kpis = $this->getAcademicKPIs();

        // Define display config for each KPI: label, target, thresholds
        $config = [
            'active_classes'      => ['label' => 'Active Classes',        'target' => null,  'good' => 1,  'suffix' => ''],
            'total_students'      => ['label' => 'Total Students',         'target' => null,  'good' => 1,  'suffix' => ''],
            'overall_avg_percent' => ['label' => 'Overall Avg Score (%)',  'target' => 70,    'good' => 70, 'suffix' => '%'],
            'pass_rate_percent'   => ['label' => 'Pass Rate (%)',          'target' => 75,    'good' => 75, 'suffix' => '%'],
            'dropout_rate'        => ['label' => 'Dropout Rate (%)',       'target' => 2,     'good' => -1, 'suffix' => '%'], // lower is better
            'transition_rate'     => ['label' => 'Transition Rate (%)',    'target' => 85,    'good' => 85, 'suffix' => '%'],
        ];

        $rows = [];
        foreach ($config as $key => $cfg) {
            if (!array_key_exists($key, $kpis)) continue;
            $value = $kpis[$key];
            $target = $cfg['target'];

            // Determine status
            if ($target === null) {
                $status = 'N/A';
            } elseif ($cfg['good'] === -1) {
                // Lower is better (dropout rate)
                $status = ($value <= $target) ? 'Good' : 'Needs Attention';
            } else {
                $status = ($value >= $cfg['good']) ? 'Good' : (($value >= $cfg['good'] * 0.8) ? 'Fair' : 'Needs Attention');
            }

            $rows[] = [
                'kpi'    => $cfg['label'],
                'value'  => $value . $cfg['suffix'],
                'target' => $target !== null ? $target . $cfg['suffix'] : '—',
                'status' => $status,
            ];
        }

        return $rows;
    }

    /**
     * Get operational risks
     * Returns: { pending_approvals, admissions_queue, discipline_cases, audit_logs }
     */
    public function getOperationalRisks()
    {
        $result = [
            'pending_approvals' => [],
            'admissions_queue'  => [],
            'discipline_cases'  => [],
            'audit_logs'        => [],
        ];

        // Pending approvals by type
        try {
            $stmt = $this->db->query("
                SELECT
                    wd.name as workflow_type,
                    COUNT(*) as count
                FROM workflow_instances wi
                JOIN workflow_definitions wd ON wi.workflow_id = wd.id
                WHERE wi.status IN ('pending', 'in_progress')
                GROUP BY wd.name
            ");
            $result['pending_approvals'] = $stmt->fetchAll();
        } catch (\Exception $e) {}

        // Audit logs (recent)
        try {
            $stmt = $this->db->query("
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
            ");
            $result['audit_logs'] = $stmt->fetchAll();
        } catch (\Exception $e) {}

        // Pending admissions and discipline cases
        try {
            $ht = new HeadteacherAnalyticsService();

            $pendingAdmissions = $ht->getPendingAdmissions();
            $result['admissions_queue'] = $pendingAdmissions['data'] ?? [];

            $disciplineCases = $ht->getDisciplineCases();
            $result['discipline_cases'] = $disciplineCases['data'] ?? [];
        } catch (\Exception $e) {
            $result['admissions_queue'] = [];
            $result['discipline_cases'] = [];
        }

        return $result;
    }
}
?>
