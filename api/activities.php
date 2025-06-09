<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/modules/activities/ActivitiesAPI.php';
require_once __DIR__ . '/includes/BulkOperationsHelper.php';

use App\API\Modules\activities\ActivitiesAPI;
use Exception;

header('Content-Type: application/json');

$activitiesApi = new ActivitiesAPI($db);
$bulkHelper = new \App\API\Includes\BulkOperationsHelper($db);

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
                echo json_encode($activitiesApi->list($_GET));
            } elseif ($action === 'view' && $id) {
                echo json_encode($activitiesApi->get($id));
            } elseif ($action === 'upcoming') {
                echo json_encode($activitiesApi->getUpcoming());
            } elseif ($action === 'student-activities' && $id) {
                echo json_encode($activitiesApi->getStudentActivities($id));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;

        case 'POST':
            if ($action === 'add') {
                echo json_encode($activitiesApi->create($input));
            } elseif ($action === 'update' && $id) {
                echo json_encode($activitiesApi->update($id, $input));
            } elseif ($action === 'delete' && $id) {
                echo json_encode($activitiesApi->delete($id));
            } elseif ($action === 'register') {
                echo json_encode($activitiesApi->registerParticipant($input));
            } elseif ($action === 'update-status' && $id) {
                echo json_encode($activitiesApi->updateParticipantStatus($id, $input));
            } elseif ($action === 'bulk_insert') {
                if (!empty($_FILES['file'])) {
                    $result = $bulkHelper->processUploadedFile($_FILES['file']);
                    if ($result['status'] === 'success') {
                        $data = $result['data'];
                        $unique = ['activity_code']; // adjust as needed
                        $insertResult = $bulkHelper->bulkInsert('activities', $data, $unique);
                        echo json_encode($insertResult);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    $data = $input;
                    $unique = ['activity_code'];
                    $insertResult = $bulkHelper->bulkInsert('activities', $data, $unique);
                    echo json_encode($insertResult);
                }
                exit;
            } elseif ($action === 'bulk_update') {
                $identifier = 'id';
                $result = $bulkHelper->bulkUpdate('activities', $input, $identifier);
                echo json_encode($result);
                exit;
            } elseif ($action === 'bulk_delete') {
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "DELETE FROM activities WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($ids);
                echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                exit;
            } elseif ($action === 'export') {
                $format = $_GET['format'] ?? 'csv';
                $query = "SELECT * FROM activities";
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                require_once __DIR__ . '/includes/ExportHelper.php';
                $exportHelper = new \App\API\Includes\ExportHelper();
                $exportHelper->export($rows, $format, 'activities_export');
                exit;
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