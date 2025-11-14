<?php

namespace App\API\Router;

use Exception;

class ControllerRouter
{
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

            // Remaining segments are: [id, resource, ...]
            $id = !empty($segments) && is_numeric($segments[0]) ? array_shift($segments) : null;
            $resource = !empty($segments) ? array_shift($segments) : null;

            // Load controller class
            $controller = $this->loadController($controllerName);

            // Build method name from HTTP method + resource
            $methodName = $this->buildMethodName($method, $resource);

            // Check if method exists on controller
            if (!method_exists($controller, $methodName)) {
                return $this->abort(404, "Method '{$methodName}' not found on controller '{$controllerName}'");
            }

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
        $controllerName = strtolower($controllerName);
        $className = 'App\\API\\Controllers\\' . ucfirst($controllerName);

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

        // Camel case the resource: 'terms' -> 'Terms', 'user_profile' -> 'UserProfile'
        $parts = explode('_', $resource);
        $camelResource = implode('', array_map('ucfirst', $parts));

        return $base . $camelResource; // 'getTerms', 'postStudents', etc.
    }

    /**
     * Normalize URI by removing /api prefix and query strings
     */
    private function normalizeUri($uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);

        // Remove /api prefix
        $path = preg_replace('#^/api/#', '', $path);

        // Remove trailing slash
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
}
