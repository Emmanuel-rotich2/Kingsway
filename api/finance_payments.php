<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use App\API\Modules\finance\FinancePaymentsAPI;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

$api = new FinancePaymentsAPI();

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
        if ($action === 'bank_callback') {
            echo json_encode($api->recordBankTransaction($input));
        } elseif ($action === 'mpesa_callback') {
            echo json_encode($api->recordMpesaTransaction($input));
        } elseif ($action === 'cash_payment') {
            echo json_encode($api->recordCashPayment($input));
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Invalid POST action']);
        }
        break;
    case 'GET':
        if ($action === 'student_transactions' && isset($_GET['student_id'])) {
            echo json_encode($api->getStudentTransactions($_GET['student_id']));
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Invalid GET action']);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
