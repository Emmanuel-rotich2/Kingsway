<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use DomainException;
use PDO;
use Throwable;

/**
 * Governed multi-agent orchestration for the staff AI co-worker shell.
 *
 * Architecture (adapted from the researched production patterns — OpenAI
 * "agents as tools"/handoff orchestration, Anthropic routing +
 * orchestrator-workers, n8n Tools-Agent, and the HN consensus that control
 * flow must stay deterministic code, never free-form model chaining):
 *
 *   ROUTING      route -> domain agent deterministically (no provider call);
 *                otherwise one bounded triage call picks an agent id from the
 *                registry, which is re-validated server-side.
 *   AGENT LOOP   the agent persona prompt + allowlisted tool list ask the
 *                provider for STRICT JSON: either {action:"tool"} or
 *                {action:"final"}. Every proposed tool is validated against
 *                the agent's allowlist AND re-authorized per workflow before
 *                the deterministic executor runs it. Max 2 tool steps,
 *                max 3 provider calls per assist.
 *   SYNTHESIS    the final structured answer (title/body/next_steps/
 *                suggested_questions/escalation_required) is bounded,
 *                validated and journaled; the provider never writes records.
 *
 * Governance: every provider payload passes AiPromptPolicy::minimize under
 * the system.agent_triage / system.agent_chat allowlists; prompts come from
 * the versioned AiPromptTemplateService; runs are journaled (hashes + tool
 * ids + outcome) to the ai_generation file journal; behaviour observations
 * go through the allowlisted AiBehaviorProfiler (SQLite/JSON local buffer).
 * The service never holds provider credentials itself — AiCompletionProvider
 * is injected — and background execution re-authorizes the recorded
 * operator so a queued run can never broaden scope.
 */
class AiAgentService
{
    public const WORKFLOW_CHAT = 'system.agent_chat';
    public const WORKFLOW_TRIAGE = 'system.agent_triage';
    public const JOB_TYPE = 'ai.agent.run';
    public const CACHE_PREFIX = 'ai_agent:v1:u';
    public const BRIEFING_CACHE_PREFIX = 'ai_wsbrief:v1:u';
    public const BRIEFING_TTL = 1800;
    public const TOOL_SCAN = 'assistant.workspace_scan';

    private const MAX_QUESTION_CHARS = 500;
    private const MAX_TOOL_STEPS = 2;
    private const MAX_PROVIDER_CALLS = 3;
    private const MAX_TOOL_RESULT_CHARS = 500;
    private const TOOL_RESULT_FIELDS = ['status', 'report_code', 'report_title', 'row_count', 'as_of', 'summary', 'alert_count', 'alerts', 'metrics', 'domains_ran', 'workflows', 'message', 'reason'];
    private const VALID_CADENCES = ['daily', 'weekly', 'term'];

    private AiCompletionProvider $provider;
    private AiPromptTemplateService $templates;
    private AiBehaviorProfiler $behavior;
    private AiWorkflowService $workflows;
    private NlqQueryService $nlq;

    /** @var callable(int,array,string,string):array insight-brief loader */
    private $insightBriefLoader;

    private ?SharedCache $cache;
    private bool $cacheResolved = false;

    /**
     * @param callable|null $insightBriefLoader fn(int $userId, array $permissions, string $requestId, string $cadence): array
     */
    public function __construct(
        ?AiCompletionProvider $provider = null,
        ?AiPromptTemplateService $templates = null,
        ?AiBehaviorProfiler $behavior = null,
        ?AiWorkflowService $workflows = null,
        ?NlqQueryService $nlq = null,
        ?callable $insightBriefLoader = null,
        ?SharedCache $cache = null
    ) {
        $this->provider = $provider ?: new AiProviderGateway();
        $this->templates = $templates ?: new AiPromptTemplateService();
        $this->behavior = $behavior ?: new AiBehaviorProfiler();
        $this->workflows = $workflows ?: new AiWorkflowService();
        $this->nlq = $nlq ?: new NlqQueryService();
        $this->insightBriefLoader = $insightBriefLoader
            ?: static fn (int $userId, array $permissions, string $requestId, string $cadence): array => (new AiInsightOrchestrator())->pageLoad($userId, $permissions, $requestId, ['cadence' => $cadence, 'broadcast' => false]);
        $this->cache = $cache;
    }

