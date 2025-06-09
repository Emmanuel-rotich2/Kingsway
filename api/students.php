<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
$db = \App\Config\Database::getInstance()->getConnection();

require_once __DIR__ . '/includes/BulkOperationsHelper.php';

require_once __DIR__ . '/includes/BulkCrudController.php';
use App\API\Includes\BulkCrudController;
use App\API\Modules\students\StudentsAPI;
use Exception;

$bulkHelper = new \App\API\Includes\BulkOperationsHelper($db);
$bulkCrud = new BulkCrudController($db);
// Handle all bulk and file operations centrally
$bulkCrud->handle(
    'students',
    ['admission_number'], // unique columns
    'id',
    [
        'profile_pic_column' => 'profile_pic',
        'document_table' => 'student_documents',
        'document_ref_column' => 'student_id'
    ]
);

require_once __DIR__ . '/modules/students/StudentsAPI.php';


header('Content-Type: application/json');

$studentsApi = new StudentsAPI($db);

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
            if ($action === 'list') {
                echo json_encode($studentsApi->list($_GET));
            } elseif ($action === 'view' && $id) {
                echo json_encode($studentsApi->get($id));
            } elseif ($action === 'profile' && $id) {
                echo json_encode($studentsApi->getProfile($id));
            } elseif ($action === 'attendance' && $id) {
                echo json_encode($studentsApi->getAttendance($id));
            } elseif ($action === 'performance' && $id) {
                echo json_encode($studentsApi->getPerformance($id));
            } elseif ($action === 'fees' && $id) {
                echo json_encode($studentsApi->getFees($id));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;
        case 'POST':
            if ($action === 'add') {
                echo json_encode($studentsApi->create($input));
            } elseif ($action === 'update' && $id) {
                echo json_encode($studentsApi->update($id, $input));
            } elseif ($action === 'delete' && $id) {
                echo json_encode($studentsApi->delete($id));
            } elseif ($action === 'promote' && $id) {
                echo json_encode($studentsApi->promote($id, $input));
            } elseif ($action === 'transfer' && $id) {
                echo json_encode($studentsApi->transfer($id, $input));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid POST request']);
            }
            break;
        default:
            echo json_encode(['status' => 'error', 'message' => 'Unsupported HTTP method']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
