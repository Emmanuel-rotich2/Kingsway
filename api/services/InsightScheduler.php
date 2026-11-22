<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use DomainException;
use PDO;
use Throwable;

/**
 * InsightScheduler - decides WHEN and WHETHER deterministic intelligence
 * findings become a reviewable insight briefing (roadmap P3b).
 *
 * This service owns the "three proactive trigger patterns" for the briefing
 * surface:
 *   1. Scheduled (cron): a worker-authenticated API endpoint reached by a single
 *      `curl` line in the crontab (both localhost and production) calls
 *      enqueueBrief() for a daily/weekly/term cadence; the worker executes the
 *      job later. scripts/cron/briefings.php is dormant and must not be
 *      scheduled — see AGENTS.md "Background scheduling (curl crontab only)".
 *   2. Event-driven (server-side PHP hooks): a domain service calls
 *      enqueueBrief() after a mutation; frontend JS never pushes provider work.
 *   3. Page-load: /api/dashboard/insight-brief calls pageLoad(), which serves a
 *      fresh cached briefing (<1h) immediately and, when stale, enqueues a
 *      background regeneration and returns a "generating" state. There is never
 *      a synchronous provider call on page load.
 *
 * Governance rules enforced here (see AGENTS.md):
 *   - The service decides only WHEN/WHETHER to call the provider; it never holds
 *     a provider credential and delegates all drafting to DraftRepository under
 *     the governed reports.school_brief workflow (prompt policy allowlist +
 *     reviewable draft lifecycle + approval separation).
 *   - Findings are computed by the deterministic IntelligenceEngine (no LLM);
 *     the provider is an explanation/generation layer only.
 *   - Background regeneration reuses the existing JobQueue (ai.insight.generate)
 *     - never a second queue - and the worker re-authorizes with the recorded
 *     operator context so a queued run cannot broaden scope.
 *   - All batches, drafts, and skips are journaled to the ai_generation file
 *     journal (never a database log table).
 */
final class InsightScheduler
{
    public const JOB_TYPE = 'ai.insight.generate';
    public const WORKFLOW = 'reports.school_brief';
    public const CADENCE_DAILY = 'daily';
    public const CADENCE_WEEKLY = 'weekly';
    public const CADENCE_TERM = 'term';
    public const BRIEF_TTL = 3600;

    private const VALID_CADENCES = [self::CADENCE_DAILY, self::CADENCE_WEEKLY, self::CADENCE_TERM];
    private const AUDIENCE_ALLOWLIST = ['', 'staff', 'leadership', 'academic', 'finance', 'attendance'];
    private const MAX_SUMMARY_ITEMS = 20;

    /** @var SharedCache */
    private $cache;

    /** @var callable(string,array):int */
    private $queuer;

    /** @var IntelligenceEngine|null */
    private $engine;

    /** @var DraftRepository|null */
    private $drafts;

    /** @var callable():bool provider-enabled gate (injectable for hermetic tests) */
    private $providerEnabled;

    /** @var array<string,mixed> per-domain options forwarded to IntelligenceEngine */
    private $engineOptions;

    /** @var callable(PDO,int):list<string>|null authoritative resolver seam */
    private $permissionResolver;

