<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PDO;

/**
 * IntelligenceEngine - deterministic rule orchestrator (P3a).
 *
 * Runs the registered deterministic detectors, aggregates their findings into
 * a single report, journals it to the `intelligence` file journal, and pushes
 * any raised alerts through the realtime EventBroadcaster pipeline so they
 * surface in the notification bell.
 *
 * Deliberately provider-free: this engine only computes values, flags, and
 * alerts (SQL/rules over the governed views). It never holds a provider
 * credential and never decides WHEN to call the LLM - the AiInsightOrchestrator
 * (P3b) owns that decision and reuses this engine's findings as context.
 */
final class IntelligenceEngine
{
    /**
     * Detector catalogue: domain => detector class.
     *
     * @var array<string,class-string<IntelligenceDetector>>
     */
    public const DETECTORS = [
        'attendance' => AttendanceEarlyWarningDetector::class,
        'finance' => FeeCollectionForecastDetector::class,
        'curriculum' => CurriculumChangeDetector::class,
        'academics' => RubricConsistencyDetector::class,
        'capacity' => CapacityAlertDetector::class,
        'discipline' => DisciplinePatternDetector::class,
        'enrollment' => EnrollmentTrendDetector::class,
    ];

    /** @var PDO|null */
    private $connection;

    /** @var array<string,mixed> Per-domain options for run(). */
    private $options;

    /**
     * @param PDO|null $connection injected for tests; defaults to the app DB
     * @param array<string,mixed> $options per-domain detector options
     */
    public function __construct(?PDO $connection = null, array $options = [])
    {
        $this->connection = $connection;
        $this->options = $options;
    }

    /**
     * Run all detectors (or a subset) and return the aggregated report.
     *
     * @param string[]|null $domains subset of DETECTORS keys; null runs all
     * @return array<string,mixed> {generated_at, ran, alerts, domains}
     */
    public function run(?array $domains = null, bool $broadcast = true, bool $journal = true): array
    {
        $pdo = $this->connection ?: \App\Database\Database::getInstance()->getConnection();
        $wanted = $domains === null ? array_keys(self::DETECTORS) : $domains;

        $executed = [];
        $alerts = [];
        foreach ($wanted as $domain) {
            $class = self::DETECTORS[$domain] ?? null;
            if ($class === null || !is_subclass_of($class, IntelligenceDetector::class)) {
                continue;
            }
            $detector = new $class($pdo);
            $options = (array) ($this->options[$domain] ?? []);
            try {
                $result = $detector->detect($pdo, $options);
            } catch (\Throwable $e) {
                FileLogger::write('intelligence', [
                    'type' => 'detector_failed',
                    'domain' => $domain,
                    'error' => $e->getMessage(),
                ], 'error');
                $result = [
                    'domain' => $domain,
                    'as_of' => gmdate('Y-m-d'),
                    'metrics' => ['faulted' => true],
                    'alerts' => [],
                ];
            }
            $executed[$domain] = $result;
            foreach ((array) ($result['alerts'] ?? []) as $alert) {
                $alert['domain'] = $domain;
                $alerts[] = $alert;
                if ($broadcast) {
                    $this->broadcast($pdo, (string) $domain, $alert);
                }
            }
        }

        $report = [
            'generated_at' => gmdate('c'),
            'as_of' => gmdate('Y-m-d'),
            'ran' => array_keys($executed),
            'alert_count' => count($alerts),
            'alerts' => $alerts,
            'domains' => $executed,
        ];

        if ($journal) {
            FileLogger::write('intelligence', [
                'type' => 'engine_run',
                'domains_ran' => array_keys($executed),
                'alert_count' => count($alerts),
                'alert_codes' => array_values(array_unique(array_column($alerts, 'code'))),
            ]);
        }

        return $report;
    }

    /** Push a detector alert through EventBroadcaster. */
    private function broadcast(PDO $pdo, string $domain, array $alert): void
    {
        $scopes = (array) ($alert['target_scopes'] ?? [EventBroadcaster::DEFAULT_SCOPE]);
        $scopes = $scopes ?: [EventBroadcaster::DEFAULT_SCOPE];
        EventBroadcaster::dispatch(
            $pdo,
            'intelligence',
            $domain . '.' . (string) ($alert['code'] ?? 'alert'),
            [
                'level' => (string) ($alert['level'] ?? 'info'),
                'message' => (string) ($alert['message'] ?? ''),
                'domain' => $domain,
            ],
            $scopes
        );
    }
}