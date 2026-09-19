<?php
namespace App\API\Includes;

require_once __DIR__ . '/../../vendor/autoload.php';
use App\Config\Config;
Config::init();

use App\Database\Database;
use App\API\Services\PermissionContract;
use App\API\Services\Logger;
use App\API\Includes\FileLogger;
use App\API\Core\FileLifecycleBase;
use PDO;
use PDOStatement;
use RuntimeException;
use Exception;
use finfo;

class BaseAPI extends FileLifecycleBase
{
    /**
     * Standard API response formatter
     * @param array $data
     * @param int $statusCode
     * @return array
     */
    protected function response(array $data, int $statusCode = 200)
    {
        return ApiResponse::normalize($data, $statusCode);
    }

    protected function successResponse($data = null, string $message = 'OK', int $statusCode = 200): array
    {
        return ApiResponse::success($data, $message, $statusCode);
    }

    protected function errorResponse(string $message, int $statusCode = 400, array $errors = []): array
    {
        return ApiResponse::error($message, $statusCode, $errors);
    }
    protected $db;
    protected $user_id;
    protected $module;
    protected $request_id;
    /**
     * Common log directory for all APIs
     * @var string
     */
    protected $logDir;
    /**
     * Common timestamp for log entries
     * @var string
     */
    protected $timestamp;

    public function __construct($module = '')
    {
        // Initialize database connection
        $this->db = Database::getInstance()->getConnection();
        $this->module = $module;
        $this->user_id = $this->getCurrentUserId();
        $this->request_id = uniqid('req_');
        // Canonical log directory (project-root/logs). Keep a single source of truth.
        $this->logDir = dirname(__DIR__, 2) . '/logs';

        // If directory doesn't exist, try to create it but do not let logging failures
        // break the API response flow. Fall back to system temp dir if creation fails.
        if (!is_dir($this->logDir)) {
            // Suppress warnings from mkdir and verify after call.
            $this->ensureManagedDirectory($this->logDir);
            if (!is_dir($this->logDir)) {
                \App\API\Services\Logger::legacyError('BaseAPI: Failed to create log directory: ' . $this->logDir);
                // Use system temp dir as a fallback to avoid throwing and breaking responses
                $this->logDir = sys_get_temp_dir() . '/kingsway_logs';
                $this->ensureManagedDirectory($this->logDir);
                if (!is_dir($this->logDir)) {
                    // As a last resort, use system temp dir without subfolder
                    $this->logDir = sys_get_temp_dir();
                }
            }
        }
        $this->timestamp = date('Y-m-d H:i:s');

        // NOTE: CORS handling moved to CORSMiddleware in the Router pipeline
        // This prevents double-handling and keeps middleware concerns in middleware

        // Request outcomes are logged once by ControllerRouter after the
        // response is known. Constructor-time logging caused duplicate inbound
        // traces whenever controllers composed multiple legacy API services.
    }

    protected function getCurrentUserId()
    {
        // User ID is set by AuthMiddleware in $_SERVER['auth_user']
        // Also check $_REQUEST['user']['id'] for backward compatibility
        if (isset($_SERVER['auth_user']['user_id'])) {
            return $_SERVER['auth_user']['user_id'];
        }
        if (isset($_SERVER['auth_user']['id'])) {
            return $_SERVER['auth_user']['id'];
        }
        return $_REQUEST['user']['id'] ?? null;
    }

    /**
     * Contract-first accessor for the gRPC-style service contract layer.
     * Mirrors BaseController::contract() for controllers that extend BaseAPI
     * instead of BaseController. Controllers MUST obtain governed services
     * through this accessor — never with bare "new X(...)".
     *
     * @param string $contractOrClass governed class FQCN or short alias.
     * @param mixed  ...$args         forwarded to the real constructor.
     * @return object
     */
    protected function contract(string $contractOrClass, ...$args)
    {
        return \App\API\Services\ServiceContractBroker::contract($contractOrClass, [
            'user_id' => (int) ($this->user_id ?? 0),
            'roles' => $this->currentUserRoleNames(),
            'permissions' => $_SERVER['auth_user']['effective_permissions'] ?? [],
            'request_id' => $_SERVER['REQUEST_ID'] ?? $this->request_id,
        ], ...$args);
    }

    /**
     * Role names from the authenticated session (mirrors the BaseController
     * helper that reads $_SERVER['auth_user'] set by AuthMiddleware).
     *
     * @return array Lowercased role names.
     */
    protected function currentUserRoleNames()
    {
        $roles = $_SERVER['auth_user']['roles'] ?? [];
        if (empty($roles)) {
            return [];
        }
        if (is_string($roles[0] ?? null)) {
            return array_map('strtolower', $roles);
        }
        $names = [];
        foreach ($roles as $role) {
            if (is_array($role) && isset($role['name'])) {
                $names[] = strtolower($role['name']);
            } elseif (is_object($role) && isset($role->name)) {
                $names[] = strtolower($role->name);
            }
        }
        return $names;
    }

