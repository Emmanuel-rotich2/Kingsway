<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use PDO;

/**
 * ReadReplicaService - realtime read replica of the primary (roadmap §4.5).
 *
 * The transactional master (KingsWayAcademy) is the single WRITE authority.
 * Reads are projected into the KingsWayReads schema as `mv_<name>` VIEWS over
 * the master views / tables. Because a view is computed at read time, ANY change
 * to a master table is visible in the replica immediately - there is no
 * materialized table, no rebuild window, no staleness of even a second. Reads
 * served here never write back, so the primary connection is freed from
 * report-style scans while remaining the authoritative write store.
 *
 * The replica is derived, never an alternative source of truth; re-reading any
 * projection always returns the live master state.
 */
final class ReadReplicaService
{
    /**
     * Catalogue: projection name => source view name on the master schema.
     * The physical master schema is resolved at runtime via
     * ConnectionManager::schemaFor(NS_MASTER) so production deployments can
     * rename databases via config without touching this catalogue. The reads
     * namespace mirrors each source as a `mv_<name>` view.
     *
     * @var array<string,string>
     */
    public const PROJECTIONS = [
        'student_fee_ledger' => 'vw_student_fee_ledger',
        'collection_rate_by_class' => 'vw_collection_rate_by_class',
        'student_attendance_analytics' => 'vw_student_attendance_analytics',
        // 2026-09-09 scaling wave: heavy analytic/portal reads served off the
        // master connection. Each key is a master view promoted to a replica
        // projection; qualifiedRef()/query() degrade to the master view when
        // the reads schema is absent (env-agnostic, single-PDO constraint).
        'student_fee_balances' => 'vw_student_fee_balances',
        'student_term_performance' => 'vw_student_term_performance',
        'class_learning_area_performance' => 'vw_class_learning_area_performance',
        'student_learning_progress' => 'vw_student_learning_progress',
        'budget_utilization' => 'vw_budget_utilization',
        'staff_daily_register' => 'vw_staff_daily_register',
        'student_attendance_summary' => 'vw_student_attendance_summary',
        'staff_workload' => 'vw_staff_workload',
        'dormitory_occupancy' => 'vw_dormitory_occupancy',
        'student_transport_summary' => 'vw_student_transport_summary',
        'student_health_summary' => 'vw_student_health_summary',
        'fee_collection_monthly_trend' => 'vw_fee_collection_monthly_trend',
    ];

    /** Fully-qualified source object (master schema resolved at runtime). */
    public static function sourceFor(string $projection): string
    {
        return ConnectionManager::schemaFor(ConnectionManager::NS_MASTER) . '.' . self::PROJECTIONS[$projection];
    }

    private const MAX_QUERY_LIMIT = 500;
    private const MAX_OFFSET = 100000;

    /**
     * Memoized reachability probe for the replica view. Mirrors the probe in
     * qualifiedRef() so both read paths share one probe result per projection
     * per request. When the reads schema is absent (a host that did not create
     * KingsWayReads), the probe fails closed and every query() call degrades
     * to the identical master view - same code path in every environment.
     */
    private static function replicaReachable(string $projection): bool
    {
        if (!isset(self::$refCache[$projection])) {
            self::qualifiedRef($projection); // populates $refCache via probe
        }
        $resolved = self::$refCache[$projection];
        // qualifiedRef() returns the bare master view name as the fallback.
        return strpos($resolved, '.') !== false;
    }

    private function __construct()
    {
        // Static service facade.
    }

    public static function projections(): array
    {
        return array_keys(self::PROJECTIONS);
    }

    public static function table(string $projection): string
    {
        $safe = preg_replace('/[^a-z0-9_]/', '', $projection);
        return 'mv_' . $safe;
    }

    public static function hasProjection(string $projection): bool
    {
        return isset(self::PROJECTIONS[$projection]);
    }

