<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Database\ConnectionManager;
use App\Database\Database;
use PDO;

/**
 * ReadProjectionHealthService
 *
 * One bounded sweep across every materialized read projection: does the
 * target table exist, does reads_meta track it, and is its age inside the
 * policy window? This turns "yet another page broke with 'unavailable or
 * stale'" into a single report naming every affected view at once.
 *
 * Wired into the hourly cleanup cron (RealtimeController::postCleanup) so
 * drift is detected by the scheduler, not by a user's broken page, and safe
 * to invoke directly for on-demand diagnosis.
 */
final class ReadProjectionHealthService
{
    /**
     * @return array{checked:int, fresh:int, stale:list<string>, missing_target:list<string>, never_synced:list<string>}
     */
    public static function sweep(): array
    {
        $pdo = Database::getInstance()->getConnection();
        $readsSchema = str_replace('`', '', ConnectionManager::schemaFor(ConnectionManager::NS_READS));

        // One query per fact source, not one per projection: ~250 projections
        // must never become ~250 sweeps.
        $targets = $pdo->prepare(
            'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ?'
        );
        $targets->execute([$readsSchema, 'BASE TABLE']);
        $existing = array_fill_keys($targets->fetchAll(PDO::FETCH_COLUMN), true);

        $metaStmt = $pdo->prepare(
            "SELECT projection, status, refreshed_at,
                    TIMESTAMPDIFF(SECOND, refreshed_at, NOW()) AS age_seconds
             FROM `{$readsSchema}`.`reads_meta`"
        );
        $metaStmt->execute();
        $meta = [];
        foreach ($metaStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $meta[(string) $row['projection']] = $row;
        }

        $report = [
            'checked' => 0,
            'fresh' => 0,
            'stale' => [],
            'missing_target' => [],
            'never_synced' => [],
        ];

        foreach (ReadReplicaService::MATERIALIZED_TARGETS as $projection => $target) {
            $report['checked']++;
            if (!isset($existing[$target])) {
                $report['missing_target'][] = $projection;
                continue;
            }
            $row = $meta[$projection] ?? null;
            if ($row === null || ($row['status'] ?? '') !== 'live') {
                $report['never_synced'][] = $projection;
                continue;
            }
            $maxAge = (int) (ReadReplicaService::POLICIES[$projection]['max_age_seconds'] ?? 600);
            $age = (int) ($row['age_seconds'] ?? -1);
            if ($age < 0 || $age > $maxAge) {
                $report['stale'][] = $projection . " (age {$age}s > {$maxAge}s)";
            } else {
                $report['fresh']++;
            }
        }

        $healthy = $report['missing_target'] === []
            && $report['never_synced'] === []
            && $report['stale'] === [];
        FileLogger::write('reads', [
            'event' => 'projection_health_sweep',
            'checked' => $report['checked'],
            'fresh' => $report['fresh'],
            'stale_count' => count($report['stale']),
            'missing_target_count' => count($report['missing_target']),
            'never_synced_count' => count($report['never_synced']),
            'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['HTTP_X_KINGSWAY_REQUEST_ID'] ?? ''),
        ], $healthy ? 'info' : 'warning');

        return $report;
    }
}
