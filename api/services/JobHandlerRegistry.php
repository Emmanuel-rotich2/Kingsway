<?php

namespace App\API\Services;

use App\API\Services\RpcRegistry;
use PDO;
use RuntimeException;

/**
 * JobHandlerRegistry - maps job_type strings to worker handlers.
 *
 * Shared by the CLI cron worker and the Apache-delegated fallback endpoint so
 * both execution paths run identical business logic. Each handler receives the
 * decoded payload and the active PDO connection.
 *
 * A handler for a key MUST be registered here for the corresponding job to be
 * processed. Register real, durable handlers only — never no-op placeholders
 * that claim success without performing work.
 */
class JobHandlerRegistry
{
    /** @var array<string, callable>|null */
    private static $registry;

    /**
     * @return callable|null Handler for the given job type, or null when unknown.
     */
    public static function resolve(string $jobType)
    {
        self::build();
        return self::$registry[$jobType] ?? null;
    }

    /**
     * Build the static handler map once.
     */
    private static function build(): void
    {
        if (self::$registry !== null) {
            return;
        }

        self::$registry = [
            'rebake_realtime_buffer' => static function (array $payload, PDO $pdo): void {
                $scope = isset($payload['scope'])
                    ? EventBroadcaster::normalizeScope((string) $payload['scope'])
                    : EventBroadcaster::DEFAULT_SCOPE;
                EventBroadcaster::rebakeBuffer($pdo, $scope);
            },
            // Housekeeping: drop outbox events older than keep_hours and reset
            // the auto-increment, keeping the sync table small and fast.
            'purge_old_realtime_events' => static function (array $payload, PDO $pdo): void {
                $keepHours = isset($payload['keep_hours']) ? max(1, (int) $payload['keep_hours']) : 24;
                $cutoff = date('Y-m-d H:i:s', time() - ($keepHours * 3600));
                $stmt = $pdo->prepare("DELETE FROM system_realtime_events WHERE created_at < ?");
                $stmt->execute([$cutoff]);
                // ALTER TABLE takes a metadata lock. Only reset the identity
                // when the outbox is actually empty; doing it after every purge
                // would briefly block concurrent event writers at peak time.
                $remaining = (int) $pdo->query(
                    "SELECT COUNT(*) FROM system_realtime_events"
                )->fetchColumn();
                if ($remaining === 0) {
                    $pdo->exec("ALTER TABLE system_realtime_events AUTO_INCREMENT = 1");
                }
            },
            // Read-model health canary (roadmap §4.5). Pass-through projections
            // are recorded as not_offloaded and are not treated as failures;
            // only an enabled materialized projection that is stale, missing,
            // or mismatched should fail the canary.
            'rebuild.read.replica' => static function (array $payload, PDO $pdo): void {
                $results = \App\API\Services\ReadReplicaService::freshness();
                $bad = array_values(array_filter($results, static fn(array $r) => in_array(
                    (string) ($r['status'] ?? ''),
                    ['materialized_unhealthy', 'unavailable_master_fallback'],
                    true
                )));
                \App\API\Includes\FileLogger::write('reads', [
                    'event' => 'replica.verify',
                    'checked' => (int) count($results),
                    'mismatches' => (int) count($bad),
                    'request_id' => (string) ($payload['request_id'] ?? ''),
                ]);
                if ($bad !== []) {
                    throw new \RuntimeException('Read-replica parity mismatch on: ' . implode(', ', array_column($bad, 'projection')));
                }
            },
            // Safe fallback for Python-owned projections when the Flask worker
            // is not configured. When Python is active, RealtimeController
            // routes this family away from the serial PHP worker.
            'reads.projection.refresh' => static function (array $payload, PDO $pdo): void {
                $projection = (string) ($payload['projection'] ?? '');
                if (!ReadProjectionSynchronizer::supports($projection)) {
                    throw new RuntimeException('Unsupported read projection refresh job.');
                }
                ReadProjectionSynchronizer::synchronize($projection);
            },
            // Async RPC execution (roadmap §4.2): a queued rpc.async.dispatch
            // job re-resolves the registered method and runs it in the worker.
            // Async methods declare a `worker` (a synchronously executable sync
            // method) which performs the actual work after the facade returned
            // {job_id}. The original requester (user_id) is carried for the
            // audit trail; scope-sensitive methods must remain idempotent and
            // safe to rerun.
            'rpc.async.dispatch' => static function (array $payload, PDO $pdo): void {
                $method = isset($payload['method']) ? (string) $payload['method'] : '';
                if ($method === '') {
                    throw new RuntimeException('rpc.async.dispatch: missing method in payload');
                }
                $def = RpcRegistry::resolve($method);
                if ($def === null) {
                    throw new RuntimeException("rpc.async.dispatch: method '{$method}' not found");
                }
                // Async façade methods delegate execution to their sync `worker`
                // target; plain sync methods execute themselves.
                $target = (string) ($def['_worker'] ?? $method);
                $tdef = RpcRegistry::resolve($target);
                if ($tdef === null) {
                    throw new RuntimeException("rpc.async.dispatch: worker '{$target}' not found for '{$method}'");
                }
                if (($tdef['_mode'] ?? 'sync') !== 'sync' || !is_callable($tdef['handler'])) {
                    throw new RuntimeException("rpc.async.dispatch: method '{$method}' is not synchronously executable");
                }
                $params = isset($payload['params']) && is_array($payload['params']) ? $payload['params'] : [];
                $ctx = [
                    'user_id' => (int) ($payload['user_id'] ?? 0),
                    'roles' => [],
                    'request_id' => isset($payload['request_id']) ? (string) $payload['request_id'] : '',
                    'db' => $pdo,
                    'async' => true,
                ];
                $result = call_user_func($tdef['handler'], $params, $ctx);
                \App\API\Includes\FileLogger::write('rpc', [
                    'type' => 'async_call',
                    'method' => $method,
                    'worker' => $target,
                    'user_id' => (int) ($ctx['user_id'] ?? 0),
                    'status' => 'success',
                    'result_keys' => array_keys(is_array($result) ? $result : []),
                ]);
            },
            'ai.workflow.draft' => static function (array $payload, PDO $pdo): void {
                $workflowId = (string) ($payload['workflow_id'] ?? '');
                $input = isset($payload['input']) && is_array($payload['input']) ? $payload['input'] : [];
                if ($workflowId === '') {
                    throw new RuntimeException('ai.workflow.draft: missing workflow_id');
                }
                (new AiDraftService())->create($pdo, $workflowId, [
                    'user_id' => (int) ($payload['user_id'] ?? 0),
                    'permissions' => isset($payload['permissions']) && is_array($payload['permissions'])
                        ? $payload['permissions'] : [],
                    'request_id' => (string) ($payload['request_id'] ?? ''),
                ], $input, isset($payload['metadata']) && is_array($payload['metadata'])
                    ? $payload['metadata'] : []);
            },
            'ai.analytics.insight' => static function (array $payload, PDO $pdo): void {
                (new AiAnalyticsInsightService())->execute($pdo, $payload);
            },
            // Proactive AI briefing (roadmap P3b): the worker re-authorizes with
            // the recorded operator context (no broadened scope), re-runs the
            // deterministic engine, and creates a reviewable draft or caches the
            // deterministic summary for page-load delivery. Never a second queue.
            'ai.insight.generate' => static function (array $payload, PDO $pdo): void {
                (new AiInsightOrchestrator())->execute($pdo, $payload);
            },
            // KICD policy-watch interpretation (roadmap P3b): the worker
            // re-authorizes the governed curriculum workflow with the recorded
            // operator and stores a reviewable draft. The provider call happens
            // here, never in a controller or on page load.
            'curriculum.policy_interpret' => static function (array $payload, PDO $pdo): void {
                (new CurriculumPolicyWatchAgent())->interpret($pdo, $payload);
            },
            // Governed multi-agent runs (staff co-worker layer): queued agent
            // assists and deterministic personal digests. The Python AI
            // platform is the PRIMARY engine when configured (all provider
            // calls and agent loops run there); the PHP-native AiAgentService
            // is the resilience path and re-authorizes with the recorded
            // operator context in either case.
            'ai.agent.run' => static function (array $payload, PDO $pdo): void {
                $bridge = new AiPythonBridge();
                if ($bridge->available()) {
                    $bridge->runJob($payload);
                    return;
                }
                (new AiAgentService())->runBackground($pdo, $payload);
            },
            // Governed, non-AI automations computed by the Python engine
            // (registry-authorised; payload already bounded at enqueue time).
            'automation.run' => static function (array $payload, PDO $pdo): void {
                (new \App\API\Services\automations\AutomationArtifacts())->execute($payload, $pdo);
            },
            // Bulk messaging (TALKSASA) resilience path. Python is the primary
            // executor; when it is disabled or its lease is recovered, this
            // handler sends the same staged batch through the gateways (which
            // still prefer the Python relay per call, then the stream
            // transport) and applies the per-recipient outcomes.
            'communications.sms_batch' => static function (array $payload, PDO $pdo): void {
                (new \App\API\Services\sms\SmsBatchService($pdo))->runPhpFallback($payload, $pdo);
            },
            // Resumable bulk-document pipeline (BATCH lane via the 'print.'
            // prefix). Each invocation performs one bounded step and re-enqueues
            // itself until the batch is finalised, so 1000+ printable items
            // never hold an HTTP request open.
            'print.document_batch' => static function (array $payload, PDO $pdo): void {
                $batchId = (string) ($payload['batch_id'] ?? '');
                if ($batchId === '') {
                    throw new RuntimeException('print.document_batch: missing batch_id');
                }
                (new \App\API\Services\documents\DocumentBatchService($pdo))->step($batchId);
            },
            // Academic report card batch rendering (BATCH lane). Delegates to
            // Python service (ReportLab vector PDF + WeasyPrint fallback) for
            // high-performance multi-student report card generation.
            'academic.report_card_batch' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $studentIds = isset($payload['student_ids']) && is_array($payload['student_ids'])
                    ? array_values(array_map('intval', $payload['student_ids']))
                    : [];
                $termId = (int) ($payload['term_id'] ?? 0);
                $resultMode = (string) ($payload['result_mode'] ?? 'both');
                $outputFormat = (string) ($payload['output_format'] ?? 'pdf');

                if ($studentIds === [] || $termId <= 0) {
                    throw new RuntimeException('academic.report_card_batch: student_ids and term_id are required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python document renderer not configured for academic.report_card_batch');
                }

                // Call Python academic batch render endpoint
                $result = $bridge->renderReportCardsBatch($studentIds, $termId, $resultMode, $outputFormat, $jobId);

                // Persist artifact metadata (path, download URL) for the job
                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['total_students'] = $result['total_students'] ?? 0;
                    $meta['processed'] = $result['processed'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Finance: bank/provider file parsing, reconciliation prep, large reports
            'finance.parse_bank_file' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $provider = (string) ($payload['provider'] ?? ''); // 'kcb', 'mpesa', 'coop'
                $fileId = (int) ($payload['file_id'] ?? 0);
                $accountId = (int) ($payload['account_id'] ?? 0);

                if ($jobId === '' || $provider === '' || $fileId <= 0 || $accountId <= 0) {
                    throw new RuntimeException('finance.parse_bank_file: job_id, provider, file_id, account_id required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for finance.parse_bank_file');
                }

                $result = $bridge->parseBankFile($jobId, $provider, $fileId, $accountId);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['parsed_rows'] = $result['parsed_rows'] ?? 0;
                    $meta['matched_rows'] = $result['matched_rows'] ?? 0;
                    $meta['unmatched_rows'] = $result['unmatched_rows'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'finance.reconciliation_prep' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $provider = (string) ($payload['provider'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $accountId = (int) ($payload['account_id'] ?? 0);

                if ($jobId === '' || $provider === '' || $dateFrom === '' || $dateTo === '' || $accountId <= 0) {
                    throw new RuntimeException('finance.reconciliation_prep: job_id, provider, date_from, date_to, account_id required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for finance.reconciliation_prep');
                }

                $result = $bridge->prepareReconciliation($jobId, $provider, $dateFrom, $dateTo, $accountId);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['candidates'] = $result['candidates'] ?? 0;
                    $meta['exact_matches'] = $result['exact_matches'] ?? 0;
                    $meta['fuzzy_matches'] = $result['fuzzy_matches'] ?? 0;
                    $meta['exceptions'] = $result['exceptions'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'finance.large_report' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $reportType = (string) ($payload['report_type'] ?? ''); // 'trial_balance', 'pnl', 'fee_ledger', 'budget_util'
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $filters = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $reportType === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('finance.large_report: job_id, report_type, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for finance.large_report');
                }

                $result = $bridge->generateFinanceReport($jobId, $reportType, $dateFrom, $dateTo, $filters, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Students: import preview, bulk artifacts
            'students.import_preview' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'admission_csv', 'student_csv', 'document_pdf', 'excel'
                $options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];

                if ($jobId === '' || $fileId <= 0 || $importType === '') {
                    throw new RuntimeException('students.import_preview: job_id, file_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for students.import_preview');
                }

                $result = $bridge->importPreview($jobId, $fileId, $importType, $options);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['preview_rows'] = $result['preview_rows'] ?? 0;
                    $meta['total_rows'] = $result['total_rows'] ?? 0;
                    $meta['columns'] = $result['columns'] ?? [];
                    $meta['sample_data'] = $result['sample_data'] ?? [];
                    $meta['validation_errors'] = $result['validation_errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'students.bulk_artifact' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $artifactType = (string) ($payload['artifact_type'] ?? ''); // 'id_cards', 'report_cards', 'certificates', 'transcripts'
                $studentIds = isset($payload['student_ids']) && is_array($payload['student_ids'])
                    ? array_values(array_map('intval', $payload['student_ids']))
                    : [];
                $options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];

                if ($jobId === '' || $artifactType === '' || $studentIds === []) {
                    throw new RuntimeException('students.bulk_artifact: job_id, artifact_type, student_ids required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for students.bulk_artifact');
                }

                $result = $bridge->bulkStudentArtifacts($jobId, $artifactType, $studentIds, $options);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['total_students'] = $result['total_students'] ?? 0;
                    $meta['processed'] = $result['processed'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Communications: batch template rendering, attachment prep, delivery reconciliation
            'communications.batch_render' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $templateId = (int) ($payload['template_id'] ?? 0);
                $recipientIds = isset($payload['recipient_ids']) && is_array($payload['recipient_ids'])
                    ? array_values(array_map('intval', $payload['recipient_ids']))
                    : [];
                $channel = (string) ($payload['channel'] ?? ''); // 'sms', 'email', 'whatsapp'
                $context = isset($payload['context']) && is_array($payload['context']) ? $payload['context'] : [];

                if ($jobId === '' || $templateId <= 0 || $recipientIds === [] || $channel === '') {
                    throw new RuntimeException('communications.batch_render: job_id, template_id, recipient_ids[], channel required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for communications.batch_render');
                }

                $result = $bridge->renderCommunicationBatch($jobId, $templateId, $recipientIds, $channel, $context);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['rendered_count'] = $result['rendered_count'] ?? 0;
                    $meta['failed_count'] = $result['failed_count'] ?? 0;
                    $meta['attachments'] = $result['attachments'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'communications.reconcile_delivery' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $provider = (string) ($payload['provider'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $channel = (string) ($payload['channel'] ?? '');

                if ($jobId === '' || $provider === '' || $dateFrom === '' || $dateTo === '' || $channel === '') {
                    throw new RuntimeException('communications.reconcile_delivery: job_id, provider, date_from, date_to, channel required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for communications.reconcile_delivery');
                }

                $result = $bridge->reconcileDelivery($jobId, $provider, $dateFrom, $dateTo, $channel);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['total_sent'] = $result['total_sent'] ?? 0;
                    $meta['delivered'] = $result['delivered'] ?? 0;
                    $meta['failed'] = $result['failed'] ?? 0;
                    $meta['pending'] = $result['pending'] ?? 0;
                    $meta['discrepancies'] = $result['discrepancies'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Inventory: supplier imports, bulk exports, aggregate refresh
            'inventory.supplier_import' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $supplierId = (int) ($payload['supplier_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'catalog', 'pricelist', 'invoice'

                if ($jobId === '' || $fileId <= 0 || $supplierId <= 0 || $importType === '') {
                    throw new RuntimeException('inventory.supplier_import: job_id, file_id, supplier_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for inventory.supplier_import');
                }

                $result = $bridge->importSupplierData($jobId, $fileId, $supplierId, $importType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['imported_items'] = $result['imported_items'] ?? 0;
                    $meta['updated_items'] = $result['updated_items'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'inventory.bulk_export' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $exportType = (string) ($payload['export_type'] ?? ''); // 'stock_valuation', 'reorder_list', 'movement_log', 'full_catalog'
                $filters = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $exportType === '') {
                    throw new RuntimeException('inventory.bulk_export: job_id, export_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for inventory.bulk_export');
                }

                $result = $bridge->exportInventory($jobId, $exportType, $filters, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'inventory.aggregate_refresh' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $aggregates = isset($payload['aggregates']) && is_array($payload['aggregates']) ? $payload['aggregates'] : ['stock_valuation', 'reorder_alerts', 'category_summary'];

                if ($jobId === '') {
                    throw new RuntimeException('inventory.aggregate_refresh: job_id required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for inventory.aggregate_refresh');
                }

                $result = $bridge->refreshInventoryAggregates($jobId, $aggregates);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['refreshed'] = $result['refreshed'] ?? 0;
                    $meta['alerts_generated'] = $result['alerts_generated'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Payments: provider file parsing, exception prep
            'payments.parse_provider_file' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $provider = (string) ($payload['provider'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $paymentType = (string) ($payload['payment_type'] ?? ''); // 'fees', 'transport', 'uniforms', 'admission'

                if ($jobId === '' || $provider === '' || $fileId <= 0 || $paymentType === '') {
                    throw new RuntimeException('payments.parse_provider_file: job_id, provider, file_id, payment_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for payments.parse_provider_file');
                }

                $result = $bridge->parsePaymentFile($jobId, $provider, $fileId, $paymentType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['parsed_rows'] = $result['parsed_rows'] ?? 0;
                    $meta['matched_payments'] = $result['matched_payments'] ?? 0;
                    $meta['unmatched_rows'] = $result['unmatched_rows'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'payments.exception_prep' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $paymentType = (string) ($payload['payment_type'] ?? '');

                if ($jobId === '' || $dateFrom === '' || $dateTo === '' || $paymentType === '') {
                    throw new RuntimeException('payments.exception_prep: job_id, date_from, date_to, payment_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for payments.exception_prep');
                }

                $result = $bridge->preparePaymentExceptions($jobId, $dateFrom, $dateTo, $paymentType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['total_unmatched'] = $result['total_unmatched'] ?? 0;
                    $meta['categorized'] = $result['categorized'] ?? 0;
                    $meta['auto_resolved'] = $result['auto_resolved'] ?? 0;
                    $meta['for_review'] = $result['for_review'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Attendance: register file parsing, trend refresh
            'attendance.parse_register_file' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $sessionId = (int) ($payload['session_id'] ?? 0);
                $source = (string) ($payload['source'] ?? ''); // 'biometric', 'manual_csv', 'mobile_app'

                if ($jobId === '' || $fileId <= 0 || $sessionId <= 0 || $source === '') {
                    throw new RuntimeException('attendance.parse_register_file: job_id, file_id, session_id, source required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for attendance.parse_register_file');
                }

                $result = $bridge->parseAttendanceFile($jobId, $fileId, $sessionId, $source);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['parsed_rows'] = $result['parsed_rows'] ?? 0;
                    $meta['marked_present'] = $result['marked_present'] ?? 0;
                    $meta['marked_absent'] = $result['marked_absent'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'attendance.trend_refresh' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $scope = (string) ($payload['scope'] ?? 'school'); // 'school', 'class', 'stream', 'student'

                if ($jobId === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('attendance.trend_refresh: job_id, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for attendance.trend_refresh');
                }

                $result = $bridge->refreshAttendanceTrends($jobId, $dateFrom, $dateTo, $scope);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['refreshed'] = $result['refreshed'] ?? 0;
                    $meta['alerts_generated'] = $result['alerts_generated'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Transport: route import, aggregate reports
            'transport.route_import' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'routes', 'vehicles', 'stops', 'assignments'

                if ($jobId === '' || $fileId <= 0 || $importType === '') {
                    throw new RuntimeException('transport.route_import: job_id, file_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for transport.route_import');
                }

                $result = $bridge->importTransportData($jobId, $fileId, $importType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['imported_routes'] = $result['imported_routes'] ?? 0;
                    $meta['imported_vehicles'] = $result['imported_vehicles'] ?? 0;
                    $meta['imported_stops'] = $result['imported_stops'] ?? 0;
                    $meta['imported_assignments'] = $result['imported_assignments'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'transport.aggregate_report' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $reportType = (string) ($payload['report_type'] ?? ''); // 'occupancy', 'punctuality', 'fuel_economy', 'incidents', 'route_economics'
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $reportType === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('transport.aggregate_report: job_id, report_type, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for transport.aggregate_report');
                }

                $result = $bridge->generateTransportReport($jobId, $reportType, $dateFrom, $dateTo, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Import: generic parse/validate/preview
            'import.parse' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $fileType = (string) ($payload['file_type'] ?? ''); // 'csv', 'excel', 'ods', 'pdf'
                $options = isset($payload['options']) && is_array($payload['options']) ? $payload['options'] : [];

                if ($jobId === '' || $fileId <= 0 || $fileType === '') {
                    throw new RuntimeException('import.parse: job_id, file_id, file_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for import.parse');
                }

                $result = $bridge->parseImportFile($jobId, $fileId, $fileType, $options);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['sheets'] = $result['sheets'] ?? [];
                    $meta['total_rows'] = $result['total_rows'] ?? 0;
                    $meta['columns'] = $result['columns'] ?? [];
                    $meta['sample_data'] = $result['sample_data'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'import.validate' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $schema = isset($payload['schema']) && is_array($payload['schema']) ? $payload['schema'] : [];

                if ($jobId === '' || $fileId <= 0 || $schema === []) {
                    throw new RuntimeException('import.validate: job_id, file_id, schema[] required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for import.validate');
                }

                $result = $bridge->validateImportFile($jobId, $fileId, $schema);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['valid_rows'] = $result['valid_rows'] ?? 0;
                    $meta['invalid_rows'] = $result['invalid_rows'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'import.preview' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $maxRows = (int) ($payload['max_rows'] ?? 10);

                if ($jobId === '' || $fileId <= 0) {
                    throw new RuntimeException('import.preview: job_id, file_id required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for import.preview');
                }

                $result = $bridge->previewImportFile($jobId, $fileId, $maxRows);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['preview_rows'] = $result['preview_rows'] ?? 0;
                    $meta['columns'] = $result['columns'] ?? [];
                    $meta['sample_data'] = $result['sample_data'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Admission: document extraction, import preview
            'admission.document_extract' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $docType = (string) ($payload['doc_type'] ?? ''); // 'birth_cert', 'transfer_letter', 'medical_form', 'id_doc'

                if ($jobId === '' || $fileId <= 0 || $docType === '') {
                    throw new RuntimeException('admission.document_extract: job_id, file_id, doc_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for admission.document_extract');
                }

                $result = $bridge->extractAdmissionDocument($jobId, $fileId, $docType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['extracted_fields'] = $result['extracted_fields'] ?? [];
                    $meta['confidence'] = $result['confidence'] ?? 0;
                    $meta['requires_review'] = $result['requires_review'] ?? false;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'admission.import_preview' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'applications_csv', 'interview_scores', 'documents_zip'

                if ($jobId === '' || $fileId <= 0 || $importType === '') {
                    throw new RuntimeException('admission.import_preview: job_id, file_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for admission.import_preview');
                }

                $result = $bridge->admissionImportPreview($jobId, $fileId, $importType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['preview_rows'] = $result['preview_rows'] ?? 0;
                    $meta['total_rows'] = $result['total_rows'] ?? 0;
                    $meta['columns'] = $result['columns'] ?? [];
                    $meta['sample_data'] = $result['sample_data'] ?? [];
                    $meta['validation_errors'] = $result['validation_errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Activities: participant import, export, aggregate
            'activities.participant_import' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $activityId = (int) ($payload['activity_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'participants_csv', 'registrations_excel'

                if ($jobId === '' || $fileId <= 0 || $activityId <= 0 || $importType === '') {
                    throw new RuntimeException('activities.participant_import: job_id, file_id, activity_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for activities.participant_import');
                }

                $result = $bridge->importActivityParticipants($jobId, $fileId, $activityId, $importType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['imported_count'] = $result['imported_count'] ?? 0;
                    $meta['updated_count'] = $result['updated_count'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'activities.export' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $exportType = (string) ($payload['export_type'] ?? ''); // 'participants', 'results', 'attendance', 'certificates'
                $activityId = (int) ($payload['activity_id'] ?? 0);
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $exportType === '' || $activityId <= 0) {
                    throw new RuntimeException('activities.export: job_id, export_type, activity_id required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for activities.export');
                }

                $result = $bridge->exportActivity($jobId, $exportType, $activityId, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'activities.aggregate' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $aggregates = isset($payload['aggregates']) && is_array($payload['aggregates']) ? $payload['aggregates'] : ['participation', 'results', 'attendance'];

                if ($jobId === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('activities.aggregate: job_id, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for activities.aggregate');
                }

                $result = $bridge->aggregateActivities($jobId, $dateFrom, $dateTo, $aggregates);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['refreshed'] = $result['refreshed'] ?? 0;
                    $meta['alerts_generated'] = $result['alerts_generated'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Boarding: occupancy summary, rollcall export
            'boarding.occupancy_summary' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('boarding.occupancy_summary: job_id, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for boarding.occupancy_summary');
                }

                $result = $bridge->boardingOccupancySummary($jobId, $dateFrom, $dateTo, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'boarding.rollcall_export' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $dormitoryId = (int) ($payload['dormitory_id'] ?? 0);
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('boarding.rollcall_export: job_id, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for boarding.rollcall_export');
                }

                $result = $bridge->exportBoardingRollcall($jobId, $dateFrom, $dateTo, $dormitoryId, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Health: document extract, summarize
            'health.document_extract' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $docType = (string) ($payload['doc_type'] ?? ''); // 'medical_form', 'vaccination_record', 'incident_report', 'consent_form'

                if ($jobId === '' || $fileId <= 0 || $docType === '') {
                    throw new RuntimeException('health.document_extract: job_id, file_id, doc_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for health.document_extract');
                }

                $result = $bridge->extractHealthDocument($jobId, $fileId, $docType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['extracted_fields'] = $result['extracted_fields'] ?? [];
                    $meta['confidence'] = $result['confidence'] ?? 0;
                    $meta['requires_review'] = $result['requires_review'] ?? false;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'health.summarize' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $scope = (string) ($payload['scope'] ?? 'school'); // 'school', 'class', 'student'
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('health.summarize: job_id, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for health.summarize');
                }

                $result = $bridge->summarizeHealth($jobId, $dateFrom, $dateTo, $scope, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Library: catalogue import, bulk export
            'library.catalogue_import' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'marc', 'csv', 'excel', 'isbn_batch'

                if ($jobId === '' || $fileId <= 0 || $importType === '') {
                    throw new RuntimeException('library.catalogue_import: job_id, file_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for library.catalogue_import');
                }

                $result = $bridge->importLibraryCatalogue($jobId, $fileId, $importType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['imported_count'] = $result['imported_count'] ?? 0;
                    $meta['updated_count'] = $result['updated_count'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'library.bulk_export' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $exportType = (string) ($payload['export_type'] ?? ''); // 'catalogue', 'circulation', 'overdue', 'inventory'
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $exportType === '') {
                    throw new RuntimeException('library.bulk_export: job_id, export_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for library.bulk_export');
                }

                $result = $bridge->exportLibrary($jobId, $exportType, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Maintenance: attachment extract, summary export
            'maintenance.attachment_extract' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $workOrderId = (int) ($payload['work_order_id'] ?? 0);
                $extractType = (string) ($payload['extract_type'] ?? ''); // 'photos', 'documents', 'signatures'

                if ($jobId === '' || $fileId <= 0 || $workOrderId <= 0 || $extractType === '') {
                    throw new RuntimeException('maintenance.attachment_extract: job_id, file_id, work_order_id, extract_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for maintenance.attachment_extract');
                }

                $result = $bridge->extractMaintenanceAttachments($jobId, $fileId, $workOrderId, $extractType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['extracted_count'] = $result['extracted_count'] ?? 0;
                    $meta['metadata'] = $result['metadata'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'maintenance.summary_export' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $exportType = (string) ($payload['export_type'] ?? ''); // 'work_orders', 'costs', 'preventive', 'equipment_history'
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $dateFrom === '' || $dateTo === '' || $exportType === '') {
                    throw new RuntimeException('maintenance.summary_export: job_id, date_from, date_to, export_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for maintenance.summary_export');
                }

                $result = $bridge->exportMaintenanceSummary($jobId, $dateFrom, $dateTo, $exportType, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Schedules: extract, propose rows
            'schedules.extract' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $source = (string) ($payload['source'] ?? ''); // 'ministry_csv', 'timetable_excel', 'calendar_ics'

                if ($jobId === '' || $fileId <= 0 || $source === '') {
                    throw new RuntimeException('schedules.extract: job_id, file_id, source required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for schedules.extract');
                }

                $result = $bridge->extractSchedule($jobId, $fileId, $source);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['extracted_rows'] = $result['extracted_rows'] ?? 0;
                    $meta['conflicts'] = $result['conflicts'] ?? [];
                    $meta['proposed_rows'] = $result['proposed_rows'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'schedules.propose_rows' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $academicYearId = (int) ($payload['academic_year_id'] ?? 0);
                $termId = (int) ($payload['term_id'] ?? 0);
                $constraints = isset($payload['constraints']) && is_array($payload['constraints']) ? $payload['constraints'] : [];

                if ($jobId === '' || $academicYearId <= 0 || $termId <= 0) {
                    throw new RuntimeException('schedules.propose_rows: job_id, academic_year_id, term_id required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for schedules.propose_rows');
                }

                $result = $bridge->proposeScheduleRows($jobId, $academicYearId, $termId, $constraints);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['proposed_rows'] = $result['proposed_rows'] ?? 0;
                    $meta['conflicts'] = $result['conflicts'] ?? [];
                    $meta['coverage'] = $result['coverage'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Staff: bulk import, export, analytics
            'staff.bulk_import' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $fileId = (int) ($payload['file_id'] ?? 0);
                $importType = (string) ($payload['import_type'] ?? ''); // 'staff_csv', 'contracts_excel', 'payroll_excel', 'qualifications_csv'

                if ($jobId === '' || $fileId <= 0 || $importType === '') {
                    throw new RuntimeException('staff.bulk_import: job_id, file_id, import_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for staff.bulk_import');
                }

                $result = $bridge->importStaffData($jobId, $fileId, $importType);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['imported_count'] = $result['imported_count'] ?? 0;
                    $meta['updated_count'] = $result['updated_count'] ?? 0;
                    $meta['errors'] = $result['errors'] ?? [];
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'staff.export' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $exportType = (string) ($payload['export_type'] ?? ''); // 'staff_list', 'contracts', 'payroll', 'leave_balances', 'qualifications'
                $outputFormat = (string) ($payload['output_format'] ?? 'xlsx');

                if ($jobId === '' || $exportType === '') {
                    throw new RuntimeException('staff.export: job_id, export_type required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for staff.export');
                }

                $result = $bridge->exportStaff($jobId, $exportType, $outputFormat);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            'staff.analytics' => static function (array $payload, PDO $pdo): void {
                $jobId = (string) ($payload['job_id'] ?? '');
                $dateFrom = (string) ($payload['date_from'] ?? '');
                $dateTo = (string) ($payload['date_to'] ?? '');
                $metrics = isset($payload['metrics']) && is_array($payload['metrics']) ? $payload['metrics'] : ['workload', 'leave', 'performance', 'contracts'];

                if ($jobId === '' || $dateFrom === '' || $dateTo === '') {
                    throw new RuntimeException('staff.analytics: job_id, date_from, date_to required');
                }

                $bridge = new \App\API\Services\PythonDocumentBridge();
                if (!$bridge->available()) {
                    throw new RuntimeException('Python service not configured for staff.analytics');
                }

                $result = $bridge->analyzeStaff($jobId, $dateFrom, $dateTo, $metrics);

                $job = \App\API\Services\JobQueue::fetchJob($jobId);
                if ($job) {
                    $meta = $job['meta'] ?? [];
                    $meta['artifact'] = $result['artifact'] ?? null;
                    $meta['download_url'] = $result['download_url'] ?? null;
                    $meta['row_count'] = $result['row_count'] ?? 0;
                    $meta['sheet_count'] = $result['sheet_count'] ?? 0;
                    \App\API\Services\JobQueue::updateMeta($jobId, $meta);
                }
            },
            // Extend here with 'generate_report_card' => ..., 'send_bulk_sms' => ...
            // only once the producing workflow pushes and consumes them.
        ];
    }
}
