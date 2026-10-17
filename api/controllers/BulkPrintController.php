<?php

declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Controllers\BaseController;
use App\API\Services\documents\DocumentBatchRegistry;
use App\API\Services\documents\DocumentBatchService;
use RuntimeException;
use function App\API\Includes\formatResponse;

/**
 * BulkPrintController
 *
 * Asynchronous bulk-document pipeline façade (ID cards now; report cards,
 * portfolios, certificates, staff passes, payslips, fee statements and
 * transcripts follow). Enqueue returns immediately; the worker drains the
 * batch in bounded steps; the frontend polls status and then opens the
 * signed download URL (or the ZIP when the batch is too large for one PDF).
 *
 * Routes (convention-based ControllerRouter):
 *   POST /api/bulkprint/enqueue                  start a batch
 *   GET  /api/bulkprint/kinds                    catalogue of executable kinds
 *   GET  /api/bulkprint/status/{batchId}         progress (owner-scoped)
 *   GET  /api/bulkprint/download/{batchId}       fresh signed artifact URL
 *   POST /api/bulkprint/cancel/{batchId}         abandon a batch
 */
class BulkPrintController extends BaseController
{
    /**
     * Enforced permissions per executable kind. A kind absent from this map
     * requires authentication only and is gated at the calling page instead.
     */
    private const KIND_PERMISSIONS = [
        'student_id_cards' => ['students_print'],
        'report_cards' => ['academics_view'],
        'portfolios' => ['students_view'],
        'staff_security_passes' => ['staff_view'],
        'payslips' => ['finance_view', 'payroll_view'],
        'fee_statements' => ['finance_view'],
        'certificates' => ['academics_manage'],
        'transcripts' => ['academics_view'],
    ];

    private DocumentBatchService $batches;

    public function __construct()
    {
        parent::__construct();
        $this->batches = $this->contract(DocumentBatchService::class, $this->db->getConnection());
    }

