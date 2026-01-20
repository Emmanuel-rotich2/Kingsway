<?php
namespace App\API\Includes;

/**
 * Helper Functions for API Operations
 * 
 * IMPORTANT ARCHITECTURE NOTE:
 * This file contains only essential utility functions.
 * Redundant functions have been consolidated to avoid duplication:
 * 
 * - handleCORS() moved to api/middleware/CORSMiddleware.php
 * - formatResponse() consolidated in api/controllers/BaseController.php
 * - logActivity() / logError() consolidated in BaseAPI.php methods
 * 
 * This file now serves as a lightweight utility module only.
 */

/**
 * Generate a secure random string
 * Used for tokens, request IDs, and other security purposes
 * 
 * @param int $length Length of the string to generate
 * @return string The generated random string
 */
function generateSecureString($length = 32)
{
    return bin2hex(random_bytes($length));
}

/**
 * Map message keywords to specific HTTP status codes
 * Analyzes message text to determine appropriate status code
 * 
 * @param string $message The response message
 * @param bool $success Whether the operation was successful
 * @return int HTTP status code
 */
function mapMessageToCode($message, $success)
{
    // If successful, check for specific success codes
    if ($success) {
        $lowerMessage = strtolower($message);

        if (strpos($lowerMessage, 'created') !== false) {
            return 201; // Created
        }
        if (strpos($lowerMessage, 'accepted') !== false) {
            return 202; // Accepted
        }
        if (strpos($lowerMessage, 'no content') !== false || empty($message)) {
            return 204; // No Content
        }

        return 200; // Default success
    }

    // If error, check for specific error codes
    $lowerMessage = strtolower($message);

    // 500 Server Error - Check for SQL/database errors first
    if (
        strpos($lowerMessage, 'sqlstate') !== false ||
        strpos($lowerMessage, 'sql error') !== false ||
        strpos($lowerMessage, 'database error') !== false ||
        (strpos($lowerMessage, "table") !== false && strpos($lowerMessage, "doesn't exist") !== false) ||
        strpos($lowerMessage, 'column not found') !== false
    ) {
        return 500;
    }

    // 404 Not Found - Check for specific resource not found messages
    if (
        preg_match('/\b(expense|budget|payroll|fee|payment|record|resource|item|entity)\s+not\s+found\b/i', $message) ||
        strpos($lowerMessage, 'does not exist') !== false && !strpos($lowerMessage, 'table') !== false
    ) {
        return 404;
    }

    // 401 Unauthorized
    if (strpos($lowerMessage, 'unauthorized') !== false || strpos($lowerMessage, 'unauthenticated') !== false) {
        return 401;
    }

    // 403 Forbidden / Access Denied
    if (
        strpos($lowerMessage, 'forbidden') !== false ||
        strpos($lowerMessage, 'permission') !== false ||
        strpos($lowerMessage, 'access denied') !== false ||
        strpos($lowerMessage, 'do not have') !== false
    ) {
        return 403;
    }

    // 409 Conflict
    if (
        strpos($lowerMessage, 'conflict') !== false ||
        strpos($lowerMessage, 'already exists') !== false ||
        strpos($lowerMessage, 'duplicate') !== false
    ) {
        return 409;
    }

    // 422 Unprocessable Entity / Validation Error
    if (
        strpos($lowerMessage, 'invalid') !== false ||
        strpos($lowerMessage, 'validation') !== false ||
        strpos($lowerMessage, 'required') !== false ||
        strpos($lowerMessage, 'missing') !== false
    ) {
        return 422;
    }

    // 500 Server Error
    if (
        strpos($lowerMessage, 'server error') !== false ||
        strpos($lowerMessage, 'failed') !== false ||
        strpos($lowerMessage, 'exception') !== false
    ) {
        return 500;
    }

    // Default error code
    return 400; // Bad Request
}

