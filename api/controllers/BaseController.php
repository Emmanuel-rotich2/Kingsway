<?php
namespace App\API\Controllers;

use App\Config\Database;
use Exception;

/**
 * BaseController - Enhanced RESTful API base class
 * 
 * Provides unified response formatting, parameter extraction, nested resource routing,
 * and comprehensive error handling for all controllers.
 * 
 * RESPONSE FORMAT (ALL endpoints):
 * {
 *   "status": "success|error",
 *   "message": "Human-readable message",
 *   "data": null|object|array,
 *   "code": 200,
 *   "timestamp": "2024-11-14T10:30:00Z",
 *   "request_id": "req_12345"
 * }
 */
abstract class BaseController
{
    protected $db;
    protected $user;
    protected $requestId;
    protected $module;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->user = $_REQUEST['user'] ?? null;
        $this->requestId = uniqid('req_');
        $this->module = strtolower(str_replace('Controller', '', $this->getClassName()));

        // Set default content type
        header('Content-Type: application/json; charset=utf-8');
    }

    // ========================================================================
    // RESPONSE FORMATTING METHODS (Unified REST API responses)
    // ========================================================================

    /**
     * Success response (200 OK)
     * @param mixed $data The response data
     * @param string $message Optional message
     * @return string JSON response
     */
    protected function success($data = null, $message = 'Success')
    {
        return $this->formatResponse('success', $data, $message, 200);
    }

    /**
     * Created response (201 Created)
     * @param mixed $data The created resource
     * @param string $message Optional message
     * @return string JSON response
     */
    protected function created($data = null, $message = 'Resource created successfully')
    {
        return $this->formatResponse('success', $data, $message, 201);
    }

    /**
     * Accepted response (202 Accepted) - for async operations
     * @param mixed $data The response data
     * @param string $message Optional message
     * @return string JSON response
     */
    protected function accepted($data = null, $message = 'Request accepted for processing')
    {
        return $this->formatResponse('success', $data, $message, 202);
    }

    /**
     * No content response (204 No Content) - for delete operations
     * @return string JSON response
     */
    protected function noContent()
    {
        http_response_code(204);
        return '';
    }

    /**
     * Bad request (400 Bad Request)
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @return string JSON response
     */
    protected function badRequest($message = 'Bad request', $data = null)
    {
        return $this->formatResponse('error', $data, $message, 400);
    }

    /**
     * Unauthorized response (401 Unauthorized)
     * @param string $message Error message
     * @return string JSON response
     */
    protected function unauthorized($message = 'Unauthorized')
    {
        return $this->formatResponse('error', null, $message, 401);
    }

    /**
     * Forbidden response (403 Forbidden)
     * @param string $message Error message
     * @return string JSON response
     */
    protected function forbidden($message = 'Access forbidden')
    {
        return $this->formatResponse('error', null, $message, 403);
    }

    /**
     * Not found response (404 Not Found)
     * @param string $message Error message
     * @return string JSON response
     */
    protected function notFound($message = 'Resource not found')
    {
        return $this->formatResponse('error', null, $message, 404);
    }

    /**
     * Conflict response (409 Conflict)
     * @param string $message Error message
     * @param mixed $data Additional error data
     * @return string JSON response
     */
    protected function conflict($message = 'Resource conflict', $data = null)
    {
        return $this->formatResponse('error', $data, $message, 409);
    }

    /**
     * Unprocessable entity (422 Unprocessable Entity)
     * @param string $message Error message
     * @param mixed $data Validation errors or additional data
     * @return string JSON response
     */
    protected function unprocessable($message = 'Unprocessable entity', $data = null)
    {
        return $this->formatResponse('error', $data, $message, 422);
    }

    /**
     * Server error (500 Internal Server Error)
     * @param string $message Error message
     * @param mixed $debugData Debug information (only if DEBUG mode enabled)
     * @return string JSON response
     */
    protected function serverError($message = 'Internal server error', $debugData = null)
    {
        return $this->formatResponse(
            'error',
            DEBUG ? ['debug' => $debugData, 'request_id' => $this->requestId] : null,
            $message,
            500
        );
    }

    /**
     * Legacy method for backwards compatibility
     * @deprecated Use success(), created(), badRequest(), etc. instead
     */
    protected function respondWith($code, $message, $data = null)
    {
        $status = ($code >= 200 && $code < 300) ? 'success' : 'error';
        return $this->formatResponse($status, $data, $message, $code);
    }

    /**
     * Core response formatter - unified format for all endpoints
     * @param string $status 'success' or 'error'
     * @param mixed $data Response data
     * @param string $message Human-readable message
     * @param int $code HTTP status code
     * @return string JSON response
     */
    private function formatResponse($status, $data, $message, $code)
    {
        http_response_code($code);
        return json_encode([
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'code' => $code,
            'timestamp' => date('c'),
            'request_id' => $this->requestId
        ]);
    }

    // ========================================================================
    // PARAMETER EXTRACTION METHODS
    // ========================================================================

    /**
     * Get pagination parameters from query string
     * @return array [page, limit, offset]
     */
    protected function getPaginationParams()
    {
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $limit = isset($_GET['limit']) ?
            min((int) $_GET['limit'], MAX_PAGE_SIZE) :
            DEFAULT_PAGE_SIZE;
        $offset = ($page - 1) * $limit;
        return [$page, $limit, $offset];
    }

    /**
     * Get search/filter parameters from query string
     * @return array [search, sort, order]
     */
    protected function getSearchParams()
    {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $sort = isset($_GET['sort']) ? preg_replace('/\W/', '', $_GET['sort']) : 'id';
        $order = isset($_GET['order']) ? strtoupper(trim($_GET['order'])) : 'ASC';
        $order = in_array($order, ['ASC', 'DESC']) ? $order : 'ASC';

        return [$search, $sort, $order];
    }

    /**
     * Get a query parameter with optional default
     * @param string $key Parameter name
     * @param mixed $default Default value if not set
     * @return mixed Parameter value
     */
    protected function getParam($key, $default = null)
    {
        return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
    }

    /**
     * Get a request data field (from POST body or GET)
     * @param string $key Field name
     * @param mixed $default Default value if not set
     * @return mixed Field value
     */
    protected function getField($data, $key, $default = null)
    {
        return isset($data[$key]) ? $data[$key] : $default;
    }

    // ========================================================================
    // NESTED RESOURCE & SEGMENT HANDLING
    // ========================================================================

    /**
     * Extract first segment from remaining segments array
     * @param array $segments Remaining URL segments
     * @param int $index Index to extract (default 0)
     * @return string|null The segment value
     */
    protected function getSegment($segments, $index = 0)
    {
        return isset($segments[$index]) ? $segments[$index] : null;
    }

    /**
     * Get all remaining segments as a path
     * @param array $segments Remaining URL segments
     * @return string Path segments joined with /
     */
    protected function getRemainingSegments($segments)
    {
        return implode('/', $segments);
    }

    /**
     * Route to nested resource handler
     * 
     * Example URLs:
     * GET    /api/students/123/fees         → get student fees
     * POST   /api/students/123/fees         → create student fee
     * PUT    /api/students/123/fees/456     → update fee
     * DELETE /api/students/123/fees/456     → delete fee
     * 
     * @param int $parentId Parent resource ID
     * @param string $resource Nested resource name
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param mixed $data Request data
     * @param array $segments Remaining URL segments
     * @return mixed Response
     */
    protected function handleNestedResource($parentId, $resource, $method, $data, $segments = [])
    {
        // Get next segment if it's a nested resource ID
        $resourceId = !empty($segments) && is_numeric($segments[0]) ? array_shift($segments) : null;

        // Build method name: get{Resource} or get{Resource}By{ParentId}
        $methodName = strtolower($method) . ucwords(str_replace('_', ' ', $resource));
        $methodName = str_replace(' ', '', $methodName);

        // Try parent_resource_id specific method first
        $parentSpecificMethod = strtolower($method) . 'Parent' . ucwords($this->getClassName()) .
            ucwords(str_replace('_', ' ', $resource));
        $parentSpecificMethod = str_replace(' ', '', $parentSpecificMethod);

        // Fallback to generic method
        if (method_exists($this, $parentSpecificMethod)) {
            return $this->$parentSpecificMethod($parentId, $resourceId, $data, $segments);
        } elseif (method_exists($this, $methodName)) {
            return $this->$methodName($parentId, $resourceId, $data, $segments);
        } else {
            return $this->notFound("Nested resource '{$resource}' not found");
        }
    }

    /**
     * Route request based on action parameter
     * 
     * Allows complex operations via action parameter in request body:
     * POST /api/students/123 { "action": "promote", "new_class_id": 45 }
     * POST /api/students/123 { "action": "transfer", "destination_school": "XYZ" }
     * 
     * @param string $action Action name
     * @param int|null $id Resource ID (for operating on specific resource)
     * @param mixed $data Request data
     * @return mixed Response
     */
    protected function handleAction($action, $id = null, $data = [])
    {
        // Convert action to camelCase method name
        $methodName = 'action' . ucwords(str_replace('_', ' ', $action));
        $methodName = str_replace(' ', '', $methodName);

        if (!method_exists($this, $methodName)) {
            return $this->badRequest("Unknown action: {$action}");
        }

        return $this->$methodName($id, $data);
    }

    /**
     * Get controller class name
     * @return string Class name without 'Controller' suffix
     */
    protected function getClassName()
    {
        $className = static::class;
        $parts = explode('\\', $className);
        $shortName = end($parts);
        return str_replace('Controller', '', $shortName);
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    protected function getUser()
    {
        return $this->user;
    }

    protected function getUserId()
    {
        return $this->user['id'] ?? null;
    }

    protected function getDb()
    {
        return $this->db;
    }

    protected function getModule()
    {
        return $this->module;
    }

    // ========================================================================
    // GENERIC REST METHOD CALLER - Exposes all Module API methods as endpoints
    // ========================================================================

    /**
     * Dynamically call Module API methods based on request
     * 
     * This allows any public method in the Module API to be exposed as a REST endpoint
     * without explicit controller method definitions.
     * 
     * Usage patterns:
     *   GET  /api/academic/exam-schedules           → calls $api->getExamSchedules()
     *   POST /api/academic/verify-marks             → calls $api->verifyMarks($data)
     *   GET  /api/students/123/attendance           → calls $api->getAttendance(123, params)
     * 
     * @param object $api The Module API instance
     * @param string $httpMethod HTTP method (get, post, put, delete, patch)
     * @param string|null $resource Resource name (auto-converted to camelCase method)
     * @param int|null $id Primary ID
     * @param mixed $data Request data/parameters
     * @param array $segments Remaining URL segments
     * @return mixed API response
     */
    protected function callApiMethod($api, $httpMethod, $resource = null, $id = null, $data = [], $segments = [])
    {
        try {
            // Build method name from resource and HTTP method
            // GET /resource → getResource()
            // POST /resource → postResource() or createResource()
            // PUT /resource → putResource() or updateResource()
            // DELETE /resource → deleteResource()
            // PATCH /resource → patchResource()

            if (empty($resource)) {
                // No resource specified, call base CRUD methods
                $method = strtolower($httpMethod);
                if (method_exists($api, $method)) {
                    return $api->$method($id, $data, $segments);
                }
                return $this->badRequest('No resource specified');
            }

            // Convert kebab-case to camelCase (exam-schedules → examSchedules)
            $resourceCamel = $this->kebabToCamel($resource);

            // Try standard HTTP method pattern first
            $methodName = strtolower($httpMethod) . ucfirst($resourceCamel);
            if (method_exists($api, $methodName)) {
                return $this->invokeApiMethod($api, $methodName, $id, $data, $segments);
            }

            // For POST, try 'create' prefix instead of 'post'
            if (strtoupper($httpMethod) === 'POST') {
                $createMethod = 'create' . ucfirst($resourceCamel);
                if (method_exists($api, $createMethod)) {
                    return $this->invokeApiMethod($api, $createMethod, $id, $data, $segments);
                }
            }

            // For PUT, try 'update' prefix instead of 'put'
            if (strtoupper($httpMethod) === 'PUT') {
                $updateMethod = 'update' . ucfirst($resourceCamel);
                if (method_exists($api, $updateMethod)) {
                    return $this->invokeApiMethod($api, $updateMethod, $id, $data, $segments);
                }
            }

            // For DELETE, try 'delete' prefix
            if (strtoupper($httpMethod) === 'DELETE') {
                $deleteMethod = 'delete' . ucfirst($resourceCamel);
                if (method_exists($api, $deleteMethod)) {
                    return $this->invokeApiMethod($api, $deleteMethod, $id, $data, $segments);
                }
            }

            // Method not found
            return $this->notFound("Method '$methodName' not found in API");

        } catch (Exception $e) {
            return $this->serverError('API method call failed', $e->getMessage());
        }
    }

    /**
     * Invoke API method with proper parameter mapping and response handling
     * 
     * @param object $api The Module API instance
     * @param string $methodName The method to call
     * @param int|null $id Resource ID
     * @param mixed $data Request data
     * @param array $segments Remaining segments
     * @return string JSON response from BaseController
     */
    protected function invokeApiMethod($api, $methodName, $id = null, $data = [], $segments = [])
    {
        try {
            // Get method reflection to determine parameters
            $reflection = new \ReflectionMethod($api, $methodName);
            $params = $reflection->getParameters();

            // Build argument list based on method signature
            $args = [];
            foreach ($params as $param) {
                $paramName = $param->getName();

                // Map common parameter names
                if (in_array($paramName, ['id', 'resourceId', 'parentId', 'instanceId'])) {
                    $args[] = $id;
                } elseif (in_array($paramName, ['data', 'payload', 'params', 'request'])) {
                    $args[] = $data;
                } elseif (in_array($paramName, ['segments', 'remaining', 'nested'])) {
                    $args[] = $segments;
                } else {
                    // Try to get from $data array
                    if (isset($data[$paramName])) {
                        $args[] = $data[$paramName];
                    } elseif ($param->isDefaultValueAvailable()) {
                        $args[] = $param->getDefaultValue();
                    } else {
                        // Required parameter not provided
                        return $this->badRequest("Missing required parameter: $paramName");
                    }
                }
            }

            // Call the API method
            $result = $reflection->invokeArgs($api, $args);

            // Handle the response based on its structure
            return $this->handleApiResponse($result);

        } catch (\ReflectionException $e) {
            return $this->badRequest("Invalid method: $methodName");
        } catch (Exception $e) {
            return $this->serverError('Method execution failed', $e->getMessage());
        }
    }

    /**
     * Handle API response and convert to BaseController response format
     * 
     * API methods return raw arrays with structure:
     * [
     *   'status' => 'success|error',
     *   'message' => '...',
     *   'type' => '...',
     *   'code' => 200|201|400|404|etc,
     *   'data' => {...}
     * ]
     * 
     * This method converts to proper BaseController JSON response
     * 
     * @param mixed $result Raw API response array
     * @return string JSON response
     */
    protected function handleApiResponse($result)
    {
        // If result is not an array, it's already a JSON response from BaseController
        if (!is_array($result)) {
            return $result;
        }

        // Extract status info
        $status = $result['status'] ?? 'error';
        $code = $result['code'] ?? ($status === 'success' ? 200 : 400);
        $message = $result['message'] ?? '';
        $data = $result['data'] ?? null;

        // Route to appropriate BaseController method
        switch ($code) {
            case 200:
                return $this->success($data, $message);
            case 201:
                return $this->created($data, $message);
            case 202:
                return $this->accepted($data, $message);
            case 204:
                return $this->noContent();
            case 400:
                return $this->badRequest($message, $data);
            case 401:
                return $this->unauthorized($message);
            case 403:
                return $this->forbidden($message);
            case 404:
                return $this->notFound($message);
            case 409:
                return $this->conflict($message, $data);
            case 422:
                return $this->unprocessable($message, $data);
            case 500:
            default:
                return $this->serverError($message, $data);
        }
    }

    /**
     * Convert kebab-case to camelCase
     * exam-schedules → examSchedules
     * 
     * @param string $kebab Kebab-case string
     * @return string camelCase string
     */
    protected function kebabToCamel($kebab)
    {
        return lcfirst(str_replace('-', '', ucwords($kebab, '-')));
    }

    // ========================================================================
    // DEFAULT CRUD METHODS - Override in subclasses
    // ========================================================================

    public function get($id = null, $data = [], $segments = [])
    {
        return $this->respondWith(405, 'Method not allowed', null);
    }

    public function post($id = null, $data = [], $segments = [])
    {
        return $this->respondWith(405, 'Method not allowed', null);
    }

    public function put($id = null, $data = [], $segments = [])
    {
        return $this->respondWith(405, 'Method not allowed', null);
    }

    public function delete($id = null, $data = [], $segments = [])
    {
        return $this->respondWith(405, 'Method not allowed', null);
    }

    public function patch($id = null, $data = [], $segments = [])
    {
        return $this->respondWith(405, 'Method not allowed', null);
    }
}
