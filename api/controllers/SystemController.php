<?php
namespace App\API\Controllers;

use App\API\Modules\system\SystemAPI;
use App\API\Modules\system\SystemAdminManager;
use App\API\Modules\system\DashboardRegistryManager;
use App\API\Services\AuthSessionService;
use App\API\Services\IpAccessControlService;
use App\API\Services\SystemAdminAnalyticsService;
use App\API\Services\OperatingModeService;
use App\API\Services\TestDataManagementService;
use App\API\Services\Logger;
use Exception;

class SystemController extends BaseController
{
    private $api;
    private $systemAdminManager;
    private $dashboardRegistryManager;
    private $authSessionService;
    private $ipAccessControlService;
    private $systemAdminAnalytics;

    public function __construct()
    {
        parent::__construct();
        $this->api = new SystemAPI();
        $this->systemAdminManager = new SystemAdminManager();
        $this->dashboardRegistryManager = new DashboardRegistryManager();
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

    // ========================================================================
    // MEDIA (SystemAPI)
    // ========================================================================

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
        return $this->handleApiResponse($result);
    }

    // POST /api/system/media/album
    public function postMediaAlbum($id = null, $data = [], $segments = [])
    {
        $name = $data['name'] ?? '';
        $description = $data['description'] ?? '';
        $coverImage = $data['cover_image'] ?? null;
        $createdBy = $data['created_by'] ?? ($_REQUEST['user']['id'] ?? null);
        $result = $this->api->createAlbum($name, $description, $coverImage, $createdBy);
        return $this->handleApiResponse($result);
    }

    // GET /api/system/media/albums
    public function getMediaAlbums($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listAlbums($data);
        return $this->handleApiResponse($result);
    }

    // GET /api/system/media
    public function getMedia($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listMedia($data);
        return $this->handleApiResponse($result);
    }

    // POST /api/system/media/update
    public function postMediaUpdate($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id;
        $fields = $data['fields'] ?? [];
        $result = $this->api->updateMedia($mediaId, $fields);
        return $this->handleApiResponse($result);
    }

    // POST /api/system/media/delete
    public function postMediaDelete($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id;
        $result = $this->api->deleteMedia($mediaId);
        return $this->handleApiResponse($result);
    }

    // POST /api/system/media/album/delete
    public function postMediaAlbumDelete($id = null, $data = [], $segments = [])
    {
        $albumId = $data['album_id'] ?? $id;
        $result = $this->api->deleteAlbum($albumId);
        return $this->handleApiResponse($result);
    }

    // GET /api/system/media/preview
    public function getMediaPreview($id = null, $data = [], $segments = [])
    {
        $mediaId = $data['media_id'] ?? $id;
        $result = $this->api->getMediaPreviewUrl($mediaId);
        return $this->handleApiResponse($result);
    }

    // GET /api/system/media/can-access
    public function getMediaCanAccess($id = null, $data = [], $segments = [])
    {
        $userId = $data['user_id'] ?? ($_REQUEST['user']['id'] ?? null);
        $mediaId = $data['media_id'] ?? $id;
        $action = $data['action'] ?? 'view';
        $result = $this->api->canAccessMedia($userId, $mediaId, $action);
        return $this->handleApiResponse($result);
    }

