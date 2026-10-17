<?php

namespace App\API\Services\sms;

use PDO;
use App\API\Services\ReadReplicaService;
use App\API\Services\SystemCommsConfigService;

/**
 * SMSService — provider-agnostic messaging orchestration + persistence.
 *
 * Wraps every capability of the configured messaging provider (SMS, WhatsApp,
 * Voice, MMS, Hashed SMS, Campaigns, Contact Groups, Contacts, Profile/Balance
 * and the message log) and records local state against the canonical
 * communications tables so the school manages everything from within the
 * system. Transport is delegated to SMSGateway / WhatsAppGateway; switching
 * provider requires no change here.
 */
class SMSService
{
    private PDO $db;
    private SMSGateway $gateway;
    private SystemCommsConfigService $configService;

    public function __construct(PDO $db, ?SMSGateway $gateway = null)
    {
        $this->db = $db;
        $this->configService = new SystemCommsConfigService($db);
        $this->gateway = $gateway ?? new SMSGateway($this->configService->effectiveSmsConfig());
    }

    public function providerName(): string
    {
        return $this->gateway->providerName();
    }

    // ── Account / gateway info ──────────────────────────────────────────────

    public function gatewayInfo(): array
    {
        $cfg = $this->configService->effectiveSmsConfig();
        $apiKey = (string) ($cfg['api_key'] ?? '');
        return [
            'provider' => $cfg['provider'] ?? $this->providerName(),
            'sender_id' => $cfg['sender_id'] ?? '',
            'api_url' => $cfg['api_url'] ?? '',
            'configured' => $apiKey !== '',
            'api_key_masked' => $apiKey !== '' ? mb_substr($apiKey, 0, 3) . '...' . mb_substr($apiKey, -3) : '',
        ];
    }

