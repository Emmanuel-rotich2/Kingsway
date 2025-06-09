<?php
namespace App\API;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/modules/auth/AuthAPI.php';

use App\API\Modules\auth\AuthAPI;
use Exception;

// Set JSON content type header
header('Content-Type: application/json');

try {
    $auth = new AuthAPI();
    
    // Get request data
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? '';

    // Handle CORS preflight request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        exit(0);
    }

    // Set CORS headers for actual request
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');

    switch ($action) {
        case 'login':
            $result = $auth->login($data);
            if ($result['status'] === 'success') {
                // Save user info and main role to session
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
                $user = $result['data']['user'];
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['roles'] = $user['roles'];
                $_SESSION['main_role'] = $user['roles'][0] ?? null;
                $_SESSION['permissions'] = $user['permissions'] ?? [];
            }
            echo json_encode($result);
            break;

        case 'reset-password':
            echo json_encode($auth->resetPassword($data));
            break;

        case 'verify-token':
            $token = $_GET['token'] ?? '';
            echo json_encode($auth->verifyResetToken($token));
            break;

        case 'complete-reset':
            echo json_encode($auth->completeReset($data));
            break;

        case 'change-password':
            echo json_encode($auth->changePassword($data));
            break;

        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid action'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred',
        'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
    ]);
}