    /**
     * @param DraftRepository|null  $drafts null disables narrative drafting;
     * @param callable():bool|null $providerEnabled
     * @param array<string,mixed>  $engineOptions
     * @param callable(PDO,int):list<string>|null $permissionResolver
     */
    public function __construct(
        ?SharedCache $cache = null,
        ?callable $queuer = null,
        ?IntelligenceEngine $engine = null,
        ?DraftRepository $drafts = null,
        ?callable $providerEnabled = null,
        array $engineOptions = [],
        ?callable $permissionResolver = null
    ) {
        $this->cache = $cache ?: new SharedCache();
        $this->queuer = $queuer ?: static fn (string $jobType, array $payload): int => JobQueue::push(
            $jobType,
            $payload,
            0,
            3,
            60
        );
        $this->engine = $engine;
        $this->drafts = $drafts;
        $this->providerEnabled = $providerEnabled ?: static fn (): bool => filter_var(
            Config::get('AI_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $this->engineOptions = $engineOptions;
        $this->permissionResolver = $permissionResolver;
    }

    /**
     * Queue a briefing generation on the existing background queue.
     *
     * @param array<string,mixed> $context {user_id, permissions, request_id}
     * @param array<string,mixed> $options {domains, cache_key, audience, broadcast}
     * @return array<string,mixed> {job_id, workflow_id, cadence, cache_key, status}
     */
    public function enqueueBrief(string $cadence, array $context, array $options = []): array
    {
        if (!in_array($cadence, self::VALID_CADENCES, true)) {
            throw new DomainException('An unsupported insight briefing cadence was supplied.', 422);
        }
        $userId = (int) ($context['user_id'] ?? 0);
        $permissions = array_values(array_map('strval', (array) ($context['permissions'] ?? [])));
        if ($userId < 1) {
            throw new DomainException('An authenticated briefing owner is required.', 401);
        }
        $this->authorize($userId, $permissions);

        $domains = $this->validateDomains($options['domains'] ?? null);

        $cacheKey = (string) ($options['cache_key'] ?? '');
        if ($cacheKey === '' || strlen($cacheKey) > 200) {
            $cacheKey = $this->cacheKeyFor($userId);
        }
        $audience = (string) ($options['audience'] ?? 'staff');
        if (!in_array($audience, self::AUDIENCE_ALLOWLIST, true)) {
            throw new DomainException('An unsupported briefing audience was supplied.', 422);
        }

        $callerRequestId = substr((string) ($context['request_id'] ?? 'ai-insight-brief'), 0, 100);
        $broadcast = array_key_exists('broadcast', $options) ? (bool) $options['broadcast'] : true;
        // One job per cache window: the brief is cached for BRIEF_TTL, so a
        // day-long key matched the finished job and an expired brief could not
        // be regenerated until midnight.
        $windowStart = intdiv(time(), self::BRIEF_TTL) * self::BRIEF_TTL;
        $window = date('Y-m-d\TH:i', $windowStart);
        // JobQueue reuses a key only for an identical payload, so the key must
        // cover every field that can vary and the payload must carry nothing
        // per-call (the caller's request id differs on every page load).
        $idempotencyKey = 'ai-brief:' . hash('sha256', implode('|', [
            $cadence,
            (string) $userId,
            $cacheKey,
            implode(',', $domains ?? []),
            implode(',', $permissions),
            $audience,
            $broadcast ? '1' : '0',
            $window,
        ]));
        $payload = [
            'cadence' => $cadence,
            'domains' => $domains,
            'user_id' => $userId,
            'permissions' => $permissions,
            'request_id' => 'ai-brief-' . substr($idempotencyKey, 9, 24),
            'cache_key' => $cacheKey,
            'audience' => $audience,
            'broadcast' => $broadcast,
            'window' => $window,
            'idempotency_key' => $idempotencyKey,
        ];
        $jobId = call_user_func($this->queuer, self::JOB_TYPE, $payload);

        FileLogger::write('ai_generation', [
            'type' => 'insight_brief_queued',
            'job_id' => $jobId,
            'cadence' => $cadence,
            'domains' => $domains,
            'operator_id' => $userId,
            'cache_key' => $cacheKey,
            'request_id' => $payload['request_id'],
            'caller_request_id' => $callerRequestId,
        ]);

        return [
            'job_id' => $jobId,
            'workflow_id' => self::WORKFLOW,
            'cadence' => $cadence,
            'cache_key' => $cacheKey,
            'status' => 'queued',
        ];
    }

    /**
     * Page-load insight surface: serve the fresh cached briefing (<1h) or
     * enqueue a background regeneration and report "generating". Never performs
     * a synchronous provider call on page load.
     *
     * @param int[] $permissions effective permission codes for the caller
     * @param array<string,mixed> $options {cadence, audience, broadcast}
     * @return array<string,mixed>
     */
    public function pageLoad(int $userId, array $permissions, string $requestId, array $options = []): array
    {
        if ($userId < 1) {
            throw new DomainException('An authenticated briefing owner is required.', 401);
        }
        $permissions = array_values(array_map('strval', $permissions));
        $this->authorize($userId, $permissions);

        $cacheKey = $this->cacheKeyFor($userId);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return array_merge((array) $cached, [
                'status' => 'ready',
                'cache_key' => $cacheKey,
            ]);
        }

        $queued = $this->enqueueBrief((string) ($options['cadence'] ?? self::CADENCE_DAILY), [
            'user_id' => $userId,
            'permissions' => $permissions,
            'request_id' => $requestId,
        ], [
            'cache_key' => $cacheKey,
            'audience' => (string) ($options['audience'] ?? 'staff'),
            'broadcast' => array_key_exists('broadcast', $options) ? (bool) $options['broadcast'] : false,
        ]);

        return [
            'status' => 'generating',
            'cache_key' => $cacheKey,
            'job_id' => (int) ($queued['job_id'] ?? 0),
            'workflow_id' => self::WORKFLOW,
            'last_generated_at' => null,
        ];
    }

    /**
     * Worker execution for ai.insight.generate: re-authorizes with the recorded
     * operator, runs the deterministic engine, then - when the provider is
     * enabled - creates a reviewable draft via DraftRepository. The deterministic
     * summary is always cached so page-load and the notification bell get data
     * even when the provider is unavailable.
     *
     * @param array<string,mixed> $payload enqueued by enqueueBrief()
     * @return array<string,mixed> {status, cache_key, generated_at, as_of, alert_count, draft_id, provider_used}
     */
    public function execute(PDO $pdo, array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId < 1) {
            throw new DomainException('Background insight briefing payload is incomplete.', 422);
        }
        $permissions = $this->permissionResolver !== null
            ? (array) call_user_func($this->permissionResolver, $pdo, $userId)
            // Resolved through the leaf scope resolver, not the agent
            // assistant: instantiating the assistant from here completed a
            // cycle (AgentAssistant -> AgentToolExecutor -> InsightScheduler
            // -> AgentAssistant) that made every background brief unsafe to run.
            : (new OperatorScopeResolver($pdo))->permissions($userId);
        if ($permissions === []) {
            throw new DomainException('Current operator permissions could not be resolved.', 403);
        }
        $cadence = (string) ($payload['cadence'] ?? self::CADENCE_DAILY);
        if (!in_array($cadence, self::VALID_CADENCES, true)) {
            throw new DomainException('An unsupported insight briefing cadence was supplied.', 422);
        }
        $this->authorize($userId, $permissions);

        $domains = $this->validateDomains($payload['domains'] ?? null);
        $cacheKey = (string) ($payload['cache_key'] ?? $this->cacheKeyFor($userId));
        $audience = (string) ($payload['audience'] ?? 'staff');
        // `broadcast` is still accepted in the payload contract but has no
        // effect: background work is never announced to browsers.
        $requestId = substr((string) ($payload['request_id'] ?? 'ai-insight-brief'), 0, 100);

        $engine = $this->engine ?: new IntelligenceEngine($pdo, $this->engineOptions);
        $report = $engine->run($domains, false, true);

        $alertCount = (int) ($report['alert_count'] ?? 0);
        $alertsSummary = array_map(
            static fn (array $alert): string => sprintf(
                '[%s] %s: %s',
                (string) ($alert['level'] ?? 'info'),
                (string) ($alert['code'] ?? 'alert'),
                mb_substr(trim((string) ($alert['message'] ?? '')), 0, 500)
            ),
            array_slice((array) ($report['alerts'] ?? []), 0, self::MAX_SUMMARY_ITEMS)
        );
        $metricsSummary = $this->flattenMetrics((array) ($report['domains'] ?? []));

        $input = [
            'cadence' => $cadence,
            'report_date' => gmdate('Y-m-d'),
            'as_of' => (string) ($report['as_of'] ?? gmdate('Y-m-d')),
            'domains_ran' => implode(',', (array) ($report['ran'] ?? [])),
            'alert_count' => (string) $alertCount,
            'alerts_summary' => $alertsSummary,
            'metrics_summary' => $metricsSummary,
            'audience' => $audience,
        ];

        $draftId = null;
        if (call_user_func($this->providerEnabled) && $this->drafts !== null) {
            try {
                $draft = $this->drafts->create($pdo, self::WORKFLOW, [
                    'user_id' => $userId,
                    'permissions' => $permissions,
                    'request_id' => $requestId,
                ], $input, [
                    'subject_type' => 'school_insight_brief',
                    'subject_id' => 0,
                    'scope' => $audience,
                    'cadence' => $cadence,
                    'execution' => 'background',
                ]);
                $draftId = (int) ($draft['draft_id'] ?? 0);
            } catch (Throwable $e) {
                FileLogger::write('ai_generation', [
                    'type' => 'insight_brief_draft_failed',
                    'cadence' => $cadence,
                    'operator_id' => $userId,
                    'reason' => $e->getMessage(),
                    'request_id' => $requestId,
                ], 'warning');
            }
        } else {
            FileLogger::write('ai_generation', [
                'type' => 'insight_brief_provider_skipped',
                'cadence' => $cadence,
                'operator_id' => $userId,
                'reason' => call_user_func($this->providerEnabled) ? 'draft_service_unavailable' : 'ai_disabled_by_config',
                'request_id' => $requestId,
            ]);
        }

        $brief = [
            'generated_at' => (string) ($report['generated_at'] ?? gmdate('c')),
            'as_of' => (string) ($report['as_of'] ?? gmdate('Y-m-d')),
            'cadence' => $cadence,
            'audience' => $audience,
            'domains_ran' => (array) ($report['ran'] ?? []),
            'alert_count' => $alertCount,
            'alerts' => $alertsSummary,
            'metrics' => $metricsSummary,
            'draft_id' => $draftId,
            'provider_used' => $draftId !== null,
        ];
        $this->cache->set($cacheKey, $brief, self::BRIEF_TTL);

        FileLogger::write('ai_generation', [
            'type' => 'insight_brief_executed',
            'cadence' => $cadence,
            'domains_ran' => (array) ($report['ran'] ?? []),
            'alert_count' => $alertCount,
            'draft_id' => $draftId,
            'provider_used' => $draftId !== null,
            'operator_id' => $userId,
            'cache_key' => $cacheKey,
            'request_id' => $requestId,
        ]);

        return [
            'status' => 'ready',
            'cache_key' => $cacheKey,
            'generated_at' => $brief['generated_at'],
            'as_of' => $brief['as_of'],
            'alert_count' => $alertCount,
            'draft_id' => $draftId,
            'provider_used' => $draftId !== null,
        ];
    }

