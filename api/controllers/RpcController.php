<?php

namespace App\API\Controllers;

use App\API\Includes\FileLogger;
use App\API\Includes\RpcSchemaValidator;
use App\API\Services\JobQueue;
use App\API\Services\Logger;
use App\API\Services\RpcRegistry;
use DomainException;
use Throwable;

/**
 * RpcController — the JSON-RPC 2.0 facade mounted at /api/rpc.
 *
 * Roadmap §4.2: same middleware stack as the REST API (CORS, IP control, rate
 * limit, Auth, CSRF, RBAC, RouteAuthorization, Device) is reused because the
 * facade is reached through the shared Router. Inside here we resolve methods
 * from the static RpcRegistry, validate each request contract-first with
 * RpcSchemaValidator, enforce per-method authorization, and return the standard
 * project envelope with a JSON-RPC 2.0 payload in `data`:
 *
 *   { jsonrpc, result|error, id }
 *
 * POST /api/rpc accepts a single request object or a JSON-RPC batch array.
 * Long-running methods (mode=async) return { job_id } immediately and execute
 * through the offload queue worker via rpc.async.dispatch.
 */
class RpcController extends BaseController
{
    /** JSON-RPC standard error codes. */
    private const E_PARSE = -32700;
    private const E_INVALID_REQUEST = -32600;
    private const E_METHOD_NOT_FOUND = -32601;
    private const E_INVALID_PARAMS = -32602;
    private const E_INTERNAL = -32603;
    private const E_AUTH = -32003;

    public function __construct()
    {
        parent::__construct();
        $this->bootIfNeeded();
    }

    private function bootIfNeeded(): void
    {
        // Warm the registry without disturbing anything else; registrations are
        // static and idempotent.
        RpcRegistry::methods();
    }

    /**
     * GET /api/rpc — contract discovery (method catalogue).
     */
    public function getRpc($id = null, $data = [], $segments = [])
    {
        return $this->postRpc($id, [], $segments, true);
    }

    /**
     * POST /api/rpc — execute one JSON-RPC request or a batch.
     *
     * The ControllerRouter has already consumed php://input and passes the
     * decoded JSON body through $data, so this method MUST NOT re-read the
     * input stream (in PHP it can only be read once per request).
     */
    public function postRpc($id = null, $data = [], $segments = [], bool $discoveryOnly = false)
    {
        $start = microtime(true);
        $userId = (int) ($this->getUserId() ?? 0);

        try {
            $body = $data;

            if ($discoveryOnly) {
                // GET discovery: serve the catalogue via an explicit call.
                $body = ['jsonrpc' => '2.0', 'id' => null, 'method' => 'system.schema.list', 'params' => []];
            } elseif (is_string($body) && $body !== '') {
                // Some clients post a raw JSON string rather than an object.
                $body = json_decode($body, true);
                if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
                    return $this->rpcEnvelopeError(null, self::E_PARSE, 'Parse error: invalid JSON body.', 400, $userId, $start);
                }
            }

            if ($body === null) {
                return $this->rpcEnvelopeError(null, self::E_INVALID_REQUEST, 'Invalid Request: body must be a JSON object or a batch array.', 400, $userId, $start);
            }

            if ($this->isList($body)) {
                if ($body === []) {
                    return $this->rpcEnvelopeError(null, self::E_INVALID_REQUEST, 'Invalid Request: empty batch.', 400, $userId, $start);
                }
                $responses = [];
                foreach ($body as $request) {
                    if (!is_array($request)) {
                        $responses[] = $this->buildError(null, self::E_INVALID_REQUEST, 'Invalid Request: batch item must be an object.');
                        continue;
                    }
                    $responses[] = $this->dispatch($request);
                }
                $jsonRpc = $responses;
                $message = count($responses) . ' RPC call(s) processed';
                return $this->rpcEnvelope($jsonRpc, $message, 200, true, $userId, $start);
            }

            $request = is_array($body) ? $body : [];
            if (!isset($request['method'])) {
                return $this->rpcEnvelopeError(null, self::E_INVALID_REQUEST, 'Invalid Request: missing method.', 400, $userId, $start);
            }

            $response = $this->dispatch($request);
            $success = !isset($response['error']);
            $code = isset($response['error'])
                ? (int) ($response['error']['data']['http'] ?? 200)
                : (isset($response['result']['job_id']) ? 202 : 200);
            $message = $success
                ? (isset($response['result']['job_id']) ? 'Request accepted for processing' : 'OK')
                : (string) ($response['error']['message'] ?? 'RPC error');

            return $this->rpcEnvelope($response, $message, $code, $success, $userId, $start);
        } catch (Throwable $e) {
            Logger::critical('rpc', 'RPC dispatch failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->rpcEnvelopeError(null, self::E_INTERNAL, 'Internal error', 500, $userId, $start, true);
        }
    }

