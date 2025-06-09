<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
$db = \App\Config\Database::getInstance()->getConnection();
require_once __DIR__ . '/modules/attendance/AttendanceAPI.php';
require_once __DIR__ . '/includes/BulkOperationsHelper.php';

use App\API\Modules\Attendance\attendanceAPI;
use Exception;

header('Content-Type: application/json');

$bulkHelper = new \App\API\Includes\BulkOperationsHelper($db);
$attendanceApi = new AttendanceAPI($db);

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
                echo json_encode($attendanceApi->list($_GET));
            } elseif ($action === 'view' && $id) {
                echo json_encode($attendanceApi->get($id));
            } elseif ($id && $action) {
                // Custom GET actions (summary, report)
                echo json_encode($attendanceApi->handleCustomGet($id, $action, $_GET));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;

        case 'POST':
            if ($action === 'add') {
                echo json_encode($attendanceApi->create($input));
            } elseif ($action === 'update' && $id) {
                echo json_encode($attendanceApi->update($id, $input));
            } elseif ($action === 'delete' && $id) {
                echo json_encode($attendanceApi->delete($id));
            } elseif ($action === 'bulk_insert') {
                if (!empty($_FILES['file'])) {
                    $result = $bulkHelper->processUploadedFile($_FILES['file']);
                    if ($result['status'] === 'success') {
                        $data = $result['data'];
                        $unique = ['attendance_id']; // adjust as needed
                        $insertResult = $bulkHelper->bulkInsert('attendance', $data, $unique);
                        echo json_encode($insertResult);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    $data = $input;
                    $unique = ['attendance_id'];
                    $insertResult = $bulkHelper->bulkInsert('attendance', $data, $unique);
                    echo json_encode($insertResult);
                }
                exit;
            } elseif ($action === 'bulk_update') {
                $identifier = 'id';
                $result = $bulkHelper->bulkUpdate('attendance', $input, $identifier);
                echo json_encode($result);
                exit;
            } elseif ($action === 'bulk_delete') {
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "DELETE FROM attendance WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($ids);
                echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                exit;
            } elseif ($action === 'export') {
                $format = $_GET['format'] ?? 'csv';
                $query = "SELECT * FROM attendance";
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                require_once __DIR__ . '/includes/ExportHelper.php';
                $exportHelper = new \App\API\Includes\ExportHelper();
                $exportHelper->export($rows, $format, 'attendance_export');
                exit;
            } elseif ($id && $action) {
                // Custom POST actions (bulk-mark)
                echo json_encode($attendanceApi->handleCustomPost($id, $action, $input));
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