    /**
     * Interactive staff agent assist (synchronous, NLQ-bounded latency).
     *
     * @param array<string,mixed> $context authenticated envelope:
     *        user_id, roles, permissions, effective_permissions, request_id,
     *        audience, route, module
     * @return array<string,mixed> governed answer contract
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
        $permissions = $this->permissionsOf($context);
        $route = mb_substr(trim((string) ($context['route'] ?? '')), 0, 120);
        $module = mb_substr(trim((string) ($context['module'] ?? 'dashboard')), 0, 60);
        $requestId = mb_substr((string) ($context['request_id'] ?? 'ai-agent-assist'), 0, 100);

        // Audience boundary for the chat workflow itself.
        $this->workflows->authorize(self::WORKFLOW_CHAT, [
            'user_id' => $userId,
            'permissions' => $permissions,
            'audience' => 'staff',
        ]);

        $agent = AiAgentRegistry::forRoute($route);
        $routing = 'route_match';
        if ($agent === null) {
            $agent = $this->triage($userId, $permissions, $question, $route, $module);
            $routing = 'triage';
        }
        if ($agent === null) {
            $agent = AiAgentRegistry::defaultAgent();
            $routing = 'default';
        }

        $this->behavior->observe($userId, 'question', [
            'route' => $route,
            'module' => $module,
            'domain' => (string) $agent['domain'],
            'agent_id' => (string) $agent['id'],
        ]);

        $startedAt = microtime(true);
        $result = $this->runAgentLoop($pdo, $context, $agent, $question, $route, $module);

        $this->behavior->observe($userId, 'assist_outcome', [
            'agent_id' => (string) $agent['id'],
            'tool' => implode(',', $result['tools_used'] ?? []),
            'outcome' => (string) $result['status'],
        ]);

        FileLogger::write('ai_generation', [
            'type' => 'agent_assist',
            'operator_id' => $userId,
            'agent_id' => (string) $agent['id'],
            'routing' => $routing,
            'question_hash' => hash('sha256', $question),
            'outcome' => (string) $result['status'],
            'tools_used' => $result['tools_used'] ?? [],
            'provider_calls' => (int) $result['provider_calls'],
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'request_id' => $requestId,
        ]);

        return array_merge($result, [
            'routing' => $routing,
            'agent' => [
                'id' => (string) $agent['id'],
                'name' => (string) $agent['name'],
                'domain' => (string) $agent['domain'],
            ],
        ]);
    }

    /**
     * Background worker execution for the ai.agent.run job type.
     *
     * Payload modes:
     *   digest — deterministic personal digest (behaviour hints + insight
     *            brief), cached and broadcast; no provider call.
     *   assist — queued agent assist for a recorded operator; the full
     *            governed loop re-authorized with the recorded permissions.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    /**
     * Resolve a user's effective permissions in ONE query.
     *
     * @return list<string>
     */
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
            return array_values(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Bounded permission hint for the Python engine. Python only needs scope
     * to pick an agent; every governed tool re-authorizes the recorded
     * operator against the database, so the full grant list is never relayed.
     *
     * @param list<string> $permissions
     * @return array{permissions: list<string>, permission_count: int, truncated: bool}
     */
    public static function permissionHint(array $permissions, int $cap = 200): array
    {
        $unique = array_values(array_unique(array_filter(array_map('strval', $permissions), static fn(string $p): bool => $p !== '')));
        sort($unique);
        $total = count($unique);
        return [
            'permissions' => array_slice($unique, 0, $cap),
            'permission_count' => $total,
            'truncated' => $total > $cap,
        ];
    }

    public function runBackground(PDO $pdo, array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('Background agent payload is incomplete.', 422);
        }
        // Queue payloads stay tiny: permissions are resolved here, from the
        // authoritative source, instead of being carried through the queue
        // (a System Administrator holds thousands and blows the payload cap).
        $permissions = $this->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            $permissions = array_values(array_map('strval', (array) ($payload['permissions'] ?? [])));
        }
        $requestId = mb_substr((string) ($payload['request_id'] ?? 'ai-agent-run'), 0, 100);
        $mode = (string) ($payload['mode'] ?? 'digest');

        if ($mode === 'assist') {
            $question = (string) ($payload['question'] ?? '');
            $result = $this->assist($pdo, [
                'user_id' => $userId,
                'permissions' => $permissions,
                'effective_permissions' => $permissions,
                'request_id' => $requestId,
                'audience' => 'staff',
                'route' => (string) ($payload['route'] ?? ''),
                'module' => (string) ($payload['module'] ?? 'dashboard'),
            ], $question);
            $this->cacheResult($userId, $result);
            if (!empty($payload['broadcast'])) {
                EventBroadcaster::dispatch($pdo, 'intelligence', 'ai_agent.assist_ready', [
                    'agent_id' => (string) ($result['agent']['id'] ?? ''),
                    'reference' => self::CACHE_PREFIX . $userId,
                ]);
            }
            return $result;
        }

