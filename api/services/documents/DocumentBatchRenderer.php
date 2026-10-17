<?php

declare(strict_types=1);

namespace App\API\Services\documents;

/**
 * Renderer boundary for the bulk-document pipeline.
 *
 * The service depends only on this interface so orchestration (batching,
 * resume, merge, progress) is unit-testable with an in-memory fake and the
 * concrete engine (Python/WeasyPrint) can be swapped without touching the
 * pipeline. Implementations must never see the database.
 */
interface DocumentBatchRenderer
{
    /** Whether the engine is configured and reachable. */
    public function available(): bool;

    /**
     * Render a group of HTML documents into one combined PDF.
     *
     * @param array<int, array{document_id: string, html: string}> $htmlDocuments
     * @return array{pdf: string, pages: int} raw PDF bytes + page count
     */
    public function renderCombined(array $htmlDocuments): array;

    /**
     * Merge ordered raw PDF byte strings into one PDF.
     *
     * @param array<int, string> $pdfList raw PDF bytes, in order
     * @return string raw merged PDF bytes
     */
    public function merge(array $pdfList): string;
}
