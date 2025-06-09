<?php
namespace App\API\Includes;

use App\Config\Database;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;
use PDO;

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/helpers.php';

function authenticate() {
    try {
        // Get token from header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';

        if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'No token provided'
            ]);
            exit;
        }

        $token = $matches[1];

        // Verify token
        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid token'
            ]);
            exit;
        }

        // Check if token is expired
        if ($decoded->exp < time()) {
            http_response_code(401);
            echo json_encode([
                'status' => 'error',
                'message' => 'Token has expired'
            ]);
            exit;
        }

        // Store user data in request
        $_REQUEST['user'] = [
            'id' => $decoded->user_id,
            'username' => $decoded->username,
            'role' => $decoded->role,
            'permissions' => $decoded->permissions
        ];

        return true;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Authentication error',
            'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
        ]);
        exit;
    }
}

function authorize($requiredPermissions = []) {
    try {
        if (!isset($_REQUEST['user'])) {
            throw new Exception('User not authenticated');
        }

        $user = $_REQUEST['user'];

        // If no specific permissions required, just check if user is authenticated
        if (empty($requiredPermissions)) {
            return true;
        }

        // Check if user has required permissions
        $hasPermission = false;
        foreach ($requiredPermissions as $permission) {
            if (in_array($permission, $user['permissions'])) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Insufficient permissions'
            ]);
            exit;
        }

        return true;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Authorization error',
            'debug' => getenv('APP_DEBUG') ? $e->getMessage() : null
        ]);
        exit;
    }
}

// Handle CORS for all API requests
handleCORS();