<?php

namespace App\API\Services;

use App\Config\Config;
use App\Database\Database;
use PDO;

/**
 * Read-only System Administrator dashboard analytics.
 *
 * Every metric is sourced from a verified System Domain table or a live
 * runtime check. Missing telemetry is reported explicitly; no synthetic
 * fallback values are returned.
 */
final class SystemAdminAnalyticsService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getAuthEvents(): array
    {
        $entries = $this->readAuthLogEntries();
        $lifetimeRecords = count($entries);

        $since = date('Y-m-d H:i:s', time() - 86400);
        $events = [];
        $successfulLogins = 0;
        $failedLogins = 0;

        foreach ($entries as $entry) {
            if (($entry['type'] ?? '') !== 'login_attempt') {
                continue;
            }
            $createdAt = (string) ($entry['timestamp'] ?? '');
            if ($createdAt < $since) {
                continue;
            }
            $status = strtolower((string) ($entry['status'] ?? 'failed'));
            if ($status === 'success') {
                $successfulLogins++;
            } else {
                $failedLogins++;
            }
            if (count($events) >= 100) {
                continue;
            }
            $events[] = [
                'id' => null,
                'user_id' => isset($entry['user_id'])
                    ? (int) $entry['user_id']
                    : null,
                'username' => $entry['username'] ?? null,
                'first_name' => null,
                'last_name' => null,
                'email' => null,
                'action' => $status === 'success'
                    ? 'login_success'
                    : 'login_failed',
                'entity' => 'user',
                'details' => $entry['failure_reason'] ?? null,
                'ip_address' => $entry['ip'] ?? $entry['ip_address'] ?? null,
                'user_agent' => $entry['user_agent'] ?? null,
                'status' => $status,
                'created_at' => $createdAt,
            ];
        }

        $events = $this->enrichEventUsers($events);

        return [
            'events' => $events,
            'summary' => [
                'successful_logins' => $successfulLogins,
                'failed_logins' => $failedLogins,
                'total_events' => $successfulLogins + $failedLogins,
                'tracking_available' => $lifetimeRecords > 0,
                'period' => '24 hours',
            ],
            'generated_at' => date('c'),
        ];
    }

    /**
     * Return the complete, server-paginated Authentication Logs registry.
     *
     * Unlike the dashboard summary, this method has no implicit time window.
     * Every filter is validated before it is used to build the query.
     */
    public function getAuthenticationLogs(array $filters = []): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if (strlen($search) > 200) {
            throw new \InvalidArgumentException(
                'Search must not exceed 200 characters'
            );
        }
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (!in_array($status, ['', 'success', 'failed'], true)) {
            throw new \InvalidArgumentException(
                'Status must be success or failed'
            );
        }

        $failureReason = trim(
            (string) ($filters['failure_reason'] ?? '')
        );
        if (strlen($failureReason) > 100) {
            throw new \InvalidArgumentException(
                'Failure reason must not exceed 100 characters'
            );
        }

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if ($dateFrom !== '' && !$this->isValidDate($dateFrom)) {
            throw new \InvalidArgumentException(
                'From date must use YYYY-MM-DD'
            );
        }
        if ($dateTo !== '' && !$this->isValidDate($dateTo)) {
            throw new \InvalidArgumentException(
                'To date must use YYYY-MM-DD'
            );
        }
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            throw new \InvalidArgumentException(
                'From date cannot be later than To date'
            );
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = (int) ($filters['limit'] ?? 50);
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 50;
        }

        $entries = $this->readAuthLogEntries();
        $lifetimeRecords = count($entries);

        $rows = [];
        $failureReasons = [];
        $since24h = date('Y-m-d H:i:s', time() - 86400);
        $eventsLast24h = 0;
        $uniqueIps = [];

        foreach ($entries as $entry) {
            if (($entry['type'] ?? '') !== 'login_attempt') {
                continue;
            }

            $logStatus = strtolower((string) ($entry['status'] ?? 'failed'));
            if ($status !== '' && $logStatus !== $status) {
                continue;
            }

            $reason = trim((string) ($entry['failure_reason'] ?? ''));
            if ($failureReason !== '' && $reason !== $failureReason) {
                continue;
            }

            $createdAt = (string) ($entry['timestamp'] ?? '');
            if ($dateFrom !== '' && $createdAt !== ''
                && $createdAt < ($dateFrom . ' 00:00:00')) {
                continue;
            }
            if ($dateTo !== '' && $createdAt !== ''
                && $createdAt > ($dateTo . ' 23:59:59')) {
                continue;
            }

            $username = (string) ($entry['username'] ?? '');
            $ipAddress = (string) ($entry['ip'] ?? $entry['ip_address'] ?? '');
            $userAgent = (string) ($entry['user_agent'] ?? '');
            if ($search !== '') {
                $haystack = strtolower(
                    $username . ' ' . $ipAddress . ' ' . $userAgent . ' ' . $reason
                );
                if (strpos($haystack, strtolower($search)) === false) {
                    continue;
                }
            }

            $rows[] = [
                'id' => null,
                'user_id' => isset($entry['user_id'])
                    ? (int) $entry['user_id']
                    : null,
                'attempted_identifier' => $username,
                'username' => $username,
                'first_name' => null,
                'last_name' => null,
                'email' => null,
                'account_status' => null,
                'consecutive_failed_attempts' => null,
                'account_locked_until' => null,
                'status' => $logStatus,
                'failure_reason' => $reason !== '' ? $reason : null,
                'ip_address' => $ipAddress !== '' ? $ipAddress : null,
                'user_agent' => $userAgent !== '' ? $userAgent : null,
                'created_at' => $createdAt !== '' ? $createdAt : null,
            ];

            if ($reason !== '') {
                $failureReasons[$reason] = true;
            }
            if ($createdAt >= $since24h) {
                $eventsLast24h++;
            }
            if ($ipAddress !== '') {
                $uniqueIps[$ipAddress] = true;
            }
        }

        $enrichment = $this->enrichAuthRows($rows);
        $enriched = $enrichment['rows'];
        $currentlyLocked = $enrichment['locked_count'];

        $total = count($rows);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;
        $pageRows = array_slice($enriched, $offset, $limit);

        $successful = count(array_filter(
            $rows,
            static fn (array $r): bool => $r['status'] === 'success'
        ));
        $failed = $total - $successful;

        return [
            'rows' => $pageRows,
            'summary' => [
                'total_events' => $total,
                'successful_events' => $successful,
                'failed_events' => $failed,
                'events_last_24h' => $eventsLast24h,
                'unique_ip_addresses' => count($uniqueIps),
                'currently_locked_accounts' => $currentlyLocked,
                'tracking_available' => $lifetimeRecords > 0,
            ],
            'available_filters' => [
                'failure_reasons' => array_values(array_filter(
                    array_map(
                        static fn (string $reason): string => trim($reason),
                        array_keys($failureReasons)
                    )
                )),
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'failure_reason' => $failureReason,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'generated_at' => date('c'),
        ];
    }

    /**
     * Return only failed attempts through the canonical authentication query.
     *
     * The status is forced after caller filters are received so this endpoint
     * can never be broadened to successful authentication events.
     */
    public function getFailedLoginAttempts(array $filters = []): array
    {
        $filters['status'] = 'failed';

        return $this->getAuthenticationLogs($filters);
    }

    /**
     * Return active authenticated sessions with bounded server pagination.
     *
     * The same method supplies the dashboard summary and the dedicated session
     * registry, so there is one read contract over user_sessions.
     */
    public function getActiveSessions(
        array $filters = [],
        ?int $currentSessionId = null
    ): array {
        $search = trim((string) ($filters['search'] ?? ''));
        if (strlen($search) > 200) {
            throw new \InvalidArgumentException(
                'Search must not exceed 200 characters'
            );
        }

        $roleFilter = trim((string) ($filters['role_id'] ?? ''));
        $roleId = null;
        if ($roleFilter !== '') {
            if (!ctype_digit($roleFilter) || (int) $roleFilter <= 0) {
                throw new \InvalidArgumentException(
                    'Role ID must be a positive integer'
                );
            }
            $roleId = (int) $roleFilter;
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = (int) ($filters['limit'] ?? 100);
        if (!in_array($limit, [25, 50, 100], true)) {
            $limit = 50;
        }
        $currentSessionId = max(0, (int) ($currentSessionId ?? 0));
        $idleTimeoutSeconds = max(
            300,
            defined('AUTH_IDLE_TIMEOUT_SECONDS')
                ? (int) AUTH_IDLE_TIMEOUT_SECONDS
                : 1800
        );

        $baseWhere = [
            "s.session_status = 'active'",
            's.logout_time IS NULL',
        ];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';
            $baseWhere[] = '(
                u.username LIKE ?
                OR p.email LIKE ?
                OR p.first_name LIKE ?
                OR p.last_name LIKE ?
                OR r.name LIKE ?
                OR s.ip_address LIKE ?
                OR s.user_agent LIKE ?
            )';
            array_push(
                $params,
                $term,
                $term,
                $term,
                $term,
                $term,
                $term,
                $term
            );
        }
        if ($roleId !== null) {
            $baseWhere[] = 'EXISTS (
                SELECT 1
                FROM user_roles urf
                WHERE urf.user_id = u.id
                  AND urf.role_id = ?
            )';
            $params[] = $roleId;
        }

        $activeWhere = $baseWhere;
        $activeWhere[] = "s.last_activity >= DATE_SUB(
            NOW(),
            INTERVAL {$idleTimeoutSeconds} SECOND
        )";

        $baseWhereSql = implode(' AND ', $baseWhere);
        $activeWhereSql = implode(' AND ', $activeWhere);
        $fromSql = '
            FROM user_sessions s
            INNER JOIN users u ON u.id = s.user_id
            LEFT JOIN persons p ON p.id = u.person_id
            LEFT JOIN (
                SELECT ur.user_id, MIN(ur.role_id) AS role_id
                FROM user_roles ur
                GROUP BY ur.user_id
            ) ur ON ur.user_id = u.id
            LEFT JOIN roles r ON r.id = ur.role_id
            LEFT JOIN (
                SELECT rt.user_id, MAX(rt.expires_at) AS expires_at
                FROM refresh_tokens rt
                WHERE rt.revoked_at IS NULL
                GROUP BY rt.user_id
            ) rt ON rt.user_id = u.id
        ';

        $summaryStmt = $this->db->query(
            "SELECT
                COUNT(*) AS active_sessions,
                COUNT(DISTINCT s.user_id) AS unique_users,
                COUNT(DISTINCT NULLIF(s.ip_address, ''))
                    AS unique_ip_addresses,
                COALESCE(
                    SUM(
                        CASE
                            WHEN rt.expires_at <= DATE_ADD(
                                NOW(),
                                INTERVAL 24 HOUR
                            )
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS expiring_next_24h
             $fromSql
             WHERE $activeWhereSql",
            $params
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $idleStmt = $this->db->query(
            "SELECT COUNT(*)
             $fromSql
             WHERE $baseWhereSql
               AND s.last_activity < DATE_SUB(
                    NOW(),
                    INTERVAL {$idleTimeoutSeconds} SECOND
               )",
            $params
        );
        $idleExpiredCount = (int) $idleStmt->fetchColumn();

        $total = (int) ($summary['active_sessions'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        // LIMIT/OFFSET and currentSessionId are interpolated only after strict
        // integer normalization.
        $rowsStmt = $this->db->query(
            "SELECT
                s.id,
                s.user_id,
                u.username,
                p.first_name,
                p.last_name,
                p.email,
                u.status AS account_status,
                ur.role_id,
                r.name AS role_name,
                s.ip_address,
                s.user_agent,
                s.last_activity,
                rt.expires_at,
                s.created_at,
                TIMESTAMPDIFF(
                    SECOND,
                    s.last_activity,
                    NOW()
                ) AS idle_seconds,
                CASE
                    WHEN s.id = $currentSessionId THEN 1
                    ELSE 0
                END AS is_current
             $fromSql
             WHERE $activeWhereSql
             ORDER BY
                is_current DESC,
                s.last_activity DESC,
                s.id DESC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $byRoleStmt = $this->db->query(
            "SELECT
                COALESCE(r.name, 'Unknown') AS role_name,
                COUNT(*) AS session_count
             $fromSql
             WHERE $activeWhereSql
             GROUP BY ur.role_id, r.name
             ORDER BY role_name",
            $params
        );
        $byRole = [];
        foreach ($byRoleStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byRole[(string) $row['role_name']] =
                (int) $row['session_count'];
        }

        $availableRoles = $this->db->query(
            "SELECT DISTINCT
                r.id,
                r.name
             FROM user_sessions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN user_roles ur ON ur.user_id = u.id
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE s.session_status = 'active'
               AND s.logout_time IS NULL
               AND s.last_activity >= DATE_SUB(
                    NOW(),
                    INTERVAL {$idleTimeoutSeconds} SECOND
               )
             ORDER BY r.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $trackedSessionRecords = $this->scalar(
            'SELECT COUNT(*) FROM user_sessions'
        );

        return [
            'sessions' => $rowsStmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'enabled_users' => $this->scalar(
                    "SELECT COUNT(*)
                     FROM users
                     WHERE status = 'active'"
                ),
                'total_active_sessions' => $trackedSessionRecords > 0
                    ? $total
                    : null,
                'unique_users' => (int) (
                    $summary['unique_users'] ?? 0
                ),
                'unique_ip_addresses' => (int) (
                    $summary['unique_ip_addresses'] ?? 0
                ),
                'expiring_next_24h' => (int) (
                    $summary['expiring_next_24h'] ?? 0
                ),
                // Kept for frontend compatibility; the threshold now follows
                // AUTH_IDLE_TIMEOUT_SECONDS instead of being hard-coded.
                'idle_over_30_minutes' => $idleExpiredCount,
                'idle_timeout_seconds' => $idleTimeoutSeconds,
                'tracking_available' => $trackedSessionRecords > 0,
                'by_role' => $byRole,
            ],
            'available_filters' => [
                'roles' => $availableRoles,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'search' => $search,
                'role_id' => $roleId,
            ],
            'current_session_id' => $currentSessionId > 0
                ? $currentSessionId
                : null,
            'generated_at' => date('c'),
        ];
    }

    public function getUptime(): array
    {
        $databaseStatus = 'down';
        $databaseLatency = null;
        $startedAt = microtime(true);

        try {
            $databaseStatus = (int) $this->db
                ->query('SELECT 1')
                ->fetchColumn() === 1
                ? 'healthy'
                : 'down';
            $databaseLatency = round(
                (microtime(true) - $startedAt) * 1000,
                2
            );
        } catch (\Throwable $error) {
            $databaseStatus = 'down';
        }

        $projectRoot = dirname(__DIR__, 2);
        $freeBytes = @disk_free_space($projectRoot);
        $totalBytes = @disk_total_space($projectRoot);
        $freePercent = (
            is_numeric($freeBytes) &&
            is_numeric($totalBytes) &&
            (float) $totalBytes > 0
        )
            ? round(((float) $freeBytes / (float) $totalBytes) * 100, 2)
            : null;

        $storageStatus = $freePercent === null
            ? 'unavailable'
            : ($freePercent < 10 ? 'attention' : 'healthy');

        $serverUptimeSeconds = $this->readServerUptimeSeconds();

        return [
            'database' => [
                'status' => $databaseStatus,
                'latency_ms' => $databaseLatency,
            ],
            'runtime' => [
                'php_version' => PHP_VERSION,
                'environment' => Config::getEnvironment(),
                'server_uptime_seconds' => $serverUptimeSeconds,
                'server_uptime_formatted' => $serverUptimeSeconds === null
                    ? null
                    : $this->formatDuration($serverUptimeSeconds),
            ],
            'storage' => [
                'status' => $storageStatus,
                'free_bytes' => is_numeric($freeBytes)
                    ? (int) $freeBytes
                    : null,
                'total_bytes' => is_numeric($totalBytes)
                    ? (int) $totalBytes
                    : null,
                'free_percent' => $freePercent,
                'free_formatted' => is_numeric($freeBytes)
                    ? $this->formatBytes((int) $freeBytes)
                    : null,
            ],
            'generated_at' => date('c'),
        ];
    }

    /**
     * Pure transform: journal entries -> health error rows.
     *
     * Kept separate from the I/O so the severity contract is unit-testable
     * without a database or a writable journal.
     *
     * @param array<int,array<string,mixed>> $entries Raw `errors` journal entries.
     * @return array{errors:array<int,array<string,mixed>>,count:int}
     */
    public static function healthErrorRows(array $entries, ?string $since = null, int $limit = 50): array
    {
        $since = $since ?? date('Y-m-d H:i:s', time() - 86400);
        $rows = [];
        $count = 0;
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            // Only genuine fault severities count. Benign success traces (a
            // token header being present, a successful login, a created user)
            // are journaled as debug/info and must never surface as errors.
            $level = strtolower((string) ($entry['level'] ?? ''));
            if (!in_array($level, ['error', 'critical'], true)) {
                continue;
            }
            $createdAt = (string) ($entry['timestamp'] ?? '');
            if ($createdAt !== '' && $createdAt < $since) {
                continue;
            }
            $count++;
            if (count($rows) >= $limit) {
                continue;
            }
            $rows[] = [
                'id' => null,
                'error_type' => (string) ($entry['type'] ?? $level),
                'message' => (string) ($entry['message'] ?? ''),
                'file_path' => $entry['source_file'] ?? null,
                'line_number' => $entry['source_line'] ?? null,
                'user_id' => $entry['user_id'] ?? null,
                'ip_address' => $entry['ip'] ?? $entry['ip_address'] ?? null,
                'created_at' => $createdAt,
            ];
        }
        return ['errors' => $rows, 'count' => $count];
    }

    public function getHealthErrors(): array
    {
        // Errors are journaled to the `errors` file by Logger (see the logging
        // rule: logs are file journals, never database tables). The former
        // system_error_logs table was dropped in migration 042, so it must not
        // be queried here — that produced a 1146 on every health refresh while
        // the dashboard still reported zero errors.
        $errors = [];
        $errorCount = 0;
        try {
            $extracted = self::healthErrorRows(\App\API\Includes\FileLogger::recent('errors', 5000));
            $errors = $extracted['errors'];
            $errorCount = $extracted['count'];
        } catch (\Throwable $e) {
            // A missing or unreadable journal must not break the health page.
            $errors = [];
            $errorCount = 0;
        }

        $incidentsStmt = $this->db->query(
            "SELECT
                id,
                title,
                severity,
                status,
                description,
                assigned_to,
                created_at,
                updated_at
             FROM system_security_incidents
             WHERE status NOT IN ('resolved', 'closed')
             ORDER BY
                FIELD(severity, 'critical', 'high', 'medium', 'low'),
                created_at DESC
             LIMIT 50"
        );

        $openIncidentCount = $this->scalar(
            "SELECT COUNT(*)
             FROM system_security_incidents
             WHERE status NOT IN ('resolved', 'closed')"
        );
        $criticalIncidentCount = $this->scalar(
            "SELECT COUNT(*)
             FROM system_security_incidents
             WHERE status NOT IN ('resolved', 'closed')
               AND severity = 'critical'"
        );

        return [
            'errors' => $errors,
            'incidents' => $incidentsStmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'system_errors_24h' => $errorCount,
                'open_incidents' => $openIncidentCount,
                'critical_incidents' => $criticalIncidentCount,
                'period' => '24 hours',
            ],
            'generated_at' => date('c'),
        ];
    }

    public function getHealthWarnings(): array
    {
        $failedAuthGroups = [];
        try {
            $since = date('Y-m-d H:i:s', time() - 86400);
            $grouped = [];
            foreach (\App\API\Includes\FileLogger::recent('auth', 5000) as $e) {
                if (($e['type'] ?? '') !== 'login_attempt') {
                    continue;
                }
                if (strtolower((string) ($e['status'] ?? '')) !== 'failed') {
                    continue;
                }
                $createdAt = (string) ($e['timestamp'] ?? '');
                if ($createdAt < $since) {
                    continue;
                }
                $ip = (string) ($e['ip'] ?? $e['ip_address'] ?? 'unknown');
                if ($ip === '') {
                    $ip = 'unknown';
                }
                if (!isset($grouped[$ip])) {
                    $grouped[$ip] = [
                        'attempt_count' => 0,
                        'last_attempt' => $createdAt,
                        'reasons' => [],
                    ];
                }
                $grouped[$ip]['attempt_count']++;
                if ($createdAt > $grouped[$ip]['last_attempt']) {
                    $grouped[$ip]['last_attempt'] = $createdAt;
                }
                $reason = trim((string) ($e['failure_reason'] ?? ''));
                if ($reason !== '') {
                    $grouped[$ip]['reasons'][$reason] = true;
                }
            }
            foreach ($grouped as $ip => $group) {
                $failedAuthGroups[] = [
                    'id' => null,
                    'ip_address' => $ip,
                    'attempt_count' => $group['attempt_count'],
                    'created_at' => $group['last_attempt'],
                    'reasons' => implode(', ', array_keys($group['reasons'])),
                ];
            }
            usort($failedAuthGroups, static function (array $a, array $b): int {
                return $b['attempt_count'] <=> $a['attempt_count'];
            });
            $failedAuthGroups = array_slice($failedAuthGroups, 0, 25);
        } catch (\Throwable $e) {
            // login_attempts was dropped; the frontend reads the auth log
            // file for this signal instead.
        }
        $warnings = array_map(static function (array $row): array {
            $attempts = (int) ($row['attempt_count'] ?? 0);
            $ipAddress = (string) ($row['ip_address'] ?? 'unknown');

            return [
                'id' => $row['id'] ?? null,
                'title' => 'Repeated failed authentication',
                'message' => sprintf(
                    'IP %s recorded %d failed attempt%s in the last 24 hours%s.',
                    $ipAddress,
                    $attempts,
                    $attempts === 1 ? '' : 's',
                    empty($row['reasons'])
                        ? ''
                        : ' (' . $row['reasons'] . ')'
                ),
                'severity' => 'warning',
                'ip_address' => $ipAddress,
                'attempt_count' => $attempts,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $failedAuthGroups);

        $alertsStmt = $this->db->query(
            "SELECT
                id,
                title,
                message,
                severity,
                created_at
             FROM system_alerts
             WHERE resolved = 0
             ORDER BY
                FIELD(severity, 'critical', 'warning', 'info'),
                created_at DESC
             LIMIT 50"
        );

        $jobsStmt = $this->db->query(
            "SELECT
                id,
                job_type,
                status,
                attempts,
                max_attempts,
                next_attempt_at,
                last_error,
                created_at,
                updated_at
             FROM system_background_jobs
             WHERE status IN ('retrying', 'failed')
             ORDER BY updated_at DESC
             LIMIT 50"
        );

        $pendingJobs = $this->scalar(
            "SELECT COUNT(*)
             FROM system_background_jobs
             WHERE status IN ('queued', 'retrying')"
        );
        $failedJobs = $this->scalar(
            "SELECT COUNT(*)
             FROM system_background_jobs
             WHERE status = 'failed'
               AND updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $unresolvedAlerts = $this->scalar(
            'SELECT COUNT(*) FROM system_alerts WHERE resolved = 0'
        );

        return [
            'warnings' => $warnings,
            'alerts' => $alertsStmt->fetchAll(PDO::FETCH_ASSOC),
            'jobs' => $jobsStmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'authentication_warning_groups' => count($warnings),
                'unresolved_alerts' => $unresolvedAlerts,
                'pending_jobs' => $pendingJobs,
                'failed_jobs_24h' => $failedJobs,
                'period' => '24 hours',
            ],
            'generated_at' => date('c'),
        ];
    }

    public function getApiLoad(): array
    {
        $lifetimeSamples = $this->scalar(
            'SELECT COUNT(*) FROM system_api_metrics'
        );

        $summaryStmt = $this->db->query(
            "SELECT
                COUNT(*) AS total_requests,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS api_errors,
                AVG(duration_ms) AS average_duration_ms,
                MAX(duration_ms) AS maximum_duration_ms
             FROM system_api_metrics
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $endpointStmt = $this->db->query(
            "SELECT
                endpoint AS route,
                http_method AS method,
                COUNT(*) AS request_count,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS error_count,
                ROUND(AVG(duration_ms), 2) AS average_duration_ms,
                ROUND(MAX(duration_ms), 2) AS maximum_duration_ms
             FROM system_api_metrics
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY endpoint, http_method
             ORDER BY request_count DESC, route ASC
             LIMIT 25"
        );

        $hourlyStmt = $this->db->query(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') AS hour_bucket,
                COUNT(*) AS request_count,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) AS error_count,
                ROUND(AVG(duration_ms), 2) AS average_duration_ms
             FROM system_api_metrics
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY hour_bucket
             ORDER BY hour_bucket ASC"
        );
        $hourly = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);

        $peakHour = null;
        $peakRequests = null;
        foreach ($hourly as $row) {
            $requests = (int) ($row['request_count'] ?? 0);
            if ($peakRequests === null || $requests > $peakRequests) {
                $peakRequests = $requests;
                $peakHour = $row['hour_bucket'] ?? null;
            }
        }

        $totalRequests = (int) ($summary['total_requests'] ?? 0);

        return [
            'endpoints' => $endpointStmt->fetchAll(PDO::FETCH_ASSOC),
            'hourly' => $hourly,
            'summary' => [
                'telemetry_available' => $lifetimeSamples > 0,
                'total_requests_24h' => $totalRequests,
                'api_errors_24h' => (int) ($summary['api_errors'] ?? 0),
                'average_duration_ms' => $summary['average_duration_ms'] === null
                    ? null
                    : round((float) $summary['average_duration_ms'], 2),
                'maximum_duration_ms' => $summary['maximum_duration_ms'] === null
                    ? null
                    : round((float) $summary['maximum_duration_ms'], 2),
                'peak_hour' => $peakHour,
                'peak_hour_requests' => $peakRequests,
                'requests_per_second' => $totalRequests > 0
                    ? round($totalRequests / 86400, 6)
                    : 0,
                'period' => '24 hours',
            ],
            'generated_at' => date('c'),
        ];
    }

    private function scalar(string $sql, array $params = []): int
    {
        return (int) ($this->db->query($sql, $params)->fetchColumn() ?: 0);
    }

    /**
     * Read the auth log file entries (login attempts) newest-first.
     */
    private function readAuthLogEntries(int $limit = 5000): array
    {
        try {
            return \App\API\Includes\FileLogger::recent('auth', $limit);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminAnalyticsService] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Best-effort enrichment of auth events with person/account details.
     */
    private function enrichEventUsers(array $events): array
    {
        $userIds = [];
        foreach ($events as $event) {
            $id = (int) ($event['user_id'] ?? 0);
            if ($id > 0) {
                $userIds[$id] = true;
            }
        }
        $details = $this->loadUserDetails($userIds);
        foreach ($events as &$event) {
            $detail = $details[$event['user_id']] ?? null;
            if (!$detail) {
                continue;
            }
            $event['username'] = $detail['username'] ?? $event['username'];
            $event['first_name'] = $detail['first_name'] ?? null;
            $event['last_name'] = $detail['last_name'] ?? null;
            $event['email'] = $detail['email'] ?? null;
        }
        unset($event);

        return $events;
    }

    /**
     * Best-effort enrichment of auth log rows with person/account details.
     *
     * Returns ['rows' => [...], 'locked_count' => int].
     */
    private function enrichAuthRows(array $rows): array
    {
        $userIds = [];
        foreach ($rows as $row) {
            $id = (int) ($row['user_id'] ?? 0);
            if ($id > 0) {
                $userIds[$id] = true;
            }
        }
        $details = $this->loadUserDetails($userIds);
        $lockedCount = 0;
        foreach ($rows as &$row) {
            $detail = $details[$row['user_id']] ?? null;
            if (!$detail) {
                continue;
            }
            $row['username'] = $detail['username'] ?? $row['username'];
            $row['first_name'] = $detail['first_name'] ?? null;
            $row['last_name'] = $detail['last_name'] ?? null;
            $row['email'] = $detail['email'] ?? null;
            $row['account_status'] = $detail['account_status'] ?? null;
            $row['consecutive_failed_attempts'] = $detail['consecutive_failed_attempts'] ?? null;
            $row['account_locked_until'] = $detail['account_locked_until'] ?? null;
            if (!empty($detail['account_locked_until'])
                && $detail['account_locked_until'] > date('Y-m-d H:i:s')) {
                $lockedCount++;
            }
        }
        unset($row);

        return ['rows' => $rows, 'locked_count' => $lockedCount];
    }

    /**
     * Load person/account details for the given user IDs, best-effort.
     */
    private function loadUserDetails(array $userIds): array
    {
        $ids = array_filter(array_map('intval', array_keys($userIds)));
        if (!$ids) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->getConnection()->prepare(
                "SELECT
                    user_id,
                    username,
                    user_status AS account_status,
                    failed_login_attempts AS consecutive_failed_attempts,
                    account_locked_until,
                    first_name,
                    last_name,
                    email
                 FROM " . ReadReplicaService::qualifiedRef('person_directory') . "
                 WHERE user_id IN ($placeholders)"
            );
            $stmt->execute($ids);
            $details = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $details[(int) $row['user_id']] = $row;
            }
            return $details;
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminAnalyticsService] ' . $e->getMessage());
            return [];
        }
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        return checkdate($month, $day, $year);
    }

    private function readServerUptimeSeconds(): ?int
    {
        $path = '/proc/uptime';
        if (!is_readable($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (!is_string($contents) || trim($contents) === '') {
            return null;
        }

        $seconds = (float) explode(' ', trim($contents))[0];
        return $seconds >= 0 ? (int) floor($seconds) : null;
    }

    private function formatDuration(int $seconds): string
    {
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . 'd';
        }
        if ($hours > 0 || $days > 0) {
            $parts[] = $hours . 'h';
        }
        $parts[] = $minutes . 'm';

        return implode(' ', $parts);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min(
            (int) floor(log($bytes, 1024)),
            count($units) - 1
        );

        return round($bytes / (1024 ** $power), 2) . ' ' . $units[$power];
    }
}