    private function authorize(int $userId, array $permissions): void
    {
        (new WorkflowAuthorization())->authorize(self::WORKFLOW, [
            'user_id' => $userId,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Allowlist domains against the engine catalogue; null means all domains.
     *
     * @param mixed $requested
     */
    private function validateDomains($requested): ?array
    {
        if (!is_array($requested) || $requested === []) {
            return null;
        }
        $domains = [];
        foreach ($requested as $domain) {
            $domain = (string) $domain;
            if (!isset(IntelligenceEngine::DETECTORS[$domain])) {
                throw new DomainException("Unsupported intelligence domain '{$domain}'.", 422);
            }
            $domains[] = $domain;
        }
        return array_values(array_unique($domains));
    }

    /**
     * Flatten each detector's metrics into bounded scalar lines
     * ("domain.metric=value") that satisfy the prompt-policy aggregate allowlist.
     */
    private function flattenMetrics(array $domains): array
    {
        $lines = [];
        foreach ($domains as $name => $result) {
            if (!is_array($result)) {
                continue;
            }
            $metrics = is_array($result['metrics'] ?? null) ? $result['metrics'] : [];
            foreach ($metrics as $key => $value) {
                if (count($lines) >= self::MAX_SUMMARY_ITEMS) {
                    return $lines;
                }
                $display = is_scalar($value)
                    ? trim((string) $value)
                    : trim((string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $lines[] = (string) $name . '.' . (string) $key . '=' . mb_substr($display, 0, 500);
            }
        }
        return $lines;
    }

    private function cacheKeyFor(int $userId): string
    {
        return 'ai_brief:v1:u' . $userId;
    }
}