    public function balance(): array
    {
        try {
            $result = $this->gateway->balance();
        } catch (\Throwable $e) {
            return ['success' => false, 'balance' => 0, 'message' => $e->getMessage()];
        }
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'balance' => $this->extractBalance($result['data'] ?? null),
            'message' => $result['message'] ?? ($result['ok'] ?? false ? 'Balance retrieved' : 'Balance unavailable'),
            'raw' => $result['data'] ?? null,
        ];
    }

    public function profile(): array
    {
        try {
            $result = $this->gateway->profile();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'profile' => null];
        }
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'profile' => $result['data'] ?? null,
        ];
    }

    // ── Contact groups ──────────────────────────────────────────────────────

    public function groups(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, provider, provider_group_id, entity_type, entity_ref, contact_count, sync_status, last_error, last_synced_at
               FROM communication_groups ORDER BY name"
        );
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($groups as &$group) {
            $count = $this->db->prepare("SELECT COUNT(*) FROM contact_directory WHERE group_id = ?");
            $count->execute([(int) $group['id']]);
            $group['local_contact_count'] = (int) $count->fetchColumn();
            $group['provisioned'] = !empty($group['provider_group_id']);
        }
        return $groups;
    }

    public function remoteGroups(): array
    {
        try {
            $result = $this->gateway->listGroups();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'groups' => []];
        }
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'groups' => $this->normalizeList($result['data'] ?? null),
        ];
    }

    public function createGroup(string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Group name is required');
        }
        $result = $this->gateway->createGroup($name);
        if (!($result['ok'] ?? false)) {
            throw new \RuntimeException($result['message'] ?: 'Group could not be created at the provider');
        }
        $providerGroupId = $this->extractUid($result['data'] ?? null);
        $stmt = $this->db->prepare(
            "INSERT INTO communication_groups (name, provider, provider_group_id, entity_type, created_by, sync_status, last_synced_at)
             VALUES (?, ?, ?, 'external', ?, 'synced', NOW())"
        );
        $stmt->execute([$name, $this->providerName(), $providerGroupId, $this->currentUserId()]);
        return ['id' => (int) $this->db->lastInsertId(), 'name' => $name, 'provider_group_id' => $providerGroupId];
    }

    public function updateGroup(int $id, string $name): array
    {
        $group = $this->findGroup($id);
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Group name is required');
        }
        if (!empty($group['provider_group_id'])) {
            $result = $this->gateway->updateGroup($group['provider_group_id'], $name);
            if (!($result['ok'] ?? false)) {
                throw new \RuntimeException($result['message'] ?: 'Group could not be updated at the provider');
            }
        }
        $this->db->prepare("UPDATE communication_groups SET name = ?, sync_status = 'synced', last_synced_at = NOW() WHERE id = ?")->execute([$name, $id]);
        return ['id' => $id, 'name' => $name];
    }

    public function deleteGroup(int $id): array
    {
        $group = $this->findGroup($id);
        if (!empty($group['provider_group_id'])) {
            $this->gateway->deleteGroup($group['provider_group_id']);
        }
        $this->db->prepare("DELETE FROM contact_directory WHERE group_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM communication_groups WHERE id = ?")->execute([$id]);
        return ['id' => $id];
    }

    /**
     * Pull provider groups into the local registry and link by name.
     */
    public function syncGroups(): array
    {
        $remote = $this->gateway->listGroups();
        if (!($remote['ok'] ?? false)) {
            throw new \RuntimeException($remote['message'] ?: 'Provider groups could not be fetched');
        }
        $remoteGroups = $this->normalizeList($remote['data'] ?? null);
        $provider = $this->providerName();
        $inserted = 0;
        $linked = 0;

        foreach ($remoteGroups as $remoteGroup) {
            $uid = $this->extractUid($remoteGroup);
            $name = (string) ($remoteGroup['name'] ?? $remoteGroup['group_name'] ?? $remoteGroup['title'] ?? '');
            if ($uid === '' || $name === '') {
                continue;
            }
            $existing = $this->db->prepare("SELECT id FROM communication_groups WHERE provider = ? AND provider_group_id = ?");
            $existing->execute([$provider, $uid]);
            if ($existing->fetchColumn()) {
                continue;
            }
            $byName = $this->db->prepare("SELECT id FROM communication_groups WHERE provider = ? AND provider_group_id IS NULL AND name = ? LIMIT 1");
            $byName->execute([$provider, $name]);
            $localId = $byName->fetchColumn();
            if ($localId) {
                $this->db->prepare("UPDATE communication_groups SET provider_group_id = ?, sync_status = 'synced', last_synced_at = NOW() WHERE id = ?")
                    ->execute([$uid, (int) $localId]);
                $linked++;
            } else {
                $this->db->prepare(
                    "INSERT INTO communication_groups (name, provider, provider_group_id, entity_type, created_by, sync_status, last_synced_at)
                     VALUES (?, ?, ?, 'external', ?, 'synced', NOW())"
                )->execute([$name, $provider, $uid, $this->currentUserId()]);
                $inserted++;
            }
        }

        return ['inserted' => $inserted, 'linked' => $linked, 'remote' => count($remoteGroups)];
    }

    /**
     * Create any local group that is not yet present at the provider.
     */
    public function provisionGroups(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name FROM communication_groups WHERE provider = ? AND (provider_group_id IS NULL OR provider_group_id = '') ORDER BY id"
        );
        $stmt->execute([$this->providerName()]);
        $provisioned = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
            $result = $this->gateway->createGroup($group['name']);
            if (!($result['ok'] ?? false)) {
                $this->db->prepare("UPDATE communication_groups SET sync_status = 'error', last_error = ? WHERE id = ?")
                    ->execute([substr($result['message'] ?? 'Provider error', 0, 500), (int) $group['id']]);
                continue;
            }
            $uid = $this->extractUid($result['data'] ?? null);
            $this->db->prepare("UPDATE communication_groups SET provider_group_id = ?, sync_status = 'synced', last_synced_at = NOW(), last_error = NULL WHERE id = ?")
                ->execute([$uid, (int) $group['id']]);
            $provisioned++;
        }
        return ['provisioned' => $provisioned];
    }

    /**
     * One-shot reconciliation: pull provider groups, create any missing local
     * groups at the provider, then push unlinked contacts for every group.
     */
    public function syncAll(): array
    {
        $groups = $this->syncGroups();
        $provisioned = $this->provisionGroups();

        $pushed = 0;
        $failed = 0;
        $stmt = $this->db->prepare("SELECT id FROM communication_groups WHERE provider = ? ORDER BY id");
        $stmt->execute([$this->providerName()]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $group) {
            try {
                $result = $this->syncContacts((int) $group['id']);
                $pushed += (int) ($result['pushed'] ?? 0);
                $failed += (int) ($result['failed'] ?? 0);
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return [
            'groups' => $groups,
            'provisioned' => $provisioned,
            'contacts_pushed' => $pushed,
            'contacts_failed' => $failed,
        ];
    }

    // ── Contacts ────────────────────────────────────────────────────────────

    public function contacts(int $groupId, string $search = ''): array
    {
        $sql = "SELECT id, contact_type, name, email, phone, group_id, provider, provider_contact_uid, sync_status, last_synced_at
                  FROM contact_directory WHERE group_id = ?";
        $params = [$groupId];
        if ($search !== '') {
            $sql .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= " ORDER BY name LIMIT 1000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addContact(int $groupId, array $data): array
    {
        $group = $this->findGroup($groupId);
        $phone = $this->normalizePhone((string) ($data['phone'] ?? ''));
        if ($phone === '') {
            throw new \InvalidArgumentException('A valid phone number is required');
        }
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName);
        if ($name === '') {
            $name = $phone;
        }

        $providerUid = null;
        if (!empty($group['provider_group_id'])) {
            $result = $this->gateway->storeContact($group['provider_group_id'], $phone, $firstName, $lastName);
            if (!($result['ok'] ?? false)) {
                throw new \RuntimeException($result['message'] ?: 'Contact could not be stored at the provider');
            }
            $providerUid = $this->extractUid($result['data'] ?? null);
        }

        $this->db->prepare(
            "INSERT INTO contact_directory (contact_type, name, email, phone, group_id, provider, provider_contact_uid, source_type, sync_status, last_synced_at)
             VALUES ('external', ?, ?, ?, ?, ?, ?, 'manual', ?, NOW())"
        )->execute([
            $name,
            $data['email'] ?? null,
            $phone,
            $groupId,
            $this->providerName(),
            $providerUid,
            $providerUid ? 'synced' : 'pending',
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'provider_contact_uid' => $providerUid];
    }

    public function updateContact(int $contactId, array $data): array
    {
        $contact = $this->findContact($contactId);
        $phone = $this->normalizePhone((string) ($data['phone'] ?? $contact['phone']));
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $name = trim($firstName . ' ' . $lastName) ?: (string) ($data['name'] ?? $contact['name']);

        if (!empty($contact['provider_contact_uid'])) {
            $group = $this->findGroup((int) $contact['group_id']);
            if (!empty($group['provider_group_id'])) {
                $result = $this->gateway->updateContact($group['provider_group_id'], $contact['provider_contact_uid'], $phone, $firstName, $lastName);
                if (!($result['ok'] ?? false)) {
                    throw new \RuntimeException($result['message'] ?: 'Contact could not be updated at the provider');
                }
            }
        }

        $this->db->prepare("UPDATE contact_directory SET name = ?, email = ?, phone = ?, sync_status = 'synced', last_synced_at = NOW() WHERE id = ?")
            ->execute([$name, $data['email'] ?? $contact['email'], $phone, $contactId]);
        return ['id' => $contactId];
    }

    public function deleteContact(int $contactId): array
    {
        $contact = $this->findContact($contactId);
        if (!empty($contact['provider_contact_uid']) && !empty($contact['group_id'])) {
            $group = $this->findGroup((int) $contact['group_id']);
            if (!empty($group['provider_group_id'])) {
                $this->gateway->deleteContact($group['provider_group_id'], $contact['provider_contact_uid']);
            }
        }
        $this->db->prepare("DELETE FROM contact_directory WHERE id = ?")->execute([$contactId]);
        return ['id' => $contactId];
    }

    /**
     * Push local contacts for a group to the provider (our DB is source of truth).
     */
    public function syncContacts(int $groupId): array
    {
        $group = $this->findGroup($groupId);
        if (empty($group['provider_group_id'])) {
            $provision = $this->gateway->createGroup($group['name']);
            if (!($provision['ok'] ?? false)) {
                throw new \RuntimeException($provision['message'] ?: 'Group must be provisioned before syncing contacts');
            }
            $uid = $this->extractUid($provision['data'] ?? null);
            $this->db->prepare("UPDATE communication_groups SET provider_group_id = ?, sync_status = 'synced', last_synced_at = NOW() WHERE id = ?")
                ->execute([$uid, $groupId]);
            $group['provider_group_id'] = $uid;
        }

        $stmt = $this->db->prepare("SELECT id, name, email, phone, provider_contact_uid FROM contact_directory WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pushed = 0;
        $failed = 0;
        foreach ($contacts as $contact) {
            try {
                if (!empty($contact['provider_contact_uid'])) {
                    continue;
                }
                $phone = $this->normalizePhone((string) $contact['phone']);
                if ($phone === '') {
                    continue;
                }
                $parts = explode(' ', trim((string) $contact['name']), 2);
                $result = $this->gateway->storeContact($group['provider_group_id'], $phone, $parts[0] ?? '', $parts[1] ?? '');
                if (!($result['ok'] ?? false)) {
                    $failed++;
                    $this->db->prepare("UPDATE contact_directory SET sync_status = 'error' WHERE id = ?")->execute([(int) $contact['id']]);
                    continue;
                }
                $uid = $this->extractUid($result['data'] ?? null);
                $this->db->prepare("UPDATE contact_directory SET provider_contact_uid = ?, provider = ?, sync_status = 'synced', last_synced_at = NOW() WHERE id = ?")
                    ->execute([$uid, $this->providerName(), (int) $contact['id']]);
                $pushed++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM contact_directory WHERE group_id = ?");
        $countStmt->execute([$groupId]);
        $this->db->prepare("UPDATE communication_groups SET contact_count = ?, sync_status = ?, last_synced_at = NOW() WHERE id = ?")
            ->execute([(int) $countStmt->fetchColumn(), $failed > 0 ? 'partial' : 'synced', $groupId]);

        return ['group_id' => $groupId, 'pushed' => $pushed, 'failed' => $failed, 'total' => count($contacts)];
    }

    /**
     * Resolve school entities (parents/staff/students/vendors) into contacts
     * for a mirror group, so provider groups reflect our database.
     */
    public function resolveAudience(string $entityType, string $search = ''): array
    {
        switch ($entityType) {
            case 'parents':
                $sql = "SELECT full_name AS name, email, phone FROM " . ReadReplicaService::qualifiedRef('person_directory') . "
                         WHERE parent_id IS NOT NULL AND parent_status = 'active' AND phone IS NOT NULL AND phone <> ''";
                $searchColumn = 'full_name';
                break;
            case 'staff':
                $sql = "SELECT full_name AS name, email, phone FROM " . ReadReplicaService::qualifiedRef('staff_directory') . "
                         WHERE user_status = 'active' AND staff_status = 'active' AND phone IS NOT NULL AND phone <> ''";
                $searchColumn = 'full_name';
                break;
            case 'students':
                $sql = "SELECT full_name AS name, email, phone FROM " . ReadReplicaService::qualifiedRef('student_directory') . "
                         WHERE student_status = 'active' AND phone IS NOT NULL AND phone <> ''";
                $searchColumn = 'full_name';
                break;
            case 'vendors':
                $sql = "SELECT name, email, phone FROM suppliers WHERE status = 'active' AND phone IS NOT NULL AND phone <> ''";
                $searchColumn = 'name';
                break;
            default:
                return [];
        }

        $search = trim($search);
        $params = [];
        if ($search !== '') {
            $sql .= " AND ({$searchColumn} LIKE ? OR phone LIKE ? OR email LIKE ?)";
            $like = '%' . $search . '%';
            $params = [$like, $like, $like];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Materialise a school directory audience (parents/staff/students/vendors)
     * into a group's contact_directory membership so the group is a real,
     * editable, provider-syncable list. Existing directory-sourced rows are
     * refreshed in place, stale ones are removed, and manual rows are never
     * touched.
     */
    public function importAudience(int $groupId): array
    {
        $group = $this->findGroup($groupId);
        $entityType = strtolower((string) ($group['entity_type'] ?? ''));
        $contactType = ['parents' => 'parent', 'staff' => 'staff', 'students' => 'student', 'vendors' => 'external'][$entityType] ?? null;
        if ($contactType === null) {
            throw new \InvalidArgumentException('This group is not linked to a school directory audience.');
        }

        $audience = $this->resolveAudience($entityType);
        $provider = $this->providerName();

        $existingStmt = $this->db->prepare(
            "SELECT id, phone, source_type, provider_contact_uid FROM contact_directory WHERE group_id = ?"
        );
        $existingStmt->execute([$groupId]);
        $existing = $existingStmt->fetchAll(PDO::FETCH_ASSOC);

        $byPhone = [];
        $importedPhones = [];
        foreach ($existing as $row) {
            $digits = preg_replace('/\D+/', '', (string) $row['phone']) ?? '';
            if ($digits !== '') {
                $byPhone[$digits] = $row;
            }
            if ((string) ($row['source_type'] ?? '') === $entityType) {
                $importedPhones[$digits] = $row;
            }
        }

        $insert = $this->db->prepare(
            "INSERT INTO contact_directory (contact_type, name, email, phone, group_id, provider, source_type, sync_status, last_synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NULL)"
        );
        $refresh = $this->db->prepare(
            "UPDATE contact_directory SET name = ?, email = ? WHERE id = ?"
        );

        $added = 0;
        $updated = 0;
        $seen = [];
        foreach ($audience as $member) {
            $phone = $this->normalizePhone((string) ($member['phone'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if ($digits === '') {
                continue;
            }
            $seen[$digits] = true;
            $name = trim((string) ($member['name'] ?? '')) ?: $phone;
            $email = $member['email'] ?? null;

            if (isset($byPhone[$digits])) {
                $row = $byPhone[$digits];
                if ((string) ($row['source_type'] ?? '') === $entityType) {
                    $refresh->execute([$name, $email, (int) $row['id']]);
                    $updated++;
                }
                continue;
            }

            $insert->execute([$contactType, $name, $email, $phone, $groupId, $provider, $entityType]);
            $added++;
        }

        $removed = 0;
        $providerGroupId = (string) ($group['provider_group_id'] ?? '');
        $delete = $this->db->prepare("DELETE FROM contact_directory WHERE id = ?");
        foreach ($importedPhones as $digits => $row) {
            if (isset($seen[$digits])) {
                continue;
            }
            // Deactivated/deleted directory members are removed locally and,
            // when already mirrored, also deleted from the provider so the
            // provider list never keeps sending to a departed person.
            $uid = (string) ($row['provider_contact_uid'] ?? '');
            if ($uid !== '' && $providerGroupId !== '') {
                try {
                    $this->gateway->deleteContact($providerGroupId, $uid);
                } catch (\Throwable $e) {
                    // Local removal still proceeds; the next contact sync
                    // reports any provider-side leftovers.
                }
            }
            $delete->execute([(int) $row['id']]);
            $removed++;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM contact_directory WHERE group_id = ?");
        $countStmt->execute([$groupId]);
        $total = (int) $countStmt->fetchColumn();
        $this->db->prepare("UPDATE communication_groups SET contact_count = ?, sync_status = 'pending', last_error = NULL WHERE id = ?")
            ->execute([$total, $groupId]);

        return [
            'group_id' => $groupId,
            'entity_type' => $entityType,
            'audience' => count($audience),
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'total' => $total,
        ];
    }

    // ── Messaging dispatch ──────────────────────────────────────────────────

    public function send(array $params): array
    {
        $mode = strtolower((string) ($params['mode'] ?? $params['channel'] ?? 'sms'));
        $allowed = ['sms', 'whatsapp', 'voice', 'mms', 'hashed', 'campaign'];
        if (!in_array($mode, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported messaging mode');
        }

        $message = trim((string) ($params['message'] ?? $params['body'] ?? ''));
        if ($message === '' && $mode !== 'mms') {
            throw new \InvalidArgumentException('Message body is required');
        }

        $type = $mode === 'campaign' ? 'sms' : $mode;
        $scheduleTime = $params['schedule_time'] ?? null;
        if ($scheduleTime !== null && $scheduleTime !== '') {
            $scheduleTime = $this->normalizeScheduleTime((string) $scheduleTime);
        } else {
            $scheduleTime = null;
        }

        $targets = $this->resolveTargets($mode, $params);
        if (empty($targets)) {
            throw new \InvalidArgumentException('At least one recipient is required');
        }

        $channelMeta = array_filter([
            'mode' => $mode,
            'schedule_time' => $scheduleTime,
            'media_url' => $params['media_url'] ?? null,
            'language' => $params['language'] ?? null,
            'gender' => $params['gender'] ?? null,
            'dlt_template_id' => $params['dlt_template_id'] ?? null,
            'recipient_format' => $params['recipient_format'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        $communicationId = $this->createCommunicationRow($type, $mode, $message, $channelMeta, $scheduleTime);
        $endpointRows = $this->createEndpointRows($communicationId, $mode, $targets);

        // Bulk sends (non-campaign, more than one recipient, no provider-side
        // schedule) run as ONE durable batch job executed by the Python
        // platform, with PHP applying the per-recipient outcomes. Single
        // sends and campaign calls stay on the synchronous path.
        if ($scheduleTime === null && count($endpointRows) > 1) {
            $staged = (new SmsBatchService($this->db))->stage($communicationId, $mode, $endpointRows, $this->currentUserId());
            if ($staged !== null) {
                return [
                    'communication_id' => $communicationId,
                    'mode' => $mode,
                    'provider' => $this->providerName(),
                    'status' => 'queued',
                    'batch' => true,
                    'job_id' => $staged['job_id'],
                    'sent' => 0,
                    'failed' => 0,
                    'total' => count($endpointRows),
                    'queue_uid' => null,
                ];
            }
        }

        $summary = $this->deliver($communicationId, $mode, $message, $endpointRows, $params, $scheduleTime);

        return [
            'communication_id' => $communicationId,
            'mode' => $mode,
            'provider' => $this->providerName(),
            'status' => $summary['status'],
            'batch' => false,
            'job_id' => null,
            'sent' => $summary['sent'],
            'failed' => $summary['failed'],
            'total' => count($endpointRows),
            'queue_uid' => $summary['queue_uid'] ?? null,
        ];
    }

    public function queueStatus(string $queueUid): array
    {
        $result = $this->gateway->queueStatus($queueUid);
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'status' => $result['data'] ?? null,
        ];
    }

    // ── Message log ─────────────────────────────────────────────────────────

    public function listMessages(array $filters = []): array
    {
        $sql = "SELECT c.id, c.type, c.provider, c.status, c.subject, SUBSTRING(c.body, 1, 200) AS preview,
                       c.scheduled_at, c.processed_at, c.created_at, c.last_error,
                       (SELECT COUNT(*) FROM communication_recipients r WHERE r.communication_id = c.id) AS recipient_count,
                       (SELECT COUNT(*) FROM communication_recipients r WHERE r.communication_id = c.id AND r.status IN ('sent','delivered')) AS sent_count,
                       (SELECT COUNT(*) FROM communication_recipients r WHERE r.communication_id = c.id AND r.status = 'failed') AS failed_count
                  FROM communications c WHERE 1 = 1";
        $params = [];

        if (!empty($filters['type'])) {
            $sql .= " AND c.type = ?";
            $params[] = $filters['type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= " AND c.created_at >= ?";
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= " AND c.created_at <= ?";
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['search'])) {
            $sql .= " AND (c.subject LIKE ? OR c.body LIKE ?)";
            $like = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $sql .= " ORDER BY c.created_at DESC, c.id DESC LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stats = $this->messageStats();
        return ['messages' => $messages, 'stats' => $stats];
    }

    public function messageStats(): array
    {
        $stmt = $this->db->query(
            "SELECT type, status, COUNT(*) AS total FROM communications GROUP BY type, status"
        );
        $byType = [];
        $byStatus = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byType[$row['type']] = ($byType[$row['type']] ?? 0) + (int) $row['total'];
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + (int) $row['total'];
        }
        return ['by_type' => $byType, 'by_status' => $byStatus];
    }

    public function remoteMessages(): array
    {
        $result = $this->gateway->listMessages();
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'messages' => $this->normalizeList($result['data'] ?? null),
        ];
    }

    public function remoteMessage(string $uid): array
    {
        $result = $this->gateway->getMessage($uid);
        return [
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? '',
            'message_detail' => $result['data'] ?? null,
        ];
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function resolveTargets(string $mode, array $params): array
    {
        if ($mode === 'campaign') {
            $ids = $params['contact_list_ids'] ?? $params['recipients'] ?? [];
            $ids = is_array($ids) ? $ids : array_filter(array_map('trim', explode(',', (string) $ids)));
            $uids = [];
            foreach ($ids as $id) {
                $group = $this->findGroup((int) $id);
                if (empty($group['provider_group_id'])) {
                    throw new \RuntimeException("Group '{$group['name']}' has not been provisioned with the provider yet");
                }
                $uids[] = $group['provider_group_id'];
            }
            return $uids;
        }

        if (!empty($params['group_id'])) {
            $contacts = $this->contacts((int) $params['group_id']);
            return array_values(array_filter(array_map(static fn ($c) => trim((string) $c['phone']), $contacts)));
        }

        if (!empty($params['audience'])) {
            $audience = $this->resolveAudience((string) $params['audience']);
            return array_values(array_filter(array_map(static fn ($c) => trim((string) $c['phone']), $audience)));
        }

        $recipients = $params['recipients'] ?? $params['recipient'] ?? [];
        if (is_string($recipients)) {
            $recipients = array_filter(array_map('trim', explode(',', $recipients)));
        }
        $normalized = [];
        foreach ((array) $recipients as $recipient) {
            $phone = $this->normalizePhone((string) $recipient);
            if ($phone !== '') {
                $normalized[] = $phone;
            }
        }
        return array_values(array_unique($normalized));
    }

    private function createCommunicationRow(string $type, string $mode, string $message, array $channelMeta, ?string $scheduleTime): int
    {
        $status = $scheduleTime !== null ? 'scheduled' : 'queued';
        $stmt = $this->db->prepare(
            "INSERT INTO communications (type, provider, subject, body, channel_meta, status, priority, scheduled_at, sender_id)
             VALUES (?, ?, ?, ?, ?, ?, 'medium', ?, ?)"
        );
        $stmt->execute([
            $type,
            $this->providerName(),
            $this->subjectFor($mode),
            $message,
            $channelMeta ? json_encode($channelMeta, JSON_UNESCAPED_SLASHES) : null,
            $status,
            $scheduleTime,
            $this->currentUserId(),
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function createEndpointRows(int $communicationId, string $mode, array $targets): array
    {
        $channel = $mode === 'campaign' ? 'sms' : $mode;
        $rows = [];
        $recipientStmt = $this->db->prepare("INSERT INTO communication_recipients (communication_id, recipient_id, status) VALUES (?, NULL, 'queued')");
        $endpointStmt = $this->db->prepare("INSERT INTO communication_recipient_endpoints (communication_recipient_id, channel, address, status) VALUES (?, ?, ?, 'pending')");
        foreach ($targets as $target) {
            $recipientStmt->execute([$communicationId]);
            $recipientId = (int) $this->db->lastInsertId();
            $endpointStmt->execute([$recipientId, $channel, $target]);
            $rows[] = ['id' => (int) $this->db->lastInsertId(), 'recipient_id' => $recipientId, 'address' => $target];
        }
        return $rows;
    }

    private function deliver(int $communicationId, string $mode, string $message, array $endpoints, array $params, ?string $scheduleTime): array
    {
        $sent = 0;
        $failed = 0;
        $queueUid = null;

        if ($mode === 'campaign') {
            try {
                $result = $this->gateway->sendCampaign(implode(',', array_column($endpoints, 'address')), $message, $scheduleTime, $params['dlt_template_id'] ?? null);
                foreach ($endpoints as $endpoint) {
                    $this->recordSuccess($endpoint, $result);
                }
                $sent = count($endpoints);
            } catch (\Throwable $e) {
                foreach ($endpoints as $endpoint) {
                    $this->recordFailure($endpoint, $e->getMessage());
                }
                $failed = count($endpoints);
            }
        } else {
            foreach ($endpoints as $endpoint) {
                try {
                    $result = $this->sendOne($mode, $endpoint['address'], $message, $params, $scheduleTime);
                    if ($mode === 'hashed' && is_array($result['data'] ?? null) && !empty($result['data']['queue_uid'])) {
                        $queueUid = (string) $result['data']['queue_uid'];
                    }
                    $this->recordSuccess($endpoint, $result);
                    $sent++;
                } catch (\Throwable $e) {
                    $this->recordFailure($endpoint, $e->getMessage());
                    $failed++;
                }
            }
        }

        $status = ($failed === 0 && $sent > 0)
            ? ($scheduleTime !== null ? 'scheduled' : 'sent')
            : 'failed';
        $this->db->prepare("UPDATE communications SET status = ?, processed_at = NOW() WHERE id = ?")->execute([$status, $communicationId]);
        if ($failed > 0) {
            $this->db->prepare("UPDATE communications SET last_error = ? WHERE id = ?")->execute(["{$failed} recipient(s) failed", $communicationId]);
        }

        return ['status' => $status, 'sent' => $sent, 'failed' => $failed, 'queue_uid' => $queueUid];
    }

    private function sendOne(string $mode, string $address, string $message, array $params, ?string $scheduleTime = null): array
    {
        switch ($mode) {
            case 'whatsapp':
                return $this->gateway->sendWhatsApp($address, $message, $params['media_url'] ?? null);
            case 'voice':
                return $this->gateway->sendVoice($address, $message, $params['language'] ?? null, $params['gender'] ?? null);
            case 'mms':
                return $this->gateway->sendMMS($address, $message, (string) ($params['media_url'] ?? ''));
            case 'hashed':
                return $this->gateway->sendHashed($address, $message, (string) ($params['recipient_format'] ?? 'hashed'));
            case 'sms':
            default:
                if ($scheduleTime !== null) {
                    return $this->gateway->sendBulk($address, $message, $scheduleTime);
                }
                return $this->gateway->send($address, $message);
        }
    }

    private function recordSuccess(array $endpoint, array $result): void
    {
        $providerId = $this->extractUid($result['data'] ?? null);
        $this->db->prepare("INSERT INTO communication_delivery_attempts (endpoint_id, attempt_no, request_status, provider_message_id, provider_status, request_started_at, request_finished_at, raw_response) VALUES (?, 1, 'accepted', ?, ?, NOW(), NOW(), ?)")
            ->execute([$endpoint['id'], $providerId, $result['status'] ?? 'accepted', json_encode($result['data'] ?? null)]);
        $this->db->prepare("UPDATE communication_recipient_endpoints SET status = 'sent', provider_message_id = ?, provider_status = ?, attempt_count = attempt_count + 1, last_attempt_at = NOW(), delivered_at = NOW() WHERE id = ?")
            ->execute([$providerId, $result['status'] ?? 'sent', $endpoint['id']]);
        $this->db->prepare("UPDATE communication_recipients SET status = 'sent', delivered_at = NOW(), delivery_attempts = delivery_attempts + 1, last_attempt_at = NOW() WHERE id = ?")
            ->execute([$endpoint['recipient_id']]);
    }

    private function recordFailure(array $endpoint, string $error): void
    {
        $this->db->prepare("INSERT INTO communication_delivery_attempts (endpoint_id, attempt_no, request_status, error_message, request_started_at, request_finished_at) VALUES (?, 1, 'error', ?, NOW(), NOW())")
            ->execute([$endpoint['id'], substr($error, 0, 1000)]);
        $this->db->prepare("UPDATE communication_recipient_endpoints SET status = 'failed', provider_status = 'failed', attempt_count = attempt_count + 1, last_attempt_at = NOW(), last_error = ? WHERE id = ?")
            ->execute([substr($error, 0, 1000), $endpoint['id']]);
        $this->db->prepare("UPDATE communication_recipients SET status = 'failed', delivery_attempts = delivery_attempts + 1, last_attempt_at = NOW(), error_message = ? WHERE id = ?")
            ->execute([substr($error, 0, 1000), $endpoint['recipient_id']]);
    }

    private function findGroup(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM communication_groups WHERE id = ?");
        $stmt->execute([$id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            throw new \InvalidArgumentException('Contact group not found');
        }
        return $group;
    }

    private function findContact(int $id): array
    {
        $stmt = $this->db->prepare("SELECT * FROM contact_directory WHERE id = ?");
        $stmt->execute([$id]);
        $contact = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$contact) {
            throw new \InvalidArgumentException('Contact not found');
        }
        return $contact;
    }

    private function subjectFor(string $mode): string
    {
        return ucfirst($mode === 'hashed' ? 'Hashed SMS' : ($mode === 'campaign' ? 'SMS Campaign' : $mode));
    }

    private function normalizeScheduleTime(string $value): string
    {
        $ts = strtotime($value);
        if ($ts === false) {
            throw new \InvalidArgumentException('Invalid schedule time');
        }
        return date('Y-m-d H:i', $ts);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '254')) {
            return $digits;
        }
        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '254' . substr($digits, 1);
        }
        if (strlen($digits) === 9) {
            return '254' . $digits;
        }
        return $digits;
    }

    private function extractUid($data): ?string
    {
        if (is_string($data) && $data !== '') {
            return $data;
        }
        if (!is_array($data)) {
            return null;
        }
        foreach (['uid', 'id', 'contact_uid', 'group_id', 'contact_list_id', 'message_id', 'queue_uid'] as $key) {
            if (!empty($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                $found = $this->extractUid($value);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    private function normalizeList($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        if ($this->isList($data)) {
            return array_values(array_filter($data, 'is_array'));
        }
        foreach (['groups', 'contacts', 'messages', 'reports', 'items', 'result'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }
        return [$data];
    }

    private function isList(array $data): bool
    {
        if ($data === []) {
            return true;
        }
        return array_keys($data) === range(0, count($data) - 1);
    }

    private function extractBalance($data): float
    {
        if (is_numeric($data)) {
            return (float) $data;
        }
        if (!is_array($data)) {
            return 0.0;
        }
        foreach (['balance', 'sms_unit', 'unit', 'credits', 'amount', 'remaining_balance', 'remaining_units'] as $key) {
            if (isset($data[$key]) && is_numeric(str_replace(['Ksh', 'KES', ' ', ','], '', (string) $data[$key])) ) {
                $num = (string) $data[$key];
                $num = preg_replace('/[^0-9.]/', '', $num);
                if ($num !== '') {
                    return (float) $num;
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

    private function currentUserId(): ?int
    {
        $uid = $_SESSION['user_id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
        return $uid !== null && (int) $uid > 0 ? (int) $uid : null;
    }
}
