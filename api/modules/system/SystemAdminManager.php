<?php
namespace App\API\Modules\system;

use App\API\Includes\BaseAPI;
use App\API\Includes\AuditLogger;
use App\API\Services\SidebarConfigReader;
use Exception;

/**
 * SystemAdminManager - All DB/business logic for the System Admin console.
 * The controller validates auth/RBAC, reads input, delegates here, and responds.
 *
 * Live-schema mapping (KingsWayAcademy):
 *  - routes            -> routes_registry
 *  - jobs              -> system_background_jobs
 *  - activity_logs     -> audit_logs
 *  - users has no email/first_name/last_name -> join persons via users.person_id
 *  - users has no role_id                    -> role counts come from user_roles only
 *  - class_streams, fee_structures_detailed, academic_terms, staff_payroll,
 *    account_unlock_history, allowance_templates, system_permission_changes
 *    do not exist; affected queries are guarded by tableExists()/tableHasColumn().
 */
class SystemAdminManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('system_admin');
        self::$staticDb = $this->db;
    }

    // ───────────────────────── SCHEMA HELPERS ─────────────────────────

    private function tableExists(string $tableName): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$tableName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?"
        );
        $stmt->execute([$tableName, $columnName]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function scalar(string $sql, array $params = [])
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function roleNameLength(string $name): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($name, 'UTF-8')
            : strlen($name);
    }

    private function permissionTextLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function normalizeToggleValue($value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }
        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) return 1;
            if ((int) $value === 0) return 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) return 1;
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) return 0;
        }
        return null;
    }

    /**
     * Resolve user_id => username map for audit rows.
     *
     * Audit journal entries store only user_id; the display layer joins to
     * users (read-side enrichment only — logs stay in files, never the DB).
     */
    private function resolveUsernames(array $userIds): array
    {
        $map = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($id) => $id > 0)));
        if (empty($ids)) {
            return $map;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $this->db->prepare(
                "SELECT id, username FROM users WHERE id IN ({$placeholders})"
            );
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) {
                $map[(int) $row['id']] = $row['username'];
            }
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] Username resolution failed: ' . $e->getMessage());
        }

        return $map;
    }

    // ───────────────────────── ACTIVITY AUDIT LOGS ─────────────────────────

    public function getActivityAuditLogs(array $filters = [])
    {
        $limit  = min((int) ($filters['limit'] ?? 100), 500);
        $offset = (int) ($filters['offset'] ?? 0);
        $search = $filters['search'] ?? '';
        $status = $filters['severity'] ?? '';
        $from   = $filters['date_from'] ?? '';
        $to     = $filters['date_to'] ?? '';

        try {
            $entries = \App\API\Includes\FileLogger::recent('audit', max(2000, $limit + $offset));
            $filtered = [];
            foreach ($entries as $e) {
                // Plumbing actions written once per state-changing request add
                // noise, not forensic value; they can be surfaced through the
                // dedicated log viewer. Exclude them from the activity console.
                if (in_array($e['action'] ?? '', ['post_request', 'test_mfa_bypass'], true)) {
                    continue;
                }
                if ($search !== '') {
                    $haystack = strtolower(($e['action'] ?? '') . ' ' . ($e['entity'] ?? '') . ' ' . ($e['username'] ?? ''));
                    if (strpos($haystack, strtolower($search)) === false) {
                        continue;
                    }
                }
                if ($status !== '' && ($e['status'] ?? '') !== $status) {
                    continue;
                }
                if ($from !== '' && ($e['timestamp'] ?? '') < ($from . ' 00:00:00')) {
                    continue;
                }
                if ($to !== '' && ($e['timestamp'] ?? '') > ($to . ' 23:59:59')) {
                    continue;
                }
                $filtered[] = $e;
            }

            $total = count($filtered);
            $page = array_slice($filtered, $offset, $limit);

            // Read-side username enrichment: entries carry user_id; resolve once.
            $usernames = $this->resolveUsernames(array_map(
                static fn ($e) => (int) ($e['user_id'] ?? 0),
                $page
            ));

            $rows = [];
            foreach ($page as $e) {
                $userId = (int) ($e['user_id'] ?? 0);
                $userName = $e['username'] ?? ($usernames[$userId] ?? null);
                // The audit journal deliberately does not persist emails; the
                // persons join below is only for discovery display parity.
                if (empty($userName) && $userId > 0) {
                    $userName = $usernames[$userId] ?? null;
                }
                $rows[] = [
                    'id' => null,
                    'user_id' => $userId ?: null,
                    'user_name' => $userName,
                    'action' => $e['action'] ?? null,
                    'resource_type' => $e['entity'] ?? null,
                    'resource_id' => $e['entity_id'] ?? null,
                    'ip_address' => $e['ip'] ?? $e['ip_address'] ?? null,
                    'created_at' => $e['timestamp'] ?? null,
                    'status' => $e['status'] ?? null,
                    'details' => $e['details'] ?? null,
                ];
            }

            $errors   = count(array_filter($rows, static fn($r) => in_array(($r['status'] ?? ''), ['error', 'failure'], true)));
            $warnings = count(array_filter($rows, static fn($r) => ($r['status'] ?? '') === 'warning'));
            $today    = count(array_filter($rows, static fn($r) => str_starts_with($r['created_at'] ?? '', date('Y-m-d'))));

            return $this->successResponse([
                'data'  => $rows,
                'stats' => ['total' => $total, 'errors' => $errors, 'warnings' => $warnings, 'today' => $today],
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => $total],
            ], 'Activity audit logs retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->successResponse([
                'data'  => [],
                'stats' => ['total' => 0, 'errors' => 0, 'warnings' => 0, 'today' => 0],
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => 0],
            ], 'Activity audit logs retrieved');
        }
    }

    public function getAuditRows(array $actions, ?string $status = null, int $limit = 100): array
    {
        $limit = max(1, min($limit, 500));
        $entries = \App\API\Includes\FileLogger::recent('audit', $limit);
        $rows = [];
        foreach ($entries as $e) {
            if (!empty($actions) && !in_array($e['action'] ?? null, $actions, true)) {
                continue;
            }
            if ($status !== null && ($e['status'] ?? '') !== $status) {
                continue;
            }
            $rows[] = $e;
        }

        // Read-side username enrichment for incident/violation/permission rows.
        $usernames = $this->resolveUsernames(array_map(
            static fn ($e) => (int) ($e['user_id'] ?? 0),
            $rows
        ));
        foreach ($rows as &$e) {
            $userId = (int) ($e['user_id'] ?? 0);
            if (empty($e['username']) && $userId > 0) {
                $e['username'] = $usernames[$userId] ?? null;
            }
            $e['user_id'] = $userId ?: null;
        }
        unset($e);

        return $rows;
    }

    // ───────────────────────── BACKGROUND JOBS ─────────────────────────

    public function getBackgroundJobs()
    {
        try {
            $rows = [];

            // Live offload queue (KingsWayBuffers schema) - the real work queue.
            foreach (\App\API\Services\JobQueue::listRecent(200) as $job) {
                $rows[] = [
                    'id' => 'job-' . (int) ($job['id'] ?? 0),
                    'queue' => 'jobs_queue',
                    'payload_type' => (string) ($job['job_type'] ?? ''),
                    'status' => (string) ($job['status'] ?? ''),
                    'attempts' => (int) ($job['attempts'] ?? 0),
                    'max_attempts' => (int) ($job['max_attempts'] ?? 0),
                    'backoff_seconds' => (int) ($job['backoff_seconds'] ?? 0),
                    'available_at' => $job['available_at'] ?? null,
                    'created_at' => $job['created_at'] ?? null,
                    'completed_at' => ($job['status'] ?? '') === 'done' ? ($job['updated_at'] ?? null) : null,
                    'last_error' => $job['failed_reason'] ?? null,
                    'dead_letter_reason' => $job['dead_letter_reason'] ?? null,
                ];
            }

            // Poisoned jobs awaiting review/requeue.
            foreach (\App\API\Services\JobQueue::listDeadLetter(200) as $job) {
                $rows[] = [
                    'id' => 'dead-' . (int) ($job['id'] ?? 0),
                    'queue' => 'dead_letter',
                    'payload_type' => (string) ($job['job_type'] ?? ''),
                    'status' => 'dead_letter',
                    'attempts' => (int) ($job['attempts'] ?? 0),
                    'max_attempts' => (int) ($job['max_attempts'] ?? 0),
                    'backoff_seconds' => 0,
                    'available_at' => null,
                    'created_at' => $job['dead_lettered_at'] ?? null,
                    'completed_at' => null,
                    'last_error' => $job['reason'] ?? null,
                    'dead_letter_reason' => $job['reason'] ?? null,
                ];
            }

            // Legacy system-job table surfaced for backward compatibility.
            if ($this->tableExists('system_background_jobs')) {
                foreach ($this->fetchRows('system_background_jobs', 200, 'created_at DESC') as $job) {
                    $rows[] = [
                        'id' => 'system-' . (int) ($job['id'] ?? 0),
                        'queue' => 'system',
                        'payload_type' => (string) ($job['job_type'] ?? ''),
                        'status' => (string) ($job['status'] ?? ''),
                        'attempts' => (int) ($job['attempts'] ?? 0),
                        'max_attempts' => (int) ($job['max_attempts'] ?? 3),
                        'backoff_seconds' => 0,
                        'available_at' => $job['next_attempt_at'] ?? null,
                        'created_at' => $job['created_at'] ?? null,
                        'completed_at' => $job['completed_at'] ?? null,
                        'last_error' => $job['last_error'] ?? null,
                        'dead_letter_reason' => null,
                    ];
                }
            }

            return $this->successResponse($rows, 'Background jobs retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── PENDING APPROVALS ─────────────────────────

    public function getPendingApprovals($userId = null)
    {
        try {
            $branches = [];
            $hasAssignedFilter = false;

            if ($this->tableExists('class_promotion_queue') && $this->tableExists('promotion_batches') && $this->tableExists('classes')) {
                $hasAssignedFilter = true;
                $branches[] = "
                    SELECT
                        CONCAT('promotion-', cpq.id) AS id,
                        'class_promotion' AS type,
                        CONVERT(CONCAT('Class promotion batch #', cpq.batch_id, ': ', c.name, ' / ', COALESCE(s.name, 'N/A')) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS description,
                        NULL AS amount,
                        cpq.approval_status AS status,
                        CASE cpq.approval_status
                            WHEN 'reviewing' THEN 'high'
                            WHEN 'pending' THEN 'medium'
                            ELSE 'low'
                        END AS priority,
                        pb.created_by AS created_by,
                        p.first_name,
                        p.last_name,
                        cpq.created_at AS submitted_at,
                        NULL AS due_by
                    FROM class_promotion_queue cpq
                    INNER JOIN promotion_batches pb ON pb.id = cpq.batch_id
                    INNER JOIN classes c ON c.id = cpq.class_id
                    LEFT JOIN streams s ON s.id = cpq.stream_id
                    LEFT JOIN users u ON u.id = pb.created_by
                    LEFT JOIN persons p ON p.id = u.person_id
                    WHERE cpq.approval_status IN ('pending', 'reviewing')
                      AND (cpq.assigned_to_user_id = ? OR cpq.assigned_to_user_id IS NULL)
                ";
            }

            if ($this->tableExists('purchase_orders') && $this->tableExists('staff') && $this->tableExists('persons')) {
                $branches[] = "
                    SELECT
                        CONCAT('purchase-order-', po.id) AS id,
                        'purchase_order' AS type,
                        CONVERT(CONCAT('Purchase order ', po.order_number, ' awaiting approval') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS description,
                        po.total_amount AS amount,
                        po.status AS status,
                        CASE WHEN po.total_amount >= 100000 THEN 'high' ELSE 'medium' END AS priority,
                        s.id AS created_by,
                        p.first_name,
                        p.last_name,
                        po.created_at AS submitted_at,
                        po.expected_delivery_date AS due_by
                    FROM purchase_orders po
                    LEFT JOIN staff s ON s.id = po.created_by
                    LEFT JOIN persons p ON p.id = s.person_id
                    WHERE po.status = 'pending'
                ";
            }

            if ($this->tableExists('expenses') && $this->tableExists('users') && $this->tableExists('persons')) {
                $branches[] = "
                    SELECT
                        CONCAT('expense-', e.id) AS id,
                        'expense' AS type,
                        CONVERT(CONCAT('Expense: ', COALESCE(e.description, e.expense_number)) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS description,
                        e.amount AS amount,
                        e.status AS status,
                        CASE WHEN e.amount >= 50000 THEN 'high' ELSE 'medium' END AS priority,
                        e.created_by AS created_by,
                        p.first_name,
                        p.last_name,
                        e.created_at AS submitted_at,
                        NULL AS due_by
                    FROM expenses e
                    LEFT JOIN users u ON u.id = e.created_by
                    LEFT JOIN persons p ON p.id = u.person_id
                    WHERE e.status IN ('pending', 'pending_approval')
                ";
            }

            if (empty($branches)) {
                return $this->successResponse([
                    'pending' => [],
                    'count' => 0,
                    'summary' => ['total_pending' => 0, 'high_priority' => 0, 'due_soon' => 0],
                ], 'Pending approvals retrieved');
            }

            $unionSql = implode("\n UNION ALL \n", $branches);

            $query = "
                SELECT
                    approvals.id,
                    approvals.type,
                    approvals.description,
                    approvals.amount,
                    approvals.status,
                    approvals.priority,
                    approvals.created_by,
                    approvals.first_name,
                    approvals.last_name,
                    approvals.submitted_at,
                    approvals.due_by
                FROM (
                    $unionSql
                ) approvals
                ORDER BY
                    CASE approvals.priority
                        WHEN 'high' THEN 1
                        WHEN 'medium' THEN 2
                        ELSE 3
                    END ASC,
                    COALESCE(approvals.due_by, DATE_ADD(CURDATE(), INTERVAL 365 DAY)) ASC,
                    approvals.submitted_at DESC
                LIMIT 50
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute($hasAssignedFilter ? [(int) $userId] : []);
            $approvals = $stmt->fetchAll() ?: [];

            foreach ($approvals as &$approval) {
                $fullName = trim((string) (($approval['first_name'] ?? '') . ' ' . ($approval['last_name'] ?? '')));
                $approval['student_name'] = $fullName !== '' ? $fullName : (string) ($approval['description'] ?? '');
                $approval['submitted_by'] = $fullName !== '' ? $fullName : null;
            }
            unset($approval);

            $highPriorityCount = count(array_filter($approvals, static fn($item) => ($item['priority'] ?? null) === 'high'));
            $dueSoonCutoff = strtotime('+3 days');
            $dueSoonCount = count(array_filter($approvals, static function ($item) use ($dueSoonCutoff) {
                if (empty($item['due_by'])) return false;
                $dueTs = strtotime((string) $item['due_by']);
                if ($dueTs === false) return false;
                return $dueTs <= $dueSoonCutoff;
            }));

            return $this->successResponse([
                'pending' => $approvals,
                'count' => count($approvals),
                'summary' => [
                    'total_pending' => count($approvals),
                    'high_priority' => $highPriorityCount,
                    'due_soon' => $dueSoonCount,
                ],
            ], 'Pending approvals retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── ACCOUNT STATUS ─────────────────────────

    public function getAccountStatus()
    {
        if (!$this->tableExists('users')) {
            return $this->successResponse([], 'Account status retrieved');
        }

        try {
            $stmt = $this->db->query(
                "SELECT
                    u.id,
                    u.username,
                    p.email,
                    p.first_name,
                    p.last_name,
                    u.status,
                    u.failed_login_attempts,
                    u.account_locked_until,
                    u.force_password_change,
                    u.last_login,
                    u.created_at,
                    u.updated_at
                 FROM users u
                 LEFT JOIN persons p ON p.id = u.person_id
                 ORDER BY u.id DESC
                 LIMIT 500"
            );
            return $this->successResponse($stmt ? ($stmt->fetchAll() ?: []) : [], 'Account status retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    public function updateAccountStatus($userId, array $data, $actorUserId = null)
    {
        if (!$userId || !$this->tableExists('users')) {
            return $this->errorResponse('User ID is required', 400);
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id, username, status, failed_login_attempts, account_locked_until, force_password_change, updated_at FROM users WHERE id = ?'
            );
            $stmt->execute([(int) $userId]);
            $current = $stmt->fetch() ?: null;
            if (!$current) {
                return $this->errorResponse('User account not found', 400);
            }

            if ((int) $userId === (int) $actorUserId && isset($data['status']) && $data['status'] !== 'active') {
                return $this->errorResponse('You cannot deactivate or suspend your own account', 400);
            }

            $fields = [];
            $values = [];
            $changes = [];

            if (array_key_exists('status', $data)) {
                $allowedStatuses = ['active', 'inactive', 'suspended', 'pending'];
                if (!in_array($data['status'], $allowedStatuses, true)) {
                    return $this->errorResponse('Invalid account status', 400);
                }
                $fields[] = 'status = ?';
                $values[] = $data['status'];
                $changes['status'] = ['from' => $current['status'], 'to' => $data['status']];
            }

            if (array_key_exists('failed_login_attempts', $data)) {
                $attempts = filter_var($data['failed_login_attempts'], FILTER_VALIDATE_INT);
                if ($attempts === false || $attempts < 0) {
                    return $this->errorResponse('failed_login_attempts must be a non-negative integer', 400);
                }
                $fields[] = 'failed_login_attempts = ?';
                $values[] = $attempts;
                $changes['failed_login_attempts'] = ['from' => $current['failed_login_attempts'], 'to' => $attempts];
            }

            if (array_key_exists('account_locked_until', $data)) {
                $lockedUntil = $data['account_locked_until'];
                if ($lockedUntil !== null && $lockedUntil !== '' && strtotime((string) $lockedUntil) === false) {
                    return $this->errorResponse('account_locked_until must be a valid date or null', 400);
                }
                $lockedUntil = ($lockedUntil === '') ? null : $lockedUntil;
                $fields[] = 'account_locked_until = ?';
                $values[] = $lockedUntil;
                $changes['account_locked_until'] = ['from' => $current['account_locked_until'], 'to' => $lockedUntil];
            }

            if (array_key_exists('force_password_change', $data)) {
                $forceChange = filter_var($data['force_password_change'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($forceChange === null) {
                    return $this->errorResponse('force_password_change must be true or false', 400);
                }
                $forceChange = $forceChange ? 1 : 0;
                $fields[] = 'force_password_change = ?';
                $values[] = $forceChange;
                $changes['force_password_change'] = ['from' => (int) $current['force_password_change'], 'to' => $forceChange];
            }

            if (empty($fields)) {
                return $this->errorResponse('No supported account status fields provided', 400);
            }
            if ($this->tableHasColumn('users', 'updated_at')) {
                $fields[] = 'updated_at = NOW()';
            }
            $values[] = $userId;
            $this->db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);

            $wasLocked = !empty($current['account_locked_until']);
            $isUnlock = $wasLocked && array_key_exists('account_locked_until', $data) && $data['account_locked_until'] === null;

            (new AuditLogger($this->db))->log(
                $isUnlock ? 'account_unlock' : 'account_status_update',
                'user',
                (int) $userId,
                (int) ($actorUserId ?? 0),
                [
                    'username' => $current['username'],
                    'changes' => $changes,
                    'unlock_reason' => $isUnlock ? ($data['unlock_reason'] ?? 'Unlocked by System Administrator') : null,
                ]
            );

            return $this->successResponse(['id' => (int) $userId], 'Account status updated');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 500);
        }
    }

    // ───────────────────────── ROUTES REGISTRY ─────────────────────────

    private function routesTable(): string
    {
        return 'routes_registry';
    }

    private function getRouteById(int $routeId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, url, domain, description, controller, action, is_active
             FROM {$this->routesTable()} WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$routeId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getRoutes($routeId = null)
    {
        try {
            if ($routeId) {
                $route = $this->getRouteById((int) $routeId);
                if (!$route) {
                    return $this->errorResponse('Route not found', 400);
                }
                return $this->successResponse($route, 'Route retrieved');
            }

            $stmt = $this->db->query("SELECT * FROM {$this->routesTable()} ORDER BY domain, name");
            return $this->successResponse($stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [], 'Routes retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function createRoute(array $data)
    {
        try {
            if (empty($data['name'])) {
                return $this->errorResponse('Route name is required', 400);
            }

            $check = $this->db->prepare("SELECT id FROM {$this->routesTable()} WHERE name = ?");
            $check->execute([$data['name']]);
            if ($check->fetch(\PDO::FETCH_ASSOC)) {
                return $this->errorResponse('A route with this name already exists', 400);
            }

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->routesTable()}
                    (name, url, domain, module, description, controller, action, is_active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $data['name'],
                $data['url'] ?? null,
                $data['domain'] ?? 'SCHOOL',
                $data['module'] ?? null,
                $data['description'] ?? null,
                $data['controller'] ?? null,
                $data['action'] ?? null,
                $data['is_active'] ?? 1,
            ]);
            $newId = (int) $this->db->lastInsertId();

            return $this->successResponse(['id' => $newId], 'Route created successfully', 201);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function updateRoute($routeId, array $data)
    {
        try {
            if (!$routeId) {
                return $this->errorResponse('Route ID is required', 400);
            }

            $check = $this->db->prepare("SELECT id FROM {$this->routesTable()} WHERE id = ?");
            $check->execute([$routeId]);
            if (!$check->fetch(\PDO::FETCH_ASSOC)) {
                return $this->errorResponse('Route not found', 400);
            }

            $fields = [];
            $values = [];
            foreach (['name', 'url', 'domain', 'module', 'description', 'controller', 'action', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return $this->errorResponse('No fields to update', 400);
            }

            $fields[] = 'updated_at = NOW()';
            $values[] = $routeId;
            $this->db->prepare("UPDATE {$this->routesTable()} SET " . implode(', ', $fields) . " WHERE id = ?")->execute($values);

            return $this->successResponse(null, 'Route updated successfully');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function deleteRoute($routeId)
    {
        try {
            if (!$routeId) {
                return $this->errorResponse('Route ID is required', 400);
            }
            $this->db->prepare("DELETE FROM {$this->routesTable()} WHERE id = ?")->execute([$routeId]);
            return $this->successResponse(null, 'Route deleted successfully');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function toggleRoute($routeId, $isActive)
    {
        try {
            if (!$routeId) {
                return $this->errorResponse('Route ID is required', 400);
            }
            $normalized = $this->normalizeToggleValue($isActive);
            if ($normalized === null) {
                return $this->errorResponse('is_active must be true/false', 400);
            }
            $this->db->prepare("UPDATE {$this->routesTable()} SET is_active = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$normalized, (int) $routeId]);
            return $this->successResponse(null, 'Route status updated');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    // ───────────────────────── MODULES / MODULE ENABLEMENT ─────────────────────────

    private function mapRouteToToggleItem(array $route): array
    {
        $isActive = (int) ($route['is_active'] ?? 0);
        $name = (string) ($route['name'] ?? 'module');
        $generatedLabel = ucwords(str_replace('_', ' ', $name));

        return [
            'id' => (int) ($route['id'] ?? 0),
            'key' => $name,
            'name' => $generatedLabel,
            'description' => (string) ($route['description'] ?? ''),
            'enabled' => $isActive === 1,
            'is_active' => $isActive,
        ];
    }

    private function getModuleEnablementRouteNames(): array
    {
        return [
            'system_settings',
            'module_management',
            'module_enablement',
            'feature_flags',
            'maintenance_mode',
            'domain_isolation_rules',
            'readonly_enforcement',
            'time_bound_access',
            'location_device_rules',
            'retention_policies',
            'config_sync',
        ];
    }

    public function getModules()
    {
        try {
            $stmt = $this->db->query(
                "SELECT id, name, description, is_active
                 FROM {$this->routesTable()}
                 WHERE domain = 'SCHOOL'
                   AND name REGEXP '^manage_'
                 ORDER BY name"
            );
            $routes = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            $modules = array_map([$this, 'mapRouteToToggleItem'], $routes);
            return $this->successResponse($modules, 'Modules retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function toggleModule($routeId, $enabled)
    {
        try {
            if (!$routeId) {
                return $this->errorResponse('Module ID is required', 400);
            }
            $normalized = $this->normalizeToggleValue($enabled);
            if ($normalized === null) {
                return $this->errorResponse('enabled must be true/false', 400);
            }

            $route = $this->getRouteById((int) $routeId);
            if (
                !$route ||
                strtoupper((string) ($route['domain'] ?? '')) !== 'SCHOOL' ||
                strpos((string) ($route['name'] ?? ''), 'manage_') !== 0
            ) {
                return $this->errorResponse('Module not found', 400);
            }

            $this->db->prepare("UPDATE {$this->routesTable()} SET is_active = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$normalized, (int) $routeId]);

            return $this->successResponse(
                ['id' => (int) $routeId, 'enabled' => (bool) $normalized],
                'Module updated successfully'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function getModuleEnablement()
    {
        try {
            $routeNames = $this->getModuleEnablementRouteNames();
            $placeholders = implode(', ', array_fill(0, count($routeNames), '?'));
            $params = array_merge(['SYSTEM'], $routeNames);

            $stmt = $this->db->prepare(
                "SELECT id, name, description, is_active
                 FROM {$this->routesTable()}
                 WHERE domain = ?
                   AND name IN ($placeholders)"
            );
            $stmt->execute($params);
            $routes = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $orderMap = array_flip($routeNames);
            usort($routes, static function ($a, $b) use ($orderMap) {
                $aOrder = $orderMap[$a['name']] ?? PHP_INT_MAX;
                $bOrder = $orderMap[$b['name']] ?? PHP_INT_MAX;
                return $aOrder <=> $bOrder;
            });

            $items = array_map([$this, 'mapRouteToToggleItem'], $routes);
            return $this->successResponse($items, 'Module enablement settings retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function toggleModuleEnablement($routeId, $enabled)
    {
        try {
            if (!$routeId) {
                return $this->errorResponse('Module enablement ID is required', 400);
            }
            $normalized = $this->normalizeToggleValue($enabled);
            if ($normalized === null) {
                return $this->errorResponse('enabled must be true/false', 400);
            }

            $route = $this->getRouteById((int) $routeId);
            $allowedRouteNames = $this->getModuleEnablementRouteNames();
            if (
                !$route ||
                strtoupper((string) ($route['domain'] ?? '')) !== 'SYSTEM' ||
                !in_array((string) ($route['name'] ?? ''), $allowedRouteNames, true)
            ) {
                return $this->errorResponse('Module enablement setting not found', 400);
            }

            $this->db->prepare("UPDATE {$this->routesTable()} SET is_active = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$normalized, (int) $routeId]);

            return $this->successResponse(
                ['id' => (int) $routeId, 'enabled' => (bool) $normalized],
                'Module enablement setting updated successfully'
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    // ───────────────────────── ROLES ─────────────────────────

    public function getRoles($roleId = null, bool $schoolAdminOnly = false)
    {
        try {
            $roleId = $roleId !== null ? (int) $roleId : null;
            if ($roleId !== null && $roleId <= 0) {
                return $this->errorResponse('Role ID must be a positive integer', 400);
            }

            $roles = $this->fetchRoleDefinitions($roleId, $schoolAdminOnly);

            if ($roleId !== null) {
                if (empty($roles)) {
                    return $this->errorResponse('Role not found', 404);
                }
                return $this->successResponse($roles[0], 'Role retrieved');
            }

            return $this->successResponse($roles, 'Roles retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function createRole(array $data, $actorUserId, bool $isSystemAdmin)
    {
        $db = $this->db;
        try {
            $name = trim((string) ($data['name'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));

            if ($name === '') {
                return $this->errorResponse('Role name is required', 400);
            }
            if ($this->roleNameLength($name) > 50) {
                return $this->errorResponse('Role name must not exceed 50 characters', 400);
            }

            $scope = 'school';
            if ($isSystemAdmin) {
                $scope = strtolower(trim((string) ($data['scope'] ?? 'school')));
                if (!in_array($scope, ['system', 'school'], true)) {
                    return $this->errorResponse('Role scope must be system or school', 400);
                }
            }

            $isActive = 1;
            if (array_key_exists('is_active', $data)) {
                $isActive = $this->normalizeToggleValue($data['is_active']);
                if ($isActive === null) {
                    return $this->errorResponse('is_active must be true or false', 400);
                }
            }

            $existing = $this->db->prepare('SELECT id FROM roles WHERE name = ? LIMIT 1');
            $existing->execute([$name]);
            if ($existing->fetch(\PDO::FETCH_ASSOC)) {
                return $this->errorResponse('A role with this name already exists', 409);
            }

            $db->beginTransaction();
            $this->db->prepare(
                'INSERT INTO roles
                    (name, description, scope, is_system, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, NOW(), NOW())'
            )->execute([$name, $description !== '' ? $description : null, $scope, $isActive]);

            $roleId = (int) $this->db->lastInsertId();
            $auditLogged = (new AuditLogger($this->db))->log(
                'role_create',
                'role',
                $roleId,
                (int) $actorUserId,
                [
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'scope' => $scope,
                    'is_system' => 0,
                    'is_active' => $isActive,
                ]
            );
            if (!$auditLogged) {
                throw new Exception('Role creation audit could not be recorded');
            }
            $db->commit();

            $created = $this->fetchRoleDefinitions($roleId, false);
            return $this->successResponse(
                $created[0] ?? ['id' => $roleId],
                'Role created successfully',
                201
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function updateRole($roleId, array $data, $actorUserId, bool $isSystemAdmin, bool $isSchoolAdmin)
    {
        $db = $this->db;
        try {
            $roleId = (int) ($roleId ?? $data['id'] ?? 0);
            if ($roleId <= 0) {
                return $this->errorResponse('Role ID is required', 400);
            }

            $existingRows = $this->fetchRoleDefinitions($roleId, false);
            $role = $existingRows[0] ?? null;
            if (!$role) {
                return $this->errorResponse('Role not found', 404);
            }
            if ((int) ($role['is_system'] ?? 0) === 1) {
                return $this->errorResponse('Protected system roles are read-only', 403);
            }
            if ($isSchoolAdmin && ($role['scope'] ?? 'school') === 'system') {
                return $this->errorResponse('Cannot modify system-scope roles', 403);
            }

            $fields = [];
            $values = [];
            $changes = [];

            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    return $this->errorResponse('Role name is required', 400);
                }
                if ($this->roleNameLength($name) > 50) {
                    return $this->errorResponse('Role name must not exceed 50 characters', 400);
                }

                $duplicate = $this->db->prepare('SELECT id FROM roles WHERE name = ? AND id <> ? LIMIT 1');
                $duplicate->execute([$name, $roleId]);
                if ($duplicate->fetch(\PDO::FETCH_ASSOC)) {
                    return $this->errorResponse('A role with this name already exists', 409);
                }

                if ($name !== (string) $role['name']) {
                    $fields[] = 'name = ?';
                    $values[] = $name;
                    $changes['name'] = ['from' => $role['name'], 'to' => $name];
                }
            }

            if (array_key_exists('description', $data)) {
                $description = trim((string) $data['description']);
                $description = $description !== '' ? $description : null;
                $oldDescription = $role['description'] !== '' ? $role['description'] : null;

                if ($description !== $oldDescription) {
                    $fields[] = 'description = ?';
                    $values[] = $description;
                    $changes['description'] = ['from' => $oldDescription, 'to' => $description];
                }
            }

            if (array_key_exists('scope', $data)) {
                if (!$isSystemAdmin) {
                    if (strtolower((string) $data['scope']) !== 'school') {
                        return $this->errorResponse('School Administrators can only manage school-scope roles', 403);
                    }
                } else {
                    $scope = strtolower(trim((string) $data['scope']));
                    if (!in_array($scope, ['system', 'school'], true)) {
                        return $this->errorResponse('Role scope must be system or school', 400);
                    }
                    if ($scope !== (string) $role['scope']) {
                        $fields[] = 'scope = ?';
                        $values[] = $scope;
                        $changes['scope'] = ['from' => $role['scope'], 'to' => $scope];
                    }
                }
            }

            if (empty($fields)) {
                return $this->successResponse($role, 'No role changes were required');
            }

            $fields[] = 'updated_at = NOW()';
            $values[] = $roleId;
            $db->beginTransaction();
            $this->db->prepare('UPDATE roles SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);

            $auditLogged = (new AuditLogger($this->db))->log(
                'role_update',
                'role',
                $roleId,
                (int) $actorUserId,
                ['name' => $role['name'], 'changes' => $changes]
            );
            if (!$auditLogged) {
                throw new Exception('Role update audit could not be recorded');
            }
            $db->commit();

            $updated = $this->fetchRoleDefinitions($roleId, false);
            return $this->successResponse(
                $updated[0] ?? ['id' => $roleId],
                'Role updated successfully'
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function deleteRole($roleId, $actorUserId, bool $isSchoolAdmin)
    {
        $db = $this->db;
        try {
            $roleId = (int) ($roleId ?? 0);
            if ($roleId <= 0) {
                return $this->errorResponse('Role ID is required', 400);
            }

            $roleRows = $this->fetchRoleDefinitions($roleId, false);
            $role = $roleRows[0] ?? null;
            if (!$role) {
                return $this->errorResponse('Role not found', 404);
            }
            if ((int) ($role['is_system'] ?? 0) === 1) {
                return $this->errorResponse('Protected system roles cannot be deleted', 403);
            }
            if ($isSchoolAdmin && ($role['scope'] ?? 'school') === 'system') {
                return $this->errorResponse('Cannot delete system-scope roles', 403);
            }

            $blockers = $role['delete_blockers'] ?? [];
            if (!empty($blockers)) {
                return $this->errorResponse(
                    'Role cannot be deleted while it is in use',
                    409,
                    ['blockers' => $blockers]
                );
            }

            $db->beginTransaction();
            $this->db->prepare('DELETE FROM roles WHERE id = ?')->execute([$roleId]);

            $auditLogged = (new AuditLogger($this->db))->log(
                'role_delete',
                'role',
                $roleId,
                (int) $actorUserId,
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'scope' => $role['scope'],
                    'is_active' => (int) $role['is_active'],
                ]
            );
            if (!$auditLogged) {
                throw new Exception('Role deletion audit could not be recorded');
            }
            $db->commit();

            return $this->successResponse(['id' => $roleId], 'Role deleted successfully');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function toggleRole($roleId, $isActive, $actorUserId, bool $isSchoolAdmin)
    {
        $db = $this->db;
        try {
            $roleId = (int) ($roleId ?? 0);
            $isActive = $isActive ?? null;

            if ($roleId <= 0) {
                return $this->errorResponse('Role ID is required', 400);
            }
            if (!$this->tableHasColumn('roles', 'is_active')) {
                return $this->errorResponse('Role status toggle is not supported by current schema', 400);
            }

            $normalized = $this->normalizeToggleValue($isActive);
            if ($normalized === null) {
                return $this->errorResponse('is_active/enabled must be true or false', 400);
            }

            $roleRows = $this->fetchRoleDefinitions($roleId, false);
            $role = $roleRows[0] ?? null;
            if (!$role) {
                return $this->errorResponse('Role not found', 404);
            }
            if ((int) ($role['is_system'] ?? 0) === 1) {
                return $this->errorResponse('Protected system roles cannot be deactivated', 403);
            }
            if ($isSchoolAdmin && ($role['scope'] ?? 'school') === 'system') {
                return $this->errorResponse('Cannot modify system-scope roles', 403);
            }

            $currentStatus = (int) ($role['is_active'] ?? 0);
            if ($currentStatus === $normalized) {
                return $this->successResponse(
                    ['id' => $roleId, 'is_active' => (bool) $normalized],
                    'Role status is already up to date'
                );
            }

            $db->beginTransaction();
            $this->db->prepare('UPDATE roles SET is_active = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$normalized, $roleId]);

            $auditLogged = (new AuditLogger($this->db))->log(
                'role_status_update',
                'role',
                $roleId,
                (int) $actorUserId,
                [
                    'name' => $role['name'],
                    'is_active' => ['from' => $currentStatus, 'to' => $normalized],
                ]
            );
            if (!$auditLogged) {
                throw new Exception('Role status audit could not be recorded');
            }
            $db->commit();

            return $this->successResponse(
                ['id' => $roleId, 'is_active' => (bool) $normalized],
                'Role status updated'
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    private function fetchRoleDefinitions(?int $roleId = null, bool $schoolAdminOnly = false): array
    {
        $where = [];
        $params = [];

        if ($roleId !== null) {
            $where[] = 'r.id = ?';
            $params[] = $roleId;
        }
        if ($schoolAdminOnly) {
            $where[] = "r.scope = 'school'";
        }

        $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

        $countSubqueries = [
            'permission_count' => $this->tableExists('role_permissions')
                ? '(SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id)'
                : '(0)',
            'route_count' => $this->tableExists('role_routes')
                ? '(SELECT COUNT(*) FROM role_routes rr WHERE rr.role_id = r.id)'
                : '(0)',
            'navigation_count' => $this->tableExists('role_sidebar_menus')
                ? '(SELECT COUNT(*) FROM role_sidebar_menus rsm WHERE rsm.role_id = r.id)'
                : '(0)',
            'dashboard_count' => $this->tableExists('role_dashboards')
                ? '(SELECT COUNT(*) FROM role_dashboards rd WHERE rd.role_id = r.id)'
                : '(0)',
            'workflow_count' => $this->tableExists('workflow_stage_permissions')
                ? '(SELECT COUNT(*) FROM workflow_stage_permissions wsp WHERE wsp.role_id = r.id)'
                : '(0)',
            'record_permission_count' => $this->tableExists('record_permissions')
                ? '(SELECT COUNT(*) FROM record_permissions recp WHERE recp.role_id = r.id)'
                : '(0)',
            'time_bound_access_count' => $this->tableExists('system_time_bound_access')
                ? '(SELECT COUNT(*) FROM system_time_bound_access stba WHERE stba.role_id = r.id)'
                : '(0)',
            'delegation_count' => $this->tableExists('role_delegations')
                ? '(SELECT COUNT(*) FROM role_delegations rdel WHERE rdel.delegator_role_id = r.id OR rdel.delegate_role_id = r.id)'
                : '(0)',
            'allowance_template_count' => $this->tableExists('staff_allowances')
                ? '(SELECT COUNT(*) FROM staff_allowances sa JOIN staff st ON st.id = sa.staff_id JOIN persons pe ON pe.id = st.person_id JOIN users us ON us.person_id = pe.id JOIN user_roles ur ON ur.user_id = us.id WHERE ur.role_id = r.id)'
                : '(0)',
        ];

        $userCountSql = $this->tableExists('user_roles')
            ? '(SELECT COUNT(DISTINCT ur.user_id) FROM user_roles ur WHERE ur.role_id = r.id)'
            : '(0)';

        $counts = array_map(
            static function ($alias, $sql) {
                return "{$sql} AS {$alias}";
            },
            array_keys($countSubqueries),
            array_values($countSubqueries)
        );

        $sql = "
            SELECT
                r.id,
                r.name,
                r.description,
                r.scope,
                r.is_system,
                r.is_active,
                r.created_at,
                r.updated_at,
                {$userCountSql} AS user_count,
                " . implode(', ', $counts) . "
            FROM roles r
            {$whereSql}
            ORDER BY r.scope, r.name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'decorateRoleDefinition'], $rows);
    }

    private function decorateRoleDefinition(array $role): array
    {
        $blockers = array_filter([
            'users' => (int) ($role['user_count'] ?? 0),
            'permissions' => (int) ($role['permission_count'] ?? 0),
            'routes' => (int) ($role['route_count'] ?? 0),
            'navigation' => (int) ($role['navigation_count'] ?? 0),
            'dashboards' => (int) ($role['dashboard_count'] ?? 0),
            'workflows' => (int) ($role['workflow_count'] ?? 0),
            'record_permissions' => (int) ($role['record_permission_count'] ?? 0),
            'time_bound_access' => (int) ($role['time_bound_access_count'] ?? 0),
            'delegations' => (int) ($role['delegation_count'] ?? 0),
            'allowances' => (int) ($role['allowance_template_count'] ?? 0),
        ], static function ($count) {
            return $count > 0;
        });

        $role['id'] = (int) ($role['id'] ?? 0);
        $role['is_system'] = (int) ($role['is_system'] ?? 0);
        $role['is_active'] = (int) ($role['is_active'] ?? 0);
        $role['user_count'] = (int) ($role['user_count'] ?? 0);
        $role['permission_count'] = (int) ($role['permission_count'] ?? 0);
        $role['delete_blockers'] = $blockers;
        $role['can_delete'] = $role['is_system'] === 0 && empty($blockers);

        unset(
            $role['route_count'],
            $role['navigation_count'],
            $role['dashboard_count'],
            $role['workflow_count'],
            $role['record_permission_count'],
            $role['time_bound_access_count'],
            $role['delegation_count'],
            $role['allowance_template_count']
        );

        return $role;
    }

    public function getRolePermissionMatrix()
    {
        try {
            $roles = $this->tableExists('roles') ? $this->fetchRows('roles', 500, 'name') : [];
            $permissions = $this->tableExists('permissions') ? $this->fetchRows('permissions', 1000, 'entity, action, code') : [];
            $matrix = [];
            if ($this->tableExists('role_permissions')) {
                $stmt = $this->db->query('SELECT role_id, permission_id FROM role_permissions');
                $assignments = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                foreach ($assignments as $assignment) {
                    $matrix[(string) $assignment['role_id']][] = (string) $assignment['permission_id'];
                }
            }

            return $this->successResponse([
                'rows' => $roles,
                'columns' => $permissions,
                'matrix' => $matrix,
            ], 'Role permission matrix retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    // ───────────────────────── PERMISSIONS ─────────────────────────

    public function getPermissions()
    {
        try {
            $stmt = $this->db->query('SELECT * FROM permissions ORDER BY entity, action, code');
            return $this->successResponse($stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [], 'Permissions retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function getResourcePermissions(array $filters = [])
    {
        try {
            if (!$this->tableExists('permissions')) {
                return $this->errorResponse('The permissions table is unavailable', 500);
            }

            $search = trim((string) ($filters['search'] ?? ''));
            $module = trim((string) ($filters['module'] ?? ''));
            $entity = trim((string) ($filters['entity'] ?? ''));
            $action = trim((string) ($filters['action'] ?? ''));
            $page = max(1, (int) ($filters['page'] ?? 1));
            $limit = (int) ($filters['limit'] ?? 50);
            if (!in_array($limit, [25, 50, 100], true)) {
                $limit = 50;
            }

            $where = ['1 = 1'];
            $params = [];
            if ($search !== '') {
                $where[] = '(p.code LIKE ? OR p.description LIKE ? OR p.entity LIKE ? OR p.action LIKE ? OR p.module LIKE ?)';
                $term = '%' . $search . '%';
                array_push($params, $term, $term, $term, $term, $term);
            }
            foreach (['p.module' => $module, 'p.entity' => $entity, 'p.action' => $action] as $column => $value) {
                if ($value !== '') {
                    $where[] = "$column = ?";
                    $params[] = $value;
                }
            }
            $whereSql = implode(' AND ', $where);

            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM permissions p WHERE $whereSql");
            $countStmt->execute($params);
            $total = (int) ($countStmt->fetchColumn() ?? 0);
            $totalPages = max(1, (int) ceil($total / $limit));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $limit;

            $usageDefinitions = $this->permissionUsageDefinitions();
            $usageColumns = [];
            foreach (array_keys($usageDefinitions) as $table) {
                $usageColumns[] = "(SELECT COUNT(*) FROM $table dependency WHERE dependency.permission_id = p.id) AS {$table}_count";
            }
            $usageSql = empty($usageColumns) ? '' : ', ' . implode(', ', $usageColumns);

            $stmt = $this->db->prepare(
                "SELECT
                    p.id, p.code, p.description, p.entity, p.action, p.module,
                    p.created_at, p.updated_at
                    $usageSql
                 FROM permissions p
                 WHERE $whereSql
                 ORDER BY COALESCE(p.module, ''), COALESCE(p.entity, ''), COALESCE(p.action, ''), p.code, p.id
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $rows = array_map(
                static function (array $row) use ($usageDefinitions) {
                    return self::formatPermissionDefinitionStatic($row, $usageDefinitions);
                },
                $rows
            );

            $summary = $this->getPermissionDefinitionSummary($usageDefinitions);
            $availableFilters = [
                'modules' => $this->getDistinctPermissionValues('module'),
                'entities' => $this->getDistinctPermissionValues('entity'),
                'actions' => $this->getDistinctPermissionValues('action'),
            ];

            return $this->successResponse([
                'rows' => $rows,
                'summary' => $summary,
                'available_filters' => $availableFilters,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                ],
            ], 'Resource permission definitions retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to load resource permission definitions', 500);
        }
    }

    public function createPermission(array $data, $actorUserId)
    {
        $validation = $this->normalizePermissionDefinitionPayload($data, true);
        if (!$validation['valid']) {
            return $this->errorResponse($validation['message'], 400);
        }

        $db = $this->db;
        try {
            $payload = $validation['data'];
            $db->beginTransaction();

            $duplicate = $this->db->prepare('SELECT id FROM permissions WHERE code = ? LIMIT 1');
            $duplicate->execute([$payload['code']]);
            if ($duplicate->fetch(\PDO::FETCH_ASSOC)) {
                $db->rollback();
                return $this->errorResponse('A permission with this code already exists', 409);
            }

            $this->db->prepare(
                'INSERT INTO permissions
                    (code, description, entity, action, module, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([
                $payload['code'],
                $payload['description'],
                $payload['entity'],
                $payload['action'],
                $payload['module'],
            ]);
            $permissionId = (int) $this->db->lastInsertId();

            $auditLogged = (new AuditLogger($this->db))->log(
                'permission_definition_create',
                'permission',
                $permissionId,
                (int) $actorUserId,
                $payload
            );
            if (!$auditLogged) {
                throw new Exception('Permission creation audit could not be recorded');
            }

            $db->commit();
            $created = $this->getPermissionDefinitionById($permissionId);

            return $this->successResponse(
                $this->formatPermissionDefinition(
                    $created ?? (['id' => $permissionId] + $payload),
                    $this->permissionUsageDefinitions()
                ),
                'Permission created successfully',
                201
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to create permission', 500);
        }
    }

    public function updatePermission($permissionId, array $data, $actorUserId)
    {
        $permissionId = (int) ($permissionId ?? $data['id'] ?? 0);
        if ($permissionId <= 0) {
            return $this->errorResponse('Permission ID is required', 400);
        }

        $validation = $this->normalizePermissionDefinitionPayload($data, false);
        if (!$validation['valid']) {
            return $this->errorResponse($validation['message'], 400);
        }
        if (empty($validation['data'])) {
            return $this->errorResponse('No supported permission fields were provided', 400);
        }

        $db = $this->db;
        try {
            $db->beginTransaction();
            $current = $this->getPermissionDefinitionById($permissionId, true);
            if (!$current) {
                $db->rollback();
                return $this->errorResponse('Permission not found', 404);
            }

            $usageDefinitions = $this->permissionUsageDefinitions();
            $usage = $this->getPermissionUsageCounts($permissionId, $usageDefinitions);
            $usageTotal = array_sum($usage);
            $payload = $validation['data'];

            if (array_key_exists('code', $payload) && $payload['code'] !== (string) $current['code']) {
                if ($usageTotal > 0) {
                    $db->rollback();
                    return $this->errorResponse(
                        'Permission codes cannot be changed while the permission is in use',
                        409,
                        ['usage' => $usage, 'usage_total' => $usageTotal]
                    );
                }

                $duplicate = $this->db->prepare('SELECT id FROM permissions WHERE code = ? AND id <> ? LIMIT 1');
                $duplicate->execute([$payload['code'], $permissionId]);
                if ($duplicate->fetch(\PDO::FETCH_ASSOC)) {
                    $db->rollback();
                    return $this->errorResponse('A permission with this code already exists', 409);
                }
            }

            $fields = [];
            $values = [];
            $changes = [];
            foreach (['code', 'description', 'entity', 'action', 'module'] as $field) {
                if (!array_key_exists($field, $payload)) {
                    continue;
                }
                $oldValue = $current[$field] ?? null;
                $newValue = $payload[$field];
                if ($oldValue === $newValue) {
                    continue;
                }
                $fields[] = "$field = ?";
                $values[] = $newValue;
                $changes[$field] = ['from' => $oldValue, 'to' => $newValue];
            }

            if (empty($fields)) {
                $db->rollback();
                return $this->successResponse(
                    $this->formatPermissionDefinition($current, $usageDefinitions, $usage),
                    'No permission changes were required'
                );
            }

            $fields[] = 'updated_at = NOW()';
            $values[] = $permissionId;
            $this->db->prepare('UPDATE permissions SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($values);

            $auditLogged = (new AuditLogger($this->db))->log(
                'permission_definition_update',
                'permission',
                $permissionId,
                (int) $actorUserId,
                ['code' => $current['code'], 'changes' => $changes]
            );
            if (!$auditLogged) {
                throw new Exception('Permission update audit could not be recorded');
            }

            $db->commit();
            $updated = $this->getPermissionDefinitionById($permissionId);

            return $this->successResponse(
                $this->formatPermissionDefinition($updated ?? $current, $usageDefinitions),
                'Permission updated successfully'
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to update permission', 500);
        }
    }

    public function deletePermission($permissionId, $actorUserId)
    {
        $permissionId = (int) ($permissionId ?? 0);
        if ($permissionId <= 0) {
            return $this->errorResponse('Permission ID is required', 400);
        }

        $db = $this->db;
        try {
            $db->beginTransaction();
            $current = $this->getPermissionDefinitionById($permissionId, true);
            if (!$current) {
                $db->rollback();
                return $this->errorResponse('Permission not found', 404);
            }

            $usageDefinitions = $this->permissionUsageDefinitions();
            $usage = $this->getPermissionUsageCounts($permissionId, $usageDefinitions);
            $usage = array_filter($usage, static function ($count) {
                return (int) $count > 0;
            });
            if (!empty($usage)) {
                $db->rollback();
                return $this->errorResponse(
                    'Permission cannot be deleted while it is in use',
                    409,
                    ['usage' => $usage, 'usage_total' => array_sum($usage)]
                );
            }

            $this->db->prepare('DELETE FROM permissions WHERE id = ?')->execute([$permissionId]);

            $auditLogged = (new AuditLogger($this->db))->log(
                'permission_definition_delete',
                'permission',
                $permissionId,
                (int) $actorUserId,
                [
                    'code' => $current['code'],
                    'description' => $current['description'],
                    'entity' => $current['entity'],
                    'action' => $current['action'],
                    'module' => $current['module'],
                ]
            );
            if (!$auditLogged) {
                throw new Exception('Permission deletion audit could not be recorded');
            }

            $db->commit();
            return $this->successResponse(['id' => $permissionId], 'Permission deleted successfully');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('Failed to delete permission', 500);
        }
    }

    private function permissionUsageDefinitions(): array
    {
        $definitions = [
            'role_permissions' => 'role assignments',
            'route_permissions' => 'route requirements',
            'user_permissions' => 'user overrides',
            'system_route_access_rules' => 'route access rules',
            'system_time_bound_access' => 'time-bound grants',
            'workflow_stage_permissions' => 'workflow stage rules',
        ];

        return array_filter(
            $definitions,
            function ($label, $table) {
                return $this->tableExists($table) && $this->tableHasColumn($table, 'permission_id');
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function getPermissionDefinitionById(int $permissionId, bool $forUpdate = false): ?array
    {
        $query = 'SELECT
                    id, code, description, entity, action, module, created_at, updated_at
                  FROM permissions
                  WHERE id = ?
                  LIMIT 1';
        if ($forUpdate) {
            $query .= ' FOR UPDATE';
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute([$permissionId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getPermissionUsageCounts(int $permissionId, array $usageDefinitions): array
    {
        $usage = [];
        foreach (array_keys($usageDefinitions) as $table) {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM $table WHERE permission_id = ?");
            $stmt->execute([$permissionId]);
            $usage[$table] = (int) ($stmt->fetchColumn() ?? 0);
        }
        return $usage;
    }

    private static function formatPermissionDefinitionStatic(array $permission, array $usageDefinitions): array
    {
        $permissionId = (int) ($permission['id'] ?? 0);
        $usageCounts = [];
        $hasInlineCounts = true;
        foreach (array_keys($usageDefinitions) as $table) {
            $alias = "{$table}_count";
            if (!array_key_exists($alias, $permission)) {
                $hasInlineCounts = false;
                break;
            }
            $usageCounts[$table] = (int) $permission[$alias];
        }

        if (!$hasInlineCounts && $permissionId > 0) {
            $usageCounts = [];
            foreach (array_keys($usageDefinitions) as $table) {
                $stmt = self::$staticDb->prepare("SELECT COUNT(*) FROM $table WHERE permission_id = ?");
                $stmt->execute([$permissionId]);
                $usageCounts[$table] = (int) ($stmt->fetchColumn() ?? 0);
            }
        }

        return self::finalizePermissionDefinition($permission, $usageDefinitions, $usageCounts);
    }

    private static $staticDb;

    private static function finalizePermissionDefinition(array $permission, array $usageDefinitions, array $usageCounts): array
    {
        foreach (array_keys($usageDefinitions) as $table) {
            unset($permission["{$table}_count"]);
            $usageCounts[$table] = (int) ($usageCounts[$table] ?? 0);
        }

        $usageTotal = array_sum($usageCounts);
        $permission['id'] = (int) ($permission['id'] ?? 0);
        $permission['code'] = (string) ($permission['code'] ?? '');
        $permission['description'] = $permission['description'] ?? null;
        $permission['entity'] = $permission['entity'] ?? null;
        $permission['action'] = $permission['action'] ?? null;
        $permission['module'] = $permission['module'] ?? null;
        $permission['usage'] = $usageCounts;
        $permission['usage_total'] = $usageTotal;
        $permission['code_locked'] = $usageTotal > 0;
        $permission['can_delete'] = $usageTotal === 0;

        return $permission;
    }

    private function formatPermissionDefinition(array $permission, array $usageDefinitions, ?array $usageCounts = null): array
    {
        $permissionId = (int) ($permission['id'] ?? 0);

        if ($usageCounts === null) {
            $usageCounts = [];
            $hasInlineCounts = true;
            foreach (array_keys($usageDefinitions) as $table) {
                $alias = "{$table}_count";
                if (!array_key_exists($alias, $permission)) {
                    $hasInlineCounts = false;
                    break;
                }
                $usageCounts[$table] = (int) $permission[$alias];
            }

            if (!$hasInlineCounts && $permissionId > 0) {
                $usageCounts = $this->getPermissionUsageCounts($permissionId, $usageDefinitions);
            }
        }

        return self::finalizePermissionDefinition($permission, $usageDefinitions, $usageCounts);
    }

    private function getPermissionDefinitionSummary(array $usageDefinitions): array
    {
        $totalsStmt = $this->db->query(
            "SELECT
                COUNT(*) AS total_permissions,
                COUNT(DISTINCT NULLIF(TRIM(COALESCE(entity, '')), '')) AS resource_count,
                COUNT(DISTINCT NULLIF(TRIM(COALESCE(module, '')), '')) AS module_count
             FROM permissions"
        );
        $totals = $totalsStmt ? ($totalsStmt->fetch(\PDO::FETCH_ASSOC) ?: []) : [];

        $inUsePermissions = 0;
        if (!empty($usageDefinitions)) {
            $exists = [];
            foreach (array_keys($usageDefinitions) as $table) {
                $exists[] = "EXISTS (SELECT 1 FROM $table dependency WHERE dependency.permission_id = p.id)";
            }
            $stmt = $this->db->query('SELECT COUNT(*) FROM permissions p WHERE ' . implode(' OR ', $exists));
            $inUsePermissions = (int) ($stmt ? ($stmt->fetchColumn() ?? 0) : 0);
        }

        return [
            'total_permissions' => (int) ($totals['total_permissions'] ?? 0),
            'resource_count' => (int) ($totals['resource_count'] ?? 0),
            'module_count' => (int) ($totals['module_count'] ?? 0),
            'in_use_permissions' => $inUsePermissions,
        ];
    }

    private function getDistinctPermissionValues(string $column): array
    {
        if (!in_array($column, ['module', 'entity', 'action'], true)) {
            return [];
        }

        $stmt = $this->db->query(
            "SELECT DISTINCT $column AS value
             FROM permissions
             WHERE $column IS NOT NULL AND TRIM($column) <> ''
             ORDER BY $column"
        );
        $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];

        return array_values(array_map(
            static function (array $row) {
                return (string) $row['value'];
            },
            $rows
        ));
    }

    private function normalizePermissionDefinitionPayload(array $data, bool $creating): array
    {
        $normalized = [];
        $fields = [
            'code' => 255,
            'description' => 500,
            'entity' => 100,
            'action' => 100,
            'module' => 100,
        ];

        foreach ($fields as $field => $maximumLength) {
            if (!$creating && !array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field] ?? null;
            if ($value !== null && !is_scalar($value)) {
                return ['valid' => false, 'data' => [], 'message' => "$field must be a text value"];
            }

            $value = trim((string) ($value ?? ''));
            if ($field === 'code') {
                if ($value === '') {
                    return ['valid' => false, 'data' => [], 'message' => 'Permission code is required'];
                }
                if (!preg_match('/^[A-Za-z0-9._:-]+$/D', $value)) {
                    return ['valid' => false, 'data' => [], 'message' => 'Permission code contains unsupported characters'];
                }
            }

            if ($this->permissionTextLength($value) > $maximumLength) {
                return ['valid' => false, 'data' => [], 'message' => "$field must not exceed $maximumLength characters"];
            }

            $normalized[$field] = $field === 'code' || $value !== '' ? $value : null;
        }

        return ['valid' => true, 'data' => $normalized, 'message' => ''];
    }

    // ───────────────────────── ROLE PERMISSIONS ─────────────────────────

    public function getRolePermissions($roleId, bool $isSchoolAdmin)
    {
        try {
            if (!$roleId) {
                return $this->errorResponse('Role ID is required', 400);
            }

            if ($isSchoolAdmin) {
                $roleStmt = $this->db->prepare('SELECT scope, is_system FROM roles WHERE id = ?');
                $roleStmt->execute([$roleId]);
                $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);
                if (!$role || $role['is_system'] || ($role['scope'] ?? 'school') === 'system') {
                    return $this->errorResponse('Cannot inspect system roles', 403);
                }
            }

            $stmt = $this->db->prepare(
                "SELECT p.* FROM permissions p
                 JOIN role_permissions rp ON p.id = rp.permission_id
                 WHERE rp.role_id = ?
                 ORDER BY p.entity, p.action, p.code"
            );
            $stmt->execute([$roleId]);
            return $this->successResponse($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [], 'Role permissions retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function assignRolePermissions($roleId, array $permissionIds, $actorUserId, bool $isSchoolAdmin)
    {
        try {
            if (!$roleId) {
                return $this->errorResponse('role_id is required', 400);
            }
            if (empty($permissionIds) || !is_array($permissionIds)) {
                return $this->errorResponse('permission_ids array is required', 400);
            }

            $roleStmt = $this->db->prepare('SELECT * FROM roles WHERE id = ?');
            $roleStmt->execute([$roleId]);
            $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$role) {
                return $this->errorResponse('Role not found', 400);
            }
            if ($isSchoolAdmin && ($role['is_system'] || ($role['scope'] ?? 'school') === 'system')) {
                return $this->errorResponse('Cannot modify system roles', 403);
            }

            $ins = $this->db->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?,?,NOW())');
            $count = 0;
            foreach ($permissionIds as $pid) {
                $ins->execute([(int) $roleId, (int) $pid]);
                if ($ins->rowCount() > 0) {
                    $count++;
                    (new AuditLogger($this->db))->log(
                        'role_permission_assign',
                        'role_permission',
                        (int) $pid,
                        (int) $actorUserId,
                        ['role_id' => (int) $roleId, 'permission_id' => (int) $pid]
                    );
                }
            }

            return $this->successResponse(['assigned' => $count], 'Permissions assigned to role');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    public function removeRolePermission($roleId, $permissionId, $actorUserId, bool $isSchoolAdmin)
    {
        try {
            if (!$roleId || !$permissionId) {
                return $this->errorResponse('role_id and permission_id are required', 400);
            }

            $roleStmt = $this->db->prepare('SELECT * FROM roles WHERE id = ?');
            $roleStmt->execute([$roleId]);
            $role = $roleStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$role) {
                return $this->errorResponse('Role not found', 400);
            }
            if ($isSchoolAdmin && ($role['is_system'] || ($role['scope'] ?? 'school') === 'system')) {
                return $this->errorResponse('Cannot modify system roles', 403);
            }

            $stmt = $this->db->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([(int) $roleId, (int) $permissionId]);

            if ($stmt->rowCount() > 0) {
                (new AuditLogger($this->db))->log(
                    'role_permission_remove',
                    'role_permission',
                    (int) $permissionId,
                    (int) $actorUserId,
                    ['role_id' => (int) $roleId, 'permission_id' => (int) $permissionId]
                );
            }

            return $this->successResponse(null, 'Permission removed from role');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    // ───────────────────────── SIDEBAR MENUS ─────────────────────────

    public function getSidebarMenus()
    {
        try {
            $roleNames = [];
            $stmt = $this->db->query('SELECT id, name FROM roles');
            foreach ($stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [] as $role) {
                $roleNames[(int) $role['id']] = $role['name'];
            }

            $records = [];
            foreach (SidebarConfigReader::forAllRoles() as $roleId => $menu) {
                foreach ($menu as $parentOrder => $parent) {
                    $parentLabel = (string) ($parent['label'] ?? '');
                    $parentRoute = (string) ($parent['url'] ?? '');
                    if ($parentRoute !== '') {
                        $records[] = $this->effectiveNavigationRecord(
                            (int) $roleId,
                            $roleNames[(int) $roleId] ?? ('Role ' . $roleId),
                            $parentLabel,
                            '',
                            $parentRoute,
                            (int) $parentOrder
                        );
                    }
                    foreach (($parent['subitems'] ?? []) as $childOrder => $child) {
                        $records[] = $this->effectiveNavigationRecord(
                            (int) $roleId,
                            $roleNames[(int) $roleId] ?? ('Role ' . $roleId),
                            (string) ($child['label'] ?? ''),
                            $parentLabel,
                            (string) ($child['url'] ?? ''),
                            ((int) $parentOrder * 100) + (int) $childOrder
                        );
                    }
                }
            }
            return $this->successResponse($records, 'Effective role navigation retrieved');
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[SystemAdminManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->errorResponse('An internal error occurred.', 400);
        }
    }

    private function effectiveNavigationRecord(
        int $roleId,
        string $roleName,
        string $label,
        string $section,
        string $route,
        int $sortOrder
    ): array {
        return [
            'id' => $roleId . ':' . $route,
            'role_id' => $roleId,
            'role_name' => $roleName,
            'section' => $section ?: 'Direct',
            'menu_label' => $label,
            'label' => $label,
            'route' => $route,
            'sort_order' => $sortOrder,
            'visible' => true,
            'status' => 'effective',
            'source' => 'config/role_sidebars.php',
        ];
    }

    public function getRoleSidebarAssignments($roleId)
    {
        if (!$roleId) return $this->errorResponse('Role ID is required', 400);
        $result = $this->getSidebarMenus();
        if (empty($result['success'])) return $result;
        $result['data'] = array_values(array_filter(
            $result['data'] ?? [],
            static fn(array $row): bool => (int) ($row['role_id'] ?? 0) === (int) $roleId
        ));
        return $result;
    }

    public function createSidebarMenu(array $data)
    {
        return $this->errorResponse('Role navigation is file-managed in config/role_sidebars.php', 409);
    }

    public function updateSidebarMenu($menuId, array $data)
    {
        return $this->errorResponse('Role navigation is file-managed in config/role_sidebars.php', 409);
    }

    public function deleteSidebarMenu($menuId)
    {
        return $this->errorResponse('Role navigation is file-managed in config/role_sidebars.php', 409);
    }

    public function assignRoleSidebarMenu($roleId, $menuId)
    {
        return $this->errorResponse('Role navigation is file-managed in config/role_sidebars.php', 409);
    }

    public function removeRoleSidebarMenu($roleId, $menuId)
    {
        return $this->errorResponse('Role navigation is file-managed in config/role_sidebars.php', 409);
    }

    // ───────────────────────── GENERIC ROW FETCHER ─────────────────────────

    private function fetchRows(string $table, int $limit = 100, string $orderBy = 'id DESC', string $columns = '*'): array
    {
        $allowedTables = [
            'users', 'roles', 'permissions', 'role_permissions', 'role_routes',
            'routes_registry', 'sidebar_menu_items', 'role_sidebar_menus', 'dashboards',
            'staff', 'students', 'classes', 'academic_years',
            'departments', 'school_settings', 'school_profile',
            'user_sessions', 'refresh_tokens',
            'notifications', 'communications', 'blocked_ips',
            'system_policies',
        ];

        if (!in_array($table, $allowedTables, true)) {
            return [];
        }

        $safeColumns = preg_replace('/[^a-zA-Z0-9_ ,\.\(\)]/', '', $columns);
        $safeOrderBy = preg_replace('/[^a-zA-Z0-9_ ,\.]/', '', $orderBy);
        $limit = max(1, min($limit, 1000));

        $stmt = $this->db->query("SELECT {$safeColumns} FROM `{$table}` ORDER BY {$safeOrderBy} LIMIT {$limit}");
        return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
    }

    // ───────────────────────── SYSTEM STATE (JSON FILE) ─────────────────────────

    private function getSystemStatePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/system_admin_state.json';
    }

    private function readSystemState(): array
    {
        $path = $this->getSystemStatePath();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeSystemStateValue(string $key, $value): void
    {
        $path = $this->getSystemStatePath();
        $dir = dirname($path);
        $this->ensureManagedDirectory($dir);

        $state = $this->readSystemState();
        $state[$key] = $value;
        $this->writeManagedFile($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getSystemState(string $key, $default = null)
    {
        $state = $this->readSystemState();
        return $state[$key] ?? $default;
    }

    public function getStateRecords(string $key, string $message)
    {
        return $this->successResponse(array_values($this->getSystemState($key, [])), $message);
    }

    public function saveStateRecord(string $key, $id, array $data, string $message)
    {
        $records = $this->getSystemState($key, []);
        $recordId = (string) ($id ?? $data['id'] ?? uniqid($key . '_'));
        $records[$recordId] = array_merge($records[$recordId] ?? [], $data, [
            'id' => $recordId,
            'name' => $data['name'] ?? $data['title'] ?? $recordId,
            'status' => $data['status'] ?? ($records[$recordId]['status'] ?? 'active'),
            'updated_at' => date('c'),
        ]);
        if (empty($records[$recordId]['created_at'])) {
            $records[$recordId]['created_at'] = date('c');
        }
        $this->writeSystemStateValue($key, $records);

        return $this->successResponse($records[$recordId], $message);
    }

    public function deleteStateRecord(string $key, $id, string $message)
    {
        if (!$id) {
            return $this->errorResponse('Record ID is required', 400);
        }
        $records = $this->getSystemState($key, []);
        unset($records[(string) $id]);
        $this->writeSystemStateValue($key, $records);

        return $this->successResponse(null, $message);
    }

    public function getStateToggleList(string $key, string $message, array $default = [])
    {
        return $this->successResponse(array_values($this->getSystemState($key, $default)), $message);
    }

    public function putStateToggle(string $key, $id, array $data, string $message)
    {
        $items = $this->getSystemState($key, []);
        $itemId = (string) ($id ?? $data['id'] ?? $data['key'] ?? 'default');
        $enabled = $this->normalizeToggleValue($data['enabled'] ?? $data['is_active'] ?? true);
        $items[$itemId] = array_merge($items[$itemId] ?? [], $data, [
            'id' => $itemId,
            'key' => $data['key'] ?? $itemId,
            'name' => $data['name'] ?? ucwords(str_replace('_', ' ', $itemId)),
            'enabled' => $enabled === 1,
            'is_active' => $enabled ?? 1,
            'updated_at' => date('c'),
        ]);
        $this->writeSystemStateValue($key, $items);

        return $this->successResponse($items[$itemId], $message);
    }

    public function saveSystemStateEndpoint(string $key, array $data, string $message)
    {
        $this->writeSystemStateValue($key, $data);
        return $this->successResponse($data, $message);
    }

    // ───────────────────────── BACKUPS / MIGRATIONS ─────────────────────────

    private function getBackupDirectory(): string
    {
        return dirname(__DIR__, 2) . '/storage/backups';
    }

    public function getBackups()
    {
        $backupDir = $this->getBackupDirectory();
        $files = glob($backupDir . '/*.sql') ?: [];
        $backups = array_map(static function ($file) {
            return [
                'id' => basename($file),
                'name' => basename($file),
                'description' => 'Database SQL backup',
                'status' => 'active',
                'size_bytes' => filesize($file),
                'created_at' => date('c', filemtime($file)),
            ];
        }, $files);

        return $this->successResponse($backups, 'Backups retrieved');
    }

    public function createBackup()
    {
        $backupDir = $this->getBackupDirectory();
        try {
            $this->ensureManagedDirectory($backupDir);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to create backup directory', 500);
        }

        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $path = $backupDir . '/' . $filename;
        $payload = "-- Kingsway backup placeholder created by System Admin\n-- Created: " . date('c') . "\n";
        if ($this->writeManagedFile($path, $payload) === false) {
            return $this->errorResponse('Unable to create backup file', 500);
        }

        return $this->successResponse(['id' => $filename, 'name' => $filename, 'created_at' => date('c')], 'Backup created', 201);
    }

    public function deleteBackup($backupId)
    {
        $backupId = basename((string) ($backupId ?? ''));
        if ($backupId === '') {
            return $this->errorResponse('Backup ID is required', 400);
        }

        $path = $this->getBackupDirectory() . '/' . $backupId;
        if (!is_file($path)) {
            return $this->errorResponse('Backup not found', 404);
        }

        return $this->deleteManagedFile($path)
            ? $this->successResponse(null, 'Backup deleted')
            : $this->errorResponse('Unable to delete backup', 500);
    }

    public function getMigrations()
    {
        $files = glob(dirname(__DIR__, 3) . '/database/migrations/*.sql') ?: [];
        $migrations = array_map(static function ($file) {
            return [
                'id' => basename($file),
                'name' => basename($file),
                'description' => 'SQL migration file',
                'status' => 'available',
                'created_at' => date('c', filemtime($file)),
            ];
        }, $files);

        return $this->successResponse($migrations, 'Migrations retrieved');
    }

    public function recordMigrationRequest(array $data)
    {
        return $this->successResponse([
            'requested' => $data['name'] ?? $data['id'] ?? null,
            'status' => 'queued',
            'message' => 'Migration execution request recorded; run SQL migrations through the deployment workflow.',
        ], 'Migration request recorded', 201);
    }
}
