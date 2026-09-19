<?php

namespace App\API\Services;

use App\API\Services\RpcRegistry;
use PDO;
use RuntimeException;

/**
 * JobHandlerRegistry - maps job_type strings to worker handlers.
 *
 * Shared by the CLI cron worker and the Apache-delegated fallback endpoint so
 * both execution paths run identical business logic. Each handler receives the
 * decoded payload and the active PDO connection.
 *
 * A handler for a key MUST be registered here for the corresponding job to be
 * processed. Register real, durable handlers only — never no-op placeholders
 * that claim success without performing work.
 */
class JobHandlerRegistry
{
    /** @var array<string, callable>|null */
    private static $registry;

    /**
     * @return callable|null Handler for the given job type, or null when unknown.
     */
    public static function resolve(string $jobType)
    {
        self::build();
        return self::$registry[$jobType] ?? null;
    }

    /**
     * Build the static handler map once.
     */
    private static function build(): void
    {
        if (self::$registry !== null) {
            return;
        }

        self::$registry = [
            'rebake_realtime_buffer' => static function (array $payload, PDO $pdo): void {
                $scope = isset($payload['scope'])
                    ? EventBroadcaster::normalizeScope((string) $payload['scope'])
                    : EventBroadcaster::DEFAULT_SCOPE;
                EventBroadcaster::rebakeBuffer($pdo, $scope);
            },
            // Housekeeping: drop outbox events older than keep_hours and reset
            // the auto-increment, keeping the sync table small and fast.
            'purge_old_realtime_events' => static function (array $payload, PDO $pdo): void {
                $keepHours = isset($payload['keep_hours']) ? max(1, (int) $payload['keep_hours']) : 24;
                $cutoff = date('Y-m-d H:i:s', time() - ($keepHours * 3600));
                $stmt = $pdo->prepare("DELETE FROM system_realtime_events WHERE created_at < ?");
                $stmt->execute([$cutoff]);
                // ALTER TABLE takes a metadata lock. Only reset the identity
                // when the outbox is actually empty; doing it after every purge
                // would briefly block concurrent event writers at peak time.
                $remaining = (int) $pdo->query(
                    "SELECT COUNT(*) FROM system_realtime_events"
                )->fetchColumn();
                if ($remaining === 0) {
                    $pdo->exec("ALTER TABLE system_realtime_events AUTO_INCREMENT = 1");
                }
            },
            // Read-model health canary (roadmap §4.5). Pass-through projections
            // are recorded as not_offloaded and are not treated as failures;
            // only an enabled materialized projection that is stale, missing,
            // or mismatched should fail the canary.
            'rebuild.read.replica' => static function (array $payload, PDO $pdo): void {
                $results = \App\API\Services\ReadReplicaService::freshness();
                $bad = array_values(array_filter($results, static fn(array $r) => in_array(
                    (string) ($r['status'] ?? ''),
                    ['materialized_unhealthy', 'unavailable_master_fallback'],
                    true
                )));
                \App\API\Includes\FileLogger::write('reads', [
                    'event' => 'replica.verify',
                    'checked' => (int) count($results),
                    'mismatches' => (int) count($bad),
                    'request_id' => (string) ($payload['request_id'] ?? ''),
                ]);
                if ($bad !== []) {
                    throw new \RuntimeException('Read-replica parity mismatch on: ' . implode(', ', array_column($bad, 'projection')));
                }
            },
            // Async RPC execution (roadmap §4.2): a queued rpc.async.dispatch
            // job re-resolves the registered method and runs it in the worker.
            // Async methods declare a `worker` (a synchronously executable sync
            // method) which performs the actual work after the facade returned
            // {job_id}. The original requester (user_id) is carried for the
            // audit trail; scope-sensitive methods must remain idempotent and
            // safe to rerun.
            'rpc.async.dispatch' => static function (array $payload, PDO $pdo): void {
                $method = isset($payload['method']) ? (string) $payload['method'] : '';
                if ($method === '') {
                    throw new RuntimeException('rpc.async.dispatch: missing method in payload');
                }
                $def = RpcRegistry::resolve($method);
                if ($def === null) {
                    throw new RuntimeException("rpc.async.dispatch: method '{$method}' not found");
                }
                // Async façade methods delegate execution to their sync `worker`
                // target; plain sync methods execute themselves.
                $target = (string) ($def['_worker'] ?? $method);
                $tdef = RpcRegistry::resolve($target);
                if ($tdef === null) {
                    throw new RuntimeException("rpc.async.dispatch: worker '{$target}' not found for '{$method}'");
                }
                if (($tdef['_mode'] ?? 'sync') !== 'sync' || !is_callable($tdef['handler'])) {
                    throw new RuntimeException("rpc.async.dispatch: method '{$method}' is not synchronously executable");
                }
                $params = isset($payload['params']) && is_array($payload['params']) ? $payload['params'] : [];
                $ctx = [
                    'user_id' => (int) ($payload['user_id'] ?? 0),
                    'roles' => [],
                    'request_id' => isset($payload['request_id']) ? (string) $payload['request_id'] : '',
                    'db' => $pdo,
                    'async' => true,
                ];
                $result = call_user_func($tdef['handler'], $params, $ctx);
                \App\API\Includes\FileLogger::write('rpc', [
                    'type' => 'async_call',
                    'method' => $method,
                    'worker' => $target,
                    'user_id' => (int) ($ctx['user_id'] ?? 0),
                    'status' => 'success',
                    'result_keys' => array_keys(is_array($result) ? $result : []),
                ]);
            },
            'ai.workflow.draft' => static function (array $payload, PDO $pdo): void {
                $workflowId = (string) ($payload['workflow_id'] ?? '');
                $input = isset($payload['input']) && is_array($payload['input']) ? $payload['input'] : [];
                if ($workflowId === '') {
                    throw new RuntimeException('ai.workflow.draft: missing workflow_id');
                }
                (new AiDraftService())->create($pdo, $workflowId, [
                    'user_id' => (int) ($payload['user_id'] ?? 0),
                    'permissions' => isset($payload['permissions']) && is_array($payload['permissions'])
                        ? $payload['permissions'] : [],
                    'request_id' => (string) ($payload['request_id'] ?? ''),
                ], $input, isset($payload['metadata']) && is_array($payload['metadata'])
                    ? $payload['metadata'] : []);
            },
            'ai.analytics.insight' => static function (array $payload, PDO $pdo): void {
                (new AiAnalyticsInsightService())->execute($pdo, $payload);
            },
            // Proactive AI briefing (roadmap P3b): the worker re-authorizes with
            // the recorded operator context (no broadened scope), re-runs the
            // deterministic engine, and creates a reviewable draft or caches the
            // deterministic summary for page-load delivery. Never a second queue.
            'ai.insight.generate' => static function (array $payload, PDO $pdo): void {
                (new AiInsightOrchestrator())->execute($pdo, $payload);
            },
            // KICD policy-watch interpretation (roadmap P3b): the worker
            // re-authorizes the governed curriculum workflow with the recorded
            // operator and stores a reviewable draft. The provider call happens
            // here, never in a controller or on page load.
            'curriculum.policy_interpret' => static function (array $payload, PDO $pdo): void {
                (new CurriculumPolicyWatchAgent())->interpret($pdo, $payload);
            },
            // Extend here with 'generate_report_card' => ..., 'send_bulk_sms' => ...
            // only once the producing workflow pushes and consumes them.
        ];
    }
}
