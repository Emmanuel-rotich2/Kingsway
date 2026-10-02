<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\API\Services\SharedCache;
use App\API\Modules\reports\ReportsAPI;
use DomainException;
use PDO;
use RuntimeException;

/**
 * Governed NLQ ("talk to your data") engine.
 *
 * The provider is used ONLY to parse the staff member's natural-language
 * question into a constrained report intent. Execution is deterministic and
 * always routed through the governed ReportsAPI with the caller's own role and
 * row-level scope. The model never receives learner/staff records, never sees
 * row data, and never receives or produces SQL — it only selects a report code
 * and filters from the catalogue already authorized for that user.
 *
 * Security boundary: every provider-supplied `report_code` and filter key is
 * re-validated against the server-side accessible catalogue. Anything not
 * explicitly authorized is discarded; an empty authorized catalogue returns
 * "cannot_answer" without any provider call.
 */
class NlqQueryService
{
    public const WORKFLOW = 'system.nlq_query';

    private const MAX_QUESTION_CHARS = 500;
    private const MAX_REPORTS = 60;
    private const MAX_ROWS = 25;
    private const MAX_FILTER_ITEMS = 10;

    /** @var AiCompletionProvider */
    private $provider;

    /** @var AiPromptTemplateService */
    private $templates;

    /** @var callable|null fn(array $user): array */
    private $catalogueProvider;

    /** @var callable|null fn(string $code, array $params, array $user, string $requestId): array */
    private $executor;

    /** @var SharedCache|null Injected cache; null disables intent caching. */
    private ?SharedCache $cache;

    /** @var bool Set once we decide to build a default cache. */
    private bool $cacheResolved = false;

    public function __construct(
        ?AiCompletionProvider $provider = null,
        ?AiPromptTemplateService $templates = null,
        ?callable $catalogueProvider = null,
        ?callable $executor = null,
        ?SharedCache $cache = null
    ) {
        $this->cache = $cache;
        $this->provider = $provider ?: new AiProviderGateway();
        $this->templates = $templates ?: new AiPromptTemplateService();
        $this->catalogueProvider = $catalogueProvider;
        $this->executor = $executor;
    }