/**
 * Format response data for APIs
 * 
 * Intelligently maps boolean success and message to specific HTTP status codes
 * Analyzes message keywords to determine appropriate code (404, 401, 403, 409, 422, etc)
 * 
 * NOTE: This returns RAW DATA ARRAY ONLY - NO JSON ENCODING
 * Controllers are responsible for final JSON formatting via BaseController methods
 * 
 * This function exists for backward compatibility with workflow classes.
 * Workflows can use this to structure their responses, which Controllers
 * will then properly format and JSON-encode.
 * 
 * Usage:
 *   // Success response (analyzes message for code)
 *   return formatResponse(true, $data, 'Resource created');  // Returns code 201
 *   return formatResponse(true, $data, 'Operation completed'); // Returns code 200
 *   
 *   // Error response (analyzes message for specific error)
 *   return formatResponse(false, null, 'User not found');  // Returns code 404
 *   return formatResponse(false, null, 'Unauthorized');  // Returns code 401
 *   return formatResponse(false, null, 'Invalid input');  // Returns code 422
 * 
 * @param bool $success Whether operation was successful
 * @param mixed $data Response payload
 * @param string $message Custom message (analyzed to determine code)
 * @return array Raw data array with status, message, type, code, and data
 */
function formatResponse($success = true, $data = null, $message = '')
{
    // Intelligently map message to specific HTTP code
    $code = mapMessageToCode($message, $success);
    $statusInfo = getStatusInfo($code);

    return [
        'status' => $statusInfo['status'],
        'message' => !empty($message) ? $message : $statusInfo['message'],
        'type' => $statusInfo['type'],
        'code' => $code,
        'data' => $data
    ];
}

/**
 * HTTP Status Code to Message Mapping
 * Maps status codes to standardized messages and response types
 * 
 * @param int $code HTTP status code
 * @return array [status, message, type]
 */
function getStatusInfo($code)
{
    $statusMap = [
        // 2xx Success
        200 => ['status' => 'success', 'message' => 'Success', 'type' => 'OK'],
        201 => ['status' => 'success', 'message' => 'Resource created successfully', 'type' => 'Created'],
        202 => ['status' => 'success', 'message' => 'Request accepted for processing', 'type' => 'Accepted'],
        204 => ['status' => 'success', 'message' => 'No content', 'type' => 'NoContent'],

        // 4xx Client Errors
        400 => ['status' => 'error', 'message' => 'Bad request', 'type' => 'BadRequest'],
        401 => ['status' => 'error', 'message' => 'Unauthorized', 'type' => 'Unauthorized'],
        403 => ['status' => 'error', 'message' => 'Access forbidden', 'type' => 'Forbidden'],
        404 => ['status' => 'error', 'message' => 'Resource not found', 'type' => 'NotFound'],
        409 => ['status' => 'error', 'message' => 'Resource conflict', 'type' => 'Conflict'],
        422 => ['status' => 'error', 'message' => 'Unprocessable entity', 'type' => 'Unprocessable'],

        // 5xx Server Errors
        500 => ['status' => 'error', 'message' => 'Internal server error', 'type' => 'ServerError'],
        501 => ['status' => 'error', 'message' => 'Not implemented', 'type' => 'NotImplemented'],
        502 => ['status' => 'error', 'message' => 'Bad gateway', 'type' => 'BadGateway'],
        503 => ['status' => 'error', 'message' => 'Service unavailable', 'type' => 'ServiceUnavailable'],
    ];

    return $statusMap[$code] ?? ['status' => 'error', 'message' => 'Unknown error', 'type' => 'UnknownError'];
}

/**
 * Error response helper - returns raw array for API modules
 * 
 * Automatically generates status and message based on HTTP status code
 * 
 * NOTE: Returns RAW DATA ARRAY ONLY - NO JSON ENCODING
 * Controllers will wrap this with proper formatting via BaseController methods
 * 
 * Usage in Module APIs (AcademicAPI, StaffAPI, etc):
 *   if (!$record) {
 *       return errorResponse('Record not found', null, 404);
 *   }
 *   
 *   if (!$this->authorize(['admin'])) {
 *       return errorResponse('You do not have permission', null, 403);
 *   }
 *   
 *   if ($validationFails) {
 *       return errorResponse('Invalid input data', ['fields' => $errors], 400);
 *   }
 * 
 * The Controller will then route based on code:
 *   $result = $this->api->get($id);
 *   if ($result['status'] === 'error') {
 *       return $this->respondWithErrorCode($result['code'] ?? 500, $result['message'], $result['data']);
 *   }
 * 
 * @param string $message Custom error message (overrides default)
 * @param mixed $data Optional error details (validation errors, etc)
 * @param int $code HTTP status code (400, 401, 403, 404, 422, 500, etc)
 * @return array Raw error array with status, message, type, code, and data
 */
