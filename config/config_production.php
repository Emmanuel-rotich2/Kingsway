<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

/**
 * Kingsway Academy
 * Production environment configuration.
 *
 * Loaded when APP_ENV=production.
 */

define('DEBUG', false);

/*
|--------------------------------------------------------------------------
| Application URL and storage root
|--------------------------------------------------------------------------
*/

define(
    'BASE_URL',
    rtrim(
        (string) (
            $_ENV['BASE_URL']
            ?? 'https://www.kingswaypreparatoryschool.sc.ke'
        ),
        '/'
    )
);

define(
    'UPLOAD_PATH',
    rtrim(
        (string) (
            $_ENV['UPLOAD_PATH']
            ?? '/home/kingswa4/uploads'
        ),
        '/\\'
    )
);

require_once __DIR__ . '/upload_paths.php';

/*
|--------------------------------------------------------------------------
| School identity
|--------------------------------------------------------------------------
*/

define('SCHOOL_NAME', 'Kingsway Preparatory School');
define('SCHOOL_CODE', 'KWPS');
define('SCHOOL_ADDRESS', 'P.O Box 203-20203, Londiani, Kenya');
define('SCHOOL_PHONE', '+254-720-113030 / +254-720-113031');
define('SCHOOL_EMAIL', 'info@kingswaypreparatoryschool.sc.ke');
define('SCHOOL_PRINCIPAL_NAME', 'Mr Bett Junior');
define('SCHOOL_PRINCIPAL_TITLE', 'Headteacher');
define('SCHOOL_MOTTO', 'In God We Soar');

define('CURRENT_YEAR', date('Y'));
define('CURRENT_TERM', (int) ceil((int) date('n') / 3));

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'kingswa4_root');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'kingswa4_kingswayacademy');
define('DB_PORT', (int) ($_ENV['DB_PORT'] ?? 3306));
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$jwtSecret = trim((string) ($_ENV['JWT_SECRET'] ?? ''));

if ($jwtSecret === '') {
    throw new RuntimeException(
        'JWT_SECRET must be configured in production.'
    );
}

define('JWT_SECRET', $jwtSecret);

define(
    'JWT_EXPIRY',
    (int) ($_ENV['JWT_EXPIRY'] ?? 3600)
);

define(
    'JWT_ISSUER',
    $_ENV['JWT_ISSUER'] ?? 'kingsway-prep-school'
);

define(
    'JWT_AUDIENCE',
    $_ENV['JWT_AUDIENCE'] ?? 'kingsway-staff'
);


$authIdleTimeoutSeconds = max(
    300,
    (int) ($_ENV['AUTH_IDLE_TIMEOUT_SECONDS'] ?? 1800)
);

define(
    'AUTH_IDLE_TIMEOUT_SECONDS',
    $authIdleTimeoutSeconds
);

$authRefreshWindowSeconds = max(
    60,
    min(
        max(60, JWT_EXPIRY - 60),
        (int) ($_ENV['AUTH_REFRESH_WINDOW_SECONDS'] ?? 600)
    )
);

define(
    'AUTH_REFRESH_WINDOW_SECONDS',
    $authRefreshWindowSeconds
);

define(
    'AUTH_SESSION_MONITOR_INTERVAL_SECONDS',
    max(
        15,
        (int) (
            $_ENV['AUTH_SESSION_MONITOR_INTERVAL_SECONDS']
            ?? 30
        )
    )
);

/*
|--------------------------------------------------------------------------
| Email
|--------------------------------------------------------------------------
*/

define(
    'SMTP_HOST',
    $_ENV['SMTP_HOST'] ?? 'mail.kingswaypreparatoryschool.sc.ke'
);

define(
    'SMTP_PORT',
    (int) ($_ENV['SMTP_PORT'] ?? 587)
);

define(
    'SMTP_USERNAME',
    $_ENV['SMTP_USERNAME']
        ?? 'info@kingswaypreparatoryschool.sc.ke'
);

