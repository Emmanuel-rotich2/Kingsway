<?php

namespace App\API\Services;

use App\API\Includes\AuditLogger;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Canonical owner of the system_ip_rules registry and request-time policy.
 *
 * Policy semantics:
 * - matching active deny rules always deny;
 * - when at least one active allow rule exists, an address must match one;
 * - without active allow rules, access remains open unless denied.
 */
final class IpAccessControlService
{
    private PDO $db;

    private const ALLOWED_LIMITS = [25, 50, 100];
    private const RULE_TYPES = ['allow', 'deny'];
    private const STATUSES = ['active', 'scheduled', 'expired', 'disabled'];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Resolve the address used for policy decisions.
     *
     * Forwarded headers are considered only when REMOTE_ADDR belongs to an
     * explicitly configured TRUSTED_PROXY_CIDRS entry.
     */
    public static function resolveClientIp(): string
    {
        $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!self::isValidIp($remoteAddress)) {
            return '';
        }

        if (!self::isTrustedProxy($remoteAddress)) {
            return $remoteAddress;
        }

        $candidates = [];
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidates[] = trim(
                (string) $_SERVER['HTTP_CF_CONNECTING_IP']
            );
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (
                explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'])
                as $candidate
            ) {
                $candidates[] = trim($candidate);
            }
        }
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $candidates[] = trim((string) $_SERVER['HTTP_X_REAL_IP']);
        }

        foreach ($candidates as $candidate) {
            if (self::isValidIp($candidate)) {
                return $candidate;
            }
        }

        return $remoteAddress;
    }

    /**
     * Return a filtered, paginated, actor-enriched rule registry.
     */
    public function getRegistry(
        array $filters,
        string $currentIp
    ): array {
        $search = trim((string) ($filters['search'] ?? ''));
        if (strlen($search) > 200) {
            $search = substr($search, 0, 200);
        }

        $ruleType = strtolower(
            trim((string) ($filters['rule_type'] ?? ''))
        );
        if (
            $ruleType !== '' &&
            !in_array($ruleType, self::RULE_TYPES, true)
        ) {
            throw new InvalidArgumentException(
                'Rule type must be allow or deny'
            );
        }

        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (
            $status !== '' &&
            !in_array($status, self::STATUSES, true)
        ) {
            throw new InvalidArgumentException(
                'Rule status is not supported'
            );
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = (int) ($filters['limit'] ?? 50);
        if (!in_array($limit, self::ALLOWED_LIMITS, true)) {
            $limit = 50;
        }

        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(
                r.cidr LIKE ?
                OR r.description LIKE ?
                OR cu.username LIKE ?
                OR cu.first_name LIKE ?
                OR cu.last_name LIKE ?
                OR uu.username LIKE ?
                OR uu.first_name LIKE ?
                OR uu.last_name LIKE ?
            )';
            array_push(
                $params,
                $term,
                $term,
                $term,
                $term,
                $term,
                $term,
                $term,
                $term
            );
        }

        if ($ruleType !== '') {
            $where[] = 'r.rule_type = ?';
            $params[] = $ruleType;
        }

        if ($status !== '') {
            $where[] = $this->statusCondition($status);
        }

        $whereSql = implode(' AND ', $where);
        $joins = '
            LEFT JOIN users cu ON cu.id = r.created_by
            LEFT JOIN users uu ON uu.id = r.updated_by
        ';

        $statusExpression = $this->statusExpression();
        $summaryStatement = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(
                    CASE
                        WHEN $statusExpression = 'active'
                         AND r.rule_type = 'allow' THEN 1
                        ELSE 0
                    END
                ), 0) AS active_allow,
                COALESCE(SUM(
                    CASE
                        WHEN $statusExpression = 'active'
                         AND r.rule_type = 'deny' THEN 1
                        ELSE 0
                    END
                ), 0) AS active_deny,
                COALESCE(SUM(
                    CASE WHEN $statusExpression = 'scheduled' THEN 1
                    ELSE 0 END
                ), 0) AS scheduled,
                COALESCE(SUM(
                    CASE WHEN $statusExpression = 'expired' THEN 1
                    ELSE 0 END
                ), 0) AS expired,
                COALESCE(SUM(
                    CASE WHEN $statusExpression = 'disabled' THEN 1
                    ELSE 0 END
                ), 0) AS disabled
             FROM system_ip_rules r
             $joins
             WHERE $whereSql"
        );
        $summaryStatement->execute($params);
        $summary = $summaryStatement->fetch(PDO::FETCH_ASSOC) ?: [];

        $total = (int) ($summary['total'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        // LIMIT/OFFSET are interpolated only after strict integer bounds.
        $rowStatement = $this->db->prepare(
            "SELECT
                r.id,
                r.rule_type,
                r.cidr,
                r.description,
                r.enabled,
                r.starts_at,
                r.expires_at,
                r.created_by,
                r.updated_by,
                r.created_at,
                r.updated_at,
                COALESCE(
                    NULLIF(
                        TRIM(CONCAT_WS(' ', cu.first_name, cu.last_name)),
                        ''
                    ),
                    cu.username
                ) AS created_by_name,
                COALESCE(
                    NULLIF(
                        TRIM(CONCAT_WS(' ', uu.first_name, uu.last_name)),
                        ''
                    ),
                    uu.username
                ) AS updated_by_name,
                $statusExpression AS status
             FROM system_ip_rules r
             $joins
             WHERE $whereSql
             ORDER BY
                r.enabled DESC,
                CASE r.rule_type WHEN 'deny' THEN 0 ELSE 1 END,
                r.updated_at DESC,
                r.id DESC
             LIMIT $limit OFFSET $offset"
        );
        $rowStatement->execute($params);
        $rows = $rowStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['enabled'] = (int) $row['enabled'];
            $row['created_by'] = $row['created_by'] !== null
                ? (int) $row['created_by']
                : null;
            $row['updated_by'] = $row['updated_by'] !== null
                ? (int) $row['updated_by']
                : null;
            if (!self::isValidCidr((string) $row['cidr'])) {
                $row['status'] = 'invalid';
            }
            $row['matches_current_ip'] =
                $currentIp !== '' &&
                self::cidrContains((string) $row['cidr'], $currentIp)
                    ? 1
                    : 0;
        }
        unset($row);

        $decision = $currentIp !== ''
            ? $this->evaluate($currentIp)
            : [
                'allowed' => true,
                'reason' => 'client_ip_unavailable',
                'active_allow_rules' => 0,
                'active_deny_rules' => 0,
                'matched_allow_rule_ids' => [],
                'matched_deny_rule_ids' => [],
            ];

        return [
            'rules' => $rows,
            'summary' => [
                'total' => $total,
                'active_allow' => (int) (
                    $summary['active_allow'] ?? 0
                ),
                'active_deny' => (int) (
                    $summary['active_deny'] ?? 0
                ),
                'scheduled' => (int) ($summary['scheduled'] ?? 0),
                'expired' => (int) ($summary['expired'] ?? 0),
                'disabled' => (int) ($summary['disabled'] ?? 0),
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'search' => $search,
                'rule_type' => $ruleType,
                'status' => $status,
            ],
            'available_filters' => [
                'rule_types' => self::RULE_TYPES,
                'statuses' => self::STATUSES,
            ],
            'current_ip' => $currentIp,
            'current_decision' => $decision,
            'generated_at' => date('c'),
        ];
    }

    /**
     * Evaluate active rules for one address.
     */
    public function evaluate(string $ipAddress): array
    {
        if (!self::isValidIp($ipAddress)) {
            throw new InvalidArgumentException(
                'A valid client IP address is required'
            );
        }

        $statement = $this->db->query(
            "SELECT
                id,
                rule_type,
                cidr,
                enabled,
                starts_at,
                expires_at
             FROM system_ip_rules
             WHERE enabled = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY id"
        );
        $rules = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $this->evaluateRows(
            $ipAddress,
            $rules,
            new DateTimeImmutable('now')
        );
    }

    public function createRule(
        array $data,
        int $actorUserId,
        string $currentIp
    ): array {
        $this->assertActorAndClient($actorUserId, $currentIp);

        return $this->transactional(function () use (
            $data,
            $actorUserId,
            $currentIp
        ) {
            $rules = $this->getRulesForUpdate();
            $normalized = $this->normalizeRuleData($data);
            $this->assertNoEquivalentRule(
                $rules,
                $normalized['rule_type'],
                $normalized['cidr']
            );

            $candidateRules = $rules;
            $candidateRules[] = array_merge($normalized, ['id' => 0]);
            $this->assertCurrentClientRemainsAllowed(
                $candidateRules,
                $currentIp
            );

            try {
                $statement = $this->db->prepare(
                    'INSERT INTO system_ip_rules (
                        rule_type,
                        cidr,
                        description,
                        enabled,
                        starts_at,
                        expires_at,
                        created_by,
                        updated_by,
                        created_at,
                        updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                );
                $statement->execute([
                    $normalized['rule_type'],
                    $normalized['cidr'],
                    $normalized['description'],
                    $normalized['enabled'],
                    $normalized['starts_at'],
                    $normalized['expires_at'],
                    $actorUserId,
                    $actorUserId,
                ]);
            } catch (PDOException $error) {
                if ((string) $error->getCode() === '23000') {
                    throw new DomainException(
                        'An equivalent IP rule already exists'
                    );
                }
                throw $error;
            }

            $ruleId = (int) $this->db->lastInsertId();
            $created = array_merge(
                $normalized,
                [
                    'id' => $ruleId,
                    'created_by' => $actorUserId,
                    'updated_by' => $actorUserId,
                ]
            );
            $this->writeAudit(
                'ip_rule_create',
                $ruleId,
                $actorUserId,
                ['after' => $this->auditSnapshot($created)]
            );

            return $created;
        });
    }

    public function updateRule(
        int $ruleId,
        array $data,
        int $actorUserId,
        string $currentIp
    ): array {
        if ($ruleId <= 0) {
            throw new InvalidArgumentException(
                'A valid IP rule ID is required'
            );
        }
        $this->assertActorAndClient($actorUserId, $currentIp);

        return $this->transactional(function () use (
            $ruleId,
            $data,
            $actorUserId,
            $currentIp
        ) {
            $rules = $this->getRulesForUpdate();
            $index = $this->findRuleIndex($rules, $ruleId);
            if ($index === null) {
                throw new OutOfBoundsException('IP rule not found');
            }

            $existing = $rules[$index];
            $normalized = $this->normalizeRuleData($data, $existing);
            $this->assertNoEquivalentRule(
                $rules,
                $normalized['rule_type'],
                $normalized['cidr'],
                $ruleId
            );

            $updated = array_merge(
                $existing,
                $normalized,
                ['updated_by' => $actorUserId]
            );
            $candidateRules = $rules;
            $candidateRules[$index] = $updated;
            $this->assertCurrentClientRemainsAllowed(
                $candidateRules,
                $currentIp
            );

            try {
                $statement = $this->db->prepare(
                    'UPDATE system_ip_rules
                     SET rule_type = ?,
                         cidr = ?,
                         description = ?,
                         enabled = ?,
                         starts_at = ?,
                         expires_at = ?,
                         updated_by = ?,
                         updated_at = NOW()
                     WHERE id = ?'
                );
                $statement->execute([
                    $normalized['rule_type'],
                    $normalized['cidr'],
                    $normalized['description'],
                    $normalized['enabled'],
                    $normalized['starts_at'],
                    $normalized['expires_at'],
                    $actorUserId,
                    $ruleId,
                ]);
            } catch (PDOException $error) {
                if ((string) $error->getCode() === '23000') {
                    throw new DomainException(
                        'An equivalent IP rule already exists'
                    );
                }
                throw $error;
            }

            $this->writeAudit(
                'ip_rule_update',
                $ruleId,
                $actorUserId,
                [
                    'before' => $this->auditSnapshot($existing),
                    'after' => $this->auditSnapshot($updated),
                ]
            );

            return $updated;
        });
    }

    public function deleteRule(
        int $ruleId,
        int $actorUserId,
        string $currentIp
    ): array {
        if ($ruleId <= 0) {
            throw new InvalidArgumentException(
                'A valid IP rule ID is required'
            );
        }
        $this->assertActorAndClient($actorUserId, $currentIp);

        return $this->transactional(function () use (
            $ruleId,
            $actorUserId,
            $currentIp
        ) {
            $rules = $this->getRulesForUpdate();
            $index = $this->findRuleIndex($rules, $ruleId);
            if ($index === null) {
                throw new OutOfBoundsException('IP rule not found');
            }

            $existing = $rules[$index];
            $candidateRules = $rules;
            array_splice($candidateRules, $index, 1);
            $this->assertCurrentClientRemainsAllowed(
                $candidateRules,
                $currentIp
            );

            $statement = $this->db->prepare(
                'DELETE FROM system_ip_rules WHERE id = ?'
            );
            $statement->execute([$ruleId]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('The IP rule could not be deleted');
            }

            $this->writeAudit(
                'ip_rule_delete',
                $ruleId,
                $actorUserId,
                ['before' => $this->auditSnapshot($existing)]
            );

            return [
                'id' => $ruleId,
                'deleted' => true,
            ];
        });
    }

    /**
     * Match an IPv4 or IPv6 address against a CIDR. Bare addresses are treated
     * as /32 or /128 for compatibility with legacy rows.
     */
    public static function cidrContains(
        string $cidr,
        string $ipAddress
    ): bool {
        $network = self::parseCidr($cidr);
        $packedIp = @inet_pton(trim($ipAddress));
        if ($network === null || $packedIp === false) {
            return false;
        }
        if (strlen($packedIp) !== strlen($network['packed'])) {
            return false;
        }

        return hash_equals(
            $network['packed'],
            self::maskPackedAddress($packedIp, $network['prefix'])
        );
    }

    public static function normalizeCidr(string $cidr): string
    {
        $parsed = self::parseCidr($cidr);
        if ($parsed === null) {
            throw new InvalidArgumentException(
                'Enter a valid IPv4 or IPv6 address/CIDR'
            );
        }

        return $parsed['normalized'];
    }

    private function normalizeRuleData(
        array $data,
        ?array $existing = null
    ): array {
        $ruleType = strtolower(trim((string) (
            array_key_exists('rule_type', $data)
                ? $data['rule_type']
                : ($existing['rule_type'] ?? '')
        )));
        if (!in_array($ruleType, self::RULE_TYPES, true)) {
            throw new InvalidArgumentException(
                'Rule type must be allow or deny'
            );
        }

        $cidrInput = trim((string) (
            array_key_exists('cidr', $data)
                ? $data['cidr']
                : ($existing['cidr'] ?? '')
        ));
        if ($cidrInput === '' || strlen($cidrInput) > 100) {
            throw new InvalidArgumentException(
                'A valid IP address or CIDR is required'
            );
        }
        $cidr = self::normalizeCidr($cidrInput);

        $description = trim((string) (
            array_key_exists('description', $data)
                ? $data['description']
                : ($existing['description'] ?? '')
        ));
        if (strlen($description) > 1000) {
            throw new InvalidArgumentException(
                'Description must not exceed 1000 characters'
            );
        }

        $enabledValue = array_key_exists('enabled', $data)
            ? $data['enabled']
            : ($existing['enabled'] ?? 1);
        $enabled = $this->normalizeBoolean($enabledValue);
        if ($enabled === null) {
            throw new InvalidArgumentException(
                'Enabled must be true or false'
            );
        }

        $startsAt = $this->normalizeDateTime(
            array_key_exists('starts_at', $data)
                ? $data['starts_at']
                : ($existing['starts_at'] ?? null),
            'Start time'
        );
        $expiresAt = $this->normalizeDateTime(
            array_key_exists('expires_at', $data)
                ? $data['expires_at']
                : ($existing['expires_at'] ?? null),
            'Expiry time'
        );

        if (
            $startsAt !== null &&
            $expiresAt !== null &&
            new DateTimeImmutable($expiresAt) <=
                new DateTimeImmutable($startsAt)
        ) {
            throw new InvalidArgumentException(
                'Expiry time must be later than start time'
            );
        }

        return [
            'rule_type' => $ruleType,
            'cidr' => $cidr,
            'description' => $description !== '' ? $description : null,
            'enabled' => $enabled,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ];
    }

    private function normalizeDateTime($value, string $label): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $input = trim((string) $value);
        $formats = [
            'Y-m-d\TH:i',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
        ];

        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat(
                '!' . $format,
                $input
            );
            $errors = DateTimeImmutable::getLastErrors();
            $valid = $date !== false && (
                $errors === false ||
                (
                    (int) ($errors['warning_count'] ?? 0) === 0 &&
                    (int) ($errors['error_count'] ?? 0) === 0
                )
            );
            if ($valid && $date->format($format) === $input) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        throw new InvalidArgumentException(
            $label . ' must be a valid local date and time'
        );
    }

    private function normalizeBoolean($value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1
                ? 1
                : ((int) $value === 0 ? 0 : null);
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return 1;
        }
        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return 0;
        }

        return null;
    }

    private function getRulesForUpdate(): array
    {
        $statement = $this->db->query(
            'SELECT
                id,
                rule_type,
                cidr,
                description,
                enabled,
                starts_at,
                expires_at,
                created_by,
                updated_by,
                created_at,
                updated_at
             FROM system_ip_rules
             ORDER BY id
             FOR UPDATE'
        );

        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function findRuleIndex(array $rules, int $ruleId): ?int
    {
        foreach ($rules as $index => $rule) {
            if ((int) ($rule['id'] ?? 0) === $ruleId) {
                return $index;
            }
        }

        return null;
    }

    private function assertNoEquivalentRule(
        array $rules,
        string $ruleType,
        string $normalizedCidr,
        ?int $exceptId = null
    ): void {
        foreach ($rules as $rule) {
            if (
                $exceptId !== null &&
                (int) ($rule['id'] ?? 0) === $exceptId
            ) {
                continue;
            }
            if ((string) ($rule['rule_type'] ?? '') !== $ruleType) {
                continue;
            }

            try {
                $existingCidr = self::normalizeCidr(
                    (string) ($rule['cidr'] ?? '')
                );
            } catch (InvalidArgumentException $error) {
                continue;
            }

            if ($existingCidr === $normalizedCidr) {
                throw new DomainException(
                    'An equivalent IP rule already exists'
                );
            }
        }
    }

    private function assertActorAndClient(
        int $actorUserId,
        string $currentIp
    ): void {
        if ($actorUserId <= 0) {
            throw new InvalidArgumentException(
                'An authenticated administrator is required'
            );
        }
        if (!self::isValidIp($currentIp)) {
            throw new DomainException(
                'The current client IP could not be verified safely'
            );
        }
    }

    private function assertCurrentClientRemainsAllowed(
        array $candidateRules,
        string $currentIp
    ): void {
        $decision = $this->evaluateRows(
            $currentIp,
            $candidateRules,
            new DateTimeImmutable('now')
        );

        if (!$decision['allowed']) {
            throw new DomainException(
                'This change would immediately block your current IP address'
            );
        }
    }

    private function evaluateRows(
        string $ipAddress,
        array $rules,
        DateTimeImmutable $now
    ): array {
        $activeAllowRuleIds = [];
        $activeDenyRuleIds = [];
        $matchedAllowRuleIds = [];
        $matchedDenyRuleIds = [];

        foreach ($rules as $rule) {
            if (!$this->isRuleActiveAt($rule, $now)) {
                continue;
            }

            $ruleId = (int) ($rule['id'] ?? 0);
            $ruleType = (string) ($rule['rule_type'] ?? '');
            $cidr = (string) ($rule['cidr'] ?? '');
            if (!in_array($ruleType, self::RULE_TYPES, true)) {
                continue;
            }
            if (!self::isValidCidr($cidr)) {
                continue;
            }

            if ($ruleType === 'allow') {
                $activeAllowRuleIds[] = $ruleId;
                if (self::cidrContains($cidr, $ipAddress)) {
                    $matchedAllowRuleIds[] = $ruleId;
                }
            } else {
                $activeDenyRuleIds[] = $ruleId;
                if (self::cidrContains($cidr, $ipAddress)) {
                    $matchedDenyRuleIds[] = $ruleId;
                }
            }
        }

        if (!empty($matchedDenyRuleIds)) {
            $allowed = false;
            $reason = 'deny_rule';
        } elseif (
            !empty($activeAllowRuleIds) &&
            empty($matchedAllowRuleIds)
        ) {
            $allowed = false;
            $reason = 'not_allowlisted';
        } elseif (!empty($activeAllowRuleIds)) {
            $allowed = true;
            $reason = 'allow_rule';
        } else {
            $allowed = true;
            $reason = 'no_active_allowlist';
        }

        return [
            'allowed' => $allowed,
            'reason' => $reason,
            'active_allow_rules' => count($activeAllowRuleIds),
            'active_deny_rules' => count($activeDenyRuleIds),
            'matched_allow_rule_ids' => $matchedAllowRuleIds,
            'matched_deny_rule_ids' => $matchedDenyRuleIds,
        ];
    }

    private function isRuleActiveAt(
        array $rule,
        DateTimeImmutable $now
    ): bool {
        if ((int) ($rule['enabled'] ?? 0) !== 1) {
            return false;
        }

        $startsAt = $rule['starts_at'] ?? null;
        if (
            $startsAt !== null &&
            trim((string) $startsAt) !== '' &&
            new DateTimeImmutable((string) $startsAt) > $now
        ) {
            return false;
        }

        $expiresAt = $rule['expires_at'] ?? null;
        if (
            $expiresAt !== null &&
            trim((string) $expiresAt) !== '' &&
            new DateTimeImmutable((string) $expiresAt) <= $now
        ) {
            return false;
        }

        return true;
    }

    private function statusExpression(): string
    {
        return "CASE
            WHEN r.enabled = 0 THEN 'disabled'
            WHEN r.expires_at IS NOT NULL
             AND r.expires_at <= NOW() THEN 'expired'
            WHEN r.starts_at IS NOT NULL
             AND r.starts_at > NOW() THEN 'scheduled'
            ELSE 'active'
        END";
    }

    private function statusCondition(string $status): string
    {
        switch ($status) {
            case 'active':
                return "r.enabled = 1
                    AND (r.starts_at IS NULL OR r.starts_at <= NOW())
                    AND (r.expires_at IS NULL OR r.expires_at > NOW())";
            case 'scheduled':
                return "r.enabled = 1
                    AND r.starts_at IS NOT NULL
                    AND r.starts_at > NOW()
                    AND (r.expires_at IS NULL OR r.expires_at > NOW())";
            case 'expired':
                return "r.enabled = 1
                    AND r.expires_at IS NOT NULL
                    AND r.expires_at <= NOW()";
            case 'disabled':
                return 'r.enabled = 0';
            default:
                throw new InvalidArgumentException(
                    'Rule status is not supported'
                );
        }
    }

    private function auditSnapshot(array $rule): array
    {
        return [
            'id' => isset($rule['id']) ? (int) $rule['id'] : null,
            'rule_type' => $rule['rule_type'] ?? null,
            'cidr' => $rule['cidr'] ?? null,
            'description' => $rule['description'] ?? null,
            'enabled' => isset($rule['enabled'])
                ? (int) $rule['enabled']
                : null,
            'starts_at' => $rule['starts_at'] ?? null,
            'expires_at' => $rule['expires_at'] ?? null,
            'created_by' => isset($rule['created_by'])
                ? (int) $rule['created_by']
                : null,
            'updated_by' => isset($rule['updated_by'])
                ? (int) $rule['updated_by']
                : null,
        ];
    }

    private function writeAudit(
        string $action,
        int $ruleId,
        int $actorUserId,
        array $details
    ): void {
        $logged = (new AuditLogger($this->db))->log(
            $action,
            'ip_rule',
            $ruleId,
            $actorUserId,
            $details
        );
        if (!$logged) {
            throw new RuntimeException(
                'The IP-rule audit record could not be written'
            );
        }
    }

    private function transactional(callable $callback): array
    {
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->db->commit();
            }
            return $result;
        } catch (Throwable $error) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    private static function isTrustedProxy(string $ipAddress): bool
    {
        $configured = defined('TRUSTED_PROXY_CIDRS')
            ? constant('TRUSTED_PROXY_CIDRS')
            : (
                $_ENV['TRUSTED_PROXY_CIDRS']
                ?? getenv('TRUSTED_PROXY_CIDRS')
            );
        if ($configured === false || $configured === null) {
            return false;
        }
        if (is_string($configured)) {
            $configured = preg_split(
                '/[\s,]+/',
                $configured,
                -1,
                PREG_SPLIT_NO_EMPTY
            );
        }
        if (!is_array($configured)) {
            return false;
        }

        foreach ($configured as $cidr) {
            if (self::cidrContains((string) $cidr, $ipAddress)) {
                return true;
            }
        }

        return false;
    }

    private static function isValidIp(string $ipAddress): bool
    {
        return @inet_pton(trim($ipAddress)) !== false;
    }

    private static function isValidCidr(string $cidr): bool
    {
        return self::parseCidr($cidr) !== null;
    }

    private static function parseCidr(string $cidr): ?array
    {
        $input = trim($cidr);
        if ($input === '') {
            return null;
        }

        if (strpos($input, '/') === false) {
            $packed = @inet_pton($input);
            if ($packed === false) {
                return null;
            }
            $input .= strlen($packed) === 4 ? '/32' : '/128';
        }

        $parts = explode('/', $input);
        if (count($parts) !== 2) {
            return null;
        }

        $address = trim($parts[0]);
        $prefixText = trim($parts[1]);
        if (
            $address === '' ||
            $prefixText === '' ||
            !ctype_digit($prefixText)
        ) {
            return null;
        }

        $packed = @inet_pton($address);
        if ($packed === false) {
            return null;
        }

        $maximumPrefix = strlen($packed) * 8;
        $prefix = (int) $prefixText;
        if ($prefix < 0 || $prefix > $maximumPrefix) {
            return null;
        }

        $network = self::maskPackedAddress($packed, $prefix);
        $networkAddress = @inet_ntop($network);
        if ($networkAddress === false) {
            return null;
        }

        return [
            'packed' => $network,
            'prefix' => $prefix,
            'maximum_prefix' => $maximumPrefix,
            'normalized' => $networkAddress . '/' . $prefix,
        ];
    }

    private static function maskPackedAddress(
        string $packedAddress,
        int $prefix
    ): string {
        $bytes = array_values(unpack('C*', $packedAddress));
        $remainingBits = $prefix;

        foreach ($bytes as $index => $byte) {
            if ($remainingBits >= 8) {
                $remainingBits -= 8;
                continue;
            }
            if ($remainingBits <= 0) {
                $bytes[$index] = 0;
                continue;
            }

            $mask = (0xFF << (8 - $remainingBits)) & 0xFF;
            $bytes[$index] = $byte & $mask;
            $remainingBits = 0;
        }

        return pack('C*', ...$bytes);
    }
}
