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

    public const MAILBOX_PERSONAL = 'personal';

    public const MAILBOX_GROUP = 'group';

    private const UPDATABLE_COLUMNS = [
        'label',
        'email_address',
        'display_name',
        'mailbox_type',
        'owner_user_id',
        'signature',
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

            $sql = 'SELECT id, label, email_address, display_name, mailbox_type, owner_user_id, signature, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at FROM comms_email_profiles';
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
            'mailbox_type'  => (string) ($row['mailbox_type'] ?? self::MAILBOX_GROUP),
            'owner_user_id' => isset($row['owner_user_id']) && $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null,
            'signature'     => (string) ($row['signature'] ?? ''),
            'smtp_host'     => (string) $row['smtp_host'],
            'smtp_port'     => (int) $row['smtp_port'],
            'smtp_username' => (string) $row['smtp_username'],
            'smtp_password' => EmailCredentialCipher::decrypt((string) ($row['smtp_password'] ?? '')),
            'imap_host'     => (string) $row['imap_host'],
            'imap_port'     => (int) $row['imap_port'],
            'imap_username' => (string) $row['imap_username'],
            'imap_password' => EmailCredentialCipher::decrypt((string) ($row['imap_password'] ?? '')),
            'is_active'     => (int) $row['is_active'],
            'is_default'    => (int) $row['is_default'],
            'assigned_role_ids' => json_decode((string) ($row['assigned_role_ids'] ?? ''), true) ?: [],
        ];
    }

    /**
     * Return a single profile with masked secrets, or null when not found.
     * With $withMembers the explicit member list (resolved names) is included
     * for the administration UI.
     */
    public function getProfile(int $id, bool $withMembers = false): ?array
    {
        try {
            $row = $this->fetchRaw($id);

            if ($row === null) {
                return null;
            }

            $profile = $this->hydrate($row);

            if ($withMembers) {
                $profile['members'] = $this->listMembers($id);
            }

            return $profile;
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
            $mailboxType = $this->normalizeMailboxType($data['mailbox_type'] ?? self::MAILBOX_GROUP);
            $ownerUserId = $this->normalizeOwnerUserId($data['owner_user_id'] ?? null);
            $signature = trim((string) ($data['signature'] ?? ''));

            if ($mailboxType === self::MAILBOX_PERSONAL && $ownerUserId === null) {
                throw new \InvalidArgumentException('A personal mailbox requires owner_user_id.');
            }
            if ($ownerUserId !== null) {
                $this->assertUserExists($ownerUserId);
            }

            if ($isDefault === 1) {
                $this->db->exec('UPDATE comms_email_profiles SET is_default = 0');
            }

            $stmt = $this->db->prepare(
                'INSERT INTO comms_email_profiles
                 (label, email_address, display_name, mailbox_type, owner_user_id, signature, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $label,
                $email,
                $displayName,
                $mailboxType,
                $ownerUserId,
                $signature === '' ? null : $signature,
                $smtpHost,
                $smtpPort,
                $smtpUsername,
                EmailCredentialCipher::encrypt($smtpPassword),
                $imapHost,
                $imapPort,
                $imapUsername,
                EmailCredentialCipher::encrypt($imapPassword),
                $isActive,
                $isDefault,
                $roles,
            ]);

            $profileId = (int) $this->db->lastInsertId();

            if ($mailboxType === self::MAILBOX_PERSONAL && $ownerUserId !== null) {
                $this->setMembers($profileId, [['user_id' => $ownerUserId, 'access_level' => 'send']], $ownerUserId);
            }
            if (array_key_exists('members', $data)) {
                $this->setMembers($profileId, $this->normalizeMembers($data['members']), null);
            }

            return $this->getProfile($profileId);
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
            $existing = $this->fetchRaw($id);
            if ($existing === null) {
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
                    $value = EmailCredentialCipher::encrypt($value);
                } elseif ($column === 'assigned_role_ids') {
                    $value = $this->normalizeRoles($value);
                } elseif ($column === 'mailbox_type') {
                    $value = $this->normalizeMailboxType($value);
                } elseif ($column === 'owner_user_id') {
                    $value = $this->normalizeOwnerUserId($value);
                    if ($value !== null) {
                        $this->assertUserExists($value);
                    }
                } elseif ($column === 'signature') {
                    $value = trim((string) $value);
                    $value = $value === '' ? null : $value;
                } elseif (in_array($column, ['smtp_port', 'imap_port', 'is_active', 'is_default'], true)) {
                    $value = (int) $value;
                } else {
                    $value = (string) $value;
                }

                $sets[] = "{$column} = ?";
                $params[] = $value;
            }

            $mailboxType = array_key_exists('mailbox_type', $data)
                ? $this->normalizeMailboxType($data['mailbox_type'])
                : (string) ($existing['mailbox_type'] ?? self::MAILBOX_GROUP);
            $ownerUserId = array_key_exists('owner_user_id', $data)
                ? $this->normalizeOwnerUserId($data['owner_user_id'])
                : (isset($existing['owner_user_id']) && $existing['owner_user_id'] !== null ? (int) $existing['owner_user_id'] : null);

            if ($mailboxType === self::MAILBOX_PERSONAL && $ownerUserId === null) {
                throw new \InvalidArgumentException('A personal mailbox requires owner_user_id.');
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

            if (array_key_exists('members', $data)) {
                $this->setMembers($id, $this->normalizeMembers($data['members']), null);
            }

            $oldOwnerId = isset($existing['owner_user_id']) && $existing['owner_user_id'] !== null ? (int) $existing['owner_user_id'] : null;
            $ownerChanged = array_key_exists('owner_user_id', $data) && $oldOwnerId !== $ownerUserId;
            if ($ownerChanged && $oldOwnerId !== null) {
                $stmt = $this->db->prepare('DELETE FROM comms_email_profile_users WHERE profile_id = ? AND user_id = ?');
                $stmt->execute([$id, $oldOwnerId]);
            }
            if ($ownerChanged && $ownerUserId !== null) {
                $stmt = $this->db->prepare(
                    'INSERT INTO comms_email_profile_users (profile_id, user_id, access_level, added_by, created_at)
                     VALUES (?, ?, \'send\', NULL, NOW())
                     ON DUPLICATE KEY UPDATE access_level = \'send\''
                );
                $stmt->execute([$id, $ownerUserId]);
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
            $stmt = $this->db->prepare('SELECT id, email_address, display_name, smtp_host, smtp_port, smtp_username, smtp_password, signature, is_active FROM comms_email_profiles WHERE id = ?');
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
                'password'   => EmailCredentialCipher::decrypt((string) ($row['smtp_password'] ?? '')),
                'from_email' => $row['email_address'],
                'from_name'  => $row['display_name'],
                'profile_id' => (int) $row['id'],
                'signature'  => (string) ($row['signature'] ?? ''),
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

    /**
     * Explicit members of a mailbox, with resolved staff names for the admin UI.
     */
    public function listMembers(int $profileId): array
    {
        try {
            $stmt = $this->db->prepare('SELECT user_id, access_level, can_read, can_reply, can_compose, added_by, created_at FROM comms_email_profile_users WHERE profile_id = ? ORDER BY created_at ASC, user_id ASC');
            $stmt->execute([$profileId]);
            $members = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if ($members === []) {
                return [];
            }

            $userIds = array_values(array_unique(array_map(static fn (array $m): int => (int) $m['user_id'], $members)));
            $users = $this->fetchUsersByIds($userIds);

            $ownerStmt = $this->db->prepare('SELECT owner_user_id FROM comms_email_profiles WHERE id = ?');
            $ownerStmt->execute([$profileId]);
            $ownerId = $ownerStmt->fetchColumn();
            $ownerId = ($ownerId === false || $ownerId === null) ? null : (int) $ownerId;

            $result = [];
            foreach ($members as $member) {
                $userId = (int) $member['user_id'];
                $user = $users[$userId] ?? null;
                $fullName = trim((string) ($user['full_name'] ?? ''));
                $result[] = [
                    'user_id'      => $userId,
                    'username'     => (string) ($user['username'] ?? ''),
                    'full_name'    => $fullName !== '' ? $fullName : (string) ($user['username'] ?? ('User #' . $userId)),
                    'email'        => (string) ($user['email'] ?? ''),
                    'status'       => (string) ($user['status'] ?? ''),
                    'access_level' => (string) $member['access_level'],
                    'can_read'     => (int) $member['can_read'] === 1,
                    'can_reply'    => (int) $member['can_reply'] === 1,
                    'can_compose'  => (int) $member['can_compose'] === 1,
                    'is_owner'     => $ownerId !== null && $ownerId === $userId,
                    'added_by'     => $member['added_by'] === null ? null : (int) $member['added_by'],
                    'created_at'   => (string) $member['created_at'],
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] listMembers failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Replace the explicit member list of a mailbox. The owner of a personal
     * mailbox is always kept with send access so a direct call cannot lock the
     * owner out of their own mailbox.
     */
    public function setMembers(int $profileId, array $members, ?int $actorId): array
    {
        try {
            if ($this->fetchRaw($profileId) === null) {
                throw new \Exception('Email profile not found.');
            }

            $normalized = [];
            foreach ($this->normalizeMembers($members) as $row) {
                $normalized[(int) $row['user_id']] = $row;
            }

            $ownerStmt = $this->db->prepare('SELECT owner_user_id FROM comms_email_profiles WHERE id = ?');
            $ownerStmt->execute([$profileId]);
            $ownerId = $ownerStmt->fetchColumn();
            if ($ownerId !== false && $ownerId !== null) {
                $normalized[(int) $ownerId] = ['user_id' => (int) $ownerId, 'can_read' => 1, 'can_reply' => 1, 'can_compose' => 1];
            }

            $this->db->beginTransaction();

            $delete = $this->db->prepare('DELETE FROM comms_email_profile_users WHERE profile_id = ?');
            $delete->execute([$profileId]);

            $insert = $this->db->prepare(
                'INSERT INTO comms_email_profile_users (profile_id, user_id, access_level, can_read, can_reply, can_compose, added_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE access_level = VALUES(access_level), can_read = VALUES(can_read), can_reply = VALUES(can_reply), can_compose = VALUES(can_compose), added_by = VALUES(added_by)'
            );
            foreach ($normalized as $member) {
                $canRead = (int) $member['can_read'];
                $canReply = (int) $member['can_reply'];
                $canCompose = (int) $member['can_compose'];
                $accessLevel = ($canRead && ($canReply || $canCompose)) ? 'send' : 'read';
                $insert->execute([$profileId, $member['user_id'], $accessLevel, $canRead, $canReply, $canCompose, $actorId]);
            }

            $this->db->commit();

            return $this->listMembers($profileId);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[EmailProfileService] setMembers failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Mailboxes a staff user may open in the mail client, with the access level
     * each grant provides. Rules: School Administrator (role 4) sees everything
     * with send access; explicit pivot memberships and personal ownership come
     * next; group mailboxes additionally keep the legacy assigned_role_ids
     * behaviour (empty list = all staff, intersection = matching roles).
     *
     * @return array<int, array<string, mixed>>
     */
    public function mailboxesForUser(int $userId, array $roleIds, bool $isSchoolAdmin, bool $isSystemAdmin = false): array
    {
        // System Administrator configures mail accounts only and must never
        // gain mailbox visibility in the mail client. A user who is also a
        // School Administrator keeps their school-admin mailbox duties.
        if ($isSystemAdmin && !$isSchoolAdmin) {
            return [];
        }

        try {
            $roleIds = array_map('intval', $roleIds);

            $pivotStmt = $this->db->prepare('SELECT profile_id, access_level, can_read, can_reply, can_compose FROM comms_email_profile_users WHERE user_id = ?');
            $pivotStmt->execute([$userId]);
            $pivot = [];
            foreach ($pivotStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $pivot[(int) $row['profile_id']] = [
                    'access_level' => (string) $row['access_level'],
                    'can_read'     => (int) $row['can_read'] === 1,
                    'can_reply'    => (int) $row['can_reply'] === 1,
                    'can_compose'  => (int) $row['can_compose'] === 1,
                ];
            }

            $visible = [];
            foreach ($this->listProfiles() as $profile) {
                $isActive = (int) ($profile['is_active'] ?? 0) === 1;
                // Ordinary staff only ever see mailboxes that are ready to use.
                // A School Administrator additionally sees not-yet-configured
                // accounts (is_active = 0) so they know the mailbox is expected
                // and can finish its setup.
                if (!$isActive && !$isSchoolAdmin) {
                    continue;
                }

                $profileId = (int) $profile['id'];
                $isOwner = ($profile['owner_user_id'] ?? null) === $userId;
                $capabilities = null;

                if ($isSchoolAdmin || $isOwner) {
                    $capabilities = ['can_read' => true, 'can_reply' => true, 'can_compose' => true];
                } elseif (isset($pivot[$profileId])) {
                    $capabilities = [
                        'can_read'    => $pivot[$profileId]['can_read'],
                        'can_reply'   => $pivot[$profileId]['can_reply'],
                        'can_compose' => $pivot[$profileId]['can_compose'],
                    ];
                } elseif (($profile['mailbox_type'] ?? self::MAILBOX_GROUP) === self::MAILBOX_GROUP) {
                    $assigned = $profile['assigned_role_ids'] ?? [];
                    if (!is_array($assigned) || $assigned === [] || array_intersect($assigned, $roleIds) !== []) {
                        $capabilities = ['can_read' => true, 'can_reply' => true, 'can_compose' => true];
                    }
                }

                if ($capabilities === null) {
                    continue;
                }

                // A not-yet-configured mailbox is listed for administrators but
                // grants no capability until the credentials are set.
                if (!$isActive) {
                    $capabilities = ['can_read' => false, 'can_reply' => false, 'can_compose' => false];
                }

                $visible[] = [
                    'id'                => $profileId,
                    'label'             => (string) $profile['label'],
                    'email_address'     => (string) $profile['email_address'],
                    'display_name'      => (string) $profile['display_name'],
                    'mailbox_type'      => (string) ($profile['mailbox_type'] ?? self::MAILBOX_GROUP),
                    'owner_user_id'     => $profile['owner_user_id'],
                    'signature'         => $profile['signature'] ?? '',
                    'is_active'         => (int) $profile['is_active'],
                    'is_default'        => (int) $profile['is_default'],
                    'assigned_role_ids' => $profile['assigned_role_ids'] ?? [],
                    'is_owner'          => $isOwner,
                    'can_read'          => $capabilities['can_read'],
                    'can_reply'         => $capabilities['can_reply'],
                    'can_compose'       => $capabilities['can_compose'],
                    'can_send'          => $capabilities['can_reply'] || $capabilities['can_compose'],
                ];
            }

            return $visible;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[EmailProfileService] mailboxesForUser failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve a profile for mail-client use after checking the caller's access.
     * Returns the unmasked credentials row plus the access level, or null when
     * the mailbox is not visible to the caller.
     */
    public function resolveAccessibleProfile(int $profileId, int $userId, array $roleIds, bool $isSchoolAdmin, bool $isSystemAdmin = false): ?array
    {
        foreach ($this->mailboxesForUser($userId, $roleIds, $isSchoolAdmin, $isSystemAdmin) as $mailbox) {
            if ((int) ($mailbox['id'] ?? 0) !== $profileId) {
                continue;
            }
            // A not-yet-configured account is listed for management but cannot
            // be opened until an administrator stores its credentials.
            if (($mailbox['is_active'] ?? 1) === 0) {
                return null;
            }
            $credentials = $this->getCredentials($profileId);
            if ($credentials === null) {
                return null;
            }

            return [
                'profile'     => $credentials,
                'can_read'    => (bool) ($mailbox['can_read'] ?? false),
                'can_reply'   => (bool) ($mailbox['can_reply'] ?? false),
                'can_compose' => (bool) ($mailbox['can_compose'] ?? false),
                'can_send'    => (bool) ($mailbox['can_send'] ?? false),
                'is_owner'    => (bool) $mailbox['is_owner'],
                'mailbox'     => $mailbox,
            ];
        }

        return null;
    }

    private function fetchUsersByIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $stmt = $this->db->prepare("SELECT id, person_id, username, status FROM users WHERE id IN ({$placeholders})");
        $stmt->execute($userIds);

        $users = [];
        $personIds = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $userId = (int) $row['id'];
            $users[$userId] = [
                'username'  => (string) $row['username'],
                'status'    => (string) $row['status'],
                'full_name' => '',
                'email'     => '',
            ];
            if ($row['person_id'] !== null) {
                $personIds[$userId] = (int) $row['person_id'];
            }
        }

        if ($personIds !== []) {
            $uniquePersonIds = array_values(array_unique(array_values($personIds)));
            $placeholders = implode(',', array_fill(0, count($uniquePersonIds), '?'));
            $stmt = $this->db->prepare("SELECT id, first_name, last_name, email FROM persons WHERE id IN ({$placeholders})");
            $stmt->execute($uniquePersonIds);
            $persons = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $persons[(int) $row['id']] = $row;
            }
            foreach ($personIds as $userId => $personId) {
                if (isset($persons[$personId])) {
                    $person = $persons[$personId];
                    $users[$userId]['full_name'] = trim((string) $person['first_name'] . ' ' . (string) $person['last_name']);
                    $users[$userId]['email'] = (string) ($person['email'] ?? '');
                }
            }
        }

        return $users;
    }

    private function normalizeMailboxType(mixed $value): string
    {
        $type = strtolower(trim((string) $value));

        return $type === self::MAILBOX_PERSONAL ? self::MAILBOX_PERSONAL : self::MAILBOX_GROUP;
    }

    private function normalizeOwnerUserId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        $userId = (int) $value;

        return $userId > 0 ? $userId : null;
    }

    /**
     * @return array<int, array{user_id:int, access_level:string}>
     */
    private function normalizeMembers(mixed $members): array
    {
        if ($members === null || $members === '') {
            return [];
        }
        if (is_string($members)) {
            $members = json_decode($members, true);
        }
        if (!is_array($members)) {
            throw new \InvalidArgumentException('members must be an array of user ids or {user_id, can_read, can_reply, can_compose} objects.');
        }

        $clean = [];
        foreach ($members as $member) {
            if (is_array($member)) {
                $userId = (int) ($member['user_id'] ?? $member['id'] ?? 0);
                $flags = $this->memberFlags($member);
            } else {
                $userId = (int) $member;
                $flags = ['can_read' => 1, 'can_reply' => 1, 'can_compose' => 1];
            }

            if ($userId <= 0) {
                continue;
            }
            // A later grant wins; merge by OR-ing capabilities so a member listed
            // twice never loses access.
            if (!isset($clean[$userId])) {
                $clean[$userId] = $flags;
            } else {
                $clean[$userId] = [
                    'can_read'    => ($clean[$userId]['can_read'] || $flags['can_read']) ? 1 : 0,
                    'can_reply'   => ($clean[$userId]['can_reply'] || $flags['can_reply']) ? 1 : 0,
                    'can_compose' => ($clean[$userId]['can_compose'] || $flags['can_compose']) ? 1 : 0,
                ];
            }
        }

        $result = [];
        foreach ($clean as $userId => $flags) {
            $this->assertUserExists((int) $userId);
            $result[] = ['user_id' => (int) $userId] + $flags;
        }

        return $result;
    }

    /**
     * Resolve read/reply/compose flags from an inbound member payload. Accepts
     * explicit booleans or the legacy `access_level` string ('read' or 'send').
     *
     * @return array{can_read:int, can_reply:int, can_compose:int}
     */
    private function memberFlags(array $member): array
    {
        $hasFlags = array_key_exists('can_read', $member)
            || array_key_exists('can_reply', $member)
            || array_key_exists('can_compose', $member);

        if (!$hasFlags) {
            $level = strtolower(trim((string) ($member['access_level'] ?? 'send')));
            return $level === 'read'
                ? ['can_read' => 1, 'can_reply' => 0, 'can_compose' => 0]
                : ['can_read' => 1, 'can_reply' => 1, 'can_compose' => 1];
        }

        $flag = static function ($value): int {
            if (is_bool($value)) {
                return $value ? 1 : 0;
            }
            return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
        };

        return [
            'can_read'    => $flag($member['can_read'] ?? 0),
            'can_reply'   => $flag($member['can_reply'] ?? 0),
            'can_compose' => $flag($member['can_compose'] ?? 0),
        ];
    }

    private function assertUserExists(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() === false) {
            throw new \InvalidArgumentException('User #' . $userId . ' does not exist.');
        }
    }

    private function fetchRaw(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, label, email_address, display_name, mailbox_type, owner_user_id, signature, smtp_host, smtp_port, smtp_username, smtp_password, imap_host, imap_port, imap_username, imap_password, is_active, is_default, assigned_role_ids, created_at, updated_at FROM comms_email_profiles WHERE id = ?');
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
            'mailbox_type'      => (string) ($row['mailbox_type'] ?? self::MAILBOX_GROUP),
            'owner_user_id'     => isset($row['owner_user_id']) && $row['owner_user_id'] !== null ? (int) $row['owner_user_id'] : null,
            'signature'         => (string) ($row['signature'] ?? ''),
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
            'credentials_encrypted' => EmailCredentialCipher::enabled(),
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
                EmailCredentialCipher::encrypt($password),
                self::DEFAULT_HOST,
                self::IMAP_PORT,
                $username,
                EmailCredentialCipher::encrypt($password),
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
            'password'      => EmailCredentialCipher::decrypt((string) ($profile['imap_password'] ?? '')),
            'protocol'      => 'imap',
        ];
    }

    private function isTruthy(string $value): bool
    {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }
}