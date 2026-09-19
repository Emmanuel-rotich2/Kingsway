<?php

namespace App\API\Router;

use Exception;
use App\API\Services\Logger;

class ControllerRouter
{
    /** @var float Request start time (microtime) for duration capture. */
    private $start;

    public function __construct()
    {
        $this->start = microtime(true);
        // Debug logging only in DEBUG mode
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

            // Remaining segments are: [resource, id/value, ...nested]
            // Strategy:
            // 1. Extract the first numeric segment as the resource ID. This supports
            //    both /resource/123 and nested action routes such as
            //    /resource/123/verify.
            // 2. Otherwise, join all remaining segments as the resource name
            //    (e.g. years/list -> years-list).

            $id = null;
            $resource = null;

            if (!empty($segments)) {
                // An ID may be followed by an action (for example, verify or
                // permissions), so do not require it to be the final segment.
                // Use the first numeric segment to preserve the existing route
                // convention while allowing REST-style nested actions.
                foreach ($segments as $index => $segment) {
                    if (is_numeric($segment)) {
                        $id = $segment;
                        array_splice($segments, $index, 1);
                        break;
                    }
                }

                // Join remaining segments with hyphens to form resource name
                // e.g., ['years', 'list'] → 'years-list'
                if (!empty($segments)) {
                    $resource = implode('-', $segments);
                }
            }

            // Load controller class
            $controller = $this->loadController($controllerName);

            // Special case: if resource is 'index', call index() directly
            if ($resource === 'index' && method_exists($controller, 'index')) {
                return $controller->index();
            }

            // Build primary method name from HTTP method + resource
            $methodName = $this->buildMethodName($method, $resource);
            $candidates = [];
            if ($resource) {
                $candidates[] = $methodName;
            }
            $ctrlCamel = ucfirst($controllerName);
            $httpLower = strtolower($method);
            $isPlural = (substr($ctrlCamel, -1) === 's');
            $singular = $isPlural ? substr($ctrlCamel, 0, -1) : $ctrlCamel;
            $candidates[] = $httpLower . $ctrlCamel;
            if ($isPlural) {
                $candidates[] = $httpLower . $singular;
            }
            // Only fall back to the generic action/list handlers (get, index) for a
            // BARE resource (e.g. GET /api/academic or /api/academic/{id}). A NAMED
            // resource that has no handler must 404 — otherwise unknown slugs silently
            // resolve to get() and return the controller's list payload (masking
            // broken endpoints as a false "success"). This was the root cause of
            // academic pages appearing to work while fetching irrelevant subjects data.
            if (!$resource) {
                $candidates[] = $httpLower;
                $candidates[] = 'index';
            }
            $candidates = array_values(array_unique($candidates));

            // Find the first method that exists
            $found = null;
            foreach ($candidates as $cand) {
                if (method_exists($controller, $cand)) {
                    $found = $cand;
                    break;
                }
            }

            // Fallback: if the full joined resource resolves to nothing but the
            // resource-minus-last-segment maps to an existing method, treat the
            // last segment as a string ID. This supports RESTful routes with
            // non-numeric IDs (e.g. /parent-portal/mpesa-status/{checkoutRequestId}
            // where Safaricom checkout IDs are alphanumeric). Only applies when the
            // full resource would otherwise 404, so existing named resources such
            // as /inventory/items-list are never affected.
            if (!$found && $resource && count($segments) > 1) {
                $stringId = array_pop($segments);
                $prefixResource = implode('-', $segments);
                $prefixMethod = $this->buildMethodName($method, $prefixResource);
                if ($prefixResource && method_exists($controller, $prefixMethod)) {
                    $found = $prefixMethod;
                    $id = $stringId;
                } else {
                    // Restore segments if fallback didn't apply
                    $segments[] = $stringId;
                }
            }

            if ($found) {
                $methodName = $found;
            } else {
                $this->debugLog(['unresolved' => $methodName, 'uri' => $_SERVER['REQUEST_URI'] ?? '', 'candidates' => $candidates]);
                return $this->abort(404, "Method '{$methodName}' not found on controller '{$controllerName}'");
            }

            // Get request data
            $data = $this->getRequestBody($method);

            // Call controller method with id and data
            $result = $controller->$methodName($id, $data, $segments);

            // Keep subsequent browser reads on the master briefly after a
            // successful mutation. The service carries this across requests
            // with a signed, short-lived cookie; internal workers do not need
            // browser stickiness.
            if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
                && is_array($result)
                && ($result['success'] ?? false) === true
                && !str_contains(strtolower((string) ($resource ?? '')), 'worker')
                && !str_contains(strtolower((string) ($resource ?? '')), 'cleanup')) {
                \App\API\Services\StickyMasterService::pin();
            }

            $this->trace($method, $controllerName, $resource, $id, $result);

            if (is_array($result)) {
                \App\API\Services\RealtimeMutationPublisher::publish(
                    $method,
                    $controllerName,
                    $resource,
                    $result
                );
            }

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

            // Guard against non-JSON-serializable types (objects, resources, etc.)
            if (!is_numeric($result) && !is_bool($result) && $result !== null) {
                return $this->abort(500,
                    'Controller returned non-serializable type: ' . gettype($result)
                );
            }

