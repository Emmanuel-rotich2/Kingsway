<?php
namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use App\Database\Database;
use Exception;

class SystemController extends BaseController
{
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->api = new SystemAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'System API is running']);
    }

    // POST /api/system/media/upload
    public function postMediaUpload($id = null, $data = [], $segments = [])
    {
        $file = $_FILES['file'] ?? null;
        $context = $data['context'] ?? 'public';
        $entityId = $data['entity_id'] ?? null;
        $albumId = $data['album_id'] ?? null;
        $uploaderId = $data['uploader_id'] ?? ($_REQUEST['user']['id'] ?? null);
        $description = $data['description'] ?? '';
        $tags = $data['tags'] ?? '';
        $result = $this->api->uploadMedia($file, $context, $entityId, $albumId, $uploaderId, $description, $tags);
        return $this->handleResponse($result);
    }

    // POST /api/system/media/album
    public function postMediaAlbum($id = null, $data = [], $segments = [])
    {
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $coverImage = $data['cover_image'] ?? null;
        $createdBy = $data['created_by'] ?? ($_REQUEST['user']['id'] ?? null);
        $result = $this->api->createAlbum($name, $description, $coverImage, $createdBy);
        return $this->handleResponse($result);
    }

    // GET /api/system/media/albums
    public function getMediaAlbums($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listAlbums($data);
        return $this->handleResponse($result);
    }

    // GET /api/system/media
    public function getMedia($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listMedia($data);
        return $this->handleResponse($result);
    }

    // POST /api/system/media/update
    public function postMediaUpdate($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id;
        $fields = $data['fields'] ?? [];
        $result = $this->api->updateMedia($mediaId, $fields);
        return $this->handleResponse($result);
    }

    // POST /api/system/media/delete
    public function postMediaDelete($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id;
        $result = $this->api->deleteMedia($mediaId);
        return $this->handleResponse($result);
    }

    // POST /api/system/media/album/delete
    public function postMediaAlbumDelete($id = null, $data = [], $segments = [])
    {
        $albumId = $data['album_id'] ?? $id;
        $result = $this->api->deleteAlbum($albumId);
        return $this->handleResponse($result);
    }

    // GET /api/system/media/preview
    public function getMediaPreview($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id;
        $result = $this->api->getMediaPreviewUrl($mediaId);
        return $this->handleResponse($result);
    }

    // GET /api/system/media/can-access
    public function getMediaCanAccess($id = null, $data = [], $segments = [])
    {
        $userId = $data['user_id'] ?? ($_REQUEST['user']['id'] ?? null);
        $mediaId = $data['media_id'] ?? $id;
        $action = $data['action'] ?? 'view';
        $result = $this->api->canAccessMedia($userId, $mediaId, $action);
        return $this->handleResponse($result);
    }


    // GET /api/system/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $result = $this->api->readLogs($data);
        return $this->handleResponse($result);
    }

    // POST /api/system/logs/clear
    public function postLogsClear($id = null, $data = [], $segments = [])
    {
        $result = $this->api->clearLogs();
        return $this->handleResponse($result);
    }

    // POST /api/system/logs/archive
    public function postLogsArchive($id = null, $data = [], $segments = [])
    {
        $result = $this->api->archiveLogs();
        return $this->handleResponse($result);
    }

    // GET /api/system/school-config
    public function getSchoolConfig($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $result = $this->api->getSchoolConfig($id);
        return $this->handleResponse($result);
    }

    // POST /api/system/school-config
    public function postSchoolConfig($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $result = $this->api->setSchoolConfig($data);
        return $this->handleResponse($result);
    }

    // GET /api/system/health
    public function getHealth($id = null, $data = [], $segments = [])
    {
        $result = $this->api->healthCheck();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/system/activity-audit-logs
     * Returns activity audit log entries with filtering and pagination
     */
    public function getActivityAuditLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $filters = array_merge($_GET, $data ?? []);
        $limit   = min((int)($filters['limit'] ?? 100), 500);
        $offset  = (int)($filters['offset'] ?? 0);
        $search  = $filters['search'] ?? '';
        $level   = $filters['severity'] ?? '';
        $from    = $filters['date_from'] ?? '';
        $to      = $filters['date_to'] ?? '';

        $where  = ['1=1'];
        $params = [];

        if ($search !== '') {
            $where[]  = '(message LIKE ? OR source LIKE ? OR user LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($level !== '') {
            $where[]  = 'level = ?';
            $params[] = $level;
        }
        if ($from !== '') {
            $where[]  = 'created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[]  = 'created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }

        $whereClause = implode(' AND ', $where);

        try {
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM activity_logs WHERE $whereClause");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $this->db->prepare(
                "SELECT id, level, message, source, user, ip_address, created_at
                 FROM activity_logs
                 WHERE $whereClause
                 ORDER BY created_at DESC
                 LIMIT ? OFFSET ?"
            );
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $errors   = count(array_filter($rows, fn($r) => ($r['level'] ?? '') === 'error'));
            $warnings = count(array_filter($rows, fn($r) => ($r['level'] ?? '') === 'warning'));
            $today    = count(array_filter($rows, fn($r) => str_starts_with($r['created_at'] ?? '', date('Y-m-d'))));

            return $this->success([
                'data'  => $rows,
                'stats' => ['total' => $total, 'errors' => $errors, 'warnings' => $warnings, 'today' => $today],
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => $total],
            ]);
        } catch (\Throwable $e) {
            // Table may not exist — return empty gracefully
            return $this->success([
                'data'  => [],
                'stats' => ['total' => 0, 'errors' => 0, 'warnings' => 0, 'today' => 0],
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => 0],
            ]);
        }
    }

    /**
     * GET /api/system/auth-events
     * Returns authentication events (logins/logouts) for audit trail
     */
    public function getAuthEvents($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            // Query audit log for auth events (last 24 hours)
            $query = "
                SELECT 
                    al.id,
                    al.user_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    al.action,
                    al.details,
                    al.ip_address,
                    al.status,
                    al.created_at
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.action IN ('login', 'logout', 'password_change')
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY al.created_at DESC
                LIMIT 50
            ";

            $result = $db->query($query, []);
            $events = $result->fetchAll() ?? [];

            // Count by event type
            $successCount = 0;
            $failureCount = 0;
            foreach ($events as $event) {
                if ($event['status'] === 'failure') {
                    $failureCount++;
                } else if ($event['status'] === 'success') {
                    $successCount++;
                }
            }

            return $this->success([
                'events' => $events,
                'summary' => [
                    'successful_logins' => $successCount,
                    'failed_logins' => $failureCount,
                    'total_events' => count($events),
                    'timeframe' => '24 hours'
                ]
            ], 'Auth events retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve auth events: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/active-sessions
     * Returns currently active user sessions
     */
    public function getActiveSessions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            // Query sessions for active users
            $query = "
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.role_id,
                    r.name as role_name,
                    u.last_login,
                    u.status
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.status = 'active'
                ORDER BY u.last_login DESC
                LIMIT 100
            ";

            $result = $db->query($query, []);
            $sessions = $result->fetchAll() ?? [];

            // Count by role
            $roleCount = [];
            foreach ($sessions as $session) {
                $role = $session['role_name'] ?? 'Unknown';
                $roleCount[$role] = ($roleCount[$role] ?? 0) + 1;
            }

            return $this->success([
                'sessions' => $sessions,
                'summary' => [
                    'total_active_users' => count($sessions),
                    'by_role' => $roleCount,
                    'last_updated' => date('Y-m-d H:i:s')
                ]
            ], 'Active sessions retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve active sessions: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/uptime
     * Returns system infrastructure uptime metrics
     * SECURITY: System Admin only
     */
    public function getSystemUptime($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();
            $databaseHealthy = false;
            try {
                $ping = $db->query("SELECT 1 AS ok", []);
                $databaseHealthy = (bool) ($ping && (int) ($ping->fetchColumn() ?? 0) === 1);
            } catch (Exception $e) {
                $databaseHealthy = false;
            }

            $failedAuthCount = (int) ($db->query(
                "SELECT COUNT(*) FROM failed_auth_attempts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                []
            )->fetchColumn() ?? 0);

            $failedAuditCount = (int) ($db->query(
                "SELECT COUNT(*) FROM audit_logs WHERE status = 'failure' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                []
            )->fetchColumn() ?? 0);

            $components = [
                [
                    'component' => 'Database Server',
                    'uptime_percent' => $databaseHealthy ? 100.0 : 0.0,
                    'status' => $databaseHealthy ? 'healthy' : 'down',
                    'checks' => 1,
                    'last_check' => date('Y-m-d H:i:s')
                ],
                [
                    'component' => 'Authentication Layer',
                    'uptime_percent' => max(0, 100 - min(100, $failedAuthCount)),
                    'status' => $failedAuthCount >= 25 ? 'degraded' : 'healthy',
                    'checks' => $failedAuthCount,
                    'last_check' => date('Y-m-d H:i:s')
                ],
                [
                    'component' => 'Audit Pipeline',
                    'uptime_percent' => max(0, 100 - min(100, $failedAuditCount * 2)),
                    'status' => $failedAuditCount > 0 ? 'degraded' : 'healthy',
                    'checks' => $failedAuditCount,
                    'last_check' => date('Y-m-d H:i:s')
                ]
            ];

            $totalUptime = array_sum(array_column($components, 'uptime_percent')) / max(1, count($components));

            return $this->success([
                'overall_uptime_percent' => round($totalUptime, 2),
                'components' => $components,
                'period' => '24 hours',
                'last_updated' => date('Y-m-d H:i:s')
            ], 'System uptime retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve uptime metrics: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/health-errors
     * Returns critical and high severity system errors
     * SECURITY: System Admin only
     */
    public function getSystemHealthErrors($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            // Query audit failures (last 24 hours)
            $query = "
                SELECT 
                    al.id,
                    'error' AS severity,
                    al.action AS error_type,
                    COALESCE(al.details, CONCAT('Action ', al.action, ' failed')) AS message,
                    al.entity AS file,
                    created_at
                FROM audit_logs al
                WHERE al.status = 'failure'
                  AND al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY al.created_at DESC
                LIMIT 25
            ";

            $result = $db->query($query, []);
            $errors = $result->fetchAll() ?? [];
            $criticalCount = count($errors);

            return $this->success([
                'errors' => $errors,
                'summary' => [
                    'critical_errors' => $criticalCount,
                    'total_errors' => count($errors),
                    'timeframe' => '24 hours'
                ]
            ], 'System errors retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve system errors: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/health-warnings
     * Returns medium and low severity system warnings
     * SECURITY: System Admin only
     */
    public function getSystemHealthWarnings($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();
            $query = "
                SELECT
                    MIN(faa.id) AS id,
                    'warning' AS severity,
                    'Authentication' AS type,
                    CONCAT('IP ', faa.ip_address, ' has ', COUNT(*), ' failed authentication attempts in the last 24h') AS message,
                    MAX(faa.created_at) AS created_at
                FROM failed_auth_attempts faa
                WHERE faa.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY faa.ip_address
                HAVING COUNT(*) >= 3
                ORDER BY COUNT(*) DESC, MAX(faa.created_at) DESC
                LIMIT 25
            ";

            $result = $db->query($query, []);
            $warnings = $result->fetchAll() ?? [];

            return $this->success([
                'warnings' => $warnings,
                'summary' => [
                    'total_warnings' => count($warnings),
                    'timeframe' => '24 hours'
                ]
            ], 'System warnings retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve system warnings: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/api-load
     * Returns API performance and request load metrics
     * SECURITY: System Admin only
     */
    public function getAPILoad($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $endpointQuery = "
                SELECT
                    CONCAT('/', al.entity) AS route,
                    UPPER(al.action) AS method,
                    COUNT(*) AS request_count
                FROM audit_logs al
                WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY al.entity, al.action
                ORDER BY request_count DESC
                LIMIT 20
            ";
            $endpointRows = $db->query($endpointQuery, [])->fetchAll() ?? [];
            $endpoints = array_map(static function ($row) {
                return [
                    'route' => $row['route'],
                    'method' => $row['method'],
                    'request_count' => (int) $row['request_count'],
                    'avg_response_time' => null,
                    'max_response_time' => null
                ];
            }, $endpointRows);

            $hourlyQuery = "
                SELECT
                    HOUR(created_at) AS hour,
                    COUNT(*) AS requests
                FROM audit_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(created_at)
                ORDER BY hour ASC
            ";
            $hourlyRows = $db->query($hourlyQuery, [])->fetchAll() ?? [];
            $hourlyData = array_map(static function ($row) {
                return [
                    'hour' => (int) $row['hour'],
                    'requests' => (int) $row['requests'],
                    'avg_response_time' => null
                ];
            }, $hourlyRows);

            $totalRequests = array_sum(array_column($endpoints, 'request_count'));
            $peakHour = null;
            $peakRequests = -1;
            foreach ($hourlyData as $hourData) {
                if ((int) $hourData['requests'] > $peakRequests) {
                    $peakRequests = (int) $hourData['requests'];
                    $peakHour = (int) $hourData['hour'];
                }
            }

            return $this->success([
                'endpoints' => $endpoints,
                'hourly' => $hourlyData,
                'summary' => [
                    'total_requests_24h' => $totalRequests,
                    'avg_response_time_ms' => null,
                    'peak_hour' => $peakHour,
                    'requests_per_second' => round($totalRequests / (24 * 3600), 6)
                ]
            ], 'API load metrics retrieved');
        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve API load metrics: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/pending-approvals
     * Returns workflow items pending director/admin approval
     * SECURITY: Director and School Admin only
     */
    public function getPendingApprovals($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureDirectorOrSchoolAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            // Get current user from auth middleware (stored in $this->user by BaseController)
            $userId = $this->getUserId();

            if (!$userId) {
                return $this->badRequest('Authentication required - please log in again');
            }

            // Pull pending approvals from real workflow-backed tables.
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
                    SELECT
                        CONCAT('promotion-', cpq.id) AS id,
                        'class_promotion' AS type,
                        CONCAT('Class promotion batch #', cpq.batch_id, ': ', c.name, ' / ', COALESCE(cs.stream_name, 'N/A')) AS description,
                        NULL AS amount,
                        cpq.approval_status AS status,
                        CASE cpq.approval_status
                            WHEN 'reviewing' THEN 'high'
                            WHEN 'pending' THEN 'medium'
                            ELSE 'low'
                        END AS priority,
                        pb.created_by AS created_by,
                        u.first_name,
                        u.last_name,
                        cpq.created_at AS submitted_at,
                        NULL AS due_by
                    FROM class_promotion_queue cpq
                    INNER JOIN promotion_batches pb ON pb.id = cpq.batch_id
                    INNER JOIN classes c ON c.id = cpq.class_id
                    LEFT JOIN class_streams cs ON cs.id = cpq.stream_id
                    LEFT JOIN users u ON u.id = pb.created_by
                    WHERE cpq.approval_status IN ('pending', 'reviewing')
                      AND (cpq.assigned_to_user_id = ? OR cpq.assigned_to_user_id IS NULL)

                    UNION ALL

                    SELECT
                        CONCAT('fee-structure-', fsd.id) AS id,
                        'fee_structure' AS type,
                        CONCAT('Fee structure review: ', COALESCE(sl.name, CONCAT('Level ', fsd.level_id)), ' / ', COALESCE(at.name, CONCAT('Term ', fsd.term_id)), ' ', fsd.academic_year) AS description,
                        fsd.amount AS amount,
                        fsd.status AS status,
                        CASE fsd.status
                            WHEN 'reviewed' THEN 'high'
                            WHEN 'pending_review' THEN 'medium'
                            ELSE 'low'
                        END AS priority,
                        fsd.created_by AS created_by,
                        u2.first_name,
                        u2.last_name,
                        fsd.created_at AS submitted_at,
                        fsd.due_date AS due_by
                    FROM fee_structures_detailed fsd
                    LEFT JOIN school_levels sl ON sl.id = fsd.level_id
                    LEFT JOIN academic_terms at ON at.id = fsd.term_id
                    LEFT JOIN users u2 ON u2.id = fsd.created_by
                    WHERE fsd.status IN ('pending_review', 'reviewed')

                    UNION ALL

                    SELECT
                        CONCAT('purchase-order-', po.id) AS id,
                        'purchase_order' AS type,
                        CONCAT('Purchase order ', po.order_number, ' awaiting approval') AS description,
                        po.total_amount AS amount,
                        po.status AS status,
                        CASE
                            WHEN po.total_amount >= 100000 THEN 'high'
                            ELSE 'medium'
                        END AS priority,
                        su.id AS created_by,
                        su.first_name,
                        su.last_name,
                        po.created_at AS submitted_at,
                        po.expected_delivery_date AS due_by
                    FROM purchase_orders po
                    LEFT JOIN staff s ON s.id = po.created_by
                    LEFT JOIN users su ON su.id = s.user_id
                    WHERE po.status = 'pending'

                    UNION ALL

                    SELECT
                        CONCAT('payroll-', sp.id) AS id,
                        'payroll' AS type,
                        CONCAT('Payroll ', sp.payroll_period, ' awaiting approval') AS description,
                        sp.net_salary AS amount,
                        sp.status AS status,
                        'high' AS priority,
                        NULL AS created_by,
                        NULL AS first_name,
                        NULL AS last_name,
                        sp.created_at AS submitted_at,
                        NULL AS due_by
                    FROM staff_payroll sp
                    WHERE sp.status IN ('pending', 'verification')

                    UNION ALL

                    SELECT
                        CONCAT('expense-', e.id) AS id,
                        'expense' AS type,
                        CONCAT('Expense: ', COALESCE(e.description, e.expense_category)) AS description,
                        e.amount AS amount,
                        e.status AS status,
                        CASE
                            WHEN e.amount >= 50000 THEN 'high'
                            ELSE 'medium'
                        END AS priority,
                        e.created_by AS created_by,
                        u4.first_name,
                        u4.last_name,
                        e.created_at AS submitted_at,
                        NULL AS due_by
                    FROM expenses e
                    LEFT JOIN users u4 ON u4.id = e.created_by
                    WHERE e.status = 'pending'
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

            $result = $db->query($query, [$userId]);
            $approvals = $result->fetchAll() ?? [];

            foreach ($approvals as &$approval) {
                $fullName = trim((string) (($approval['first_name'] ?? '') . ' ' . ($approval['last_name'] ?? '')));
                $approval['student_name'] = $fullName !== '' ? $fullName : (string) ($approval['description'] ?? '');
                $approval['submitted_by'] = $fullName !== '' ? $fullName : null;
            }
            unset($approval);

            $highPriorityCount = count(array_filter($approvals, static function ($item) {
                return ($item['priority'] ?? null) === 'high';
            }));
            $dueSoonCutoff = strtotime('+3 days');
            $dueSoonCount = count(array_filter($approvals, static function ($item) use ($dueSoonCutoff) {
                if (empty($item['due_by'])) {
                    return false;
                }

                $dueTs = strtotime((string) $item['due_by']);
                if ($dueTs === false) {
                    return false;
                }

                return $dueTs <= $dueSoonCutoff;
            }));

            return $this->success([
                'pending' => $approvals,
                'count' => count($approvals),
                'summary' => [
                    'total_pending' => count($approvals),
                    'high_priority' => $highPriorityCount,
                    'due_soon' => $dueSoonCount
                ]
            ], 'Pending approvals retrieved');

        } catch (Exception $e) {
            return $this->serverError('Failed to retrieve pending approvals: ' . $e->getMessage());
        }
    }

    /**
     * Unified API response handler (matches StudentsController)
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    return $this->badRequest($result['error'] ?? $result['message'] ?? 'Operation failed');
                }
            }
            return $this->success($result);
        }
        return $this->success($result);
    }

    private function ensureSystemAdminAccess()
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        if ($this->userHasRole('System Administrator') || $this->userHasPermission('*')) {
            return null;
        }

        return $this->forbidden('System Administrator access required');
    }

    /** Returns true if the current user is a School Administrator (school-scope, not system). */
    private function isSchoolAdmin(): bool
    {
        return $this->userHasRole('School Administrator') && !$this->userHasRole('System Administrator');
    }

    /** Returns true if the current user is a System Administrator. */
    private function isSystemAdmin(): bool
    {
        return $this->userHasRole('System Administrator') || $this->userHasPermission('*');
    }

    /**
     * Allows system admin full access; allows school admin read/scoped access.
     * Returns forbidden response for everyone else.
     */
    private function ensureRoleManagementAccess()
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if ($this->isSystemAdmin() || $this->isSchoolAdmin()) {
            return null;
        }
        return $this->forbidden('Access denied');
    }

    /**
     * Allow System Admin, Director, or any user with wildcard permission.
     * School owner (Director) has the same visibility as System Admin for
     * operational endpoints such as audit logs, sessions, and school config.
     */
    private function ensureSystemOrDirectorAccess()
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        if ($this->userHasPermission('*') || $this->userHasAny([], [], ['System Administrator', 'Director'])) {
            return null;
        }

        return $this->forbidden('System Administrator or Director access required');
    }

    private function ensureDirectorOrSchoolAdminAccess()
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }

        if ($this->userHasRole('System Administrator') || $this->userHasPermission('*')) {
            return null;
        }

        if ($this->userHasAny([], [], ['Director', 'School Administrator'])) {
            return null;
        }

        return $this->forbidden('Director or School Administrator access required');
    }

    // ========================================================================
    // ROUTES MANAGEMENT (System Admin Only)
    // ========================================================================

    /**
     * GET /api/system/routes - List all routes
     */
    public function getRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            if ($id) {
                // Get single route
                $query = "SELECT * FROM routes WHERE id = ?";
                $result = $db->query($query, [$id]);
                $route = $result->fetch();

                if (!$route) {
                    return $this->badRequest('Route not found');
                }

                return $this->success($route, 'Route retrieved');
            }

            // Get all routes
            $query = "SELECT * FROM routes ORDER BY domain, name";
            $result = $db->query($query, []);
            $routes = $result->fetchAll() ?? [];

            return $this->success($routes, 'Routes retrieved');

        } catch (Exception $e) {
            return $this->badRequest('Failed to load routes: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/routes - Create a new route
     */
    public function postRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            // Validate required fields
            if (empty($data['name'])) {
                return $this->badRequest('Route name is required');
            }

            // Check for duplicate name
            $check = $db->query("SELECT id FROM routes WHERE name = ?", [$data['name']]);
            if ($check->fetch()) {
                return $this->badRequest('A route with this name already exists');
            }

            $query = "INSERT INTO routes (name, url, domain, description, controller, action, is_active, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $db->query($query, [
                $data['name'],
                $data['url'] ?? null,
                $data['domain'] ?? 'SCHOOL',
                $data['description'] ?? null,
                $data['controller'] ?? null,
                $data['action'] ?? null,
                $data['is_active'] ?? 1
            ]);

            $newId = $db->lastInsertId();

            return $this->success(['id' => $newId], 'Route created successfully');

        } catch (Exception $e) {
            return $this->badRequest('Failed to create route: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/system/routes - Update a route
     */
    public function putRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $routeId = $id ?? $data['id'] ?? null;
            if (!$routeId) {
                return $this->badRequest('Route ID is required');
            }

            // Check route exists
            $check = $db->query("SELECT id FROM routes WHERE id = ?", [$routeId]);
            if (!$check->fetch()) {
                return $this->badRequest('Route not found');
            }

            $fields = [];
            $values = [];

            foreach (['name', 'url', 'domain', 'description', 'controller', 'action', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return $this->badRequest('No fields to update');
            }

            $fields[] = "updated_at = NOW()";
            $values[] = $routeId;

            $query = "UPDATE routes SET " . implode(', ', $fields) . " WHERE id = ?";
            $db->query($query, $values);

            return $this->success(null, 'Route updated successfully');

        } catch (Exception $e) {
            return $this->badRequest('Failed to update route: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/system/routes - Delete a route
     */
    public function deleteRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $routeId = $id ?? $data['id'] ?? null;
            if (!$routeId) {
                return $this->badRequest('Route ID is required');
            }

            $db->query("DELETE FROM routes WHERE id = ?", [$routeId]);

            return $this->success(null, 'Route deleted successfully');

        } catch (Exception $e) {
            return $this->badRequest('Failed to delete route: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/routes-toggle - Toggle route status
     */
    public function postRoutesToggle($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $routeId = $id ?? $data['id'] ?? null;
            $isActive = $data['is_active'] ?? null;

            if (!$routeId) {
                return $this->badRequest('Route ID is required');
            }

            $db->query("UPDATE routes SET is_active = ?, updated_at = NOW() WHERE id = ?", [$isActive, $routeId]);

            return $this->success(null, 'Route status updated');

        } catch (Exception $e) {
            return $this->badRequest('Failed to toggle status: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // ROLES MANAGEMENT (System Admin Only)
    // ========================================================================

    /**
     * GET /api/system/roles - List all roles
     */
    public function getRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();
            $schoolAdminOnly = $this->isSchoolAdmin();

            if ($id) {
                $query = $schoolAdminOnly
                    ? "SELECT * FROM roles WHERE id = ? AND (scope='school' OR scope IS NULL)"
                    : "SELECT * FROM roles WHERE id = ?";
                $role = $db->query($query, [$id])->fetch();
                if (!$role) {
                    return $this->badRequest('Role not found');
                }
                return $this->success($role, 'Role retrieved');
            }

            // System admin sees all; school admin sees only school-scope roles
            $query = $schoolAdminOnly
                ? "SELECT * FROM roles WHERE (scope='school' OR scope IS NULL) ORDER BY name"
                : "SELECT * FROM roles ORDER BY name";
            $roles = $db->query($query, [])->fetchAll() ?? [];

            return $this->success($roles, 'Roles retrieved');

        } catch (Exception $e) {
            return $this->badRequest('Failed to load roles: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/roles - Create a new role
     */
    public function postRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            if (empty($data['name'])) {
                return $this->badRequest('Role name is required');
            }

            $check = $db->query("SELECT id FROM roles WHERE name = ?", [$data['name']]);
            if ($check->fetch()) {
                return $this->badRequest('A role with this name already exists');
            }

            // School admin can only create school-scope, non-system roles
            $scope    = 'school';
            $isSystem = 0;
            if ($this->isSystemAdmin()) {
                $scope    = in_array($data['scope'] ?? '', ['system', 'school']) ? $data['scope'] : 'school';
                $isSystem = (int)(bool)($data['is_system'] ?? false);
            }

            $db->query(
                "INSERT INTO roles (name, description, scope, is_system, created_at) VALUES (?, ?, ?, ?, NOW())",
                [$data['name'], $data['description'] ?? null, $scope, $isSystem]
            );

            return $this->success(['id' => (int)$db->lastInsertId(), 'scope' => $scope], 'Role created successfully');

        } catch (Exception $e) {
            return $this->badRequest('Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/system/roles - Update a role
     */
    public function putRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId = $id ?? $data['id'] ?? null;
            if (!$roleId) {
                return $this->badRequest('Role ID is required');
            }

            $role = $db->query("SELECT * FROM roles WHERE id = ?", [$roleId])->fetch();
            if (!$role) {
                return $this->badRequest('Role not found');
            }

            // School admin cannot edit system-scoped or is_system=1 roles
            if ($this->isSchoolAdmin() && ($role['is_system'] || ($role['scope'] ?? 'school') === 'system')) {
                return $this->forbidden('Cannot modify system roles');
            }

            $allowedFields = ['name', 'description'];
            // Only system admin can change scope/is_system
            if ($this->isSystemAdmin()) {
                $allowedFields[] = 'scope';
                $allowedFields[] = 'is_system';
            }

            $fields = [];
            $values = [];
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $fields[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (empty($fields)) {
                return $this->badRequest('No fields to update');
            }

            $fields[]  = "updated_at = NOW()";
            $values[]  = $roleId;
            $db->query("UPDATE roles SET " . implode(', ', $fields) . " WHERE id = ?", $values);

            return $this->success(null, 'Role updated successfully');

        } catch (Exception $e) {
            return $this->badRequest('Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/system/roles - Delete a role
     */
    public function deleteRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId = $id ?? $data['id'] ?? null;
            if (!$roleId) {
                return $this->badRequest('Role ID is required');
            }

            $role = $db->query("SELECT * FROM roles WHERE id = ?", [$roleId])->fetch();
            if (!$role) {
                return $this->badRequest('Role not found');
            }

            // No one can delete is_system=1 roles
            if ($role['is_system']) {
                return $this->badRequest('Cannot delete system roles');
            }

            // School admin cannot delete system-scope roles
            if ($this->isSchoolAdmin() && ($role['scope'] ?? 'school') === 'system') {
                return $this->forbidden('Cannot delete system-scope roles');
            }

            $db->query("DELETE FROM roles WHERE id = ?", [$roleId]);

            return $this->success(null, 'Role deleted successfully');

        } catch (Exception $e) {
            return $this->badRequest('Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/roles-toggle - Toggle role status
     */
    public function postRolesToggle($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId = $id ?? $data['id'] ?? null;
            $isActive = $data['is_active'] ?? $data['enabled'] ?? null;

            if (!$roleId) {
                return $this->badRequest('Role ID is required');
            }

            if (!$this->tableHasColumn('roles', 'is_active')) {
                return $this->badRequest('Role status toggle is not supported by current schema');
            }

            $normalized = $this->normalizeToggleValue($isActive);
            if ($normalized === null) {
                return $this->badRequest('is_active/enabled must be true/false');
            }

            $db->query("UPDATE roles SET is_active = ?, updated_at = NOW() WHERE id = ?", [$normalized, $roleId]);

            return $this->success(null, 'Role status updated');

        } catch (Exception $e) {
            return $this->badRequest('Failed to toggle status: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // PERMISSIONS MANAGEMENT (System Admin Only)
    // ========================================================================

    /**
     * GET /api/system/permissions - List all permissions
     */
    public function getPermissions($id = null, $data = [], $segments = [])
    {
        // Both system admin and school admin can read permissions (school admin cannot create them)
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $query = "SELECT * FROM permissions ORDER BY entity, action, code";
            $permissions = $db->query($query, [])->fetchAll() ?? [];

            return $this->success($permissions, 'Permissions retrieved');

        } catch (Exception $e) {
            return $this->badRequest('Failed to load permissions: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/permissions - Create a new permission (System Admin only)
     */
    public function postPermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            if (empty($data['code'])) {
                return $this->badRequest('Permission code is required');
            }

            $check = $db->query("SELECT id FROM permissions WHERE code = ?", [$data['code']])->fetch();
            if ($check) {
                return $this->badRequest('A permission with this code already exists');
            }

            $db->query(
                "INSERT INTO permissions (code, name, description, entity, action, created_at) VALUES (?,?,?,?,?,NOW())",
                [
                    $data['code'],
                    $data['name'] ?? $data['code'],
                    $data['description'] ?? null,
                    $data['entity'] ?? null,
                    $data['action'] ?? null,
                ]
            );

            return $this->success(['id' => (int)$db->lastInsertId()], 'Permission created');

        } catch (Exception $e) {
            return $this->badRequest('Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/role-permissions - Get permissions for a role
     */
    public function getRolePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId = $id ?? $data['role_id'] ?? $_GET['role_id'] ?? null;
            if (!$roleId) {
                return $this->badRequest('Role ID is required');
            }

            // School admin can only inspect school-scope roles
            if ($this->isSchoolAdmin()) {
                $role = $db->query("SELECT scope, is_system FROM roles WHERE id = ?", [$roleId])->fetch();
                if (!$role || $role['is_system'] || ($role['scope'] ?? 'school') === 'system') {
                    return $this->forbidden('Cannot inspect system roles');
                }
            }

            $permissions = $db->query(
                "SELECT p.* FROM permissions p
                 JOIN role_permissions rp ON p.id = rp.permission_id
                 WHERE rp.role_id = ?
                 ORDER BY p.entity, p.action, p.code",
                [$roleId]
            )->fetchAll() ?? [];

            return $this->success($permissions, 'Role permissions retrieved');

        } catch (Exception $e) {
            return $this->badRequest('Failed to load role permissions: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/role-permissions - Assign existing permissions to a role
     * School admin can assign to school-scope roles only; cannot create new permissions.
     */
    public function postRolePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId        = $id ?? $data['role_id'] ?? null;
            $permissionIds = $data['permission_ids'] ?? [];

            if (!$roleId) {
                return $this->badRequest('role_id is required');
            }
            if (empty($permissionIds) || !is_array($permissionIds)) {
                return $this->badRequest('permission_ids array is required');
            }

            $role = $db->query("SELECT * FROM roles WHERE id = ?", [$roleId])->fetch();
            if (!$role) {
                return $this->badRequest('Role not found');
            }

            if ($this->isSchoolAdmin() && ($role['is_system'] || ($role['scope'] ?? 'school') === 'system')) {
                return $this->forbidden('Cannot modify system roles');
            }

            $ins = $db->getConnection()->prepare(
                "INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?,?,NOW())"
            );
            $count = 0;
            foreach ($permissionIds as $pid) {
                $ins->execute([(int)$roleId, (int)$pid]);
                $count++;
            }

            return $this->success(['assigned' => $count], 'Permissions assigned to role');

        } catch (Exception $e) {
            return $this->badRequest('Failed to assign permissions: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/system/role-permissions - Remove a permission from a role
     */
    public function deleteRolePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId       = $data['role_id'] ?? null;
            $permissionId = $id ?? $data['permission_id'] ?? null;

            if (!$roleId || !$permissionId) {
                return $this->badRequest('role_id and permission_id are required');
            }

            $role = $db->query("SELECT * FROM roles WHERE id = ?", [$roleId])->fetch();
            if (!$role) {
                return $this->badRequest('Role not found');
            }

            if ($this->isSchoolAdmin() && ($role['is_system'] || ($role['scope'] ?? 'school') === 'system')) {
                return $this->forbidden('Cannot modify system roles');
            }

            $db->query(
                "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                [(int)$roleId, (int)$permissionId]
            );

            return $this->success(null, 'Permission removed from role');

        } catch (Exception $e) {
            return $this->badRequest('Failed to remove permission: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // SIDEBAR MENU MANAGEMENT (System Admin Only)
    // ========================================================================

    /**
     * GET /api/system/sidebar-menus - List all sidebar menu items
     */
    public function getSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $query = "SELECT * FROM sidebar_menu_items ORDER BY parent_id, display_order, name";
            $result = $db->query($query, []);
            $menus = $result->fetchAll() ?? [];

            return $this->success($menus, 'Sidebar menus retrieved');

        } catch (Exception $e) {
            return $this->badRequest('Failed to load sidebar menus: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/role-sidebar-assignments - Get sidebar assignments for a role
     */
    public function getRoleSidebarAssignments($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId = $id ?? $data['role_id'] ?? $_GET['role_id'] ?? null;
            if (!$roleId) {
                return $this->badRequest('Role ID is required');
            }

            $query = "SELECT sm.* FROM sidebar_menu_items sm 
                      JOIN role_sidebar_menus rsm ON sm.id = rsm.menu_item_id 
                      WHERE rsm.role_id = ? 
                      ORDER BY sm.parent_id, sm.display_order";
            $result = $db->query($query, [$roleId]);
            $menus = $result->fetchAll() ?? [];

            return $this->success($menus, 'Role sidebar assignments retrieved');

        } catch (Exception $e) {
            return $this->badRequest('Failed to load assignments: ' . $e->getMessage());
        }
    }

    // ========================================================================
    // MODULE TOGGLES (System Admin pages using ToggleConfigController)
    // ========================================================================

    /**
     * GET /api/system/modules
     * Lists school modules backed by SCHOOL routes starting with manage_
     */
    public function getModules($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $query = "
                SELECT id, name, description, is_active
                FROM routes
                WHERE domain = 'SCHOOL'
                  AND name REGEXP '^manage_'
                ORDER BY name
            ";
            $result = $db->query($query, []);
            $routes = $result->fetchAll() ?? [];

            $modules = array_map([$this, 'mapRouteToToggleItem'], $routes);

            return $this->success($modules, 'Modules retrieved');
        } catch (Exception $e) {
            return $this->badRequest('Failed to load modules: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/system/modules/{id}
     * Toggles a school module route on/off.
     */
    public function putModules($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $routeId = $id ?? $data['id'] ?? null;
            if (!$routeId) {
                return $this->badRequest('Module ID is required');
            }

            $enabled = $this->normalizeToggleValue($data['enabled'] ?? $data['is_active'] ?? null);
            if ($enabled === null) {
                return $this->badRequest('enabled must be true/false');
            }

            $route = $this->getRouteById((int) $routeId);
            if (
                !$route ||
                strtoupper((string) ($route['domain'] ?? '')) !== 'SCHOOL' ||
                !str_starts_with((string) ($route['name'] ?? ''), 'manage_')
            ) {
                return $this->badRequest('Module not found');
            }

            $db->query(
                "UPDATE routes SET is_active = ?, updated_at = NOW() WHERE id = ?",
                [$enabled, (int) $routeId]
            );

            return $this->success(
                ['id' => (int) $routeId, 'enabled' => (bool) $enabled],
                'Module updated successfully'
            );
        } catch (Exception $e) {
            return $this->badRequest('Failed to update module: ' . $e->getMessage());
        }
    }

    /**
     * GET /api/system/module-enablement
     * Lists SYSTEM-level enablement toggles for module governance screens.
     */
    public function getModuleEnablement($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();
            $routeNames = $this->getModuleEnablementRouteNames();
            $placeholders = implode(', ', array_fill(0, count($routeNames), '?'));
            $params = array_merge(['SYSTEM'], $routeNames);

            $query = "
                SELECT id, name, description, is_active
                FROM routes
                WHERE domain = ?
                  AND name IN ($placeholders)
            ";
            $result = $db->query($query, $params);
            $routes = $result->fetchAll() ?? [];

            $orderMap = array_flip($routeNames);
            usort($routes, function ($a, $b) use ($orderMap) {
                $aOrder = $orderMap[$a['name']] ?? PHP_INT_MAX;
                $bOrder = $orderMap[$b['name']] ?? PHP_INT_MAX;
                return $aOrder <=> $bOrder;
            });

            $items = array_map([$this, 'mapRouteToToggleItem'], $routes);

            return $this->success($items, 'Module enablement settings retrieved');
        } catch (Exception $e) {
            return $this->badRequest('Failed to load module enablement settings: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/system/module-enablement/{id}
     * Toggles a SYSTEM-level module governance route on/off.
     */
    public function putModuleEnablement($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $routeId = $id ?? $data['id'] ?? null;
            if (!$routeId) {
                return $this->badRequest('Module enablement ID is required');
            }

            $enabled = $this->normalizeToggleValue($data['enabled'] ?? $data['is_active'] ?? null);
            if ($enabled === null) {
                return $this->badRequest('enabled must be true/false');
            }

            $route = $this->getRouteById((int) $routeId);
            $allowedRouteNames = $this->getModuleEnablementRouteNames();
            if (
                !$route ||
                strtoupper((string) ($route['domain'] ?? '')) !== 'SYSTEM' ||
                !in_array((string) ($route['name'] ?? ''), $allowedRouteNames, true)
            ) {
                return $this->badRequest('Module enablement setting not found');
            }

            $db->query(
                "UPDATE routes SET is_active = ?, updated_at = NOW() WHERE id = ?",
                [$enabled, (int) $routeId]
            );

            return $this->success(
                ['id' => (int) $routeId, 'enabled' => (bool) $enabled],
                'Module enablement setting updated successfully'
            );
        } catch (Exception $e) {
            return $this->badRequest('Failed to update module enablement setting: ' . $e->getMessage());
        }
    }

    /**
     * Normalize toggle input to 0/1.
     */
    private function normalizeToggleValue($value): ?int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value)) {
            if ((int) $value === 1) {
                return 1;
            }
            if ((int) $value === 0) {
                return 0;
            }
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return 1;
            }
            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return 0;
            }
        }

        return null;
    }

    /**
     * Fetch a route record by ID.
     */
    private function getRouteById(int $routeId): ?array
    {
        $db = Database::getInstance();
        $result = $db->query(
            "SELECT id, name, domain, description, is_active FROM routes WHERE id = ? LIMIT 1",
            [$routeId]
        );

        $row = $result->fetch();
        return $row ?: null;
    }

    /**
     * Check whether a table column exists in the active schema.
     */
    private function tableHasColumn(string $tableName, string $columnName): bool
    {
        $db = Database::getInstance();
        $result = $db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?",
            [$tableName, $columnName]
        );

        return (int) ($result->fetchColumn() ?? 0) > 0;
    }

    /**
     * Transform a route record to ToggleConfigController-friendly payload.
     */
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
            'is_active' => $isActive
        ];
    }

    /**
     * SYSTEM routes shown on Module Enablement screen.
     */
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
            'config_sync'
        ];
    }
}
