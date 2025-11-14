<?php
namespace App\API\Includes;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/helpers.php';

use App\Config\Database;
use PDO;
use RuntimeException;
use Exception;
use finfo;

class BaseAPI
{
    protected $db;
    protected $user_id;
    protected $module;
    protected $request_id;

    public function __construct($module = '')
    {
        // Initialize database connection
        $this->db = Database::getInstance()->getConnection();
        $this->module = $module;
        $this->user_id = $this->getCurrentUserId();
        $this->request_id = uniqid('req_');

        // NOTE: CORS handling moved to CORSMiddleware in the Router pipeline
        // This prevents double-handling and keeps middleware concerns in middleware

        // Log API request
        $this->logRequest();
    }

    protected function getCurrentUserId()
    {
        // User ID is now set by AuthMiddleware in $_REQUEST['user']['id']
        return $_REQUEST['user']['id'] ?? null;
    }

    protected function logRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $endpoint = $_SERVER['REQUEST_URI'];
        $ip = $_SERVER['REMOTE_ADDR'];
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

        // Remove sensitive data
        unset($params['password'], $params['token']);

        // Log request to system activity log
        $message = sprintf(
            'API Request: [%s] %s from IP %s with params %s',
            $method,
            $endpoint,
            $ip,
            json_encode($params)
        );

        $this->logToFile('system_activity.log', [
            'request_id' => $this->request_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'request',
            'method' => $method,
            'endpoint' => $endpoint,
            'ip' => $ip,
            'user_id' => $this->user_id,
            'module' => $this->module,
            'params' => $params
        ]);

        // Log to database
        $this->logAction('request', null, $message);
    }

    protected function logAction($action_type, $record_id, $description)
    {
        try {
            // Log to system activity log file
            $this->logToFile('system_activity.log', [
                'request_id' => $this->request_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'type' => 'action',
                'action' => $action_type,
                'module' => $this->module,
                'record_id' => $record_id,
                'user_id' => $this->user_id,
                'description' => $description
            ]);

            // Log to database
            $stmt = $this->db->prepare("
                INSERT INTO system_logs (
                    request_id, user_id, action, entity_type, 
                    entity_id, description, ip_address, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $this->request_id,
                $this->user_id,
                $action_type,
                $this->module,
                $record_id,
                $description,
                $_SERVER['REMOTE_ADDR']
            ]);

            // For audit logs
            if (in_array($action_type, ['create', 'update', 'delete'])) {
                $this->logAudit($action_type, $record_id, $description);
            }
        } catch (Exception $e) {
            // Log error but don't throw - we don't want logging to break the main flow
            $this->logError($e, 'Failed to log action');
        }
    }

    protected function logError($e, $context = '')
    {
        $errorData = [
            'request_id' => $this->request_id,
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => 'error',
            'module' => $this->module,
            'context' => $context,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $this->user_id,
            'ip' => $_SERVER['REMOTE_ADDR']
        ];

        // Log to errors.log
        $this->logToFile('errors.log', $errorData);

        // Log to database
        try {
            $stmt = $this->db->prepare("
                INSERT INTO error_logs (
                    request_id, user_id, module, context,
                    error_message, error_code, file_name,
                    line_number, stack_trace, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $this->request_id,
                $this->user_id,
                $this->module,
                $context,
                $e->getMessage(),
                $e->getCode(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ]);
        } catch (Exception $logError) {
            // Last resort - if we can't log to DB, at least we logged to file
            error_log("Failed to log error to database: " . $logError->getMessage());
        }
    }

    protected function logAudit($action, $record_id, $description)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (
                    request_id, user_id, action, table_name,
                    record_id, description, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, NOW()
                )
            ");

            $stmt->execute([
                $this->request_id,
                $this->user_id,
                $action,
                $this->module,
                $record_id,
                $description
            ]);
        } catch (Exception $e) {
            // Log error but don't throw
            $this->logError($e, 'Failed to create audit log');
        }
    }

    protected function logToFile($filename, $data)
    {
        try {
            $logDir = __DIR__ . '/../../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }

            $logFile = $logDir . '/' . $filename;
            $logEntry = json_encode($data) . "\n";

            file_put_contents($logFile, $logEntry, FILE_APPEND);
        } catch (Exception $e) {
            error_log("Failed to write to log file {$filename}: " . $e->getMessage());
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
        return Database::getInstance()->beginTransaction();
    }

    protected function commit()
    {
        return Database::getInstance()->commit();
    }

    protected function rollback()
    {
        return Database::getInstance()->rollback();
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
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $limit = isset($_GET['limit']) ?
            min((int) $_GET['limit'], MAX_PAGE_SIZE) :
            DEFAULT_PAGE_SIZE;
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
        try {
            if (!isset($file['error']) || is_array($file['error'])) {
                throw new RuntimeException('Invalid parameters.');
            }

            switch ($file['error']) {
                case UPLOAD_ERR_OK:
                    break;
                case UPLOAD_ERR_NO_FILE:
                    throw new RuntimeException('No file sent.');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new RuntimeException('Exceeded filesize limit.');
                default:
                    throw new RuntimeException('Unknown errors.');
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeTypes = [
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'pdf' => 'application/pdf',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt' => 'text/plain',
                'csv' => 'text/csv',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'mp3' => 'audio/mpeg',
                'mp4' => 'video/mp4',
                'avi' => 'video/x-msvideo',
                'mov' => 'video/quicktime',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'svg' => 'image/svg+xml',
            ];

            $ext = array_search($finfo->file($file['tmp_name']), $mimeTypes, true);

            if (false === $ext) {
                throw new RuntimeException('Invalid file format.');
            }

            if (!in_array($ext, $allowedTypes)) {
                throw new RuntimeException('File type not allowed.');
            }

            $filename = sprintf(
                '%s-%s.%s',
                uniqid(),
                date('Y-m-d-H-i-s'),
                $ext
            );

            if (!is_dir($destination)) {
                mkdir($destination, 0755, true);
            }

            if (!move_uploaded_file($file['tmp_name'], $destination . '/' . $filename)) {
                throw new RuntimeException('Failed to move uploaded file.');
            }

            return $filename;

        } catch (RuntimeException $e) {
            throw new RuntimeException($e->getMessage());
        }
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
            // Prefer stored procedure if available
            if ($this->routineExists('sp_emit_event', 'PROCEDURE')) {
                $this->callProcedure('sp_emit_event', [$eventType, json_encode($data)], false);
                return;
            }
            // Fallback direct insert
            $stmt = $this->db->prepare("INSERT INTO system_events (event_type, event_data, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$eventType, json_encode($data)]);
        } catch (Exception $e) {
            // Swallow errors to avoid breaking main flow
            $this->logError($e, 'emitEvent failed');
        }
    }

    // ---------- RBAC helpers ----------
    protected function getCurrentUserRole()
    {
        // User role is now set by AuthMiddleware in $_REQUEST['user']['role']
        return $_REQUEST['user']['role'] ?? null;
    }

    protected function getCurrentUser()
    {
        // Full user object set by AuthMiddleware
        return $_REQUEST['user'] ?? null;
    }
}
