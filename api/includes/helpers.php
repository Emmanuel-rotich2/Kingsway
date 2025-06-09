<?php
namespace App\API\Includes;

/**
 * Handle CORS headers
 */
function handleCORS() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
    
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }

    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        }
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
        }
        exit(0);
    }
}

/**
 * Log activity to database
 */
function logActivity($action, $description, $user_id = null, $entity_type = null, $entity_id = null) {
    $db = \App\Config\Database::getInstance()->getConnection();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $created_at = date('Y-m-d H:i:s');
    // Insert into DB
    $sql = "INSERT INTO system_logs (action, entity_type, entity_id, description, user_id, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$action, $entity_type, $entity_id, $description, $user_id, $ip_address, $user_agent, $created_at]);
    // Log to file
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . '/system_activity.log';
    $logEntry = json_encode([
        'timestamp' => $created_at,
        'action' => $action,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
        'description' => $description,
        'user_id' => $user_id,
        'ip_address' => $ip_address,
        'user_agent' => $user_agent
    ]) . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Log errors to a dedicated error log file
 */
function logError($description, $context = []) {
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) mkdir($logDir, 0755, true);
    $logFile = $logDir . '/errors.log';
    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'description' => $description,
        'context' => $context
    ];
    file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * Format API response
 */
function formatResponse($status, $data = null, $message = '', $httpCode = 200) {
    http_response_code($httpCode);
    return [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    if (is_string($data)) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}