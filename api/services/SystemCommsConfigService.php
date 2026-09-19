<?php

namespace App\API\Services;

/**
 * Manages communication provider configuration (SMS, Email, WhatsApp) stored
 * as key-value pairs in the school_settings table. DB values override PHP
 * constants at runtime, giving operators a UI-driven way to reconfigure
 * providers without touching config files.
 */
class SystemCommsConfigService
{
    private \PDO $db;

    /** @var array<int, array{setting_key: string, setting_value: ?string}> cached comms rows */
    private ?array $dbCache = null;

    // ── Key maps: db_key => [constant_name, label, is_secret] ────────────

    private const SMS_KEYS = [
        'comms_sms_provider'   => ['SMS_PROVIDER', 'SMS Provider', false],
        'comms_sms_username'   => ['SMS_USERNAME', 'SMS Username', false],
        'comms_sms_api_key'    => ['SMS_API_KEY', 'SMS API Key', true],
        'comms_sms_appname'    => ['SMS_APPNAME', 'SMS App Name', false],
        'comms_sms_sender_id'  => ['SMS_SENDER_ID', 'Sender ID', false],
        'comms_sms_shortcode'  => ['SMS_SHORTCODE', 'Short Code', false],
        'comms_sms_wa_number'  => ['SMS_WHATSAPP_NUMBER', 'WhatsApp Number (AT)', false],
        'comms_sms_wa_url'     => ['SMS_WHATSAPP_API_URL', 'WhatsApp API URL', false],
        'comms_twilio_sid'     => ['TWILIO_ACCOUNT_SID', 'Twilio Account SID', false],
        'comms_twilio_token'   => ['TWILIO_AUTH_TOKEN', 'Twilio Auth Token', true],
        'comms_twilio_from'    => ['TWILIO_FROM', 'Twilio From Number', false],
    ];

    private const EMAIL_KEYS = [
        'comms_email_host'       => ['SMTP_HOST', 'SMTP Host', false],
        'comms_email_port'       => ['SMTP_PORT', 'SMTP Port', false],
        'comms_email_username'   => ['SMTP_USERNAME', 'SMTP Username', false],
        'comms_email_password'   => ['SMTP_PASSWORD', 'SMTP Password', true],
        'comms_email_from_email' => ['SMTP_FROM_EMAIL', 'From Email', false],
        'comms_email_from_name'  => ['SMTP_FROM_NAME', 'From Name', false],
    ];

    private const WA_KEYS = [
        'comms_wa_username' => ['SMS_USERNAME', 'WhatsApp Username', false],
        'comms_wa_api_key'  => ['SMS_API_KEY', 'WhatsApp API Key', true],
        'comms_wa_number'   => ['SMS_WHATSAPP_NUMBER', 'WhatsApp Number', false],
        'comms_wa_api_url'  => ['SMS_WHATSAPP_API_URL', 'WhatsApp API URL', false],
    ];

    /** Group name → const map */
    private const GROUPS = [
        'sms'      => self::SMS_KEYS,
        'email'    => self::EMAIL_KEYS,
        'whatsapp' => self::WA_KEYS,
    ];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    // ── Public API ────────────────────────────────────────────────────────

    /**
     * Return config fields for a single group (sms | email | whatsapp).
     *
     * Each field: {key, constant_name, label, value, is_secret, source, configured}
     */
    public function getGroup(string $group): array
    {
        if (!isset(self::GROUPS[$group])) {
            return [];
        }

        $map  = self::GROUPS[$group];
        $rows = $this->loadDbValues();
        $out  = [];

        foreach ($map as $dbKey => $meta) {
            [$constName, $label, $isSecret] = $meta;

            $dbVal  = $rows[$dbKey]['setting_value'] ?? null;
            $envVal = $this->envValue($constName);

            if ($dbVal !== null && $dbVal !== '') {
                $value  = $isSecret ? $this->maskSecret($dbVal) : $dbVal;
                $source = 'db';
            } elseif ($envVal !== '') {
                $value  = $isSecret ? $this->maskSecret($envVal) : $envVal;
                $source = 'env';
            } else {
                $value  = '';
                $source = 'env';
            }

            $out[] = [
                'key'            => $dbKey,
                'constant_name'  => $constName,
                'label'          => $label,
                'value'          => $value,
                'is_secret'      => $isSecret,
                'source'         => $source,
                'configured'     => ($dbVal !== null && $dbVal !== '') || $envVal !== '',
            ];
        }

        return $out;
    }

