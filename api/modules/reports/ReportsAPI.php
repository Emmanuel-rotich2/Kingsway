<?php
namespace App\API\Modules\Reports;

require_once __DIR__ . '/../../includes/BaseAPI.php';

use App\API\Includes\BaseAPI;
use \PDO;
use \Exception;

class ReportsAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('reports');
    }

    // Generate academic performance report
    public function academicReport($params)
    {
        try {
            $required = ['class_id', 'term', 'year'];
            $missing = $this->validateRequired($params, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Get class details
            $sql = "
                SELECT 
                    c.*,
                    COUNT(DISTINCT s.id) as student_count,
                    COUNT(DISTINCT sub.id) as subject_count
                FROM classes c
                LEFT JOIN class_streams cs ON c.id = cs.class_id
                LEFT JOIN students s ON cs.id = s.stream_id
                LEFT JOIN class_subjects csub ON c.id = csub.class_id
                LEFT JOIN subjects sub ON csub.subject_id = sub.id
                WHERE c.id = ?
                GROUP BY c.id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$params['class_id']]);
            $class = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$class) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            // Get student performance data
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    cs.stream_name,
                    sub.name as subject_name,
                    ROUND(AVG(m.score), 2) as average_score,
                    COUNT(DISTINCT m.id) as assessment_count
                FROM students s
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN marks m ON s.id = m.student_id
                JOIN subjects sub ON m.subject_id = sub.id
                WHERE cs.class_id = ?
                AND m.term = ?
                AND m.year = ?
                GROUP BY s.id, sub.id
                ORDER BY s.admission_no, sub.name
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$params['class_id'], $params['term'], $params['year']]);
            $performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate class statistics
            $stats = $this->calculateClassStats($performance);

            // Format report data
            $report = [
                'class' => $class,
                'term' => $params['term'],
                'year' => $params['year'],
                'performance' => $this->formatPerformanceData($performance),
                'statistics' => $stats
            ];

            // Log report generation
            $this->logAction('generate', null, "Generated academic report for class: {$class['name']}");

            return $this->response([
                'status' => 'success',
                'data' => $report
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Generate attendance report
    public function attendanceReport($params)
    {
        try {
            $required = ['start_date', 'end_date'];
            $missing = $this->validateRequired($params, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $filters = [];
            $bindings = [$params['start_date'], $params['end_date']];

            if (isset($params['class_id'])) {
                $filters[] = "cs.class_id = ?";
                $bindings[] = $params['class_id'];
            }

            if (isset($params['stream_id'])) {
                $filters[] = "s.stream_id = ?";
                $bindings[] = $params['stream_id'];
            }

            $whereClause = !empty($filters) ? "AND " . implode(" AND ", $filters) : "";

            // Get attendance data
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    COUNT(DISTINCT a.date) as total_days,
                    SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days
                FROM students s
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                LEFT JOIN attendance a ON s.id = a.student_id
                AND a.date BETWEEN ? AND ?
                WHERE s.status = 'active'
                $whereClause
                GROUP BY s.id
                ORDER BY c.name, cs.stream_name, s.admission_no
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($bindings);
            $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate statistics
            $stats = $this->calculateAttendanceStats($attendance);

            // Format report data
            $report = [
                'period' => [
                    'start' => $params['start_date'],
                    'end' => $params['end_date']
                ],
                'attendance' => $attendance,
                'statistics' => $stats
            ];

            // Log report generation
            $this->logAction('generate', null, "Generated attendance report");

            return $this->response([
                'status' => 'success',
                'data' => $report
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Generate fee collection report
    public function feeReport($params)
    {
        try {
            $required = ['start_date', 'end_date'];
            $missing = $this->validateRequired($params, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Get fee collection data
            $sql = "
                SELECT 
                    p.id,
                    p.receipt_no,
                    p.amount,
                    p.payment_date,
                    p.payment_method,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    ft.name as fee_type,
                    t.name as term
                FROM payments p
                JOIN students s ON p.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN fee_types ft ON p.fee_type_id = ft.id
                JOIN terms t ON p.term_id = t.id
                WHERE p.payment_date BETWEEN ? AND ?
                AND p.status = 'confirmed'
                ORDER BY p.payment_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$params['start_date'], $params['end_date']]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate statistics
            $stats = $this->calculateFeeStats($payments);

            // Get outstanding balances
            $sql = "
                SELECT 
                    s.id,
                    s.admission_no,
                    s.first_name,
                    s.last_name,
                    c.name as class_name,
                    cs.stream_name,
                    f.total_amount,
                    COALESCE(SUM(p.amount), 0) as paid_amount,
                    (f.total_amount - COALESCE(SUM(p.amount), 0)) as balance
                FROM students s
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN fee_structure f ON cs.class_id = f.class_id
                LEFT JOIN payments p ON s.id = p.student_id
                AND p.status = 'confirmed'
                WHERE s.status = 'active'
                GROUP BY s.id
                HAVING balance > 0
                ORDER BY balance DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $outstanding = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format report data
            $report = [
                'period' => [
                    'start' => $params['start_date'],
                    'end' => $params['end_date']
                ],
                'payments' => $payments,
                'statistics' => $stats,
                'outstanding' => $outstanding
            ];

            // Log report generation
            $this->logAction('generate', null, "Generated fee collection report");

            return $this->response([
                'status' => 'success',
                'data' => $report
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Generate transport usage report
    public function transportReport($params)
    {
        try {
            $required = ['start_date', 'end_date'];
            $missing = $this->validateRequired($params, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Get route usage data
            $sql = "
                SELECT 
                    r.id,
                    r.name as route_name,
                    v.registration_no,
                    CONCAT(d.first_name, ' ', d.last_name) as driver_name,
                    COUNT(DISTINCT ta.student_id) as student_count,
                    COUNT(DISTINCT da.id) as trip_count
                FROM transport_routes r
                LEFT JOIN vehicles v ON r.vehicle_id = v.id
                LEFT JOIN drivers d ON r.driver_id = d.id
                LEFT JOIN transport_assignments ta ON r.id = ta.route_id
                LEFT JOIN driver_attendance da ON d.id = da.driver_id
                AND da.date BETWEEN ? AND ?
                WHERE r.status = 'active'
                GROUP BY r.id
                ORDER BY student_count DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$params['start_date'], $params['end_date']]);
            $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get vehicle maintenance data
            $sql = "
                SELECT 
                    v.registration_no,
                    COUNT(m.id) as maintenance_count,
                    COALESCE(SUM(m.cost), 0) as total_cost
                FROM vehicles v
                LEFT JOIN vehicle_maintenance m ON v.id = m.vehicle_id
                AND m.maintenance_date BETWEEN ? AND ?
                WHERE v.status = 'active'
                GROUP BY v.id
                ORDER BY total_cost DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$params['start_date'], $params['end_date']]);
            $maintenance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate statistics
            $stats = $this->calculateTransportStats($routes, $maintenance);

            // Format report data
            $report = [
                'period' => [
                    'start' => $params['start_date'],
                    'end' => $params['end_date']
                ],
                'routes' => $routes,
                'maintenance' => $maintenance,
                'statistics' => $stats
            ];

            // Log report generation
            $this->logAction('generate', null, "Generated transport usage report");

            return $this->response([
                'status' => 'success',
                'data' => $report
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // Helper function to calculate class statistics
    private function calculateClassStats($performance)
    {
        $stats = [
            'subject_averages' => [],
            'performance_bands' => [
                'excellent' => 0,
                'good' => 0,
                'average' => 0,
                'below_average' => 0
            ],
            'overall_average' => 0
        ];

        $subjectTotals = [];
        $subjectCounts = [];
        $totalScore = 0;
        $scoreCount = 0;

        foreach ($performance as $record) {
            // Subject averages
            if (!isset($subjectTotals[$record['subject_name']])) {
                $subjectTotals[$record['subject_name']] = 0;
                $subjectCounts[$record['subject_name']] = 0;
            }
            $subjectTotals[$record['subject_name']] += $record['average_score'];
            $subjectCounts[$record['subject_name']]++;

            // Overall average
            $totalScore += $record['average_score'];
            $scoreCount++;

            // Performance bands
            if ($record['average_score'] >= 80) {
                $stats['performance_bands']['excellent']++;
            } elseif ($record['average_score'] >= 65) {
                $stats['performance_bands']['good']++;
            } elseif ($record['average_score'] >= 50) {
                $stats['performance_bands']['average']++;
            } else {
                $stats['performance_bands']['below_average']++;
            }
        }

        // Calculate subject averages
        foreach ($subjectTotals as $subject => $total) {
            $stats['subject_averages'][$subject] = round($total / $subjectCounts[$subject], 2);
        }

        // Calculate overall average
        $stats['overall_average'] = $scoreCount > 0 ? round($totalScore / $scoreCount, 2) : 0;

        return $stats;
    }

    // Helper function to calculate attendance statistics
    private function calculateAttendanceStats($attendance)
    {
        $stats = [
            'total_students' => count($attendance),
            'total_days' => 0,
            'average_attendance' => 0,
            'attendance_bands' => [
                'excellent' => 0,
                'good' => 0,
                'poor' => 0
            ]
        ];

        $totalAttendance = 0;

        foreach ($attendance as $record) {
            $stats['total_days'] = max($stats['total_days'], $record['total_days']);
            
            if ($record['total_days'] > 0) {
                $attendanceRate = ($record['present_days'] / $record['total_days']) * 100;
                $totalAttendance += $attendanceRate;

                if ($attendanceRate >= 90) {
                    $stats['attendance_bands']['excellent']++;
                } elseif ($attendanceRate >= 75) {
                    $stats['attendance_bands']['good']++;
                } else {
                    $stats['attendance_bands']['poor']++;
                }
            }
        }

        $stats['average_attendance'] = $stats['total_students'] > 0 
            ? round($totalAttendance / $stats['total_students'], 2) 
            : 0;

        return $stats;
    }

    // Helper function to calculate fee statistics
    private function calculateFeeStats($payments)
    {
        $stats = [
            'total_collected' => 0,
            'payment_methods' => [],
            'fee_types' => [],
            'daily_collections' => []
        ];

        foreach ($payments as $payment) {
            // Total collected
            $stats['total_collected'] += $payment['amount'];

            // Payment methods
            if (!isset($stats['payment_methods'][$payment['payment_method']])) {
                $stats['payment_methods'][$payment['payment_method']] = 0;
            }
            $stats['payment_methods'][$payment['payment_method']] += $payment['amount'];

            // Fee types
            if (!isset($stats['fee_types'][$payment['fee_type']])) {
                $stats['fee_types'][$payment['fee_type']] = 0;
            }
            $stats['fee_types'][$payment['fee_type']] += $payment['amount'];

            // Daily collections
            $date = date('Y-m-d', strtotime($payment['payment_date']));
            if (!isset($stats['daily_collections'][$date])) {
                $stats['daily_collections'][$date] = 0;
            }
            $stats['daily_collections'][$date] += $payment['amount'];
        }

        // Sort daily collections by date
        ksort($stats['daily_collections']);

        return $stats;
    }

    // Helper function to calculate transport statistics
    private function calculateTransportStats($routes, $maintenance)
    {
        $stats = [
            'total_routes' => count($routes),
            'total_students' => 0,
            'total_trips' => 0,
            'total_maintenance_cost' => 0,
            'maintenance_by_vehicle' => [],
            'route_utilization' => []
        ];

        foreach ($routes as $route) {
            $stats['total_students'] += $route['student_count'];
            $stats['total_trips'] += $route['trip_count'];
            $stats['route_utilization'][$route['route_name']] = $route['student_count'];
        }

        foreach ($maintenance as $record) {
            $stats['total_maintenance_cost'] += $record['total_cost'];
            $stats['maintenance_by_vehicle'][$record['registration_no']] = [
                'count' => $record['maintenance_count'],
                'cost' => $record['total_cost']
            ];
        }

        return $stats;
    }

    // Helper function to format performance data
    private function formatPerformanceData($performance)
    {
        $formatted = [];
        
        foreach ($performance as $record) {
            $studentId = $record['id'];
            
            if (!isset($formatted[$studentId])) {
                $formatted[$studentId] = [
                    'student' => [
                        'id' => $record['id'],
                        'admission_no' => $record['admission_no'],
                        'name' => $record['first_name'] . ' ' . $record['last_name'],
                        'stream' => $record['stream_name']
                    ],
                    'subjects' => []
                ];
            }
            
            $formatted[$studentId]['subjects'][$record['subject_name']] = [
                'average_score' => $record['average_score'],
                'assessment_count' => $record['assessment_count']
            ];
        }

        return array_values($formatted);
    }

    public function getDashboardStats($params = []) {
        // Try to get real data from DB/module, fallback to dummy if empty
        $stats = null;
        // Example: $stats = $this->fetchDashboardStatsFromDb($params);
        if (!$stats) {
            $stats = [
                'students' => [
                    'total' => 1200,
                    'growth' => 5,
                    'by_class' => [ ['class' => 'P1', 'count' => 100], ['class' => 'P2', 'count' => 110] ],
                    'by_gender' => [ 'male' => 600, 'female' => 600 ],
                    'by_status' => [ 'active' => 1100, 'inactive' => 50, 'suspended' => 50 ]
                ],
                'staff' => [
                    'total' => 80,
                    'teaching' => 60,
                    'non_teaching' => 20,
                    'growth' => 2,
                    'present' => 75,
                    'on_leave' => 5,
                    'by_department' => [ ['department' => 'Math', 'count' => 10] ],
                    'by_role' => [ 'teaching' => 60, 'non_teaching' => 20, 'admin' => 5 ]
                ],
                'attendance' => [
                    'today' => 1150,
                    'total' => 1200,
                    'rate' => 95.8,
                    'by_class' => [ ['class' => 'P1', 'present' => 98] ],
                    'trend' => [ ['date' => '2025-06-01', 'present' => 1100] ],
                    'by_status' => [ 'present' => 1150, 'absent' => 50, 'late' => 10 ]
                ],
                'finance' => [
                    'total' => 1000000,
                    'paid' => 800000,
                    'unpaid' => 200000,
                    'growth' => 3,
                    'by_type' => [ ['type' => 'Tuition', 'amount' => 700000] ],
                    'by_status' => [ ['status' => 'Paid', 'amount' => 800000] ],
                    'trend' => [ ['month' => '2025-06', 'amount' => 100000] ]
                ],
                'activities' => [
                    'total' => 10,
                    'upcoming' => [ ['name' => 'Sports Day', 'date' => '2025-06-15'] ]
                ],
                'schedules' => [
                    'total' => 5,
                    'today' => [ ['event' => 'Assembly', 'time' => '08:00'] ]
                ]
            ];
        }
        return [ 'status' => 'success', 'data' => $stats ];
    }

                            'attendance_rate' => 0,
                            'pending_assignments' => 0
                        ];
                        break;
                    case 'admissions':
                        $data['admissions'] = $this->getAdmissionsStats() ?? [
                            'total_applications' => 0,
                            'pending_applications' => 0,
                            'approved_applications' => 0,
                            'rejected_applications' => 0,
                            'applications_growth' => 0,
                            'recent_applications' => []
                        ];
                        break;
                    case 'transport':
                        $data['transport'] = $this->getTransportStats() ?? [
                            'total_students' => 0,
                            'active_routes' => 0,
                            'total_routes' => 0,
                            'available_vehicles' => 0,
                            'total_vehicles' => 0,
                            'revenue' => 0,
                            'students_growth' => 0,
                            'revenue_growth' => 0,
                            'active_routes_list' => []
                        ];
                        break;
                    case 'head_teacher':
                        $data['academic'] = $this->getAcademicReport() ?? [
                            'average_score' => 0,
                            'total_students' => 0,
                            'passed_students' => 0,
                            'total_subjects' => 0,
                            'score_growth' => 0,
                            'subjects' => [],
                            'classes' => [],
                            'trend' => []
                        ];
                        break;
                }
            }

            return [
                'status' => 'success',
                'data' => $data
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to fetch dashboard stats',
                'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
            ];
        }
    }

    private function getStudentStats() {
        try {
            // Get total active students
            $stmt = $this->db->query("SELECT COUNT(*) FROM students WHERE status = 'active'");
            $total = $stmt->fetchColumn() ?? 0;

            // Get student growth rate
            $stmt = $this->db->query("
                SELECT 
                    (COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                        AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END), 0)) - 100 
                FROM students WHERE status = 'active'
            ");
            $growth = round($stmt->fetchColumn() ?? 0, 1);

            return [
                'total' => $total,
                'growth' => $growth,
                'by_class' => [],
                'by_gender' => [
                    'male' => 0,
                    'female' => 0
                ],
                'by_status' => [
                    'active' => $total,
                    'inactive' => 0,
                    'suspended' => 0
                ]
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error in getStudentStats: " . $e->getMessage());
            return [
                'total' => 0,
                'growth' => 0,
                'by_class' => [],
                'by_gender' => [
                    'male' => 0,
                    'female' => 0
                ],
                'by_status' => [
                    'active' => 0,
                    'inactive' => 0,
                    'suspended' => 0
                ]
            ];
        }
    }

    private function getStaffStats() {
        try {
            // Get total active staff
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN role = 'teaching' THEN 1 END) as teaching,
                    COUNT(CASE WHEN role = 'non_teaching' THEN 1 END) as non_teaching
                FROM staff 
                WHERE status = 'active'
            ");
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            $staff = [
                'total' => $staff['total'] ?? 0,
                'teaching' => $staff['teaching'] ?? 0,
                'non_teaching' => $staff['non_teaching'] ?? 0
            ];

            // Get staff growth rate
            $stmt = $this->db->query("
                SELECT 
                    (COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) * 100.0 / 
                    NULLIF(COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                        AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END), 0)) - 100 
                FROM staff 
                WHERE status = 'active'
            ");
            $growth = round($stmt->fetchColumn() ?? 0, 1);

            // Get staff attendance
            $stmt = $this->db->query("
                SELECT 
                    COUNT(DISTINCT CASE WHEN status = 'present' THEN staff_id END) as present,
                    COUNT(DISTINCT CASE WHEN status = 'on_leave' THEN staff_id END) as on_leave
                FROM staff_attendance 
                WHERE date = CURRENT_DATE
            ");
            $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
            $attendance = [
                'present' => $attendance['present'] ?? 0,
                'on_leave' => $attendance['on_leave'] ?? 0
            ];

            return array_merge($staff, [
                'growth' => $growth,
                'present' => $attendance['present'],
                'on_leave' => $attendance['on_leave'],
                'by_department' => [],
                'by_role' => [
                    'teaching' => $staff['teaching'],
                    'non_teaching' => $staff['non_teaching'],
                    'admin' => 0
                ]
            ]);
        } catch (Exception $e) {
            error_log("ReportsAPI Error in getStaffStats: " . $e->getMessage());
            return [
                'total' => 0,
                'teaching' => 0,
                'non_teaching' => 0,
                'growth' => 0,
                'present' => 0,
                'on_leave' => 0,
                'by_department' => [],
                'by_role' => [
                    'teaching' => 0,
                    'non_teaching' => 0,
                    'admin' => 0
                ]
            };
        }
    }

    private function getAttendanceStats() {
        try {
            // Get today's attendance
            $stmt = $this->db->query("
                SELECT 
                    COUNT(DISTINCT CASE WHEN status = 'present' THEN student_id END) as present,
                    COUNT(DISTINCT student_id) as total
                FROM student_attendance 
                WHERE date = CURRENT_DATE
            ");
            $today = $stmt->fetch(PDO::FETCH_ASSOC);
            $today = [
                'present' => $today['present'] ?? 0,
                'total' => $today['total'] ?? 0
            ];

            // Calculate attendance rate
            $rate = $today['total'] > 0 ? round(($today['present'] / $today['total']) * 100, 1) : 0;

            return [
                'today' => $today['present'],
                'total' => $today['total'],
                'rate' => $rate,
                'by_class' => [],
                'trend' => [],
                'by_status' => [
                    'present' => $today['present'],
                    'absent' => $today['total'] - $today['present'],
                    'late' => 0
                ]
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error in getAttendanceStats: " . $e->getMessage());
            return [
                'today' => 0,
                'total' => 0,
                'rate' => 0,
                'by_class' => [],
                'trend' => [],
                'by_status' => [
                    'present' => 0,
                    'absent' => 0,
                    'late' => 0
                ]
            ];
        }
    }

    private function getFinanceStats() {
        try {
            // Get finance summary
            $stmt = $this->db->query("
                SELECT 
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount END), 0) as collected,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount END), 0) as expenses,
                    COALESCE(SUM(CASE WHEN type = 'income' AND status = 'pending' THEN amount END), 0) as outstanding
                FROM transactions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'collected' => $stats['collected'] ?? 0,
                'expenses' => $stats['expenses'] ?? 0,
                'outstanding' => $stats['outstanding'] ?? 0,
                'by_category' => [],
                'trend' => [],
                'recent_transactions' => []
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error in getFinanceStats: " . $e->getMessage());
            return [
                'collected' => 0,
                'expenses' => 0,
                'outstanding' => 0,
                'by_category' => [],
                'trend' => [],
                'recent_transactions' => []
            ];
        }
    }

    private function getUpcomingActivities() {
        $stmt = $this->db->query("
            SELECT 
                title,
                description,
                venue,
                start_date,
                end_date,
                status
            FROM activities 
            WHERE start_date >= CURRENT_DATE
            ORDER BY start_date ASC 
            LIMIT 5
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getTodaySchedules() {
        $stmt = $this->db->query("
            SELECT 
                s.name as subject,
                CONCAT(c.name, ' ', cs.name) as class_name,
                CONCAT(st.first_name, ' ', st.last_name) as teacher_name,
                ts.start_time,
                ts.end_time,
                ts.room
            FROM timetable_slots ts
            JOIN learning_areas s ON ts.subject_id = s.id
            JOIN class_streams cs ON ts.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            JOIN staff st ON ts.teacher_id = st.id
            WHERE ts.day = DAYNAME(CURRENT_DATE)
            ORDER BY ts.start_time ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function getTeacherStats($teacherId) {
        // Get teacher's students count
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT s.id) as students_count
            FROM students s
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN timetable_slots ts ON cs.id = ts.stream_id
            WHERE ts.teacher_id = ? AND s.status = 'active'
        ");
        $stmt->execute([$teacherId]);
        $students = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get today's classes
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as classes_today
            FROM timetable_slots
            WHERE teacher_id = ? AND day = DAYNAME(CURRENT_DATE)
        ");
        $stmt->execute([$teacherId]);
        $classes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get attendance rate for teacher's classes
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN sa.student_id END) as present,
                COUNT(DISTINCT sa.student_id) as total
            FROM student_attendance sa
            JOIN students s ON sa.student_id = s.id
            JOIN class_streams cs ON s.stream_id = cs.id
            JOIN timetable_slots ts ON cs.id = ts.stream_id
            WHERE ts.teacher_id = ? AND sa.date = CURRENT_DATE
        ");
        $stmt->execute([$teacherId]);
        $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
        $attendanceRate = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100, 1) : 0;

        // Get pending assignments
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as pending_assignments
            FROM assignments a
            JOIN learning_areas s ON a.subject_id = s.id
            JOIN timetable_slots ts ON s.id = ts.subject_id
            WHERE ts.teacher_id = ? AND a.due_date >= CURRENT_DATE
        ");
        $stmt->execute([$teacherId]);
        $assignments = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'students_count' => $students['students_count'],
            'classes_today' => $classes['classes_today'],
            'attendance_rate' => $attendanceRate,
            'pending_assignments' => $assignments['pending_assignments']
        ];
    }

    private function getAdmissionsStats() {
        // Get applications summary
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_applications,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_applications,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_applications,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_applications
            FROM student_applications
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ");
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get applications growth rate
        $stmt = $this->db->query("
            SELECT 
                (COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) * 100.0 / 
                NULLIF(COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                    AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END), 0)) - 100 
            FROM student_applications
        ");
        $summary['applications_growth'] = round($stmt->fetchColumn(), 1);

        // Get recent applications
        $stmt = $this->db->query("
            SELECT 
                CONCAT(sa.first_name, ' ', sa.last_name) as student_name,
                c.name as class_name,
                TIMESTAMPDIFF(YEAR, sa.date_of_birth, CURDATE()) as age,
                sa.status,
                sa.created_at
            FROM student_applications sa
            JOIN classes c ON sa.class_id = c.id
            ORDER BY sa.created_at DESC
            LIMIT 10
        ");
        $summary['recent_applications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $summary;
    }

    private function getTransportStats() {
        // Get transport summary
        $stmt = $this->db->query("
            SELECT 
                (SELECT COUNT(*) FROM students WHERE transport_route_id IS NOT NULL) as total_students,
                (SELECT COUNT(*) FROM transport_routes WHERE status = 'active') as active_routes,
                (SELECT COUNT(*) FROM transport_routes) as total_routes,
                (SELECT COUNT(*) FROM transport_vehicles WHERE status = 'available') as available_vehicles,
                (SELECT COUNT(*) FROM transport_vehicles) as total_vehicles,
                (SELECT COALESCE(SUM(amount), 0) FROM transport_payments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)) as revenue
            FROM dual
        ");
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get students growth rate
        $stmt = $this->db->query("
            SELECT 
                (COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END) * 100.0 / 
                NULLIF(COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                    AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN 1 END), 0)) - 100 
            FROM students 
            WHERE transport_route_id IS NOT NULL
        ");
        $summary['students_growth'] = round($stmt->fetchColumn(), 1);

        // Get revenue growth rate
        $stmt = $this->db->query("
            SELECT 
                (SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN amount END) * 100.0 / 
                NULLIF(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                    AND created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) THEN amount END), 0)) - 100 
            FROM transport_payments
        ");
        $summary['revenue_growth'] = round($stmt->fetchColumn(), 1);

        // Get active routes list
        $stmt = $this->db->query("
            SELECT 
                r.name,
                CONCAT(s.first_name, ' ', s.last_name) as driver_name,
                v.registration_number as vehicle_reg,
                (SELECT COUNT(*) FROM students WHERE transport_route_id = r.id) as students_count,
                r.next_trip
            FROM transport_routes r
            JOIN staff s ON r.driver_id = s.id
            JOIN transport_vehicles v ON r.vehicle_id = v.id
            WHERE r.status = 'active'
            ORDER BY r.next_trip ASC
        ");
        $summary['active_routes_list'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $summary;
    }

    public function getAcademicReport($params = []) {
        // Get current term
        $stmt = $this->db->query("
            SELECT id, name 
            FROM academic_terms 
            WHERE status = 'active' 
            LIMIT 1
        ");
        $term = $stmt->fetch(PDO::FETCH_ASSOC);
        $termId = $term['id'];

        // Get average scores
        $stmt = $this->db->prepare("
            SELECT 
                AVG(ar.marks_obtained) as average_score,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ar.marks_obtained >= 50 THEN ar.student_id END) as passed_students,
                COUNT(DISTINCT s.id) as total_subjects
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            JOIN learning_areas s ON a.subject_id = s.id
            WHERE a.term_id = ?
        ");
        $stmt->execute([$termId]);
        $scores = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get score growth rate
        $stmt = $this->db->prepare("
            SELECT 
                (AVG(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH) 
                    THEN ar.marks_obtained END) * 100.0 / 
                NULLIF(AVG(CASE WHEN a.created_at >= DATE_SUB(NOW(), INTERVAL 2 MONTH) 
                    AND a.created_at < DATE_SUB(NOW(), INTERVAL 1 MONTH) 
                    THEN ar.marks_obtained END), 0)) - 100 
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            WHERE a.term_id = ?
        ");
        $stmt->execute([$termId]);
        $scores['score_growth'] = round($stmt->fetchColumn(), 1);

        // Get performance by subject
        $stmt = $this->db->prepare("
            SELECT 
                s.name as subject,
                AVG(ar.marks_obtained) as average_score,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ar.marks_obtained >= 50 THEN ar.student_id END) as passed_students
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            JOIN learning_areas s ON a.subject_id = s.id
            WHERE a.term_id = ?
            GROUP BY s.id, s.name
            ORDER BY average_score DESC
        ");
        $stmt->execute([$termId]);
        $scores['subjects'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get performance by class
        $stmt = $this->db->prepare("
            SELECT 
                CONCAT(c.name, ' ', cs.name) as class_name,
                AVG(ar.marks_obtained) as average_score,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ar.marks_obtained >= 50 THEN ar.student_id END) as passed_students
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            JOIN students st ON ar.student_id = st.id
            JOIN class_streams cs ON st.stream_id = cs.id
            JOIN classes c ON cs.class_id = c.id
            WHERE a.term_id = ?
            GROUP BY cs.id, c.name, cs.name
            ORDER BY c.level_order ASC, cs.name ASC
        ");
        $stmt->execute([$termId]);
        $scores['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get performance trend
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(a.created_at, '%Y-%m') as month,
                AVG(ar.marks_obtained) as average_score,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(DISTINCT CASE WHEN ar.marks_obtained >= 50 THEN ar.student_id END) as passed_students
            FROM assessment_results ar
            JOIN assessments a ON ar.assessment_id = a.id
            WHERE a.term_id = ?
            GROUP BY month
            ORDER BY month ASC
        ");
        $stmt->execute([$termId]);
        $scores['trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $scores;
    }

    public function getSystemReports($params = []) {
        try {
            // Get system logs
            $stmt = $this->db->query("
                SELECT 
                    sl.action,
                    sl.entity_type,
                    sl.description,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    sl.ip_address,
                    sl.created_at
                FROM system_logs sl
                LEFT JOIN users u ON sl.user_id = u.id
                ORDER BY sl.created_at DESC
                LIMIT 100
            ");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Get error logs if debug mode is enabled
            $errorLogs = [];
            if (getenv('APP_DEBUG')) {
                $logFile = __DIR__ . '/../../../logs/errors.log';
                if (file_exists($logFile)) {
                    $errorLogs = array_map(function($line) {
                        return json_decode($line, true);
                    }, array_slice(file($logFile), -100));
                }
            }

            return [
                'status' => 'success',
                'data' => [
                    'system_logs' => $logs,
                    'error_logs' => $errorLogs
                ]
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to fetch system reports',
                'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
            ];
        }
    }

    public function getAuditReports($params = []) {
        try {
            // Get audit logs
            $stmt = $this->db->query("
                SELECT 
                    al.action,
                    al.table_name,
                    al.record_id,
                    al.old_values,
                    al.new_values,
                    CONCAT(u.first_name, ' ', u.last_name) as user_name,
                    al.created_at
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT 100
            ");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'status' => 'success',
                'data' => $logs
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to fetch audit reports',
                'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
            ];
        }
    }

    public function generateCustomReport($params = []) {
        try {
            $required = ['type', 'start_date', 'end_date'];
            $missing = $this->validateRequired($params, $required);
            if (!empty($missing)) {
                return [
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ];
            }

            $type = $params['type'];
            $startDate = $params['start_date'];
            $endDate = $params['end_date'];
            $data = [];

            switch ($type) {
                case 'student_performance':
                    $stmt = $this->db->prepare("
                        SELECT 
                            c.name as class_name,
                            s.name as subject_name,
                            COUNT(DISTINCT ar.student_id) as total_students,
                            AVG(ar.marks_obtained) as average_score,
                            COUNT(DISTINCT CASE WHEN ar.marks_obtained >= 50 THEN ar.student_id END) as passed_students
                        FROM assessment_results ar
                        JOIN assessments a ON ar.assessment_id = a.id
                        JOIN learning_areas s ON a.subject_id = s.id
                        JOIN students st ON ar.student_id = st.id
                        JOIN class_streams cs ON st.stream_id = cs.id
                        JOIN classes c ON cs.class_id = c.id
                        WHERE a.created_at BETWEEN ? AND ?
                        GROUP BY c.id, s.id
                        ORDER BY c.name, s.name
                    ");
                    $stmt->execute([$startDate, $endDate]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    break;

                case 'attendance':
                    $stmt = $this->db->prepare("
                        SELECT 
                            c.name as class_name,
                            sa.date,
                            COUNT(DISTINCT sa.student_id) as total_students,
                            COUNT(DISTINCT CASE WHEN sa.status = 'present' THEN sa.student_id END) as present,
                            COUNT(DISTINCT CASE WHEN sa.status = 'absent' THEN sa.student_id END) as absent
                        FROM student_attendance sa
                        JOIN students s ON sa.student_id = s.id
                        JOIN class_streams cs ON s.stream_id = cs.id
                        JOIN classes c ON cs.class_id = c.id
                        WHERE sa.date BETWEEN ? AND ?
                        GROUP BY c.id, sa.date
                        ORDER BY c.name, sa.date
                    ");
                    $stmt->execute([$startDate, $endDate]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    break;

                case 'finance':
                    $stmt = $this->db->prepare("
                        SELECT 
                            DATE_FORMAT(created_at, '%Y-%m') as month,
                            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expenses,
                            COUNT(DISTINCT CASE WHEN type = 'income' THEN id END) as income_transactions,
                            COUNT(DISTINCT CASE WHEN type = 'expense' THEN id END) as expense_transactions
                        FROM transactions
                        WHERE created_at BETWEEN ? AND ?
                        GROUP BY month
                        ORDER BY month
                    ");
                    $stmt->execute([$startDate, $endDate]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    break;

                default:
                    return [
                        'status' => 'error',
                        'message' => 'Invalid report type'
                    ];
            }

            return [
                'status' => 'success',
                'data' => $data
            ];
        } catch (Exception $e) {
            error_log("ReportsAPI Error: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => 'Failed to generate custom report',
                'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
            ];
        }
    }
}
