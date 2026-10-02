<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Database\ConnectionManager;
use App\Database\Database;
use App\API\Includes\FileLogger;
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

    /**
     * Indexes required by the read APIs after each atomic rebuild. MySQL's
     * CREATE TABLE ... AS SELECT does not copy indexes, so without this
     * allowlist every refresh silently turns the read model back into a full
     * scan. Keep names and columns code-owned; never accept them from a job.
     *
     * @var array<string,array<string,array<int,string>>>
     */
    private const READ_INDEXES = [
        'collection_rate_by_class' => [
            'idx_level_term' => ['level_code', 'academic_term'],
        ],
        'class_learning_area_performance' => [
            'idx_year_class_term' => ['academic_year_class_id', 'term_number'],
            'idx_academic_year' => ['academic_year'],
            'idx_term_number' => ['term_number'],
            'idx_class_name' => ['class_name'],
            'idx_stream_name' => ['stream_name'],
            'idx_learning_area' => ['learning_area'],
        ],
        'budget_utilization' => [
            'idx_budget' => ['budget_id'],
            'idx_year_term' => ['academic_year', 'term'],
        ],
        'dormitory_occupancy' => [
            'idx_dormitory_year' => ['dormitory_id', 'academic_year'],
            'idx_academic_year' => ['academic_year'],
            'idx_gender' => ['gender'],
        ],
        'fee_collection_monthly_trend' => [
            'idx_month' => ['month'],
        ],
        'fee_status_summary' => [
            'idx_year_term_class_stream' => ['academic_year', 'term_number', 'class_id', 'stream_id'],
            'idx_class_stream' => ['class_id', 'stream_id'],
            'idx_academic_year' => ['academic_year'],
            'idx_term_number' => ['term_number'],
            'idx_student_period' => ['student_id', 'academic_year', 'term_number'],
            'idx_admission_no' => ['admission_no'],
            'idx_payment_status' => ['payment_status'],
            'idx_current_balance' => ['current_balance'],
            'idx_student_type' => ['student_type_id'],
            'idx_level' => ['level_id'],
        ],
    ];

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
        $sourceQueryMs = 0;
        $indexBuildMs = 0;
        $rows = 0;
        $lockName = 'KingswayProjection:' . $projection;
        $lock = ConnectionManager::run(static function (PDO $active) use ($lockName): int {
            $stmt = $active->prepare('SELECT GET_LOCK(?, 0)');
            $stmt->execute([$lockName]);
            return (int) $stmt->fetchColumn();
        }, ConnectionManager::NS_READS);
        if ($lock !== 1) {
            throw new RuntimeException('This read projection is already being refreshed.');
        }

        try {
            ConnectionManager::run(static function (PDO $active) use ($reads, $source, $stage, $projection, &$sourceQueryMs, &$indexBuildMs, &$rows): void {
                self::assertSourceExists($active, $source);
                self::createStage($active, $reads, $source, $stage);
                $queryStarted = microtime(true);
                $insert = $active->exec('INSERT INTO ' . self::qid($reads, $stage) . ' SELECT * FROM ' . self::qualified($source));
                $sourceQueryMs = (int) round((microtime(true) - $queryStarted) * 1000);
                $rows = max(0, (int) $insert);

                $indexStarted = microtime(true);
                self::createReadIndexes($active, $reads, $stage, $projection);
                $indexBuildMs = (int) round((microtime(true) - $indexStarted) * 1000);
            }, ConnectionManager::NS_READS);

            $result = ConnectionManager::run(static function (PDO $active) use ($reads, $target, $stage, $source, $projection, $rows): array {
                self::publish($active, $reads, $target, $stage);
                $watermark = self::watermark($active, $source);
                self::writeMeta($active, $reads, $projection, $source, $rows, $watermark, null);
                return ['rows_count' => $rows, 'source_watermark' => $watermark];
            }, ConnectionManager::NS_READS);

            $report = [
                'status' => 'published',
                'projection' => $projection,
                'target' => $target,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'source_query_ms' => $sourceQueryMs,
                'index_build_ms' => $indexBuildMs,
            ] + $result;
            FileLogger::write('reads', ['event' => 'projection_refreshed'] + $report);
            return $report;
        } catch (\Throwable $e) {
            FileLogger::write('reads', [
                'event' => 'projection_refresh_failed',
                'projection' => $projection,
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
                'error_class' => get_class($e),
            ], 'error');
            try {
                ConnectionManager::run(static function (PDO $active) use ($reads, $stage, $projection, $source, $e): void {
                    self::dropIfExists($active, $reads, $stage);
                    $target = self::targetTable($projection);
                    if (self::objectExists($active, $reads, $target)) {
                        // Keep serving the last known good snapshot until its
                        // normal freshness limit expires; a failed refresh must
                        // not force every reader back onto the heavy source view.
                        $meta = $active->prepare('UPDATE ' . self::qid($reads, 'reads_meta') . ' SET last_error = ? WHERE projection = ?');
                        $meta->execute([self::redactError($e->getMessage()), $projection]);
                    } else {
                        self::writeMeta($active, $reads, $projection, $source, 0, null, self::redactError($e->getMessage()));
                    }
                }, ConnectionManager::NS_READS);
            } catch (\Throwable $metaError) {
                // Preserve the original synchronization failure.
            }
            throw new RuntimeException('Read projection synchronization failed: ' . self::redactError($e->getMessage()), 0, $e);
        } finally {
            try {
                ConnectionManager::run(static function (PDO $active) use ($lockName): void {
                    $stmt = $active->prepare('SELECT RELEASE_LOCK(?)');
                    $stmt->execute([$lockName]);
                }, ConnectionManager::NS_READS);
            } catch (\Throwable $releaseError) {
                FileLogger::write('reads', [
                    'event' => 'projection_lock_release_failed',
                    'projection' => $projection,
                    'error_class' => get_class($releaseError),
                ], 'warning');
            }
            unset($pdo);
        }
    }

    /** Recreate the indexed read shape on the staging snapshot before publish. */
    private static function createReadIndexes(PDO $pdo, string $reads, string $stage, string $projection): void
    {
        $indexes = self::READ_INDEXES[$projection] ?? [];
        if ($indexes === []) {
            throw new RuntimeException("Projection '{$projection}' has no registered read indexes.");
        }

        $stmt = $pdo->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
        );
        $stmt->execute([$reads, $stage]);
        $columns = array_fill_keys(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []), true);

        foreach ($indexes as $name => $indexColumns) {
            foreach ($indexColumns as $column) {
                if (!isset($columns[$column])) {
                    throw new RuntimeException("Registered read index column '{$column}' is missing from '{$projection}'.");
                }
            }
            $quotedColumns = array_map(static fn (string $column): string => '`' . str_replace('`', '', $column) . '`', $indexColumns);
            $pdo->exec(
                'ALTER TABLE ' . self::qid($reads, $stage)
                . ' ADD INDEX `' . str_replace('`', '', $name) . '` (' . implode(',', $quotedColumns) . ')'
            );
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
