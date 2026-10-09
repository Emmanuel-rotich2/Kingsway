<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Services\EventBroadcaster;
use App\API\Services\JobHandlerRegistry;
use App\API\Services\JobQueue;
use App\API\Services\ReadProjectionSynchronizer;
use App\API\Services\RealtimeScopeResolver;
use App\Config\Config;

/**
 * RealtimeController - authenticated fallback access to the real-time engine.
 *
 * Primary read path: the public static events_buffer.json is served directly by
 * the web server (zero PHP), so real-time consumers normally never hit PHP.
 *
 * This controller provides:
 *   GET /api/realtime/sync?last_id=<id>   Authenticated outbox history reader.
 *     Used only when a client has drifted behind the bounded rolling buffer
 *     (e.g. was offline), so it can catch up on missed events. It is NOT part
 *     of the routine polling loop — invoking it per-poll would reintroduce the
 *     thundering-herd problem this engine is built to avoid.
 *
 *   GET /api/realtime/buffer                Returns the current static buffer
 *     (diagnostic / debugging convenience).
 *
 * Both endpoints require a staff JWT; the outbox reader is not restricted to a
 * single role because it only relays events already queued for broadcast.
 */
class RealtimeController extends BaseAPI
{
    private const SYNC_BATCH_LIMIT = 200;

    public function __construct()
    {
        parent::__construct('realtime');
    }

    /**
     * GET /api/realtime/sync
     *
     * Authenticated catch-up reader for consumers that fell behind the static
     * buffer window. Returns events with id strictly greater than last_id.
     */
    public function getSync($id = null, $data = [])
    {
        $lastId = isset($data['last_id']) ? max(0, (int) $data['last_id']) : 0;
        $allowedScopes = $this->allowedScopes();

        try {
            $rows = \App\API\Services\EventBroadcaster::syncBatch(
                $this->db, $lastId, $allowedScopes, self::SYNC_BATCH_LIMIT
            );

            $events = [];
            $newLastId = $lastId;
            foreach ($rows as $row) {
                $decoded = json_decode((string) $row['payload'], true);
                $newLastId = (int) $row['id'];
                $events[] = [
                    'id'         => (int) $row['id'],
                    'domain'     => $row['domain'],
                    'event_name' => $row['event_name'],
                    'payload'    => is_array($decoded) ? $decoded : null,
                    'created_at' => $row['created_at'],
                ];
            }

            // Signal whether the caller has logically caught up to the newest
            // event in the whole outbox, so it knows when history is complete.
            $latestInOutbox = $this->latestOutboxId($allowedScopes);

            return [
                'success'   => true,
                'status'    => 'success',
                'data'      => [
                    'events'      => $events,
                    'latest_id'   => $newLastId,
                    'previous_id' => $lastId,
                    'caught_up'   => $newLastId >= $latestInOutbox,
                    'as_of'       => date('Y-m-d H:i:s'),
                ],
                'message'   => 'OK',
                'errors'    => [],
                'code'      => 200,
            ];
        } catch (\Exception $e) {
            $this->logError($e, 'RealtimeController::getSync');
            return $this->errorResponse('Unable to synchronise real-time events.', 500);
        }
    }