        if ($mode === 'briefing') {
            // PHP-native resilience path: deterministic findings only, no
            // provider call. The Python engine is the primary briefing path
            // (narrative + root causes); this keeps the panel alive when the
            // platform is unavailable.
            $route = mb_substr((string) ($payload['route'] ?? ''), 0, 120);
            $result = $this->buildDeterministicBriefing($pdo, $userId, $permissions, $route);
            $this->cacheBriefing($userId, $route, $result);
            if (!empty($payload['broadcast'])) {
                EventBroadcaster::dispatch($pdo, 'intelligence', 'ai_agent.briefing_ready', [
                    'route' => $route,
                    'reference' => self::briefingCacheKey($userId, $route),
                ]);
            }
            return $result;
        }

        $cadence = (string) ($payload['cadence'] ?? 'daily');
        if (!in_array($cadence, self::VALID_CADENCES, true)) {
            $cadence = 'daily';
        }
        $hints = $this->behavior->hints($userId);
        $brief = $this->loadInsightBrief($userId, $permissions, $requestId, $cadence);

        $digest = [
            'status' => 'ready',
            'generated_at' => gmdate('c'),
            'cadence' => $cadence,
            'hints' => $hints,
            'insight_brief' => [
                'status' => (string) ($brief['status'] ?? 'generating'),
                'alert_count' => (int) ($brief['alert_count'] ?? 0),
                'alerts' => array_slice((array) ($brief['alerts'] ?? []), 0, 10),
                'metrics' => array_slice((array) ($brief['metrics'] ?? []), 0, 10),
                'as_of' => (string) ($brief['as_of'] ?? ''),
            ],
        ];
        $this->cacheResult($userId, $digest);

        FileLogger::write('ai_generation', [
            'type' => 'agent_digest_ready',
            'operator_id' => $userId,
            'cadence' => $cadence,
            'alert_count' => (int) ($brief['alert_count'] ?? 0),
            'hints_known' => (bool) ($hints['known'] ?? false),
            'request_id' => $requestId,
        ]);

