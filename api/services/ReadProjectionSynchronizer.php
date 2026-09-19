<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\ConnectionManager;
use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Incrementally deployable materialized read projection synchronizer.
 *
 * The first projection is an aggregate dashboard trend. It is rebuilt into a
 * staging table and atomically renamed into place, so readers see either the
 * previous complete snapshot or the next complete snapshot—never a half-filled
 * table. The source remains KingsWayAcademy and the target is KingsWayReads.
 */
final class ReadProjectionSynchronizer
{
    private const STAGE_PREFIX = '__stage_';

    private function __construct()
    {
    }

    public static function supports(string $projection): bool
    {
        return isset(ReadReplicaService::MATERIALIZED_TARGETS[$projection]);
    }

    public static function targetTable(string $projection): string
    {
        if (!self::supports($projection)) {
            throw new \DomainException("Projection '{$projection}' is not materialized yet.", 422);
        }
        return ReadReplicaService::MATERIALIZED_TARGETS[$projection];
    }

    /**
     * Refresh one projection and return a redacted operational result.
     *
     * @return array<string,mixed>
     */
    public static function synchronize(string $projection): array
    {
        $target = self::targetTable($projection);
        $source = ReadReplicaService::sourceFor($projection);
        $reads = ConnectionManager::schemaFor(ConnectionManager::NS_READS);
        $stage = self::STAGE_PREFIX . $target . '_' . substr(bin2hex(random_bytes(8)), 0, 12);
        $pdo = Database::getInstance()->getConnection();
        $started = microtime(true);

        try {
            ConnectionManager::run(static function (PDO $active) use ($reads, $source, $stage): void {
                self::assertSourceExists($active, $source);
                self::createStage($active, $reads, $source, $stage);
                $active->exec('INSERT INTO ' . self::qid($reads, $stage) . ' SELECT * FROM ' . self::qualified($source));
            }, ConnectionManager::NS_READS);

            $result = ConnectionManager::run(static function (PDO $active) use ($reads, $target, $stage, $source, $projection): array {
                self::publish($active, $reads, $target, $stage);
                $rows = (int) $active->query('SELECT COUNT(*) FROM ' . self::qid($reads, $target))->fetchColumn();
                $watermark = self::watermark($active, $source);
                self::writeMeta($active, $reads, $projection, $source, $rows, $watermark, null);
                return ['rows_count' => $rows, 'source_watermark' => $watermark];
            }, ConnectionManager::NS_READS);

            return [
                'status' => 'published',
                'projection' => $projection,
                'target' => $target,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ] + $result;
        } catch (\Throwable $e) {
            try {
                ConnectionManager::run(static function (PDO $active) use ($reads, $stage, $projection, $source, $e): void {
                    self::dropIfExists($active, $reads, $stage);
                    self::writeMeta($active, $reads, $projection, $source, 0, null, self::redactError($e->getMessage()));
                }, ConnectionManager::NS_READS);
            } catch (\Throwable $metaError) {
                // Preserve the original synchronization failure.
            }
            throw new RuntimeException('Read projection synchronization failed: ' . self::redactError($e->getMessage()), 0, $e);
        } finally {
            unset($pdo);
        }
    }

    private static function assertSourceExists(PDO $pdo, string $source): void
    {
        [$schema, $object] = self::splitQualified($source);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$schema, $object]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException("Source projection '{$object}' does not exist.");
        }
    }

    private static function createStage(PDO $pdo, string $reads, string $source, string $stage): void
    {
        self::dropIfExists($pdo, $reads, $stage);
        // CREATE TABLE AS SELECT copies the view's result columns without
        // copying source indexes or exposing source DDL to the reads schema.
        $pdo->exec('CREATE TABLE ' . self::qid($reads, $stage) . ' AS SELECT * FROM ' . self::qualified($source) . ' WHERE 1 = 0');
    }

    private static function publish(PDO $pdo, string $reads, string $target, string $stage): void
    {
        $old = self::STAGE_PREFIX . 'old_' . $target . '_' . substr(bin2hex(random_bytes(6)), 0, 8);
        $targetExists = self::objectExists($pdo, $reads, $target);
        if ($targetExists) {
            $pdo->exec('RENAME TABLE ' . self::qid($reads, $target) . ' TO ' . self::qid($reads, $old) . ', ' . self::qid($reads, $stage) . ' TO ' . self::qid($reads, $target));
            self::dropIfExists($pdo, $reads, $old);
            return;
        }
        $pdo->exec('RENAME TABLE ' . self::qid($reads, $stage) . ' TO ' . self::qid($reads, $target));
    }

    private static function objectExists(PDO $pdo, string $schema, string $object): bool
    {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
        $stmt->execute([$schema, $object]);
        return (int) $stmt->fetchColumn() === 1;
    }

    private static function dropIfExists(PDO $pdo, string $schema, string $object): void
    {
        if (self::objectExists($pdo, $schema, $object)) {
            $pdo->exec('DROP TABLE ' . self::qid($schema, $object));
        }
    }

    private static function watermark(PDO $pdo, string $source): ?string
    {
        try {
            $value = $pdo->query('SELECT MAX(`month`) FROM ' . self::qualified($source))->fetchColumn();
            return $value === false || $value === null ? null : (string) $value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function writeMeta(PDO $pdo, string $reads, string $projection, string $source, int $rows, ?string $watermark, ?string $error): void
    {
        $policy = ReadReplicaService::policy($projection);
        $stmt = $pdo->prepare(
            'INSERT INTO ' . self::qid($reads, 'reads_meta') . ' '
            . '(projection, source_view, rows_count, source_watermark, as_of, refreshed_at, status, storage_mode, sensitivity, max_age_seconds, last_error) '
            . 'VALUES (:projection, :source_view, :rows_count, :source_watermark, NOW(), '
            . ($error === null ? 'NOW()' : 'NULL') . ', :status, :storage_mode, :sensitivity, :max_age_seconds, :last_error) '
            . 'ON DUPLICATE KEY UPDATE source_view=VALUES(source_view), rows_count=VALUES(rows_count), '
            . 'source_watermark=VALUES(source_watermark), as_of=VALUES(as_of), refreshed_at=VALUES(refreshed_at), '
            . 'status=VALUES(status), storage_mode=VALUES(storage_mode), sensitivity=VALUES(sensitivity), '
            . 'max_age_seconds=VALUES(max_age_seconds), last_error=VALUES(last_error)'
        );
        $stmt->execute([
            ':projection' => $projection,
            ':source_view' => $source,
            ':rows_count' => $rows,
            ':source_watermark' => $watermark,
            ':status' => $error === null ? 'live' : 'failed',
            ':storage_mode' => 'materialized_table',
            ':sensitivity' => $policy['sensitivity'],
            ':max_age_seconds' => $policy['max_age_seconds'],
            ':last_error' => $error,
        ]);
    }

    private static function splitQualified(string $source): array
    {
        $parts = explode('.', $source, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Source object must be schema-qualified.');
        }
        return [trim($parts[0], '`'), trim($parts[1], '`')];
    }

    private static function qualified(string $source): string
    {
        [$schema, $object] = self::splitQualified($source);
        return self::qid($schema, $object);
    }

    private static function qid(string $schema, string $object): string
    {
        return '`' . str_replace('`', '', $schema) . '`.`' . str_replace('`', '', $object) . '`';
    }

    private static function redactError(string $message): string
    {
        $message = preg_replace('/(?:password|secret|token|key)\s*[=:]\s*[^\s,;]+/i', '$1=[redacted]', $message) ?? $message;
        return substr($message, 0, 500);
    }
}
