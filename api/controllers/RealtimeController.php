<?php

namespace App\API\Controllers;

use App\API\Includes\BaseAPI;
use App\API\Services\EventBroadcaster;
use App\API\Services\JobHandlerRegistry;
use App\API\Services\JobQueue;
use App\API\Services\RealtimeScopeResolver;

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
            $scopeMarks = implode(',', array_fill(0, count($allowedScopes), '?'));
            $stmt = $this->db->prepare(
                "SELECT id, domain, event_name, payload, created_at
                 FROM system_realtime_events
                 WHERE id > ? AND (target_scope IN ({$scopeMarks}) OR target_scope IS NULL)
                 ORDER BY id ASC
                 LIMIT " . self::SYNC_BATCH_LIMIT
            );
            $stmt->execute(array_merge([$lastId], $allowedScopes));

            $events = [];
            $newLastId = $lastId;
            while ($row = $stmt->fetch()) {
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
        JobQueue::recoverStale();
        $ids = JobQueue::claimBatch($limit);
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
     * rows, old outbox events, and rotated static buffers.
     */
    public function postCleanup($id = null, $data = [], $segments = [])
    {
        if (!$this->hasValidWorkerCredential()) {
            return $this->errorResponse('Invalid worker credential', 403);
        }

        $report = [
            'buffers_purged' => EventBroadcaster::purgeOldBufferFiles(48),
        ];

        $report = array_merge($report, JobQueue::purgeOld());

        $stmt = $this->db->prepare(
            "DELETE FROM system_realtime_events
             WHERE created_at < NOW() - INTERVAL 12 HOUR"
        );
        $stmt->execute();
        $report['events_purged'] = $stmt->rowCount();

        return $this->successResponse($report, 'Cleanup completed', 200);
    }

    private function hasValidWorkerCredential(): bool
    {
        $expected = defined('COMMUNICATION_WORKER_SECRET') ? (string) COMMUNICATION_WORKER_SECRET : '';
        $provided = $_SERVER['HTTP_X_KINGSWAY_WORKER_SECRET'] ?? '';

        return $expected !== ''
            && is_string($provided)
            && hash_equals($expected, $provided);
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
            $allowedScopes = array_values(array_unique(array_map([EventBroadcaster::class, 'normalizeScope'], $allowedScopes)));
            if (empty($allowedScopes)) {
                return 0;
            }
            $marks = implode(',', array_fill(0, count($allowedScopes), '?'));
            $stmt = $this->db->prepare(
                "SELECT COALESCE(MAX(id), 0) FROM system_realtime_events
                 WHERE target_scope IN ({$marks}) OR target_scope IS NULL"
            );
            $stmt->execute($allowedScopes);
            $value = $stmt->fetchColumn();
            return (int) $value;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
