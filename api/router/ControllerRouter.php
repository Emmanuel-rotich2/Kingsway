<?php

namespace App\API\router;

use Exception;

class ControllerRouter
{
    public function __construct()
    {
        // Write a test entry to router_debug.log on every instantiation
        if (php_sapi_name() !== 'cli') {
            $logDir = dirname(__DIR__, 2) . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/router_debug.log';
            $entry = json_encode(['router_debug_test' => 'init', 'ts' => date('c')]) . "\n";
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        }
    }
    public function route()
    {
        try {
            // Parse the request
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = $this->normalizeUri($_SERVER['REQUEST_URI']);
            $segments = array_filter(explode('/', $uri)); // Remove empty segments

            if (empty($segments)) {
                return $this->abort(400, "Invalid request path");
            }

            // Get controller name (first segment)
            $controllerName = array_shift($segments);

            // Remaining segments are: [resource, id] or [resource]
            // Try to extract: first segment is always the resource/action
            $resource = !empty($segments) ? array_shift($segments) : null;
            // Second segment is the ID if it's numeric
            $id = !empty($segments) && is_numeric($segments[0]) ? array_shift($segments) : null;

            // Load controller class
            $controller = $this->loadController($controllerName);

            // Special case: if resource is 'index', call index() directly
            if ($resource === 'index' && method_exists($controller, 'index')) {
                $this->writeRouterDebugLog([
                    'timestamp' => date('c'),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'controller' => $controllerName,
                    'resource' => $resource,
                    'resolved_method' => 'index (special case)'
                ]);
                return $controller->index();
            }

            // Build primary method name from HTTP method + resource
            $methodName = $this->buildMethodName($method, $resource);
            $candidates = [];
            if ($resource) {
                $candidates[] = $methodName; // e.g., getReportsCompareYearlyCollections
            }
            // Controller-specific method: e.g., getUsers, getUser
            $ctrlCamel = ucfirst($controllerName);
            $httpLower = strtolower($method);
            $isPlural = (substr($ctrlCamel, -1) === 's');
            $singular = $isPlural ? substr($ctrlCamel, 0, -1) : $ctrlCamel;
            // Try plural and singular forms
            $candidates[] = $httpLower . $ctrlCamel; // getUsers
            if ($isPlural) {
                $candidates[] = $httpLower . $singular; // getUser
            }
            // Generic HTTP method (get, post, etc.)
            $candidates[] = $httpLower;
            // Common index method
            $candidates[] = 'index';
            // Remove duplicates, preserve order
            $candidates = array_values(array_unique($candidates));


            // DEBUG: Log what we're about to try, and all available methods on the controller
            $this->writeRouterDebugLog([
                'timestamp' => date('c'),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'controller' => $controllerName,
                'resource' => $resource,
                'method_candidates' => $candidates,
                'controller_methods' => get_class_methods($controller)
            ]);

            // Find the first method that exists
            $found = null;
            foreach ($candidates as $cand) {
                if (method_exists($controller, $cand)) {
                    $found = $cand;
                    break;
                }
            }

            // DEBUG: Log what we found
            $this->writeRouterDebugLog([
                'timestamp' => date('c'),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'controller' => $controllerName,
                'resource' => $resource,
                'method_candidates' => $candidates,
                'resolved_method' => $found
            ]);

            if ($found) {
                $methodName = $found;
            } else {
                // Log diagnostic info before aborting
                $this->writeRouterDebugLog([
                    'timestamp' => date('c'),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'controller' => $controllerName,
                    'initial_method' => $methodName,
                    'candidates' => $candidates,
                    'found' => null
                ]);

                return $this->abort(404, "Method '{$methodName}' not found on controller '{$controllerName}'");
            }

            // Log successful resolution
            $this->writeRouterDebugLog([
                'timestamp' => date('c'),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'controller' => $controllerName,
                'resolved_method' => $methodName
            ]);

            // Get request data
            $data = $this->getRequestBody($method);

            // Call controller method with id and data
            $result = $controller->$methodName($id, $data, $segments);

            // Return result
            if (is_array($result)) {
                return $result;
            }

            // If result is JSON string, decode and return
            if (is_string($result)) {
                $decoded = json_decode($result, true);
                return $decoded ?? [
                    'status' => 'success',
                    'data' => $result
                ];
            }

            return [
                'status' => 'success',
                'data' => $result
            ];

        } catch (Exception $e) {
            return $this->abort(500, $e->getMessage());
        }
    }

