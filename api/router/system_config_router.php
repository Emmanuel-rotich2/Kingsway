<?php
/**
 * System Configuration Router
 * 
 * Entry point for /api/system/* endpoints
 * 
 * @package App\API\Router
 * @since 2025-12-28
 */

require_once dirname(__DIR__) . '/controllers/SystemConfigController.php';

use App\API\Controllers\SystemConfigController;

/**
 * Handle system configuration requests
 * 
 * @param string $method HTTP method
 * @param array $params Query parameters
 * @param array $body Request body
 * @param array $user Authenticated user info (user_id, role_id)
 * @return array Response
 */
function handleSystemConfigRequest(string $method, array $params, array $body, array $user): array
{
    // Extract path after /api/system/
    $route = $_GET['route'] ?? '';
    $pathParts = array_values(array_filter(explode('/', $route)));

    // Remove 'system' prefix if present
    if (!empty($pathParts) && $pathParts[0] === 'system') {
        array_shift($pathParts);
    }

    $controller = new SystemConfigController(
        $user['user_id'] ?? null,
        $user['role_id'] ?? null
    );

    $response = $controller->handleRequest($method, $pathParts, $params, $body);

    // Set HTTP response code
    http_response_code($response['status'] ?? 200);

    return $response['body'];
}

/**
 * Parse raw input for body
 */
function getRequestBody(): array
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return $_POST;
    }

    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Get authenticated user from session/JWT
 */
function getAuthenticatedUser(): array
{
    // Check session first
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['user_id']) && isset($_SESSION['role_id'])) {
        return [
            'user_id' => (int) $_SESSION['user_id'],
            'role_id' => (int) $_SESSION['role_id']
        ];
    }

    // TODO: Check JWT token if session not available
    // This would integrate with existing auth middleware

    return [
        'user_id' => null,
        'role_id' => null
    ];
}

// Main execution when accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');

    $method = $_SERVER['REQUEST_METHOD'];
    $params = $_GET;
    $body = getRequestBody();
    $user = getAuthenticatedUser();

    // Verify authentication
    if ($user['user_id'] === null) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required'
        ]);
        exit;
    }

    $response = handleSystemConfigRequest($method, $params, $body, $user);
    echo json_encode($response);
}
