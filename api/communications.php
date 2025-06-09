<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/BulkOperationsHelper.php';

use App\API\Modules\communications\CommunicationsAPI;
use Exception;

header('Content-Type: application/json');

$communicationsApi = new CommunicationsAPI();
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
                echo json_encode($communicationsApi->list($_GET));
            } elseif ($action === 'view' && $id) {
                echo json_encode($communicationsApi->get($id));
            } elseif ($action === 'announcements') {
                echo json_encode($communicationsApi->getAnnouncements($_GET));
            } elseif ($action === 'notifications') {
                echo json_encode($communicationsApi->getNotifications($_GET));
            } elseif ($action === 'templates') {
                echo json_encode($communicationsApi->getTemplates($_GET['type'] ?? null));
            } elseif ($action === 'groups') {
                echo json_encode($communicationsApi->getGroups());
            } elseif ($action === 'sms-templates') {
                echo json_encode($communicationsApi->getSMSTemplates());
            } elseif ($action === 'email-templates') {
                echo json_encode($communicationsApi->getEmailTemplates());
            } elseif ($action === 'sms-config') {
                echo json_encode($communicationsApi->getSMSConfig());
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;

        case 'POST':
            if ($action === 'add') {
                echo json_encode($communicationsApi->create($input));
            } elseif ($action === 'update' && $id) {
                echo json_encode($communicationsApi->update($id, $input));
            } elseif ($action === 'delete' && $id) {
                echo json_encode($communicationsApi->delete($id));
            } elseif ($action === 'send-announcement') {
                echo json_encode($communicationsApi->sendAnnouncement($input));
            } elseif ($action === 'send-notification') {
                echo json_encode($communicationsApi->sendNotification($input));
            } elseif ($action === 'send-bulk-sms') {
                echo json_encode($communicationsApi->sendBulkSMS($input));
            } elseif ($action === 'send-bulk-email') {
                echo json_encode($communicationsApi->sendBulkEmail($input));
            } elseif ($action === 'create-template') {
                echo json_encode($communicationsApi->createTemplate($input));
            } elseif ($action === 'create-group') {
                echo json_encode($communicationsApi->createGroup($input));
            } elseif ($action === 'create-sms-template') {
                echo json_encode($communicationsApi->createSMSTemplate($input));
            } elseif ($action === 'create-email-template') {
                echo json_encode($communicationsApi->createEmailTemplate($input));
            } elseif ($action === 'update-sms-config') {
                echo json_encode($communicationsApi->updateSMSConfig($input));
            } elseif ($action === 'bulk_insert') {
                if (!empty($_FILES['file'])) {
                    $result = $bulkHelper->processUploadedFile($_FILES['file']);
                    if ($result['status'] === 'success') {
                        $data = $result['data'];
                        $unique = ['message_id']; // adjust as needed
                        $insertResult = $bulkHelper->bulkInsert('communications', $data, $unique);
                        echo json_encode($insertResult);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    $data = $input;
                    $unique = ['message_id'];
                    $insertResult = $bulkHelper->bulkInsert('communications', $data, $unique);
                    echo json_encode($insertResult);
                }
                exit;
            } elseif ($action === 'bulk_update') {
                $identifier = 'id';
                $result = $bulkHelper->bulkUpdate('communications', $input, $identifier);
                echo json_encode($result);
                exit;
            } elseif ($action === 'bulk_delete') {
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "DELETE FROM communications WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($ids);
                echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                exit;
            } elseif ($action === 'export') {
                $format = $_GET['format'] ?? 'csv';
                $query = "SELECT * FROM communications";
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                require_once __DIR__ . '/includes/ExportHelper.php';
                $exportHelper = new \App\API\Includes\ExportHelper();
                $exportHelper->export($rows, $format, 'communications_export');
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