    protected function logRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $endpoint = $_SERVER['REQUEST_URI'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $params = [];

        // Get request parameters based on method
        if ($method === 'GET') {
            $params = $_GET;
        } elseif (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $params = $_POST;
            if (empty($params)) {
                $input = file_get_contents('php://input');
                if (!empty($input)) {
                    $params = json_decode($input, true) ?? [];
                }
            }
        }

        // Central logging service: every request is traced to a structured
        // log file (category 'http'), never to the database. Values are never
        // persisted: unknown/custom form keys can contain secrets or private
        // records that a name-based redaction list cannot reliably identify.
        Logger::request(sprintf('[%s] %s', $method, $endpoint), [
            'method' => $method,
            'endpoint' => $endpoint,
            'ip' => $ip,
            'module' => $this->module,
            'parameter_keys' => array_slice(array_map('strval', array_keys(is_array($params) ? $params : [])), 0, 100),
            'parameter_count' => is_array($params) ? count($params) : 0,
        ]);
    }

    protected function logAction($action_type, $record_id, $description)
    {
        try {
            Logger::info('events', $description, [
                'type' => 'action',
                'action' => $action_type,
                'module' => $this->module,
                'record_id' => $record_id,
            ]);

            // For audit-worthy actions, also write to the audit log file.
            if (in_array($action_type, ['create', 'update', 'delete'])) {
                $this->logAudit($action_type, $record_id, $description);
            }
        } catch (Exception $e) {
            // Logging must never break the main flow.
            $this->logError($e, 'Failed to log action');
        }
    }

    protected function logError($e, $context = '')
    {
        if ($e instanceof \Throwable || $e instanceof \Exception) {
            $errorData = [
                'module' => $this->module,
                'context' => $context,
                'message' => 'An internal error occurred.',
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ];
        } else {
            $errorData = [
                'module' => $this->module,
                'context' => $context,
                'message' => is_string($e) ? $e : (is_array($e) ? json_encode($e) : (string) $e),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ];
        }

        // Central error logging to the structured 'errors' file only.
        Logger::error('errors', (string) ($errorData['message'] ?? 'An internal error occurred.'), $errorData);
    }

    protected function logAudit($action, $record_id, $description)
    {
        // Central audit log file only, never the database.
        Logger::audit((string) $action, (string) ($this->module ?? 'app'), $record_id, $description, [
            'module' => $this->module,
            'record_id' => $record_id,
        ]);
    }

    /**
     * Compatibility shim for legacy writers that pass a full filename.
     * Routes through the structured FileLogger (category = basename) instead
     * of appending to raw flat files under logs/. Deprecated: new code should
     * use App\API\Services\Logger directly.
     *
     * @deprecated Use App\API\Services\Logger instead.
     */
    protected function logToFile($filename, $data)
    {
        try {
            $category = preg_replace('/\.log$/i', '', basename((string) $filename)) ?: 'app';
            $level = isset($data['level']) ? (string) $data['level'] : 'info';
            if (!in_array($level, ['debug', 'info', 'warning', 'error', 'critical'], true)) {
                $level = 'info';
            }
            if (isset($data['type']) && $data['type'] === 'error') {
                $level = 'error';
            }
            FileLogger::write($category, is_array($data) ? $data : ['data' => $data], $level);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError("Failed to write to log file {$filename}: " . $e->getMessage());
        }
    }

    protected function requireActionPermission(string $module, string $action): void
    {
        $user = $this->getCurrentUser() ?? [];
        if (!PermissionContract::userCan($user, $module, $action)) {
            $userId = (int) ($user['user_id'] ?? $user['id'] ?? 0);
            \App\API\Includes\SecurityEventNotifier::permissionDenied(
                'Forbidden: missing permission for ' . $module . '.' . $action,
                [
                    'entity' => 'permission',
                    'entity_id' => $module . '.' . $action,
                    'details' => [
                        'module' => $module,
                        'action' => $action,
                        'role_ids' => $user['role_ids'] ?? null,
                    ],
                    'user_id' => $userId ?: null,
                    'username' => $user['username'] ?? null,
                ]
            );
            http_response_code(403);
            throw new RuntimeException(
                'Forbidden: missing permission for ' . $module . '.' . $action,
                403
            );
        }
    }

    protected function getAllowedActions(string $module): array
    {
        return PermissionContract::allowedActions($this->getCurrentUser() ?? [], $module);
    }

    protected function auditSensitiveAction(string $action, $recordId, string $description): void
    {
        if (in_array($action, ['create', 'edit', 'update', 'approve', 'reject', 'delete', 'export', 'print'], true)) {
            $this->logAudit($action, $recordId, $description);
        }
    }

    protected function validateRequired($data, $fields)
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    protected function sanitizeInput($data)
    {
        return sanitizeInput($data);
    }

    protected function beginTransaction()
    {
        if ($this->db && $this->db instanceof PDO) {
            return $this->db->beginTransaction();
        }
        throw new Exception('No valid database connection for transaction');
    }