    public function postEnqueue(string $id = null, array $data = []): array
    {
        if ($auth = $this->guard()) {
            return $auth;
        }

        $kind = trim((string) ($data['kind'] ?? ''));
        if ($kind === '') {
            return $this->badRequest('A document batch kind is required.');
        }
        if (!DocumentBatchRegistry::has($kind)) {
            return $this->badRequest('Unknown or unavailable document batch kind.');
        }
        if (($allowed = self::KIND_PERMISSIONS[$kind] ?? null) !== null) {
            if (!$this->userHasAny(...$allowed)) {
                return $this->forbidden('Insufficient permission to start this bulk print.');
            }
        }

        $selection = isset($data['selection']) && is_array($data['selection'])
            ? $data['selection'] : [];
        $options = isset($data['options']) && is_array($data['options'])
            ? $data['options'] : [];
        if ($kind === 'student_id_cards') {
            $ids = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if ($ids === []) {
                return $this->badRequest('Select at least one student before printing.');
            }
            $selection['student_ids'] = $ids;
            if (isset($options['printerMode']) && !in_array(strtolower((string) $options['printerMode']), ['a4_pdf', 'direct_card'], true)) {
                return $this->badRequest('Invalid printer mode.');
            }
            if (isset($options['side']) && !in_array(strtolower((string) $options['side']), ['front', 'back', 'both'], true)) {
                return $this->badRequest('Card side must be front, back or both.');
            }
        } elseif ($kind === 'report_cards') {
            $ids = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if ($ids === []) {
                return $this->badRequest('Select at least one student before printing report cards.');
            }
            $selection['student_ids'] = $ids;
            if (!isset($options['term_id']) || (int) $options['term_id'] <= 0) {
                return $this->badRequest('A term_id is required for report card generation.');
            }
            if (isset($options['result_mode']) && !in_array((string) $options['result_mode'], ['summative', 'formative', 'both'], true)) {
                return $this->badRequest('result_mode must be summative, formative, or both.');
            }
        } elseif ($kind === 'portfolios') {
            $ids = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if ($ids === []) {
                return $this->badRequest('Select at least one student before printing portfolios.');
            }
            $selection['student_ids'] = $ids;
        } elseif ($kind === 'staff_security_passes') {
            $ids = array_values(array_map('intval', (array) ($selection['staff_ids'] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if ($ids === []) {
                return $this->badRequest('Select at least one staff member before printing security passes.');
            }
            $selection['staff_ids'] = $ids;
            if (isset($options['printerMode']) && !in_array(strtolower((string) $options['printerMode']), ['a4_pdf', 'direct_card'], true)) {
                return $this->badRequest('Invalid printer mode.');
            }
            if (isset($options['side']) && !in_array(strtolower((string) $options['side']), ['front', 'back', 'both'], true)) {
                return $this->badRequest('Card side must be front, back or both.');
            }
        } elseif ($kind === 'payslips') {
            $ids = array_values(array_map('intval', (array) ($selection['staff_ids'] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if ($ids === []) {
                return $this->badRequest('Select at least one staff member before printing payslips.');
            }
            $selection['staff_ids'] = $ids;
            if (!isset($options['payroll_month']) || (int) $options['payroll_month'] < 1 || (int) $options['payroll_month'] > 12) {
                return $this->badRequest('A valid payroll_month (1-12) is required.');
            }
            if (!isset($options['payroll_year']) || (int) $options['payroll_year'] < 2000 || (int) $options['payroll_year'] > 2100) {
                return $this->badRequest('A valid payroll_year is required.');
            }
        } elseif ($kind === 'fee_statements') {
            $ids = array_values(array_map('intval', (array) ($selection['student_ids'] ?? [])));
            $ids = array_values(array_filter($ids, static fn (int $v): bool => $v > 0));
            if ($ids === []) {
                return $this->badRequest('Select at least one student before printing fee statements.');
            }
            $selection['student_ids'] = $ids;
        } elseif ($kind === 'certificates') {
            $certType = trim((string) ($selection['certificate_type'] ?? ''));
            if ($certType === '' || !preg_match('/^[a-z0-9_]{1,80}$/i', $certType)) {
                return $this->badRequest('A valid certificate_type is required.');
            }
            $recipients = (array) ($selection['recipients'] ?? []);
            $recipients = array_values(array_filter($recipients, static fn ($v): bool => is_array($v)));
            if ($recipients === []) {
                return $this->badRequest('At least one recipient is required for certificates.');
            }
            $selection['certificate_type'] = $certType;
            $selection['recipients'] = $recipients;
        } elseif ($kind === 'transcripts') {
            $transcripts = (array) ($selection['transcripts'] ?? []);
            $transcripts = array_values(array_filter($transcripts, static fn ($v): bool => is_array($v)));
            if ($transcripts === []) {
                return $this->badRequest('At least one transcript payload is required.');
            }
            $selection['transcripts'] = $transcripts;
        }

        try {
            $context = [
                'user_id' => (int) ($this->user['id'] ?? 0),
                'permissions' => $this->user['permissions'] ?? [],
                'request_id' => (string) ($data['request_id'] ?? ''),
            ];
            $batch = $this->batches->enqueue($kind, $selection, $options, $context);
            return formatResponse(true, $batch, 'Bulk print started.');
        } catch (RuntimeException $e) {
            return $this->badRequest($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[BulkPrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    public function getKinds(string $id = null, array $data = []): array
    {
        if ($auth = $this->guard()) {
            return $auth;
        }
        return formatResponse(true, [
            'kinds' => DocumentBatchRegistry::catalogue(),
        ], 'OK');
    }

    public function getStatus(string $id = null, array $data = []): array
    {
        if ($auth = $this->guard()) {
            return $auth;
        }
        try {
            $batch = $this->batches->status($this->batchId($id), (int) ($this->user['id'] ?? 0));
            return formatResponse(true, $batch, 'OK');
        } catch (RuntimeException $e) {
            return $this->forbidden($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[BulkPrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    public function getDownload(string $id = null, array $data = []): array
    {
        if ($auth = $this->guard()) {
            return $auth;
        }
        try {
            $artifact = $this->batches->artifact($this->batchId($id), (int) ($this->user['id'] ?? 0));
            if ($artifact === null) {
                return $this->badRequest('The bulk print is not ready for download yet.');
            }
            $url = $artifact['mime_type'] === 'application/zip'
                ? $this->downloads()->generatedDownloadUrlForAbsolutePath($artifact['path'])
                : $this->downloads()->printUrlForAbsolutePath($artifact['path']);
            return formatResponse(true, [
                'filename' => $artifact['filename'],
                'mime_type' => $artifact['mime_type'],
                'download_url' => $url,
            ], 'OK');
        } catch (RuntimeException $e) {
            return $this->forbidden($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[BulkPrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    public function postCancel(string $id = null, array $data = []): array
    {
        if ($auth = $this->guard()) {
            return $auth;
        }
        try {
            $batch = $this->batches->cancel($this->batchId($id), (int) ($this->user['id'] ?? 0));
            return formatResponse(true, $batch, 'Bulk print cancelled.');
        } catch (RuntimeException $e) {
            return $this->forbidden($e->getMessage());
        } catch (\Throwable $e) {
            \App\API\Services\Logger::legacyError('[BulkPrintController] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    private function batchId(?string $id): string
    {
        $id = trim((string) ($id ?? ''));
        if (preg_match('/^[a-f0-9]{16,64}$/', $id) !== 1) {
            throw new RuntimeException('Invalid document batch id.');
        }
        return $id;
    }

    private function guard(): ?array
    {
        if (!$this->user || empty($this->user['id'])) {
            return $this->unauthorized('Authentication required');
        }
        return null;
    }
}