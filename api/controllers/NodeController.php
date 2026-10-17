<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Services\InterServiceClient;
use App\API\Services\NodeReadService;
use App\API\Services\JobQueue;
use App\Config\Config;

/**
 * NodeController - PHP bridge to Node.js gateway
 *
 * Provides authenticated browser data coordination and PHP-owned job status.
 * Node has no queue claim, completion, failure, or result-persistence endpoint.
 */
class NodeController extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('node');
    }

    /**
     * POST /api/node/jobs/enqueue
     * Node is a frontend data coordinator, not a background job worker.
     * Async writes and artifact generation must be authorized and enqueued by
     * their PHP domain API for processing by the registered backend worker.
     */
    public function postJobsEnqueue($id = null, $data = [])
    {
        $user = $this->getCurrentUser() ?: [];
        $userId = (int) ($user['user_id'] ?? $user['id'] ?? $this->user_id ?? 0);
        if ($userId < 1) {
            return $this->errorResponse('Authentication required.', 401);
        }

        return $this->errorResponse(
            'Node does not enqueue or process jobs. Submit asynchronous writes through the authorized PHP domain API.',
            410
        );
    }

    /**
     * GET /api/node/jobs/{jobId}/status
     * Read PHP-owned job status and any currently authorized result descriptor.
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
        $ownerId = (int) ($job['payload']['operator_id'] ?? $job['payload']['user_id'] ?? 0);
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

        if ((string) ($job['status'] ?? '') === 'done' && is_array($job['payload']['result'] ?? null)) {
            if (!NodeReadService::canReadJobResult($this->db, $job, $userId)) {
                return $this->errorResponse('You are no longer permitted to retrieve this result.', 403);
            }
            $status['result'] = $job['payload']['result'];
        }

        return $this->successResponse($status, 'Job status retrieved', 200);
    }

    /** Worker-secret-only liveness probe; returns no school or user data. */
    public function getInternalHealth($id = null, $data = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        return $this->successResponse([
            'status' => 'ok',
            'service' => 'php-api',
            'timestamp' => gmdate(DATE_ATOM),
        ], 'PHP API is reachable', 200);
    }

    private function hasValidWorkerCredential(): bool
    {
        // Match Node's configured KINGSWAY_WORKER_SECRET exactly. The broader
        // communications worker secret may be independently rotated.
        $expected = (string) Config::get(
            'KINGSWAY_WORKER_SECRET',
            Config::get('NODE_REALTIME_PUBLISH_SECRET', '')
        );
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';

        return $expected !== '' && is_string($provided) && hash_equals($expected, $provided);
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

            $isc = $this->contract(InterServiceClient::class);
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




}
