<?php

namespace App\Config;
/**
 * Kingsway Academy - Production Environment Configuration
 * 
 * This file is loaded when APP_ENV=production
 */

// Debug mode - DISABLED for production
define('DEBUG', false);

// Base URL - Production domain
define('BASE_URL', $_ENV['BASE_URL'] ?? 'https://kingswaypreparatoryschool.sc.ke');

// File upload paths - Production absolute paths
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? '/home/kingswa4/uploads');
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

// Database Configuration - Production (load from .env)
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'kingswa4_root');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'kingswa4_kingswayacademy');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// JWT Configuration - Production (MUST use secure secret from .env)
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '51c47afc73a6f2cf1a052309d1f8a8bb4839d7bc7aaddb32cd8f26b2898aed23');
define('JWT_EXPIRY', $_ENV['JWT_EXPIRY'] ?? 3600);
define('JWT_ISSUER', $_ENV['JWT_ISSUER'] ?? 'kingsway-prep-school');
define('JWT_AUDIENCE', $_ENV['JWT_AUDIENCE'] ?? 'kingsway-staff');// Email Configuration - Production (load from .env)
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'mail.kingswaypreparatoryschool.sc.ke');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? 'info@kingswaypreparatoryschool.sc.ke');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'info@kingswaypreparatoryschool.sc.ke');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? '');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Kingsway Preparatory School');

// SMS Configuration - Production (load from .env)
define('SMS_PROVIDER', $_ENV['SMS_PROVIDER'] ?? 'africastalking');
define('SMS_API_KEY', $_ENV['SMS_API_KEY'] ?? '');
define('SMS_USERNAME', $_ENV['SMS_USERNAME'] ?? 'sandbox');
define('SMS_APPNAME', $_ENV['SMS_APPNAME'] ?? 'Sandbox');
define('SMS_SENDER_ID', $_ENV['SMS_SENDER_ID'] ?? 'Kingsway Preparatory');
define('SMS_SHORTCODE', $_ENV['SMS_SHORTCODE'] ?? '20174');
define('SMS_WHATSAPP_NUMBER', $_ENV['SMS_WHATSAPP_NUMBER'] ?? '+254710398690');

// M-Pesa Configuration - Production (MUST load from .env)
define('MPESA_ENVIRONMENT', $_ENV['MPESA_ENVIRONMENT'] ?? 'production');
define('MPESA_BASE_URL', MPESA_ENVIRONMENT === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke');
define('MPESA_CONSUMER_KEY', $_ENV['MPESA_CONSUMER_KEY'] ?? '');
define('MPESA_CONSUMER_SECRET', $_ENV['MPESA_CONSUMER_SECRET'] ?? '');
define('MPESA_SHORTCODE', $_ENV['MPESA_SHORTCODE'] ?? '');
define('MPESA_PASSKEY', $_ENV['MPESA_PASSKEY'] ?? '');
define('MPESA_INITIATOR_NAME', $_ENV['MPESA_INITIATOR_NAME'] ?? '');
define('MPESA_INITIATOR_PASSWORD', $_ENV['MPESA_INITIATOR_PASSWORD'] ?? '');

// M-Pesa Security Credential - Production (MUST load from .env)
define('MPESA_SECURITY_CREDENTIAL', $_ENV['MPESA_SECURITY_CREDENTIAL'] ?? '');

// KCB Bank Buni Configuration - Production (load from .env)
define('KCB_ENVIRONMENT', $_ENV['KCB_ENVIRONMENT'] ?? 'production');
define('KCB_BASE_URL', KCB_ENVIRONMENT === 'production'
    ? 'https://uat.buni.kcbgroup.com'
    : 'https://uat.buni.kcbgroup.com');
define('KCB_CONSUMER_KEY', $_ENV['KCB_CONSUMER_KEY'] ?? '');
define('KCB_CONSUMER_SECRET', $_ENV['KCB_CONSUMER_SECRET'] ?? '');
define('KCB_API_KEY', $_ENV['KCB_API_KEY'] ?? '');

// KCB Account Details - Production (load from .env)
define('KCB_ORGANIZATION_REFERENCE', $_ENV['KCB_ORGANIZATION_REFERENCE'] ?? '');
define('KCB_CREDIT_ACCOUNT', $_ENV['KCB_CREDIT_ACCOUNT'] ?? '');
define('KCB_PUBLIC_KEY_PATH', __DIR__ . '/kcb_public_key.pem');