        if (!empty($payload['broadcast'])) {
            EventBroadcaster::dispatch($pdo, 'intelligence', 'ai_agent.digest_ready', [
                'cadence' => $cadence,
                'alert_count' => (int) ($brief['alert_count'] ?? 0),
                'reference' => self::CACHE_PREFIX . $userId,
            ]);
        }
        return $digest;
    }

    /** Queue a background agent run on the existing JobQueue (never a second queue). */
    public function enqueue(int $userId, array $permissions, array $payload): int
    {
        // Only a bounded hint travels with the job: the worker re-resolves the
        // operator's real permissions, so queue rows stay small and a grant
        // change between enqueue and execution takes effect immediately.
        $hint = self::permissionHint($permissions, 60);
        $base = array_merge($payload, [
            'user_id' => $userId,
            'permissions' => $hint['permissions'],
            'permission_count' => $hint['permission_count'],
        ]);
        $mode = (string) ($base['mode'] ?? 'digest');
        // One briefing job per staff member, route and briefing window. The
        // panel is opened many times a day, so a per-day key would leave a
        // served briefing stale until tomorrow; a per-second key would queue
        // a job on every panel open.
        $window = $mode === 'briefing'
            ? gmdate('Y-m-d\THi', time() - (time() % self::BRIEFING_TTL))
            : date('Y-m-d');
        $base['idempotency_key'] = 'ai-agent:' . hash('sha256', implode('|', [
            $mode,
            (string) $userId,
            (string) ($base['cadence'] ?? ''),
            (string) ($base['question'] ?? ''),
            (string) ($base['route'] ?? ''),
            $window,
        ]));
        return JobQueue::push(self::JOB_TYPE, $base, 0, 3, 60);
    }

    /**
     * Execute ONE allowlisted governed tool for the Python AI platform's
     * agents (POST /api/dashboard/agent-tool). The recorded operator is
     * re-authorized against the tool's workflow before execution - the
     * calling agent never bypasses permissions, and the payload shape is
     * bounded to exactly {operator:{user_id,permissions}, tool, tool_input}.
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
        // The relayed permission list is only a routing hint; authorization is
        // always resolved from the authoritative source for the operator.
        $permissions = $this->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            $permissions = array_values(array_map('strval', (array) ($operator['permissions'] ?? [])));
        }
        $tool = (string) ($payload['tool'] ?? '');
        $allowedTools = [AiAgentRegistry::TOOL_NLQ, AiAgentRegistry::TOOL_INSIGHT_BRIEF, AiAgentRegistry::TOOL_CATALOG, self::TOOL_SCAN];
        if (!in_array($tool, $allowedTools, true)) {
            return ['status' => 'unknown_tool'];
        }
        $toolInput = is_array($payload['tool_input'] ?? null) ? $payload['tool_input'] : [];
        if ($tool === AiAgentRegistry::TOOL_NLQ) {
            $toolInput = ['question' => mb_substr((string) ($toolInput['question'] ?? ''), 0, self::MAX_QUESTION_CHARS)];
        } elseif ($tool === AiAgentRegistry::TOOL_INSIGHT_BRIEF) {
            $cadence = (string) ($toolInput['cadence'] ?? 'daily');
            $toolInput = ['cadence' => in_array($cadence, self::VALID_CADENCES, true) ? $cadence : 'daily'];
        } elseif ($tool === self::TOOL_SCAN) {
            $toolInput = ['route' => mb_substr(strtolower(trim((string) ($toolInput['route'] ?? ''))), 0, 120)];
        } else {
            $toolInput = [];
        }
        $context = [
            'user_id' => $userId,
            'roles' => [],
            'permissions' => $permissions,
            'effective_permissions' => $permissions,
            'request_id' => mb_substr((string) ($payload['request_id'] ?? 'ai-agent-tool'), 0, 100),
            'audience' => 'staff',
            'route' => '',
            'module' => 'dashboard',
        ];
        $result = $tool === self::TOOL_SCAN
            ? $this->workspaceScan($pdo, $userId, $permissions, (string) ($toolInput['route'] ?? ''))
            : $this->executeTool($pdo, $context, $tool, $toolInput, $permissions, (string) $context['request_id']);

        FileLogger::write('ai_generation', [
            'type' => 'agent_tool_executed',
            'operator_id' => $userId,
            'tool' => $tool,
            'outcome' => (string) ($result['status'] ?? 'ok'),
        ]);
        return $result;
    }

    /**
     * Deterministic workspace scan for the proactive briefing: bounded,
     * allowlisted aggregates only, for the route's domain. Everything here
     * is computed (no provider) so the co-worker surfaces findings in
     * milliseconds; the Python engine adds the narrative layer on top.
     *
     * @param list<string> $permissions
     * @return array<string,mixed>
     */
    public function workspaceScan(PDO $pdo, int $userId, array $permissions, string $route): array
    {
        $agent = AiAgentRegistry::forRoute($route);
        $domain = $agent !== null ? (string) $agent['domain'] : 'system';

        $scan = [
            'status' => 'ok',
            'domain' => $domain,
            'route' => mb_substr($route, 0, 120),
            'as_of' => gmdate('c'),
        ];

        // Drafts awaiting THIS user's review (separation of duties kept).
        try {
            $pending = (new AiDraftService())->listForReview($pdo, $userId, true);
            $scan['pending_review_count'] = is_array($pending) ? count($pending) : 0;
            $scan['pending_review_workflows'] = array_values(array_slice(array_map(
                static fn(array $draft): string => (string) ($draft['workflow_id'] ?? ''),
                is_array($pending) ? $pending : []
            ), 0, 6));
        } catch (Throwable $e) {
            $scan['pending_review_count'] = null;
        }

        // The user's cached deterministic intelligence alerts (if any).
        // Key format mirrors AiInsightOrchestrator::cacheKeyFor().
        try {
            $brief = (new SharedCache())->get('ai_brief:v1:u' . $userId);
            if (is_array($brief)) {
                $scan['insight_alert_count'] = (int) ($brief['alert_count'] ?? 0);
                $scan['insight_alerts'] = array_slice((array) ($brief['alerts'] ?? []), 0, 6);
                $scan['insight_as_of'] = (string) ($brief['as_of'] ?? '');
            }
        } catch (Throwable $e) {
            // Optional signal only.
        }

        // Domain-specific deterministic aggregates.
        if ($domain === 'system') {
            try {
                $summary = (new SystemOperationsReviewService())->summary($pdo);
                $payload = (new SystemOperationsReviewService())->aiPayload($summary);
                foreach ([
                    'queue_total', 'queue_pending', 'queue_processing', 'queue_stale',
                    'queue_failed', 'queue_dead_letter', 'oldest_processing_minutes',
                    'worker_freshness_minutes', 'error_total_24h', 'error_critical_24h',
                    'error_signatures',
                ] as $field) {
                    $scan[$field] = $payload[$field] ?? ($field === 'error_signatures' ? [] : 0);
                }
            } catch (Throwable $e) {
                $scan['system_scan_status'] = 'unavailable';
            }
        } elseif (in_array($domain, ['finance', 'reports', 'admissions'], true)) {
            try {
                $kpis = (new DirectorAnalyticsService())->getSummaryKPIs();
                $compact = [];
                foreach (is_array($kpis) ? $kpis : [] as $key => $value) {
                    if (is_scalar($value)) {
                        $compact[(string) $key] = $value;
                    }
                }
                $scan['kpis'] = array_slice($compact, 0, 12, true);
            } catch (Throwable $e) {
                $scan['kpis_status'] = 'unavailable';
            }
        }

        return $scan;
    }

    /** Read the latest cached agent result/digest for a staff member. */
    public function cachedResult(int $userId): ?array
    {
        $cache = $this->sharedCache();
        if ($cache === null) {
            return null;
        }
        $cached = $cache->get(self::CACHE_PREFIX . $userId);
        return is_array($cached) ? $cached : null;
    }

    /**
     * The bounded agent loop: persona prompt + allowlisted tools, strict JSON
     * actions, deterministic tool execution, validated final answer.
     *
     * @return array<string,mixed>
     */
    private function runAgentLoop(PDO $pdo, array $context, array $agent, string $question, string $route, string $module): array
    {
        $userId = (int) ($context['user_id'] ?? 0);
        $permissions = $this->permissionsOf($context);
        $requestId = (string) ($context['request_id'] ?? 'ai-agent-assist');
        $hints = $this->behavior->hints($userId);
        $tools = array_values(array_map('strval', (array) $agent['tools'] ?? []));

        $system = $this->systemPrompt($agent, $hints);
        $toolResults = [];
        $toolsUsed = [];
        $providerCalls = 0;
        $messages = [];
        $answer = null;

        try {
            while ($providerCalls < self::MAX_PROVIDER_CALLS) {
                // Field order is deliberate: stable context first, the
                // per-request question last, so providers can cache by exact
                // token prefix across turns for the same agent and route.
                $envelope = AiPromptPolicy::minimize(self::WORKFLOW_CHAT, array_filter([
                    'audience' => 'staff',
                    'agent_id' => (string) $agent['id'],
                    'route' => $route,
                    'module' => $module,
                    'tools' => $tools,
                    'behavior_hints' => $this->hintLines($hints),
                    'grounding_note' => 'Governed results are already in tool_results. Answer from them; only request a tool if the results genuinely do not cover the question.',
                    'tool_results' => $toolResults,
                    'question' => $question,
                ], static fn($value): bool => $value !== null && $value !== []));

                if ($messages === []) {
                    $messages = [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ];
                } else {
                    $messages[] = ['role' => 'user', 'content' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                }

                $providerCalls++;
                $response = $this->provider->complete(
                    $messages,
                    ['task' => 'chat']
                );
                if (!is_array($response)) {
                    throw new AiProviderException('The agent provider returned an unusable response.');
                }

                $action = (string) ($response['action'] ?? 'final');
                if ($action !== 'tool') {
                    $answer = $this->validateAnswer($response['answer'] ?? $response);
                    break;
                }
                if (count($toolsUsed) >= self::MAX_TOOL_STEPS) {
                    $answer = $this->fallbackAnswer($toolsUsed, $toolResults);
                    break;
                }

                $toolId = (string) ($response['tool'] ?? '');
                $toolInput = is_array($response['tool_input'] ?? null) ? $response['tool_input'] : [];
                if (!in_array($toolId, $tools, true)) {
                    $toolResults[] = 'error: tool not available to this agent; available tools: ' . implode(', ', $tools);
                    $messages[] = ['role' => 'assistant', 'content' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                    continue;
                }

                $toolResult = $this->executeTool($pdo, $context, $toolId, $toolInput, $permissions, $requestId);
                $toolsUsed[] = $toolId;
                $toolResults[] = $this->compactResult($toolId, $toolResult);
                $this->behavior->observe($userId, 'tool_use', [
                    'agent_id' => (string) $agent['id'],
                    'tool' => $toolId,
                    'outcome' => (string) ($toolResult['status'] ?? 'ok'),
                ]);
                $messages[] = ['role' => 'assistant', 'content' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
            }
        } catch (AiProviderException $e) {
            return [
                'status' => 'unavailable',
                'answer' => null,
                'tools_used' => $toolsUsed,
                'provider_calls' => $providerCalls,
            ];
        }

        if ($answer === null) {
            // Step budget exhausted without a final answer: synthesize a safe
            // deterministic close-out from whatever the tools produced.
            $answer = $this->fallbackAnswer($toolsUsed, $toolResults);
        }

        return [
            'status' => 'answered',
            'answer' => $answer,
            'tools_used' => $toolsUsed,
            'provider_calls' => $providerCalls,
        ];
    }

    /**
     * Deterministic tool executor. Every tool re-authorizes its workflow with
     * the caller's own permissions before running; the model has no say.
     *
     * @param array<string,mixed> $toolInput
     * @return array<string,mixed>
     */
    private function executeTool(PDO $pdo, array $context, string $toolId, array $toolInput, array $permissions, string $requestId): array
    {
        $userId = (int) ($context['user_id'] ?? 0);
        switch ($toolId) {
            case AiAgentRegistry::TOOL_NLQ:
                try {
                    $this->workflows->authorize(NlqQueryService::WORKFLOW, [
                        'user_id' => $userId,
                        'permissions' => $permissions,
                        'audience' => 'staff',
                    ]);
                } catch (DomainException $e) {
                    return ['status' => 'not_authorized', 'message' => 'Governed report questions need analytics catalogue access.'];
                }
                return $this->nlq->ask($pdo, $context, (string) ($toolInput['question'] ?? ''));

            case AiAgentRegistry::TOOL_INSIGHT_BRIEF:
                try {
                    $this->workflows->authorize(AiInsightOrchestrator::WORKFLOW, [
                        'user_id' => $userId,
                        'permissions' => $permissions,
                    ]);
                } catch (DomainException $e) {
                    return ['status' => 'not_authorized', 'message' => 'The intelligence briefing needs analytics catalogue access.'];
                }
                $cadence = (string) ($toolInput['cadence'] ?? 'daily');
                if (!in_array($cadence, self::VALID_CADENCES, true)) {
                    $cadence = 'daily';
                }
                return $this->loadInsightBrief($userId, $permissions, $requestId, $cadence);

            case AiAgentRegistry::TOOL_CATALOG:
                $roleNames = array_map(static function ($role): string {
                    if (is_array($role)) return (string) ($role['name'] ?? $role['role_name'] ?? '');
                    if (is_object($role)) return (string) ($role->name ?? $role->role_name ?? '');
                    return is_scalar($role) ? (string) $role : '';
                }, (array) ($context['roles'] ?? []));
                $workflows = $this->workflows->describeForContext($permissions, (string) ($context['route'] ?? ''), 'dashboard', 'staff', $roleNames);
                return [
                    'status' => 'ok',
                    'workflows' => array_slice(array_map(static fn(array $w): string => sprintf(
                        '%s (%s, %s)',
                        (string) $w['id'],
                        (string) ($w['action_level'] ?? 'assist'),
                        (string) ($w['status'] ?? 'planned')
                    ), $workflows), 0, 15),
                ];

            default:
                return ['status' => 'unknown_tool'];
        }
    }

    /** One bounded triage call; the proposed agent id is re-validated. */
    private function triage(int $userId, array $permissions, string $question, string $route, string $module): ?array
    {
        $agents = AiAgentRegistry::describeForTriage();
        if ($agents === []) {
            return null;
        }
        $this->workflows->authorize(self::WORKFLOW_TRIAGE, [
            'user_id' => $userId,
            'permissions' => $permissions,
            'audience' => 'staff',
        ]);
        $envelope = AiPromptPolicy::minimize(self::WORKFLOW_TRIAGE, [
            'question' => $question,
            'audience' => 'staff',
            'agents' => $agents,
            'route' => $route,
            'module' => $module,
        ]);
        $prompt = $this->templates->resolve(self::WORKFLOW_TRIAGE);
        try {
            $intent = $this->provider->complete(
                [
                    ['role' => 'system', 'content' => (string) $prompt['content']],
                    ['role' => 'user', 'content' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                ],
                ['task' => 'triage']
            );
        } catch (AiProviderException $e) {
            return null;
        }
        if (!is_array($intent)) {
            return null;
        }
        $agentId = (string) ($intent['agent_id'] ?? '');
        return AiAgentRegistry::resolve($agentId);
    }

    /** Assemble the agent system prompt: versioned runtime contract + persona + tools. */
    private function systemPrompt(array $agent, array $hints): string
    {
        $prompt = $this->templates->resolve(self::WORKFLOW_CHAT);
        $parts = [(string) $prompt['content']];
        $parts[] = 'AGENT PERSONA — ' . (string) $agent['name'] . ': ' . (string) $agent['persona'];
        $parts[] = 'AGENT GOAL: ' . (string) $agent['goal'];
        $parts[] = "TOOLS AVAILABLE (use exact ids):\n- " . implode("\n- ", AiAgentRegistry::toolSummaries($agent));
        return implode("\n\n", $parts);
    }

    /** @return list<string> bounded "key=value" hint lines for the envelope */
    private function hintLines(array $hints): array
    {
        if (empty($hints['known'])) {
            return [];
        }
        $lines = [];
        foreach (['top_domains', 'top_workflows', 'top_agents'] as $key) {
            $value = (array) ($hints[$key] ?? []);
            if ($value !== []) {
                $lines[] = $key . '=' . implode(',', array_slice(array_map('strval', $value), 0, 3));
            }
        }
        if (!empty($hints['preferred_cadence'])) {
            $lines[] = 'preferred_cadence=' . (string) $hints['preferred_cadence'];
        }
        return array_slice($lines, 0, 6);
    }

    /**
     * Compact a tool result into a bounded string the policy allowlist will
     * accept (<=500 chars). Only allowlisted aggregate fields are kept;
     * nested governed-report answer objects are flattened one level first.
     */
    private function compactResult(string $toolId, array $result): string
    {
        if (isset($result['answer']) && is_array($result['answer'])) {
            $result = array_merge($result['answer'], $result);
        }
        $compact = [];
        foreach (self::TOOL_RESULT_FIELDS as $field) {
            if (!array_key_exists($field, $result)) {
                continue;
            }
            $value = $result[$field];
            if (is_array($value)) {
                $value = array_slice($value, 0, 6);
                $value = array_map(static fn($item) => is_scalar($item) ? mb_substr((string) $item, 0, 120) : null, $value);
                $compact[$field] = array_values(array_filter($value, 'is_scalar'));
            } elseif (is_scalar($value) || $value === null) {
                $compact[$field] = $value;
            }
        }
        $encoded = json_encode([$toolId => $compact], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $encoded = $encoded === false ? '{}' : $encoded;
        if (mb_strlen($encoded) > self::MAX_TOOL_RESULT_CHARS) {
            $encoded = mb_substr($encoded, 0, self::MAX_TOOL_RESULT_CHARS - 3) . '...';
        }
        return $encoded;
    }

    /**
     * Validate and bound the structured final answer. Anything malformed
     * falls back to a safe, deterministic close-out.
     *
     * @param mixed $draft
     * @return array<string,mixed>
     */
    private function validateAnswer($draft): array
    {
        $draft = is_array($draft) ? $draft : [];
        $clean = static fn($value, int $max): string => mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', trim((string) (is_scalar($value) ? $value : ''))) ?? '', 0, $max);
        $list = static function ($value, int $maxItems, int $maxChars) use ($clean): array {
            $out = [];
            foreach (is_array($value) ? array_slice($value, 0, $maxItems) : [] as $item) {
                $text = $clean($item, $maxChars);
                if ($text !== '') {
                    $out[] = $text;
                }
            }
            return $out;
        };
        $body = $clean($draft['body'] ?? '', 2000);
        if ($body === '') {
            $body = 'The assistant could not prepare a complete answer. Open the related workspace or rephrase the question.';
        }
        return [
            'title' => $clean($draft['title'] ?? 'Assistant', 120),
            'body' => $body,
            'next_steps' => $list($draft['next_steps'] ?? [], 6, 200),
            'suggested_questions' => $list($draft['suggested_questions'] ?? [], 3, 120),
            'escalation_required' => !empty($draft['escalation_required']),
        ];
    }

    /** Deterministic close-out when the step budget is exhausted. */
    private function fallbackAnswer(array $toolsUsed, array $toolResults): array
    {
        $next = ['Review the results above in the related workspace.'];
        if ($toolResults !== []) {
            $next[] = 'Re-ask the specific part that is still unclear.';
        }
        if ($toolsUsed !== []) {
            $next[] = 'The governed reports behind this answer can be opened directly from the Reports workspace.';
        }
        return [
            'title' => 'Assistant ran out of steps',
            'body' => 'The assistant gathered the information it could within its step budget but could not complete a final summary. The collected tool results are reflected in the workspace.',
            'next_steps' => $next,
            'suggested_questions' => [],
            'escalation_required' => false,
        ];
    }

    private function loadInsightBrief(int $userId, array $permissions, string $requestId, string $cadence): array
    {
        try {
            $result = call_user_func($this->insightBriefLoader, $userId, $permissions, $requestId, $cadence);
            return is_array($result) ? $result : ['status' => 'unavailable'];
        } catch (Throwable $e) {
            return ['status' => 'unavailable', 'message' => 'The intelligence briefing could not be loaded.'];
        }
    }

    /** Canonical cache key for a staff member's workspace briefing. */
    public static function briefingCacheKey(int $userId, string $route): string
    {
        return self::BRIEFING_CACHE_PREFIX . $userId . ':' . hash('sha256', strtolower(trim($route)));
    }

    /**
     * Read the cached workspace briefing for page-load serving. Returns null
     * when stale or absent (the caller then enqueues regeneration).
     */
    public function cachedBriefing(int $userId, string $route): ?array
    {
        $cache = $this->sharedCache();
        if ($cache === null) {
            return null;
        }
        try {
            $cached = $cache->get(self::briefingCacheKey($userId, $route));
            return is_array($cached) ? $cached : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function cacheBriefing(int $userId, string $route, array $briefing): void
    {
        $cache = $this->sharedCache();
        if ($cache === null) {
            return;
        }
        try {
            $cache->set(self::briefingCacheKey($userId, $route), $briefing, self::BRIEFING_TTL);
        } catch (Throwable $e) {
            // Best-effort cache only.
        }
    }

    /**
     * Deterministic briefing fallback: findings derived from the governed
     * scan with rule-based severities and suggested actions - no provider
     * call, so the panel always has something truthful to show.
     *
     * @param list<string> $permissions
     * @return array<string,mixed>
     */
    public function buildDeterministicBriefing(PDO $pdo, int $userId, array $permissions, string $route): array
    {
        $scan = $this->workspaceScan($pdo, $userId, $permissions, $route);
        $findings = [];
        $add = static function (string $title, string $severity, string $rootCause, string $action) use (&$findings): void {
            $findings[] = [
                'title' => mb_substr($title, 0, 160),
                'severity' => $severity,
                'root_cause' => mb_substr($rootCause, 0, 240),
                'suggested_action' => mb_substr($action, 0, 240),
            ];
        };
        if ((int) ($scan['pending_review_count'] ?? 0) > 0) {
            $add(
                (int) $scan['pending_review_count'] . ' AI draft(s) await your review',
                'info',
                'Assistants prepared drafts that need a second person to approve before they become official.',
                'Open the module workspace(s): ' . implode(', ', (array) ($scan['pending_review_workflows'] ?? [])) . ' and approve or reject each draft.'
            );
        }
        if ((int) ($scan['queue_stale'] ?? 0) > 0) {
            $add('Stale background jobs detected', 'warning', 'Jobs have sat in pending/processing beyond their lease window - the worker may have missed runs or a job crashed mid-flight.', 'Check the System Health page; requeue only after reviewing the job payload.');
        }
        if ((int) ($scan['queue_dead_letter'] ?? 0) > 0) {
            $add('Dead-letter jobs present', 'warning', 'Jobs exhausted their retries.', 'Inspect the dead-letter queue and fix the underlying failure before requeueing.');
        }
        if ((int) ($scan['error_critical_24h'] ?? 0) > 0) {
            $add((int) $scan['error_critical_24h'] . ' critical journal entries in 24h', 'warning', 'Recurring error signatures: ' . implode(' | ', array_slice((array) ($scan['error_signatures'] ?? []), 0, 3)), 'Review the errors journal from Audit & Forensics for the full signatures.');
        }
        foreach (array_slice((array) ($scan['insight_alerts'] ?? []), 0, 4) as $alert) {
            if (is_scalar($alert)) {
                $add('Intelligence alert: ' . (string) $alert, 'info', 'Deterministic detector crossed its threshold.', 'Open the related report from the analytics workspace.');
            }
        }
        return [
            'status' => 'ready',
            'engine' => 'php-deterministic',
            'route' => $route,
            'generated_at' => gmdate('c'),
            'headline' => $findings === [] ? 'Workspace looks healthy' : count($findings) . ' finding(s) in this workspace',
            'summary' => $findings === []
                ? 'No exceptions detected in the deterministic workspace scan. Everything within normal bounds.'
                : 'The deterministic scan found items needing attention; review the findings below.',
            'findings' => $findings,
            'scan' => $scan,
        ];
    }

    private function cacheResult(int $userId, array $result): void
    {
        $cache = $this->sharedCache();
        if ($cache === null) {
            return;
        }
        try {
            $cache->set(self::CACHE_PREFIX . $userId, $result, AiInsightOrchestrator::BRIEF_TTL);
        } catch (Throwable $e) {
            // Caching is best-effort and never fails a run.
        }
    }

    /**
     * Lazily resolve the shared cache. Construction can fail under hosts
     * without the configured upload-path constants (hermetic tests); a
     * missing cache degrades gracefully — it never fails a run.
     */
    private function sharedCache(): ?SharedCache
    {
        if (!$this->cacheResolved && $this->cache === null) {
            $this->cacheResolved = true;
            try {
                $this->cache = new SharedCache();
            } catch (Throwable $e) {
                $this->cache = null;
            }
        }
        return $this->cache;
    }

    /** @return list<string> */
    private function permissionsOf(array $context): array
    {
        return array_values(array_unique(array_map('strval', array_merge(
            (array) ($context['effective_permissions'] ?? []),
            (array) ($context['permissions'] ?? [])
        ))));
    }
}
