<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;

/**
 * RealtimeGatewayHealth - periodic observability for the Node gateway.
 *
 * The gateway exposes deterministic counters at /internal/stats
 * (worker-secret, loopback) but nothing read them, so the realtime layer was
 * invisible to the system-health surfaces. This service scrapes those
 * counters and journals them into the file-based `realtime` journal, from
 * the hourly curl cleanup endpoint (RealtimeController::postCleanup) - the
 * established "wire maintenance into an endpoint the crontab already calls"
 * pattern. Deterministic counters only; no browser data, no learner data.
 *
 * Failure posture: observability is best-effort. A dormant gateway (no
 * routing keys configured) or an unreachable one is reported, never thrown.
 */
final class RealtimeGatewayHealth
{
    private const SERVICE = 'realtime_node';
    private const STATS_PATH = '/internal/stats';
    private const TIMEOUT_SECONDS = 4;

    /** @var callable|null Injectable config reader for hermetic tests. */
    private static $configReader = null;

    /** @var callable|null Injectable transport for hermetic tests. */
    private static $transportOverride = null;

    /** Test hook: inject reader/transport; pass nulls to restore. */
    public static function injectForTests(?callable $configReader = null, ?callable $transport = null): void
    {
        self::$configReader = $configReader;
        self::$transportOverride = $transport;
    }

    /**
     * Scrape the gateway's deterministic counters and journal them.
     *
     * @return array<string,mixed> A small report for the curl caller. Never throws.
     */
    public static function journalSnapshot(): array
    {
        try {
            $reader = self::$configReader ?? static fn (string $key, $default = null) => Config::get($key, $default);
            $client = new InterServiceClient(self::$transportOverride, $reader);
            if (!$client->isConfigured(self::SERVICE)) {
                return ['status' => 'dormant'];
            }

            [$raw, $status] = $client->get(self::SERVICE, self::STATS_PATH, [
                'Accept: application/json',
                'X-Kingsway-Worker-Secret: ' . self::workerSecret($reader),
            ], self::TIMEOUT_SECONDS);

            if ($status < 200 || $status >= 300 || !is_string($raw)) {
                FileLogger::write('realtime', [
                    'event' => 'gateway.health_unreachable',
                    'http_status' => $status,
                ]);
                return ['status' => 'unreachable', 'http_status' => $status];
            }

            $stats = json_decode($raw, true);
            if (!is_array($stats)) {
                return ['status' => 'unparsable'];
            }

            $snapshot = [
                'connections_active' => (int) ($stats['connections_active'] ?? 0),
                'queue_depth' => (int) ($stats['queue_depth'] ?? 0),
                'backlog_bytes' => (int) ($stats['backlog_bytes'] ?? 0),
                'bus_mode' => (string) ($stats['bus_mode'] ?? 'unknown'),
                'coalesce_pending' => (int) ($stats['coalesce_pending'] ?? 0),
                'uptime_seconds' => (int) ($stats['uptime_seconds'] ?? 0),
            ];
            FileLogger::write('realtime', [
                'event' => 'gateway.health',
                'link' => $client->linkInUse(self::SERVICE),
            ] + $snapshot);

            return ['status' => 'ok'] + $snapshot;
        } catch (\Throwable $error) {
            // Never fail the owning cleanup request over observability.
            FileLogger::write('realtime', [
                'event' => 'gateway.health_error',
                'exception' => get_class($error),
            ]);
            return ['status' => 'error'];
        }
    }

    private static function workerSecret(callable $reader): string
    {
        $secret = (string) $reader('NODE_REALTIME_PUBLISH_SECRET', '');
        if ($secret === '') {
            $secret = (string) $reader('KINGSWAY_WORKER_SECRET', '');
        }
        return $secret;
    }
}
