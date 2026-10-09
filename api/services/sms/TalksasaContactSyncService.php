<?php

declare(strict_types=1);

namespace App\API\Services\sms;

use App\API\Services\ReadReplicaService;

/**
 * Talksasa contact-group synchronization.
 *
 * Reads the school's contact numbers from materialized KingsWayReads
 * projections only (single-table reads, no request-path joins), then adds any
 * missing numbers to the configured Talksasa contact groups.
 *
 * Group mappings live in school_settings as talksasa_group_<name> = <group_id>
 * (e.g. talksasa_group_parents, talksasa_group_staff, talksasa_group_class-4).
 *
 * v1 policy: numbers are added only. Removal by Talksasa uid is not yet
 * implemented and is intentionally left out of this contract.
 */
final class TalksasaContactSyncService
{
    private const CLASS_PREFIX = 'class-';

    private string $baseUrl;
    private string $apiKey;

    public function __construct(private \PDO $db)
    {
        $this->baseUrl = rtrim(
            defined('TALKSASA_API_URL')
                ? (string) constant('TALKSASA_API_URL')
                : 'https://bulksms.talksasa.com/api/v3/',
            '/'
        );
        $this->apiKey = defined('SMS_API_KEY') ? (string) constant('SMS_API_KEY') : '';
    }

    /**
     * Configured Talksasa groups: local group name => Talksasa group id.
     *
     * @return array<string, string>
     */
    public function groupMappings(): array
    {
        $stmt = $this->db->prepare(
            "SELECT setting_key, setting_value FROM school_settings WHERE setting_key LIKE 'talksasa_group_%'"
        );
        $stmt->execute();
        $groups = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $groupName = str_replace('talksasa_group_', '', (string) $row['setting_key']);
            if ($groupName !== '' && $row['setting_value'] !== null && $row['setting_value'] !== '') {
                $groups[$groupName] = (string) $row['setting_value'];
            }
        }
        return $groups;
    }

    /**
     * Phone numbers of active guardians that are linked to at least one learner.
     * Source: mmv_guardian_link (student_parents + parents + persons composed).
     *
     * @return list<string>
     */
    public function parentPhones(): array
    {
        $stmt = $this->db->query(
            'SELECT DISTINCT parent_phone FROM ' . ReadReplicaService::qualifiedRef('guardian_link')
            . " WHERE parent_status = 'active' AND parent_phone IS NOT NULL"
        );
        return array_values(array_filter($stmt->fetchAll(\PDO::FETCH_COLUMN), static fn($v): bool => is_string($v) && $v !== ''));
    }

    /**
     * Phone numbers of active staff members.
     * Source: mmv_person_directory (staff flag columns).
     *
     * @return list<string>
     */
    public function staffPhones(): array
    {
        $stmt = $this->db->query(
            'SELECT DISTINCT phone FROM ' . ReadReplicaService::qualifiedRef('person_directory')
            . " WHERE staff_id IS NOT NULL AND staff_status = 'active' AND phone IS NOT NULL"
        );
        return array_values(array_filter($stmt->fetchAll(\PDO::FETCH_COLUMN), static fn($v): bool => is_string($v) && $v !== ''));
    }

    /**
     * Phone numbers of guardians whose learner is currently in the given class.
     * Two single-table projection reads intersected in PHP: the current class
     * roster from mmv_student_directory, then the guardians from
     * mmv_guardian_link for exactly those learners.
     *
     * @return list<string>
     */
    public function classParentPhones(int $classId): array
    {
        if ($classId <= 0) {
            return [];
        }
        $rosterStmt = $this->db->prepare(
            'SELECT student_id FROM ' . ReadReplicaService::qualifiedRef('student_directory')
            . ' WHERE class_id = :cid AND is_current_year = 1'
        );
        $rosterStmt->bindValue(':cid', $classId, \PDO::PARAM_INT);
        $rosterStmt->execute();
        $studentIds = array_map('intval', $rosterStmt->fetchAll(\PDO::FETCH_COLUMN));
        if ($studentIds === []) {
            return [];
        }

        $phones = [];
        foreach (array_chunk($studentIds, 500) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $stmt = $this->db->prepare(
                'SELECT DISTINCT parent_phone FROM ' . ReadReplicaService::qualifiedRef('guardian_link')
                . " WHERE parent_phone IS NOT NULL AND student_id IN ($placeholders)"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $phone) {
                if (is_string($phone) && $phone !== '') {
                    $phones[$phone] = true;
                }
            }
        }
        return array_keys($phones);
    }

    /**
     * Numbers wanted for one configured group name.
     *
     * @return list<string>
     */
    public function groupPhones(string $groupName): array
    {
        if ($groupName === 'parents') {
            return $this->parentPhones();
        }
        if ($groupName === 'staff') {
            return $this->staffPhones();
        }
        if (str_starts_with($groupName, self::CLASS_PREFIX)) {
            $suffix = substr($groupName, strlen(self::CLASS_PREFIX));
            if (ctype_digit($suffix)) {
                return $this->classParentPhones((int) $suffix);
            }
            return [];
        }
        // Any other configured group receives both audiences (previous behaviour).
        return array_unique(array_merge($this->parentPhones(), $this->staffPhones()));
    }

    /**
     * Run the configured sync.
     *
     * @return array{synced_groups: int, synced_parent_numbers: int, synced_staff_numbers: int}
     */
    public function sync(): array
    {
        $groups = $this->groupMappings();
        $parentNumbers = $this->parentPhones();
        $staffNumbers = $this->staffPhones();

        foreach ($groups as $groupName => $groupId) {
            $existing = $this->fetchGroupPhones($groupId);
            $wanted = $this->groupPhones($groupName);
            foreach (array_unique($wanted) as $phone) {
                if (!in_array($phone, $existing, true)) {
                    $this->addGroupPhone($groupId, $phone);
                }
            }
        }

        return [
            'synced_groups' => count($groups),
            'synced_parent_numbers' => count($parentNumbers),
            'synced_staff_numbers' => count($staffNumbers),
        ];
    }

    /**
     * Existing contacts of one Talksasa group.
     *
     * @return list<string>
     */
    private function fetchGroupPhones(string $groupId): array
    {
        $response = $this->request('GET', '/contacts/' . rawurlencode($groupId) . '/all');
        if ($response === null) {
            return [];
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }
        $contacts = $decoded['data'] ?? $decoded['contacts'] ?? [];
        if (!is_array($contacts)) {
            return [];
        }
        $phones = [];
        foreach ($contacts as $contact) {
            if (is_array($contact)) {
                $phone = $contact['phone'] ?? $contact['number'] ?? '';
                if (is_string($phone) && $phone !== '') {
                    $phones[] = $phone;
                }
            }
        }
        return $phones;
    }

    private function addGroupPhone(string $groupId, string $phone): bool
    {
        return $this->request('POST', '/contacts/' . rawurlencode($groupId) . '/store', ['phone' => $phone]) !== null;
    }

    private function request(string $method, string $path, ?array $body = null): ?string
    {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            return null;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body ?? []));
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error !== '' && $error !== false) {
            error_log('Talksasa contact sync request error: ' . $error);
            return null;
        }
        if (!is_string($response)) {
            return null;
        }
        if ($method === 'POST') {
            // Per-number add failures are logged and never abort the batch.
            $decoded = json_decode($response, true);
            if (is_array($decoded) && (isset($decoded['error']) || isset($decoded['errors']))) {
                error_log('Talksasa contact add rejected: ' . substr($response, 0, 300));
            }
        }
        return $response;
    }
}
