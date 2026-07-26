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
        $lifetimeRecords = $this->scalar(
            'SELECT COUNT(*) FROM login_attempts'
        );

        $summaryStmt = $this->db->query(
            "SELECT
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS successful_logins,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed_logins,
                COUNT(*) AS total_events
             FROM login_attempts
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $eventsStmt = $this->db->query(
            "SELECT
                la.id,
                la.user_id,
                COALESCE(u.username, la.username) AS username,
                u.first_name,
                u.last_name,
                u.email,
                CASE
                    WHEN la.status = 'success' THEN 'login_success'
                    ELSE 'login_failed'
                END AS action,
                'user' AS entity,
                la.failure_reason AS details,
                la.ip_address,
                la.user_agent,
                la.status,
                la.created_at
             FROM login_attempts la
             LEFT JOIN users u ON u.id = la.user_id
             WHERE la.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY la.created_at DESC
             LIMIT 100"
        );

        return [
            'events' => $eventsStmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'successful_logins' => (int) ($summary['successful_logins'] ?? 0),
                'failed_logins' => (int) ($summary['failed_logins'] ?? 0),
                'total_events' => (int) ($summary['total_events'] ?? 0),
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

        $where = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = '(
                la.username LIKE ?
                OR u.username LIKE ?
                OR u.email LIKE ?
                OR u.first_name LIKE ?
                OR u.last_name LIKE ?
                OR la.ip_address LIKE ?
                OR la.failure_reason LIKE ?
                OR la.user_agent LIKE ?
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
        if ($status !== '') {
            $where[] = 'la.status = ?';
            $params[] = $status;
        }
        if ($failureReason !== '') {
            $where[] = 'la.failure_reason = ?';
            $params[] = $failureReason;
        }
        if ($dateFrom !== '') {
            $where[] = 'la.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[] = 'la.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $dateTo . ' 00:00:00';
        }

        $whereSql = implode(' AND ', $where);
        $fromSql = '
            FROM login_attempts la
            LEFT JOIN users u ON u.id = la.user_id
        ';

        $summaryStmt = $this->db->query(
            "SELECT
                COUNT(*) AS total_events,
                COALESCE(
                    SUM(CASE WHEN la.status = 'success' THEN 1 ELSE 0 END),
                    0
                ) AS successful_events,
                COALESCE(
                    SUM(CASE WHEN la.status = 'failed' THEN 1 ELSE 0 END),
                    0
                ) AS failed_events,
                COALESCE(
                    SUM(
                        CASE
                            WHEN la.created_at >= DATE_SUB(
                                NOW(),
                                INTERVAL 24 HOUR
                            )
                            THEN 1
                            ELSE 0
                        END
                    ),
                    0
                ) AS events_last_24h,
                COUNT(DISTINCT NULLIF(la.ip_address, ''))
                    AS unique_ip_addresses,
                COUNT(
                    DISTINCT CASE
                        WHEN u.account_locked_until IS NOT NULL
                         AND u.account_locked_until > NOW()
                        THEN u.id
                        ELSE NULL
                    END
                ) AS currently_locked_accounts
             $fromSql
             WHERE $whereSql",
            $params
        );
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $total = (int) ($summary['total_events'] ?? 0);
        $totalPages = max(1, (int) ceil($total / $limit));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $limit;

        // LIMIT and OFFSET are interpolated only after strict integer validation.
        $rowsStmt = $this->db->query(
            "SELECT
                la.id,
                la.user_id,
                la.username AS attempted_identifier,
                COALESCE(u.username, la.username) AS username,
                u.first_name,
                u.last_name,
                u.email,
                u.status AS account_status,
                u.failed_login_attempts AS consecutive_failed_attempts,
                u.account_locked_until,
                la.status,
                la.failure_reason,
                la.ip_address,
                la.user_agent,
                la.created_at
             $fromSql
             WHERE $whereSql
             ORDER BY la.created_at DESC, la.id DESC
             LIMIT $limit OFFSET $offset",
            $params
        );

        $failureReasons = $this->db->query(
            "SELECT DISTINCT failure_reason
             FROM login_attempts
             WHERE failure_reason IS NOT NULL
               AND failure_reason <> ''
             ORDER BY failure_reason"
        )->fetchAll(PDO::FETCH_COLUMN);

        $lifetimeRecords = $this->scalar(
            'SELECT COUNT(*) FROM login_attempts'
        );

        return [
            'rows' => $rowsStmt->fetchAll(PDO::FETCH_ASSOC),
            'summary' => [
                'total_events' => $total,
                'successful_events' => (int) (
                    $summary['successful_events'] ?? 0
                ),
                'failed_events' => (int) (
                    $summary['failed_events'] ?? 0
                ),
                'events_last_24h' => (int) (
                    $summary['events_last_24h'] ?? 0
                ),
                'unique_ip_addresses' => (int) (
                    $summary['unique_ip_addresses'] ?? 0
                ),
                'currently_locked_accounts' => (int) (
                    $summary['currently_locked_accounts'] ?? 0
                ),
                'tracking_available' => $lifetimeRecords > 0,
            ],
            'available_filters' => [
                'failure_reasons' => array_values(array_filter(
                    array_map(
                        static fn ($reason): string => trim(
                            (string) $reason
                        ),
                        $failureReasons ?: []
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
     * registry, so there is one read contract over auth_sessions.
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

        $baseWhere = ['s.expires_at > NOW()'];
        $params = [];

        if ($search !== '') {
            $term = '%' . $search . '%';
            $baseWhere[] = '(
                u.username LIKE ?
                OR u.email LIKE ?
                OR u.first_name LIKE ?
                OR u.last_name LIKE ?
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
            $baseWhere[] = 'u.role_id = ?';
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
            FROM auth_sessions s
            INNER JOIN users u ON u.id = s.user_id
            LEFT JOIN roles r ON r.id = u.role_id
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
                            WHEN s.expires_at <= DATE_ADD(
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
                u.first_name,
                u.last_name,
                u.email,
                u.status AS account_status,
                u.role_id,
                r.name AS role_name,
                s.ip_address,
                s.user_agent,
                s.last_activity,
                s.expires_at,
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
             GROUP BY u.role_id, r.name
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
             FROM auth_sessions s
             INNER JOIN users u ON u.id = s.user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE s.expires_at > NOW()
               AND s.last_activity >= DATE_SUB(
                    NOW(),
                    INTERVAL {$idleTimeoutSeconds} SECOND
               )
             ORDER BY r.name"
        )->fetchAll(PDO::FETCH_ASSOC);

        $trackedSessionRecords = $this->scalar(
            'SELECT COUNT(*) FROM auth_sessions'
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

    public function getHealthErrors(): array
    {
        $errorsStmt = $this->db->query(
            "SELECT
                id,
                error_type,
                message,
                file_path,
                line_number,
                user_id,
                ip_address,
                created_at
             FROM system_error_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY created_at DESC
             LIMIT 50"
        );

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

        $errorCount = $this->scalar(
            "SELECT COUNT(*)
             FROM system_error_logs
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
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
            'errors' => $errorsStmt->fetchAll(PDO::FETCH_ASSOC),
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
        $failedAuthStmt = $this->db->query(
            "SELECT
                MIN(id) AS id,
                ip_address,
                COUNT(*) AS attempt_count,
                GROUP_CONCAT(DISTINCT reason ORDER BY reason SEPARATOR ', ') AS reasons,
                MAX(created_at) AS created_at
             FROM failed_auth_attempts
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY ip_address
             HAVING COUNT(*) >= 3
             ORDER BY attempt_count DESC, created_at DESC
             LIMIT 25"
        );
        $failedAuthGroups = $failedAuthStmt->fetchAll(PDO::FETCH_ASSOC);
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