    /**
     * Return all three groups: {sms: [...], email: [...], whatsapp: [...]}.
     */
    public function getAll(): array
    {
        return [
            'sms'      => $this->getGroup('sms'),
            'email'    => $this->getGroup('email'),
            'whatsapp' => $this->getGroup('whatsapp'),
        ];
    }

    /**
     * Upsert allowed fields into school_settings and return the refreshed group.
     *
     * $fields = [['key' => 'comms_sms_provider', 'value' => 'africastalking'], ...]
     */
    public function saveGroup(string $group, array $fields): array
    {
        if (!isset(self::GROUPS[$group])) {
            return [];
        }

        $allowedKeys = array_keys(self::GROUPS[$group]);

        $stmt = $this->db->prepare(
            'INSERT INTO school_settings (setting_key, setting_value, updated_at)
             VALUES (:key, :val, NOW())
             ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = NOW()'
        );

        foreach ($fields as $field) {
            $dbKey = $field['key'] ?? '';
            if (!in_array($dbKey, $allowedKeys, true)) {
                continue;
            }
            $val = $field['value'] ?? '';
            $stmt->execute([':key' => $dbKey, ':val' => $val, ':val2' => $val]);
        }

        $this->dbCache = null;

        return $this->getGroup($group);
    }

    /**
     * Merged SMS config suitable for `new SMSGateway($config)`.
     * DB values take precedence over PHP constants.
     */
    public function effectiveSmsConfig(): array
    {
        $rows = $this->loadDbValues();

        return [
            'provider'         => $rows['comms_sms_provider']['setting_value']   ?? $this->envValue('SMS_PROVIDER')           ?: 'africastalking',
            'api_key'          => $rows['comms_sms_api_key']['setting_value']    ?? $this->envValue('SMS_API_KEY'),
            'username'         => $rows['comms_sms_username']['setting_value']   ?? $this->envValue('SMS_USERNAME'),
            'appname'          => $rows['comms_sms_appname']['setting_value']    ?? $this->envValue('SMS_APPNAME'),
            'sender_id'        => $rows['comms_sms_sender_id']['setting_value']  ?? $this->envValue('SMS_SENDER_ID'),
            'shortcode'        => $rows['comms_sms_shortcode']['setting_value']  ?? $this->envValue('SMS_SHORTCODE'),
            'wa_number'        => $rows['comms_sms_wa_number']['setting_value']  ?? $this->envValue('SMS_WHATSAPP_NUMBER'),
            'whatsapp_api_url' => $rows['comms_sms_wa_url']['setting_value']     ?? $this->envValue('SMS_WHATSAPP_API_URL') ?: 'https://chat.africastalking.com',
            'account_sid'      => $rows['comms_twilio_sid']['setting_value']     ?? $this->envValue('TWILIO_ACCOUNT_SID'),
            'auth_token'       => $rows['comms_twilio_token']['setting_value']   ?? $this->envValue('TWILIO_AUTH_TOKEN'),
            'from'             => $rows['comms_twilio_from']['setting_value']    ?? $this->envValue('TWILIO_FROM'),
        ];
    }

    /**
     * Merged WhatsApp config suitable for gateway instantiation.
     */
    public function effectiveWhatsAppConfig(): array
    {
        $rows = $this->loadDbValues();

        return [
            'provider' => 'africastalking',
            'api_key'  => $rows['comms_wa_api_key']['setting_value']  ?? $this->envValue('SMS_API_KEY'),
            'username' => $rows['comms_wa_username']['setting_value'] ?? $this->envValue('SMS_USERNAME'),
            'wa_number' => $rows['comms_wa_number']['setting_value']  ?? $this->envValue('SMS_WHATSAPP_NUMBER'),
            'api_url'  => $rows['comms_wa_api_url']['setting_value']  ?? $this->envValue('SMS_WHATSAPP_API_URL') ?: 'https://chat.africastalking.com',
        ];
    }

