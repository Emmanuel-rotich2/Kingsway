<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PDO;

/**
 * Deterministic system-administration and observability signals.
 *
 * This service computes the governed aggregates for workflow
 * `system.operations_brief`: background-queue health and recurring
 * error/critical journal signatures. It is the deterministic-first tier —
 * no provider call is ever made here. The controller forwards only the
 * bounded aggregates returned by `aiPayload()` to the AI workflow for a
 * reviewable narrative; raw journal rows, request data, and message bodies
 * are never sent to the provider.
 */
class SystemOperationsReviewService
{
    public const WORKFLOW = 'system.operations_brief';

    /** Operational journals eligible for aggregation (live categories). */
    public const JOURNAL_CATEGORIES = [
        'error', 'errors', 'auth', 'api', 'rpc', 'schedule', 'jobs',
        'worker', 'mailer', 'payments', 'finance', 'mcp', 'ai_generation', 'audit',
    ];

    private const ERROR_WINDOW_HOURS = 24;
    private const STALE_QUEUE_MINUTES = 15;
    private const MAX_ENTRIES_PER_CATEGORY = 400;
    private const MAX_SIGNATURES = 8;
    private const MAX_CATEGORIES = 8;
    private const MAX_SIGNATURE_LENGTH = 120;
    private const WORKER_FRESHNESS_LIMIT_MINUTES = 360; // 6h of inactivity = not seen

    /** @var callable fn(): array */
    private $queueSummaryProvider;

    /** @var callable fn(string $category, int $limit): array */
    private $journalReader;

    public function __construct(
        ?callable $queueSummaryProvider = null,
        ?callable $journalReader = null
    ) {
        $this->queueSummaryProvider = $queueSummaryProvider ?: static fn(): array => JobQueue::statusSummary();
        $this->journalReader = $journalReader ?: static fn(string $category, int $limit): array => FileLogger::recent($category, $limit);
    }

    /**
     * Deterministic operations snapshot (queue health + recurring errors).
     */
    public function summary(PDO $pdo): array
    {
        $queue = $this->queueSummary();
        return [
            'generated_at' => date('Y-m-d H:i:s'),
            'queue' => $queue,
            'errors' => $this->errorSummary(),
        ];
    }

    /**
     * Bounded, allow-listed payload for the `system.operations_brief` AI
     * workflow. Every key here must exist in the workflow's prompt policy.
     */
    public function aiPayload(array $summary): array
    {
        $queue = $summary['queue'] ?? [];
        $statuses = $queue['statuses'] ?? [];
        $errors = $summary['errors'] ?? [];

        return [
            'report_date' => (string) date('Y-m-d'),
            'queue_total' => (string) (int) ($queue['jobs_total'] ?? 0),
            'queue_pending' => (string) (int) ($statuses['pending'] ?? 0),
            'queue_processing' => (string) (int) ($statuses['processing'] ?? 0),
            'queue_stale' => (string) ((int) ($queue['stale_processing'] ?? 0) + (int) ($queue['stale_pending'] ?? 0)),
            'queue_failed' => (string) (int) ($statuses['failed'] ?? 0),
            'queue_done' => (string) (int) ($statuses['done'] ?? 0),
            'queue_cancelled' => (string) (int) ($statuses['cancelled'] ?? 0),
            'queue_dead_letter' => (string) (int) ($queue['dead_letter_total'] ?? 0),
            'oldest_processing_minutes' => (string) (int) ($queue['oldest_processing_minutes'] ?? 0),
            'oldest_pending_minutes' => (string) (int) ($queue['oldest_pending_minutes'] ?? 0),
            'worker_freshness_minutes' => (string) (int) ($queue['worker_seen_minutes_ago'] ?? 0),
            'error_total_24h' => (string) (int) ($errors['entries_24h'] ?? 0),
            'error_critical_24h' => (string) (int) ($errors['critical_24h'] ?? 0),
            'error_category_count' => (string) count($errors['categories'] ?? []),
            'error_signatures' => array_values(array_slice(array_map(
                static fn(string $item): string => $item,
                $errors['signatures'] ?? []
            ), 0, self::MAX_SIGNATURES)),
            'follow_up_intent' => 'Prepare an advisory system operations review of the background queue and recurring journal errors; operators verify before acting; AI must never change or re-queue jobs.',
        ];
    }

