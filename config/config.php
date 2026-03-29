<?php

// Production-only settings and environment variables

// Disable debug mode
define('DEBUG', false);

// Base URL
define('BASE_URL', env('APP_URL', 'https://www.kingswaypreparatoryschool.sc.ke'));

// Upload path
define('UPLOAD_PATH', env('UPLOAD_PATH', '/home/kingswa4/uploads'));

// Database settings

define('DB_HOST', env('DB_HOST', 'da28.host-ww.net'));
define('DB_USER', env('DB_USER', 'kingswa4_root'));
define('DB_NAME', env('DB_NAME', 'kingswa4_kingswayacademy'));
define('DB_PORT', env('DB_PORT', 3306));
define('DB_PASS', env('DB_PASS', 'admin123'));

// SMTP settings

define('SMTP_HOST', env('SMTP_HOST', 'mail.kingswaypreparatoryschool.sc.ke'));
define('SMTP_PORT', env('SMTP_PORT', 587));
define('SMTP_USERNAME', env('SMTP_USERNAME', 'info@kingswaypreparatoryschool.sc.ke'));
define('SMTP_FROM_EMAIL', env('SMTP_FROM_EMAIL', 'info@kingswaypreparatoryschool.sc.ke'));
define('SMTP_PASSWORD', env('SMTP_PASSWORD', '')); // Use environment variable for SMTP_PASSWORD

// Allowed origins
$allowed_origins = [env('APP_URL')];

// Please remove localhost detection and localhost origins.

// All other constants remain unchanged

?>