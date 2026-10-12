<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Includes\FileLogger;
use App\API\Services\InterServiceClient;
use App\API\Services\NodeJobClient;
use App\API\Services\NodeReadService;
use App\API\Services\JobQueue;
use App\Config\Config;

/**
 * NodeController - PHP bridge to Node.js gateway
 *
 * Provides:
 * - Browser-facing: POST /api/node/jobs/enqueue, GET /api/node/jobs/{jobId}/status
 * - Internal (worker secret): POST /internal/queue/claim, /complete, /failed, /processing, /enqueue
 */
class NodeController extends BaseAPI
{
    /** Keep browser enqueue aligned with the handlers registered in node_platform/app.js. */
    private const BROWSER_JOB_TYPES = [
        'students:id-cards:load',
        'report:aggregate',
        'export:bulk',
    ];

    public function __construct()
    {
        parent::__construct('node');
    }

    /**
     * POST /api/node/jobs/enqueue
     * Enqueue a job on the Node.js gateway for async processing.
     * Called by browser via window.API.nodeJobs.enqueue()
     */
    public function postJobsEnqueue($id = null, $data = [])
    {
        $user = $this->getCurrentUser() ?: [];
        $userId = (int) ($user['user_id'] ?? $user['id'] ?? $this->user_id ?? 0);
        if ($userId < 1) {
            return $this->errorResponse('Authentication required.', 401);
        }

        $job = trim((string) ($data['job'] ?? ''));
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $priority = in_array($data['priority'] ?? 'normal', ['critical', 'normal', 'batch'], true)
            ? $data['priority']
            : 'normal';
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        if ($job === '') {
            return $this->errorResponse('Job name is required.', 422);
        }
        if (!in_array($job, self::BROWSER_JOB_TYPES, true)) {
            return $this->errorResponse('Unsupported Node job type.', 422);
        }

        try {
            $nodeClient = NodeJobClient::getInstance();
            $result = $nodeClient->enqueue($job, $payload, $priority, [
                'operator_id' => $userId,
                'idempotency_key' => $idempotencyKey
            ]);

            return $this->successResponse([
                'job_id' => $result['jobId'],
                'status' => $result['status'],
                'deduplicated' => $result['deduplicated'] ?? false
            ], 'Job enqueued on Node.js gateway', 202);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Job enqueue failed: ' . $e->getMessage());
            return $this->errorResponse('The background operation could not be queued.', 503);
        }
    }

    /**
     * GET /api/node/jobs/{jobId}/status
     * Get job status from Node.js gateway
     */
    public function getJobsStatus($id = null, $data = [])
    {
        $user = $this->getCurrentUser() ?: [];
        $userId = (int) ($user['user_id'] ?? $user['id'] ?? $this->user_id ?? 0);
        if ($userId < 1) {
            return $this->errorResponse('Authentication required.', 401);
        }

        $jobId = trim((string) ($id ?? $data['job_id'] ?? ''));
        if (!preg_match('/^[1-9][0-9]{0,18}$/', $jobId)) {
            return $this->errorResponse('A valid job ID is required.', 422);
        }

        $job = JobQueue::fetchJob((int) $jobId);
        if ($job === null) {
            return $this->errorResponse('Job not found.', 404);
        }

        // The durable PHP queue is authoritative. Enforce ownership here so a
        // guessed job ID cannot be used to inspect another operator's job.
        $ownerId = (int) ($job['payload']['operator_id'] ?? 0);
        if ($ownerId !== $userId) {
            return $this->errorResponse('Job not found.', 404);
        }

        $status = [
            'job_id' => (int) $job['id'],
            'job_type' => (string) $job['job_type'],
            'status' => (string) $job['status'],
            'attempts' => (int) ($job['attempts'] ?? 0),
            // Internal worker exception text can contain service details or
            // data fragments. Keep it in the protected operational record.
            'failed_reason' => (string) ($job['status'] ?? '') === 'failed'
                ? 'The operation could not be completed.'
                : null,
        ];

        if ((string) ($job['status'] ?? '') === 'done') {
            if (!NodeReadService::canReadJobResult($this->db, $job, $userId)) {
                return $this->errorResponse('You are no longer permitted to retrieve this result.', 403);
            }
            $resultEnvelope = NodeJobClient::getInstance()->getResult($jobId, $userId);
            if (!is_array($resultEnvelope) || !array_key_exists('result', $resultEnvelope)) {
                return $this->errorResponse('The completed result is temporarily unavailable.', 503);
            }
            $status['result'] = $resultEnvelope['result'];
        }

        return $this->successResponse($status, 'Job status retrieved', 200);
    }