    // GET /api/system/logs
    public function getLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $result = $this->api->readLogs($data);
        return $this->handleApiResponse($result);
    }

    // POST /api/system/logs/clear
    public function postLogsClear($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $result = $this->api->clearLogs();
        return $this->handleApiResponse($result);
    }

    // POST /api/system/logs/archive
    public function postLogsArchive($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $result = $this->api->archiveLogs();
        return $this->handleApiResponse($result);
    }

    // GET /api/system/school-config
    public function getSchoolConfig($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $result = $this->api->getSchoolConfig($id);
        return $this->handleApiResponse($result);
    }

    // POST /api/system/school-config
    public function postSchoolConfig($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $result = $this->api->setSchoolConfig($data);
        return $this->handleApiResponse($result);
    }

    // GET /api/system/health
    public function getHealth($id = null, $data = [], $segments = [])
    {
        $result = $this->api->healthCheck();
        return $this->handleApiResponse($result);
    }

    // ========================================================================
    // AUDIT & APPROVALS
    // ========================================================================

    // GET /api/system/activity-audit-logs
    public function getActivityAuditLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        $filters = array_merge($_GET, $data ?? []);
        return $this->handleApiResponse($this->systemAdminManager->getActivityAuditLogs($filters));
    }

    // GET /api/system/pending-approvals
    public function getPendingApprovals($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureDirectorOrSchoolAdminAccess()) {
            return $auth;
        }

        try {
            $userId = $this->getUserId();
            if (!$userId) {
                return $this->badRequest('Authentication required - please log in again');
            }
            return $this->handleApiResponse($this->systemAdminManager->getPendingApprovals($userId));
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // GET /api/system/background-jobs
    public function getBackgroundJobs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getBackgroundJobs());
    }

    public function getJobInspector($id = null, $data = [], $segments = [])
    {
        return $this->getBackgroundJobs($id, $data, $segments);
    }

    // GET /api/system/security-incidents
    public function getSecurityIncidents($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->success($this->systemAdminManager->getAuditRows([
            'security_incident', 'permission_denied', 'unauthorized_access', 'failed_login', 'login_failed'
        ], null, 200), 'Security incidents retrieved');
    }

    // GET /api/system/policy-violations
    public function getPolicyViolations($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->success($this->systemAdminManager->getAuditRows([
            'policy_violation', 'permission_denied', 'rbac_denied', 'access_denied'
        ], null, 200), 'Policy violations retrieved');
    }

    // GET /api/system/permission-changes
    public function getPermissionChanges($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->success($this->systemAdminManager->getAuditRows([
            'permission_create', 'permission_update', 'permission_delete',
            'permission_definition_create', 'permission_definition_update', 'permission_definition_delete',
            'role_permission_assign', 'role_permission_remove',
            'role_create', 'role_update', 'role_delete',
        ], null, 200), 'Permission changes retrieved');
    }

    // ========================================================================
    // AUTH / SESSIONS / ANALYTICS SERVICES
    // ========================================================================

    // GET /api/system/auth-events
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // GET /api/system/active-sessions
    public function getActiveSessions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            return $this->success(
                $this->systemAdminAnalytics->getActiveSessions(
                    $filters,
                    isset($_SERVER['auth_session_id']) ? (int) $_SERVER['auth_session_id'] : null
                ),
                'Active sessions retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Active session retrieval failed: ' . $e->getMessage());
            return $this->serverError('Failed to retrieve active sessions');
        }
    }

    // POST /api/system/active-sessions-revoke
    public function postActiveSessionsRevoke($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $sessionId = filter_var($data['session_id'] ?? $id, FILTER_VALIDATE_INT);
        if ($sessionId === false || $sessionId <= 0) {
            return $this->badRequest('A valid session ID is required');
        }

        try {
            $result = $this->authSessionService->revokeByAdministrator(
                (int) $sessionId,
                (int) $this->getUserId(),
                isset($_SERVER['auth_session_id']) ? (int) $_SERVER['auth_session_id'] : null
            );
            return $this->success($result, 'Session revoked');
        } catch (\DomainException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->conflict('An internal error occurred.');
        } catch (\OutOfBoundsException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->notFound('An internal error occurred.');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Active session revocation failed: ' . $e->getMessage());
            return $this->serverError('The active session could not be revoked');
        }
    }

    // GET /api/system/uptime
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // GET /api/system/health-errors
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // GET /api/system/health-warnings
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // GET /api/system/api-load
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->serverError('An internal error occurred.');
        }
    }

    // GET /api/system/error-logs
    public function getErrorLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $limit = min((int) ($_GET['limit'] ?? 200), 500);
        $limit = max(1, $limit);

        $rows = [];
        $index = 0;
        foreach (\App\API\Includes\FileLogger::recent('errors', $limit) as $entry) {
            $index++;
            $file = (string) ($entry['file'] ?? '');
            $line = isset($entry['line']) ? (int) $entry['line'] : 0;
            $rows[] = [
                'id' => $index,
                'level' => $entry['level'] ?? 'error',
                'message' => (string) ($entry['message'] ?? ''),
                'file' => $file !== '' ? ($file . ($line > 0 ? ':' . $line : '')) : '',
                'created_at' => $entry['timestamp'] ?? null,
            ];
        }

        return $this->success($rows, 'Error logs retrieved');
    }

    // GET /api/system/api-metrics
    public function getApiMetrics($id = null, $data = [], $segments = [])
    {
        return $this->getAPILoad($id, $data, $segments);
    }

    // GET /api/system/diagnostics
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

    // GET /api/system/rate-limiting
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

    // GET /api/system/authentication-logs
    public function getAuthenticationLogs($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            return $this->success(
                $this->systemAdminAnalytics->getAuthenticationLogs($filters),
                'Authentication logs retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Authentication log retrieval failed: ' . $e->getMessage());
            return $this->serverError('Failed to retrieve authentication logs');
        }
    }

    // GET /api/system/log-categories
    public function getLogCategories($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        try {
            return $this->success([
                'categories' => Logger::categories(),
                'environment' => \App\API\Includes\FileLogger::environment(),
                'policy' => \App\API\Includes\FileLogger::policy(),
            ], 'Log categories retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[SystemController] Log category retrieval failed: ' . $e->getMessage());
            return $this->serverError('Failed to retrieve log categories');
        }
    }

    // GET /api/system/log-file — download one governed journal by safe basename.
    public function getLogFile($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        $requested = basename((string) ($_GET['file'] ?? $data['file'] ?? ''));
        if ($requested === '') return $this->badRequest('A log filename is required.');
        foreach (\App\API\Includes\FileLogger::journalPaths('', true) as $path) {
            if (!hash_equals(basename($path), $requested)) continue;
            $content = @file_get_contents($path);
            if (!is_string($content)) return $this->serverError('The log file could not be read.');
            Logger::audit('system_log_file_downloaded', 'system_logs', null, 'Governed log journal downloaded', ['file' => $requested, 'bytes' => strlen($content)]);
            return $this->success(['filename' => $requested, 'mime_type' => str_ends_with($requested, '.gz') ? 'application/gzip' : 'application/x-ndjson', 'content_base64' => base64_encode($content)], 'Log file prepared');
        }
        return $this->notFound('Log file not found');
    }

    // POST /api/system/log-archive-category — safe retention, never deletion.
    public function postLogArchiveCategory($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        $category = preg_replace('/[^a-z0-9_-]/i', '', (string) ($data['category'] ?? ''));
        if ($category === '') return $this->badRequest('A valid journal category is required.');
        $known = array_column(Logger::categories(), 'category');
        if (!in_array($category, $known, true)) return $this->notFound('Log category not found');
        $archived = \App\API\Includes\FileLogger::archiveNow($category);
        Logger::audit('system_log_archived', 'system_logs', $category, 'System log journal manually archived', ['archived' => $archived]);
        return $this->success(['category' => $category, 'archived' => $archived], $archived ? 'Journal archived' : 'Journal was already empty');
    }

    // GET /api/system/log-viewer
    public function getLogViewer($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            // Whitelist the allowed filter keys so clients only shape the query
            // within the governed set.
            $allowed = ['category', 'level', 'search', 'tag', 'user_id', 'date_from', 'date_to', 'page', 'limit', 'order', 'since', 'include_analytics', 'include_archives', 'include_regex', 'exclude_regex'];
            $filters = array_intersect_key($filters, array_flip($allowed));
            $result = Logger::read($filters);
            $result['monitoring'] = Logger::activePresence();
            $includeAnalytics = !isset($filters['include_analytics']) || filter_var($filters['include_analytics'], FILTER_VALIDATE_BOOLEAN);
            if ($includeAnalytics) $result['analytics'] = Logger::analytics($filters);
            $userIds = [];
            foreach ([$result['entries'] ?? [], $result['monitoring']['sessions'] ?? [], $result['analytics']['users'] ?? []] as $rows) {
                foreach ($rows as $row) {
                    $userId = (int) ($row['user_id'] ?? 0);
                    if ($userId > 0) $userIds[$userId] = $userId;
                }
            }
            $userIds = array_values($userIds);
            if ($userIds) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $stmt = $this->db->getConnection()->prepare("SELECT u.id,u.username,CONCAT_WS(' ',p.first_name,p.last_name) AS full_name FROM users u LEFT JOIN persons p ON p.id=u.person_id WHERE u.id IN ($placeholders)");
                $stmt->execute($userIds);
                $names = [];
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) $names[(int) $row['id']] = ['username' => $row['username'], 'full_name' => $row['full_name']];
                if (isset($result['analytics']['users'])) {
                    foreach ($result['analytics']['users'] as &$row) $row = array_merge($row, $names[(int) $row['user_id']] ?? []);
                    unset($row);
                }
                foreach ($result['monitoring']['sessions'] as &$row) $row = array_merge($row, $names[(int) ($row['user_id'] ?? 0)] ?? []);
                unset($row);
                foreach ($result['entries'] as &$row) {
                    $identity = $names[(int) ($row['user_id'] ?? 0)] ?? [];
                    if ($identity) $row['user'] = trim((string) ($identity['full_name'] ?: $identity['username']));
                }
                unset($row);
            }
            return $this->success($result, 'Log entries retrieved');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Log viewer retrieval failed: ' . $e->getMessage());
            return $this->serverError('Failed to retrieve log entries');
        }
    }

    // GET /api/system/log-export — redacted, bounded audit evidence export.
    public function getLogExport($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        try {
            $input = array_merge($_GET, is_array($data) ? $data : []);
            $allowed = ['category', 'level', 'search', 'tag', 'user_id', 'date_from', 'date_to', 'order', 'include_archives', 'include_regex', 'exclude_regex'];
            $filters = array_intersect_key($input, array_flip($allowed));
            $format = strtolower((string) ($input['format'] ?? 'csv'));
            if (!in_array($format, ['csv', 'pdf'], true)) return $this->badRequest('Export format must be csv or pdf.');
            $result = Logger::exportEntries($filters, $format === 'pdf' ? 2000 : 10000);
            $entries = $result['entries'] ?? [];
            $stamp = date('Ymd-His');

            if ($format === 'csv') {
                $stream = fopen('php://temp', 'w+');
                fputcsv($stream, ['timestamp', 'level', 'category', 'user_id', 'user', 'ip', 'method', 'route', 'status', 'action/type/event', 'message', 'request_id', 'archive']);
                foreach ($entries as $row) {
                    fputcsv($stream, [
                        $row['timestamp'] ?? '', $row['level'] ?? '', $row['_category'] ?? '',
                        $row['user_id'] ?? '', $row['user'] ?? '', $row['ip'] ?? '',
                        $row['method'] ?? '', $row['route'] ?? '', $row['status'] ?? '',
                        $row['action'] ?? $row['type'] ?? $row['event'] ?? '', $row['message'] ?? '',
                        $row['request_id'] ?? '', !empty($row['_archive']) ? 'yes' : 'no',
                    ]);
                }
                rewind($stream); $content = stream_get_contents($stream); fclose($stream);
                $mime = 'text/csv';
            } else {
                $escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $rows = '';
                foreach ($entries as $row) {
                    $label = $row['action'] ?? $row['type'] ?? $row['event'] ?? $row['message'] ?? '';
                    $rows .= '<tr><td>' . $escape($row['timestamp'] ?? '') . '</td><td>' . $escape($row['level'] ?? '') . '</td><td>' . $escape($row['_category'] ?? '') . '</td><td>' . $escape($row['user_id'] ?? '') . '</td><td>' . $escape($row['route'] ?? '') . '</td><td>' . $escape($label) . '</td></tr>';
                }
                $html = '<style>body{font-family:DejaVu Sans;color:#173528;font-size:9px}h1{color:#075f35}table{width:100%;border-collapse:collapse}th{background:#075f35;color:#fff}th,td{padding:5px;border:1px solid #ddd;text-align:left}small{color:#666}</style><h1>Kingsway System Audit Report</h1><small>Generated ' . $escape(date('c')) . ' · Environment: ' . $escape(\App\API\Includes\FileLogger::environment()) . ' · Filtered records: ' . count($entries) . '</small><table><thead><tr><th>Time</th><th>Level</th><th>Category</th><th>User</th><th>Route</th><th>Activity</th></tr></thead><tbody>' . $rows . '</tbody></table>';
                $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
                $pdf->loadHtml($html); $pdf->setPaper('A4', 'landscape'); $pdf->render();
                $content = $pdf->output(); $mime = 'application/pdf';
            }
            Logger::audit('system_log_exported', 'system_logs', null, 'System audit report exported', ['format' => $format, 'records' => count($entries), 'filters' => $filters]);
            return $this->success([
                'filename' => "kingsway-audit-{$stamp}.{$format}", 'mime_type' => $mime,
                'content_base64' => base64_encode($content), 'exported' => count($entries),
                'matching_total' => (int) ($result['total'] ?? count($entries)),
                'truncated' => (int) ($result['total'] ?? 0) > count($entries),
            ], 'Audit report generated');
        } catch (\Throwable $e) {
            Logger::error('errors', 'Audit report export failed', ['exception_class' => get_class($e), 'error' => $e->getMessage()]);
            return $this->serverError('Failed to generate audit report');
        }
    }

    // POST /api/system/client-log
    // Frontend browser telemetry ingest. Any authenticated user may push client
    // logs; only allowlisted fields are persisted to the central `client` log
    // file, correlated with the ambient request/session trace.
    public function postClientLog($id = null, $data = [], $segments = [])
    {
        if (!is_array($data) || empty($data)) {
            return $this->handleApiResponse([
                'success' => true, 'message' => 'No client events received.',
            ]);
        }

        $events = isset($data['events']) && is_array($data['events']) ? $data['events'] : [$data];
        if (count($events) > 25) {
            $events = array_slice($events, 0, 25);
        }

        $allowedLevels = ['debug', 'info', 'success', 'warning', 'error', 'critical', 'audit'];
        $written = 0;

        foreach ($events as $event) {
            if (!is_array($event) || empty($event['message'])) {
                continue;
            }
            $level = strtolower((string) ($event['level'] ?? 'info'));
            if (!in_array($level, $allowedLevels, true)) {
                $level = 'info';
            }
            $category = preg_replace('/[^a-z0-9_-]/i', '_', (string) ($event['category'] ?? 'app')) ?: 'app';
            $message = Logger::redactText(mb_substr((string) $event['message'], 0, 1200));

            $context = [];
            if (!empty($event['context']) && is_string($event['context']) && strlen($event['context']) <= 5000) {
                $decoded = json_decode($event['context'], true);
                if (is_array($decoded)) {
                    // Shallow-redact sensitive keys before persisting.
                    $decoded = Logger::redactFields($decoded);
                    $context = $decoded;
                }
            }
            foreach (['action', 'entity', 'entity_id'] as $k) {
                if (isset($event[$k]) && $event[$k] !== '') {
                    $context[$k] = (string) $event[$k];
                }
            }
            if (!empty($event['route'])) {
                $context['client_route'] = mb_substr((string) $event['route'], 0, 300);
            }

            Logger::log($category === 'audit' ? 'audit' : 'client', $level, $message, $context);
            $written++;
        }

        return $this->handleApiResponse([
            'success' => true,
            'message' => "$written client event(s) recorded.",
            'written' => $written,
        ]);
    }

    // GET /api/system/failed-login-attempts
    public function getFailedLoginAttempts($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            return $this->success(
                $this->systemAdminAnalytics->getFailedLoginAttempts($filters),
                'Failed login attempts retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Failed login attempt retrieval failed: ' . $e->getMessage());
            return $this->serverError('Failed to retrieve failed login attempts');
        }
    }

    // ========================================================================
    // SYSTEM STATE (feature flags, maintenance mode, retention, etc.)
    // ========================================================================

    // GET /api/system/data-retention
    public function getDataRetention($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->success($this->systemAdminManager->getSystemState('data_retention', [
            'status' => 'active',
            'audit_log_days' => 365,
            'auth_event_days' => 180,
            'backup_days' => 30,
        ]), 'Data retention settings retrieved');
    }

    // PUT /api/system/data-retention
    public function putDataRetention($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->saveSystemStateEndpoint('data_retention', $data, 'Data retention settings updated'));
    }

    // GET /api/system/feature-flags
    public function getFeatureFlags($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getStateToggleList('feature_flags', 'Feature flags retrieved'));
    }

    // GET /api/system/operating-mode
    public function getOperatingMode($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        return $this->success(
            (new OperatingModeService($this->db->getConnection()))->current(),
            'Operating mode retrieved'
        );
    }

    // PUT /api/system/operating-mode
    public function putOperatingMode($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        try {
            $actorId = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
            $result = (new OperatingModeService($this->db->getConnection()))->change(
                (string) ($data['mode'] ?? ''),
                $actorId,
                (string) ($data['reason'] ?? ''),
                (string) ($data['confirmation'] ?? '')
            );
            return $this->success($result, 'Production operating mode updated');
        } catch (\DomainException|\InvalidArgumentException $error) {
            return $this->badRequest($error->getMessage());
        } catch (\Throwable $error) {
            Logger::legacyError('Operating mode update failed: ' . $error->getMessage());
            return $this->serverError('Operating mode could not be updated');
        }
    }

    // GET /api/system/test-data
    public function getTestData($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        return $this->success(
            (new TestDataManagementService($this->db->getConnection()))->inventory(),
            'Test-data inventory retrieved'
        );
    }

    // DELETE /api/system/test-data
    public function deleteTestData($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) return $auth;
        try {
            $actorId = (int) ($this->user['user_id'] ?? $this->user['id'] ?? 0);
            $result = (new TestDataManagementService($this->db->getConnection()))->purgeAll(
                $actorId,
                (string) ($data['confirmation'] ?? ''),
                (string) ($data['reason'] ?? '')
            );
            return $this->success($result, 'Test accounts and classified test data were permanently deleted');
        } catch (\DomainException|\InvalidArgumentException $error) {
            return $this->badRequest($error->getMessage());
        } catch (\Throwable $error) {
            Logger::legacyError('Test-realm purge failed and was rolled back: ' . $error->getMessage());
            return $this->serverError('Test data could not be deleted; no partial purge was retained');
        }
    }

    // PUT /api/system/feature-flags
    public function putFeatureFlags($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->putStateToggle('feature_flags', $id, $data, 'Feature flag updated'));
    }

    // GET /api/system/maintenance-mode
    public function getMaintenanceMode($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getStateToggleList('maintenance_mode', 'Maintenance mode settings retrieved', [
            ['id' => 'maintenance_mode', 'key' => 'maintenance_mode', 'name' => 'Maintenance Mode', 'description' => 'Temporarily restrict application access', 'enabled' => false]
        ]));
    }

    // PUT /api/system/maintenance-mode
    public function putMaintenanceMode($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->putStateToggle('maintenance_mode', $id, $data, 'Maintenance mode updated'));
    }

    // GET /api/system/domain-isolation
    public function getDomainIsolation($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getStateToggleList('domain_isolation', 'Domain isolation settings retrieved'));
    }

    // PUT /api/system/domain-isolation
    public function putDomainIsolation($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->putStateToggle('domain_isolation', $id, $data, 'Domain isolation setting updated'));
    }

    // GET /api/system/time-bound-access
    public function getTimeBoundAccess($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getStateToggleList('time_bound_access', 'Time-bound access settings retrieved'));
    }

    // PUT /api/system/time-bound-access
    public function putTimeBoundAccess($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->putStateToggle('time_bound_access', $id, $data, 'Time-bound access setting updated'));
    }

    // GET /api/system/permission-policies
    public function getPermissionPolicies($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getStateRecords('permission_policies', 'Permission policies retrieved'));
    }

    public function postPermissionPolicies($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->saveStateRecord('permission_policies', null, $data, 'Permission policy created'));
    }

    public function putPermissionPolicies($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->saveStateRecord('permission_policies', $id, $data, 'Permission policy updated'));
    }

    public function deletePermissionPolicies($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->deleteStateRecord('permission_policies', $id ?? $data['id'] ?? null, 'Permission policy deleted'));
    }

    // GET /api/system/webhook-registry
    public function getWebhookRegistry($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getStateRecords('webhook_registry', 'Webhook registry retrieved'));
    }

    public function postWebhookRegistry($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->saveStateRecord('webhook_registry', null, $data, 'Webhook created'));
    }

    public function putWebhookRegistry($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->saveStateRecord('webhook_registry', $id, $data, 'Webhook updated'));
    }

    public function deleteWebhookRegistry($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->deleteStateRecord('webhook_registry', $id ?? $data['id'] ?? null, 'Webhook deleted'));
    }

    // GET /api/system/role-navigation
    public function getRoleNavigation($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getSidebarMenus());
    }

    // ========================================================================
    // BACKUPS / MIGRATIONS
    // ========================================================================

    // GET /api/system/backups
    public function getBackups($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getBackups());
    }

    // POST /api/system/backups
    public function postBackups($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->createBackup());
    }

    // DELETE /api/system/backups/{id}
    public function deleteBackups($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->deleteBackup($id ?? $data['id'] ?? ''));
    }

    // GET /api/system/migrations
    public function getMigrations($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getMigrations());
    }

    // POST /api/system/migrations
    public function postMigrations($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->recordMigrationRequest($data));
    }

    // ========================================================================
    // IP LISTS / TOKENS (services)
    // ========================================================================

    // GET /api/system/ip-lists
    public function getIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            return $this->success(
                $this->ipAccessControlService->getRegistry($filters, IpAccessControlService::resolveClientIp()),
                'IP access rules retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('IP rule registry retrieval failed: ' . $e->getMessage());
            return $this->serverError('IP access rules could not be retrieved');
        }
    }

    // POST /api/system/ip-lists
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->conflict('An internal error occurred.');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('IP rule creation failed: ' . $e->getMessage());
            return $this->serverError('The IP access rule could not be created');
        }
    }

    // PUT /api/system/ip-lists
    public function putIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $ruleId = filter_var($id ?? $data['id'] ?? null, FILTER_VALIDATE_INT);
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->conflict('An internal error occurred.');
        } catch (\OutOfBoundsException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->notFound('An internal error occurred.');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('IP rule update failed: ' . $e->getMessage());
            return $this->serverError('The IP access rule could not be updated');
        }
    }

    // DELETE /api/system/ip-lists
    public function deleteIpLists($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $ruleId = filter_var($id ?? $data['id'] ?? null, FILTER_VALIDATE_INT);
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
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->conflict('An internal error occurred.');
        } catch (\OutOfBoundsException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->notFound('An internal error occurred.');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('IP rule deletion failed: ' . $e->getMessage());
            return $this->serverError('The IP access rule could not be deleted');
        }
    }

    // GET /api/system/tokens
    public function getTokens($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        try {
            $filters = array_merge($_GET, is_array($data) ? $data : []);
            return $this->success(
                $this->authSessionService->getTokenRegistry(
                    $filters,
                    isset($_SERVER['auth_session_id']) ? (int) $_SERVER['auth_session_id'] : null
                ),
                'Tokens retrieved'
            );
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Token registry retrieval failed: ' . $e->getMessage());
            return $this->serverError('Token records could not be retrieved');
        }
    }

    // POST /api/system/tokens-revoke
    public function postTokensRevoke($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $tokenId = filter_var($data['token_id'] ?? $id, FILTER_VALIDATE_INT);
        $tokenType = trim((string) ($data['token_type'] ?? ''));
        if ($tokenId === false || $tokenId <= 0 || $tokenType === '') {
            return $this->badRequest('A valid token ID and token type are required');
        }

        try {
            $result = $this->authSessionService->revokeTokenByAdministrator(
                (int) $tokenId,
                $tokenType,
                (int) $this->getUserId(),
                isset($_SERVER['auth_session_id']) ? (int) $_SERVER['auth_session_id'] : null
            );
            return $this->success($result, 'Token revoked');
        } catch (\DomainException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->conflict('An internal error occurred.');
        } catch (\OutOfBoundsException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->notFound('An internal error occurred.');
        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[SystemController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->badRequest('An internal error occurred.');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('Token revocation failed: ' . $e->getMessage());
            return $this->serverError('The token could not be revoked');
        }
    }

    // ========================================================================
    // RESOURCE PERMISSIONS / ROLE PERMISSION MATRIX
    // ========================================================================

    // GET /api/system/resource-permissions
    public function getResourcePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $filters = array_merge($_GET, $data ?? []);
        return $this->handleApiResponse($this->systemAdminManager->getResourcePermissions($filters));
    }

    // GET /api/system/role-permission-matrix
    public function getRolePermissionMatrix($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getRolePermissionMatrix());
    }

    // ========================================================================
    // ACCOUNT STATUS
    // ========================================================================

    // GET /api/system/account-status
    public function getAccountStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getAccountStatus());
    }

    // PUT /api/system/account-status/{id}
    public function putAccountStatus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }

        $userId = $id ?? $data['user_id'] ?? $data['id'] ?? null;
        if (!$userId) {
            return $this->badRequest('User ID is required');
        }
        return $this->handleApiResponse($this->systemAdminManager->updateAccountStatus($userId, $data, $this->getUserId()));
    }

    // ========================================================================
    // ROUTES (routes_registry)
    // ========================================================================

    // GET /api/system/routes
    public function getRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getRoutes($id));
    }

    // POST /api/system/routes
    public function postRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->createRoute($data));
    }

    // PUT /api/system/routes
    public function putRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->updateRoute($id ?? $data['id'] ?? null, $data));
    }

    // DELETE /api/system/routes
    public function deleteRoutes($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->deleteRoute($id ?? $data['id'] ?? null));
    }

    // POST /api/system/routes-toggle
    public function postRoutesToggle($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->toggleRoute($id ?? $data['id'] ?? null, $data['is_active'] ?? null));
    }

    // Route access rules aliases
    public function getRouteAccessRules($id = null, $data = [], $segments = [])
    {
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

    // ========================================================================
    // ROLES
    // ========================================================================

    // GET /api/system/roles[/id]
    public function getRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }
        $roleId = $id ?? $data['id'] ?? $_GET['id'] ?? null;
        return $this->handleApiResponse($this->systemAdminManager->getRoles($roleId, $this->isSchoolAdmin()));
    }

    // POST /api/system/roles
    public function postRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->createRole($data, $this->getUserId(), $this->isSystemAdmin()));
    }

    // PUT /api/system/roles[/id]
    public function putRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->updateRole(
            $id ?? $data['id'] ?? null,
            $data,
            $this->getUserId(),
            $this->isSystemAdmin(),
            $this->isSchoolAdmin()
        ));
    }

    // DELETE /api/system/roles/{id}
    public function deleteRoles($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }
        $roleId = $id ?? $data['id'] ?? $_GET['id'] ?? null;
        return $this->handleApiResponse($this->systemAdminManager->deleteRole($roleId, $this->getUserId(), $this->isSchoolAdmin()));
    }

    // POST /api/system/roles-toggle
    public function postRolesToggle($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }
        $roleId = $id ?? $data['id'] ?? null;
        $isActive = $data['is_active'] ?? $data['enabled'] ?? null;
        return $this->handleApiResponse($this->systemAdminManager->toggleRole($roleId, $isActive, $this->getUserId(), $this->isSchoolAdmin()));
    }

    // ========================================================================
    // PERMISSIONS
    // ========================================================================

    // GET /api/system/permissions
    public function getPermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getPermissions());
    }

    // POST /api/system/permissions
    public function postPermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->createPermission($data, $this->getUserId()));
    }

    // PUT /api/system/permissions
    public function putPermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->updatePermission($id ?? $data['id'] ?? null, $data, $this->getUserId()));
    }

    // DELETE /api/system/permissions
    public function deletePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->deletePermission($id ?? $data['id'] ?? null, $this->getUserId()));
    }

    // ========================================================================
    // ROLE PERMISSIONS
    // ========================================================================

    // GET /api/system/role-permissions
    public function getRolePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess()) {
            return $auth;
        }
        $roleId = $id ?? $data['role_id'] ?? $_GET['role_id'] ?? null;
        return $this->handleApiResponse($this->systemAdminManager->getRolePermissions($roleId, $this->isSchoolAdmin()));
    }

    // POST /api/system/role-permissions
    public function postRolePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }
        $roleId = $id ?? $data['role_id'] ?? null;
        $permissionIds = $data['permission_ids'] ?? [];
        return $this->handleApiResponse($this->systemAdminManager->assignRolePermissions($roleId, $permissionIds, $this->getUserId(), $this->isSchoolAdmin()));
    }

    // DELETE /api/system/role-permissions
    public function deleteRolePermissions($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureRoleManagementAccess(true)) {
            return $auth;
        }
        $roleId = $data['role_id'] ?? $_GET['role_id'] ?? null;
        $permissionId = $id ?? $data['permission_id'] ?? $_GET['permission_id'] ?? null;
        return $this->handleApiResponse($this->systemAdminManager->removeRolePermission($roleId, $permissionId, $this->getUserId(), $this->isSchoolAdmin()));
    }

    // ========================================================================
    // DASHBOARD & WIDGET REGISTRY (System Admin)
    // ========================================================================

    // GET /api/system/dashboards
    public function getDashboards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $params = $_GET;
        return $this->handleApiResponse($this->dashboardRegistryManager->listDashboards($params));
    }

    // POST /api/system/dashboards
    public function postDashboards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->dashboardRegistryManager->createDashboard($data, $this->getUserId()));
    }

    // PUT /api/system/dashboards[/id]
    public function putDashboards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->dashboardRegistryManager->updateDashboard($id, $data));
    }

    // DELETE /api/system/dashboards/{id}
    public function deleteDashboards($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $dashboardId = $id ?? $data['id'] ?? $_GET['id'] ?? null;
        return $this->handleApiResponse($this->dashboardRegistryManager->deleteDashboard($dashboardId));
    }

    // GET /api/system/widgets
    public function getWidgets($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $params = $_GET;
        return $this->handleApiResponse($this->dashboardRegistryManager->listWidgets($params));
    }

    // POST /api/system/widgets
    public function postWidgets($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->dashboardRegistryManager->createWidget($data, $this->getUserId()));
    }

    // PUT /api/system/widgets[/id]
    public function putWidgets($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->dashboardRegistryManager->updateWidget($id, $data));
    }

    // DELETE /api/system/widgets/{id}
    public function deleteWidgets($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $widgetId = $id ?? $data['id'] ?? $_GET['id'] ?? null;
        return $this->handleApiResponse($this->dashboardRegistryManager->deleteWidget($widgetId));
    }

    // ========================================================================
    // SIDEBAR MENUS
    // ========================================================================

    // GET /api/system/sidebar-menus
    public function getSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getSidebarMenus());
    }

    public function getMenus($id = null, $data = [], $segments = [])
    {
        return $this->getSidebarMenus($id, $data, $segments);
    }

    // GET /api/system/role-sidebar-assignments
    public function getRoleSidebarAssignments($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        $roleId = $id ?? $data['role_id'] ?? $_GET['role_id'] ?? null;
        return $this->handleApiResponse($this->systemAdminManager->getRoleSidebarAssignments($roleId));
    }

    // POST /api/system/sidebar-menus
    public function postSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->createSidebarMenu($data));
    }

    // PUT /api/system/sidebar-menus
    public function putSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->updateSidebarMenu($id ?? $data['id'] ?? null, $data));
    }

    // DELETE /api/system/sidebar-menus
    public function deleteSidebarMenus($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->deleteSidebarMenu($id ?? $data['id'] ?? null));
    }

    // POST /api/system/role-sidebar-assignments
    public function postRoleSidebarAssignments($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->assignRoleSidebarMenu(
            $data['role_id'] ?? null,
            $data['menu_item_id'] ?? null
        ));
    }

    // DELETE /api/system/role-sidebar-assignments
    public function deleteRoleSidebarAssignments($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->removeRoleSidebarMenu(
            $data['role_id'] ?? null,
            $id ?? $data['menu_item_id'] ?? null
        ));
    }

    // ========================================================================
    // MODULES / MODULE ENABLEMENT
    // ========================================================================

    // GET /api/system/modules
    public function getModules($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemOrDirectorAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getModules());
    }

    // PUT /api/system/modules/{id}
    public function putModules($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->toggleModule(
            $id ?? $data['id'] ?? null,
            $data['enabled'] ?? $data['is_active'] ?? null
        ));
    }

    // GET /api/system/module-enablement
    public function getModuleEnablement($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->getModuleEnablement());
    }

    // PUT /api/system/module-enablement/{id}
    public function putModuleEnablement($id = null, $data = [], $segments = [])
    {
        if ($auth = $this->ensureSystemAdminAccess()) {
            return $auth;
        }
        return $this->handleApiResponse($this->systemAdminManager->toggleModuleEnablement(
            $id ?? $data['id'] ?? null,
            $data['enabled'] ?? $data['is_active'] ?? null
        ));
    }

    // ========================================================================
    // AUTHORIZATION HELPERS
    // ========================================================================

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
            ? ['system.rbac.manage', 'system_roles_create', 'system_roles_edit', 'system_roles_delete']
            : ['system.rbac.view', 'system.rbac.manage', 'system_roles_view'];

        if ($this->userHasAny($permissions)) {
            return null;
        }

        return $this->forbidden('Access denied');
    }

    /**
     * Allow System Admin, Director, or any user with wildcard permission.
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
}
