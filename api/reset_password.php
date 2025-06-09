<?php
namespace App\API;

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/includes/BaseAPI.php';
require_once __DIR__ . '/modules/auth/AuthAPI.php';


use App\API\Modules\auth\AuthAPI;
use \Exception;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    if (empty($token) || empty($newPassword)) {
        throw new Exception('Token and new password are required');
    }
    $auth = new AuthAPI();
    $result = $auth->completeReset(['token' => $token, 'new_password' => $newPassword]);
    echo json_encode($result);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}