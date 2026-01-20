<?php
namespace App\Config;

use Exception;

// Debug mode (set to false in production)
define('DEBUG', true);

// Base URL Configuration (Update this with your actual domain) - MUST BE FIRST
// Detect if local or production
$isLocal = ($_SERVER['HTTP_HOST'] ?? 'localhost') === 'localhost' || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $isLocal ? 'http://127.0.0.1:8000' : 'https://kingsway.ac.ke';
define('BASE_URL', $baseUrl);

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
define('SCHOOL_NAME', 'Kingsway Preparatory School');
define('SCHOOL_CODE', 'KWPS');
define('CURRENT_YEAR', date('Y'));
define('CURRENT_TERM', ceil(date('n') / 3));

// School Contact Details
define('SCHOOL_ADDRESS', 'P.O Box 203-20203, Londiani, Kenya');
define('SCHOOL_PHONE', '+254-720-113030 / +254-720-113031');
define('SCHOOL_EMAIL', 'info@kingsway.ac.ke');
define('SCHOOL_PRINCIPAL_NAME', 'Mr Bett Junior');
define('SCHOOL_PRINCIPAL_TITLE', 'Headteacher');
define('SCHOOL_MOTTO', 'In God We Soar');
define('SCHOOL_LOGO_URL', BASE_URL . '/images/logo.jpg'); // School logo image URL

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

// Database Configuration - CI/Test Environment
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'testuser');
define('DB_PASS', getenv('DB_PASS') ?: 'testpass');
define('DB_NAME', getenv('DB_NAME') ?: 'KingsWayAcademy');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// JWT Configuration
define('JWT_SECRET', '51c47afc73a6f2cf1a052309d1f8a8bb4839d7bc7aaddb32cd8f26b2898aed23');
define('JWT_EXPIRY', 3600); // Token expiry in seconds (1 hour)
define('JWT_ISSUER', 'kingsway-prep-school');
define('JWT_AUDIENCE', 'kingsway-staff');

// System Version
define('SYSTEM_VERSION', '1.0.0');

// Email Configuration (disabled for CI)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'angisofttechnologies@gmail.com');
define('SMTP_PASSWORD', 'snhtcunelqtkujnp');
define('SMTP_FROM_EMAIL', 'angisofttechnologies@gmail.com'); // Must match authenticated account
define('SMTP_FROM_NAME', 'Kingsway Preparatory School');

// SMS Configuration (disabled for CI)
define('SMS_PROVIDER', 'africastalking'); // or 'twilio'
define('SMS_API_KEY', 'atsk_c5500c783227e742d2db31baf235dccfbce1ca1923ae3316026cdf8354c1e531e98ebf2c');
define('SMS_USERNAME', 'sandbox');