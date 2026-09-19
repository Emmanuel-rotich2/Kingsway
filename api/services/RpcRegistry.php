<?php

namespace App\API\Services;

use LogicException;
use App\API\Includes\RpcSchemaValidator;

/**
 * RpcRegistry — the JSON-RPC method catalogue behind the api/rpc facade.
 *
 * Roadmap §4.2: methods resolve through a static registry (contract-first),
 * reusing the same middleware stack as the REST API. Each entry declares the
 * method name, a callable handler, an optional request/result schema, an
 * authorization descriptor, and the execution mode:
 *
 *   - 'sync'  handlers run inline and their return value becomes the JSON-RPC
 *             "result".
 *   - 'async' handlers are invoked to build a job payload, which is enqueued on
 *             the offload queue (KingsWayBuffers) and executed by the cron/Apache
 *             worker via the `rpc.async.dispatch` job handler; the RPC returns an
 *             immediate {job_id} and the caller polls `system.job.status`.
 *
 * Authorization descriptors ('auth'):
 *   - null                               any authenticated internal staff user
 *   - 'role:System Administrator'        role-name / wildcard permission gate
 *   - 'permission:<permission_name>'     effective-permission gate
 *
 * Handlers receive (array $params, array $ctx) where ctx carries user_id,
 * roles, request_id, the master PDO, and the controller instance.
 */
class RpcRegistry
{
    /** @var array<string, array>|null */
    private static $registry = null;

    private static function boot(): void
    {
        if (self::$registry !== null) {
            return;
        }
        self::$registry = [];

        self::register([
            'method' => 'system.info',
            'summary' => 'Read-only application, environment and queue-namespace status.',
            'auth' => null,
            'schema' => [],
            'handler' => static function (array $params, array $ctx): array {
                return [
                    'app' => 'Kingsway Preparatory School',
                    'api_version' => '1.0',
                    'environment' => (string) ($_ENV['APP_ENV'] ?? 'development'),
                    'timezone' => date_default_timezone_get(),
                    'server_time' => gmdate('Y-m-d\TH:i:s\Z'),
                    'php_version' => PHP_VERSION,
                    'request_id' => (string) ($ctx['request_id'] ?? ($_SERVER['REQUEST_ID'] ?? '')),
                    'queue_namespace' => 'KingsWayBuffers',
                    'database' => 'KingsWayAcademy',
                    'authenticated_user' => (int) ($ctx['user_id'] ?? 0),
                ];
            },
        ]);

        self::register([
            'method' => 'system.schema.list',
            'summary' => 'Returns the method catalogue (names, auth, schemas) for contract-first discovery.',
            'auth' => null,
            'schema' => [],
            'handler' => static function (array $params, array $ctx): array {
                $methods = [];
                foreach (self::$registry as $name => $def) {
                    $methods[] = [
                        'method' => $name,
                        'summary' => (string) ($def['_summary'] ?? ''),
                        'mode' => (string) ($def['_mode'] ?? 'sync'),
                        'auth' => $def['_auth'],
                        'schema' => RpcSchemaValidator::describe((array) ($def['_schema'] ?? [])),
                    ];
                }
                usort($methods, static function (array $a, array $b): int {
                    return strcmp($a['method'], $b['method']);
                });
                return ['methods' => $methods, 'jsonrpc' => '2.0'];
            },
        ]);

        self::register([
            'method' => 'system.queue.status',
            'summary' => 'System Administrator: live job queue and dead-letter summary from the buffers namespace.',
            'auth' => 'role:System Administrator',
            'schema' => [],
            'handler' => static function (array $params, array $ctx): array {
                $recent = JobQueue::listRecent(500);
                $dead = JobQueue::listDeadLetter(500);
                $counts = ['pending' => 0, 'processing' => 0, 'done' => 0, 'failed' => 0, 'cancelled' => 0];
                $oldestPending = null;
                foreach ($recent as $job) {
                    $status = (string) ($job['status'] ?? 'pending');
                    if (isset($counts[$status])) {
                        $counts[$status]++;
                    }
                    if ($status === 'pending' && ($oldestPending === null || ($job['available_at'] ?? '') < $oldestPending)) {
                        $oldestPending = (string) ($job['available_at'] ?? '');
                    }
                }
                return [
                    'namespace' => 'KingsWayBuffers',
                    'jobs' => $counts,
                    'dead_letter' => count($dead),
                    'oldest_pending_available_at' => $oldestPending,
                ];
            },
        ]);

        self::register([
            'method' => 'system.job.status',
            'summary' => 'System Administrator: status of one queued/processed job by id.',
            'auth' => 'role:System Administrator',
            'schema' => [
                'required' => ['job_id'],
                'additionalProperties' => false,
                'properties' => [
                    'job_id' => ['type' => 'int', 'min' => 1],
                ],
            ],
            'handler' => static function (array $params, array $ctx): array {
                $job = JobQueue::fetchJob((int) $params['job_id']);
                if ($job === null) {
                    throw new \DomainException('Job not found.', 404);
                }
                return [
                    'job_id' => (int) $job['id'],
                    'job_type' => (string) $job['job_type'],
                    'status' => (string) $job['status'],
                    'attempts' => (int) $job['attempts'],
                    'max_attempts' => (int) $job['max_attempts'],
                    'available_at' => $job['available_at'],
                    'created_at' => $job['created_at'],
                    'last_error' => $job['failed_reason'] ?? null,
                ];
            },
        ]);

        self::register([
            'method' => 'system.info.async',
            'summary' => 'System Administrator: enqueues a system.info job onto the offload queue for the worker.',
            'auth' => 'role:System Administrator',
            'mode' => 'async',
            // The worker-side, synchronously executable method that performs the
            // actual work when the queued rpc.async.dispatch job is consumed.
            'worker' => 'system.info',
            'schema' => [],
        ]);
    }

