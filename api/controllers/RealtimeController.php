<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Services\JobHandlerRegistry;
use App\API\Services\JobQueue;
use App\API\Services\ReadProjectionSynchronizer;
use App\API\Services\RealtimeScopeResolver;
use App\API\Services\sms\SmsBatchService;
use App\API\Services\sms\SmsPythonRelay;
use App\Config\Config;

/**
 * RealtimeController - realtime capability minting plus the worker endpoints.
 *
 *   GET  /api/realtime/stream-token   Short-lived capability for the browser's
 *                                     Node SSE stream (the only live-update
 *                                     transport), scoped to the user's roles.
 *   POST /api/realtime/worker|cleanup|maintenance|python-job-*
 *                                     Worker-secret endpoints driven by the
 *                                     ticker (python_platform/schedules.yaml).
 */
class RealtimeController extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('realtime');
    }

    /**
     * GET /api/realtime/stream-token
     * Issue a short-lived capability containing only the scopes this
     * authenticated user's roles allow (RealtimeScopeResolver).
     */
    public function getStreamToken($id = null, $data = [])
    {
        $user = $this->getCurrentUser() ?: [];
        $userId = (int) ($user['user_id'] ?? $user['id'] ?? $this->user_id ?? 0);
        // Browser-facing base: environment-agnostic resolver that never hands
        // a loopback/private address to a remote browser (a loopback URL is
        // only usable when the client shares the host with PHP, i.e. local
        // development). NODE_REALTIME_PUBLIC_URL wins when configured.
        $url = \App\API\Services\RealtimeGatewayPublisher::liveBrowserStreamBase();
        if ($url !== '') {
            $url = rtrim($url, '/');
        }
        if ($userId < 1) {
            return $this->errorResponse('Authentication required.', 401);
        }
        if ($url === '') {
            // A deployment without the Node gateway is a supported state, not a
            // failure: the browser falls back to polling and stops asking.
            return $this->success(['enabled' => false], 'Realtime stream is not configured.');
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

        // Claimed jobs (an AI draft can take over a minute) must finish and
        // record their outcome even if the ticker's HTTP call gives up first.
        ignore_user_abort(true);

        $limit = max(1, min(50, (int) ($data['limit'] ?? 10)));
        $pythonTypes = $this->activePythonJobTypes();
        JobQueue::recoverStale(15, $pythonTypes);
        $claims = JobQueue::claimBatchWithAttempts($limit, $pythonTypes);
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
                $claims = array_merge($claims, JobQueue::claimStarvedForTypesWithAttempts($takeoverTypes, 120, max(1, min(5, $limit))));
            }
        }
        $done = 0;
        $failed = 0;
        $retried = 0;

        foreach ($claims as $claim) {
            $id = (int) ($claim['id'] ?? 0);
            $attempts = max(0, (int) ($claim['attempts'] ?? -1));
            if ($id < 1) continue;
            $job = JobQueue::fetchJob($id);
            if (!JobQueue::jobMatchesAttempt($job, $attempts)) {
                continue;
            }
            \App\API\Services\WorkerJobTelemetry::recordLifecycle($job, 'processing');
            $payload = $job['payload'];
            try {
                // Typed handlers receive the durable queue id so they can
                // attach a result descriptor to the owned job record.
                $payload['_job_id'] = $id;
                $handler = JobHandlerRegistry::resolve($job['job_type']);
                if ($handler === null) {
                    throw new \RuntimeException("No registered handler for job_type '{$job['job_type']}'");
                }
                $handler($payload, $this->db);
                if (JobQueue::markDoneForAttempt($id, $attempts)) {
                    \App\API\Services\WorkerJobTelemetry::recordLifecycle($job, 'completed');
                    // AI jobs a human is waiting on tell their operator's own
                    // channel; every other family completes silently.
                    \App\API\Services\WorkerJobTelemetry::announceCompletion($job, 'completed');
                    $done++;
                }
            } catch (\Throwable $e) {
                // Authorization failures are deterministic: retrying the same
                // operator's job cannot restore access and only delays feedback.
                $permanentAuthorizationFailure = $e instanceof \DomainException
                    && in_array((int) $e->getCode(), [401, 403], true);
                $failureStatus = $permanentAuthorizationFailure
                    ? JobQueue::markPermanentlyFailedForAttempt($id, $attempts, $e->getMessage())
                    : JobQueue::markFailedForAttempt($id, $attempts, $e->getMessage());
                if ($failureStatus === null) continue;
                \App\API\Services\WorkerJobTelemetry::recordLifecycle(
                    $job,
                    $failureStatus === JobQueue::STATUS_PENDING ? 'retrying' : 'failed',
                    $e
                );
                \App\API\Services\WorkerJobTelemetry::announceCompletion($job, 'failed');
                if ($failureStatus === JobQueue::STATUS_PENDING) {
                    $retried++;
                } else {
                    $failed++;
                }
            }
        }

        return $this->successResponse(
            ['claimed' => count($claims), 'done' => $done, 'retried' => $retried, 'failed' => $failed],
            'Worker processed',
            200
        );
    }

    /**
     * POST /api/realtime/cleanup
     *
     * Bounded retention maintenance for shared-hosting cron. This deliberately
     * avoids schema operations and table locks; it only removes expired queue
     * rows and expired local SQLite cache entries.
     *
     * Run by the ticker as a `kind: http` job in python_platform/schedules.yaml.
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
            ($this->contract(\App\API\Services\ScheduledMaintenanceService::class))->runDailyMaintenance();
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

        $report = JobQueue::purgeOld();

        // Expired SQLite cache rows are only removed lazily when a key is read
        // again, so a key that is written once and never revisited would
        // otherwise persist indefinitely. Prune them here, on the same curl
        // schedule as the rest of the retention work.
        try {
            $storage = \App\API\Services\StorageRoot::path('php');
            $report['local_buffers'] = ($this->contract(\App\API\Services\LocalBufferMaintenanceService::class, [
                'shared_cache' => $storage . '/cache/sqlite',
                'response_cache' => $storage . '/response_cache/sqlite',
                'offline_replay' => $storage . '/offline_replay/store',
                'local_buffers' => $storage . '/buffers',
            ]))->run();
        } catch (\Throwable $error) {
            // Local cache pruning is best-effort and must never fail the job.
            \App\API\Services\Logger::legacyError('[RealtimeController] local buffer maintenance failed: ' . $error->getMessage());
            $report['local_buffers'] = ['status' => 'degraded'];
        }

        // Sweep document batch state and artifacts (48-hour TTL).
        try {
            $purged = ($this->contract(\App\API\Services\documents\DocumentBatchStore::class))->purgeExpired(48);
            $report['document_batches_purged'] = $purged;
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[RealtimeController] document batch purge failed: ' . $error->getMessage());
            $report['document_batches_purged'] = 0;
        }

        // Scrape the Node gateway's deterministic counters into the realtime
        // journal, so the realtime layer is observable from the existing
        // system-health surfaces. Best-effort: never fails the cleanup job.
        $report['gateway_health'] = \App\API\Services\RealtimeGatewayHealth::journalSnapshot();

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
        $jobType = $job === null ? '' : (string) $job['job_type'];
        if ($job === null || $job['status'] !== JobQueue::STATUS_PROCESSING
            || !in_array($jobType, $this->activePythonJobTypes(), true)
            || $attempts !== (int) $job['attempts']) {
            return $this->errorResponse('Python job lease is not valid for input access', 409);
        }
        try {
            if ($jobType === 'communications.sms_batch') {
                $input = (new SmsBatchService($this->db))->preparePythonJob($job['payload'], $this->db);
                return $this->successResponse($input, 'Authorized messaging batch input', 200);
            }
            $input = $this->contract(\App\API\Services\automations\AutomationArtifacts::class)
                ->preparePythonJob($job['payload'], $this->db);
            return $this->successResponse($input, 'Authorized automation input', 200);
        } catch (\Throwable $error) {
            \App\API\Services\Logger::legacyError('[RealtimeController] Python input preparation failed: ' . get_class($error));
            return $this->errorResponse('Python job input is unavailable or no longer authorized', 422);
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
        } elseif ($job['job_type'] === 'communications.sms_batch') {
            if (!in_array('communications.sms_batch', $this->activePythonJobTypes(), true) || !is_array($data['result'] ?? null)) {
                return $this->errorResponse('Messaging batch result is not available to this worker', 422);
            }
            try {
                $descriptor = (new SmsBatchService($this->db))->applyPythonResult($job['payload'], $data['result'], $this->db);
            } catch (\Throwable $error) {
                \App\API\Services\Logger::legacyError('[RealtimeController] Messaging batch result failed validation: ' . get_class($error));
                return $this->errorResponse('Python messaging batch result failed validation', 422);
            }
        }
        $safeResult = in_array($job['job_type'], ['automation.run', 'communications.sms_batch'], true)
            ? $descriptor
            : (is_array($data['result'] ?? null) ? $data['result'] : null);
        if (!JobQueue::markDoneForAttempt($jobId, $claimedAttempts, $safeResult)) {
            return $this->errorResponse('Python job lease has been recovered', 409);
        }
        if ($descriptor !== null && $job['job_type'] === 'automation.run') {
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
        // Long batches report bounded progress; the counters only ever move
        // forward inside [0, total] so a stale worker cannot corrupt the meta.
        if ($job['job_type'] === 'communications.sms_batch' && is_array($data['progress'] ?? null)) {
            $processed = max(0, (int) ($data['progress']['processed'] ?? 0));
            $total = max(0, (int) ($data['progress']['total'] ?? 0));
            if ($total > 0) {
                // updateMeta replaces the document, so merge with what is there.
                $meta = is_array($job['meta'] ?? null) ? $job['meta'] : [];
                $meta['processed'] = min($processed, $total);
                $meta['total'] = $total;
                JobQueue::updateMeta($jobId, $meta);
            }
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
        try {
            if ((new SmsPythonRelay())->available()) {
                $types[] = 'communications.sms_batch';
            }
        } catch (\Throwable $error) {
            // See the read projection fallback above.
        }
        return $types;
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
        // Audience-aware resolution: a parent-only account is minted family
        // channels for its linked learners and can never inherit the staff
        // 'all' channel (see RealtimeScopeResolver::scopesForUser).
        return RealtimeScopeResolver::scopesForUser(
            $this->db,
            $user,
            $this->extractRoleIds($user)
        );
    }
}
