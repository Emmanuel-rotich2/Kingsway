<?php
namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use App\API\Includes\AuditLogger;
use App\API\Services\AuthSessionService;
use App\API\Services\IpAccessControlService;
use App\API\Services\SystemAdminAnalyticsService;
use App\Database\Database;
use Exception;

class SystemController extends BaseController
{
    private $api;
    private $authSessionService;
    private $ipAccessControlService;
    private $systemAdminAnalytics;

    public function __construct()
    {
        parent::__construct();
        $this->api = new SystemAPI();
        $this->authSessionService = new AuthSessionService(
            $this->db->getConnection()
        );
        $this->ipAccessControlService = new IpAccessControlService(
            $this->db->getConnection()
        );
        $this->systemAdminAnalytics = new SystemAdminAnalyticsService();
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
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            return $this->success(
                $this->systemAdminAnalytics->getAuthEvents(),
                'Auth events retrieved'
            );
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
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge(
                $_GET,
                is_array($data) ? $data : []
            );

            return $this->success(
                $this->systemAdminAnalytics->getActiveSessions(
                    $filters,
                    isset($_SERVER['auth_session_id'])
                        ? (int) $_SERVER['auth_session_id']
                        : null
                ),
                'Active sessions retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'Active session retrieval failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'Failed to retrieve active sessions'
            );
        }
    }

    /** POST /api/system/active-sessions-revoke */
    public function postActiveSessionsRevoke($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $sessionId = filter_var(
            $data['session_id'] ?? $id,
            FILTER_VALIDATE_INT
        );
        if ($sessionId === false || $sessionId <= 0) {
            return $this->badRequest(
                'A valid session ID is required'
            );
        }

        try {
            $result = $this->authSessionService
                ->revokeByAdministrator(
                    (int) $sessionId,
                    (int) $this->getUserId(),
                    isset($_SERVER['auth_session_id'])
                        ? (int) $_SERVER['auth_session_id']
                        : null
                );

            return $this->success(
                $result,
                'Session revoked'
            );
        } catch (\DomainException $e) {
            return $this->conflict($e->getMessage());
        } catch (\OutOfBoundsException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'Active session revocation failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'The active session could not be revoked'
            );
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
            return $this->success(
                $this->systemAdminAnalytics->getUptime(),
                'System runtime health retrieved'
            );
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
            return $this->success(
                $this->systemAdminAnalytics->getHealthErrors(),
                'System errors retrieved'
            );
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
            return $this->success(
                $this->systemAdminAnalytics->getHealthWarnings(),
                'System warnings retrieved'
            );
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
            return $this->success(
                $this->systemAdminAnalytics->getApiLoad(),
                'API load metrics retrieved'
            );
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
    private function ensureRoleManagementAccess(bool $manage = false)
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        if ($this->isSystemAdmin() || $this->isSchoolAdmin()) {
            return null;
        }

        $permissions = $manage
            ? [
                'system.rbac.manage',
                'system_roles_create',
                'system_roles_edit',
                'system_roles_delete',
            ]
            : [
                'system.rbac.view',
                'system.rbac.manage',
                'system_roles_view',
            ];

        if ($this->userHasAny($permissions)) {
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
    // SYSTEM ADMIN PAGE ENDPOINTS
    // ========================================================================

    public function getAuthenticationLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge(
                $_GET,
                is_array($data) ? $data : []
            );

            return $this->success(
                $this->systemAdminAnalytics->getAuthenticationLogs($filters),
                'Authentication logs retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'Authentication log retrieval failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'Failed to retrieve authentication logs'
            );
        }
    }

    public function getFailedLoginAttempts($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge(
                $_GET,
                is_array($data) ? $data : []
            );

            return $this->success(
                $this->systemAdminAnalytics->getFailedLoginAttempts($filters),
                'Failed login attempts retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'Failed login attempt retrieval failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'Failed to retrieve failed login attempts'
            );
        }
    }

    public function getErrorLogs($id = null, $data = [], $segments = [])
    {
        return $this->getLogs($id, $data, $segments);
    }

    public function getApiMetrics($id = null, $data = [], $segments = [])
    {
        return $this->getAPILoad($id, $data, $segments);
    }

    public function getDiagnostics($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success([
            'status' => 'online',
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? PHP_SAPI,
            'memory_limit' => ini_get('memory_limit'),
            'memory_usage' => round(memory_get_usage(true) / 1048576, 2) . ' MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1048576, 2) . ' MB',
            'loaded_extensions' => get_loaded_extensions(),
            'timestamp' => date('c'),
        ], 'System diagnostics retrieved');
    }

    public function getRateLimiting($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success([
            'status' => 'active',
            'uptime' => $this->formatUptime(),
            'window' => defined('RATE_LIMIT_WINDOW') ? RATE_LIMIT_WINDOW : null,
            'max_requests' => defined('RATE_LIMIT_MAX_REQUESTS') ? RATE_LIMIT_MAX_REQUESTS : null,
            'source' => 'RateLimitMiddleware',
            'timestamp' => date('c'),
        ], 'Rate limiting status retrieved');
    }

    public function getDataRetention($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success($this->getSystemState('data_retention', [
            'status' => 'active',
            'audit_log_days' => 365,
            'auth_event_days' => 180,
            'backup_days' => 30,
        ]), 'Data retention settings retrieved');
    }

    public function putDataRetention($id = null, $data = [], $segments = [])
    {
        return $this->saveSystemStateEndpoint('data_retention', $data, 'Data retention settings updated');
    }

    public function getBackgroundJobs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $rows = $this->tableExists('jobs') ? $this->fetchRows('jobs', 200, 'created_at DESC') : [];
        return $this->success($rows, 'Background jobs retrieved');
    }

    public function getJobInspector($id = null, $data = [], $segments = [])
    {
        return $this->getBackgroundJobs($id, $data, $segments);
    }

    public function getSecurityIncidents($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success($this->getAuditRows([
            'security_incident', 'permission_denied', 'unauthorized_access', 'failed_login', 'login_failed'
        ], null, 200), 'Security incidents retrieved');
    }

    public function getPolicyViolations($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success($this->getAuditRows([
            'policy_violation', 'permission_denied', 'rbac_denied', 'access_denied'
        ], null, 200), 'Policy violations retrieved');
    }

    public function getPermissionChanges($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success($this->getAuditRows([
            'permission_create', 'permission_update', 'permission_delete', 'role_permission_assign', 'role_permission_remove'
        ], null, 200), 'Permission changes retrieved');
    }

    public function getBackups($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

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

        return $this->success($backups, 'Backups retrieved');
    }

    public function postBackups($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $backupDir = $this->getBackupDirectory();
        try {
            $this->ensureManagedDirectory($backupDir);
        } catch (\Throwable $exception) {
            return $this->serverError('Unable to create backup directory');
        }

        $filename = 'backup_' . date('Ymd_His') . '.sql';
        $path = $backupDir . '/' . $filename;
        $payload = "-- Kingsway backup placeholder created by System Admin\n-- Created: " . date('c') . "\n";
        if ($this->writeManagedFile($path, $payload) === false) {
            return $this->serverError('Unable to create backup file');
        }

        return $this->success(['id' => $filename, 'name' => $filename, 'created_at' => date('c')], 'Backup created');
    }

    public function deleteBackups($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $backupId = basename((string) ($id ?? $data['id'] ?? ''));
        if ($backupId === '') {
            return $this->badRequest('Backup ID is required');
        }

        $path = $this->getBackupDirectory() . '/' . $backupId;
        if (!is_file($path)) {
            return $this->notFound('Backup not found');
        }

        return $this->deleteManagedFile($path)
            ? $this->success(null, 'Backup deleted')
            : $this->serverError('Unable to delete backup');
    }

    public function getMigrations($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
        $migrations = array_map(static function ($file) {
            return [
                'id' => basename($file),
                'name' => basename($file),
                'description' => 'SQL migration file',
                'status' => 'available',
                'created_at' => date('c', filemtime($file)),
            ];
        }, $files);

        return $this->success($migrations, 'Migrations retrieved');
    }

    public function postMigrations($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success([
            'requested' => $data['name'] ?? $data['id'] ?? null,
            'status' => 'queued',
            'message' => 'Migration execution request recorded; run SQL migrations through the deployment workflow.',
        ], 'Migration request recorded');
    }

    public function getFeatureFlags($id = null, $data = [], $segments = [])
    {
        return $this->getStateToggleList('feature_flags', 'Feature flags retrieved');
    }

    public function putFeatureFlags($id = null, $data = [], $segments = [])
    {
        return $this->putStateToggle('feature_flags', $id, $data, 'Feature flag updated');
    }

    public function getMaintenanceMode($id = null, $data = [], $segments = [])
    {
        return $this->getStateToggleList('maintenance_mode', 'Maintenance mode settings retrieved', [
            ['id' => 'maintenance_mode', 'key' => 'maintenance_mode', 'name' => 'Maintenance Mode', 'description' => 'Temporarily restrict application access', 'enabled' => false]
        ]);
    }

    public function putMaintenanceMode($id = null, $data = [], $segments = [])
    {
        return $this->putStateToggle('maintenance_mode', $id, $data, 'Maintenance mode updated');
    }

    public function getDomainIsolation($id = null, $data = [], $segments = [])
    {
        return $this->getStateToggleList('domain_isolation', 'Domain isolation settings retrieved');
    }

    public function putDomainIsolation($id = null, $data = [], $segments = [])
    {
        return $this->putStateToggle('domain_isolation', $id, $data, 'Domain isolation setting updated');
    }

    public function getTimeBoundAccess($id = null, $data = [], $segments = [])
    {
        return $this->getStateToggleList('time_bound_access', 'Time-bound access settings retrieved');
    }

    public function putTimeBoundAccess($id = null, $data = [], $segments = [])
    {
        return $this->putStateToggle('time_bound_access', $id, $data, 'Time-bound access setting updated');
    }

    public function getPermissionPolicies($id = null, $data = [], $segments = [])
    {
        return $this->getStateRecords('permission_policies', 'Permission policies retrieved');
    }

    public function postPermissionPolicies($id = null, $data = [], $segments = [])
    {
        return $this->saveStateRecord('permission_policies', null, $data, 'Permission policy created');
    }

    public function putPermissionPolicies($id = null, $data = [], $segments = [])
    {
        return $this->saveStateRecord('permission_policies', $id, $data, 'Permission policy updated');
    }

    public function deletePermissionPolicies($id = null, $data = [], $segments = [])
    {
        return $this->deleteStateRecord('permission_policies', $id ?? $data['id'] ?? null, 'Permission policy deleted');
    }

    public function getRouteAccessRules($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->getRoutes($id, $data, $segments);
    }

    public function postRouteAccessRules($id = null, $data = [], $segments = [])
    {
        return $this->postRoutes($id, $data, $segments);
    }

    public function putRouteAccessRules($id = null, $data = [], $segments = [])
    {
        return $this->putRoutes($id, $data, $segments);
    }

    public function deleteRouteAccessRules($id = null, $data = [], $segments = [])
    {
        return $this->deleteRoutes($id, $data, $segments);
    }

    public function getWebhookRegistry($id = null, $data = [], $segments = [])
    {
        return $this->getStateRecords('webhook_registry', 'Webhook registry retrieved');
    }

    public function postWebhookRegistry($id = null, $data = [], $segments = [])
    {
        return $this->saveStateRecord('webhook_registry', null, $data, 'Webhook created');
    }

    public function putWebhookRegistry($id = null, $data = [], $segments = [])
    {
        return $this->saveStateRecord('webhook_registry', $id, $data, 'Webhook updated');
    }

    public function deleteWebhookRegistry($id = null, $data = [], $segments = [])
    {
        return $this->deleteStateRecord('webhook_registry', $id ?? $data['id'] ?? null, 'Webhook deleted');
    }

    public function getMenus($id = null, $data = [], $segments = [])
    {
        return $this->getSidebarMenus($id, $data, $segments);
    }

    public function getRoleNavigation($id = null, $data = [], $segments = [])
    {
        return $this->getStateRecords('role_navigation', 'Role navigation config retrieved');
    }

    public function getIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge(
                $_GET,
                is_array($data) ? $data : []
            );

            return $this->success(
                $this->ipAccessControlService->getRegistry(
                    $filters,
                    IpAccessControlService::resolveClientIp()
                ),
                'IP access rules retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'IP rule registry retrieval failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'IP access rules could not be retrieved'
            );
        }
    }

    public function postIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $rule = $this->ipAccessControlService->createRule(
                is_array($data) ? $data : [],
                (int) $this->getUserId(),
                IpAccessControlService::resolveClientIp()
            );

            return $this->created($rule, 'IP access rule created');
        } catch (\DomainException $e) {
            return $this->conflict($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log('IP rule creation failed: ' . $e->getMessage());
            return $this->serverError(
                'The IP access rule could not be created'
            );
        }
    }

    public function putIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $ruleId = filter_var(
            $id ?? $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );
        if ($ruleId === false || $ruleId <= 0) {
            return $this->badRequest('A valid IP rule ID is required');
        }

        try {
            $rule = $this->ipAccessControlService->updateRule(
                (int) $ruleId,
                is_array($data) ? $data : [],
                (int) $this->getUserId(),
                IpAccessControlService::resolveClientIp()
            );

            return $this->success($rule, 'IP access rule updated');
        } catch (\DomainException $e) {
            return $this->conflict($e->getMessage());
        } catch (\OutOfBoundsException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log('IP rule update failed: ' . $e->getMessage());
            return $this->serverError(
                'The IP access rule could not be updated'
            );
        }
    }

    public function deleteIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $ruleId = filter_var(
            $id ?? $data['id'] ?? null,
            FILTER_VALIDATE_INT
        );
        if ($ruleId === false || $ruleId <= 0) {
            return $this->badRequest('A valid IP rule ID is required');
        }

        try {
            $result = $this->ipAccessControlService->deleteRule(
                (int) $ruleId,
                (int) $this->getUserId(),
                IpAccessControlService::resolveClientIp()
            );

            return $this->success($result, 'IP access rule deleted');
        } catch (\DomainException $e) {
            return $this->conflict($e->getMessage());
        } catch (\OutOfBoundsException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log('IP rule deletion failed: ' . $e->getMessage());
            return $this->serverError(
                'The IP access rule could not be deleted'
            );
        }
    }

    public function getTokens($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge(
                $_GET,
                is_array($data) ? $data : []
            );

            return $this->success(
                $this->authSessionService->getTokenRegistry(
                    $filters,
                    isset($_SERVER['auth_session_id'])
                        ? (int) $_SERVER['auth_session_id']
                        : null
                ),
                'Tokens retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'Token registry retrieval failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'Token records could not be retrieved'
            );
        }
    }

    public function postTokensRevoke($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $tokenId = filter_var(
            $data['token_id'] ?? $id,
            FILTER_VALIDATE_INT
        );
        $tokenType = trim((string) ($data['token_type'] ?? ''));
        if ($tokenId === false || $tokenId <= 0 || $tokenType === '') {
            return $this->badRequest(
                'A valid token ID and token type are required'
            );
        }

        try {
            $result = $this->authSessionService
                ->revokeTokenByAdministrator(
                    (int) $tokenId,
                    $tokenType,
                    (int) $this->getUserId(),
                    isset($_SERVER['auth_session_id'])
                        ? (int) $_SERVER['auth_session_id']
                        : null
                );

            return $this->success($result, 'Token revoked');
        } catch (\DomainException $e) {
            return $this->conflict($e->getMessage());
        } catch (\OutOfBoundsException $e) {
            return $this->notFound($e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return $this->badRequest($e->getMessage());
        } catch (Exception $e) {
            error_log(
                'Token revocation failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'The token could not be revoked'
            );
        }
    }

    public function getResourcePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            if (!$this->tableExists('permissions')) {
                return $this->serverError('The permissions table is unavailable');
            }

            $filters = array_merge($_GET, $data ?? []);
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
                $where[] = '(
                    p.code LIKE ?
                    OR p.description LIKE ?
                    OR p.entity LIKE ?
                    OR p.action LIKE ?
                    OR p.module LIKE ?
                )';
                $term = '%' . $search . '%';
                array_push($params, $term, $term, $term, $term, $term);
            }
            foreach (
                [
                    'p.module' => $module,
                    'p.entity' => $entity,
                    'p.action' => $action,
                ] as $column => $value
            ) {
                if ($value !== '') {
                    $where[] = "$column = ?";
                    $params[] = $value;
                }
            }

            $whereSql = implode(' AND ', $where);
            $total = (int) ($this->db->query(
                "SELECT COUNT(*) FROM permissions p WHERE $whereSql",
                $params
            )->fetchColumn() ?? 0);
            $totalPages = max(1, (int) ceil($total / $limit));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $limit;

            $usageDefinitions = $this->permissionUsageDefinitions();
            $usageColumns = [];
            foreach (array_keys($usageDefinitions) as $table) {
                $usageColumns[] = "(
                    SELECT COUNT(*)
                    FROM $table dependency
                    WHERE dependency.permission_id = p.id
                ) AS {$table}_count";
            }
            $usageSql = empty($usageColumns)
                ? ''
                : ', ' . implode(', ', $usageColumns);

            $rows = $this->db->query(
                "SELECT
                    p.id,
                    p.code,
                    p.description,
                    p.entity,
                    p.action,
                    p.module,
                    p.created_at,
                    p.updated_at
                    $usageSql
                 FROM permissions p
                 WHERE $whereSql
                 ORDER BY
                    COALESCE(p.module, ''),
                    COALESCE(p.entity, ''),
                    COALESCE(p.action, ''),
                    p.code,
                    p.id
                 LIMIT $limit OFFSET $offset",
                $params
            )->fetchAll() ?? [];

            $rows = array_map(
                fn (array $row) => $this->formatPermissionDefinition(
                    $row,
                    $usageDefinitions
                ),
                $rows
            );

            $summary = $this->getPermissionDefinitionSummary(
                $usageDefinitions
            );
            $availableFilters = [
                'modules' => $this->getDistinctPermissionValues('module'),
                'entities' => $this->getDistinctPermissionValues('entity'),
                'actions' => $this->getDistinctPermissionValues('action'),
            ];

            return $this->success([
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
        } catch (Exception $e) {
            error_log(
                'Resource permission retrieval failed: ' . $e->getMessage()
            );
            return $this->serverError(
                'Failed to load resource permission definitions'
            );
        }
    }

    public function getRolePermissionMatrix($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        $roles = $this->tableExists('roles') ? $this->fetchRows('roles', 500, 'name') : [];
        $permissions = $this->tableExists('permissions') ? $this->fetchRows('permissions', 1000, 'entity, action, code') : [];
        $matrix = [];
        if ($this->tableExists('role_permissions')) {
            $assignments = $this->db->query('SELECT role_id, permission_id FROM role_permissions', [])->fetchAll() ?? [];
            foreach ($assignments as $assignment) {
                $matrix[(string) $assignment['role_id']][] = (string) $assignment['permission_id'];
            }
        }

        return $this->success([
            'rows' => $roles,
            'columns' => $permissions,
            'matrix' => $matrix,
        ], 'Role permission matrix retrieved');
    }

    public function getAccountStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success($this->tableExists('users') ? $this->fetchRows(
            'users',
            500,
            'id DESC',
            'id, username, email, first_name, last_name, status, failed_login_attempts, account_locked_until, force_password_change, last_login, created_at, updated_at'
        ) : [], 'Account status retrieved');
    }

    public function putAccountStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $userId = $id ?? $data['user_id'] ?? $data['id'] ?? null;
        if (!$userId || !$this->tableExists('users')) {
            return $this->badRequest('User ID is required');
        }

        $current = $this->db->query(
            'SELECT id, username, status, failed_login_attempts, account_locked_until, force_password_change, updated_at FROM users WHERE id = ?',
            [(int) $userId]
        )->fetch();
        if (!$current) {
            return $this->badRequest('User account not found');
        }

        if ((int) $userId === (int) ($this->user['id'] ?? 0) && isset($data['status']) && $data['status'] !== 'active') {
            return $this->badRequest('You cannot deactivate or suspend your own account');
        }

        $fields = [];
        $values = [];
        $changes = [];

        if (array_key_exists('status', $data)) {
            $allowedStatuses = ['active', 'inactive', 'suspended', 'pending'];
            if (!in_array($data['status'], $allowedStatuses, true)) {
                return $this->badRequest('Invalid account status');
            }
            $fields[] = 'status = ?';
            $values[] = $data['status'];
            $changes['status'] = ['from' => $current['status'], 'to' => $data['status']];
        }

        if (array_key_exists('failed_login_attempts', $data)) {
            $attempts = filter_var($data['failed_login_attempts'], FILTER_VALIDATE_INT);
            if ($attempts === false || $attempts < 0) {
                return $this->badRequest('failed_login_attempts must be a non-negative integer');
            }
            $fields[] = 'failed_login_attempts = ?';
            $values[] = $attempts;
            $changes['failed_login_attempts'] = ['from' => $current['failed_login_attempts'], 'to' => $attempts];
        }

        if (array_key_exists('account_locked_until', $data)) {
            $lockedUntil = $data['account_locked_until'];
            if ($lockedUntil !== null && $lockedUntil !== '' && strtotime((string) $lockedUntil) === false) {
                return $this->badRequest('account_locked_until must be a valid date or null');
            }
            $lockedUntil = ($lockedUntil === '') ? null : $lockedUntil;
            $fields[] = 'account_locked_until = ?';
            $values[] = $lockedUntil;
            $changes['account_locked_until'] = ['from' => $current['account_locked_until'], 'to' => $lockedUntil];
        }

        if (array_key_exists('force_password_change', $data)) {
            $forceChange = filter_var($data['force_password_change'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($forceChange === null) {
                return $this->badRequest('force_password_change must be true or false');
            }
            $forceChange = $forceChange ? 1 : 0;
            $fields[] = 'force_password_change = ?';
            $values[] = $forceChange;
            $changes['force_password_change'] = ['from' => (int) $current['force_password_change'], 'to' => $forceChange];
        }
        if (empty($fields)) {
            return $this->badRequest('No supported account status fields provided');
        }
        if ($this->tableHasColumn('users', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }
        $values[] = $userId;
        $this->db->query('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?', $values);

        $wasLocked = !empty($current['account_locked_until']);
        $isUnlock = $wasLocked && array_key_exists('account_locked_until', $data) && $data['account_locked_until'] === null;
        if ($isUnlock && $this->tableExists('account_unlock_history')) {
            $this->db->query(
                'INSERT INTO account_unlock_history
                    (user_id, locked_reason, locked_date, unlocked_date, unlocked_by, unlock_reason, created_at)
                 VALUES (?, ?, ?, NOW(), ?, ?, NOW())',
                [
                    (int) $userId,
                    'Account lock recorded on user account',
                    $current['updated_at'],
                    (int) ($this->user['id'] ?? 0),
                    $data['unlock_reason'] ?? 'Unlocked by System Administrator',
                ]
            );
        }

        (new AuditLogger($this->db->getConnection()))->log(
            'account_status_update',
            'user',
            (int) $userId,
            (int) ($this->user['id'] ?? 0),
            ['username' => $current['username'], 'changes' => $changes]
        );

        return $this->success(['id' => (int) $userId], 'Account status updated');
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
    // ROLES MANAGEMENT
    // ========================================================================

    /**
     * GET /api/system/roles[/id] - List role definitions or return one role.
     */
    public function getRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }

        try {
            $roleId = $id ?? $data['id'] ?? $_GET['id'] ?? null;
            $roleId = $roleId !== null ? (int) $roleId : null;

            if ($roleId !== null && $roleId <= 0) {
                return $this->badRequest('Role ID must be a positive integer');
            }

            $roles = $this->fetchRoleDefinitions(
                $roleId,
                $this->isSchoolAdmin()
            );

            if ($roleId !== null) {
                if (empty($roles)) {
                    return $this->notFound('Role not found');
                }

                return $this->success($roles[0], 'Role retrieved');
            }

            return $this->success($roles, 'Roles retrieved');
        } catch (Exception $e) {
            return $this->badRequest('Failed to load roles: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/roles - Create a custom role definition.
     *
     * is_system is intentionally reserved for seeded application roles. Runtime
     * role creation always produces a custom role.
     */
    public function postRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }

        $db = null;

        try {
            $db = Database::getInstance();
            $name = trim((string) ($data['name'] ?? ''));
            $description = trim((string) ($data['description'] ?? ''));

            if ($name === '') {
                return $this->badRequest('Role name is required');
            }
            if ($this->roleNameLength($name) > 50) {
                return $this->badRequest('Role name must not exceed 50 characters');
            }

            $scope = 'school';
            if ($this->isSystemAdmin()) {
                $scope = strtolower(trim((string) ($data['scope'] ?? 'school')));
                if (!in_array($scope, ['system', 'school'], true)) {
                    return $this->badRequest('Role scope must be system or school');
                }
            }

            $isActive = 1;
            if (array_key_exists('is_active', $data)) {
                $isActive = $this->normalizeToggleValue($data['is_active']);
                if ($isActive === null) {
                    return $this->badRequest('is_active must be true or false');
                }
            }

            $existing = $db->query(
                'SELECT id FROM roles WHERE name = ? LIMIT 1',
                [$name]
            )->fetch();
            if ($existing) {
                return $this->conflict('A role with this name already exists');
            }

            $db->beginTransaction();
            $db->query(
                'INSERT INTO roles
                    (name, description, scope, is_system, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, 0, ?, NOW(), NOW())',
                [$name, $description !== '' ? $description : null, $scope, $isActive]
            );

            $roleId = (int) $db->lastInsertId();
            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'role_create',
                'role',
                $roleId,
                $this->getUserId(),
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
            return $this->created(
                $created[0] ?? ['id' => $roleId],
                'Role created successfully'
            );
        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->badRequest('Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * PUT /api/system/roles[/id] - Update a custom role definition.
     */
    public function putRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }

        $db = null;

        try {
            $db = Database::getInstance();
            $roleId = (int) ($id ?? $data['id'] ?? 0);

            if ($roleId <= 0) {
                return $this->badRequest('Role ID is required');
            }

            $existingRows = $this->fetchRoleDefinitions($roleId, false);
            $role = $existingRows[0] ?? null;
            if (!$role) {
                return $this->notFound('Role not found');
            }
            if ((int) ($role['is_system'] ?? 0) === 1) {
                return $this->forbidden('Protected system roles are read-only');
            }
            if (
                $this->isSchoolAdmin() &&
                ($role['scope'] ?? 'school') === 'system'
            ) {
                return $this->forbidden('Cannot modify system-scope roles');
            }

            $fields = [];
            $values = [];
            $changes = [];

            if (array_key_exists('name', $data)) {
                $name = trim((string) $data['name']);
                if ($name === '') {
                    return $this->badRequest('Role name is required');
                }
                if ($this->roleNameLength($name) > 50) {
                    return $this->badRequest('Role name must not exceed 50 characters');
                }

                $duplicate = $db->query(
                    'SELECT id FROM roles WHERE name = ? AND id <> ? LIMIT 1',
                    [$name, $roleId]
                )->fetch();
                if ($duplicate) {
                    return $this->conflict('A role with this name already exists');
                }

                if ($name !== (string) $role['name']) {
                    $fields[] = 'name = ?';
                    $values[] = $name;
                    $changes['name'] = [
                        'from' => $role['name'],
                        'to' => $name,
                    ];
                }
            }

            if (array_key_exists('description', $data)) {
                $description = trim((string) $data['description']);
                $description = $description !== '' ? $description : null;
                $oldDescription = $role['description'] !== ''
                    ? $role['description']
                    : null;

                if ($description !== $oldDescription) {
                    $fields[] = 'description = ?';
                    $values[] = $description;
                    $changes['description'] = [
                        'from' => $oldDescription,
                        'to' => $description,
                    ];
                }
            }

            if (array_key_exists('scope', $data)) {
                if (!$this->isSystemAdmin()) {
                    if (strtolower((string) $data['scope']) !== 'school') {
                        return $this->forbidden('School Administrators can only manage school-scope roles');
                    }
                } else {
                    $scope = strtolower(trim((string) $data['scope']));
                    if (!in_array($scope, ['system', 'school'], true)) {
                        return $this->badRequest('Role scope must be system or school');
                    }
                    if ($scope !== (string) $role['scope']) {
                        $fields[] = 'scope = ?';
                        $values[] = $scope;
                        $changes['scope'] = [
                            'from' => $role['scope'],
                            'to' => $scope,
                        ];
                    }
                }
            }

            if (empty($fields)) {
                return $this->success($role, 'No role changes were required');
            }

            $fields[] = 'updated_at = NOW()';
            $values[] = $roleId;
            $db->beginTransaction();
            $db->query(
                'UPDATE roles SET ' . implode(', ', $fields) . ' WHERE id = ?',
                $values
            );

            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'role_update',
                'role',
                $roleId,
                $this->getUserId(),
                ['name' => $role['name'], 'changes' => $changes]
            );
            if (!$auditLogged) {
                throw new Exception('Role update audit could not be recorded');
            }
            $db->commit();

            $updated = $this->fetchRoleDefinitions($roleId, false);
            return $this->success(
                $updated[0] ?? ['id' => $roleId],
                'Role updated successfully'
            );
        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->badRequest('Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * DELETE /api/system/roles/{id} - Delete an unused custom role.
     */
    public function deleteRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }

        $db = Database::getInstance();

        try {
            $roleId = (int) ($id ?? $data['id'] ?? $_GET['id'] ?? 0);
            if ($roleId <= 0) {
                return $this->badRequest('Role ID is required');
            }

            $roleRows = $this->fetchRoleDefinitions($roleId, false);
            $role = $roleRows[0] ?? null;
            if (!$role) {
                return $this->notFound('Role not found');
            }
            if ((int) ($role['is_system'] ?? 0) === 1) {
                return $this->forbidden('Protected system roles cannot be deleted');
            }
            if (
                $this->isSchoolAdmin() &&
                ($role['scope'] ?? 'school') === 'system'
            ) {
                return $this->forbidden('Cannot delete system-scope roles');
            }

            $blockers = $role['delete_blockers'] ?? [];
            if (!empty($blockers)) {
                return $this->conflict(
                    'Role cannot be deleted while it is in use',
                    ['blockers' => $blockers]
                );
            }

            $db->beginTransaction();
            $db->query('DELETE FROM roles WHERE id = ?', [$roleId]);

            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'role_delete',
                'role',
                $roleId,
                $this->getUserId(),
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

            return $this->success(['id' => $roleId], 'Role deleted successfully');
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            return $this->badRequest('Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * POST /api/system/roles-toggle - Activate or deactivate a custom role.
     */
    public function postRolesToggle($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }

        $db = null;

        try {
            $db = Database::getInstance();
            $roleId = (int) ($id ?? $data['id'] ?? 0);
            $isActive = $data['is_active'] ?? $data['enabled'] ?? null;

            if ($roleId <= 0) {
                return $this->badRequest('Role ID is required');
            }
            if (!$this->tableHasColumn('roles', 'is_active')) {
                return $this->badRequest('Role status toggle is not supported by current schema');
            }

            $normalized = $this->normalizeToggleValue($isActive);
            if ($normalized === null) {
                return $this->badRequest('is_active/enabled must be true or false');
            }

            $roleRows = $this->fetchRoleDefinitions($roleId, false);
            $role = $roleRows[0] ?? null;
            if (!$role) {
                return $this->notFound('Role not found');
            }
            if ((int) ($role['is_system'] ?? 0) === 1) {
                return $this->forbidden('Protected system roles cannot be deactivated');
            }
            if (
                $this->isSchoolAdmin() &&
                ($role['scope'] ?? 'school') === 'system'
            ) {
                return $this->forbidden('Cannot modify system-scope roles');
            }

            $currentStatus = (int) ($role['is_active'] ?? 0);
            if ($currentStatus === $normalized) {
                return $this->success(
                    ['id' => $roleId, 'is_active' => (bool) $normalized],
                    'Role status is already up to date'
                );
            }

            $db->beginTransaction();
            $db->query(
                'UPDATE roles SET is_active = ?, updated_at = NOW() WHERE id = ?',
                [$normalized, $roleId]
            );

            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'role_status_update',
                'role',
                $roleId,
                $this->getUserId(),
                [
                    'name' => $role['name'],
                    'is_active' => [
                        'from' => $currentStatus,
                        'to' => $normalized,
                    ],
                ]
            );
            if (!$auditLogged) {
                throw new Exception('Role status audit could not be recorded');
            }
            $db->commit();

            return $this->success(
                ['id' => $roleId, 'is_active' => (bool) $normalized],
                'Role status updated'
            );
        } catch (Exception $e) {
            if ($db && $db->inTransaction()) {
                $db->rollback();
            }
            return $this->badRequest('Failed to toggle status: ' . $e->getMessage());
        }
    }

    /**
     * Return role definitions with live relationship counts.
     *
     * roles.user_count is not trusted because the canonical dump contains no
     * trigger that maintains it. User assignments are resolved from user_roles
     * plus legacy users.role_id rows that are not already represented there.
     */
    private function fetchRoleDefinitions(
        ?int $roleId = null,
        bool $schoolAdminOnly = false
    ): array {
        $where = [];
        $params = [];

        if ($roleId !== null) {
            $where[] = 'r.id = ?';
            $params[] = $roleId;
        }
        if ($schoolAdminOnly) {
            $where[] = "r.scope = 'school'";
        }

        $whereSql = empty($where)
            ? ''
            : ' WHERE ' . implode(' AND ', $where);

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
                (
                    SELECT COUNT(DISTINCT ur.user_id)
                    FROM user_roles ur
                    WHERE ur.role_id = r.id
                ) + (
                    SELECT COUNT(*)
                    FROM users u
                    WHERE u.role_id = r.id
                      AND NOT EXISTS (
                          SELECT 1
                          FROM user_roles existing_ur
                          WHERE existing_ur.user_id = u.id
                            AND existing_ur.role_id = r.id
                      )
                ) AS user_count,
                (
                    SELECT COUNT(*)
                    FROM role_permissions rp
                    WHERE rp.role_id = r.id
                ) AS permission_count,
                (
                    SELECT COUNT(*)
                    FROM role_routes rr
                    WHERE rr.role_id = r.id
                ) AS route_count,
                (
                    SELECT COUNT(*)
                    FROM role_sidebar_menus rsm
                    WHERE rsm.role_id = r.id
                ) AS navigation_count,
                (
                    SELECT COUNT(*)
                    FROM role_dashboards rd
                    WHERE rd.role_id = r.id
                ) AS dashboard_count,
                (
                    SELECT COUNT(*)
                    FROM workflow_stage_permissions wsp
                    WHERE wsp.role_id = r.id
                ) AS workflow_count,
                (
                    SELECT COUNT(*)
                    FROM record_permissions recp
                    WHERE recp.role_id = r.id
                ) AS record_permission_count,
                (
                    SELECT COUNT(*)
                    FROM system_time_bound_access stba
                    WHERE stba.role_id = r.id
                ) AS time_bound_access_count,
                (
                    SELECT COUNT(*)
                    FROM role_delegations rdel
                    WHERE rdel.delegator_role_id = r.id
                       OR rdel.delegate_role_id = r.id
                ) AS delegation_count,
                (
                    SELECT COUNT(*)
                    FROM allowance_templates atpl
                    WHERE atpl.role_id = r.id
                ) AS allowance_template_count
            FROM roles r
            {$whereSql}
            ORDER BY r.scope, r.name
        ";

        $rows = Database::getInstance()->query($sql, $params)->fetchAll() ?? [];
        return array_map(
            [$this, 'decorateRoleDefinition'],
            $rows
        );
    }

    /**
     * Cast role values and expose only non-zero delete blockers.
     */
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
            'allowance_templates' => (int) ($role['allowance_template_count'] ?? 0),
        ], static fn ($count) => $count > 0);

        $role['id'] = (int) ($role['id'] ?? 0);
        $role['is_system'] = (int) ($role['is_system'] ?? 0);
        $role['is_active'] = (int) ($role['is_active'] ?? 0);
        $role['user_count'] = (int) ($role['user_count'] ?? 0);
        $role['permission_count'] = (int) ($role['permission_count'] ?? 0);
        $role['delete_blockers'] = $blockers;
        $role['can_delete'] =
            $role['is_system'] === 0 &&
            empty($blockers);

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

    private function roleNameLength(string $name): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($name, 'UTF-8')
            : strlen($name);
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

        $validation = $this->normalizePermissionDefinitionPayload(
            $data,
            true
        );
        if (!$validation['valid']) {
            return $this->badRequest($validation['message']);
        }

        $db = Database::getInstance();

        try {
            $payload = $validation['data'];
            $db->beginTransaction();

            $duplicate = $db->query(
                'SELECT id FROM permissions WHERE code = ? LIMIT 1',
                [$payload['code']]
            )->fetch();
            if ($duplicate) {
                $db->rollback();
                return $this->conflict(
                    'A permission with this code already exists'
                );
            }

            $db->query(
                'INSERT INTO permissions
                    (
                        code,
                        description,
                        entity,
                        action,
                        module,
                        created_at,
                        updated_at
                    )
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $payload['code'],
                    $payload['description'],
                    $payload['entity'],
                    $payload['action'],
                    $payload['module'],
                ]
            );
            $permissionId = (int) $db->lastInsertId();

            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'permission_definition_create',
                'permission',
                $permissionId,
                $this->getUserId(),
                $payload
            );
            if (!$auditLogged) {
                throw new Exception(
                    'Permission creation audit could not be recorded'
                );
            }

            $db->commit();
            $created = $this->getPermissionDefinitionById($permissionId);

            return $this->created(
                $this->formatPermissionDefinition(
                    $created ?? ['id' => $permissionId] + $payload,
                    $this->permissionUsageDefinitions()
                ),
                'Permission created successfully'
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('Permission creation failed: ' . $e->getMessage());
            return $this->serverError('Failed to create permission');
        }
    }

    public function putPermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $permissionId = (int) ($id ?? $data['id'] ?? 0);
        if ($permissionId <= 0) {
            return $this->badRequest('Permission ID is required');
        }

        $validation = $this->normalizePermissionDefinitionPayload(
            $data,
            false
        );
        if (!$validation['valid']) {
            return $this->badRequest($validation['message']);
        }
        if (empty($validation['data'])) {
            return $this->badRequest(
                'No supported permission fields were provided'
            );
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();
            $current = $this->getPermissionDefinitionById(
                $permissionId,
                true
            );
            if (!$current) {
                $db->rollback();
                return $this->notFound('Permission not found');
            }

            $usageDefinitions = $this->permissionUsageDefinitions();
            $usage = $this->getPermissionUsageCounts(
                $permissionId,
                $usageDefinitions
            );
            $usageTotal = array_sum($usage);
            $payload = $validation['data'];

            if (
                array_key_exists('code', $payload) &&
                $payload['code'] !== (string) $current['code']
            ) {
                if ($usageTotal > 0) {
                    $db->rollback();
                    return $this->conflict(
                        'Permission codes cannot be changed while the permission is in use',
                        ['usage' => $usage, 'usage_total' => $usageTotal]
                    );
                }

                $duplicate = $db->query(
                    'SELECT id
                     FROM permissions
                     WHERE code = ? AND id <> ?
                     LIMIT 1',
                    [$payload['code'], $permissionId]
                )->fetch();
                if ($duplicate) {
                    $db->rollback();
                    return $this->conflict(
                        'A permission with this code already exists'
                    );
                }
            }

            $fields = [];
            $values = [];
            $changes = [];
            foreach (
                ['code', 'description', 'entity', 'action', 'module'] as $field
            ) {
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
                $changes[$field] = [
                    'from' => $oldValue,
                    'to' => $newValue,
                ];
            }

            if (empty($fields)) {
                $db->rollback();
                return $this->success(
                    $this->formatPermissionDefinition(
                        $current,
                        $usageDefinitions,
                        $usage
                    ),
                    'No permission changes were required'
                );
            }

            $fields[] = 'updated_at = NOW()';
            $values[] = $permissionId;
            $db->query(
                'UPDATE permissions SET ' .
                    implode(', ', $fields) .
                    ' WHERE id = ?',
                $values
            );

            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'permission_definition_update',
                'permission',
                $permissionId,
                $this->getUserId(),
                [
                    'code' => $current['code'],
                    'changes' => $changes,
                ]
            );
            if (!$auditLogged) {
                throw new Exception(
                    'Permission update audit could not be recorded'
                );
            }

            $db->commit();
            $updated = $this->getPermissionDefinitionById($permissionId);

            return $this->success(
                $this->formatPermissionDefinition(
                    $updated ?? $current,
                    $usageDefinitions
                ),
                'Permission updated successfully'
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('Permission update failed: ' . $e->getMessage());
            return $this->serverError('Failed to update permission');
        }
    }

    public function deletePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $permissionId = (int) ($id ?? $data['id'] ?? 0);
        if ($permissionId <= 0) {
            return $this->badRequest('Permission ID is required');
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();
            $current = $this->getPermissionDefinitionById(
                $permissionId,
                true
            );
            if (!$current) {
                $db->rollback();
                return $this->notFound('Permission not found');
            }

            $usageDefinitions = $this->permissionUsageDefinitions();
            $usage = $this->getPermissionUsageCounts(
                $permissionId,
                $usageDefinitions
            );
            $usage = array_filter(
                $usage,
                static fn ($count) => (int) $count > 0
            );
            if (!empty($usage)) {
                $db->rollback();
                return $this->conflict(
                    'Permission cannot be deleted while it is in use',
                    [
                        'usage' => $usage,
                        'usage_total' => array_sum($usage),
                    ]
                );
            }

            $db->query(
                'DELETE FROM permissions WHERE id = ?',
                [$permissionId]
            );

            $auditLogged = (new AuditLogger($db->getConnection()))->log(
                'permission_definition_delete',
                'permission',
                $permissionId,
                $this->getUserId(),
                [
                    'code' => $current['code'],
                    'description' => $current['description'],
                    'entity' => $current['entity'],
                    'action' => $current['action'],
                    'module' => $current['module'],
                ]
            );
            if (!$auditLogged) {
                throw new Exception(
                    'Permission deletion audit could not be recorded'
                );
            }

            $db->commit();
            return $this->success(
                ['id' => $permissionId],
                'Permission deleted successfully'
            );
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            error_log('Permission deletion failed: ' . $e->getMessage());
            return $this->serverError('Failed to delete permission');
        }
    }

    /**
     * Permission relationships that make a definition unsafe to rename/delete.
     *
     * @return array<string, string>
     */
    private function permissionUsageDefinitions(): array
    {
        $definitions = [
            'role_permissions' => 'role assignments',
            'route_permissions' => 'route requirements',
            'user_permissions' => 'user overrides',
            'system_permission_changes' => 'permission change records',
            'system_route_access_rules' => 'route access rules',
            'system_time_bound_access' => 'time-bound grants',
            'workflow_stage_permissions' => 'workflow stage rules',
        ];

        return array_filter(
            $definitions,
            fn ($label, $table) =>
                $this->tableExists($table) &&
                $this->tableHasColumn($table, 'permission_id'),
            ARRAY_FILTER_USE_BOTH
        );
    }

    private function getPermissionDefinitionById(
        int $permissionId,
        bool $forUpdate = false
    ): ?array {
        $query = 'SELECT
                    id,
                    code,
                    description,
                    entity,
                    action,
                    module,
                    created_at,
                    updated_at
                  FROM permissions
                  WHERE id = ?
                  LIMIT 1';
        if ($forUpdate) {
            $query .= ' FOR UPDATE';
        }

        $row = $this->db->query($query, [$permissionId])->fetch();
        return $row ?: null;
    }

    /**
     * @param array<string, string> $usageDefinitions
     * @return array<string, int>
     */
    private function getPermissionUsageCounts(
        int $permissionId,
        array $usageDefinitions
    ): array {
        $usage = [];
        foreach (array_keys($usageDefinitions) as $table) {
            $usage[$table] = (int) ($this->db->query(
                "SELECT COUNT(*) FROM $table WHERE permission_id = ?",
                [$permissionId]
            )->fetchColumn() ?? 0);
        }

        return $usage;
    }

    /**
     * @param array<string, string> $usageDefinitions
     * @param array<string, int>|null $usageCounts
     */
    private function formatPermissionDefinition(
        array $permission,
        array $usageDefinitions,
        ?array $usageCounts = null
    ): array {
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
                $usageCounts = $this->getPermissionUsageCounts(
                    $permissionId,
                    $usageDefinitions
                );
            }
        }

        foreach (array_keys($usageDefinitions) as $table) {
            unset($permission["{$table}_count"]);
            $usageCounts[$table] = (int) ($usageCounts[$table] ?? 0);
        }

        $usageTotal = array_sum($usageCounts);
        $permission['id'] = $permissionId;
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

    /**
     * @param array<string, string> $usageDefinitions
     */
    private function getPermissionDefinitionSummary(
        array $usageDefinitions
    ): array {
        $totals = $this->db->query(
            "SELECT
                COUNT(*) AS total_permissions,
                COUNT(
                    DISTINCT NULLIF(TRIM(COALESCE(entity, '')), '')
                ) AS resource_count,
                COUNT(
                    DISTINCT NULLIF(TRIM(COALESCE(module, '')), '')
                ) AS module_count
             FROM permissions",
            []
        )->fetch() ?: [];

        $inUsePermissions = 0;
        if (!empty($usageDefinitions)) {
            $exists = [];
            foreach (array_keys($usageDefinitions) as $table) {
                $exists[] = "EXISTS (
                    SELECT 1
                    FROM $table dependency
                    WHERE dependency.permission_id = p.id
                )";
            }
            $inUsePermissions = (int) ($this->db->query(
                'SELECT COUNT(*)
                 FROM permissions p
                 WHERE ' . implode(' OR ', $exists),
                []
            )->fetchColumn() ?? 0);
        }

        return [
            'total_permissions' => (int) (
                $totals['total_permissions'] ?? 0
            ),
            'resource_count' => (int) ($totals['resource_count'] ?? 0),
            'module_count' => (int) ($totals['module_count'] ?? 0),
            'in_use_permissions' => $inUsePermissions,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getDistinctPermissionValues(string $column): array
    {
        if (!in_array($column, ['module', 'entity', 'action'], true)) {
            return [];
        }

        $rows = $this->db->query(
            "SELECT DISTINCT $column AS value
             FROM permissions
             WHERE $column IS NOT NULL
               AND TRIM($column) <> ''
             ORDER BY $column",
            []
        )->fetchAll() ?? [];

        return array_values(array_map(
            static fn (array $row) => (string) $row['value'],
            $rows
        ));
    }

    /**
     * Normalize and validate permission definition fields.
     *
     * @return array{valid: bool, data: array, message: string}
     */
    private function normalizePermissionDefinitionPayload(
        array $data,
        bool $creating
    ): array {
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
                return [
                    'valid' => false,
                    'data' => [],
                    'message' => "$field must be a text value",
                ];
            }

            $value = trim((string) ($value ?? ''));
            if ($field === 'code') {
                if ($value === '') {
                    return [
                        'valid' => false,
                        'data' => [],
                        'message' => 'Permission code is required',
                    ];
                }
                if (!preg_match('/^[A-Za-z0-9._:-]+$/D', $value)) {
                    return [
                        'valid' => false,
                        'data' => [],
                        'message' =>
                            'Permission code contains unsupported characters',
                    ];
                }
            }

            if ($this->permissionTextLength($value) > $maximumLength) {
                return [
                    'valid' => false,
                    'data' => [],
                    'message' =>
                        "$field must not exceed $maximumLength characters",
                ];
            }

            $normalized[$field] = $field === 'code' || $value !== ''
                ? $value
                : null;
        }

        return [
            'valid' => true,
            'data' => $normalized,
            'message' => '',
        ];
    }

    private function permissionTextLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
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
        if ($auth = $this->ensureRoleManagementAccess(true)) {
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
                if ($ins->rowCount() > 0) {
                    $count++;
                    (new AuditLogger($db->getConnection()))->log(
                        'role_permission_assign',
                        'role_permission',
                        (int)$pid,
                        $this->getUserId(),
                        ['role_id' => (int)$roleId, 'permission_id' => (int)$pid]
                    );
                }
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
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }

        try {
            $db = Database::getInstance();

            $roleId       = $data['role_id'] ?? $_GET['role_id'] ?? null;
            $permissionId = $id ?? $data['permission_id'] ?? $_GET['permission_id'] ?? null;

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

            $statement = $db->query(
                "DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?",
                [(int)$roleId, (int)$permissionId]
            );

            if ($statement->rowCount() > 0) {
                (new AuditLogger($db->getConnection()))->log(
                    'role_permission_remove',
                    'role_permission',
                    (int)$permissionId,
                    $this->getUserId(),
                    ['role_id' => (int)$roleId, 'permission_id' => (int)$permissionId]
                );
            }

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

    public function postSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        if (empty($data['name']) || empty($data['label'])) return $this->badRequest('name and label are required');
        try {
            $this->db->query(
                'INSERT INTO sidebar_menu_items (name,label,icon,url,route_id,parent_id,menu_type,display_order,domain,is_active) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$data['name'], $data['label'], $data['icon'] ?? null, $data['url'] ?? null,
                 $data['route_id'] ?? null, $data['parent_id'] ?? null, $data['menu_type'] ?? 'sidebar',
                 (int)($data['display_order'] ?? 0), $data['domain'] ?? 'SYSTEM', (int)($data['is_active'] ?? 1)]
            );
            return $this->created(['id' => (int)$this->db->lastInsertId()], 'Sidebar menu created');
        } catch (Exception $e) { return $this->badRequest('Failed to create sidebar menu: ' . $e->getMessage()); }
    }

    public function putSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        $menuId = $id ?? $data['id'] ?? null;
        if (!$menuId) return $this->badRequest('Menu ID is required');
        $fields = []; $values = [];
        foreach (['name','label','icon','url','route_id','parent_id','menu_type','display_order','domain','is_active'] as $field) {
            if (array_key_exists($field, $data)) { $fields[] = "$field = ?"; $values[] = $data[$field]; }
        }
        if (!$fields) return $this->badRequest('No supported menu fields provided');
        $values[] = $menuId;
        try {
            $this->db->query('UPDATE sidebar_menu_items SET ' . implode(', ', $fields) . ' WHERE id = ?', $values);
            return $this->success(null, 'Sidebar menu updated');
        } catch (Exception $e) { return $this->badRequest('Failed to update sidebar menu: ' . $e->getMessage()); }
    }

    public function deleteSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        $menuId = $id ?? $data['id'] ?? null;
        if (!$menuId) return $this->badRequest('Menu ID is required');
        try {
            $this->db->query('DELETE FROM role_sidebar_menus WHERE menu_item_id = ?', [$menuId]);
            $this->db->query('DELETE FROM sidebar_menu_items WHERE id = ?', [$menuId]);
            return $this->success(null, 'Sidebar menu deleted');
        } catch (Exception $e) { return $this->badRequest('Failed to delete sidebar menu: ' . $e->getMessage()); }
    }

    public function postRoleSidebarAssignments($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        $roleId = $data['role_id'] ?? null; $menuId = $data['menu_item_id'] ?? null;
        if (!$roleId || !$menuId) return $this->badRequest('role_id and menu_item_id are required');
        $this->db->query('INSERT IGNORE INTO role_sidebar_menus (role_id,menu_item_id) VALUES (?,?)', [$roleId,$menuId]);
        return $this->success(null, 'Menu assigned to role');
    }

    public function deleteRoleSidebarAssignments($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        $roleId = $data['role_id'] ?? null; $menuId = $id ?? $data['menu_item_id'] ?? null;
        if (!$roleId || !$menuId) return $this->badRequest('role_id and menu_item_id are required');
        $this->db->query('DELETE FROM role_sidebar_menus WHERE role_id = ? AND menu_item_id = ?', [$roleId,$menuId]);
        return $this->success(null, 'Menu removed from role');
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

    private function formatUptime(): string
    {
        if (!is_readable('/proc/uptime')) {
            return 'unknown';
        }

        $contents = file_get_contents('/proc/uptime');
        $seconds = (int) floor((float) explode(' ', trim((string) $contents))[0]);
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%dd %dh %dm', $days, $hours, $minutes);
    }

    private function getAuditRows(array $actions, ?string $status = null, int $limit = 100): array
    {
        if (!$this->tableExists('audit_logs')) {
            return [];
        }

        $where = [];
        $params = [];
        if (!empty($actions)) {
            $where[] = 'action IN (' . implode(', ', array_fill(0, count($actions), '?')) . ')';
            $params = array_merge($params, $actions);
        }
        if ($status !== null && $this->tableHasColumn('audit_logs', 'status')) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $whereClause = empty($where) ? '1=1' : implode(' AND ', $where);
        $limit = max(1, min($limit, 500));

        $query = "SELECT * FROM audit_logs WHERE $whereClause ORDER BY created_at DESC LIMIT $limit";
        return $this->db->query($query, $params)->fetchAll() ?? [];
    }

    private function fetchRows(string $table, int $limit = 100, string $orderBy = 'id DESC', string $columns = '*'): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $limit = max(1, min($limit, 1000));
        return $this->db->query("SELECT $columns FROM $table ORDER BY $orderBy LIMIT $limit", [])->fetchAll() ?? [];
    }

    private function getSystemState(string $key, $default)
    {
        $state = $this->readSystemState();
        return $state[$key] ?? $default;
    }

    private function getStateRecords(string $key, string $message)
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success(array_values($this->getSystemState($key, [])), $message);
    }

    private function saveStateRecord(string $key, $id, array $data, string $message)
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

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

        return $this->success($records[$recordId], $message);
    }

    private function deleteStateRecord(string $key, $id, string $message)
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        if (!$id) {
            return $this->badRequest('Record ID is required');
        }

        $records = $this->getSystemState($key, []);
        unset($records[(string) $id]);
        $this->writeSystemStateValue($key, $records);

        return $this->success(null, $message);
    }

    private function getStateToggleList(string $key, string $message, array $default = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        return $this->success(array_values($this->getSystemState($key, $default)), $message);
    }

    private function putStateToggle(string $key, $id, array $data, string $message)
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

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

        return $this->success($items[$itemId], $message);
    }

    private function saveSystemStateEndpoint(string $key, array $data, string $message)
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $this->writeSystemStateValue($key, $data);
        return $this->success($data, $message);
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

    private function getSystemStatePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/system_admin_state.json';
    }

    private function getBackupDirectory(): string
    {
        return dirname(__DIR__, 2) . '/storage/backups';
    }

    private function tableExists(string $tableName): bool
    {
        $result = $this->db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            [$tableName]
        );

        return (int) ($result->fetchColumn() ?? 0) > 0;
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