    /**
     * Merged SMTP config for PHPMailer.
     */
    public function effectiveSmtp(): array
    {
        $rows = $this->loadDbValues();

        return [
            'host'       => $rows['comms_email_host']['setting_value']       ?? $this->envValue('SMTP_HOST'),
            'port'       => $rows['comms_email_port']['setting_value']       ?? $this->envValue('SMTP_PORT'),
            'username'   => $rows['comms_email_username']['setting_value']   ?? $this->envValue('SMTP_USERNAME'),
            'password'   => $rows['comms_email_password']['setting_value']   ?? $this->envValue('SMTP_PASSWORD'),
            'from_email' => $rows['comms_email_from_email']['setting_value'] ?? $this->envValue('SMTP_FROM_EMAIL'),
            'from_name'  => $rows['comms_email_from_name']['setting_value']  ?? $this->envValue('SMTP_FROM_NAME'),
        ];
    }

    /**
     * Attempt a balance check using the effective SMS config.
     * For africastalking, instantiate the SDK and call getBalance().
     * Does NOT actually send an SMS.
     */
    public function testSms(string $testPhone): array
    {
        try {
            $cfg = $this->effectiveSmsConfig();

            if (empty($cfg['username']) || empty($cfg['api_key'])) {
                return ['success' => false, 'message' => 'SMS username or API key is not configured.'];
            }

            if ($cfg['provider'] !== 'africastalking') {
                return ['success' => false, 'message' => "Balance check is only available for africastalking provider. Current provider: {$cfg['provider']}."];
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $at       = new \AfricasTalking\SDK\AfricasTalking($cfg['username'], $cfg['api_key']);
            $balance  = $at->account()->getBalance();

            return [
                'success' => true,
                'message' => 'Africa\'s Talking account is reachable.',
                'balance' => is_object($balance) && isset($balance->balance) ? (float) $balance->balance : (float) $balance,
            ];
        } catch (\Throwable $e) {
            Logger::legacyError('[SystemCommsConfig] testSms failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SMS provider connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Attempt an SMTP connection via PHPMailer without sending mail.
     */
    public function testEmail(): array
    {
        try {
            $cfg = $this->effectiveSmtp();

            if (empty($cfg['host']) || empty($cfg['port'])) {
                return ['success' => false, 'message' => 'SMTP host or port is not configured.'];
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = (int) $cfg['port'];
            $mail->SMTPAuth   = !empty($cfg['username']);
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->smtpConnect();
            $mail->smtp->close();

            return ['success' => true, 'message' => 'SMTP connection successful.'];
        } catch (\Throwable $e) {
            Logger::legacyError('[SystemCommsConfig] testEmail failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Validate WhatsApp config presence and optionally test AT balance.
     */
    public function testWhatsApp(): array
    {
        try {
            $cfg = $this->effectiveWhatsAppConfig();

            if (empty($cfg['username']) || empty($cfg['api_key'])) {
                return ['success' => false, 'message' => 'WhatsApp username or API key is not configured.'];
            }

            require_once __DIR__ . '/../../vendor/autoload.php';

            $at      = new \AfricasTalking\SDK\AfricasTalking($cfg['username'], $cfg['api_key']);
            $balance = $at->account()->getBalance();

            return [
                'success' => true,
                'message' => 'WhatsApp (Africa\'s Talking) account is reachable.',
                'balance' => is_object($balance) && isset($balance->balance) ? (float) $balance->balance : (float) $balance,
            ];
        } catch (\Throwable $e) {
            Logger::legacyError('[SystemCommsConfig] testWhatsApp failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'WhatsApp provider connection failed: ' . $e->getMessage()];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Load and cache all comms_* rows from school_settings.
     */
    private function loadDbValues(): array
    {
        if ($this->dbCache !== null) {
            return $this->dbCache;
        }

        $this->dbCache = [];

        try {
            $stmt = $this->db->query(
                "SELECT setting_key, setting_value FROM school_settings WHERE setting_key LIKE 'comms\\_%'"
            );
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $this->dbCache[$row['setting_key']] = $row;
            }
        } catch (\Throwable $e) {
            Logger::legacyError('[SystemCommsConfig] loadDbValues failed: ' . $e->getMessage());
        }

        return $this->dbCache;
    }

    /**
     * Return the value of a global constant if defined, else empty string.
     */
    private function envValue(string $constantName): string
    {
        return defined($constantName) ? (string) constant($constantName) : '';
    }

    /**
     * Mask a secret value: first 3 + '...' + last 3 for long strings, '****' for short.
     */
    private function maskSecret(string $value): string
    {
        if (mb_strlen($value) > 8) {
            return mb_substr($value, 0, 3) . '...' . mb_substr($value, -3);
        }
        return '****';
    }
}
