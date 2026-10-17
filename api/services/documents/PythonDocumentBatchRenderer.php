<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Services\PythonDocumentBridge;
use RuntimeException;

/**
 * DocumentBatchRenderer backed by the private Python (WeasyPrint) renderer.
 *
 * Per-request caps are enforced by PythonDocumentBridge (<=18 MB / <=8 MB per
 * document / <=100 documents); the batch service is responsible for keeping
 * each group inside those limits.
 */
final class PythonDocumentBatchRenderer implements DocumentBatchRenderer
{
    private PythonDocumentBridge $bridge;

    public function __construct(?PythonDocumentBridge $bridge = null)
    {
        $this->bridge = $bridge ?? new PythonDocumentBridge();
    }

    public function available(): bool
    {
        return $this->bridge->available();
    }

    public function renderCombined(array $htmlDocuments): array
    {
        if ($htmlDocuments === []) {
            throw new RuntimeException('A render group cannot be empty.');
        }
        $documents = [];
        foreach (array_values($htmlDocuments) as $index => $document) {
            $documents[] = [
                'document_id' => (string) ($document['document_id'] ?? ('doc-' . ($index + 1))),
                'html' => (string) ($document['html'] ?? ''),
            ];
        }

        $result = $this->bridge->renderDocuments($documents, 'combined', 'none');
        $encoded = $result['pdf_base64'] ?? null;
        $pdf = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('The renderer returned an invalid combined PDF.');
        }

        return [
            'pdf' => $pdf,
            'pages' => (int) ($result['page_count'] ?? 0),
        ];
    }

    public function merge(array $pdfList): string
    {
        if (count($pdfList) < 2) {
            throw new RuntimeException('At least two PDFs are required to merge.');
        }
        $encoded = [];
        foreach ($pdfList as $pdf) {
            if (!is_string($pdf) || $pdf === '') {
                throw new RuntimeException('A PDF is required for merging.');
            }
            $encoded[] = base64_encode($pdf);
        }

        $merged = $this->bridge->mergePdfs($encoded);
        $binary = base64_decode($merged, true);
        if (!is_string($binary) || !str_starts_with($binary, '%PDF-')) {
            throw new RuntimeException('The renderer returned an invalid merged PDF.');
        }

        return $binary;
    }
}