    private function queueSummary(): array
    {
        $raw = (array) ($this->queueSummaryProvider)();
        $statuses = array_map('intval', (array) ($raw['statuses'] ?? []));
        return [
            'statuses' => $statuses,
            'jobs_total' => (int) ($raw['jobs_total'] ?? 0),
            'dead_letter_total' => (int) ($raw['dead_letter_total'] ?? 0),
            'dead_letter_24h' => (int) ($raw['dead_letter_24h'] ?? 0),
            'stale_processing' => (int) ($raw['stale_processing'] ?? 0),
            'stale_pending' => (int) ($raw['stale_pending'] ?? 0),
            'oldest_processing_minutes' => max(0, (int) ($raw['oldest_processing_minutes'] ?? 0)),
            'oldest_pending_minutes' => max(0, (int) ($raw['oldest_pending_minutes'] ?? 0)),
            'worker_seen_minutes_ago' => $this->workerFreshness(),
        ];
    }

    private function workerFreshness(): int
    {
        $newest = null;
        foreach (['schedule', 'jobs'] as $category) {
            foreach (($this->journalReader)($category, 100) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $timestamp = (string) ($entry['timestamp'] ?? '');
                $parsed = strtotime($timestamp);
                if ($parsed === false || $parsed < 1) {
                    continue;
                }
                $newest = max($newest, $parsed);
            }
        }
        if ($newest === null) {
            return self::WORKER_FRESHNESS_LIMIT_MINUTES;
        }
        $minutes = (int) floor(max(0, time() - $newest) / 60);
        return min($minutes, self::WORKER_FRESHNESS_LIMIT_MINUTES);
    }

    private function errorSummary(): array
    {
        $entries24h = 0;
        $critical24h = 0;
        $categoryCounts = [];
        $signatureCounts = [];

        foreach (self::JOURNAL_CATEGORIES as $category) {
            foreach (($this->journalReader)($category, self::MAX_ENTRIES_PER_CATEGORY) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $level = strtolower((string) ($entry['level'] ?? ''));
                if ($level !== 'error' && $level !== 'critical') {
                    continue;
                }
                $timestamp = (string) ($entry['timestamp'] ?? '');
                $parsed = strtotime($timestamp);
                if ($parsed === false || (time() - $parsed) > self::ERROR_WINDOW_HOURS * 3600) {
                    continue;
                }
                $entries24h++;
                if ($level === 'critical') {
                    $critical24h++;
                }
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

                $message = $this->signature((string) ($entry['message'] ?? $entry['type'] ?? 'unknown'));
                if ($message !== '') {
                    $signatureCounts[$message] = ($signatureCounts[$message] ?? 0) + 1;
                }
            }
        }

        arsort($signatureCounts);
        arsort($categoryCounts);

        return [
            'window_hours' => self::ERROR_WINDOW_HOURS,
            'entries_24h' => $entries24h,
            'critical_24h' => $critical24h,
            'categories' => array_slice($categoryCounts, 0, self::MAX_CATEGORIES, true),
            'signatures' => array_values(array_slice(array_map(
                static fn(string $signature, int $count): string => $signature . ' (x' . $count . ')',
                array_keys($signatureCounts),
                array_values($signatureCounts)
            ), 0, self::MAX_SIGNATURES)),
            'top_signatures' => array_slice($signatureCounts, 0, self::MAX_SIGNATURES, true),
        ];
    }

    /** Normalized, identity-free signature for recurring error aggregation. */
    public function signature(string $message): string
    {
        $normalized = strtolower(trim($message));
        $normalized = preg_replace('#https?://#', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/', 'x', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/', 'x', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b[a-z0-9]{2,}(?:-[a-z0-9]{2,})+\b/', 'x', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/', 'x', $normalized) ?? $normalized;
        $normalized = preg_replace('/\b[a-z0-9]{9,}\b/', 'x', $normalized) ?? $normalized;
        $normalized = preg_replace('/[0-9]+/', 'n', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9 .\/_-]+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        return mb_substr(trim($normalized), 0, self::MAX_SIGNATURE_LENGTH);
    }
}