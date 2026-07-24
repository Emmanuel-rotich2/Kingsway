<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;

/**
 * Telemetry Controller
 *
 * Receives client-side error reports and performance/usage telemetry posted by
 * ErrorReporter (js/core/error_reporter.js). The browser batches these and sends
 * them fire-and-forget; this endpoint must acknowledge quickly and never 401
 * (it is registered as a public endpoint in AuthMiddleware so a mid-refresh
 * token can't trap the reporter in a retry loop).
 *
 * Entries are appended to logs/telemetry.log (one JSON object per line) so they
 * can be ingested by a log pipeline later without a schema migration. Sending
 * timestamps, ids and payload structure come straight from the client.
 */
class TelemetryController extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('telemetry');
    }

    /**
     * POST /api/telemetry/data
     */
    public function postData($id = null, $data = [])
    {
        return $this->ingest('telemetry', $data);
    }

    /**
     * POST /api/telemetry/errors
     */
    public function postErrors($id = null, $data = [])
    {
        return $this->ingest('error', $data);
    }

    /**
     * Resolve a directory we can actually write to (mirrors BaseAPI's fallback:
     * project logs/ if writable, otherwise a per-user temp dir). Telemetry must
     * never fail loud when the web-server user lacks write access to the app dir.
     */
    private function resolveLogDir(): string
    {
        $candidates = [
            dirname(__DIR__, 2) . '/logs',
            sys_get_temp_dir() . '/kingsway_logs',
            sys_get_temp_dir(),
        ];
        foreach ($candidates as $dir) {
            try {
                $this->ensureManagedDirectory($dir);
            } catch (\Throwable $exception) {
                continue;
            }
            if (is_dir($dir) && is_writable($dir)) {
                return $dir;
            }
        }
        return sys_get_temp_dir();
    }

    /**
     * Normalize and append the batch to the telemetry log file.
     * Failures are swallowed: a broken telemetry sink must never surface an
     * error to the client or trigger retry storms.
     */
    private function ingest(string $kind, $payload): array
    {
        try {
            $entries = $payload['telemetry'] ?? $payload['errors'] ?? $payload;
            if (!is_array($entries)) {
                $entries = [$entries];
            }

            $logDir = $this->resolveLogDir();

            $userId = $this->user_id ?? ($_SERVER['auth_user']['user_id'] ?? null);
            $line = json_encode([
                'kind'      => $kind,
                'received'  => date('c'),
                'user_id'   => $userId,
                'entries'   => $entries,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($line !== false) {
                @$this->writeManagedFile($logDir . '/telemetry.log', $line . "\n", FILE_APPEND | LOCK_EX);
            }
        } catch (\Throwable $e) {
            // Swallow — telemetry must never fail loud.
            error_log('TelemetryController ingest failed: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'status'  => 'success',
            'data'    => null,
            'message' => 'Telemetry received',
            'errors'  => [],
            'code'    => 200,
        ];
    }
}
