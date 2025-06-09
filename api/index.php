<?php
namespace App\API;

use App\API\Includes\BaseAPI;
use App\API\Modules\staff\StaffAPI;
use App\API\Modules\students\StudentsAPI;
use App\API\Modules\academic\AcademicAPI;
use App\API\Modules\attendance\AttendanceAPI;
use App\API\Modules\finance\FinanceAPI;
use App\API\Modules\transport\TransportAPI;
use App\API\Modules\inventory\InventoryAPI;
use App\API\Modules\activities\ActivitiesAPI;
use App\API\Modules\reports\ReportsAPI;
use App\API\Modules\auth\AuthAPI;
use App\API\Modules\communications\CommunicationsAPI;
use App\API\Modules\users\UsersAPI;
use App\API\Modules\schedules\SchedulesAPI;
use Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/helpers.php';

use function App\API\Includes\formatResponse;

// Handle CORS
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($origin, ALLOWED_ORIGINS)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

// Parse the request URL
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = dirname($_SERVER['SCRIPT_NAME']);
$endpoint = str_replace($base_path, '', $request_uri);
$endpoint = trim($endpoint, '/');
$parts = explode('/', $endpoint);

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$data = [];
if ($method === 'GET') {
    $data = $_GET;
} else {
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $data = json_decode($input, true);
    }
    if (json_last_error() !== JSON_ERROR_NONE) {
        $data = $_POST;
    }
}

// Route the request to the appropriate handler
try {
    switch ($parts[0]) {
        case 'staff':
            $api = new StaffAPI();
            break;
            
        case 'students':
            $api = new StudentsAPI();
            break;
            
        case 'academic':
            $api = new AcademicAPI();
            break;
            
        case 'attendance':
            $api = new AttendanceAPI();
            break;
            
        case 'finance':
            $api = new FinanceAPI();
            break;
            
        case 'transport':
            $api = new TransportAPI();
            break;
            
        case 'inventory':
            $api = new InventoryAPI();
            break;
            
        case 'activities':
            $api = new ActivitiesAPI();
            break;
            
        case 'reports':
            $api = new ReportsAPI();
            break;
            
        case 'auth':
            $api = new AuthAPI();
            break;

        case 'communications':
            $api = new CommunicationsAPI();
            break;

        case 'users':
            $api = new UsersAPI();
            break;

        case 'schedules':
            $api = new SchedulesAPI();
            break;
            
        default:
            throw new Exception('Invalid endpoint');
    }

    // Call the appropriate method based on HTTP method and endpoint
    $id = isset($parts[1]) ? $parts[1] : null;
    $action = isset($parts[2]) ? $parts[2] : null;

    switch ($method) {
        case 'GET':
            if ($id && $action) {
                echo json_encode($api->handleCustomGet($id, $action, $data));
            } elseif ($id) {
                echo json_encode($api->get($id));
            } else {
                echo json_encode($api->list($data));
            }
            break;

        case 'POST':
            if ($id && $action) {
                echo json_encode($api->handleCustomPost($id, $action, $data));
            } else {
                echo json_encode($api->create($data));
            }
            break;

        case 'PUT':
            if ($id) {
                echo json_encode($api->update($id, $data));
            } else {
                throw new Exception('ID is required for update');
            }
            break;

        case 'DELETE':
            if ($id) {
                echo json_encode($api->delete($id));
            } else {
                throw new Exception('ID is required for delete');
            }
            break;

        default:
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(formatResponse(
        'error',
        DEBUG ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ] : null,
        $e->getMessage(),
        500
    ));
} 