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
            || !in_array($pageNumbering, ['local', 'none', 'auto'], true)) {
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

    /**
     * Merge multiple PDFs returned from the renderer.
     *
     * @param array<int, string> $encodedPdfs Base64-encoded PDF strings.
     * @return string Merged PDF as base64.
     */
    public function mergePdfs(array $encodedPdfs): string
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python document renderer is not configured.');
        }
        if (count($encodedPdfs) < 2) {
            throw new RuntimeException('At least two PDFs are required to merge.');
        }

        $pdfs = [];
        $totalBytes = 0;
        foreach ($encodedPdfs as $index => $encoded) {
            if (!is_string($encoded) || $encoded === '') {
                throw new RuntimeException('An encoded PDF is required for merging.');
            }
            $decoded = base64_decode($encoded, true);
            if (!is_string($decoded) || !str_starts_with($decoded, '%PDF-')) {
                throw new RuntimeException('The Python renderer returned an invalid PDF document.');
            }
            $size = strlen($decoded);
            $totalBytes += $size;
            if ($size > 9 * 1024 * 1024 || $totalBytes > 30 * 1024 * 1024) {
                throw new RuntimeException('The merged PDF input exceeds the size limit.');
            }
            $pdfs[] = ['pdf_base64' => $encoded];
        }

        $body = json_encode(['pdfs' => $pdfs], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('The merge request could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/documents/merge-pdfs',
            $headers,
            $body,
            90
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_document_merge',
            'input_count' => count($pdfs),
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python document renderer did not complete the PDF merge.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data) || !is_string($data['pdf_base64'] ?? null)) {
            throw new RuntimeException('The Python renderer returned an invalid merged PDF.');
        }
        $merged = base64_decode($data['pdf_base64'], true);
        if (!is_string($merged) || !str_starts_with($merged, '%PDF-')) {
            throw new RuntimeException('The Python renderer returned an invalid merged PDF.');
        }

        return $data['pdf_base64'];
    }

    /**
     * Render academic report cards in batch via Python service.
     *
     * @param array<int, int> $studentIds
     * @param int $termId
     * @param string $resultMode 'summative'|'formative'|'both'
     * @param string $outputFormat 'pdf'|'zip'
     * @param string $jobId
     * @return array{artifact: string, download_url: string, total_students: int, processed: int}
     */
    public function renderReportCardsBatch(
        array $studentIds,
        int $termId,
        string $resultMode,
        string $outputFormat,
        string $jobId
    ): array {
        if (!$this->available()) {
            throw new RuntimeException('The Python document renderer is not configured.');
        }
        if ($studentIds === []) {
            throw new RuntimeException('No student IDs provided for report card batch.');
        }
        if ($termId <= 0) {
            throw new RuntimeException('Invalid term_id for report card batch.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'student_ids' => $studentIds,
            'term_id' => $termId,
            'result_mode' => $resultMode,
            'output_format' => $outputFormat,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('The report card batch request could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/documents/report-cards/batch',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_report_card_batch',
            'job_id' => $jobId,
            'student_count' => count($studentIds),
            'term_id' => $termId,
            'result_mode' => $resultMode,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python document renderer did not complete the report card batch.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python renderer returned an invalid report card batch response.');
        }
        return [
            'artifact' => $data['artifact'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'total_students' => (int) ($data['total_students'] ?? 0),
            'processed' => (int) ($data['processed'] ?? 0),
        ];
    }

    /**
     * Parse bank/provider file via Python service (Polars CSV/Excel parsing).
     */
    public function parseBankFile(string $jobId, string $provider, int $fileId, int $accountId): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'provider' => $provider,
            'file_id' => $fileId,
            'account_id' => $accountId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/finance/parse-bank-file',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_finance_parse_bank_file',
            'job_id' => $jobId,
            'provider' => $provider,
            'file_id' => $fileId,
            'account_id' => $accountId,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the bank file parse.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid parse response.');
        }
        return [
            'parsed_rows' => (int) ($data['parsed_rows'] ?? 0),
            'matched_rows' => (int) ($data['matched_rows'] ?? 0),
            'unmatched_rows' => (int) ($data['unmatched_rows'] ?? 0),
            'errors' => (array) ($data['errors'] ?? []),
        ];
    }

    /**
     * Prepare reconciliation candidates via Python service.
     */
    public function prepareReconciliation(string $jobId, string $provider, string $dateFrom, string $dateTo, int $accountId): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'provider' => $provider,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_id' => $accountId,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/finance/reconciliation/prepare',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_finance_reconciliation_prep',
            'job_id' => $jobId,
            'provider' => $provider,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'account_id' => $accountId,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the reconciliation prep.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid reconciliation response.');
        }
        return [
            'candidates' => (int) ($data['candidates'] ?? 0),
            'exact_matches' => (int) ($data['exact_matches'] ?? 0),
            'fuzzy_matches' => (int) ($data['fuzzy_matches'] ?? 0),
            'exceptions' => (int) ($data['exceptions'] ?? 0),
        ];
    }

    /**
     * Generate large finance report via Python service (xlsxwriter streaming).
     */
    public function generateFinanceReport(
        string $jobId,
        string $reportType,
        string $dateFrom,
        string $dateTo,
        array $filters,
        string $outputFormat
    ): array {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'report_type' => $reportType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'filters' => $filters,
            'output_format' => $outputFormat,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/finance/report',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_finance_large_report',
            'job_id' => $jobId,
            'report_type' => $reportType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'output_format' => $outputFormat,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the finance report.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid finance report response.');
        }
        return [
            'artifact' => $data['artifact'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'row_count' => (int) ($data['row_count'] ?? 0),
            'sheet_count' => (int) ($data['sheet_count'] ?? 0),
        ];
    }

    /**
     * Import preview via Python service (Polars CSV/Excel/PDF parsing).
     */
    public function importPreview(string $jobId, int $fileId, string $importType, array $options): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'file_id' => $fileId,
            'import_type' => $importType,
            'options' => $options,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/students/import-preview',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_students_import_preview',
            'job_id' => $jobId,
            'file_id' => $fileId,
            'import_type' => $importType,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the import preview.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid import preview response.');
        }
        return [
            'preview_rows' => (int) ($data['preview_rows'] ?? 0),
            'total_rows' => (int) ($data['total_rows'] ?? 0),
            'columns' => (array) ($data['columns'] ?? []),
            'sample_data' => (array) ($data['sample_data'] ?? []),
            'validation_errors' => (array) ($data['validation_errors'] ?? []),
        ];
    }

    /**
     * Bulk student artifacts via Python service (ReportLab/WeasyPrint).
     */
    public function bulkStudentArtifacts(string $jobId, string $artifactType, array $studentIds, array $options): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }

        $body = json_encode([
            'job_id' => $jobId,
            'artifact_type' => $artifactType,
            'student_ids' => $studentIds,
            'options' => $options,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post(
            'python_ai',
            '/api/students/bulk-artifacts',
            $headers,
            $body,
            300
        );
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_students_bulk_artifacts',
            'job_id' => $jobId,
            'artifact_type' => $artifactType,
            'student_count' => count($studentIds),
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);

        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the bulk student artifacts.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid bulk artifacts response.');
        }
        return [
            'artifact' => $data['artifact'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'total_students' => (int) ($data['total_students'] ?? 0),
            'processed' => (int) ($data['processed'] ?? 0),
        ];
    }

    // ─── Communications ──────────────────────────────────────────────

    /**
     * Batch render communication templates via Python service.
     */
    public function renderCommunicationBatch(string $jobId, int $templateId, array $recipientIds, string $channel, array $context): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'template_id' => $templateId,
            'recipient_ids' => $recipientIds,
            'channel' => $channel,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/communications/batch-render', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_communications_batch_render',
            'job_id' => $jobId,
            'template_id' => $templateId,
            'channel' => $channel,
            'recipient_count' => count($recipientIds),
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the communication batch render.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid batch render response.');
        }
        return [
            'rendered_count' => (int) ($data['rendered_count'] ?? 0),
            'failed_count' => (int) ($data['failed_count'] ?? 0),
            'attachments' => (array) ($data['attachments'] ?? []),
        ];
    }

    /**
     * Reconcile delivery status via Python service.
     */
    public function reconcileDelivery(string $jobId, string $provider, string $dateFrom, string $dateTo, string $channel): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'provider' => $provider,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'channel' => $channel,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/communications/reconcile-delivery', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_communications_reconcile_delivery',
            'job_id' => $jobId,
            'provider' => $provider,
            'channel' => $channel,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the delivery reconciliation.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid reconciliation response.');
        }
        return [
            'total_sent' => (int) ($data['total_sent'] ?? 0),
            'delivered' => (int) ($data['delivered'] ?? 0),
            'failed' => (int) ($data['failed'] ?? 0),
            'pending' => (int) ($data['pending'] ?? 0),
            'discrepancies' => (int) ($data['discrepancies'] ?? 0),
        ];
    }

    // ─── Inventory ───────────────────────────────────────────────────

    /**
     * Import supplier data via Python service.
     */
    public function importSupplierData(string $jobId, int $fileId, int $supplierId, string $importType): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'file_id' => $fileId,
            'supplier_id' => $supplierId,
            'import_type' => $importType,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/inventory/supplier-import', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_inventory_supplier_import',
            'job_id' => $jobId,
            'file_id' => $fileId,
            'supplier_id' => $supplierId,
            'import_type' => $importType,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the supplier import.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid supplier import response.');
        }
        return [
            'imported_items' => (int) ($data['imported_items'] ?? 0),
            'updated_items' => (int) ($data['updated_items'] ?? 0),
            'errors' => (array) ($data['errors'] ?? []),
        ];
    }

    /**
     * Bulk export inventory via Python service.
     */
    public function exportInventory(string $jobId, string $exportType, array $filters, string $outputFormat): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'export_type' => $exportType,
            'filters' => $filters,
            'output_format' => $outputFormat,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/inventory/export', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_inventory_export',
            'job_id' => $jobId,
            'export_type' => $exportType,
            'output_format' => $outputFormat,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the inventory export.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid inventory export response.');
        }
        return [
            'artifact' => $data['artifact'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'row_count' => (int) ($data['row_count'] ?? 0),
            'sheet_count' => (int) ($data['sheet_count'] ?? 0),
        ];
    }

    /**
     * Refresh inventory aggregates via Python service.
     */
    public function refreshInventoryAggregates(string $jobId, array $aggregates): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'aggregates' => $aggregates,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/inventory/refresh-aggregates', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_inventory_aggregate_refresh',
            'job_id' => $jobId,
            'aggregates' => $aggregates,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the inventory aggregate refresh.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid aggregate refresh response.');
        }
        return [
            'refreshed' => (int) ($data['refreshed'] ?? 0),
            'alerts_generated' => (int) ($data['alerts_generated'] ?? 0),
        ];
    }

    // ─── Payments ────────────────────────────────────────────────────

    /**
     * Parse payment provider file via Python service.
     */
    public function parsePaymentFile(string $jobId, string $provider, int $fileId, string $paymentType): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'provider' => $provider,
            'file_id' => $fileId,
            'payment_type' => $paymentType,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/payments/parse-provider-file', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_payments_parse_provider_file',
            'job_id' => $jobId,
            'provider' => $provider,
            'file_id' => $fileId,
            'payment_type' => $paymentType,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the payment file parse.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid payment file parse response.');
        }
        return [
            'parsed_rows' => (int) ($data['parsed_rows'] ?? 0),
            'matched_payments' => (int) ($data['matched_payments'] ?? 0),
            'unmatched_rows' => (int) ($data['unmatched_rows'] ?? 0),
            'errors' => (array) ($data['errors'] ?? []),
        ];
    }

    /**
     * Prepare payment exceptions via Python service.
     */
    public function preparePaymentExceptions(string $jobId, string $dateFrom, string $dateTo, string $paymentType): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'payment_type' => $paymentType,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/payments/exceptions/prepare', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_payments_exception_prep',
            'job_id' => $jobId,
            'payment_type' => $paymentType,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the payment exception prep.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid payment exception prep response.');
        }
        return [
            'total_unmatched' => (int) ($data['total_unmatched'] ?? 0),
            'categorized' => (int) ($data['categorized'] ?? 0),
            'auto_resolved' => (int) ($data['auto_resolved'] ?? 0),
            'for_review' => (int) ($data['for_review'] ?? 0),
        ];
    }

    // ─── Attendance ──────────────────────────────────────────────────

    /**
     * Parse attendance register file via Python service.
     */
    public function parseAttendanceFile(string $jobId, int $fileId, int $sessionId, string $source): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'file_id' => $fileId,
            'session_id' => $sessionId,
            'source' => $source,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/attendance/parse-register-file', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_attendance_parse_register_file',
            'job_id' => $jobId,
            'file_id' => $fileId,
            'session_id' => $sessionId,
            'source' => $source,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the attendance file parse.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid attendance file parse response.');
        }
        return [
            'parsed_rows' => (int) ($data['parsed_rows'] ?? 0),
            'marked_present' => (int) ($data['marked_present'] ?? 0),
            'marked_absent' => (int) ($data['marked_absent'] ?? 0),
            'errors' => (array) ($data['errors'] ?? []),
        ];
    }

    /**
     * Refresh attendance trends via Python service.
     */
    public function refreshAttendanceTrends(string $jobId, string $dateFrom, string $dateTo, string $scope): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'scope' => $scope,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/attendance/trend-refresh', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_attendance_trend_refresh',
            'job_id' => $jobId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'scope' => $scope,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the attendance trend refresh.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid attendance trend refresh response.');
        }
        return [
            'refreshed' => (int) ($data['refreshed'] ?? 0),
            'alerts_generated' => (int) ($data['alerts_generated'] ?? 0),
        ];
    }

    // ─── Transport ───────────────────────────────────────────────────

    /**
     * Import transport data via Python service.
     */
    public function importTransportData(string $jobId, int $fileId, string $importType): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'file_id' => $fileId,
            'import_type' => $importType,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/transport/import', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_transport_import',
            'job_id' => $jobId,
            'file_id' => $fileId,
            'import_type' => $importType,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the transport import.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid transport import response.');
        }
        return [
            'imported_routes' => (int) ($data['imported_routes'] ?? 0),
            'imported_vehicles' => (int) ($data['imported_vehicles'] ?? 0),
            'imported_stops' => (int) ($data['imported_stops'] ?? 0),
            'imported_assignments' => (int) ($data['imported_assignments'] ?? 0),
            'errors' => (array) ($data['errors'] ?? []),
        ];
    }

    /**
     * Generate transport aggregate report via Python service.
     */
    public function generateTransportReport(string $jobId, string $reportType, string $dateFrom, string $dateTo, string $outputFormat): array
    {
        if (!$this->available()) {
            throw new RuntimeException('The Python service is not configured.');
        }
        $body = json_encode([
            'job_id' => $jobId,
            'report_type' => $reportType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'output_format' => $outputFormat,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', ''),
        ];
        $started = microtime(true);
        [$raw, $status, $transportError] = $this->client->post('python_ai', '/api/transport/report', $headers, $body, 300);
        \App\API\Includes\FileLogger::write('document_generation', [
            'type' => 'python_transport_report',
            'job_id' => $jobId,
            'report_type' => $reportType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'output_format' => $outputFormat,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'transport_error' => $transportError !== '',
        ]);
        if (!is_string($raw) || $status < 200 || $status >= 300) {
            throw new RuntimeException('The Python service did not complete the transport report.');
        }
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? ($decoded['data'] ?? null) : null;
        if (!is_array($data)) {
            throw new RuntimeException('The Python service returned an invalid transport report response.');
        }
        return [
            'artifact' => $data['artifact'] ?? '',
            'download_url' => $data['download_url'] ?? '',
            'row_count' => (int) ($data['row_count'] ?? 0),
            'sheet_count' => (int) ($data['sheet_count'] ?? 0),
        ];
    }

    // ─── Phase 3: Specialized Workloads ──────────────────────────────

    // Import
    public function parseImportFile(string $jobId, int $fileId, string $fileType, array $options): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'file_type' => $fileType, 'options' => $options], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/import/parse', $headers, $body, 300);
        $this->log('python_import_parse', compact('jobId', 'fileId', 'fileType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python import parse failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['sheets' => $data['sheets'] ?? [], 'total_rows' => (int)($data['total_rows'] ?? 0), 'columns' => $data['columns'] ?? [], 'sample_data' => $data['sample_data'] ?? []];
    }
    public function validateImportFile(string $jobId, int $fileId, array $schema): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'schema' => $schema], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/import/validate', $headers, $body, 300);
        $this->log('python_import_validate', compact('jobId', 'fileId', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python import validate failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['valid_rows' => (int)($data['valid_rows'] ?? 0), 'invalid_rows' => (int)($data['invalid_rows'] ?? 0), 'errors' => $data['errors'] ?? []];
    }
    public function previewImportFile(string $jobId, int $fileId, int $maxRows): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'max_rows' => $maxRows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/import/preview', $headers, $body, 300);
        $this->log('python_import_preview', compact('jobId', 'fileId', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python import preview failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['preview_rows' => (int)($data['preview_rows'] ?? 0), 'columns' => $data['columns'] ?? [], 'sample_data' => $data['sample_data'] ?? []];
    }

    // Admission
    public function extractAdmissionDocument(string $jobId, int $fileId, string $docType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'doc_type' => $docType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/admission/document-extract', $headers, $body, 300);
        $this->log('python_admission_document_extract', compact('jobId', 'fileId', 'docType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python admission document extract failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['extracted_fields' => $data['extracted_fields'] ?? [], 'confidence' => (float)($data['confidence'] ?? 0), 'requires_review' => (bool)($data['requires_review'] ?? false)];
    }
    public function admissionImportPreview(string $jobId, int $fileId, string $importType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'import_type' => $importType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/admission/import-preview', $headers, $body, 300);
        $this->log('python_admission_import_preview', compact('jobId', 'fileId', 'importType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python admission import preview failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['preview_rows' => (int)($data['preview_rows'] ?? 0), 'total_rows' => (int)($data['total_rows'] ?? 0), 'columns' => $data['columns'] ?? [], 'sample_data' => $data['sample_data'] ?? [], 'validation_errors' => $data['validation_errors'] ?? []];
    }

    // Activities
    public function importActivityParticipants(string $jobId, int $fileId, int $activityId, string $importType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'activity_id' => $activityId, 'import_type' => $importType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/activities/import-participants', $headers, $body, 300);
        $this->log('python_activities_import_participants', compact('jobId', 'fileId', 'activityId', 'importType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python activity participants import failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['imported_count' => (int)($data['imported_count'] ?? 0), 'updated_count' => (int)($data['updated_count'] ?? 0), 'errors' => $data['errors'] ?? []];
    }
    public function exportActivity(string $jobId, string $exportType, int $activityId, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'export_type' => $exportType, 'activity_id' => $activityId, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/activities/export', $headers, $body, 300);
        $this->log('python_activities_export', compact('jobId', 'exportType', 'activityId', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python activity export failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }
    public function aggregateActivities(string $jobId, string $dateFrom, string $dateTo, array $aggregates): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'aggregates' => $aggregates], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/activities/aggregate', $headers, $body, 300);
        $this->log('python_activities_aggregate', compact('jobId', 'dateFrom', 'dateTo', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python activity aggregate failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['refreshed' => (int)($data['refreshed'] ?? 0), 'alerts_generated' => (int)($data['alerts_generated'] ?? 0)];
    }

    // Boarding
    public function boardingOccupancySummary(string $jobId, string $dateFrom, string $dateTo, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/boarding/occupancy-summary', $headers, $body, 300);
        $this->log('python_boarding_occupancy_summary', compact('jobId', 'dateFrom', 'dateTo', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python boarding occupancy summary failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }
    public function exportBoardingRollcall(string $jobId, string $dateFrom, string $dateTo, int $dormitoryId, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'dormitory_id' => $dormitoryId, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/boarding/rollcall-export', $headers, $body, 300);
        $this->log('python_boarding_rollcall_export', compact('jobId', 'dateFrom', 'dateTo', 'dormitoryId', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python boarding rollcall export failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }

    // Health
    public function extractHealthDocument(string $jobId, int $fileId, string $docType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'doc_type' => $docType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/health/document-extract', $headers, $body, 300);
        $this->log('python_health_document_extract', compact('jobId', 'fileId', 'docType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python health document extract failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['extracted_fields' => $data['extracted_fields'] ?? [], 'confidence' => (float)($data['confidence'] ?? 0), 'requires_review' => (bool)($data['requires_review'] ?? false)];
    }
    public function summarizeHealth(string $jobId, string $dateFrom, string $dateTo, string $scope, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'scope' => $scope, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/health/summarize', $headers, $body, 300);
        $this->log('python_health_summarize', compact('jobId', 'dateFrom', 'dateTo', 'scope', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python health summarize failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }

    // Library
    public function importLibraryCatalogue(string $jobId, int $fileId, string $importType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'import_type' => $importType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/library/catalogue-import', $headers, $body, 300);
        $this->log('python_library_catalogue_import', compact('jobId', 'fileId', 'importType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python library catalogue import failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['imported_count' => (int)($data['imported_count'] ?? 0), 'updated_count' => (int)($data['updated_count'] ?? 0), 'errors' => $data['errors'] ?? []];
    }
    public function exportLibrary(string $jobId, string $exportType, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'export_type' => $exportType, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/library/export', $headers, $body, 300);
        $this->log('python_library_export', compact('jobId', 'exportType', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python library export failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }

    // Maintenance
    public function extractMaintenanceAttachments(string $jobId, int $fileId, int $workOrderId, string $extractType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'work_order_id' => $workOrderId, 'extract_type' => $extractType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/maintenance/attachment-extract', $headers, $body, 300);
        $this->log('python_maintenance_attachment_extract', compact('jobId', 'fileId', 'workOrderId', 'extractType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python maintenance attachment extract failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['extracted_count' => (int)($data['extracted_count'] ?? 0), 'metadata' => $data['metadata'] ?? []];
    }
    public function exportMaintenanceSummary(string $jobId, string $dateFrom, string $dateTo, string $exportType, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'export_type' => $exportType, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/maintenance/summary-export', $headers, $body, 300);
        $this->log('python_maintenance_summary_export', compact('jobId', 'dateFrom', 'dateTo', 'exportType', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python maintenance summary export failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }

    // Schedules
    public function extractSchedule(string $jobId, int $fileId, string $source): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'source' => $source], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/schedules/extract', $headers, $body, 300);
        $this->log('python_schedules_extract', compact('jobId', 'fileId', 'source', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python schedule extract failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['extracted_rows' => (int)($data['extracted_rows'] ?? 0), 'conflicts' => $data['conflicts'] ?? [], 'proposed_rows' => (int)($data['proposed_rows'] ?? 0)];
    }
    public function proposeScheduleRows(string $jobId, int $academicYearId, int $termId, array $constraints): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'academic_year_id' => $academicYearId, 'term_id' => $termId, 'constraints' => $constraints], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/schedules/propose-rows', $headers, $body, 300);
        $this->log('python_schedules_propose_rows', compact('jobId', 'academicYearId', 'termId', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python schedule propose rows failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['proposed_rows' => (int)($data['proposed_rows'] ?? 0), 'conflicts' => $data['conflicts'] ?? [], 'coverage' => (float)($data['coverage'] ?? 0)];
    }

    // Staff
    public function importStaffData(string $jobId, int $fileId, string $importType): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'file_id' => $fileId, 'import_type' => $importType], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/staff/import', $headers, $body, 300);
        $this->log('python_staff_import', compact('jobId', 'fileId', 'importType', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python staff import failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['imported_count' => (int)($data['imported_count'] ?? 0), 'updated_count' => (int)($data['updated_count'] ?? 0), 'errors' => $data['errors'] ?? []];
    }
    public function exportStaff(string $jobId, string $exportType, string $outputFormat): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'export_type' => $exportType, 'output_format' => $outputFormat], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/staff/export', $headers, $body, 300);
        $this->log('python_staff_export', compact('jobId', 'exportType', 'outputFormat', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python staff export failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }
    public function analyzeStaff(string $jobId, string $dateFrom, string $dateTo, array $metrics): array
    {
        if (!$this->available()) throw new RuntimeException('Python service not configured.');
        $body = json_encode(['job_id' => $jobId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'metrics' => $metrics], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . (string) Config::get('AI_PYTHON_SECRET', '')];
        $started = microtime(true);
        [$raw, $status, $te] = $this->client->post('python_ai', '/api/staff/analytics', $headers, $body, 300);
        $this->log('python_staff_analytics', compact('jobId', 'dateFrom', 'dateTo', 'metrics', 'status', 'te', 'started'));
        if (!is_string($raw) || $status < 200 || $status >= 300) throw new RuntimeException('Python staff analytics failed.');
        $data = (json_decode($raw, true)['data'] ?? []);
        return ['artifact' => $data['artifact'] ?? '', 'download_url' => $data['download_url'] ?? '', 'row_count' => (int)($data['row_count'] ?? 0), 'sheet_count' => (int)($data['sheet_count'] ?? 0)];
    }

    private function log(string $type, array $ctx): void
    {
        \App\API\Includes\FileLogger::write('document_generation', array_merge(['type' => $type], $ctx));
    }

}
