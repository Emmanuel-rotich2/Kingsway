<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/modules/reports/ReportsAPI.php';

use App\API\Modules\Reports\ReportsAPI;
use Exception;
use PDO;

// Disable error reporting for production
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    $reportsApi = new ReportsAPI();
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $id = $_GET['id'] ?? null;

    // Parse input data
    $input = [];
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $input = $_POST;
        if (empty($input)) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
        }
    }

    switch ($method) {
        case 'GET':
            if ($action === 'dashboard_stats') {
                // Return dashboard stats in the exact format expected by admin_dashboard.php
                $stats = [
                    'summaryCards' => [
                        [
                            'title' => 'Total Students',
                            'count' => 1240,
                            'percent' => 92,
                            'days' => 7,
                            'icon' => 'bi-people-fill',
                            'bgColor' => '#6f42c1',
                            'iconColor' => 'text-white',
                            'iconSize' => 'fs-3',
                            'textColor' => 'text-white',
                            'subTextColor' => 'text-white-50',
                            'cardClass' => 'card-rounded small-card shadow-sm',
                            'iconPosition' => 'start'
                        ],
                        [
                            'title' => 'Present Today',
                            'count' => 1175,
                            'percent' => 95,
                            'days' => 1,
                            'icon' => 'bi-person-check-fill',
                            'bgColor' => '#198754',
                            'iconColor' => 'text-white',
                            'iconSize' => 'fs-3',
                            'textColor' => 'text-white',
                            'subTextColor' => 'text-white-50',
                            'cardClass' => 'card-rounded small-card shadow-sm',
                            'iconPosition' => 'start'
                        ],
                        [
                            'title' => 'Teachers',
                            'count' => 48,
                            'percent' => 100,
                            'days' => 1,
                            'icon' => 'bi-person-badge-fill',
                            'bgColor' => '#0d6efd',
                            'iconColor' => 'text-white',
                            'iconSize' => 'fs-3',
                            'textColor' => 'text-white',
                            'subTextColor' => 'text-white-50',
                            'cardClass' => 'card-rounded small-card shadow-sm',
                            'iconPosition' => 'start'
                        ],
                        [
                            'title' => 'Fees Collected (Ksh)',
                            'count' => 2350000,
                            'percent' => 80,
                            'days' => 30,
                            'icon' => 'bi-currency-dollar',
                            'bgColor' => '#fd7e14',
                            'iconColor' => 'text-white',
                            'iconSize' => 'fs-3',
                            'textColor' => 'text-white',
                            'subTextColor' => 'text-white-50',
                            'cardClass' => 'card-rounded small-card shadow-sm',
                            'iconPosition' => 'start'
                        ]
                    ],
                    'months' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    'admissions' => [30, 40, 35, 50, 60, 55, 70, 68, 90, 75, 80, 1000],
                    'feePayments' => [200000, 180000, 220000, 250000, 210000, 230000, 240000, 260000, 270000, 250000, 245000, 255000],
                    'doughnutData' => [1175, 65],
                    'incomeData' => [80, 20],
                    'withdrawData' => [60, 40],
                    'activityLabels' => ['Sat', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                    'activityData' => [120, 130, 140, 135, 145, 150, 148],
                    'salesStats' => [
                        [
                            'label' => 'Current Term',
                            'value' => 2350000,
                            'change' => '+5%',
                            'changeClass' => 'text-success'
                        ],
                        [
                            'label' => 'Last Term',
                            'value' => 2250000,
                            'change' => '+2%',
                            'changeClass' => 'text-success'
                        ],
                        [
                            'label' => 'Outstanding Fees',
                            'value' => 600000,
                            'change' => '-1%',
                            'changeClass' => 'text-danger'
                        ]
                    ],
                    'studentHeaders' => ['No', 'Name', 'Admission Date', 'Class', 'Parent Contact', 'Status'],
                    'studentRows' => [
                        [1, 'Faith Wanjiku', '2024-05-10', 'Grade 4', '0712 345678', 'Active'],
                        [2, 'Brian Otieno', '2024-05-09', 'Form 1', '0722 123456', 'Active'],
                        [3, 'Mercy Mwikali', '2024-05-08', 'Grade 8', '0733 987654', 'Active'],
                        [4, 'Samuel Kiptoo', '2024-05-07', 'Form 2', '0700 112233', 'Active'],
                        [5, 'Janet Njeri', '2024-05-06', 'Grade 6', '0799 445566', 'Active']
                    ]
                ];
                echo json_encode(['status' => 'success', 'data' => $stats]);
                exit;
            } elseif ($action === 'academic') {
                echo json_encode($reportsApi->getAcademicReport($input));
            } elseif ($action === 'system') {
                echo json_encode($reportsApi->getSystemReports($input));
            } elseif ($action === 'audit') {
                echo json_encode($reportsApi->getAuditReports($input));
            } else {
                throw new Exception('Invalid GET request');
            }
            break;

        case 'POST':
            if ($action === 'custom') {
                echo json_encode($reportsApi->generateCustomReport($input));
            } else {
                throw new Exception('Invalid POST request');
            }
            break;

        default:
            throw new Exception('Unsupported HTTP method');
    }
} catch (Exception $e) {
    error_log("Reports API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing your request',
        'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
    ]);
}