    // ============================================================================
    // INTERNAL ENDPOINTS (worker secret authenticated)
    // Called by Node.js gateway to manage KingsWayBuffers.jobs_queue
    // ============================================================================

    /**
     * POST /internal/queue/enqueue
     * Node.js gateway calls this to enqueue a job in KingsWayBuffers.jobs_queue
     */
    public function postInternalQueueEnqueue($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $jobType = trim((string) ($data['job_type'] ?? ''));
        $payload = is_array($data['payload'] ?? null) ? $data['payload'] : [];
        $priority = in_array($data['priority'] ?? 'normal', ['critical', 'normal', 'batch'], true)
            ? $data['priority']
            : 'normal';
        $operatorId = (int) ($data['operator_id'] ?? 0);
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        if ($jobType === '') {
            return $this->errorResponse('job_type is required.', 422);
        }

        try {
            // JobQueue::push(jobType, payload, delaySeconds, maxAttempts, backoffSeconds, priority)
            // Operator + idempotency travel INSIDE the payload: push() only
            // deduplicates on payload['idempotency_key'] and has no operator
            // column, so neither may be passed positionally.
            if ($operatorId > 0) {
                $payload['operator_id'] = $operatorId;
            }
            if ($idempotencyKey !== '') {
                $payload['idempotency_key'] = $idempotencyKey;
            }

            $jobId = JobQueue::push(
                $jobType,
                $payload,
                0,
                3,
                60,
                $this->priorityToInt($priority)
            );

            return $this->successResponse(['job_id' => $jobId], 'Job enqueued', 202);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Internal enqueue failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to enqueue job: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /internal/queue/claim
     * Node.js gateway claims jobs for processing
     */
    public function postInternalQueueClaim($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $lane = trim((string) ($data['lane'] ?? 'normal'));
        $priority = $data['priority'] ?? 0;
        $limit = max(1, min(50, (int) ($data['limit'] ?? 10)));

        try {
            $pythonTypes = $this->activePythonJobTypes();
            JobQueue::recoverStale(15, $pythonTypes);
            
            $ids = JobQueue::claimBatch($limit, $pythonTypes, $priority);
            
            $jobs = [];
            foreach ($ids as $jobId) {
                $job = JobQueue::fetchJob($jobId);
                if ($job) {
                    $jobs[] = [
                        'id' => (int) $job['id'],
                        'job_type' => $job['job_type'],
                        'payload' => $job['payload'],
                        'attempts' => (int) $job['attempts']
                    ];
                }
            }

            return $this->successResponse(['jobs' => $jobs], 'Jobs claimed', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Claim failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to claim jobs', 500);
        }
    }

    /**
     * POST /internal/queue/recover
     * Re-queue leases that a dead gateway worker left processing (Python job
     * families excluded; they recover on their own runtime). Pure recovery: it
     * never claims work.
     */
    public function postInternalQueueRecover($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $leaseMinutes = max(1, min(60, (int) ($data['lease_minutes'] ?? 15)));

        try {
            $pythonTypes = $this->activePythonJobTypes();
            $recovered = JobQueue::recoverStale($leaseMinutes, $pythonTypes);
            return $this->successResponse(['recovered' => $recovered], 'Stale leases recovered', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Recover failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to recover stale jobs', 500);
        }
    }

    /**
     * POST /internal/queue/processing
     * Node.js gateway marks job as processing
     */
    public function postInternalQueueProcessing($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $jobId = (int) ($data['job_id'] ?? 0);
        if ($jobId < 1) {
            return $this->errorResponse('job_id required', 422);
        }

        try {
            // Queue bookkeeping lives in the KingsWayBuffers offload schema; the
            // primary PDO never touches jobs_queue. Claims already flip the row
            // to 'processing', so this re-marks it atomically (no-op when a claim
            // already performed the transition).
            \App\Database\ConnectionManager::run(static function (\PDO $pdo) use ($jobId): void {
                $stmt = $pdo->prepare(
                    "UPDATE `jobs_queue` SET `status` = 'processing', `updated_at` = NOW() "
                    . "WHERE `id` = ? AND `status` = 'pending'"
                );
                $stmt->execute([$jobId]);
            }, \App\Database\ConnectionManager::NS_BUFFERS);
            return $this->successResponse(['job_id' => $jobId], 'Marked processing', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Processing failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to mark processing', 500);
        }
    }

    /**
     * POST /internal/queue/complete
     * Node.js gateway marks job as completed
     */
    public function postInternalQueueComplete($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $jobId = (int) ($data['job_id'] ?? 0);
        $result = $data['result'] ?? null;

        if ($jobId < 1) {
            return $this->errorResponse('job_id required', 422);
        }

        try {
            JobQueue::markDone($jobId);
            return $this->successResponse(['job_id' => $jobId], 'Job completed', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Complete failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to complete job', 500);
        }
    }

    /**
     * POST /internal/queue/failed
     * Node.js gateway marks job as failed
     */
    public function postInternalQueueFailed($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $jobId = (int) ($data['job_id'] ?? 0);
        $reason = trim((string) ($data['reason'] ?? 'Node.js worker failed'));

        if ($jobId < 1) {
            return $this->errorResponse('job_id required', 422);
        }

        try {
            $status = JobQueue::markFailed($jobId, $reason);
            return $this->successResponse(['job_id' => $jobId, 'status' => $status], 'Job failed recorded', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] Failed failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to mark job failed', 500);
        }
    }

    /**
     * GET /api/node/internal-queue-status
     * Node.js gateway reads the authoritative durable row (PHP owns the queue).
     */
    public function getInternalQueueStatus($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $jobId = (int) ($id ?? $data['job_id'] ?? 0);
        if ($jobId < 1) {
            return $this->errorResponse('job_id required', 422);
        }

        $job = JobQueue::fetchJob($jobId);
        if ($job === null) {
            return $this->errorResponse('Job not found', 404);
        }

        return $this->successResponse([
            'job_id' => (int) $job['id'],
            'job_type' => $job['job_type'],
            'status' => $job['status'],
            'attempts' => (int) ($job['attempts'] ?? 0),
            'failed_reason' => $job['failed_reason'] ?? null,
        ], 'Job status', 200);
    }

    /**
     * GET|POST /api/node/internal-read
     *
     * The RBAC read proxy: Node's offloaded jobs fetch school data as a named,
     * permission-gated view instead of touching MySQL. The gateway relays an
     * operator id, but authorization is ALWAYS resolved here from the
     * authoritative source - the relayed permission list is only a routing
     * hint and is never trusted. Worker-secret + loopback only.
     */
    public function getInternalRead($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $payload = is_array($data) ? $data : [];
        $operatorId = (int) ($payload['operator_id'] ?? ($payload['operator']['user_id'] ?? 0));
        $view = trim((string) ($payload['view'] ?? ''));
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];

        if ($operatorId < 1) {
            return $this->errorResponse('operator_id required', 422);
        }
        if ($view === '') {
            return $this->errorResponse('view required', 422);
        }

        try {
            $db = $this->db;
            $read = new NodeReadService($db);

            if (!$read->known($view)) {
                return $this->errorResponse('Unknown view', 404);
            }

            // Server-resolved permissions; the relayed list is ignored.
            $permissions = NodeReadService::resolvePermissions($db, $operatorId);

            $required = $read->requiredPermission($view);
            if ($required !== null && !NodeReadService::permits($db, $operatorId, $required)) {
                FileLogger::write('node_read', [
                    'type'        => 'internal_read',
                    'operator_id' => $operatorId,
                    'view'        => $view,
                    'request_id'  => (string) ($payload['request_id'] ?? ''),
                    'outcome'     => 'denied',
                ]);
                return $this->errorResponse('Operator is not permitted for this view', 403);
            }

            $result = $read->read($view, $operatorId, $permissions, $input);

            FileLogger::write('node_read', [
                'type'        => 'internal_read',
                'operator_id' => $operatorId,
                'view'        => $view,
                'request_id'  => (string) ($payload['request_id'] ?? ''),
                'outcome'     => 'ok',
            ]);

            return $this->successResponse([
                'view' => $view,
                'data' => $result['data'] ?? null,
                'meta' => $result['meta'] ?? [],
            ], 'Read ok', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] internal read failed: ' . $e->getMessage());
            return $this->errorResponse('Read failed', 500);
        }
    }

    /** Same contract as getInternalRead, for POST bodies. */
    public function postInternalRead($id = null, $data = [], $segments = [])
    {
        return $this->getInternalRead($id, $data, $segments);
    }

    /**
     * POST /api/node/view
     *
     * Browser-facing Node data bridge (JWT-authenticated; NOT worker-secret).
     * PHP resolves the operator's permissions authoritatively, then relays a
     * registered view operation to Node over the worker-secret internal route.
     * The browser can request registered domain data only. PHP authenticates
     * the request and privately relays it to Node; page rendering stays in JS.
     */
    public function postView($id = null, $data = [], $segments = [])
    {
        $payload = is_array($data) ? $data : [];
        $entity = trim((string) ($payload['entity'] ?? ''));
        $view = trim((string) ($payload['view'] ?? ''));
        $input = is_array($payload['input'] ?? null) ? $payload['input'] : [];
        $contract = (string) ($payload['contract'] ?? 'data');

        if ($contract !== 'data') {
            return $this->errorResponse('Only the data response contract is supported.', 422);
        }

        if ($entity === '' || $view === '') {
            return $this->errorResponse('entity and view required', 422);
        }

        $operatorId = (int) $this->user_id;
        if ($operatorId < 1) {
            return $this->errorResponse('Authenticated operator required', 401);
        }

        try {
            // Authoritative server-side resolution; the browser relays nothing.
            $permissions = NodeReadService::resolvePermissions($this->db, $operatorId);
            $requestId = 'view_' . bin2hex(random_bytes(6));

            $isc = new InterServiceClient();
            $viewHeaders = ['Content-Type' => 'application/json'];
            $authorization = (string) (
                \App\API\Middleware\AuthMiddleware::getAuthorizationHeader() ?? ''
            );
            if (!preg_match('/^Bearer\s+\S+$/i', $authorization)) {
                return $this->errorResponse('Authenticated session token required.', 401);
            }
            $viewHeaders['Authorization'] = $authorization;
            $workerSecret = (string) Config::get('KINGSWAY_WORKER_SECRET', '');
            if ($workerSecret === '') {
                $workerSecret = (string) Config::get('NODE_REALTIME_PUBLISH_SECRET', '');
            }
            if ($workerSecret !== '') {
                $viewHeaders['X-Kingsway-Worker-Secret'] = $workerSecret;
            }
            $response = $isc->post(
                'realtime_node',
                '/internal/views/' . rawurlencode($entity) . '/' . rawurlencode($view),
                $viewHeaders,
                json_encode([
                    'operator_id' => $operatorId,
                    'permissions' => array_values($permissions),
                    'request_id' => $requestId,
                    'input' => $input,
                    'contract' => $contract,
                ]),
                60
            );

            $body = $response[0] ?? '';
            $status = (int) ($response[1] ?? 0);

            if ($status < 200 || $status >= 300) {
                if (in_array($status, [401, 403], true)) {
                    return $this->errorResponse(
                        $status === 403 ? 'You are not permitted to view this data.' : 'Authenticated session required.',
                        $status
                    );
                }
                throw new \RuntimeException("View gateway error ($status): $body");
            }

            $result = json_decode($body, true) ?? [];

            if (!is_array($result['data'] ?? null)) {
                throw new \RuntimeException('Node returned an invalid data envelope.');
            }

            return $this->successResponse([
                'entity' => $entity,
                'view' => $view,
                'data' => $result['data'],
                'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
            ], 'View data retrieved', 200);
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[NodeController] view failed: ' . $e->getMessage());
            return $this->errorResponse('View failed', 502);
        }
    }

    private function hasValidWorkerCredential(): bool
    {
        // The Node.js gateway authenticates its internal calls with the single
        // shared KINGSWAY_WORKER_SECRET. NODE_REALTIME_PUBLISH_SECRET is kept as
        // a deployment fallback for hosts that predate the unified secret.
        $expected = (string) Config::get('KINGSWAY_WORKER_SECRET', '');
        if ($expected === '') {
            $expected = (string) Config::get('NODE_REALTIME_PUBLISH_SECRET', '');
        }
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';
        return $expected !== '' && is_string($provided) && hash_equals($expected, $provided);
    }

    private function priorityToInt(string $priority): int
    {
        return match ($priority) {
            'critical' => JobQueue::PRIORITY_CRITICAL,
            'batch' => JobQueue::PRIORITY_BATCH,
            default => JobQueue::PRIORITY_NORMAL,
        };
    }

    private function activePythonJobTypes(): array
    {
        $types = [];
        try {
            if ($this->contract(\App\API\Services\ReadProjectionBridge::class)->enabled()) {
                $types[] = 'reads.projection.refresh';
            }
        } catch (\Throwable) {}
        try {
            if ($this->contract(\App\API\Services\AutomationBridge::class)->available()) {
                $types[] = 'automation.run';
            }
        } catch (\Throwable) {}
        return $types;
    }
}
