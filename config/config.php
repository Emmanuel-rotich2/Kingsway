<?php
namespace App\Config;

// Debug mode
define('DEBUG', true);

// Base URL
$isLocal = ($_SERVER['HTTP_HOST'] ?? 'localhost') === 'localhost'
    || strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false;

$baseUrl = $isLocal
    ? 'http://localhost/Kingsway'
    : 'https://www.kingswaypreparatoryschool.sc.ke';

define('BASE_URL', $baseUrl);

// Paths
define('UPLOAD_PATH', '/home/kingswa4/uploads');
define('STUDENT_PHOTOS', UPLOAD_PATH . '/students');
define('STAFF_PHOTOS', UPLOAD_PATH . '/staff');
define('DOCUMENTS', UPLOAD_PATH . '/documents');

// Create upload directories if they don't exist
$directories = [UPLOAD_PATH, STUDENT_PHOTOS, STAFF_PHOTOS, DOCUMENTS];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Session settings
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0');

// Error reporting
if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Time zone
date_default_timezone_set('Africa/Nairobi');

// API settings
define('API_VERSION', '1.0');
define('API_BASE_URL', '/api/v1');

// Database
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'kingswa4_kingswayacademy');
define('DB_USER', 'kingswa4_root');
define('DB_PASS', 'admin123');

// JWT
define('JWT_SECRET', 'change_this_secret');
define('JWT_EXPIRY', 3600);
define('JWT_ISSUER', 'kingsway-prep-school');
define('JWT_AUDIENCE', 'kingsway-staff');

// System
define('SYSTEM_VERSION', '1.0.0');

// Allowed origins
$allowed_origins = [BASE_URL];

define('SMTP_HOST', 'mail.kingswaypreparatoryschool.sc.ke');
define('SMTP_PORT',  587);
define('SMTP_USERNAME', 'info@kingswaypreparatoryschool.sc.ke');
define('SMTP_FROM_EMAIL', 'info@kingswaypreparatoryschool.sc.ke');
define('SMTP_PASSWORD', '@Kingsway123'); 
define('SMTP_FROM_NAME', 'Kingsway Preparatory School');


// SMS Configuration
define('SMS_PROVIDER', 'africastalking'); // or 'twilio'
define('SMS_API_KEY', 'atsk_c5500c783227e742d2db31baf235dccfbce1ca1923ae3316026cdf8354c1e531e98ebf2c');
define('SMS_USERNAME', 'sandbox');
define('SMS_APPNAME', 'Sandbox');
define('SMS_SENDER_ID', 'Kingsway Preparatory'); // Alphanumeric sender ID  
define('SMS_SHORTCODE', '20174'); // Fallback shortcode

define('SMS_WHATSAPP_NUMBER', '+254710398690'); // WhatsApp business number (update with your number)

// M-Pesa Configuration
define('MPESA_ENVIRONMENT', 'sandbox'); // 'sandbox' or 'production'
define('MPESA_CONSUMER_KEY', 'KOuNiCwAxbOoHXgwLhiO19uukdg2AwfDClPeuImtm2dSSN5h');
define('MPESA_CONSUMER_SECRET', '1xGaj8DxjK0iAK0gOESHUfy2hnfVmQQPDr2AAiMDggwHHNPw16odelfjGGfAO1IV');
define('MPESA_SHORTCODE', 'your_mpesa_shortcode'); // Your paybill number
define('MPESA_PASSKEY', 'your_mpesa_passkey');
define('MPESA_INITIATOR_NAME', 'your_initiator_name');
define('MPESA_INITIATOR_PASSWORD', 'your_initiator_password');