    /**
     * Register a method definition. Throws on duplicate or malformed names.
     */
    public static function register(array $def): void
    {
        self::boot();
        $method = (string) ($def['method'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9._]{1,120}$/', $method)) {
            throw new LogicException("Invalid RPC method name '{$method}'.");
        }
        if (isset(self::$registry[$method])) {
            throw new LogicException("RPC method '{$method}' is already registered.");
        }

        $mode = (string) ($def['mode'] ?? 'sync');
        if (!in_array($mode, ['sync', 'async'], true)) {
            throw new LogicException("RPC method '{$method}' has an invalid mode '{$mode}'.");
        }
        $worker = $def['worker'] ?? null;
        if ($worker !== null) {
            $worker = (string) $worker;
            if ($mode !== 'async') {
                throw new LogicException("RPC method '{$method}' declares a worker but is not async.");
            }
            if (preg_match('/^[a-z][a-z0-9.]{1,120}$/', $worker) !== 1 || $worker === $method) {
                throw new LogicException("RPC method '{$method}' has an invalid worker target '{$worker}'.");
            }
        }
        if ($mode === 'async' && !isset($def['schema'])) {
            // Async methods still need a validation schema for the queue payload?
            // No: the client params are validated by the facade before the
            // handler builds the job, so an explicit schema is optional here.
            $def['schema'] = [];
        }
        if ($mode === 'sync' && !is_callable($def['handler'] ?? null)) {
            throw new LogicException("RPC method '{$method}' must declare a callable handler.");
        }
        if ($mode === 'async' && isset($def['handler']) && !is_callable($def['handler'])) {
            throw new LogicException("RPC method '{$method}' async handler must be callable when provided.");
        }

        $auth = isset($def['auth']) ? (string) $def['auth'] : null;
        if ($auth !== null && !preg_match('/^(role:|permission:).{1,64}$/', $auth)) {
            throw new LogicException("RPC method '{$method}' has an invalid auth descriptor.");
        }

        self::$registry[$method] = [
            '_summary' => (string) ($def['summary'] ?? ''),
            '_mode' => $mode,
            '_auth' => $auth,
            '_schema' => isset($def['schema']) && is_array($def['schema']) ? $def['schema'] : [],
            '_worker' => $worker,
            'handler' => $def['handler'] ?? null,
        ];
    }

    /**
     * Resolve a method to its definition, or null when unknown.
     */
    public static function resolve(string $method): ?array
    {
        self::boot();
        return self::$registry[$method] ?? null;
    }

    public static function has(string $method): bool
    {
        return self::resolve($method) !== null;
    }

    /**
     * Sorted list of registered method names.
     */
    public static function methods(): array
    {
        self::boot();
        $names = array_keys(self::$registry);
        sort($names);
        return $names;
    }
}