    /**
     * GET /api/realtime/stream-token
     * Issue a short-lived capability containing only the scopes this
     * authenticated user already receives from the static realtime path.
     */
    public function getStreamToken($id = null, $data = [])
    {
        $user = $this->getCurrentUser() ?: [];
        $userId = (int) ($user['user_id'] ?? $user['id'] ?? $this->user_id ?? 0);
        $url = trim((string) Config::get('NODE_REALTIME_URL', Config::get('NODE_REALTIME_PUBLIC_URL', '')));
        $url = rtrim($url, '/');
        if ($userId < 1) {
            return $this->errorResponse('Authentication required.', 401);
        }
        if ($url === '') {
            return $this->errorResponse('Realtime stream is not configured.', 503);
        }

        $channels = array_values(array_unique(array_filter(
            $this->allowedScopes(),
            static fn($scope): bool => is_string($scope)
                && preg_match('/^[a-zA-Z0-9:_-]{1,128}$/', $scope) === 1
        )));
        if ($channels === [] || count($channels) > 100) {
            return $this->errorResponse('No realtime scopes are available.', 403);
        }

        $issuedAt = time();
        $expiresAt = $issuedAt + 240;
        $claims = [
            'iss' => (string) Config::get('JWT_ISSUER', JWT_ISSUER),
            'aud' => (string) Config::get('JWT_AUDIENCE', JWT_AUDIENCE),
            'sub' => (string) $userId,
            'user_id' => $userId,
            'realtime' => true,
            'channels' => $channels,
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            'jti' => bin2hex(random_bytes(16)),
        ];

        try {
            $token = \Firebase\JWT\JWT::encode($claims, JWT_SECRET, 'HS256');
            header('Cache-Control: no-store, private');
            return $this->successResponse([
                'token' => $token,
                'url' => $url,
                'expires_at' => gmdate('c', $expiresAt),
            ], 'Realtime stream capability issued', 200);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[RealtimeController] stream capability issuance failed: ' . get_class($error));
            return $this->errorResponse('Unable to establish realtime stream.', 500);
        }
    }

    /**
     * POST /api/realtime/worker
     *
     * Internal fallback worker endpoint for DirectAdmin cron when CLI PHP lacks
     * the pdo_mysql driver. Protected by the worker secret, not a staff JWT
     * (registered as a public endpoint in AuthMiddleware) — mirroring
     * CommunicationsController::postProcessOutbox.
     */
    public function postWorker($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $limit = max(1, min(50, (int) ($data['limit'] ?? 10)));
        $pythonTypes = $this->activePythonJobTypes();
        JobQueue::recoverStale(15, $pythonTypes);
        $ids = JobQueue::claimBatch($limit, $pythonTypes);
        // A dead or slow Python runtime must never strand its job families.
        // The Python-side lease recovery only runs while Python itself is
        // reachable, so backstop it here, and take over jobs that have
        // starved past the takeover window whenever a registered PHP
        // handler can execute them safely (the projection refresh family
        // has a first-class PHP fallback by design).
        if ($pythonTypes !== []) {
            JobQueue::recoverStaleForTypes($pythonTypes, 15);
            $takeoverTypes = array_values(array_filter(
                $pythonTypes,
                static fn (string $type): bool => JobHandlerRegistry::resolve($type) !== null
            ));
            if ($takeoverTypes !== []) {
                $ids = array_merge($ids, JobQueue::claimStarvedForTypes($takeoverTypes, 120, max(1, min(5, $limit))));
            }
        }
        $done = 0;
        $failed = 0;
        $retried = 0;

        foreach ($ids as $id) {
            $job = JobQueue::fetchJob($id);
            if ($job === null) {
                continue;
            }
            $payload = $job['payload'];
            try {
                $handler = JobHandlerRegistry::resolve($job['job_type']);
                if ($handler === null) {
                    throw new \RuntimeException("No registered handler for job_type '{$job['job_type']}'");
                }
                $handler($payload, $this->db);
                JobQueue::markDone($id);
                $done++;
            } catch (\Throwable $e) {
                $failureStatus = JobQueue::markFailed($id, $e->getMessage());
                if ($failureStatus === JobQueue::STATUS_PENDING) {
                    $retried++;
                } else {
                    $failed++;
                }
            }
        }

        return $this->successResponse(
            ['claimed' => count($ids), 'done' => $done, 'retried' => $retried, 'failed' => $failed],
            'Worker processed',
            200
        );
    }