    /**
     * @param array $context authenticated envelope:
     *        user_id, roles, permissions, effective_permissions, request_id, audience
     */
    public function ask(PDO $pdo, array $context, string $question): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_CHARS) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        $audience = (string) ($context['audience'] ?? 'staff');
        if ($audience !== 'staff') {
            throw new DomainException('This assistant only serves authenticated staff.', 403);
        }

        // The user-supplied portion passes through the audience policy first.
        $clean = AiPromptPolicy::minimize(self::WORKFLOW, [
            'question' => $question,
            'audience' => $audience,
        ]);

        $user = $this->normalizeUser($context);
        $catalogue = $this->authorizedCatalogue($pdo, $user);
        if ($catalogue === []) {
            $this->journal($context, $question, null, 'no_reports', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'no_reports',
                'message' => 'No governed report is available to your role for this question.',
            ];
        }

        // Intent parsing is a pure function of the question and the caller's
        // authorized catalogue, so it is cached per (operator, question,
        // catalogue fingerprint). Staff ask repeated questions constantly
        // during a term, and this removes both the provider call and its
        // ~10s latency from every repeat.
        $fingerprint = substr(
            hash('sha256', json_encode([
                'q' => $this->normalizeQuestion((string) $clean['question']),
                'c' => $this->catalogueFingerprint($catalogue),
            ])),
            0,
            32
        );
        $cache = $this->intentCache();

        try {
            $cachedIntent = $cache !== null ? $cache->get($this->cacheKey($user, $fingerprint)) : null;
            $hit = is_array($cachedIntent) && $cachedIntent !== [];
            $intent = $hit
                ? $cachedIntent
                : $this->parseIntent($clean['question'], (string) $clean['audience'], $catalogue);
            if (!$hit && $cache !== null) {
                // Short-lived: the catalogue can change under a deploy.
                $cache->set($this->cacheKey($user, $fingerprint), $intent, 900);
            }
        } catch (AiProviderException $e) {
            $this->journal($context, $question, null, 'unavailable', 0);
            return [
                'status' => 'unavailable',
                'reason' => 'provider_unavailable',
                'message' => 'The assistant is not available right now. Use the governed reports directly.',
            ];
        }

        if (($intent['cannot_answer'] ?? true) === true || trim((string) ($intent['report_code'] ?? '')) === '') {
            $this->journal($context, $question, null, 'cannot_answer', 0);
            $clarification = $this->boundedText((string) ($intent['clarification'] ?? ''), 300);
            return [
                'status' => 'cannot_answer',
                'reason' => 'unsupported',
                'message' => $clarification !== ''
                    ? $clarification
                    : 'That question is outside the governed reports available to you.',
            ];
        }

        $byCode = [];
        foreach ($catalogue as $report) {
            $byCode[(string) $report['code']] = $report;
        }
        $code = strtoupper(trim((string) $intent['report_code']));
        if (!isset($byCode[$code])) {
            $this->journal($context, $question, null, 'report_not_allowed', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'report_not_allowed',
                'message' => 'That question maps to a report you are not authorized to run.',
            ];
        }

        $report = $byCode[$code];
        $validation = $this->validateFilters($intent['filters'] ?? [], $report);
        if ($validation['missing'] !== []) {
            $this->journal($context, $question, null, 'clarification', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'missing_filters',
                'message' => 'More detail is needed to run this report: ' . implode(', ', $validation['missing']) . '.',
            ];
        }

        $requestId = (string) ($context['request_id'] ?? '');
        try {
            $result = $this->execute($pdo, $code, $validation['filters'], $user, $requestId);
        } catch (RuntimeException | DomainException $e) {
            $this->journal($context, $question, $code, 'execution_rejected', 0);
            return [
                'status' => 'cannot_answer',
                'reason' => 'execution_rejected',
                'message' => 'That report could not be generated within your authorized scope.',
            ];
        }

        $answer = $this->buildAnswer($result, $validation['filters']);
        $this->journal($context, $question, $code, 'answered', (int) $answer['row_count']);

        return [
            'status' => 'answered',
            'audience' => 'staff',
            'intent' => [
                'report_code' => (string) $answer['report_code'],
                'filters' => $validation['filters'],
                'confidence' => max(0.0, min(1.0, (float) ($intent['confidence'] ?? 0))),
            ],
            'explanation' => $this->boundedText((string) ($intent['explanation'] ?? ''), 600),
            'answer' => $answer,
        ];
    }

    /**
     * The catalogue the caller may execute. Only authorized, executable
     * reports are ever assembled into a provider prompt.
     */
    private function authorizedCatalogue(PDO $pdo, array $user): array
    {
        $provider = $this->catalogueProvider
            ?: static fn(array $u): array => (new AnalyticsReportRegistryService($pdo))->listCatalogue($u);
        $definitions = $provider($user);
        if (!is_array($definitions)) {
            return [];
        }

        $catalogue = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }
            if (empty($definition['capabilities']['execute'])) {
                continue;
            }
            $code = strtoupper(trim((string) ($definition['code'] ?? '')));
            if ($code === '' || preg_match('/^[A-Z][A-Z0-9_]{2,99}$/', $code) !== 1) {
                continue;
            }
            $catalogue[] = [
                'code' => $code,
                'title' => $this->boundedText((string) ($definition['title'] ?? ''), 160),
                'description' => $this->boundedText((string) ($definition['description'] ?? ''), 200),
                'domain' => $this->boundedText((string) ($definition['domain'] ?? ''), 60),
                'grain' => $this->boundedText((string) ($definition['grain'] ?? ''), 60),
                'allowed_filters' => $this->stringList($definition['allowed_filters'] ?? [], 40),
                'required_filters' => $this->stringList($definition['required_filters'] ?? [], 20),
                'default_filters' => is_array($definition['default_filters'] ?? null)
                    ? $definition['default_filters']
                    : [],
            ];
            if (count($catalogue) >= self::MAX_REPORTS) {
                break;
            }
        }
        return $catalogue;
    }

    /**
     * Canonical question form for cache lookup: case, spacing, and trailing
     * punctuation are not meaningful differences for staff rephrasing the same
     * question, so they must not force a second intent parse.
     */
    private function normalizeQuestion(string $question): string
    {
        $question = mb_strtolower(trim($question));
        $question = (string) preg_replace('/[^a-z0-9]+/', ' ', $question);
        return trim($question);
    }

    /** @param array<string,mixed> $user */
    private function cacheKey(array $user, string $fingerprint): string
    {
        // Keyed by operator: an intent is only ever reused for the same
        // authorized principal, never across staff.
        return 'nlq_intent:v1:' . (int) ($user['user_id'] ?? 0) . ':' . $fingerprint;
    }

    /**
     * Stable fingerprint of the authorized catalogue, so a cache entry is
     * never reused after a permission or report-definition change.
     *
     * @param array<int,array<string,mixed>> $catalogue
     */
    private function catalogueFingerprint(array $catalogue): string
    {
        $codes = [];
        foreach ($catalogue as $report) {
            $codes[] = (string) ($report['code'] ?? '');
        }
        sort($codes);
        return substr(hash('sha256', implode('|', $codes)), 0, 16);
    }

    private function intentCache(): ?SharedCache
    {
        if (!$this->cacheResolved) {
            $this->cacheResolved = true;
            if ($this->cache === null) {
                try {
                    $this->cache = new SharedCache();
                } catch (\Throwable) {
                    $this->cache = null;
                }
            }
        }
        return $this->cache;
    }

    private function parseIntent(string $question, string $audience, array $catalogue): array
    {
        $prompt = $this->templates->resolve(self::WORKFLOW);
        $payload = json_encode([
            'question' => $question,
            'audience' => $audience,
            'reports' => $catalogue,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new DomainException('The question could not be prepared.', 500);
        }
        if (strlen($payload) > 28000) {
            throw new DomainException('The report catalogue is too large for this question.', 422);
        }

        // Intent parsing is a classification task, not an essay: a bounded
        // ceiling keeps it near ~2s instead of the ~32s an unbounded
        // generation costs, and a short timeout degrades to `unavailable`
        // rather than stalling the assistant.
        $intent = $this->provider->complete([
            ['role' => 'system', 'content' => (string) $prompt['content']],
            ['role' => 'user', 'content' => $payload],
        ], [
            'task' => 'intent',
            'max_tokens' => 200,
            'timeout' => 8,
            '_provider_retries' => 1,
            '_provider_retry_delay_ms' => 120,
        ]);
        if (!is_array($intent)) {
            throw new AiProviderException('AI provider returned an unusable intent.');
        }
        $intent['cannot_answer'] = !empty($intent['cannot_answer']);
        return $intent;
    }

    /**
     * @return array{filters:array,missing:array}
     */
    private function validateFilters($rawFilters, array $report): array
    {
        if (!is_array($rawFilters)) {
            $rawFilters = [];
        }
        $allowed = $report['allowed_filters'] ?? [];
        $filters = [];
        foreach ($rawFilters as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            if (is_array($value)) {
                $items = [];
                foreach (array_slice($value, 0, self::MAX_FILTER_ITEMS) as $item) {
                    if (!is_scalar($item)) {
                        continue 2;
                    }
                    $items[] = $this->boundedText((string) $item, 120);
                }
                if ($items !== []) {
                    $filters[$key] = $items;
                }
                continue;
            }
            if (!is_scalar($value)) {
                continue;
            }
            $filters[$key] = $this->boundedText((string) $value, 120);
        }

        $missing = [];
        foreach (($report['required_filters'] ?? []) as $required) {
            $required = (string) $required;
            if (!array_key_exists($required, $filters)) {
                $missing[] = $required;
            }
        }
        return ['filters' => $filters, 'missing' => $missing];
    }

    private function execute(PDO $pdo, string $code, array $filters, array $user, string $requestId): array
    {
        $executor = $this->executor
            ?: static fn(string $c, array $params, array $u, string $rid): array => (new ReportsAPI())->executeGoverned($c, $params, $u, $rid);
        return $executor($code, $filters, $user, $requestId);
    }

    private function buildAnswer(array $result, array $filters): array
    {
        $rows = is_array($result['rows'] ?? null) ? array_values($result['rows']) : [];
        $summary = $result['summary'] ?? [];
        return [
            'report_code' => (string) ($result['report']['code'] ?? ''),
            'report_title' => $this->boundedText((string) ($result['report']['title'] ?? ''), 200),
            'decision_purpose' => $this->boundedText((string) ($result['report']['decision_purpose'] ?? ''), 300),
            'as_of' => (string) ($result['as_of'] ?? ''),
            'freshness_minutes' => (int) ($result['report']['data_currency']['freshness_minutes'] ?? 0),
            'filters' => is_array($result['filters'] ?? null) ? $result['filters'] : $filters,
            'row_count' => (int) ($result['row_count'] ?? count($rows)),
            'summary' => $this->boundSummary($summary),
            'columns' => array_slice(is_array($result['columns'] ?? null) ? $result['columns'] : [], 0, 20),
            'rows' => array_slice($rows, 0, self::MAX_ROWS),
            'preview_limited' => count($rows) > self::MAX_ROWS,
            'warnings' => array_slice($this->stringList($result['warnings'] ?? [], 10), 0, 10),
            'run_id' => isset($result['run']['id']) ? (int) $result['run']['id'] : null,
        ];
    }

    private function boundSummary($summary): array
    {
        if (!is_array($summary)) {
            return [];
        }
        $out = [];
        $count = 0;
        foreach ($summary as $key => $value) {
            if ($count >= 40) {
                break;
            }
            $key = $this->boundedText((string) $key, 80);
            if ($key === '') {
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $out[$key] = is_string($value) ? $this->boundedText($value, 300) : $value;
                $count++;
                continue;
            }
            if (is_array($value) && count($value) <= 40) {
                $inner = [];
                foreach ($value as $innerKey => $innerValue) {
                    if (!is_scalar($innerValue) && $innerValue !== null) {
                        continue;
                    }
                    $inner[$this->boundedText((string) $innerKey, 80)] = is_string($innerValue)
                        ? $this->boundedText($innerValue, 300)
                        : $innerValue;
                }
                $out[$key] = $inner;
                $count++;
            }
        }
        return $out;
    }

    private function normalizeUser(array $context): array
    {
        $permissions = array_values(array_map('strval', (array) ($context['effective_permissions'] ?? $context['permissions'] ?? [])));
        return [
            'user_id' => (int) ($context['user_id'] ?? 0),
            'roles' => (array) ($context['roles'] ?? []),
            'permissions' => $permissions,
            'effective_permissions' => $permissions,
        ];
    }

    private function stringList($value, int $max): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach (array_slice($value, 0, $max) as $item) {
            if (is_scalar($item)) {
                $out[] = $this->boundedText((string) $item, 120);
            }
        }
        return $out;
    }

    private function boundedText(string $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
        return mb_substr(trim($value), 0, $max);
    }

    private function journal(array $context, string $question, ?string $reportCode, string $outcome, int $rowCount): void
    {
        try {
            FileLogger::write('ai_generation', [
                'type' => 'nlq_query',
                'operator_id' => (int) ($context['user_id'] ?? 0),
                'question_hash' => hash('sha256', $question),
                'audience' => 'staff',
                'report_code' => $reportCode,
                'outcome' => $outcome,
                'row_count' => $rowCount,
            ]);
        } catch (\Throwable $e) {
            // Journaling must never break an answer.
        }
    }
}