    /**
     * Load controller as a class instance
     */
    private function loadController($controllerName)
    {
        static $controllerMap = null;
        if ($controllerMap === null) {
            $controllerMap = [];
            $controllersDir = dirname(__DIR__) . '/controllers';
            foreach (glob($controllersDir . '/*Controller.php') as $file) {
                $base = basename($file, '.php'); // e.g., UsersController
                if (preg_match('/^(.*)Controller$/i', $base, $m)) {
                    $key = strtolower($m[1]);
                    $controllerMap[$key] = 'App\\API\\Controllers\\' . $base;
                }
            }
            // Log the controller map for diagnostics
            $logDir = dirname(__DIR__, 2) . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/router_debug.log';
            $entry = json_encode(['controllerMap' => $controllerMap, 'ts' => date('c')]) . "\n";
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        }
        $key = strtolower($controllerName);
        // Try plural, then singular if not found
        if (!isset($controllerMap[$key]) && substr($key, -1) === 's') {
            $singular = substr($key, 0, -1);
            if (isset($controllerMap[$singular])) {
                $key = $singular;
            }
        }
        if (!isset($controllerMap[$key])) {
            throw new Exception("Controller for '{$controllerName}' not found");
        }
        $className = $controllerMap[$key];
        if (!class_exists($className)) {
            throw new Exception("Controller class '{$className}' not found");
        }
        return new $className();
    }
    /**
     * Build method name from HTTP method and resource
     * Examples:
     *   GET + null -> get()
     *   GET + terms -> getTerms()
     *   POST + students -> postStudents()
     *   PUT + null -> put()
     *   DELETE + profile -> deleteProfile()
     */
    private function buildMethodName($httpMethod, $resource = null)
    {
        $method = strtoupper($httpMethod);
        $base = strtolower($method); // 'get', 'post', 'put', 'delete'

        if (empty($resource)) {
            return $base; // Just 'get', 'post', etc.
        }

        // Normalize resource: accept kebab-case, snake_case, or mixed
        // Replace hyphens with underscores, remove extra non-alphanumeric
        $normalized = preg_replace('/[^a-zA-Z0-9_\-]/', '', $resource);
        $normalized = str_replace('-', '_', $normalized);

        // Camel case the resource: 'terms' -> 'Terms', 'user_profile' -> 'UserProfile', 'exam-schedules' -> 'ExamSchedules'
        $parts = explode('_', $normalized);
        $camelResource = implode('', array_map('ucfirst', $parts));

        return $base . $camelResource; // 'getTerms', 'postStudents', etc.
    }

    /**
     * Normalize URI by removing /api prefix and query strings
     */
    private function normalizeUri($uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = ltrim($path, '/');
        $segments = explode('/', $path);

        // List of known local project folder names (add more as needed)
        $projectFolders = ['kingsway'];

        // If running on a custom domain (e.g., www.kingsway.ac.ke), expect /api/ as the root
        // If running locally, expect /Kingsway/api/ or /kingsway/api/
        if (
            count($segments) > 2 &&
            in_array(strtolower($segments[0]), $projectFolders) &&
            strtolower($segments[1]) === 'api'
        ) {
            // Remove project and 'api'
            $segments = array_slice($segments, 2);
        } elseif (count($segments) > 1 && strtolower($segments[0]) === 'api') {
            // Remove only 'api'
            $segments = array_slice($segments, 1);
        }
        // For production, /api/academic will work; for local, /Kingsway/api/academic will work
        $path = implode('/', $segments);
        $path = rtrim($path, '/');
        return $path;
    }
    /**
     * Get request body (JSON or form data)
     */
    private function getRequestBody($method)
    {
        if ($method === "GET") {
            return $_GET;
        }

        $input = file_get_contents("php://input");
        $decoded = json_decode($input, true);

        if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
            return $decoded;
        }

        return $_POST ?? [];
    }

    /**
     * Abort with error response
     */
    private function abort($code, $message)
    {
        http_response_code($code);
        return [
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ];
    }

    /**
     * Write router debug entry to logs/router_debug.log
     * @param array $data
     */
    private function writeRouterDebugLog(array $data)
    {
        try {
            // Use absolute path to logs directory
            $logDir = dirname(__DIR__, 2) . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            $logFile = $logDir . '/router_debug.log';
            $errFile = $logDir . '/errors.log';
            $entry = json_encode($data) . "\n";
            // Always try to write to both logs
            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            file_put_contents($errFile, '[ROUTER_DEBUG] ' . $entry, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            // Log to errors.log if anything fails
            $logDir = dirname(__DIR__, 2) . '/logs';
            $errFile = $logDir . '/errors.log';
            $errEntry = json_encode(['router_debug_log_exception' => $e->getMessage(), 'ts' => date('c')]) . "\n";
            @file_put_contents($errFile, $errEntry, FILE_APPEND | LOCK_EX);
        }
    }

}
