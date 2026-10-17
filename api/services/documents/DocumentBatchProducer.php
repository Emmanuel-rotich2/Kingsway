<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use PDO;

/**
 * Contract for a bulk-document "kind" (student ID cards, report cards,
 * portfolios, certificates, payslips, ...).
 *
 * A producer does exactly two things and nothing else:
 *   1. resolveEntities() turns a validated selection (ids, class/stream, term,
 *      filters) into the ordered list of entities to render, and
 *   2. renderHtml() turns one entity into a self-contained printable HTML
 *      document (with an optional filename hint).
 *
 * The batch service owns everything else: chunking, sending HTML to the Python
 * renderer, bounded batching, incremental merge, progress, retention and
 * artifact delivery. A producer must never touch the queue, the filesystem or
 * the renderer directly.
 *
 * Authorization is enforced before enqueue (controller guard) AND at entity
 * resolution time (the selection is intersected with the caller's authorized
 * scope). Producers receive an explicit $context carrying the recording user.
 */
interface DocumentBatchProducer
{
    /** Stable, machine-readable kind key (e.g. "student_id_cards"). */
    public static function kind(): string;

    /** Human-readable label for the UI catalogue. */
    public static function label(): string;

    /** Default render options merged under caller-supplied options. */
    public static function defaultOptions(): array;

    /**
     * Resolve and authorize the selection into an ordered entity list.
     *
     * @param array<string, mixed> $selection Caller selection (ids / filters).
     * @param array<string, mixed> $options   Render options.
     * @param array<string, mixed> $context   user_id, permissions, request_id.
     * @return array{entities: array<int, array<string, mixed>>, total: int, unit: string}
     */
    public function resolveEntities(array $selection, array $options, array $context): array;

    /**
     * Render one entity to printable HTML.
     *
     * Returning null skips the entity (e.g. missing required data); skipped
     * entities are counted but never fail the whole batch.
     *
     * @param array<string, mixed> $entity  One entry from resolveEntities().
     * @param array<string, mixed> $options Render options.
     * @param array<string, mixed> $context user_id, permissions, request_id.
     * @return array{html: string, filename: string}|null
     */
    public function renderHtml(array $entity, array $options, array $context): ?array;
}
