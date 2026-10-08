<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;
use RuntimeException;

/** Sends bounded, PHP-authorized school documents to the private Python renderer. */
final class PythonDocumentBridge
{
    private InterServiceClient $client;

    public function __construct(?InterServiceClient $client = null)
    {
        $this->client = $client ?? new InterServiceClient();
    }

    public function available(): bool
    {
        return $this->client->isConfigured('python_ai')
            && trim((string) Config::get('AI_PYTHON_SECRET', '')) !== '';
    }

    /** Render one bounded HTML chunk. Raw HTML and PDF bytes are never logged. */
    public function renderStudentIdCardPdf(string $html): string
    {
        $result = $this->renderDocuments(
            [['document_id' => 'student-id-cards', 'html' => $html]],
            'combined',
            'none'
        );
        $encodedPdf = $result['pdf_base64'] ?? null;
        $pdf = is_string($encodedPdf) ? base64_decode($encodedPdf, true) : false;
        if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('The Python renderer returned an invalid PDF document.');
        }

        return $pdf;
    }

    /**
     * Render pre-authorized HTML documents and either combine them or return
     * one PDF per recipient/document. Python receives no database credentials.
     *
     * @param array<int, array{document_id: string, html: string}> $documents
     * @return array<string, mixed>
     */
    public function renderDocuments(
        array $documents,
        string $outputMode = 'combined',
        string $pageNumbering = 'local'
    ): array {
        if (!$this->available()) {
            throw new RuntimeException('The Python document renderer is not configured.');
        }
        if ($documents === [] || count($documents) > 100) {
            throw new RuntimeException('The document batch must contain between 1 and 100 documents.');
        }
        if (!in_array($outputMode, ['combined', 'individual'], true)
            || !in_array($pageNumbering, ['local', 'none'], true)) {
            throw new RuntimeException('The document batch output options are invalid.');
        }

        $totalBytes = 0;
        foreach ($documents as $document) {
            if (!is_array($document)
                || !is_string($document['html'] ?? null)
                || trim($document['html']) === '') {
                throw new RuntimeException('A document batch entry is invalid.');
            }
            $size = strlen($document['html']);
            $totalBytes += $size;
            if ($size > 8 * 1024 * 1024 || $totalBytes > 18 * 1024 * 1024) {
                throw new RuntimeException('The document batch exceeds the renderer size limit.');
            }
        }

        $body = json_encode(
            [
                'documents' => array_values($documents),
                'output_mode' => $outputMode,
                'page_numbering' => $pageNumbering,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($body === false) {
            throw new RuntimeException('The document batch could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/documents/render-batch',
            $headers,
            $body,
            90
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_document_batch_render',
            'document_count' => count($documents),
            'output_mode' => $outputMode,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'request_bytes' => strlen($body),
            'transport_error' => $transportError !== '',
            'link' => $this->client->linkInUse('python_ai'),
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python document renderer did not complete the document batch.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data) || ($data['output_mode'] ?? null) !== $outputMode) {
            throw new RuntimeException('The Python renderer returned an invalid document batch.');
        }
        if ($outputMode === 'combined') {
            $encodedPdf = $data['pdf_base64'] ?? null;
            if (!is_string($encodedPdf) || strlen($encodedPdf) > 42 * 1024 * 1024) {
                throw new RuntimeException('The Python renderer returned an invalid or oversized PDF.');
            }
            $pdf = base64_decode($encodedPdf, true);
            if (!is_string($pdf) || !str_starts_with($pdf, '%PDF-')) {
                throw new RuntimeException('The Python renderer returned an invalid PDF document.');
            }
        }

        return $data;
    }
}
