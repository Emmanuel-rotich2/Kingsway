<?php

declare(strict_types=1);

namespace App\API\Services;

/**
 * Maintenance boundary for bounded local buffers.
 *
 * This service only prunes non-authoritative temporary state and emits bounded
 * operational counters. It never inspects or logs cached values.
 */
final class LocalBufferMaintenanceService
{
    /** @param array<string,string> $directories */
    public function __construct(private array $directories)
    {
    }

    /** @return array<string,mixed> */
    public function run(): array
    {
        $started = microtime(true);
        $report = [
            'status' => 'ok',
            'scanned' => 0,
            'skipped' => 0,
            'failed' => 0,
            'pruned' => 0,
            'buffers' => [],
        ];

        foreach ($this->directories as $name => $directory) {
            if (!is_string($directory) || $directory === '' || !is_dir($directory)) {
                $report['skipped']++;
                $report['buffers'][$name] = ['status' => 'skipped'];
                continue;
            }
            $report['scanned']++;
            try {
                $buffer = new LocalSqliteBuffer($directory);
                $health = $buffer->health();
                $pruned = $buffer->prune();
                $report['pruned'] += $pruned;
                $report['buffers'][$name] = ['status' => $health['status'], 'health' => $health, 'pruned' => $pruned];
                if ($health['status'] !== 'ok') $report['status'] = 'degraded';
            } catch (\Throwable $error) {
                $report['failed']++;
                $report['status'] = 'degraded';
                $report['buffers'][$name] = ['status' => 'failed', 'error' => substr($error->getMessage(), 0, 160)];
            }
        }

        if ($report['failed'] > 0) $report['status'] = 'degraded';
        $report['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        return $report;
    }
}
