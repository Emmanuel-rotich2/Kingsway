<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Kingsway Preparatory School
 * Development environment configuration.
 *
 * Loaded when APP_ENV=development or when running on localhost.
 */

define('DEBUG', true);

define('APP_BASE_PATH', __DIR__ . '/..');

/*
|--------------------------------------------------------------------------
| Application URL and storage root
|--------------------------------------------------------------------------
*/

define(
    'BASE_URL',
    rtrim(
        (string) ($_ENV['BASE_URL'] ?? 'http://localhost/Kingsway'),
        '/'
    )
);

define(
    'UPLOAD_PATH',
    rtrim(
        (string) (
            $_ENV['UPLOAD_PATH']
            ?? dirname(__DIR__) . '/uploads'
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

// SCHOOL_* compatibility constants are populated from the database below.

/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

define('DB_HOST', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'KingsWayAcademy');
define('DB_PORT', (int) ($_ENV['DB_PORT'] ?? 3306));
// WARNING: This default is insecure and MUST be overridden in .env for any non-development environment.
define('DB_PASS', $_ENV['DB_PASS'] ?? 'CHANGE_ME_IN_ENV_FILE');

// Auxiliary namespace schemas (roadmap §4.1). Optional overrides for local
// environments that use renamed/prefixed schemas; defaults match ConnectionManager.
define('DB_BUFFERS_NAME', trim((string) ($_ENV['DB_BUFFERS_NAME'] ?? '')) ?: 'KingsWayBuffers');
define('DB_READS_NAME', trim((string) ($_ENV['DB_READS_NAME'] ?? '')) ?: 'KingsWayReads');
define('DB_LOGS_NAME', trim((string) ($_ENV['DB_LOGS_NAME'] ?? '')) ?: 'KingsWayLogs');

/*
|--------------------------------------------------------------------------
| Academic year and term — read from database, fall back to calendar
|--------------------------------------------------------------------------
|
| NOTE: This creates a second PDO connection outside the Database singleton
| (database/Database.php). Ideally use Database::getInstance()->getConnection()
| after Config init completes. The singleton in Database.php already guards
| against duplicate connections per request.
|
*/

try {
    $_db = new \PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC]
    );
    $_row = $_db->query("SELECT id FROM academic_years WHERE is_current = 1 ORDER BY id DESC LIMIT 1")->fetch();
    define('CURRENT_YEAR', $_row ? (int) $_row['id'] : (int) date('Y'));
    $_row2 = $_db->query(
        "SELECT ayt.id
           FROM academic_year_terms ayt
           JOIN academic_years ay ON ay.id = ayt.academic_year_id
          WHERE ay.is_current = 1 AND ayt.status = 'current'
          LIMIT 1"
    )->fetch();
    define('CURRENT_TERM', $_row2 ? (int) $_row2['id'] : (int) ceil((int) date('n') / 3));
    loadSchoolIdentity($_db);
    $_db = null;
} catch (\Exception $_e) {
    define('CURRENT_YEAR', (int) date('Y'));
    define('CURRENT_TERM', (int) ceil((int) date('n') / 3));
    loadSchoolIdentity(null);
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

$jwtSecret = trim((string) ($_ENV['JWT_SECRET'] ?? ''));
if (strlen($jwtSecret) < 64) {
    throw new \RuntimeException('JWT_SECRET must be configured with at least 64 characters in every environment.');
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
$tfaEncryptionKey = trim((string) ($_ENV['TFA_ENCRYPTION_KEY'] ?? ''));
if (strlen($tfaEncryptionKey) < 64 || !ctype_xdigit($tfaEncryptionKey)) {
    throw new \RuntimeException('TFA_ENCRYPTION_KEY must be a hexadecimal key of at least 64 characters in every environment.');
}
define('TFA_ENCRYPTION_KEY', $tfaEncryptionKey);
define('PASSKEY_RP_ID', $_ENV['PASSKEY_RP_ID'] ?? 'localhost');

// Backs the unguessable slugs that secure role-scoped real-time buffer files.
$realtimeBufferSecret = trim((string) ($_ENV['REALTIME_BUFFER_SECRET'] ?? ''));
if (strlen($realtimeBufferSecret) < 64) {
    throw new \RuntimeException('REALTIME_BUFFER_SECRET must contain at least 64 characters in every environment.');
}
define('REALTIME_BUFFER_SECRET', $realtimeBufferSecret);


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
define('SMS_USERNAME', $_ENV['SMS_USERNAME'] ?? 'sandbox');
define('SMS_APPNAME', $_ENV['SMS_APPNAME'] ?? 'Sandbox');

define(
    'SMS_SENDER_ID',
    $_ENV['SMS_SENDER_ID'] ?? 'Kingsway Preparatory'
);

define(
    'SMS_SHORTCODE',
    $_ENV['SMS_SHORTCODE'] ?? '20174'
);

define(
    'SMS_WHATSAPP_NUMBER',
    $_ENV['SMS_WHATSAPP_NUMBER'] ?? '+254710398690'
);

define('SMS_WHATSAPP_API_URL', $_ENV['SMS_WHATSAPP_API_URL'] ?? 'https://chat.africastalking.com');
define('COMMUNICATION_WEBHOOK_SECRET', $_ENV['COMMUNICATION_WEBHOOK_SECRET'] ?? '');
define('AFRICASTALKING_WEBHOOK_TOKEN', $_ENV['AFRICASTALKING_WEBHOOK_TOKEN'] ?? '');
define('WHATSAPP_2FA_TEMPLATE_ID', $_ENV['WHATSAPP_2FA_TEMPLATE_ID'] ?? '');
define('COMMUNICATION_WORKER_SECRET', $_ENV['COMMUNICATION_WORKER_SECRET'] ?? '');
define('ATTENDANCE_WORKER_SECRET', $_ENV['ATTENDANCE_WORKER_SECRET'] ?? '');
define('ATTENDANCE_GATE_SECRET', $_ENV['ATTENDANCE_GATE_SECRET'] ?? '');
define('TWILIO_ACCOUNT_SID', $_ENV['TWILIO_ACCOUNT_SID'] ?? '');
define('TWILIO_AUTH_TOKEN', $_ENV['TWILIO_AUTH_TOKEN'] ?? '');
define('TWILIO_FROM', $_ENV['TWILIO_FROM'] ?? '');

/*
|--------------------------------------------------------------------------
| M-Pesa
|--------------------------------------------------------------------------
*/

define(
    'MPESA_ENVIRONMENT',
    $_ENV['MPESA_ENVIRONMENT'] ?? 'sandbox'
);


define(
    'MPESA_BASE_URL',
    MPESA_ENVIRONMENT === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke'
);

// Callback URLs sent to Safaricom must be publicly reachable, so they cannot
// reuse the local BASE_URL in development/sandbox. Override per environment;
// falls back to BASE_URL (kept for sandbox stubs and production-like setups).
define(
    'MPESA_CALLBACK_BASE_URL',
    rtrim(
        (string) ($_ENV['MPESA_CALLBACK_BASE_URL'] ?? BASE_URL),
        '/'
    )
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
define('MPESA_CERTIFICATE_PATH', $_ENV['MPESA_CERTIFICATE_PATH'] ?? __DIR__ . '/mpesa_sandbox_cert.cer');

/*
|--------------------------------------------------------------------------
| KCB Buni
|--------------------------------------------------------------------------
*/

define(
    'KCB_ENVIRONMENT',
    $_ENV['KCB_ENVIRONMENT'] ?? 'sandbox'
);

define(
    'KCB_BASE_URL',
    $_ENV['KCB_BASE_URL']
        ?? (KCB_ENVIRONMENT === 'sandbox'
            ? 'https://uat.buni.kcbgroup.com'
            : 'https://api.buni.kcbgroup.com')
);

define(
    'KCB_TOKEN_ENDPOINT',
    $_ENV['KCB_TOKEN_ENDPOINT']
        ?? ($_ENV['TOKEN_ENDPOINT'] ?? 'https://accounts.buni.kcbgroup.com/oauth2/token')
);

define(
    'KCB_REVOKE_ENDPOINT',
    $_ENV['KCB_REVOKE_ENDPOINT']
        ?? ($_ENV['REVOKE_ENDPOINT'] ?? 'https://accounts.buni.kcbgroup.com/oauth2/revoke')
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
    'KCB_DEBIT_ACCOUNT',
    $_ENV['KCB_DEBIT_ACCOUNT'] ?? ''
);

define(
    'KCB_COLLECTION_ACCOUNT_IDENTIFIER',
    $_ENV['KCB_COLLECTION_ACCOUNT_IDENTIFIER'] ?? ''
);

define(
    'KCB_CALLBACK_BASE_URL',
    $_ENV['KCB_CALLBACK_BASE_URL'] ?? (defined('BASE_URL') ? BASE_URL : '')
);

define('KCB_FUNDS_TRANSFER_PATH', $_ENV['KCB_FUNDS_TRANSFER_PATH'] ?? '/fundstransfer/1.0.0/api/v1/transfer');
define('KCB_COMPANY_CODE', $_ENV['KCB_COMPANY_CODE'] ?? '');
define('KCB_FUNDS_TRANSFER_TRANSACTION_TYPE', $_ENV['KCB_FUNDS_TRANSFER_TRANSACTION_TYPE'] ?? 'IF');
define('KCB_IPN_PATH', $_ENV['KCB_IPN_PATH'] ?? '/ipn/1.0.0');
define('KCB_MPESA_EXPRESS_PATH', $_ENV['KCB_MPESA_EXPRESS_PATH'] ?? '/mm/api/request/1.0.0');
define('KCB_MPESA_ROUTE_CODE', $_ENV['KCB_MPESA_ROUTE_CODE'] ?? '207');
define('KCB_MPESA_OPERATION', $_ENV['KCB_MPESA_OPERATION'] ?? 'STKPush');
define('KCB_TRANSFER_STATUS_PATH', $_ENV['KCB_TRANSFER_STATUS_PATH'] ?? '/kcb/bi/ips/p2p/transfer/status/inquiry/1.0.0');
define('KCB_STATUS_POLL_INITIAL_DELAY_SECONDS', max(60, (int) ($_ENV['KCB_STATUS_POLL_INITIAL_DELAY_SECONDS'] ?? 120)));
define('KCB_STATUS_POLL_MAX_ATTEMPTS', max(1, (int) ($_ENV['KCB_STATUS_POLL_MAX_ATTEMPTS'] ?? 5)));
define('KCB_STATUS_EXCEPTION_AFTER_MINUTES', max(5, (int) ($_ENV['KCB_STATUS_EXCEPTION_AFTER_MINUTES'] ?? 60)));
define('KCB_RECONCILIATION_WORKER_SECRET', $_ENV['KCB_RECONCILIATION_WORKER_SECRET'] ?? ($_ENV['COMMUNICATION_WORKER_SECRET'] ?? ''));

define(
    'KCB_PUBLIC_KEY_PATH',
    $_ENV['KCB_PUBLIC_KEY_PATH']
        ?? __DIR__ . '/kcb_public_key.pem'
);
define('KCB_VERIFY_CALLBACK_SIGNATURE', filter_var($_ENV['KCB_VERIFY_CALLBACK_SIGNATURE'] ?? '0', FILTER_VALIDATE_BOOLEAN));

/*
|--------------------------------------------------------------------------
| Application defaults
|--------------------------------------------------------------------------
*/

define('DEFAULT_PAGE_SIZE', 10);
define('MAX_PAGE_SIZE', 100);

// PHP rejects session INI changes after a request has started its session.
// Keep the development defaults for fresh requests without polluting the
// browser/API response with warnings for already-initialized sessions.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', '0');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