define(
    'SMTP_FROM_EMAIL',
    $_ENV['SMTP_FROM_EMAIL']
        ?? 'info@kingswaypreparatoryschool.sc.ke'
);

define(
    'SMTP_PASSWORD',
    $_ENV['SMTP_PASSWORD'] ?? ''
);

define(
    'SMTP_FROM_NAME',
    $_ENV['SMTP_FROM_NAME']
        ?? 'Kingsway Preparatory School'
);

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
*/

define(
    'SMS_PROVIDER',
    $_ENV['SMS_PROVIDER'] ?? 'africastalking'
);

define('SMS_API_KEY', $_ENV['SMS_API_KEY'] ?? '');
define('SMS_USERNAME', $_ENV['SMS_USERNAME'] ?? '');
define('SMS_APPNAME', $_ENV['SMS_APPNAME'] ?? 'Kingsway');

define(
    'SMS_SENDER_ID',
    $_ENV['SMS_SENDER_ID'] ?? 'Kingsway Preparatory'
);

define(
    'SMS_SHORTCODE',
    $_ENV['SMS_SHORTCODE'] ?? ''
);

define(
    'SMS_WHATSAPP_NUMBER',
    $_ENV['SMS_WHATSAPP_NUMBER'] ?? '+254710398690'
);

/*
|--------------------------------------------------------------------------
| M-Pesa
|--------------------------------------------------------------------------
*/

define(
    'MPESA_ENVIRONMENT',
    $_ENV['MPESA_ENVIRONMENT'] ?? 'production'
);

define(
    'MPESA_BASE_URL',
    MPESA_ENVIRONMENT === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke'
);

define(
    'MPESA_CONSUMER_KEY',
    $_ENV['MPESA_CONSUMER_KEY'] ?? ''
);

define(
    'MPESA_CONSUMER_SECRET',
    $_ENV['MPESA_CONSUMER_SECRET'] ?? ''
);

define(
    'MPESA_SHORTCODE',
    $_ENV['MPESA_SHORTCODE'] ?? ''
);

define(
    'MPESA_PASSKEY',
    $_ENV['MPESA_PASSKEY'] ?? ''
);

define(
    'MPESA_INITIATOR_NAME',
    $_ENV['MPESA_INITIATOR_NAME'] ?? ''
);

define(
    'MPESA_INITIATOR_PASSWORD',
    $_ENV['MPESA_INITIATOR_PASSWORD'] ?? ''
);

define(
    'MPESA_SECURITY_CREDENTIAL',
    $_ENV['MPESA_SECURITY_CREDENTIAL'] ?? ''
);

/*
|--------------------------------------------------------------------------
| KCB Buni
|--------------------------------------------------------------------------
*/

define(
    'KCB_ENVIRONMENT',
    $_ENV['KCB_ENVIRONMENT'] ?? 'production'
);

define(
    'KCB_BASE_URL',
    $_ENV['KCB_BASE_URL']
        ?? 'https://uat.buni.kcbgroup.com'
);

define(
    'KCB_CONSUMER_KEY',
    $_ENV['KCB_CONSUMER_KEY'] ?? ''
);

define(
    'KCB_CONSUMER_SECRET',
    $_ENV['KCB_CONSUMER_SECRET'] ?? ''
);

define(
    'KCB_API_KEY',
    $_ENV['KCB_API_KEY'] ?? ''
);

define(
    'KCB_ORGANIZATION_REFERENCE',
    $_ENV['KCB_ORGANIZATION_REFERENCE'] ?? ''
);

define(
    'KCB_CREDIT_ACCOUNT',
    $_ENV['KCB_CREDIT_ACCOUNT'] ?? ''
);

define(
    'KCB_PUBLIC_KEY_PATH',
    $_ENV['KCB_PUBLIC_KEY_PATH']
        ?? __DIR__ . '/kcb_public_key.pem'
);

/*
|--------------------------------------------------------------------------
| Application defaults
|--------------------------------------------------------------------------
*/

define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Lax');

error_reporting(0);
ini_set('display_errors', '0');
