<?php

namespace App\Database;

use PDO;
use RuntimeException;

/**
 * ConnectionManager - logical sharding mock under the single-connection
 * constraint (roadmap §4.1).
 *
 * Shared hosting allows exactly one PDO connection, so we re-point that single
 * lazy PDO between logical namespaces per operation instead of opening a second
 * connection. The default (master) namespace remains the transactional
 * KingsWayAcademy schema and is the ONLY write authority; the buffers namespace
 * is the offload schema for the job queue, AI cache buffers, export scratch and
 * realtime staging; the reads namespace is the materialized read replica that
 * serves analytic/reporting reads (§4.5) so the primary connection is freed
 * from read scans; the logs namespace is reserved for archived row paths (the
 * file journals stay authoritative).
 *
 * Guardrails:
 *  - Never issue USE while a transaction is open (PDO::inTransaction()).
 *  - All switching goes through run() so the previous namespace is always
 *    restored, even when the callback throws.
 *  - Every switch is recorded to the connection log journal with the request id.
 *
 * Usage:
 *   $result = ConnectionManager::run(function (PDO $pdo) { ... }, 'buffers');
 */
final class ConnectionManager
{
    public const NS_MASTER = 'master';
    public const NS_BUFFERS = 'buffers';
    public const NS_READS = 'reads';
    public const NS_LOGS = 'logs';

    /**
     * Logical namespace -> physical schema. Names resolve from config/env at
     * runtime (DB_NAME and DB_READS_NAME etc.) so production deployments can
     * rename databases without touching business logic. The app DB user must
     * own these schemas; on shared hosts that forbid a second database,
     * re-map an entry to a schema the account already owns or use prefixed
     * tables instead.
     *
     * @var array<string,string>
     */
    private const SCHEMAS = [
        self::NS_MASTER => 'KingsWayAcademy',
        self::NS_BUFFERS => 'KingsWayBuffers',
        self::NS_READS => 'KingsWayReads',
        self::NS_LOGS => 'KingsWayLogs',
    ];

    /** @var array<string,string>|null Resolved (env-overridable) schema map. */
    private static $resolvedSchemas = null;

    private static function resolveSchemas(): array
    {
        if (self::$resolvedSchemas === null) {
            $db = \App\Config\Config::get('DB_NAME');
            $resolved = self::SCHEMAS;
            if (is_string($db) && $db !== '') {
                $resolved[self::NS_MASTER] = $db;
            }
            // Optional per-namespace overrides: DB_BUFFERS_NAME, DB_READS_NAME, DB_LOGS_NAME.
            foreach ([self::NS_BUFFERS => 'DB_BUFFERS_NAME', self::NS_READS => 'DB_READS_NAME', self::NS_LOGS => 'DB_LOGS_NAME'] as $ns => $key) {
                $val = \App\Config\Config::get($key);
                if (is_string($val) && $val !== '') {
                    $resolved[$ns] = $val;
                }
            }
            self::$resolvedSchemas = $resolved;
        }
        return self::$resolvedSchemas;
    }

    /** Test seam: forget the memoized schema map so env overrides re-resolve. */
    public static function resetResolvedSchemasForTest(): void
    {
        self::$resolvedSchemas = null;
    }

    private function __construct()
    {
        // Static facade only.
    }

    public static function supports(string $namespace): bool
    {
        return isset(self::SCHEMAS[$namespace]);
    }

    public static function schemaFor(string $namespace): string
    {
        $schemas = self::resolveSchemas();
        return $schemas[$namespace] ?? $schemas[self::NS_MASTER];
    }

    /**
     * Run $fn($pdo) with the single PDO re-pointed at $namespace, then restore
     * the previous namespace. The callable receives the active PDO.
     *
     * @return mixed
     */
    public static function run(callable $fn, string $namespace)
    {
        $pdo = Database::getInstance()->getConnection();
        $target = self::schemaFor($namespace);

        if ($pdo->inTransaction()) {
            self::log('switch.blocked', $namespace, $target, ['reason' => 'open_transaction'], 'warning');
            throw new RuntimeException(
                'Cannot switch database namespace while a transaction is open.'
            );
        }

        $previous = self::currentSchema($pdo);
        if ($previous === $target) {
            self::log('run.same', $namespace, $target, ['switch' => false]);
            return $fn($pdo);
        }

        self::switchSchema($pdo, $target);
        self::log('switch.out', $namespace, $target, ['previous' => $previous]);
        try {
            return $fn($pdo);
        } finally {
            if (self::currentSchema($pdo) === $target) {
                self::switchSchema($pdo, $previous);
                self::log('switch.restore', self::NS_MASTER, $previous);
            }
        }
    }

    /**
     * Deprecated direct switch API kept for the roadmap wording (§4.1
     * setNamespace). Prefer run(); this variant refuses transactions but the
     * caller is responsible for restoring the default namespace.
     *
     * @deprecated Use ConnectionManager::run().
     */
    public static function setNamespace(string $namespace): void
    {
        $pdo = Database::getInstance()->getConnection();
        $target = self::schemaFor($namespace);
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Cannot switch database namespace while a transaction is open.');
        }
        $previous = self::currentSchema($pdo);
        if ($previous !== $target) {
            self::switchSchema($pdo, $target);
            self::log('switch.out', $namespace, $target, ['previous' => $previous]);
        }
    }

    private static function switchSchema(PDO $pdo, string $schema): void
    {
        $safe = str_replace('`', '', $schema);
        $pdo->exec('USE `' . $safe . '`');
    }

    private static function currentSchema(PDO $pdo): string
    {
        try {
            $stmt = $pdo->query('SELECT DATABASE()');
            $value = $stmt ? $stmt->fetchColumn() : false;
            return is_string($value) ? (string) $value : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private static function log(string $action, string $namespace, string $schema, array $context = [], string $level = 'info'): void
    {
        $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['HTTP_X_KINGSWAY_REQUEST_ID'] ?? 'cli');
        $context = array_merge($context, [
            'namespace' => $namespace,
            'schema' => $schema,
            'request_id' => (string) $requestId,
        ]);
        try {
            if ($level === 'warning') {
                \App\API\Services\Logger::warning('connection', $action, $context);
            } else {
                \App\API\Services\Logger::info('connection', $action, $context);
            }
        } catch (\Throwable $ignored) {
            // Logging must never break a namespace switch.
        }
    }
}