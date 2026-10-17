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
        'comms_sms_api_url'    => ['SMS_API_URL', 'API Base URL', false],
        'comms_sms_sender_id'  => ['SMS_SENDER_ID', 'Sender ID', false],
        'comms_sms_shortcode'  => ['SMS_SHORTCODE', 'Short Code', false],
        'comms_sms_wa_number'  => ['SMS_WHATSAPP_NUMBER', 'WhatsApp Number', false],
        'comms_sms_topup_paybill' => ['SMS_TOPUP_PAYBILL', 'Top-up Paybill', false],
        'comms_sms_topup_account' => ['SMS_TOPUP_ACCOUNT', 'Top-up Account Number', false],
        'comms_sms_topup_notes'   => ['SMS_TOPUP_NOTES', 'Top-up Instructions', false],
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
        'comms_wa_api_url'  => ['SMS_API_URL', 'API Base URL', false],
    ];

    private const EMAIL_ADVANCED_KEYS = [
        'comms_pop3_host'       => ['POP3_HOST', 'POP3 Host', false],
        'comms_pop3_port'       => ['POP3_PORT', 'POP3 Port', false],
        'comms_imap_host'       => ['IMAP_HOST', 'IMAP Host', false],
        'comms_imap_port'       => ['IMAP_PORT', 'IMAP Port', false],
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
     * $fields = [['key' => 'comms_sms_provider', 'value' => 'talksasa'], ...]
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

        $senderId = $rows['comms_sms_sender_id']['setting_value'] ?? $this->envValue('SMS_SENDER_ID');
        if ($senderId === '') {
            $senderId = $this->envValue('SMS_SHORTCODE');
        }

        return [
            'provider'  => $rows['comms_sms_provider']['setting_value']  ?? $this->envValue('SMS_PROVIDER') ?: 'talksasa',
            'api_key'   => $rows['comms_sms_api_key']['setting_value']   ?? $this->envValue('SMS_API_KEY'),
            'username'  => $rows['comms_sms_username']['setting_value']  ?? $this->envValue('SMS_USERNAME'),
            'appname'   => $rows['comms_sms_appname']['setting_value']   ?? $this->envValue('SMS_APPNAME'),
            'api_url'   => $rows['comms_sms_api_url']['setting_value']   ?? $this->envValue('SMS_API_URL') ?: 'https://bulksms.talksasa.com/api/v3/',
            'sender_id' => $senderId,
            'shortcode' => $rows['comms_sms_shortcode']['setting_value'] ?? $this->envValue('SMS_SHORTCODE'),
            'wa_number' => $rows['comms_sms_wa_number']['setting_value'] ?? $this->envValue('SMS_WHATSAPP_NUMBER'),
        ];
    }

    /**
     * Merged WhatsApp config suitable for gateway instantiation.
     */
    public function effectiveWhatsAppConfig(): array
    {
        $rows = $this->loadDbValues();

        $provider = $rows['comms_sms_provider']['setting_value'] ?? $this->envValue('SMS_PROVIDER');
        $senderId = $rows['comms_wa_number']['setting_value'] ?? $this->envValue('SMS_WHATSAPP_NUMBER');
        if ($senderId === '') {
            $senderId = $rows['comms_sms_sender_id']['setting_value'] ?? $this->envValue('SMS_SENDER_ID');
        }

        return [
            'provider'  => $provider !== '' ? $provider : 'talksasa',
            'api_key'   => $rows['comms_wa_api_key']['setting_value']  ?? $this->envValue('SMS_API_KEY'),
            'username'  => $rows['comms_wa_username']['setting_value'] ?? $this->envValue('SMS_USERNAME'),
            'wa_number' => $senderId,
            'sender_id' => $senderId,
            'api_url'   => $rows['comms_wa_api_url']['setting_value']  ?? $this->envValue('SMS_API_URL') ?: 'https://bulksms.talksasa.com/api/v3/',
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
     * Merged advanced email config (POP3/IMAP).
     */
    public function effectiveAdvancedEmail(): array
    {
        $rows = $this->loadDbValues();

        return [
            'pop3_host'       => $rows['comms_pop3_host']['setting_value']       ?? '',
            'pop3_port'       => $rows['comms_pop3_port']['setting_value']       ?? 110,
            'imap_host'       => $rows['comms_imap_host']['setting_value']       ?? '',
            'imap_port'       => $rows['comms_imap_port']['setting_value']       ?? 143,
        ];
    }

    /**
     * Attempt a balance check using the effective SMS config.
     * Instantiates the provider-agnostic SMSGateway and calls balance().
     * Does NOT actually send an SMS.
     */
    public function testSms(string $testPhone): array
    {
        try {
            $cfg = $this->effectiveSmsConfig();

            if (empty($cfg['api_key'])) {
                return ['success' => false, 'message' => 'SMS API key is not configured.'];
            }

            $gateway = new \App\API\Services\sms\SMSGateway($cfg);
            $result = $gateway->balance();

            if (!($result['ok'] ?? false)) {
                return ['success' => false, 'message' => $result['message'] ?: 'SMS provider connection failed.'];
            }

            return [
                'success' => true,
                'message' => 'SMS provider account is reachable.',
                'balance' => $this->extractBalance($result['data'] ?? null),
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

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = (int) $cfg['port'];
            $mail->SMTPAuth   = !empty($cfg['username']);
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

            $mail->smtpConnect();
            $mail->smtpClose();

            return ['success' => true, 'message' => 'SMTP connection successful.'];
        } catch (\Throwable $e) {
            Logger::legacyError('[SystemCommsConfig] testEmail failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check the configured messaging provider's SMS balance.
     */
    public function getSmsBalance(): array
    {
        $cfg = $this->effectiveSmsConfig();

        if (($cfg['api_key'] ?? '') === '') {
            return ['success' => false, 'balance' => 0, 'message' => 'SMS API key is not configured.'];
        }

        try {
            $gateway = new \App\API\Services\sms\SMSGateway($cfg);
            $result = $gateway->balance();
        } catch (\Throwable $e) {
            return ['success' => false, 'balance' => 0, 'message' => 'Balance check failed: ' . $e->getMessage()];
        }

        if (!($result['ok'] ?? false)) {
            return ['success' => false, 'balance' => 0, 'message' => $result['message'] ?: 'SMS balance could not be retrieved.'];
        }

        return [
            'success' => true,
            'balance' => $this->extractBalance($result['data'] ?? null),
            'message' => 'Balance retrieved',
        ];
    }

    /**
     * Validate the WhatsApp config and probe the provider account.
     */
    public function testWhatsApp(): array
    {
        try {
            $cfg = $this->effectiveWhatsAppConfig();

            if (empty($cfg['api_key'])) {
                return ['success' => false, 'message' => 'WhatsApp API key is not configured.'];
            }

            $gateway = new \App\API\Services\sms\SMSGateway($cfg);
            $result = $gateway->profile();
            if (!($result['ok'] ?? false)) {
                $result = $gateway->balance();
            }
            if (!($result['ok'] ?? false)) {
                return ['success' => false, 'message' => $result['message'] ?: 'WhatsApp provider connection failed.'];
            }

            return [
                'success' => true,
                'message' => 'WhatsApp provider account is reachable.',
                'balance' => $this->extractBalance($result['data'] ?? null),
            ];
        } catch (\Throwable $e) {
            Logger::legacyError('[SystemCommsConfig] testWhatsApp failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'WhatsApp provider connection failed: ' . $e->getMessage()];
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Pull a numeric balance out of a provider payload with varying shapes.
     */
    private function extractBalance($data): float
    {
        if (is_numeric($data)) {
            return (float) $data;
        }
        if (!is_array($data)) {
            return $this->balanceToNumber($data) ?? 0.0;
        }
        foreach (['balance', 'sms_unit', 'sms_units', 'unit', 'units', 'credits', 'amount', 'remaining_balance', 'remaining_units'] as $key) {
            if (isset($data[$key])) {
                $num = $this->balanceToNumber($data[$key]);
                if ($num !== null) {
                    return $num;
                }
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $nested = $this->extractBalance($value);
                if ($nested > 0) {
                    return $nested;
                }
            }
        }
        return 0.0;
    }

    /**
     * Coerce a provider balance value to a number, tolerating the decorated
     * forms real gateways return ("Ksh5", "KES 1,250.00", "1500 units").
     */
    private function balanceToNumber($value): ?float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (!is_string($value)) {
            return null;
        }
        $clean = preg_replace('/[^0-9.\-]/', '', $value);
        if ($clean === null || $clean === '' || $clean === '-' || $clean === '.' || $clean === '-.') {
            return null;
        }
        return (float) $clean;
    }

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