    /**
     * Schema-qualified replica reference for embedding inside a larger query
     * that runs on the master connection, e.g. correlated subqueries and
     * JOINs that cannot be split into separate replica calls.
     *
     * Resolution is per-request and config-driven (DB_READS_NAME/DB_NAME), so
     * production database renames never break the reference. When the replica
     * projection is not reachable the unqualified master view name is
     * returned, and because both are one-level pass-through views over the
     * same master objects the query stays correct either way — the replica is
     * a routing optimization, not a source of truth.
     *
     * Memoized per projection; the probe cost is one information_schema lookup
     * on first use per request.
     *
     * @throws \DomainException unknown projection
     */
    public static function qualifiedRef(string $projection): string
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown read-replica projection '{$projection}'.", 404);
        }
        if (!isset(self::$refCache[$projection])) {
            $replicaSchema = ConnectionManager::schemaFor(ConnectionManager::NS_READS);
            $view = self::table($projection);
            // Probe information_schema WITHOUT switching schema: the probe runs
            // on whatever schema is currently active (master, usually) and
            // information_schema is server-global. This keeps the call safe
            // inside an open transaction, where ConnectionManager::run() would
            // refuse to switch.
            try {
                $reachable = (bool) ConnectionManager::run(static function (PDO $pdo) use ($replicaSchema, $view): bool {
                    $stmt = $pdo->prepare(
                        'SELECT COUNT(*) FROM information_schema.VIEWS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
                    );
                    $stmt->execute([$replicaSchema, $view]);
                    return (int) $stmt->fetchColumn() > 0;
                }, ConnectionManager::NS_MASTER);
            } catch (\Throwable $e) {
                $reachable = false;
            }
            self::$refCache[$projection] = $reachable
                ? '`' . str_replace('`', '', $replicaSchema) . '`.`' . $view . '`'
                : self::PROJECTIONS[$projection];
        }
        return self::$refCache[$projection];
    }

    /** @var array<string,string> Per-request memo of resolved replica references. */
    private static $refCache = [];

    /**
     * Realtime verification: a projection's row count as seen from the replica
     * must equal the live master view. Views make this trivially true, so this
     * is an integrity assertion rather than a repair.
     */
    public static function liveFresh(string $projection): bool
    {
        if (!self::hasProjection($projection)) {
            return false;
        }
        // No reads schema deployed: reads degrade to the master view, which
        // is its own source of truth - parity is trivially true.
        if (!self::replicaReachable($projection)) {
            return true;
        }
        return (bool) ConnectionManager::run(static function (PDO $pdo) use ($projection): bool {
            $source = self::sourceFor($projection);
            $replica = (int) $pdo->query('SELECT COUNT(*) FROM `' . self::table($projection) . '`')->fetchColumn();
            $master = (int) $pdo->query('SELECT COUNT(*) FROM ' . $source)->fetchColumn();
            return $replica === $master;
        }, ConnectionManager::NS_READS);
    }

    /**
     * Freshness/watermark status for every projection. Reads_meta records the
     * live mirror status (as_of NOW()); because these are views, the replica is
     * always current to master.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function freshness(): array
    {
        // Health snapshot: on hosts without the reads schema every projection
        // degrades to the master view and is reported as live, because a
        // one-level view over master can never drift from master.
        $degraded = array_filter(
            array_keys(self::PROJECTIONS),
            static fn (string $p): bool => !self::replicaReachable($p)
        );
        if ($degraded !== []) {
            return array_map(static function (string $projection) use ($degraded): array {
                return [
                    'projection' => $projection,
                    'source_view' => self::sourceFor($projection),
                    'rows_count' => null,
                    'master_rows' => null,
                    'realtime' => true,
                    'as_of' => null,
                    'status' => in_array($projection, $degraded, true) ? 'degraded_master' : 'live',
                ];
            }, array_keys(self::PROJECTIONS));
        }
        return ConnectionManager::run(static function (PDO $pdo): array {
            $out = [];
            foreach (array_keys(self::PROJECTIONS) as $projection) {
                $source = self::sourceFor($projection);
                $replica = (int) $pdo->query('SELECT COUNT(*) FROM `' . self::table($projection) . '`')->fetchColumn();
                $master = (int) $pdo->query('SELECT COUNT(*) FROM ' . $source)->fetchColumn();
                $out[] = [
                    'projection' => $projection,
                    'source_view' => $source,
                    'rows_count' => $replica,
                    'master_rows' => $master,
                    'realtime' => $replica === $master,
                    'as_of' => $pdo->query('SELECT NOW()')->fetchColumn(),
                    'status' => 'live',
                ];
            }
            return $out;
        }, ConnectionManager::NS_READS);
    }

    /**
     * Field-projected read against the replica. View -> one-level read that is
     * always current. Only $fields columns are fetched; both $fields and
     * $filters keys are whitelisted against the live column list of the replica
     * view so a caller can never read or filter on an invented column.
     *
     * @param array<int,string>            $fields
     * @param array<string,mixed>          $filters
     * @param array<int,string>|string     $orderBy  e.g. ['student_id','DESC']
     *
     * @throws \DomainException unknown projection/field/filter
     */
    public static function query(string $projection, array $fields, array $filters = [], int $limit = 200, int $offset = 0, array $orderBy = []): array
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown read-replica projection '{$projection}'.", 404);
        }
        if ($limit < 1) {
            throw new \DomainException('Replica query limit must be >= 1.', 422);
        }
        $limit = min($limit, self::MAX_QUERY_LIMIT);
        $offset = max(0, min($offset, self::MAX_OFFSET));

        // Env-agnostic routing: when the reads schema is not deployed (e.g. a
        // shared host without KingsWayReads), serve the read from the identical
        // master view on the current connection instead of failing. Because
        // replica projections are one-level views over the same master view,
        // the result is byte-identical - only the serving schema changes.
        $namespace = self::replicaReachable($projection)
            ? ConnectionManager::NS_READS
            : ConnectionManager::NS_MASTER;

        return ConnectionManager::run(static function (PDO $pdo) use ($projection, $fields, $filters, $limit, $offset, $orderBy, $namespace): array {
            $table = ($namespace === ConnectionManager::NS_READS)
                ? self::table($projection)
                : self::PROJECTIONS[$projection];
            $cols = self::tableColumns($pdo, $table, $namespace);
            if ($fields === []) {
                $fields = $cols;
            }
            $projected = [];
            foreach ($fields as $field) {
                if (!in_array($field, $cols, true)) {
                    throw new \DomainException("Unknown replica column '{$field}' on {$projection}.", 422);
                }
                $projected[] = '`' . str_replace('`', '', $field) . '`';
            }

            $where = [];
            $params = [];
            foreach ($filters as $key => $value) {
                if (!in_array($key, $cols, true)) {
                    throw new \DomainException("Unknown replica filter column '{$key}' on {$projection}.", 422);
                }
                $col = '`' . str_replace('`', '', $key) . '`';
                if (is_array($value)) {
                    $placeholders = [];
                    foreach ($value as $i => $v) {
                        $ph = ':f_' . $key . '_' . $i;
                        $placeholders[] = $ph;
                        $params[$ph] = $v;
                    }
                    $where[] = "{$col} IN (" . implode(',', $placeholders) . ')';
                } else {
                    $ph = ':f_' . $key;
                    $where[] = "{$col} = {$ph}";
                    $params[$ph] = $value;
                }
            }

            $order = '';
            if ($orderBy !== []) {
                $orderCol = (string) $orderBy[0];
                $dir = isset($orderBy[1]) ? strtoupper((string) $orderBy[1]) : 'ASC';
                if (!in_array($orderCol, $cols, true)) {
                    throw new \DomainException("Unknown replica order column '{$orderCol}'.", 422);
                }
                $order = ' ORDER BY `' . str_replace('`', '', $orderCol) . '` ' . ($dir === 'DESC' ? 'DESC' : 'ASC');
            }

            $sql = 'SELECT ' . implode(',', $projected) . " FROM `{$table}`";
            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= $order . " LIMIT {$limit} OFFSET {$offset}";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }, $namespace);
    }

    /**
     * Column allowlist for a projection view, read from whichever schema is
     * serving the query (replica view or master view fallback).
     *
     * @return array<int,string>
     */
    private static function tableColumns(PDO $pdo, string $table, string $namespace): array
    {
        $rows = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=" . $pdo->quote(ConnectionManager::schemaFor($namespace)) . " AND TABLE_NAME=" . $pdo->quote($table) . " ORDER BY ORDINAL_POSITION"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($rows === []) {
            throw new \DomainException("Replica view '{$table}' has no columns.", 503);
        }
        return array_values(array_filter($rows));
    }
}