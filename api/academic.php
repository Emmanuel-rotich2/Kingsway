<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/modules/academic/AcademicAPI.php';
require_once __DIR__ . '/includes/BulkOperationsHelper.php';

use App\API\Modules\academic\AcademicAPI;
use Exception;

header('Content-Type: application/json');

$academicApi = new AcademicAPI($db);
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
                echo json_encode($academicApi->list($_GET));
            } elseif ($action === 'view' && $id) {
                echo json_encode($academicApi->get($id));
            } elseif ($id && $action) {
                // Custom GET actions (teachers, classes, assessments)
                echo json_encode($academicApi->handleCustomGet($id, $action, $_GET));
            } elseif ($action === 'lesson-plans') {
                echo json_encode($academicApi->getLessonPlans($_GET));
            } elseif ($action === 'curriculum-units') {
                echo json_encode($academicApi->getCurriculumUnits($_GET));
            } elseif ($action === 'academic-terms') {
                echo json_encode($academicApi->getAcademicTerms($_GET));
            } elseif ($action === 'schemes-of-work') {
                echo json_encode($academicApi->getSchemeOfWork($_GET));
            } elseif ($action === 'lesson-observations') {
                echo json_encode($academicApi->getLessonObservations($_GET));
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid GET request']);
            }
            break;

        case 'POST':
            if ($action === 'add') {
                echo json_encode($academicApi->create($input));
            } elseif ($action === 'update' && $id) {
                echo json_encode($academicApi->update($id, $input));
            } elseif ($action === 'delete' && $id) {
                echo json_encode($academicApi->delete($id));
            } elseif ($id && $action) {
                // Custom POST actions (assign-teacher, create-assessment)
                echo json_encode($academicApi->handleCustomPost($id, $action, $input));
            } elseif ($action === 'create-lesson-plan') {
                echo json_encode($academicApi->createLessonPlan($input));
            } elseif ($action === 'create-curriculum-unit') {
                echo json_encode($academicApi->createCurriculumUnit($input));
            } elseif ($action === 'create-academic-term') {
                echo json_encode($academicApi->createAcademicTerm($input));
            } elseif ($action === 'create-scheme-of-work') {
                echo json_encode($academicApi->createSchemeOfWork($input));
            } elseif ($action === 'create-lesson-observation') {
                echo json_encode($academicApi->createLessonObservation($input));
            } elseif ($action === 'bulk_insert') {
                if (!empty($_FILES['file'])) {
                    $result = $bulkHelper->processUploadedFile($_FILES['file']);
                    if ($result['status'] === 'success') {
                        $data = $result['data'];
                        $unique = ['code']; // adjust as needed
                        $insertResult = $bulkHelper->bulkInsert('academic', $data, $unique);
                        echo json_encode($insertResult);
                    } else {
                        echo json_encode($result);
                    }
                } else {
                    $data = $input;
                    $unique = ['code'];
                    $insertResult = $bulkHelper->bulkInsert('academic', $data, $unique);
                    echo json_encode($insertResult);
                }
                exit;
            } elseif ($action === 'bulk_update') {
                $identifier = 'id';
                $result = $bulkHelper->bulkUpdate('academic', $input, $identifier);
                echo json_encode($result);
                exit;
            } elseif ($action === 'bulk_delete') {
                $ids = $input['ids'] ?? [];
                if (empty($ids)) {
                    echo json_encode(['status' => 'error', 'message' => 'No IDs provided']);
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $sql = "DELETE FROM academic WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($ids);
                echo json_encode(['status' => 'success', 'deleted' => $stmt->rowCount()]);
                exit;
            } elseif ($action === 'export') {
                $format = $_GET['format'] ?? 'csv';
                $query = "SELECT * FROM academic";
                $stmt = $db->query($query);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                require_once __DIR__ . '/includes/ExportHelper.php';
                $exportHelper = new \App\API\Includes\ExportHelper();
                $exportHelper->export($rows, $format, 'academic_export');
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