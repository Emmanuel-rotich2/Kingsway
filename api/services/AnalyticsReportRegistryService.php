<?php
declare(strict_types=1);

namespace App\API\Services;

use PDO;
use RuntimeException;

/**
 * Persistence and access boundary for governed analytics metadata and runs.
 */
final class AnalyticsReportRegistryService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listCatalogue(array $user, array $filters = []): array
    {
        $roleIds = $this->roleIds($user);
        if ($roleIds === []) {
            return [];
        }

        $where = ["rd.status = 'published'", 'rd.is_current = 1'];
        $params = $roleIds;
        $roleMarks = implode(',', array_fill(0, count($roleIds), '?'));

        if (!empty($filters['domain'])) {
            $domain = $this->identifier((string) $filters['domain'], 80);
            $where[] = 'rd.domain = ?';
            $params[] = $domain;
        }
        if (!empty($filters['category'])) {
            $category = trim((string) $filters['category']);
            if (strlen($category) > 100) {
                throw new RuntimeException('Report category is too long.', 422);
            }
            $where[] = 'rd.category = ?';
            $params[] = $category;
        }
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if (strlen($search) > 100) {
                throw new RuntimeException('Report search is too long.', 422);
            }
            $where[] = '(rd.title LIKE ? OR rd.description LIKE ? OR rd.code LIKE ?)';
            $needle = '%' . $search . '%';
            $params[] = $needle;
            $params[] = $needle;
            $params[] = $needle;
        }

        $sql = "SELECT rd.*, owner.name AS owner_role_name,
                       GROUP_CONCAT(DISTINCT ara.scope_type ORDER BY ara.scope_type) AS scope_types,
                       MAX(ara.can_view) AS can_view,
                       MAX(ara.can_execute) AS can_execute,
                       MAX(ara.can_export) AS can_export,
                       MAX(ara.can_schedule) AS can_schedule,
                       MAX(ara.can_distribute) AS can_distribute,
                       MAX(ara.can_administer) AS can_administer
                FROM analytics_report_definitions rd
                JOIN analytics_report_role_access ara
                  ON ara.report_definition_id = rd.id
                 AND ara.role_id IN ($roleMarks)
                 AND ara.can_view = 1
                LEFT JOIN roles owner ON owner.id = rd.owner_role_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY rd.id, owner.name
                ORDER BY rd.domain, rd.category, rd.title";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map([$this, 'normalizeDefinition'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function accessibleDefinition(string $code, array $user, string $capability = 'view'): array
    {
        $capabilityColumns = [
            'view' => 'can_view',
            'execute' => 'can_execute',
            'export' => 'can_export',
            'schedule' => 'can_schedule',
            'distribute' => 'can_distribute',
            'administer' => 'can_administer',
        ];
        if (!isset($capabilityColumns[$capability])) {
            throw new RuntimeException('Unknown report capability.', 500);
        }

        $code = $this->reportCode($code);
        $roleIds = $this->roleIds($user);
        if ($roleIds === []) {
            throw new RuntimeException('This report is not available to your role.', 403);
        }
        $marks = implode(',', array_fill(0, count($roleIds), '?'));
        $column = $capabilityColumns[$capability];

        $sql = "SELECT rd.*, owner.name AS owner_role_name,
                       GROUP_CONCAT(DISTINCT ara.scope_type ORDER BY ara.scope_type) AS scope_types,
                       MAX(ara.can_view) AS can_view,
                       MAX(ara.can_execute) AS can_execute,
                       MAX(ara.can_export) AS can_export,
                       MAX(ara.can_schedule) AS can_schedule,
                       MAX(ara.can_distribute) AS can_distribute,
                       MAX(ara.can_administer) AS can_administer
                FROM analytics_report_definitions rd
                JOIN analytics_report_role_access ara
                  ON ara.report_definition_id = rd.id
                 AND ara.role_id IN ($marks)
                LEFT JOIN roles owner ON owner.id = rd.owner_role_id
                WHERE rd.code = ?
                  AND rd.status = 'published'
                  AND rd.is_current = 1
                  AND ara.$column = 1
                GROUP BY rd.id, owner.name
                LIMIT 1";

        // The report code is the final placeholder in SQL, after role ids.
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($roleIds, [$code]));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('This report or requested action is not available to your role.', 403);
        }
        return $this->normalizeDefinition($row);
    }

    public function listMetrics(array $user, ?string $domain = null): array
    {
        $roleIds = $this->roleIds($user);
        if ($roleIds === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($roleIds), '?'));
        $params = $roleIds;
        $where = ["md.status = 'approved'", 'md.is_current = 1'];
        if ($domain !== null && $domain !== '') {
            $where[] = 'md.domain = ?';
            $params[] = $this->identifier($domain, 80);
        }

        $stmt = $this->db->prepare(
            "SELECT DISTINCT md.*, owner.name AS owner_role_name
             FROM analytics_metric_definitions md
             JOIN analytics_report_metrics arm ON arm.metric_definition_id = md.id
             JOIN analytics_report_definitions rd ON rd.id = arm.report_definition_id
             JOIN analytics_report_role_access ara
               ON ara.report_definition_id = rd.id
              AND ara.role_id IN ($marks)
              AND ara.can_view = 1
             LEFT JOIN roles owner ON owner.id = md.owner_role_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY md.domain, md.name"
        );
        $stmt->execute($params);
        return array_map([$this, 'normalizeMetric'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function metricsForReport(int $reportDefinitionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT md.*, arm.display_order, arm.is_primary
             FROM analytics_report_metrics arm
             JOIN analytics_metric_definitions md ON md.id = arm.metric_definition_id
             WHERE arm.report_definition_id = ?
               AND md.status = 'approved'
               AND md.is_current = 1
             ORDER BY arm.display_order, md.name"
        );
        $stmt->execute([$reportDefinitionId]);
        return array_map([$this, 'normalizeMetric'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function startRun(
        array $definition,
        array $user,
        array $parameters,
        array $scope,
        array $metricVersions,
        string $requestId,
        string $mode = 'synchronous'
    ): array {
        $userId = $this->userId($user);
        if (!$userId) {
            throw new RuntimeException('Authenticated user is required to run reports.', 401);
        }
        if (!in_array($mode, ['synchronous', 'background', 'scheduled'], true)) {
            throw new RuntimeException('Invalid report execution mode.', 422);
        }

        $uuid = $this->uuidV4();
        $stmt = $this->db->prepare(
            "INSERT INTO analytics_report_runs
                (run_uuid, report_definition_id, report_code, report_version,
                 requested_by, status, execution_mode, parameters_json,
                 effective_scope_json, metric_versions_json, requested_at,
                 started_at, as_of_at, request_id)
             VALUES (?, ?, ?, ?, ?, 'running', ?, ?, ?, ?, NOW(), NOW(), NOW(), ?)"
        );
        $stmt->execute([
            $uuid,
            (int) $definition['id'],
            (string) $definition['code'],
            (int) $definition['version'],
            $userId,
            $mode,
            $this->encodeJson($parameters),
            $this->encodeJson($scope),
            $this->encodeJson($metricVersions),
            substr($requestId, 0, 100),
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'run_uuid' => $uuid,
            'status' => 'running',
            'requested_by' => $userId,
            'started_at' => gmdate('c'),
        ];
    }

    public function completeRun(int $runId, int $rowCount, array $summary, array $warnings, int $durationMs): void
    {
        $stmt = $this->db->prepare(
            "UPDATE analytics_report_runs
             SET status = 'completed', completed_at = NOW(), row_count = ?,
                 duration_ms = ?, warning_count = ?, warnings_json = ?,
                 result_summary_json = ?, updated_at = NOW()
             WHERE id = ? AND status = 'running'"
        );
        $stmt->execute([
            max(0, $rowCount),
            max(0, $durationMs),
            count($warnings),
            $this->encodeJson($warnings),
            $this->encodeJson($summary),
            $runId,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Report run could not be completed from its current state.', 409);
        }
    }

    public function failRun(int $runId, string $failureCode, string $message, int $durationMs): void
    {
        $stmt = $this->db->prepare(
            "UPDATE analytics_report_runs
             SET status = 'failed', completed_at = NOW(), duration_ms = ?,
                 failure_code = ?, failure_message = ?, updated_at = NOW()
             WHERE id = ? AND status IN ('pending','running')"
        );
        $stmt->execute([
            max(0, $durationMs),
            substr($failureCode, 0, 80),
            substr($message, 0, 500),
            $runId,
        ]);
    }

    public function runStatus(int $runId, array $user): array
    {
        $userId = $this->userId($user);
        if (!$userId) {
            throw new RuntimeException('Authentication required.', 401);
        }

        $sql = "SELECT rr.id, rr.run_uuid, rr.report_code, rr.report_version,
                       rd.title, rr.status, rr.execution_mode, rr.parameters_json,
                       rr.effective_scope_json, rr.requested_at, rr.started_at,
                       rr.completed_at, rr.as_of_at, rr.row_count, rr.duration_ms,
                       rr.warning_count, rr.warnings_json, rr.failure_code,
                       rr.failure_message, rr.result_summary_json
                FROM analytics_report_runs rr
                JOIN analytics_report_definitions rd ON rd.id = rr.report_definition_id
                WHERE rr.id = ?";
        $params = [$runId];
        if (!$this->hasPermission($user, 'analytics_report_admin')) {
            $sql .= ' AND rr.requested_by = ?';
            $params[] = $userId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Report run was not found.', 404);
        }
        foreach (['parameters_json', 'effective_scope_json', 'warnings_json', 'result_summary_json'] as $field) {
            $row[str_replace('_json', '', $field)] = $this->decodeJson($row[$field] ?? null, []);
            unset($row[$field]);
        }
        $row['id'] = (int) $row['id'];
        $row['report_version'] = (int) $row['report_version'];
        $row['row_count'] = $row['row_count'] === null ? null : (int) $row['row_count'];
        $row['duration_ms'] = $row['duration_ms'] === null ? null : (int) $row['duration_ms'];
        $row['warning_count'] = (int) $row['warning_count'];
        return $row;
    }

    public function roleIds(array $user): array
    {
        $ids = [];
        foreach ((array) ($user['role_ids'] ?? []) as $roleId) {
            if (is_numeric($roleId)) $ids[] = (int) $roleId;
        }
        if ($ids !== []) {
            return array_values(array_unique(array_filter($ids)));
        }
        foreach (($user['roles'] ?? []) as $role) {
            if (is_numeric($role)) {
                $ids[] = (int) $role;
            } elseif (is_array($role) && isset($role['id'])) {
                $ids[] = (int) $role['id'];
            } elseif (is_object($role) && isset($role->id)) {
                $ids[] = (int) $role->id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids !== []) {
            return $ids;
        }

        $userId = $this->userId($user);
        if (!$userId) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT role_id FROM user_roles WHERE user_id = ?');
        $stmt->execute([$userId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function hasPermission(array $user, string $permission): bool
    {
        $permissions = $user['effective_permissions'] ?? $user['permissions'] ?? [];
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function reportCode(string $code): string
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^[A-Z][A-Z0-9_]{2,99}$/', $code)) {
            throw new RuntimeException('Invalid report code.', 422);
        }
        return $code;
    }

    private function normalizeDefinition(array $row): array
    {
        foreach ([
            'default_filters_json' => 'default_filters',
            'allowed_filters_json' => 'allowed_filters',
            'required_filters_json' => 'required_filters',
            'columns_json' => 'columns',
            'visualizations_json' => 'visualizations',
            'export_formats_json' => 'export_formats',
        ] as $source => $target) {
            $row[$target] = $this->decodeJson($row[$source] ?? null, []);
            unset($row[$source]);
        }
        $row['id'] = (int) $row['id'];
        $row['version'] = (int) $row['version'];
        $row['owner_role_id'] = $row['owner_role_id'] === null ? null : (int) $row['owner_role_id'];
        $row['freshness_minutes'] = (int) $row['freshness_minutes'];
        $row['minimum_aggregation_size'] = (int) $row['minimum_aggregation_size'];
        $row['scope_types'] = empty($row['scope_types']) ? [] : explode(',', (string) $row['scope_types']);
        // Compatibility for installations whose analytics metadata predates
        // migration 192: discipline_incidents stores textual `type`, not a
        // numeric category foreign key.
        if (($row['code'] ?? '') === 'DISC_INCIDENT_TREND') {
            $row['allowed_filters'] = array_values(array_unique(array_map(
                static fn(string $filter): string => $filter === 'category_id' ? 'category' : $filter,
                $row['allowed_filters'] ?? []
            )));
        }
        // Attendance is stream-sensitive for class teachers. Older metadata
        // omitted stream_id even though the executor and scope service support it.
        if (($row['code'] ?? '') === 'ATT_CLASS_TERM_RATE' && !in_array('stream_id', $row['allowed_filters'] ?? [], true)) {
            $row['allowed_filters'][] = 'stream_id';
        }
        $row['capabilities'] = [
            'view' => !empty($row['can_view']),
            'execute' => !empty($row['can_execute']),
            'export' => !empty($row['can_export']),
            'schedule' => !empty($row['can_schedule']),
            'distribute' => !empty($row['can_distribute']),
            'administer' => !empty($row['can_administer']),
        ];
        unset($row['can_view'], $row['can_execute'], $row['can_export'], $row['can_schedule'], $row['can_distribute'], $row['can_administer']);
        return $row;
    }

    private function normalizeMetric(array $row): array
    {
        foreach ([
            'source_column_map_json' => 'source_column_map',
            'dimensions_json' => 'dimensions',
            'definition_json' => 'definition',
        ] as $source => $target) {
            $row[$target] = $this->decodeJson($row[$source] ?? null, []);
            unset($row[$source]);
        }
        foreach (['id', 'version', 'owner_role_id', 'freshness_minutes', 'minimum_aggregation_size', 'display_order', 'is_primary'] as $field) {
            if (array_key_exists($field, $row) && $row[$field] !== null) {
                $row[$field] = (int) $row[$field];
            }
        }
        return $row;
    }

    private function userId(array $user): ?int
    {
        $id = $user['user_id'] ?? $user['id'] ?? null;
        return $id ? (int) $id : null;
    }

    private function identifier(string $value, int $maximumLength): string
    {
        $value = strtolower(trim($value));
        if (strlen($value) > $maximumLength || !preg_match('/^[a-z0-9_]+$/', $value)) {
            throw new RuntimeException('Invalid analytics identifier.', 422);
        }
        return $value;
    }

    private function decodeJson($value, array $fallback): array
    {
        if (!is_string($value) || $value === '') {
            return $fallback;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $fallback;
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Report metadata could not be encoded.', 500);
        }
        return $encoded;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' .
            substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