    /**
     * POST /api/realtime/cleanup
     *
     * Bounded retention maintenance for shared-hosting cron. This deliberately
     * avoids schema operations and table locks; it only removes expired queue
     * rows, old outbox events, rotated static buffers, and expired local
     * SQLite cache entries.
     *
     * Scheduled from the crontab as a single curl call, which is how
     * HostAfrica shared hosting runs jobs. There is deliberately no
     * scripts/cron dispatcher in this path.
     */
    /**
     * POST /api/realtime/maintenance
     * Daily maintenance (curl crontab line with X-Kingsway-Worker-Secret).
     * Replaces the legacy webroot maintenance.php script per the curl-only
     * scheduling rule.
     */
    public function postMaintenance($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }
        try {
            (new \App\API\Services\ScheduledMaintenanceService())->runDailyMaintenance();
            $this->contract(\App\API\Services\UploadService::class)->writeFile(
                dirname(__DIR__, 2) . '/logs/maintenance.log',
                date('Y-m-d H:i:s') . " - Maintenance tasks completed successfully\n",
                FILE_APPEND
            );
            return $this->successResponse(['status' => 'completed']);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[RealtimeController] maintenance: ' . $e->getMessage());
            return $this->errorResponse('Maintenance failed.', 500);
        }
    }

    public function postCleanup($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $report = [
            'buffers_purged' => EventBroadcaster::purgeOldBufferFiles(48),
        ];

        $report = array_merge($report, JobQueue::purgeOld());

        $report['events_purged'] = \App\API\Services\EventBroadcaster::purgeOldEvents($this->db);

        // Expired SQLite cache rows are only removed lazily when a key is read
        // again, so a key that is written once and never revisited would
        // otherwise persist indefinitely. Prune them here, on the same curl
        // schedule as the rest of the retention work.
        try {
            $temp = sys_get_temp_dir();
            $report['local_buffers'] = (new \App\API\Services\LocalBufferMaintenanceService([
                'shared_cache' => $temp . '/kingsway_cache/sqlite',
                'response_cache' => $temp . '/kingsway_cache_responses/sqlite',
                'offline_replay' => $temp . '/kingsway_offline_replay/store',
                'local_buffers' => $temp . '/kingsway_local_buffers',
            ]))->run();
        } catch (\Throwable $error) {
            // Local cache pruning is best-effort and must never fail the job.
            \App\API\Services\Logger::legacyError('[RealtimeController] local buffer maintenance failed: ' . $error->getMessage());
            $report['local_buffers'] = ['status' => 'degraded'];
        }

        // Sweep every materialized projection on the same hourly schedule so
        // staleness, missing targets, and never-synced views are surfaced by
        // the scheduler instead of by a user's broken page.
        try {
            $report['projection_health'] = \App\API\Services\ReadProjectionHealthService::sweep();
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[RealtimeController] projection health sweep failed: ' . $error->getMessage());
            $report['projection_health'] = ['status' => 'degraded'];
        }

        return $this->successResponse($report, 'Cleanup completed', 200);
    }

    /** POST /api/realtime/sync-projection — HostAfrica projection worker. */
    public function postSyncProjection($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }
        $batch = filter_var($data['projection_batch'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 4],
        ]);
        if ($batch !== false && $batch !== null) {
            $batchProjections = ReadProjectionSynchronizer::batchQueueSlice($batch);
            $queued = [];
            $skipped = 0;
            foreach ($batchProjections as $projection) {
                // One outstanding refresh job per projection: without this the
                // every-5-minute batch enqueue grows an unbounded duplicate
                // backlog whenever the worker drains slower than the batch
                // cadence, and duplicate jobs collide on the projection lock.
                // A job that has sat undrained for five minutes is jammed,
                // not pending: cancel it so the queue can accept fresh work
                // instead of short-circuiting every sync to "already queued"
                // while the projection drifts stale.
                $activeJobId = JobQueue::findActiveByTypeAndPayload('reads.projection.refresh', 'projection', $projection);
                if ($activeJobId !== null && !JobQueue::activeJobOlderThan($activeJobId, 300)) {
                    $skipped++;
                    continue;
                }
                if ($activeJobId !== null) {
                    JobQueue::cancelJob($activeJobId);
                }
                $queued[] = JobQueue::push('reads.projection.refresh', [
                    'projection' => $projection,
                    'requested_by' => 'worker-cron',
                    'idempotency_key' => 'projection:' . $projection . ':' . date('YmdHi'),
                ], 0, 5, 60);
            }
            return $this->successResponse(
                ['batch' => $batch, 'count' => count($queued), 'skipped' => $skipped, 'job_ids' => $queued, 'status' => 'queued'],
                'Read projection refresh batch queued',
                202
            );
        }
        $projection = trim((string) ($data['projection'] ?? 'fee_collection_monthly_trend'));
        if (!ReadProjectionSynchronizer::supports($projection)) {
            return $this->errorResponse('Projection is not enabled for synchronization', 422);
        }
        try {
            /** @var \App\API\Services\ReadProjectionBridge $bridge */
            $bridge = $this->contract(\App\API\Services\ReadProjectionBridge::class);
            if (!$bridge->enabled()) {
                $result = ReadProjectionSynchronizer::synchronize($projection);
                $result['engine'] = 'php_fallback';
                return $this->successResponse($result, 'Read projection synchronized', 200);
            }
            $activeJobId = JobQueue::findActiveByTypeAndPayload('reads.projection.refresh', 'projection', $projection);
            if ($activeJobId !== null && !JobQueue::activeJobOlderThan($activeJobId, 300)) {
                return $this->successResponse(
                    ['job_id' => $activeJobId, 'projection' => $projection, 'status' => 'already_queued'],
                    'Read projection refresh already queued',
                    200
                );
            }
            if ($activeJobId !== null) {
                // Same anti-jam guard as the batch path: an undrained job is
                // cancelled and replaced rather than blocking new syncs.
                JobQueue::cancelJob($activeJobId);
            }
            $jobId = JobQueue::push('reads.projection.refresh', [
                'projection' => $projection,
                'requested_by' => 'worker-cron',
                'idempotency_key' => 'projection:' . $projection . ':' . date('YmdHi'),
            ], 0, 5, 60);
            return $this->successResponse(
                ['job_id' => $jobId, 'projection' => $projection, 'status' => 'queued'],
                'Read projection refresh queued',
                202
            );
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[RealtimeController] projection refresh enqueue failed: ' . $e->getMessage());
            return $this->errorResponse('Read projection refresh could not be queued', 500);
        }
    }

    /** Python worker claim; payloads are returned only for registered Python job types. */
    public function postPythonJobClaim($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) return $this->errorResponse('Invalid worker credential', 403);
        $pythonTypes = $this->activePythonJobTypes();
        if ($pythonTypes === []) return $this->successResponse(['job' => null], 'No Python handlers are enabled', 200);
        JobQueue::recoverStaleForTypes($pythonTypes, 60);
        $ids = JobQueue::claimBatchForTypes($pythonTypes, 1);
        if ($ids === []) return $this->successResponse(['job' => null], 'No Python jobs available', 200);
        $job = JobQueue::fetchJob((int) $ids[0]);
        if ($job === null || !in_array($job['job_type'], JobQueue::PYTHON_JOB_TYPES, true)) {
            return $this->errorResponse('Claimed Python job could not be loaded', 500);
        }
        return $this->successResponse([
            'job' => [
                'id' => (int) $job['id'],
                'job_type' => $job['job_type'],
                'payload' => $job['payload'],
                'attempts' => (int) $job['attempts'],
            ],
        ], 'Python job claimed', 200);
    }

    /** Return input only after rechecking the operator and exact queue lease in PHP. */
    public function postPythonJobInput($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) return $this->errorResponse('Invalid worker credential', 403);
        $jobId = max(0, (int) ($data['job_id'] ?? 0));
        $attempts = max(-1, (int) ($data['attempts'] ?? -1));
        $job = $jobId > 0 ? JobQueue::fetchJob($jobId) : null;
        if ($job === null || $job['status'] !== JobQueue::STATUS_PROCESSING
            || ($job['job_type'] ?? '') !== 'automation.run'
            || !in_array('automation.run', $this->activePythonJobTypes(), true)
            || $attempts !== (int) $job['attempts']) {
            return $this->errorResponse('Python job lease is not valid for input access', 409);
        }
        try {
            $input = $this->contract(\App\API\Services\automations\AutomationArtifacts::class)
                ->preparePythonJob($job['payload'], $this->db);
            return $this->successResponse($input, 'Authorized automation input', 200);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[RealtimeController] Python input preparation failed: ' . get_class($error));
            return $this->errorResponse('Automation input is unavailable or no longer authorized', 422);
        }
    }

    /** Python worker acknowledges completion through the PHP-owned queue lifecycle. */
    public function postPythonJobComplete($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) return $this->errorResponse('Invalid worker credential', 403);
        $jobId = max(0, (int) ($data['job_id'] ?? 0));
        $claimedAttempts = max(-1, (int) ($data['attempts'] ?? -1));
        $job = $jobId > 0 ? JobQueue::fetchJob($jobId) : null;
        if ($job === null || $job['status'] !== JobQueue::STATUS_PROCESSING
            || !in_array($job['job_type'], JobQueue::PYTHON_JOB_TYPES, true)
            || $claimedAttempts !== (int) $job['attempts']) {
            return $this->errorResponse('Python job is not owned by this worker', 409);
        }
        $descriptor = null;
        if ($job['job_type'] === 'automation.run') {
            if (!in_array('automation.run', $this->activePythonJobTypes(), true) || !is_array($data['result'] ?? null)) {
                return $this->errorResponse('Automation result is not available to this worker', 422);
            }
            try {
                $descriptor = $this->contract(\App\API\Services\automations\AutomationArtifacts::class)
                    ->stagePythonResult($job['payload'], $data['result'], $this->db);
            } catch (\Throwable $error) {
                \App\API\Services\Logger::legacyError('[RealtimeController] Python artifact validation failed: ' . get_class($error));
                return $this->errorResponse('Python automation artifact failed validation', 422);
            }
        }
        if (!JobQueue::markDoneForAttempt($jobId, $claimedAttempts)) {
            return $this->errorResponse('Python job lease has been recovered', 409);
        }
        if ($descriptor !== null) {
            $this->contract(\App\API\Services\automations\AutomationArtifacts::class)->finishPythonJob($job['payload'], $descriptor);
        }
        return $this->successResponse(['job_id' => $jobId, 'status' => JobQueue::STATUS_DONE, 'artifact' => $descriptor], 'Python job completed', 200);
    }

    /** Keep a long-running Python job lease alive without allowing lease takeover. */
    public function postPythonJobHeartbeat($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) return $this->errorResponse('Invalid worker credential', 403);
        $jobId = max(0, (int) ($data['job_id'] ?? 0));
        $attempts = max(-1, (int) ($data['attempts'] ?? -1));
        $job = $jobId > 0 ? JobQueue::fetchJob($jobId) : null;
        if ($job === null || !in_array($job['job_type'], JobQueue::PYTHON_JOB_TYPES, true)
            || $attempts !== (int) $job['attempts']
            || !JobQueue::touchProcessingAttempt($jobId, $attempts)) {
            return $this->errorResponse('Python job lease is no longer active', 409);
        }
        return $this->successResponse(['job_id' => $jobId, 'status' => JobQueue::STATUS_PROCESSING], 'Python job lease renewed', 200);
    }

    /** Python worker failures use the same retry/backoff/dead-letter policy as PHP jobs. */
    public function postPythonJobFail($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) return $this->errorResponse('Invalid worker credential', 403);
        $jobId = max(0, (int) ($data['job_id'] ?? 0));
        $claimedAttempts = max(-1, (int) ($data['attempts'] ?? -1));
        $job = $jobId > 0 ? JobQueue::fetchJob($jobId) : null;
        if ($job === null || $job['status'] !== JobQueue::STATUS_PROCESSING
            || !in_array($job['job_type'], JobQueue::PYTHON_JOB_TYPES, true)
            || $claimedAttempts !== (int) $job['attempts']) {
            return $this->errorResponse('Python job is not owned by this worker', 409);
        }
        $reason = substr(trim((string) ($data['reason'] ?? 'Python worker failed')), 0, 500);
        $status = JobQueue::markFailedForAttempt($jobId, $claimedAttempts, $reason !== '' ? $reason : 'Python worker failed');
        if ($status === null) return $this->errorResponse('Python job lease has been recovered', 409);
        return $this->successResponse(['job_id' => $jobId, 'status' => $status], 'Python failure recorded', 200);
    }

    private function hasValidWorkerCredential(): bool
    {
        $expected = defined('COMMUNICATION_WORKER_SECRET') ? (string) COMMUNICATION_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';

        return $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);
    }

    /** Python may claim only job families whose service configuration is active. */
    private function activePythonJobTypes(): array
    {
        $types = [];
        try {
            if ($this->contract(\App\API\Services\ReadProjectionBridge::class)->enabled()) {
                $types[] = 'reads.projection.refresh';
            }
        } catch (\Throwable $error) {
            // A disabled/misconfigured runtime must leave that family to its
            // safe PHP fallback rather than strand queue rows as processing.
        }
        try {
            if ($this->contract(\App\API\Services\AutomationBridge::class)->available()) {
                $types[] = 'automation.run';
            }
        } catch (\Throwable $error) {
            // See the read projection fallback above.
        }
        return $types;
    }

    /**
     * GET /api/realtime/buffer
     *
     * Returns the current static buffer for the named scope (diagnostic).
     */
    public function getBuffer($id = null, $data = [])
    {
        $scope = isset($data['scope']) ? EventBroadcaster::normalizeScope((string) $data['scope']) : EventBroadcaster::DEFAULT_SCOPE;
        $user = $this->getCurrentUser() ?: [];
        $allowed = RealtimeScopeResolver::scopesForRoles($this->extractRoleIds($user));
        if (!in_array($scope, $allowed, true)) {
            return $this->errorResponse('Forbidden: scope not assigned to your role', 403);
        }

        $path = EventBroadcaster::bufferPath($scope);
        $buffer = null;
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $buffer = json_decode($raw, true);
            }
        }
        return $this->successResponse($buffer, 'OK', 200);
    }

    /**
     * GET /api/realtime/my-buffer
     *
     * Authenticated handshake: returns the static-buffer URL(s) the current user
     * is authorized to poll, based on their roles. Called once per page load.
     * The returned paths embed unguessable HMAC slugs, so the service worker can
     * then poll them at 4s with zero further PHP while outsiders cannot guess
     * the files. Never returns payloads here — only paths.
     */
    public function getMyBuffer($id = null, $data = [])
    {
        $user = $this->getCurrentUser() ?: [];
        $roleIds = $this->extractRoleIds($user);
        $scopes = RealtimeScopeResolver::scopesForRoles($roleIds);

        $buffers = [];
        foreach ($scopes as $scope) {
            $buffers[] = [
                'scope' => $scope,
                'url'   => EventBroadcaster::bufferUrl($scope),
            ];
        }

        return $this->successResponse([
            'buffers'    => $buffers,
            'role_ids'   => $roleIds,
            'generated'  => date('Y-m-d H:i:s'),
        ], 'OK', 200);
    }

    /**
     * Extract numeric role ids from the authenticated user context, tolerating
     * the different shapes AuthMiddleware may leave in $_SERVER['auth_user'].
     *
     * @param array $user
     * @return int[]
     */
    private function extractRoleIds(array $user): array
    {
        if (!empty($user['role_ids']) && is_array($user['role_ids'])) {
            return array_values(array_unique(array_map('intval', $user['role_ids'])));
        }

        $ids = [];
        foreach (($user['roles'] ?? []) as $role) {
            if (is_numeric($role)) {
                $ids[] = (int) $role;
            } elseif (is_array($role) && isset($role['id'])) {
                $ids[] = (int) $role['id'];
            } elseif (is_object($role) && isset($role->id)) {
                $ids[] = (int) $role->id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Resolve scopes once at the HTTP boundary so every realtime read is
     * constrained by the authenticated user's role assignments.
     *
     * @return string[]
     */
    private function allowedScopes(): array
    {
        $user = $this->getCurrentUser() ?: [];
        return RealtimeScopeResolver::scopesForRoles($this->extractRoleIds($user));
    }

    private function latestOutboxId(array $allowedScopes = [EventBroadcaster::DEFAULT_SCOPE]): int
    {
        try {
            return \App\API\Services\EventBroadcaster::latestVisibleId($this->db, $allowedScopes);
        } catch (\Exception $e) {
            return 0;
        }
    }
}
