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
