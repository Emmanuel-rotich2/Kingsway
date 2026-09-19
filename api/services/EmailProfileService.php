<?php

declare(strict_types=1);

namespace App\API\Services;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * EmailProfileService – management of per-department email mailbox profiles.
 */
class EmailProfileService
{
    private \PDO $db;

    private const DEFAULT_HOST = 'mail.kingswaypreparatoryschool.sc.ke';

    private const SMTP_PORT = 587;

    private const IMAP_PORT = 993;

    private const UPDATABLE_COLUMNS = [
        'label',
        'email_address',
        'display_name',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'imap_host',
        'imap_port',
        'imap_username',
        'imap_password',
        'is_active',
        'is_default',
        'assigned_role_ids',
    ];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * List all email profiles, optionally filtered by an assigned role.
     */
    public function listProfiles(array $filters = []): array
    {
        try {
            $this->ensureConfigDefaultProfile();

            $sql = 'SELECT id, label, email_address, display_name, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at FROM comms_email_profiles';
            $params = [];

            if (isset($filters['role_id']) && $filters['role_id'] !== '') {
                $sql .= ' WHERE assigned_role_ids IS NOT NULL AND JSON_CONTAINS(assigned_role_ids, ?)';
                $params[] = json_encode((int) $filters['role_id']);
            }

            $sql .= ' ORDER BY is_default DESC, label ASC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return array_map(
                fn (array $row): array => $this->hydrate($row),
                $stmt->fetchAll(\PDO::FETCH_ASSOC)
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] listProfiles failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Return the raw (unmasked) connection credentials for internal IMAP/SMTP
     * use, or null when the profile does not exist. Only used server-side.
     */
    public function getCredentials(int $id): ?array
    {
        $row = $this->fetchRaw($id);

        if ($row === null) {
            return null;
        }

        return [
            'id'            => (int) $row['id'],
            'label'         => (string) $row['label'],
            'email_address' => (string) $row['email_address'],
            'display_name'  => (string) $row['display_name'],
            'smtp_host'     => (string) $row['smtp_host'],
            'smtp_port'     => (int) $row['smtp_port'],
            'smtp_username' => (string) $row['smtp_username'],
            'smtp_password' => (string) ($row['smtp_password'] ?? ''),
            'imap_host'     => (string) $row['imap_host'],
            'imap_port'     => (int) $row['imap_port'],
            'imap_username' => (string) $row['imap_username'],
            'imap_password' => (string) ($row['imap_password'] ?? ''),
            'is_active'     => (int) $row['is_active'],
            'is_default'    => (int) $row['is_default'],
            'assigned_role_ids' => json_decode((string) ($row['assigned_role_ids'] ?? ''), true) ?: [],
        ];
    }

    /**
     * Return a single profile with masked secrets, or null when not found.
     */
    public function getProfile(int $id): ?array
    {
        try {
            $row = $this->fetchRaw($id);

            return $row === null ? null : $this->hydrate($row);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] getProfile failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create an email profile and return the stored profile.
     */
    public function createProfile(array $data): array
    {
        try {
            $label = trim((string) ($data['label'] ?? ''));
            $email = trim((string) ($data['email_address'] ?? ''));
            $displayName = trim((string) ($data['display_name'] ?? ''));
            $smtpUsername = trim((string) ($data['smtp_username'] ?? ''));
            $smtpPassword = (string) ($data['smtp_password'] ?? '');

            if ($label === '' || $email === '' || $displayName === '' || $smtpUsername === '' || $smtpPassword === '') {
                throw new \InvalidArgumentException('label, email_address, display_name, smtp_username and smtp_password are required.');
            }

            $smtpHost = trim((string) ($data['smtp_host'] ?? self::DEFAULT_HOST));
            $smtpPort = (int) ($data['smtp_port'] ?? self::SMTP_PORT);
            $imapHost = trim((string) ($data['imap_host'] ?? $smtpHost));
            $imapPort = (int) ($data['imap_port'] ?? self::IMAP_PORT);
            $imapUsername = (string) ($data['imap_username'] ?? '');
            $imapPassword = (string) ($data['imap_password'] ?? '');
            $isActive = (int) ($data['is_active'] ?? 1);
            $isDefault = (int) ($data['is_default'] ?? 0);
            $roles = $this->normalizeRoles($data['assigned_role_ids'] ?? null);

            if ($isDefault === 1) {
                $this->db->exec('UPDATE comms_email_profiles SET is_default = 0');
            }

            $stmt = $this->db->prepare(
                'INSERT INTO comms_email_profiles
                 (label, email_address, display_name, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $label,
                $email,
                $displayName,
                $smtpHost,
                $smtpPort,
                $smtpUsername,
                $smtpPassword,
                $imapHost,
                $imapPort,
                $imapUsername,
                $imapPassword,
                $isActive,
                $isDefault,
                $roles,
            ]);

            return $this->getProfile((int) $this->db->lastInsertId());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] createProfile failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update a profile and return the stored profile.
     */
    public function updateProfile(int $id, array $data): array
    {
        try {
            if ($this->fetchRaw($id) === null) {
                throw new \Exception('Email profile not found.');
            }

            $sets = [];
            $params = [];

            foreach (self::UPDATABLE_COLUMNS as $column) {
                if (!array_key_exists($column, $data)) {
                    continue;
                }

                $value = $data[$column];

                if (in_array($column, ['smtp_password', 'imap_password'], true)) {
                    if (!is_string($value) || str_contains($value, '***')) {
                        continue;
                    }
                } elseif ($column === 'assigned_role_ids') {
                    $value = $this->normalizeRoles($value);
                } elseif (in_array($column, ['smtp_port', 'imap_port', 'is_active', 'is_default'], true)) {
                    $value = (int) $value;
                } else {
                    $value = (string) $value;
                }

                $sets[] = "{$column} = ?";
                $params[] = $value;
            }

            if ($sets !== []) {
                $sets[] = 'updated_at = NOW()';
                $params[] = $id;

                if ((int) ($data['is_default'] ?? 0) === 1) {
                    $this->db->exec('UPDATE comms_email_profiles SET is_default = 0');
                }

                $stmt = $this->db->prepare('UPDATE comms_email_profiles SET ' . implode(', ', $sets) . ' WHERE id = ?');
                $stmt->execute($params);
            }

            return $this->getProfile($id);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] updateProfile failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete a profile. The default profile cannot be deleted.
     */
    public function deleteProfile(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT id, is_default FROM comms_email_profiles WHERE id = ?');
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                return true;
            }

            if ((int) $row['is_default'] === 1) {
                throw new \Exception('The default email profile cannot be deleted.');
            }

            $stmt = $this->db->prepare('DELETE FROM comms_email_profiles WHERE id = ?');
            $stmt->execute([$id]);

            return true;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] deleteProfile failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mark a profile as the single default profile.
     */
    public function setDefault(int $id): bool
    {
        try {
            $this->db->exec('UPDATE comms_email_profiles SET is_default = 0');

            $stmt = $this->db->prepare('UPDATE comms_email_profiles SET is_default = 1, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$id]);

            return true;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] setDefault failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve the raw SMTP settings for a profile from the stored row.
     */
    public function getEffectiveSmtp(int $profileId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT id, email_address, display_name, smtp_host, smtp_port, smtp_username, smtp_password, is_active FROM comms_email_profiles WHERE id = ?');
            $stmt->execute([$profileId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                throw new \Exception('Email profile not found.');
            }

            if ((int) $row['is_active'] !== 1) {
                throw new \Exception('Email profile is not active.');
            }

            return [
                'host'       => $row['smtp_host'],
                'port'       => (int) $row['smtp_port'],
                'username'   => $row['smtp_username'],
                'password'   => $row['smtp_password'],
                'from_email' => $row['email_address'],
                'from_name'  => $row['display_name'],
            ];
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] getEffectiveSmtp failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Test SMTP connectivity via PHPMailer without sending a message.
     */
    public function testSmtp(int $profileId): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        try {
            $cfg = $this->getEffectiveSmtp($profileId);

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = $cfg['host'];
            $mail->Port       = $cfg['port'];
            $mail->SMTPAuth   = $cfg['username'] !== '';
            $mail->Username   = $cfg['username'];
            $mail->Password   = $cfg['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

            $mail->smtpConnect();
            $mail->smtpClose();

            return ['success' => true, 'message' => 'SMTP connection successful.'];
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] testSmtp failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'SMTP connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Test IMAP connectivity via the pure-PHP client and list folders.
     */
    public function testImap(int $profileId): array
    {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $client = null;

        try {
            $profile = $this->fetchRaw($profileId);

            if ($profile === null) {
                return ['success' => false, 'message' => 'Email profile not found.', 'folders' => []];
            }

            $config = $this->buildImapConfig($profile);
            $manager = new \Webklex\PHPIMAP\ClientManager();
            $client = $manager->make($config);
            $client->connect();

            $folders = [];
            foreach ($client->getFolders() as $folder) {
                $folders[] = (string) $folder->name;
            }

            return [
                'success' => true,
                'message' => 'IMAP connection successful.',
                'folders' => $folders,
            ];
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] testImap failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'IMAP connection failed: ' . $e->getMessage(), 'folders' => []];
        } finally {
            if ($client !== null) {
                try {
                    $client->disconnect();
                } catch (\Throwable $ignored) {
                }
            }
        }
    }

    /**
     * Read the audit-BCC email and toggle from school_settings.
     */
    public function getAuditBcc(): array
    {
        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM school_settings WHERE setting_key IN ('comms_email_audit_bcc', 'comms_email_audit_bcc_enabled')");
            $stmt->execute();

            $values = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $values[$row['setting_key']] = $row['setting_value'];
            }

            return [
                'email'   => (string) ($values['comms_email_audit_bcc'] ?? ''),
                'enabled' => $this->isTruthy((string) ($values['comms_email_audit_bcc_enabled'] ?? '')),
            ];
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] getAuditBcc failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Upsert the audit-BCC setting pair and return the refreshed values.
     */
    public function saveAuditBcc(string $email, bool $enabled): array
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO school_settings (setting_key, setting_value, updated_at)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
            );
            $stmt->execute(['comms_email_audit_bcc', trim($email)]);
            $stmt->execute(['comms_email_audit_bcc_enabled', $enabled ? '1' : '0']);

            return $this->getAuditBcc();
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] saveAuditBcc failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Return the default profile, or the first active profile when none is marked.
     */
    public function getDefaultProfile(): ?array
    {
        try {
            $profiles = $this->listProfiles();

            foreach ($profiles as $profile) {
                if ((int) ($profile['is_default'] ?? 0) === 1) {
                    return $profile;
                }
            }

            foreach ($profiles as $profile) {
                if ((int) ($profile['is_active'] ?? 0) === 1) {
                    return $profile;
                }
            }

            return null;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] getDefaultProfile failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function fetchRaw(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, label, email_address, display_name, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at FROM comms_email_profiles WHERE id = ?');
        $stmt->execute([$id]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function hydrate(array $row): array
    {
        $roleIds = json_decode((string) ($row['assigned_role_ids'] ?? ''), true);
        $roleIds = is_array($roleIds) ? array_map('intval', $roleIds) : [];

        return [
            'id'                => (int) $row['id'],
            'label'             => (string) $row['label'],
            'email_address'     => (string) $row['email_address'],
            'display_name'      => (string) $row['display_name'],
            'smtp_host'         => (string) $row['smtp_host'],
            'smtp_port'         => (int) $row['smtp_port'],
            'smtp_username'     => (string) $row['smtp_username'],
            'smtp_password'     => $this->maskPassword($row['smtp_password']),
            'imap_host'         => (string) $row['imap_host'],
            'imap_port'         => (int) $row['imap_port'],
            'imap_username'     => (string) $row['imap_username'],
            'imap_password'     => $this->maskPassword($row['imap_password']),
            'is_active'         => (int) $row['is_active'],
            'is_default'        => (int) $row['is_default'],
            'assigned_role_ids' => $roleIds,
            'created_at'        => (string) $row['created_at'],
            'updated_at'        => (string) $row['updated_at'],
        ];
    }

    /**
     * When the profiles table is empty, bootstrap a default profile from the
     * global SMTP configuration (.env). This lets the school-mailbox workspace
     * surface the already-configured info@ mailbox without manual setup, while
     * keeping config as the authoritative source for a freshly-initialised DB.
     */
    private function ensureConfigDefaultProfile(): void
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM comms_email_profiles');
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $email = (string) (\App\Config\Config::get('SMTP_FROM_EMAIL', ''));
        if ($email === '') {
            $email = (string) \App\Config\Config::get('SMTP_USERNAME', '');
        }
        if ($email === '') {
            return;
        }

        $host = (string) \App\Config\Config::get('SMTP_HOST', self::DEFAULT_HOST);
        $port = (int) \App\Config\Config::get('SMTP_PORT', self::SMTP_PORT);
        $username = $email;
        $password = (string) \App\Config\Config::get('SMTP_PASSWORD', '');
        $displayName = (string) \App\Config\Config::get('SMTP_FROM_NAME', 'Kingsway Preparatory School');

        try {
            $insert = $this->db->prepare(
                'INSERT INTO comms_email_profiles
                 (label, email_address, display_name, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NULL, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE updated_at = NOW()'
            );
            $insert->execute([
                'General',
                $email,
                $displayName,
                $host,
                $port,
                $username,
                $password,
                self::DEFAULT_HOST,
                self::IMAP_PORT,
                $username,
                $password,
            ]);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] config default profile bootstrap failed: ' . $e->getMessage());
        }
    }

    private function maskPassword(?string $val): string
    {
        if ($val === null || $val === '') {
            return '';
        }

        return '***';
    }

    private function normalizeRoles(mixed $roles): ?string
    {
        if ($roles === null || $roles === '') {
            return null;
        }

        $decoded = is_array($roles) ? $roles : json_decode((string) $roles, true);

        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('assigned_role_ids must be a JSON array or an array of integers.');
        }

        $clean = [];
        foreach ($decoded as $roleId) {
            $roleId = (int) $roleId;
            if ($roleId > 0) {
                $clean[] = $roleId;
            }
        }

        $clean = array_values(array_unique($clean));

        return $clean === [] ? null : json_encode($clean);
    }

    private function buildImapConfig(array $profile): array
    {
        $port = (int) ($profile['imap_port'] ?? self::IMAP_PORT);
        $encryption = ($port === 993 || $port === 990) ? 'ssl' : (($port === 143) ? 'tls' : 'none');

        return [
            'host'          => $profile['imap_host'] ?? self::DEFAULT_HOST,
            'port'          => $port,
            'encryption'    => $encryption,
            'validate_cert' => false,
            'username'      => (string) ($profile['imap_username'] ?? ''),
            'password'      => (string) ($profile['imap_password'] ?? ''),
            'protocol'      => 'imap',
        ];
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}