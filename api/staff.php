<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/modules/staff/StaffAPI.php';
require_once __DIR__ . '/includes/BulkOperationsHelper.php';
require_once __DIR__ . '/includes/BulkCrudController.php';
use App\API\Includes\BulkCrudController;
use App\API\Modules\staff\StaffAPI;
use Exception;

$bulkCrud = new BulkCrudController($db);


// Handle all bulk and file operations centrally
$bulkCrud->handle(
    'staff',
    ['staff_no'], // unique columns
    'id',
    [
        'profile_pic_column' => 'profile_pic',
        'document_table' => 'staff_documents',
        'document_ref_column' => 'staff_id'
    ]
);




header('Content-Type: application/json');

$api = new StaffAPI($db);

$method = $_SERVER['REQUEST_METHOD'];
$params = [];
parse_str($_SERVER['QUERY_STRING'], $params);

// Parse input data
$input = [];
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $input = $_POST;
    if (empty($input)) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    }
}

try {
    switch ($method) {
        case 'GET':
            $action = $params['action'] ?? '';
            $id = $params['id'] ?? null;
            
            if ($id) {
                echo $api->get($id);
            } else {
                switch ($action) {
                    case 'departments':
                        echo $api->getDepartments();
                        break;
                        
                    case 'attendance':
                        echo $api->getAttendance($params);
                        break;
                        
                    case 'leaves':
                        echo $api->getLeaves($params);
                        break;
                        
                    default:
                        echo $api->list($params);
                        break;
                }
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            $action = $params['action'] ?? '';
            
            if ($action === 'export') {
                $format = $_GET['format'] ?? 'csv';
                $query = "SELECT * FROM staff";
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                require_once __DIR__ . '/includes/ExportHelper.php';
                $exportHelper = new \App\API\Includes\ExportHelper();
                $exportHelper->export($rows, $format, 'staff_export');
                exit;
            } else {
                echo $api->create($data);
            }
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $params['id'] ?? null;
            $action = $params['action'] ?? '';
            
            if (!$id) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'ID is required'
                ]);
                break;
            }
            
            switch ($action) {
                case 'update_leave_status':
                    echo $api->updateLeaveStatus($id, $data);
                    break;
                    
                default:
                    echo $api->update($id, $data);
                    break;
            }
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
            
            echo $api->delete($id);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}