<?php

namespace App\Config;
/**
 * Kingsway Academy - Development Environment Configuration
 * 
 * This file is loaded when APP_ENV=development or running on localhost
 */

// Debug mode - ENABLED for development
define('DEBUG', true);

// Base URL - Local development
define('BASE_URL', $_ENV['BASE_URL'] ?? 'http://localhost/Kingsway');

// File upload paths - Development (relative to project root)
define('UPLOAD_PATH', $_ENV['UPLOAD_PATH'] ?? __DIR__ . '/../uploads');
define('STUDENT_PHOTOS', UPLOAD_PATH . '/students');
define('STAFF_PHOTOS', UPLOAD_PATH . '/staff');
define('DOCUMENTS', UPLOAD_PATH . '/documents');

// Database Configuration - Development
define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'KingsWayAcademy');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);
define('DB_PASS', $_ENV['DB_PASS'] ?? 'admin123');

// JWT - Use secure secret from .env
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'dev_secret_key_change_this');
define('JWT_EXPIRY', $_ENV['JWT_EXPIRY'] ?? 3600);
define('JWT_ISSUER', $_ENV['JWT_ISSUER'] ?? 'kingsway-prep-school');
define('JWT_AUDIENCE', $_ENV['JWT_AUDIENCE'] ?? 'kingsway-staff');

// Email Configuration - Load from .env for security
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'mail.kingswaypreparatoryschool.sc.ke');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USERNAME', $_ENV['SMTP_USERNAME'] ?? 'info@kingswaypreparatoryschool.sc.ke');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'info@kingswaypreparatoryschool.sc.ke');
define('SMTP_PASSWORD', $_ENV['SMTP_PASSWORD'] ?? ''); 
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Kingsway Preparatory School');


// SMS Configuration - Load from .env
define('SMS_PROVIDER', $_ENV['SMS_PROVIDER'] ?? 'africastalking');
define('SMS_API_KEY', $_ENV['SMS_API_KEY'] ?? '');
define('SMS_USERNAME', $_ENV['SMS_USERNAME'] ?? 'sandbox');
define('SMS_APPNAME', $_ENV['SMS_APPNAME'] ?? 'Sandbox');
define('SMS_SENDER_ID', $_ENV['SMS_SENDER_ID'] ?? 'Kingsway Preparatory');
define('SMS_SHORTCODE', $_ENV['SMS_SHORTCODE'] ?? '20174');
define('SMS_WHATSAPP_NUMBER', $_ENV['SMS_WHATSAPP_NUMBER'] ?? '+254710398690');

// M-Pesa Configuration - Load from .env for security
define('MPESA_ENVIRONMENT', $_ENV['MPESA_ENVIRONMENT'] ?? 'sandbox');
define('MPESA_BASE_URL', MPESA_ENVIRONMENT === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke');
define('MPESA_CONSUMER_KEY', $_ENV['MPESA_CONSUMER_KEY'] ?? '');
define('MPESA_CONSUMER_SECRET', $_ENV['MPESA_CONSUMER_SECRET'] ?? '');
define('MPESA_SHORTCODE', $_ENV['MPESA_SHORTCODE'] ?? '');
define('MPESA_PASSKEY', $_ENV['MPESA_PASSKEY'] ?? '');
define('MPESA_INITIATOR_NAME', $_ENV['MPESA_INITIATOR_NAME'] ?? '');
define('MPESA_INITIATOR_PASSWORD', $_ENV['MPESA_INITIATOR_PASSWORD'] ?? '');

// M-Pesa Security Credential - Load from .env
define('MPESA_SECURITY_CREDENTIAL', $_ENV['MPESA_SECURITY_CREDENTIAL'] ?? '');

// KCB Bank Buni Configuration - Load from .env for security
define('KCB_ENVIRONMENT', $_ENV['KCB_ENVIRONMENT'] ?? 'sandbox');
define('KCB_BASE_URL', KCB_ENVIRONMENT === 'production'
    ? 'https://uat.buni.kcbgroup.com'
    : 'https://uat.buni.kcbgroup.com');
define('KCB_CONSUMER_KEY', $_ENV['KCB_CONSUMER_KEY'] ?? '');
define('KCB_CONSUMER_SECRET', $_ENV['KCB_CONSUMER_SECRET'] ?? '');
define("KCB_API_KEY", $_ENV['KCB_API_KEY'] ?? '');

// KCB Account Details - Load from .env
define('KCB_ORGANIZATION_REFERENCE', $_ENV['KCB_ORGANIZATION_REFERENCE'] ?? '');
define('KCB_CREDIT_ACCOUNT', $_ENV['KCB_CREDIT_ACCOUNT'] ?? '');
define('KCB_PUBLIC_KEY_PATH', __DIR__ . '/kcb_public_key.pem');
