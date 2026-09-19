<?php

namespace App\API\Services;

use App\Database\ConnectionManager;
use PDO;

/**
 * ReadReplicaService - explicit read-model routing (roadmap §4.5).
 *
 * The transactional master (KingsWayAcademy) is the single WRITE authority.
 * Reads are projected into the KingsWayReads schema as allowlisted `mv_<name>`
 * objects. During migration these may be pass-through views; later a
 * projection may become a materialized table or a physical replica object.
 * Storage mode is therefore reported explicitly and a pass-through view is
 * never described as a physical offload.
 *
 * A pass-through view is not an offload: MySQL still evaluates its source
 * query on the master schema. Only a materialized table or a separately
 * connected physical replica counts as an offloaded read target.
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
        // These are candidates for materialized read models. Until their
        // materialized target exists, routing deliberately falls back to the
        // master source and reports the projection as not offloaded.
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

    /**
     * Read-model safety policy. Storage can change without changing callers.
     *
     * @var array<string,array{storage_mode:string,sensitivity:string,max_age_seconds:int}>
     */
    public const POLICIES = [
        'student_fee_ledger' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'financial', 'max_age_seconds' => 60],
        'collection_rate_by_class' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'financial', 'max_age_seconds' => 300],
        'student_attendance_analytics' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'student_fee_balances' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'financial', 'max_age_seconds' => 60],
        'student_term_performance' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'class_learning_area_performance' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'student_learning_progress' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 900],
        'budget_utilization' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'financial', 'max_age_seconds' => 300],
        'staff_daily_register' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'staff', 'max_age_seconds' => 300],
        'student_attendance_summary' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'staff_workload' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'staff', 'max_age_seconds' => 900],
        'dormitory_occupancy' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'student_transport_summary' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'personal', 'max_age_seconds' => 300],
        'student_health_summary' => ['storage_mode' => 'pass_through_view', 'sensitivity' => 'health', 'max_age_seconds' => 300],
        'fee_collection_monthly_trend' => ['storage_mode' => 'materialized_table', 'sensitivity' => 'financial', 'max_age_seconds' => 900],
    ];

    /** Materialized targets use separate names from the legacy pass-through views. */
    public const MATERIALIZED_TARGETS = [
        'fee_collection_monthly_trend' => 'mmv_fee_collection_monthly_trend',
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
        if (StickyMasterService::isPinned()) {
            return false;
        }
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
        if (isset(self::MATERIALIZED_TARGETS[$projection])) {
            return self::MATERIALIZED_TARGETS[$projection];
        }
        $safe = preg_replace('/[^a-z0-9_]/', '', $projection);
        return 'mv_' . $safe;
    }

    public static function hasProjection(string $projection): bool
    {
        return isset(self::PROJECTIONS[$projection]);
    }

    /** @return array{storage_mode:string,sensitivity:string,max_age_seconds:int} */
    public static function policy(string $projection): array
    {
        if (!self::hasProjection($projection)) {
            throw new \DomainException("Unknown read-replica projection '{$projection}'.", 404);
        }
        return self::POLICIES[$projection] ?? [
            'storage_mode' => 'unclassified',
            'sensitivity' => 'unknown',
            'max_age_seconds' => 0,
        ];
    }

    /**
     * True only when this projection is backed by an actual materialized
     * object. A view in KingsWayReads is deliberately not sufficient.
     */
    public static function isOffloaded(string $projection): bool
    {
        if (!self::hasProjection($projection) || StickyMasterService::isPinned()) {
            return false;
        }
        $policy = self::policy($projection);
        if ($policy['storage_mode'] !== 'materialized_table') {
            return false;
        }
        return self::replicaReachable($projection);
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
        // A mutation may pin the current request to the master until a future
        // physical replica has had time to catch up. Returning the source view
        // keeps callers correct even when this projection was previously
        // memoized as reachable during the same request.
        if (StickyMasterService::isPinned()) {
            return self::PROJECTIONS[$projection];
        }
        if (!isset(self::$refCache[$projection])) {
            $replicaSchema = ConnectionManager::schemaFor(ConnectionManager::NS_READS);
            $view = self::table($projection);
            $policy = self::policy($projection);
            // Probe information_schema WITHOUT switching schema: the probe runs
            // on whatever schema is currently active (master, usually) and
            // information_schema is server-global. This keeps the call safe
            // inside an open transaction, where ConnectionManager::run() would
            // refuse to switch.
            try {
                $reachable = (bool) ConnectionManager::run(static function (PDO $pdo) use ($replicaSchema, $view): bool {
                    $stmt = $pdo->prepare(
                        'SELECT COUNT(*) FROM information_schema.TABLES '
                        . 'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? '
                        . 'AND TABLE_TYPE = ?'
                    );
                    $stmt->execute([$replicaSchema, $view, 'BASE TABLE']);
                    return (int) $stmt->fetchColumn() > 0;
                }, ConnectionManager::NS_MASTER);
            } catch (\Throwable $e) {
                $reachable = false;
            }
            if ($policy['storage_mode'] !== 'materialized_table') {
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
     * Verify a reachable materialized projection using row-count parity.
     */
    public static function liveFresh(string $projection): bool
    {
        if (!self::hasProjection($projection)) {
            return false;
        }
        // A pass-through view is current but not offloaded. It must not be
        // reported as a healthy replica because it still consumes master work.
        if (!self::replicaReachable($projection)) {
            return false;
        }
        return (bool) ConnectionManager::run(static function (PDO $pdo) use ($projection): bool {
            $source = self::sourceFor($projection);
            $replica = (int) $pdo->query('SELECT COUNT(*) FROM `' . self::table($projection) . '`')->fetchColumn();
            $master = (int) $pdo->query('SELECT COUNT(*) FROM ' . $source)->fetchColumn();
            return $replica === $master;
        }, ConnectionManager::NS_READS);
    }

    /**
     * Freshness/watermark status for every projection. `reads_meta` may later
     * describe a materialized table, while pass-through views are clearly
     * marked current-but-not-offloaded.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function freshness(): array
    {
        $result = [];
        foreach (array_keys(self::PROJECTIONS) as $projection) {
            $policy = self::policy($projection);
            if (!self::isOffloaded($projection)) {
                $result[] = [
                    'projection' => $projection,
                    'source_view' => self::sourceFor($projection),
                    'rows_count' => null,
                    'master_rows' => null,
                    'realtime' => false,
                    'as_of' => null,
                    'status' => $policy['storage_mode'] === 'pass_through_view'
                        ? 'not_offloaded_pass_through'
                        : 'unavailable_master_fallback',
                    'storage_mode' => $policy['storage_mode'],
                    'sensitivity' => $policy['sensitivity'],
                    'max_age_seconds' => $policy['max_age_seconds'],
                ];
                continue;
            }
            $result[] = ConnectionManager::run(static function (PDO $pdo) use ($projection, $policy): array {
                $source = self::sourceFor($projection);
                $replica = (int) $pdo->query('SELECT COUNT(*) FROM `' . self::table($projection) . '`')->fetchColumn();
                $master = (int) $pdo->query('SELECT COUNT(*) FROM ' . $source)->fetchColumn();
                $stored = [];
                try {
                    $meta = $pdo->prepare(
                        'SELECT rows_count, source_watermark, as_of, refreshed_at, status, storage_mode, sensitivity, max_age_seconds, last_error '
                        . 'FROM `reads_meta` WHERE projection = ? LIMIT 1'
                    );
                    $meta->execute([$projection]);
                    $stored = $meta->fetch(PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $ignored) {
                    // Older installations may not have reads_meta yet.
                }
                $storageMode = (string) ($stored['storage_mode'] ?? $policy['storage_mode']);
                $refreshedAt = $stored['refreshed_at'] ?? null;
                $ageSeconds = $refreshedAt !== null ? max(0, time() - (int) strtotime((string) $refreshedAt)) : null;
                $withinFreshness = $ageSeconds !== null && $ageSeconds <= $policy['max_age_seconds'];
                return [
                    'projection' => $projection,
                    'source_view' => $source,
                    'rows_count' => $replica,
                    'master_rows' => $master,
                    'realtime' => false,
                    'as_of' => $pdo->query('SELECT NOW()')->fetchColumn(),
                    'status' => $withinFreshness && $replica === $master ? 'materialized_parity' : 'materialized_unhealthy',
                    'storage_mode' => $storageMode,
                    'sensitivity' => $stored['sensitivity'] ?? $policy['sensitivity'],
                    'max_age_seconds' => $policy['max_age_seconds'],
                    'source_watermark' => $stored['source_watermark'] ?? null,
                    'refreshed_at' => $refreshedAt,
                    'age_seconds' => $ageSeconds,
                    'within_freshness' => $withinFreshness,
                    'last_error' => $stored['last_error'] ?? null,
                ];
            }, ConnectionManager::NS_READS);
        }
        return $result;
    }

    /**
     * Field-projected read against an offloaded materialized projection. When
     * that projection is unavailable, the same allowlisted query falls back
     * to the master source for correctness. Only $fields columns are fetched; both $fields and
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

        // A missing or pass-through projection falls back to the master for
        // correctness, but is explicitly not counted as an offloaded read.
        $namespace = self::isOffloaded($projection)
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
     * Column allowlist for a projection table or master source view, read from
     * whichever schema is serving the query.
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