    protected function commit()
    {
        if ($this->db && $this->db instanceof PDO) {
            return $this->db->commit();
        }
        throw new Exception('No valid database connection for commit');
    }

    protected function rollback()
    {
        if ($this->db && $this->db instanceof PDO) {
            return $this->db->rollBack();
        }
        throw new Exception('No valid database connection for rollback');
    }

    /**
     * Run a parameterized query on the raw PDO connection and return the
     * statement. Callers may not pass bindings to PDO::query() directly
     * (its second argument is an int fetch mode), so bindings go through
     * prepare()/execute() here.
     *
     * @param array $params Values to bind (positional placeholders).
     * @return PDOStatement
     */
    protected function runQuery(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch the first column of the first row for a parameterized query.
     */
    protected function queryScalar(string $sql, array $params = [])
    {
        $value = $this->runQuery($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /**
     * Fetch the first row (associative) for a parameterized query.
     */
    protected function queryRow(string $sql, array $params = [])
    {
        $row = $this->runQuery($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    protected function handleException($e)
    {
        if ($this->db && $this->db->inTransaction()) {
            $this->rollback();
        }

        // Log the error with full context
        $this->logError($e, 'Unhandled exception in ' . $this->module);

        // Throw exception so Controller can format the response
        throw $e;
    }

    protected function getPaginationParams()
    {
        $maxPageSize     = defined('MAX_PAGE_SIZE')     ? \MAX_PAGE_SIZE     : 100;
        $defaultPageSize = defined('DEFAULT_PAGE_SIZE') ? \DEFAULT_PAGE_SIZE : 10;

        $page   = isset($_GET['page'])  ? (int) $_GET['page']  : 1;
        $limit  = isset($_GET['limit']) ? min((int) $_GET['limit'], $maxPageSize) : $defaultPageSize;
        $offset = ($page - 1) * $limit;
        return [$page, $limit, $offset];
    }

    protected function getSearchParams()
    {
        $search = isset($_GET['search']) ? $this->sanitizeInput($_GET['search']) : '';
        $sort = isset($_GET['sort']) ? $this->sanitizeInput($_GET['sort']) : 'id';
        $order = isset($_GET['order']) ? strtoupper($this->sanitizeInput($_GET['order'])) : 'ASC';
        $order = in_array($order, ['ASC', 'DESC']) ? $order : 'ASC';

        return [$search, $sort, $order];
    }

    protected function uploadFile($file, $destination, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'])
    {
        return $this->uploadLegacyCompatible(
            $file,
            $destination,
            $allowedTypes
        );
    }


    /**
     * Execute a parameterized query on the raw PDO connection.
     * Use this instead of $this->db->query($sql, $params) which calls PDO::query()
     * (PDO::query() does not accept a params array as its 2nd argument).
     */
    protected function dbQuery(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    // ---------- Stored routine helpers ----------
    protected function routineExists($name, $type = 'PROCEDURE')
    {
        $sql = "SELECT COUNT(*) FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() AND ROUTINE_NAME = ? AND ROUTINE_TYPE = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$name, strtoupper($type)]);
        return (bool) $stmt->fetchColumn();
    }

    protected function callProcedure($name, array $params = [], $expectResult = true)
    {
        $placeholders = implode(',', array_fill(0, count($params), '?'));
        $sql = "CALL {$name}(" . $placeholders . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($params));
        if ($expectResult) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return true;
    }

    protected function callFunction($name, array $params = [])
    {
        $placeholders = implode(',', array_fill(0, count($params), '?'));
        $sql = "SELECT {$name}(" . $placeholders . ") AS value";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['value'] : null;
    }

    protected function emitEvent($eventType, array $data = [])
    {
        try {
            // Write events to a file, never the database
            FileLogger::write('events', [
                'event' => $eventType,
                'data' => $data,
                'module' => $this->module,
                'user_id' => $this->user_id,
            ]);
        } catch (Exception $e) {
            // Swallow errors to avoid breaking main flow
            $this->logError($e, 'emitEvent failed');
        }
    }

    // ---------- RBAC helpers ----------
    protected function getCurrentUserRole()
    {
        // User role is set by AuthMiddleware in $_SERVER['auth_user']
        // Also check $_REQUEST['user']['role'] for backward compatibility
        if (isset($_SERVER['auth_user']['role'])) {
            return $_SERVER['auth_user']['role'];
        }
        if (isset($_SERVER['auth_user']['roles'][0])) {
            return $_SERVER['auth_user']['roles'][0];
        }
        return $_REQUEST['user']['role'] ?? null;
    }

    protected function getCurrentUser()
    {
        // Full user object set by AuthMiddleware in $_SERVER['auth_user']
        // Also check $_REQUEST['user'] for backward compatibility
        if (!empty($_SERVER['auth_user'])) {
            return $_SERVER['auth_user'];
        }
        return $_REQUEST['user'] ?? null;
    }
}