function errorResponse($data = null, $code = 400)
{
    $statusInfo = getStatusInfo($code);

    return [
        'status' => $statusInfo['status'],
        'message' => $statusInfo['message'],
        'type' => $statusInfo['type'],
        'code' => $code,
        'data' => $data
    ];
}

/**
 * Success response helper - returns raw array for API modules
 * 
 * Automatically generates status and message based on HTTP status code
 * 
 * NOTE: Returns RAW DATA ARRAY ONLY - NO JSON ENCODING
 * Controllers will wrap this with proper formatting via BaseController methods
 * 
 * Usage in Module APIs:
 *   // Simple success (200 OK)
 *   return successResponse($student);
 *   
 *   // Created (201 Created)
 *   return successResponse($newStudent, 'Student created successfully', 201);
 *   
 *   // Custom message with standard code
 *   return successResponse($data, 'Operation completed', 200);
 * 
 * @param mixed $data Response payload
 * @param string $message Custom message (overrides default code message)
 * @param int $code HTTP status code (200, 201, 202, 204, etc) - default 200
 * @return array Raw success array with status, message, type, code, and data
 */
function successResponse($data = null, $code = 200)
{
    $statusInfo = getStatusInfo($code);

    return [
        'status' => $statusInfo['status'],
        'message' => $statusInfo['message'],
        'type' => $statusInfo['type'],
        'code' => $code,
        'data' => $data
    ];
}

/**
 * Sanitize input data recursively
 * Removes whitespace, encodes HTML entities
 * 
 * @param mixed $data The data to sanitize
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    if (is_string($data)) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    return $data;
}

/**
 * Determine the best available column from a list of candidates for a given table.
 * Caches results to avoid repeated DESCRIBE calls.
 * Supports in-process caching (default) and optional DB-backed cross-process caching (via schema_discovery_cache table).
 *
 * @param string $table Table name
 * @param array $candidates Ordered list of candidate column names
 * @param int $ttlSeconds TTL in seconds for cache validity (default 300)
 * @param bool $useDbCache Whether to store/read discovery results in DB for cross-process caching
 * @return string|null
 */
function getPreferredColumnName(string $table, array $candidates = ['amount_paid', 'amount'], int $ttlSeconds = 300, bool $useDbCache = false)
{
    static $cache = [];
    $key = $table . ':' . implode(',', $candidates);

    // In-memory cached value with timestamp
    if (isset($cache[$key]) && isset($cache[$key]['ts']) && (time() - $cache[$key]['ts']) < $ttlSeconds) {
        return $cache[$key]['col'];
    }

    // Try DB-backed cache if requested
    $lookupKey = md5($key);
    if ($useDbCache) {
        try {
            $db = \App\Database\Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT columns_json, UNIX_TIMESTAMP(updated_at) as updated_ts FROM schema_discovery_cache WHERE lookup_key = ? LIMIT 1");
            $stmt->execute([$lookupKey]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && ($row['updated_ts'] !== null) && (time() - (int) $row['updated_ts']) < $ttlSeconds) {
                $cols = json_decode($row['columns_json'], true) ?? [];
                foreach ($candidates as $col) {
                    if (in_array($col, $cols, true)) {
                        $cache[$key] = ['col' => $col, 'ts' => time()];
                        return $col;
                    }
                }
            }
        } catch (\Exception $e) {
            // DB cache not available — fall through to discovery
        }
    }

    // Fallback: perform SHOW COLUMNS discovery
    try {
        $db = \App\Database\Database::getInstance()->getConnection();
        $foundCols = [];
        foreach ($candidates as $col) {
            $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
            $stmt->execute([$col]);
            if ($stmt->fetch()) {
                $foundCols[] = $col;
            }
        }

        if (!empty($foundCols)) {
            // persist to DB if requested
            if ($useDbCache) {
                try {
                    $colsJson = json_encode($foundCols);
                    $up = $db->prepare("INSERT INTO schema_discovery_cache (lookup_key, table_name, candidates, columns_json, updated_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE columns_json = VALUES(columns_json), updated_at = NOW()");
                    $up->execute([$lookupKey, $table, implode(',', $candidates), $colsJson]);
                } catch (\Exception $e) {
                    // ignore persistence errors
                }
            }

            $first = $foundCols[0];
            $cache[$key] = ['col' => $first, 'ts' => time()];
            return $first;
        }
    } catch (\Exception $e) {
        // ignore discovery errors
    }

    // Nothing found
    $cache[$key] = ['col' => null, 'ts' => time()];
    return null;
}