            return [
                'success' => true,
                'status' => 'success',
                'data' => $result,
                'message' => 'OK',
                'errors' => [],
                'code' => 200,
            ];

        } catch (\InvalidArgumentException $e) {
            \App\API\Services\Logger::legacyError('[ControllerRouter] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->abort(400, $e->getMessage());
        } catch (\RuntimeException $e) {
            \App\API\Services\Logger::legacyError('[ControllerRouter] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            if ($e->getCode() === 404) {
                return $this->abort(404, $e->getMessage());
            }
            return $this->abort(400, $e->getMessage());
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ControllerRouter] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->abort(500, 'An internal error occurred.');
        }
    }

    /**
     * Load controller as a class instance
     */
    private function loadController($controllerName)
    {
        static $controllerMap = null;
        if ($controllerMap === null) {
            // Route map is disk-cached: the per-request glob() over the
            // controllers directory is a real cost at ~1000 concurrent users
            // (every request globs once). A short TTL (120s) lets newly added
            // controllers appear within two minutes while removing the scan
            // from the concurrent hot path almost entirely.
            $controllerMap = \App\API\Services\FileCache::remember(
                'router.controller_map',
                2,
                function () {
                    $map = [];
                    $controllersDir = dirname(__DIR__) . '/controllers';
                    foreach (glob($controllersDir . '/*Controller.php') as $file) {
                        $base = basename($file, '.php');
                        if (preg_match('/^(.*)Controller$/i', $base, $m)) {
                            $map[strtolower($m[1])] = 'App\\API\\Controllers\\' . $base;
                        }
                    }
                    return $map;
                }
            );
            if (!is_array($controllerMap)) {
                $controllerMap = [];
            }
        }
        $key = strtolower($controllerName);
        // Hyphenated route segments (e.g. parent-portal) map to CamelCase
        // controller filenames (ParentPortalController → map key 'parentportal').
        // Normalize '-' to '' so the URI slug matches the map key.
        $key = str_replace('-', '', $key);
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
        $this->trace(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            strtolower(str_replace('Controller', '', basename(str_replace('\\', '/', static::class)))),
            null,
            null,
            ['code' => $code, 'message' => $message]
        );
        return [
            'success' => false,
            'status' => 'error',
            'data' => null,
            'message' => $message,
            'errors' => [],
            'code' => $code
        ];
    }

    /**
     * Emit a structured HTTP request/response trace to the central 'http' log
     * category: method, route, controller, resource, status, duration_ms,
     * user_id, session_id, ip.
     */
    private function trace(?string $method, ?string $controllerName, $resource, $id, $result): void
    {
        try {
            $durationMs = (int) round((microtime(true) - $this->start) * 1000);
            $code = 200;
            $success = true;
            if (is_array($result)) {
                $code = (int) ($result['code'] ?? (($result['success'] ?? true) ? 200 : 400));
                $success = ((bool) ($result['success'] ?? false)) || $code < 400;
            }
            if (!is_array($result)) {
                $code = (int) http_response_code();
                $success = $code < 400;
            }

            $route = ($resource !== null && $resource !== '')
                ? $method . ' /' . $controllerName . '/' . $resource
                : $method . ' /' . $controllerName . (is_numeric($id) ? '/' . $id : '');

            Logger::request(($success ? 'OK ' : 'ERR ') . $code . ' ' . $route, [
                'method' => $method,
                'controller' => $controllerName,
                'resource' => $resource !== null ? (string) $resource : null,
                'resource_id' => is_numeric($id) ? (int) $id : null,
                'status' => $code,
                'success' => $success,
                'duration_ms' => $durationMs,
                'route' => $route,
            ]);

            // Universal audit coverage for state-changing API operations.
            // Domain services may add richer before/after records, while this
            // guarantees every attempted mutation remains attributable even
            // when a legacy module has not yet added a bespoke audit call.
            $normalizedMethod = strtoupper((string) $method);
            $excluded = preg_match('#/(?:system/client-log|auth/refresh-token|realtime/)#i', $route);
            if (in_array($normalizedMethod, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !$excluded) {
                Logger::audit(
                    strtolower($normalizedMethod) . '_request',
                    (string) ($controllerName ?: 'api'),
                    is_numeric($id) ? (int) $id : null,
                    ($success ? 'Completed' : 'Failed') . ' state-changing API request',
                    [
                        'resource' => $resource !== null ? (string) $resource : null,
                        'status' => $code,
                        'success' => $success,
                        'duration_ms' => $durationMs,
                        'route' => $route,
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Never let tracing break the response flow.
            \App\API\Services\Logger::legacyError('ControllerRouter trace failed: ' . $e->getMessage());
        }
    }

    /**
     * @deprecated Unused behind defined('DEBUG') guard. Not called anywhere
     *             in the active codebase. Remove when cleaning dead code.
     */
    private function ensureLogDir(): string
    {
        $logDir = dirname(__DIR__, 2) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        return $logDir;
    }

    /**
     * @deprecated Unused behind defined('DEBUG') guard. Not called anywhere
     *             in the active codebase. Remove when cleaning dead code.
     */
    private function debugLog(array $data): void
    {
        if (!defined('DEBUG') || !DEBUG) {
            return;
        }
        try {
            $entry = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
            @(new \App\API\Services\UploadService())->writeFile($this->ensureLogDir() . '/router_debug.log', $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Silently ignore log failures in production
        }
    }

}
