<?php
namespace App\API\Includes;

require_once __DIR__ . '/BulkOperationsHelper.php';
require_once __DIR__ . '/ExportHelper.php';

class BulkCrudController {
    private $db;
    private $bulkHelper;
    private $exportHelper;

    public function __construct($db) {
        $this->db = $db;
        $this->bulkHelper = new BulkOperationsHelper($db);
        $this->exportHelper = new ExportHelper();
    }

    public function handle($table, $uniqueColumns = [], $identifier = 'id', $extra = []) {
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
            case 'POST':
                if ($action === 'bulk_insert') {
                    if (!empty($_FILES['file'])) {
                        $result = $this->bulkHelper->processUploadedFile($_FILES['file']);
                        if ($result['status'] === 'success') {
                            $data = $result['data'];
                            $insertResult = $this->bulkHelper->bulkInsert($table, $data, $uniqueColumns);
                            echo json_encode($insertResult);
                        } else {
                            echo json_encode($result);
                        }
                    } else {
                        $data = $input;
                        $insertResult = $this->bulkHelper->bulkInsert($table, $data, $uniqueColumns);
                        echo json_encode($insertResult);
                    }
                    exit;
                } elseif ($action === 'bulk_update') {
                    $result = $this->bulkHelper->bulkUpdate($table, $input, $identifier);
                    echo json_encode($result);
                    exit;
                } elseif ($action === 'bulk_delete') {
                    $ids = $input['ids'] ?? [];
                    if (empty($ids)) {
                        echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
                        exit;
                    }
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $sql = "DELETE FROM $table WHERE $identifier IN ($placeholders)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($ids);
                    echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                    exit;
                } elseif ($action === 'export') {
                    $format = $_GET['format'] ?? 'csv';
                    $columns = $input['columns'] ?? [];
                    $filters = $input['filters'] ?? [];
                    $query = "SELECT * FROM $table";
                    // Optionally add filters here
                    $stmt = $this->db->query($query);
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    $this->exportHelper->export($rows, $format, "{$table}_export", $columns);
                    exit;
                }
                // Optional: handle profile/doc upload
                elseif ($action === 'upload_profile_pic' && $id && isset($extra['profile_pic_column'])) {
                    if (!empty($_FILES['profile_pic'])) {
                        $targetDir = __DIR__ . "/../../uploads/$table/";
                        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                        $filename = "{$table}_" . $id . '_' . time() . '.' . pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetDir . $filename);
                        $stmt = $this->db->prepare("UPDATE $table SET {$extra['profile_pic_column']} = ? WHERE $identifier = ?");
                        $stmt->execute([$filename, $id]);
                        echo json_encode(['status' => 'success', 'filename' => $filename]);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
                    }
                    exit;
                }
                // Optional: handle document upload
                elseif ($action === 'upload_document' && $id && isset($extra['document_table'])) {
                    if (!empty($_FILES['document'])) {
                        $targetDir = __DIR__ . "/../../uploads/{$table}_docs/";
                        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
                        $filename = "{$table}doc_" . $id . '_' . time() . '.' . pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
                        move_uploaded_file($_FILES['document']['tmp_name'], $targetDir . $filename);
                        $stmt = $this->db->prepare("INSERT INTO {$extra['document_table']} ({$extra['document_ref_column']}, filename, uploaded_at) VALUES (?, ?, NOW())");
                        $stmt->execute([$id, $filename]);
                        echo json_encode(['status' => 'success', 'filename' => $filename]);
                    } else {
                        echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
                    }
                    exit;
                }
                break;
            default:
                echo json_encode(['status' => 'error', 'message' => 'Unsupported HTTP method']);
        }
    }
}