    /**
     * Dispatch a single validated JSON-RPC request object.
     */
    private function dispatch(array $request): array
    {
        $rpcId = $request['id'] ?? null;
        if (($request['jsonrpc'] ?? null) !== '2.0') {
            return $this->buildError($rpcId, self::E_INVALID_REQUEST, "Invalid Request: 'jsonrpc' must be \"2.0\".");
        }
        $method = (string) ($request['method'] ?? '');
        if ($method === '') {
            return $this->buildError($rpcId, self::E_INVALID_REQUEST, 'Invalid Request: missing method.');
        }

        $params = $request['params'] ?? [];
        if (!is_array($params)) {
            return $this->buildError($rpcId, self::E_INVALID_PARAMS, 'Parameters must be a JSON object.');
        }

        $def = RpcRegistry::resolve($method);
        if ($def === null) {
            return $this->buildError($rpcId, self::E_METHOD_NOT_FOUND, "Method '{$method}' not found.");
        }

        // Contract-first validation.
        try {
            $clean = RpcSchemaValidator::validate($params, (array) ($def['_schema'] ?? []));
        } catch (DomainException $e) {
            return $this->buildError($rpcId, self::E_INVALID_PARAMS, 'Invalid params: ' . $e->getMessage());
        }

        // Per-method authorization.
        $auth = $def['_auth'];
        if ($auth !== null && !$this->authorizedFor($auth)) {
            return $this->buildError($rpcId, self::E_AUTH, 'Authorization required for this method.', ['http' => 403]);
        }

        $ctx = [
            'user_id' => $this->getUserId(),
            'roles' => $this->getUserRoleNames(),
            'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId,
            'db' => \App\Database\Database::getInstance()->getConnection(),
            'controller' => $this,
        ];

        try {
            if (($def['_mode'] ?? 'sync') === 'async') {
                $jobId = JobQueue::push(
                    'rpc.async.dispatch',
                    [
                        'method' => $method,
                        'params' => $clean,
                        'user_id' => (int) ($ctx['user_id'] ?? 0),
                        'request_id' => (string) $ctx['request_id'],
                    ],
                    0,
                    3,
                    30
                );
                $result = ['job_id' => $jobId, 'status' => 'queued', 'method' => $method];
            } else {
                $result = call_user_func($def['handler'], $clean, $ctx);
                RpcSchemaValidator::validateResult($result, (array) ($def['_result'] ?? []));
            }

            FileLogger::write('rpc', [
                'type' => 'call',
                'rpc_id' => is_scalar($rpcId) ? $rpcId : null,
                'method' => $method,
                'mode' => (string) ($def['_mode'] ?? 'sync'),
                'user_id' => (int) ($ctx['user_id'] ?? 0),
                'status' => 'success',
            ]);

            return ['jsonrpc' => '2.0', 'result' => $result, 'id' => $rpcId];
        } catch (DomainException $e) {
            FileLogger::write('rpc', [
                'type' => 'call',
                'rpc_id' => is_scalar($rpcId) ? $rpcId : null,
                'method' => $method,
                'user_id' => (int) ($ctx['user_id'] ?? 0),
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            return $this->buildError($rpcId, -32000, $e->getMessage(), ['http' => (int) $e->getCode() ?: 400]);
        } catch (Throwable $e) {
            Logger::critical('rpc', 'RPC method execution failed', [
                'method' => $method,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            FileLogger::write('rpc', [
                'type' => 'call',
                'rpc_id' => is_scalar($rpcId) ? $rpcId : null,
                'method' => $method,
                'user_id' => (int) ($ctx['user_id'] ?? 0),
                'status' => 'error',
                'error' => 'Internal error',
            ]);
            return $this->buildError($rpcId, self::E_INTERNAL, 'Internal error', ['http' => 500]);
        }
    }

    /**
     * Evaluate an auth descriptor ('role:X' or 'permission:Y').
     */
    private function authorizedFor(string $auth): bool
    {
        if (strpos($auth, 'role:') === 0) {
            $roleName = substr($auth, 5);
            return $this->userHasRole($roleName) || $this->userHasPermission('*');
        }
        if (strpos($auth, 'permission:') === 0) {
            return $this->userHasPermission(substr($auth, 11));
        }
        return true;
    }

    private function buildError($id, int $code, string $message, array $data = []): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== []) {
            $error['data'] = $data;
        }
        return ['jsonrpc' => '2.0', 'error' => $error, 'id' => $id];
    }

    private function rpcEnvelope($jsonRpcResponse, string $message, int $code, bool $success, int $userId, float $start): array
    {
        FileLogger::write('rpc', [
            'type' => 'request',
            'user_id' => $userId,
            'status' => $success ? 'success' : 'error',
            'http' => $code,
            'duration_ms' => (int) round((microtime(true) - $start) * 1000),
        ]);
        $response = [
            'success' => $success,
            'status' => $success ? 'success' : 'error',
            'data' => $jsonRpcResponse,
            'message' => $message,
            'errors' => [],
            'code' => $code,
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'request_id' => $_SERVER['REQUEST_ID'] ?? $this->requestId,
        ];
        if (!$success && $code >= 400) {
            http_response_code($code);
        }
        return $response;
    }

    private function rpcEnvelopeError($id, int $code, string $message, int $http, int $userId, float $start, bool $emitted = false): array
    {
        return $this->rpcEnvelope($this->buildError($id, $code, $message), $message, $http, false, $userId, $start);
    }

    private function isList($value): bool
    {
        if (!is_array($value)) {
            return false;
        }
        if ($value === []) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }
}