// M-Pesa API URLs
define('MPESA_BASE_URL', MPESA_ENVIRONMENT === 'production'
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke');

// M-Pesa Callback URLs
define('MPESA_STK_CALLBACK_URL', BASE_URL . '/api/payments/mpesa-callback.php');
define('MPESA_C2B_VALIDATION_URL', BASE_URL . '/api/payments/mpesa-c2b-validation.php');
define('MPESA_C2B_CONFIRMATION_URL', BASE_URL . '/api/payments/mpesa-c2b-confirmation.php');

// M-Pesa B2C (Business to Customer) Callback URLs
define('MPESA_B2C_RESULT_URL', BASE_URL . '/api/payments/mpesa-b2c-callback.php');
define('MPESA_B2C_TIMEOUT_URL', BASE_URL . '/api/payments/mpesa-b2c-timeout.php');

// M-Pesa Security Credential (Required for B2C transactions)
// This is your initiator password encrypted with Safaricom's public key
// Get this from the Daraja Portal under "Security Credentials"
define('MPESA_SECURITY_CREDENTIAL', 'OoT56CKkJWtWsO41KlwfK6q4DjA+d+HN7h2OL0TaoECOxujfquTY/PeRduwm3DIKYIUhcS4HE2Bh9MgnHIGAhK8KXueSTgF/c11zNmfP9HNyyeeFGJWGCcA7Wqo+cdwx5GMBT8DIsKbSIcB8VZosS4OhsaNs93CX4ecIRVjzUzTJw7DxnJmTsLqqM2nGD1lkgSEo/4pJXbY+OfNTtHuzjicnCMRGbUQ8i5IJDqqfMZX7YO8hDp1/s+Lu3KaYg+o41PcoEUqEMBsWb3JohLhNSs4G6ZpE1PI4Vg73vcbcQYosrj4euAocU2qGxi2G282qyxAN6QUbPu45hFZ/0GLG4A==');

// KCB Bank Buni Configuration
define('KCB_ENVIRONMENT', 'sandbox'); // 'sandbox' or 'production'

// KCB Buni Sandbox Credentials
define('KCB_CONSUMER_KEY', 'VuDpL9GmLg5GgC_Y3yeNXqRsshQa');
define('KCB_CONSUMER_SECRET', 'voOG2c_HYHdV_mVmFW8nErkgTFUa');
define("KCB_API_KEY", "eyJ4NXQiOiJaREEzWldJeU1UTTVabUptTnpNeU5UTXlabU13TVRZMU4ySTJORGhsT1dSaFpEWmpNakUwTkE9PSIsImtpZCI6ImdhdGV3YXlfY2VydGlmaWNhdGVfYWxpYXMiLCJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJzdWIiOiJBbmdpU29mdFRlY2hub2xvZ2llc0BjYXJib24uc3VwZXIiLCJhcHBsaWNhdGlvbiI6eyJvd25lciI6IkFuZ2lTb2Z0VGVjaG5vbG9naWVzIiwidGllclF1b3RhVHlwZSI6bnVsbCwidGllciI6IlVubGltaXRlZCIsIm5hbWUiOiJLaW5nc1dheSBQcmVwYXJhdG9yeSBTY2hvb2wiLCJpZCI6MzI0MDMsInV1aWQiOiI3M2E3OGM2YS0yMTA0LTRkMjgtYjc2Ny1hOTVkMTg2ODJhZTQifSwiaXNzIjoiaHR0cHM6XC9cL3NhbmRib3guYnVuaS5rY2Jncm91cC5jb21cL29hdXRoMlwvdG9rZW4iLCJ0aWVySW5mbyI6eyJHb2xkIjp7InRpZXJRdW90YVR5cGUiOiJyZXF1ZXN0Q291bnQiLCJncmFwaFFMTWF4Q29tcGxleGl0eSI6MCwiZ3JhcGhRTE1heERlcHRoIjowLCJzdG9wT25RdW90YVJlYWNoIjp0cnVlLCJzcGlrZUFycmVzdExpbWl0IjowLCJzcGlrZUFycmVzdFVuaXQiOm51bGx9LCJVbmxpbWl0ZWQiOnsidGllclF1b3RhVHlwZSI6InJlcXVlc3RDb3VudCIsImdyYXBoUUxNYXhDb21wbGV4aXR5IjowLCJncmFwaFFMTWF4RGVwdGgiOjAsInN0b3BPblF1b3RhUmVhY2giOnRydWUsInNwaWtlQXJyZXN0TGltaXQiOjAsInNwaWtlQXJyZXN0VW5pdCI6bnVsbH19LCJrZXl0eXBlIjoiU0FOREJPWCIsInBlcm1pdHRlZFJlZmVyZXIiOiIiLCJzdWJzY3JpYmVkQVBJcyI6W3sic3Vic2NyaWJlclRlbmFudERvbWFpbiI6ImNhcmJvbi5zdXBlciIsIm5hbWUiOiJUcmFuc2FjdGlvbkluZm9Db3JlIiwiY29udGV4dCI6IlwvZ2V0dHJhbnNhY3Rpb25zdGF0dXNcLzEuMCIsInB1Ymxpc2hlciI6InN1cGVyX2FkbWluIiwidmVyc2lvbiI6IjEuMCIsInN1YnNjcmlwdGlvblRpZXIiOiJVbmxpbWl0ZWQifSx7InN1YnNjcmliZXJUZW5hbnREb21haW4iOiJjYXJib24uc3VwZXIiLCJuYW1lIjoiTXBlc2FFeHByZXNzQVBJU2VydmljZSIsImNvbnRleHQiOiJcL21tXC9hcGlcL3JlcXVlc3RcLzEuMC4wIiwicHVibGlzaGVyIjoic3VwZXJfYWRtaW4iLCJ2ZXJzaW9uIjoiMS4wLjAiLCJzdWJzY3JpcHRpb25UaWVyIjoiVW5saW1pdGVkIn0seyJzdWJzY3JpYmVyVGVuYW50RG9tYWluIjoiY2FyYm9uLnN1cGVyIiwibmFtZSI6IlF1ZXJ5Q29yZVRyYW5zYWN0aW9uU3RhdHVzIiwiY29udGV4dCI6IlwvdjFcL2NvcmVcL3QyNFwvcXVlcnl0cmFuc2FjdGlvblwvMS4wLjAiLCJwdWJsaXNoZXIiOiJzdXBlcl9hZG1pbiIsInZlcnNpb24iOiIxLjAuMCIsInN1YnNjcmlwdGlvblRpZXIiOiJHb2xkIn0seyJzdWJzY3JpYmVyVGVuYW50RG9tYWluIjoiY2FyYm9uLnN1cGVyIiwibmFtZSI6IkZ1bmRzVHJhbnNmZXJBUElTZXJ2aWNlIiwiY29udGV4dCI6IlwvZnVuZHN0cmFuc2ZlclwvMS4wLjAiLCJwdWJsaXNoZXIiOiJzdXBlcl9hZG1pbiIsInZlcnNpb24iOiIxLjAuMCIsInN1YnNjcmlwdGlvblRpZXIiOiJHb2xkIn0seyJzdWJzY3JpYmVyVGVuYW50RG9tYWluIjoiY2FyYm9uLnN1cGVyIiwibmFtZSI6Ikluc3RhbnRQYXltZW50Tm90aWZpY2F0aW9uIiwiY29udGV4dCI6IlwvaXBuXC8xLjAuMCIsInB1Ymxpc2hlciI6InN1cGVyX2FkbWluIiwidmVyc2lvbiI6IjEuMC4wIiwic3Vic2NyaXB0aW9uVGllciI6IlVubGltaXRlZCJ9LHsic3Vic2NyaWJlclRlbmFudERvbWFpbiI6ImNhcmJvbi5zdXBlciIsIm5hbWUiOiJRdWVyeVRyYW5zYWN0aW9uRGV0YWlscyIsImNvbnRleHQiOiJcL2tjYlwvdHJhbnNhY3Rpb25cL3F1ZXJ5XC8xLjAuMCIsInB1Ymxpc2hlciI6InN1cGVyX2FkbWluIiwidmVyc2lvbiI6IjEuMC4wIiwic3Vic2NyaXB0aW9uVGllciI6IkdvbGQifSx7InN1YnNjcmliZXJUZW5hbnREb21haW4iOiJjYXJib24uc3VwZXIiLCJuYW1lIjoiVkVORElOR0dBVEVXQVlBUElTIiwiY29udGV4dCI6Ilwva2NiXC92ZW5kaW5nR2F0ZXdheVwvdjFcLzEuMC4wIiwicHVibGlzaGVyIjoic3VwZXJfYWRtaW4iLCJ2ZXJzaW9uIjoiMS4wLjAiLCJzdWJzY3JpcHRpb25UaWVyIjoiVW5saW1pdGVkIn1dLCJ0b2tlbl90eXBlIjoiYXBpS2V5IiwicGVybWl0dGVkSVAiOiIiLCJpYXQiOjE3NjI5MDAzNDYsImp0aSI6IjI1MDE1N2JlLTQ0ODAtNDU0Ni1hZTVlLTEyODJjYjBhN2I0ZiJ9.UzOd2ZS-nCqu421usdUg8xHhVohCWali1dlNJRM8TK3xQN3TJK9xZ3GtNxM9IaizJSLSWaSTWZbAfFIUbOW3tEN8qhAQ5nhPFf-pl6x8cYH_EkbEA7W9xB2qddRJUSdDrkEiIguqoDMpxdvPqIHMpb7ZVAeoZ3yUjsQn214Cb32qp8JqM5-P8m88Urf-q7yTzUUQQTXnvU5-QKn-VlUUO85atYCs-zLW4ShMfKvWO2f0ot7QUWwzWcEZZAK59wG7vw1jA-A8f92BYXW2l0ID25KN-d_XkNdQRF1DhczIpQBrTZnSB3Hz3J6oZaZX2k5sDOPm6TGo0S3g0_UHWKeREw==");

// KCB API URLs
define('KCB_TOKEN_ENDPOINT', 'https://accounts.buni.kcbgroup.com/oauth2/token');
define('KCB_BASE_URL', KCB_ENVIRONMENT === 'production'
    ? 'https://buni.kcbgroup.com'
    : 'https://uat.buni.kcbgroup.com');
define('KCB_IPN_URL', KCB_BASE_URL . '/ipn/1.0.0');

// KCB Callback URLs
define('KCB_VALIDATION_URL', BASE_URL . '/api/payments/kcb-validation.php');
define('KCB_NOTIFICATION_URL', BASE_URL . '/api/payments/kcb-notification.php');
define('KCB_TRANSFER_CALLBACK_URL', BASE_URL . '/api/payments/kcb-transfer-callback.php');

// KCB Account Details (Update these with your actual KCB account details)
define('KCB_ORGANIZATION_REFERENCE', 'your_org_reference'); // Will be provided by KCB
define('KCB_CREDIT_ACCOUNT', 'your_kcb_account_number'); // Your school's KCB account
define('KCB_PUBLIC_KEY_PATH', __DIR__ . '/kcb_public_key.pem'); // Path to KCB public key file

// Generic Bank Configuration (For other bank integrations)
define('BANK_API_KEY', 'your_bank_api_key'); // Used for bank webhook signature validation

// CBE Curriculum Config
$CBE_GRADE_LEVELS = [
    'Play Group',
    'PP1',
    'PP2',
    'Grade 1',
    'Grade 2',
    'Grade 3',
    'Grade 4',
    'Grade 5',
    'Grade 6',
    'Grade 7',
    'Grade 8',
    'Grade 9'
];

$CBE_ASSESSMENT_TYPES = [
    'Observation',
    'Checklist',
    'Portfolio',
    'Project',
    'Test',
    'Exam',
    'Practical',
    'Oral',
    'Written',
    'Summative',
    'Formative'
];

$SCHOOL_TERMS = [
    1 => 'Term 1',
    2 => 'Term 2',
    3 => 'Term 3'
];

$SCHOOL_LEVELS = [
    'Nursery',
    'Lower Primary',
    'Upper Primary',
    'Junior Secondary'
];
