<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use DomainException;
use PDO;

/**
 * Agent orchestration delegate.
 *
 * PHP responsibilities here: validate inputs, resolve the authenticated
 * operator, and hand the governed envelope to the Python platform. Nothing in
 * this class routes, prompts, executes a tool, or calls a provider — that work
 * is owned by python_platform/app/orchestrator.py.
 */
final class AgentAssistant
{
    public const WORKFLOW_CHAT = 'system.agent_chat';
    public const WORKFLOW_TRIAGE = 'system.agent_triage';
    public const JOB_TYPE = 'ai.agent.run';

    /** Cache prefix for agent results (non-briefing caching) */
    public const CACHE_PREFIX = 'kw_agent_cache:';

    private const MAX_QUESTION_CHARS = 500;

    /** Background run modes this agent can queue for the governed worker. */
    private const RUN_MODES = ['briefing', 'digest', 'assist'];

    /** Digest cadences accepted by the queue payload. */
    private const RUN_CADENCES = ['daily', 'weekly', 'term'];

    private AgentEngineClient $bridge;

    public function __construct(?AgentEngineClient $bridge = null)
    {
        $this->bridge = $bridge ?? new PythonAiBridge();
    }

    /**
     * @param array<string,mixed> $context authenticated operator envelope
     * @return array<string,mixed> governed answer contract from Python
     */
    public function assist(PDO $pdo, array $context, string $question): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        $userId = (int) ($context['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('An authenticated staff member is required.', 401);
        }
        $audience = (string) ($context['audience'] ?? 'staff');
        if ($audience !== 'staff') {
            throw new DomainException('This assistant only serves authenticated staff.', 403);
        }

        $permissions = $this->resolveEffectivePermissions($pdo, $userId);

        // Authorization stays in PHP before any token reaches the engine.
        (new WorkflowAuthorization())->authorize(self::WORKFLOW_CHAT, [
            'user_id' => $userId,
            'permissions' => $permissions,
            'audience' => 'staff',
        ]);

        return $this->bridge->assist([
            'user_id' => $userId,
            'permissions' => $permissions,
            'roles' => $context['roles'] ?? [],
            'role_names' => $context['role_names'] ?? [],
            'effective_permissions' => $permissions,
            'audience' => 'staff',
            'route' => mb_substr((string) ($context['route'] ?? ''), 0, 120),
            'module' => mb_substr((string) ($context['module'] ?? 'dashboard'), 0, 60),
            'request_id' => mb_substr((string) ($context['request_id'] ?? 'ai-assist'), 0, 100),
            'session_id' => (string) ($context['session_id'] ?? ''),
            'history' => is_array($context['history'] ?? null) ? array_slice($context['history'], -10) : [],
            'topics' => is_array($context['topics'] ?? null) ? array_slice($context['topics'], 0, 12) : [],
            'preferences' => is_array($context['preferences'] ?? null) ? $context['preferences'] : [],
        ], $question);
    }

    /**
     * Execute one governed tool request relayed by the Python engine.
     *
     * PHP EXECUTES; it must never re-delegate. The previous shape relayed
     * straight back out to the Python engine, which has no such route, so
     * every governed tool failed with "the Python AI platform rejected the
     * request" and the assistant could never read school data.
     *
     * The recorded operator's permissions are re-resolved here from the
     * database, and AgentToolExecutor applies the workflow's own permission
     * gate, so a subject teacher can never reach finance data even if the
     * engine asks for it.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function executeToolRequest(PDO $pdo, array $payload): array
    {
        $operator = is_array($payload['operator'] ?? null) ? $payload['operator'] : [];
        $userId = (int) ($operator['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('A recorded operator is required.', 422);
        }
        $permissions = $this->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            throw new DomainException('The recorded operator permissions could not be resolved.', 403);
        }

        return (new AgentToolExecutor())->execute(
            $userId,
            $permissions,
            (string) ($payload['tool'] ?? ''),
            is_array($payload['tool_input'] ?? null) ? $payload['tool_input'] : [],
            (string) ($payload['request_id'] ?? 'agent-tool'),
            $pdo
        );
    }

    /**
     * Execute one background ai.agent.run job through the Python engine.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function runBackground(PDO $pdo, array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('Background agent payload is incomplete.', 422);
        }
        $permissions = $this->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            throw new DomainException('The task owner has no currently resolved permissions.', 403);
        }

        return $this->bridge->runJob([
            'user_id' => $userId,
            'permissions' => $permissions,
            'roles' => $payload['roles'] ?? [],
            'role_names' => $payload['role_names'] ?? [],
            'effective_permissions' => $permissions,
            'request_id' => mb_substr((string) ($payload['request_id'] ?? 'ai-agent-run'), 0, 100),
            'audience' => 'staff',
            'route' => (string) ($payload['route'] ?? ''),
            'module' => (string) ($payload['module'] ?? 'dashboard'),
            'mode' => (string) ($payload['mode'] ?? 'digest'),
            'question' => (string) ($payload['question'] ?? ''),
            'cadence' => (string) ($payload['cadence'] ?? 'daily'),
        ], $pdo);
    }

    /**
     * Queue one governed `ai.agent.run` job for the recorded operator.
     *
     * Page load (briefing), the digest worker and the insight-brief cron all
     * land here. The queue row carries a deterministic idempotency key derived
     * from the operator, mode, route and request id, so two page loads opening
     * in the same window (or a retried cron line) reuse one job instead of
     * running the same work twice.
     *
     * The grant list passed in here is only a hint: JobHandlerRegistry ->
     * PythonAiBridge::runJob() re-resolves the operator's permissions from
     * current server state before anything executes, so a stale hint can never
     * widen the recorded operator's scope.
     *
     * @param array<string,mixed> $options {mode, route, cadence, request_id, broadcast}
     * @return array<string,mixed> {job_id, job_type, mode, status}
     */
    public function enqueue(int $userId, array $permissions = [], array $options = []): array
    {
        $payload = $this->buildRunPayload($userId, $permissions, $options);
        $delay = max(0, min(86400, (int) ($options['delay_seconds'] ?? 0)));
        $jobId = JobQueue::push(self::JOB_TYPE, $payload, $delay, 3, 60);

        FileLogger::write('ai_generation', [
            'type' => 'agent_run_queued',
            'job_id' => $jobId,
            'operator_id' => $userId,
            'mode' => $payload['mode'],
            'route' => $payload['route'],
            'request_id' => $payload['request_id'],
        ]);

        return [
            'job_id' => $jobId,
            'job_type' => self::JOB_TYPE,
            'mode' => $payload['mode'],
            'status' => JobQueue::STATUS_PENDING,
        ];
    }

