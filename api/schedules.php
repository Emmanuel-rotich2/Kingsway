<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/modules/schedules/SchedulesAPI.php';
require_once __DIR__ . '/includes/BulkOperationsHelper.php';

$bulkHelper = new \App\API\Includes\BulkOperationsHelper($db);


use App\API\Modules\schedules\SchedulesAPI;
use Exception;

header('Content-Type: application/json');

$schedulesApi = new SchedulesAPI();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

// Parse input data
$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $input = $_POST;
    // For JSON requests
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($action === 'timetable') {
                echo json_encode($schedulesApi->getTimetable($_GET));
            } elseif ($action === 'exam-schedule') {
                echo json_encode($schedulesApi->getExamSchedule($_GET));
            } elseif ($action === 'events') {
                echo json_encode($schedulesApi->getEvents($_GET));
            } elseif ($action === 'activity-schedule') {
                echo json_encode($schedulesApi->getActivitySchedule($_GET));
            } elseif ($action === 'route-schedule') {
                echo json_encode($schedulesApi->getRouteSchedule($_GET));
            } elseif ($action === 'rooms') {
                echo json_encode($schedulesApi->getRooms($_GET));
            } elseif ($action === 'scheduled-reports') {
                echo json_encode($schedulesApi->getScheduledReports($_GET));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;

        case 'POST':
            if ($action === 'create-timetable-entry') {
                echo json_encode($schedulesApi->createTimetableEntry($input));
            } elseif ($action === 'create-exam-schedule') {
                echo json_encode($schedulesApi->createExamSchedule($input));
            } elseif ($action === 'create-event') {
                echo json_encode($schedulesApi->createEvent($input));
            } elseif ($action === 'create-activity-schedule') {
                echo json_encode($schedulesApi->createActivitySchedule($input));
            } elseif ($action === 'create-room') {
                echo json_encode($schedulesApi->createRoom($input));
            } elseif ($action === 'create-scheduled-report') {
                echo json_encode($schedulesApi->createScheduledReport($input));
            } elseif ($action === 'create-route-schedule') {
                echo json_encode($schedulesApi->createRouteSchedule($input));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid POST request']);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $params['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ID is required'
                ]);
                break;
            }
            
            echo $schedulesApi->update($id, $data);
            break;

        case 'DELETE':
            $id = $params['id'] ?? null;
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ID is required'
                ]);
                break;
            }
            
            echo $schedulesApi->delete($id);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
} 