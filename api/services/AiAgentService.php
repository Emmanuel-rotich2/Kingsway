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
    public function runBackground(PDO $pdo, array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $permissions = array_values(array_map('strval', (array) ($payload['permissions'] ?? [])));
        if ($userId < 1) {
            throw new DomainException('Background agent payload is incomplete.', 422);
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
        $base = array_merge($payload, [
            'user_id' => $userId,
            'permissions' => array_values(array_map('strval', $permissions)),
        ]);
        $mode = (string) ($base['mode'] ?? 'digest');
        $base['idempotency_key'] = 'ai-agent:' . hash('sha256', implode('|', [
            $mode,
            (string) $userId,
            (string) ($base['cadence'] ?? ''),
            (string) ($base['question'] ?? ''),
            date('Y-m-d'),
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
        $permissions = array_values(array_map('strval', (array) ($operator['permissions'] ?? [])));
        if ($userId < 1) {
            throw new DomainException('A recorded operator is required.', 422);
        }
        $tool = (string) ($payload['tool'] ?? '');
        $allowedTools = [AiAgentRegistry::TOOL_NLQ, AiAgentRegistry::TOOL_INSIGHT_BRIEF, AiAgentRegistry::TOOL_CATALOG];
        if (!in_array($tool, $allowedTools, true)) {
            return ['status' => 'unknown_tool'];
        }
        $toolInput = is_array($payload['tool_input'] ?? null) ? $payload['tool_input'] : [];
        if ($tool === AiAgentRegistry::TOOL_NLQ) {
            $toolInput = ['question' => mb_substr((string) ($toolInput['question'] ?? ''), 0, self::MAX_QUESTION_CHARS)];
        } elseif ($tool === AiAgentRegistry::TOOL_INSIGHT_BRIEF) {
            $cadence = (string) ($toolInput['cadence'] ?? 'daily');
            $toolInput = ['cadence' => in_array($cadence, self::VALID_CADENCES, true) ? $cadence : 'daily'];
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
        $result = $this->executeTool($pdo, $context, $tool, $toolInput, $permissions, (string) $context['request_id']);

        FileLogger::write('ai_generation', [
            'type' => 'agent_tool_executed',
            'operator_id' => $userId,
            'tool' => $tool,
            'outcome' => (string) ($result['status'] ?? 'ok'),
        ]);
        return $result;
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
                $envelope = AiPromptPolicy::minimize(self::WORKFLOW_CHAT, array_filter([
                    'question' => $question,
                    'audience' => 'staff',
                    'agent_id' => (string) $agent['id'],
                    'route' => $route,
                    'module' => $module,
                    'behavior_hints' => $this->hintLines($hints),
                    'tools' => $tools,
                    'tool_results' => $toolResults,
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
                $response = $this->provider->complete($messages);
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
            $intent = $this->provider->complete([
                ['role' => 'system', 'content' => (string) $prompt['content']],
                ['role' => 'user', 'content' => json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ]);
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