    /**
     * Validate the request and build the `ai.agent.run` queue payload.
     *
     * Kept separate from enqueue() so the contract the worker will read is
     * decision-checked before any queue work happens, matching JobQueue's own
     * validate-before-write ordering.
     *
     * @param array<string,mixed> $options {mode, route, cadence, request_id, broadcast}
     * @return array<string,mixed>
     */
    private function buildRunPayload(int $userId, array $permissions, array $options): array
    {
        if ($userId < 1) {
            throw new DomainException('An authenticated operator is required.', 401);
        }
        $mode = (string) ($options['mode'] ?? 'digest');
        if (!in_array($mode, self::RUN_MODES, true)) {
            throw new DomainException('An unsupported agent run mode was supplied.', 422);
        }
        $route = mb_substr((string) ($options['route'] ?? ''), 0, 120);
        $requestId = mb_substr((string) ($options['request_id'] ?? 'ai-agent-run'), 0, 100);
        $payload = [
            'user_id' => $userId,
            'permissions' => array_values(array_map('strval', $permissions)),
            'route' => $route,
            'module' => mb_substr((string) ($options['module'] ?? 'dashboard'), 0, 60),
            'mode' => $mode,
            'request_id' => $requestId,
            'broadcast' => !empty($options['broadcast']),
            'idempotency_key' => 'ai-agent:' . hash('sha256', implode('|', [
                self::JOB_TYPE,
                (string) $userId,
                $mode,
                $route,
                $requestId,
            ])),
        ];
        if ($mode === 'digest') {
            $cadence = (string) ($options['cadence'] ?? 'daily');
            $payload['cadence'] = in_array($cadence, self::RUN_CADENCES, true) ? $cadence : 'daily';
        }

        return $payload;
    }

    /** @return list<string> */
    public function resolveEffectivePermissions(PDO $pdo, int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT DISTINCT permission_code FROM v_user_permissions_effective
                 WHERE user_id = ? AND permission_code IS NOT NULL AND LENGTH(permission_code) > 0'
            );
            $stmt->execute([$userId]);
            return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Resolve the operator's active roles for the engine's report authorization.
     *
     * @return list<array{id:int,name:string}>
     */
    public function resolveEffectiveRoles(PDO $pdo, int $userId): array
    {
        if ($userId < 1) {
            return [];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT r.id, r.name FROM roles r
                 JOIN user_roles ur ON ur.role_id = r.id
                 WHERE ur.user_id = ? AND r.is_active = 1
                 ORDER BY r.id'
            );
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Retrieve a cached workspace briefing for a user and route.
     * Returns null if no cached briefing exists or is stale.
     *
     * @param int $userId
     * @param string $route
     * @return array<string,mixed>|null
     */
    public function cachedBriefing(int $userId, string $route): ?array
    {
        // The briefing cache is shared via SharedCache service.
        // This method provides a fallback when Python platform is unavailable.
        // In production with Python platform, briefings are generated through
        // the Python engine and cached there. This PHP-side cache is a resilience path.
        try {
            $cache = new SharedCache();
            $cached = $cache->get(self::briefingCacheKey($userId, $route));
            if (is_array($cached) && isset($cached['status']) && $cached['status'] === 'ready') {
                return $cached;
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Cache key prefix for briefings */
    public const BRIEFING_CACHE_PREFIX = 'agent_briefing:';

    /**
     * TTL for a generated briefing. Matches the insight brief TTL so a page
     * reload inside the window serves the cached copy instead of enqueuing the
     * same regeneration again.
     */
    public const BRIEFING_TTL = 3600;

    /**
     * Single source of truth for the briefing cache key.
     *
     * PythonAiBridge::cacheBriefingResult() writes with this key and
     * cachedBriefing() reads with it. They were previously two different
     * expressions, so every generated briefing was written under a key nobody
     * read and every panel open re-queued regeneration.
     */
    public static function briefingCacheKey(int $userId, string $route): string
    {
        return self::BRIEFING_CACHE_PREFIX . $userId . ':' . mb_substr($route, 0, 120);
    }
}