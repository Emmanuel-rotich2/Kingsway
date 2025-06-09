<?php
namespace App\Config;

use Exception;

// Debug mode (set to false in production)
define('DEBUG', true);

// File upload paths
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('STUDENT_PHOTOS', UPLOAD_PATH . '/students');
define('STAFF_PHOTOS', UPLOAD_PATH . '/staff');
define('DOCUMENTS', UPLOAD_PATH . '/documents');

// Create upload directories if they don't exist
$directories = [UPLOAD_PATH, STUDENT_PHOTOS, STAFF_PHOTOS, DOCUMENTS];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// System settings
define('SCHOOL_NAME', 'Kingsway Academy');
define('SCHOOL_CODE', 'KWA');
define('CURRENT_YEAR', date('Y'));
define('CURRENT_TERM', ceil(date('n')/3));

// Pagination defaults
define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// Error reporting
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Time zone
date_default_timezone_set('Africa/Nairobi');

// API settings
define('API_VERSION', '1.0');
define('API_BASE_URL', '/api/v1');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'KingsWayAcademy');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

// JWT Configuration
define('JWT_SECRET', '51c47afc73a6f2cf1a052309d1f8a8bb4839d7bc7aaddb32cd8f26b2898aed23');
define('JWT_EXPIRY', 3600); // Token expiry in seconds (1 hour)
define('JWT_ISSUER', 'kingsway-prep-school');
define('JWT_AUDIENCE', 'kingsway-staff');

// CORS settings
define('ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:8080'
]);

// System Version
define('SYSTEM_VERSION', '1.0.0');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'angisofttechnologies@gmail.com');
define('SMTP_PASSWORD', 'snhtcunelqtkujnp');
define('SMTP_FROM_EMAIL', 'noreply@kingsway.ac.ke');
define('SMTP_FROM_NAME', 'Kingsway Preparatory School');

// SMS Configuration
define('SMS_PROVIDER', 'africastalking'); // or 'twilio'
define('SMS_API_KEY', 'your-api-key');
define('SMS_USERNAME', 'your-username');
define('SMS_ACCOUNT_SID', 'your-account-sid'); // For Twilio
define('SMS_AUTH_TOKEN', 'your-auth-token'); // For Twilio
define('SMS_FROM_NUMBER', 'your-from-number');

// Utility Functions

/**
 * Generate a secure random string
 * @param int $length Length of the string to generate
 * @return string The generated string
 */
function generateSecureString($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Validate and sanitize input
 * @param mixed $data The input data to sanitize
 * @return mixed The sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Handle CORS headers
 */
function handleCORS() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit();
        }
    }
}

/**
 * Format API response
 * @param string $status Success or error
 * @param mixed $data Response data
 * @param string $message Response message
 * @param int $code HTTP status code
 * @return array Formatted response
 */
function formatResponse($status, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    return [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];
}

/**
 * Log system activity
 * @param string $action The action performed
 * @param string $description Description of the action
 * @param int $userId ID of the user performing the action
 */
function logActivity($action, $description, $userId = null) {
    try {
        $db = \App\Config\Database::getInstance();
        $db->query(
            "INSERT INTO system_logs (action, description, user_id, ip_address) VALUES (?, ?, ?, ?)",
            [$action, $description, $userId, $_SERVER['REMOTE_ADDR']]
        );
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
} 