/**
 * Build a SQL COALESCE expression using only columns that actually exist on the table.
 * If none of the candidates exist, returns the provided default (string).
 * Example: sql_coalesce_existing_columns('payment_transactions', ['amount_paid','amount'], '0')
 * -> "COALESCE(`amount_paid`,`amount`, 0)"
 *
 * @param string $table
 * @param array $candidates
 * @param string $default
 * @param int $ttlSeconds TTL in seconds for cache validity (default 300)
 * @param bool $useDbCache Whether to use DB-backed cache
 * @return string
 */
function sql_coalesce_existing_columns(string $table, array $candidates = ['amount_paid', 'amount'], string $default = '0', int $ttlSeconds = 300, bool $useDbCache = false)
{
    static $cache = [];
    $key = $table . ':' . implode(',', $candidates) . ':' . $default;
    if (isset($cache[$key]) && (time() - $cache[$key]['ts']) < $ttlSeconds) {
        return $cache[$key]['expr'];
    }

    $cols = [];
    try {
        $db = \App\Database\Database::getInstance()->getConnection();

        // Use DB-backed discovery if requested
        if ($useDbCache) {
            $lookupKey = md5($table . ':' . implode(',', $candidates));
            try {
                $stmt = $db->prepare("SELECT columns_json, UNIX_TIMESTAMP(updated_at) as updated_ts FROM schema_discovery_cache WHERE lookup_key = ? LIMIT 1");
                $stmt->execute([$lookupKey]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && ($row['updated_ts'] !== null) && (time() - (int) $row['updated_ts']) < $ttlSeconds) {
                    $existing = json_decode($row['columns_json'], true) ?? [];
                    foreach ($existing as $col) {
                        $cols[] = "`$col`";
                    }
                }
            } catch (\Exception $e) {
                // ignore and do discovery below
            }
        }

        // If no columns from DB cache or DB cache not used, perform SHOW COLUMNS
        if (empty($cols)) {
            $foundCols = [];
            foreach ($candidates as $col) {
                $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                $stmt->execute([$col]);
                if ($stmt->fetch()) {
                    $foundCols[] = $col;
                    $cols[] = "`$col`";
                }
            }

            // persist discovery to DB if requested
            if ($useDbCache && !empty($foundCols)) {
                try {
                    $lookupKey = md5($table . ':' . implode(',', $candidates));
                    $colsJson = json_encode($foundCols);
                    $up = $db->prepare("INSERT INTO schema_discovery_cache (lookup_key, table_name, candidates, columns_json, updated_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE columns_json = VALUES(columns_json), updated_at = NOW()");
                    $up->execute([$lookupKey, $table, implode(',', $candidates), $colsJson]);
                } catch (\Exception $e) {
                    // ignore persistence errors
                }
            }
        }
    } catch (\Exception $e) {
        // ignore
    }

    if (empty($cols)) {
        $expr = $default;
        $cache[$key] = ['expr' => $expr, 'ts' => time()];
        return $expr;
    }

    $expr = "COALESCE(" . implode(',', $cols) . ", $default)";
    $cache[$key] = ['expr' => $expr, 'ts' => time()];
    return $expr;
}
