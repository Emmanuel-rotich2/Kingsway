<?php

declare(strict_types=1);

namespace App\API\Services\documents;

use App\API\Includes\FileLogger;
use App\API\Services\DownloadService;
use App\API\Services\JobQueue;
use App\API\Services\PrintService;
use App\API\Services\RealtimeGatewayPublisher;
use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Resumable background bulk-document pipeline.
 *
 * A batch is a bounded worker workload, not a long-running request:
 *
 *   enqueue()  -> state file + one 'print.document_batch' job (BATCH lane)
 *   step()     -> one worker invocation: renders <=MAX_ENTITIES_PER_STEP entities,
 *                 persists groups atomically by ordinal, then either re-enqueues
 *                 itself (idempotency key unique per step) or finalizes.
 *   finalize() -> hierarchical PDF merge (memory bounded, one chunk at a time),
 *                 or an auto ZIP when the merged single PDF would exceed the
 *                 renderer's output ceiling.
 *
 * Idempotency: part files are indexed by the processed entity ordinal, so a
 * crashed step that retries reproduces (overwrites) exactly the same parts and
 * never duplicates pages. Entity resolution runs once and is persisted.
 *
 * State is a file under storage/document_batches/ (web-denied), never a
 * database table; parts and the artifact live in the batch sub-directory.
 */
final class DocumentBatchService
{
    public const JOB_TYPE = 'print.document_batch';

    private const MAX_ENTITIES_PER_STEP = 28;
    private const MAX_GROUP_DOCS = 8;
    private const MAX_GROUP_BYTES = 8 * 1024 * 1024;
    private const STEP_TIME_BUDGET_MS = 30000;
    private const AUTO_ZIP_ESTIMATED_BYTES = 24 * 1024 * 1024;
    private const MERGE_CHUNK_BYTES = 12 * 1024 * 1024;

    private PDO $db;
    private DocumentBatchStore $store;
    private DocumentBatchRenderer $renderer;

    public function __construct(
        ?PDO $db = null,
        ?DocumentBatchStore $store = null,
        ?DocumentBatchRenderer $renderer = null
    ) {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->store = $store ?? new DocumentBatchStore();
        $this->renderer = $renderer ?? new PythonDocumentBatchRenderer();
    }

    /**
     * Start a bulk-print batch.
     *
     * @param array<string, mixed> $selection
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context user_id, permissions, request_id
     * @return array{batch_id: string, kind: string, status: string}
     */
    public function enqueue(string $kind, array $selection, array $options, array $context): array
    {
        if (!DocumentBatchRegistry::has($kind)) {
            throw new RuntimeException('Unknown or unavailable document batch kind.');
        }
        $producer = DocumentBatchRegistry::make($kind, $this->db);
        $options = array_replace($producer::defaultOptions(), $options);

        $batchId = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        $state = [
            'batch_id' => $batchId,
            'kind' => $kind,
            'owner_user_id' => (int) ($context['user_id'] ?? 0),
            'request_id' => (string) ($context['request_id'] ?? ''),
            'status' => JobQueue::STATUS_PENDING,
            'options' => $options,
            'selection' => $selection,
            'total' => 0,
            'processed' => 0,
            'skipped' => 0,
            'parts' => [],
            'part_sizes' => [],
            'artifact' => null,
            'delivery' => null,
            'step' => 0,
            'error' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'expires_at' => date('Y-m-d H:i:s', time() + (48 * 3600)),
        ];
        $this->store->create($batchId, $state);

        $jobId = JobQueue::push(self::JOB_TYPE, [
            'batch_id' => $batchId,
            'idempotency_key' => 'docbatch:' . $batchId . ':s0',
        ], 0, 3, 60);

        $state['job_id'] = $jobId;
        $this->store->save($batchId, $state);

        FileLogger::write('document_generation', [
            'type' => 'document_batch_queued',
            'batch_id' => $batchId,
            'kind' => $kind,
            'job_id' => $jobId,
            'owner_user_id' => $state['owner_user_id'],
        ]);

        return [
            'batch_id' => $batchId,
            'kind' => $kind,
            'status' => JobQueue::STATUS_PENDING,
        ];
    }

    /**
     * Process one worker step for a batch. Safe to resume after retry.
     */
    public function step(string $batchId): array
    {
        $state = $this->store->load($batchId);
        if ($state === null) {
            throw new RuntimeException('Document batch not found: ' . $batchId);
        }
        if (in_array($state['status'] ?? '', ['done', 'failed', 'cancelled'], true)) {
            return $this->publicStatus($state);
        }

        $kind = (string) $state['kind'];
        $producer = DocumentBatchRegistry::make($kind, $this->db);
        $context = [
            'user_id' => (int) ($state['owner_user_id'] ?? 0),
            'request_id' => (string) ($state['request_id'] ?? ''),
        ];

        $state['status'] = 'processing';
        $state['job_id'] = (int) ($state['job_id'] ?? 0);

        if ($state['total'] <= 0 || $state['entities'] === []) {
            $resolved = $producer->resolveEntities(
                (array) ($state['selection'] ?? []),
                (array) ($state['options'] ?? []),
                $context
            );
            $state['total'] = max(0, (int) ($resolved['total'] ?? count($resolved['entities'])));
            $state['entities'] = array_values($resolved['entities']);
            $state['unit'] = (string) ($resolved['unit'] ?? 'items');
            $state['processed'] = 0;
            $this->store->save($batchId, $state);
            if ($state['total'] === 0) {
                return $this->finishSkipped($state, $batchId, $producer, 'No eligible ' . $kind . ' were found for the selection.');
            }
        }

        $entities = $state['entities'];
        $options = (array) ($state['options'] ?? []);
        $startWall = microtime(true);
        $processed = (int) $state['processed'];
        $skipped = (int) $state['skipped'];
        $remainingInStep = self::MAX_ENTITIES_PER_STEP;

        while ($processed < count($entities) && $remainingInStep > 0) {
            $group = [];
            $groupBytes = 0;
            while ($processed < count($entities) && $remainingInStep > 0) {
                if ($this->exceededBudget($startWall)) {
                    break 2;
                }
                $entity = $entities[$processed];
                try {
                    $html = $producer->renderHtml($entity, $options, $context);
                } catch (\Throwable $e) {
                    $skipped++;
                    $processed++;
                    $remainingInStep--;
                    FileLogger::write('document_generation', [
                        'type' => 'document_batch_entity_failed',
                        'batch_id' => $batchId,
                        'kind' => $kind,
                        'entity_id' => (string) ($entity['id'] ?? ''),
                        'error' => $this->safeError($e),
                    ]);
                    continue;
                }
                $processed++;
                $remainingInStep--;
                if ($html === null) {
                    $skipped++;
                    continue;
                }
                $bytes = strlen($html['html'] ?? '');
                if ($bytes > self::MAX_GROUP_BYTES) {
                    $skipped++;
                    $this->journalSkippedOversize($batchId, $kind, $entity, $bytes);
                    continue;
                }
                if ($group !== [] && (count($group) >= self::MAX_GROUP_DOCS || $groupBytes + $bytes > self::MAX_GROUP_BYTES)) {
                    $processed = $this->flushGroup($batchId, $state, $processed, $group, $startWall);
                    $group = [];
                    $groupBytes = 0;
                }
                $group[] = $html;
                $groupBytes += $bytes;
            }
            $processed = $this->flushGroup($batchId, $state, $processed, $group, $startWall);
        }

        $state['processed'] = $processed;
        $state['skipped'] = $skipped;
        $state['updated_at'] = date('Y-m-d H:i:s');
        $this->store->save($batchId, $state);

        FileLogger::write('document_generation', [
            'type' => 'document_batch_step',
            'batch_id' => $batchId,
            'kind' => $kind,
            'processed' => $processed,
            'total' => (int) $state['total'],
            'skipped' => $skipped,
            'parts' => count($state['parts']),
            'duration_ms' => (int) round((microtime(true) - $startWall) * 1000),
        ]);

        $this->publishJobEvent('JOB_PROGRESS', $state, [
            'progress' => (int) $state['total'] > 0
                ? (int) round($processed / max(1, (int) $state['total']) * 100) : 0,
            'status' => 'running',
        ]);

        if ($processed < (int) $state['total']) {
            $state['step'] = (int) ($state['step'] ?? 0) + 1;
            $state['job_id'] = JobQueue::push(self::JOB_TYPE, [
                'batch_id' => $batchId,
                'idempotency_key' => 'docbatch:' . $batchId . ':s' . (int) $state['step'],
            ], 0, 3, 60);
            $this->store->save($batchId, $state);
            return $this->publicStatus($state);
        }

        return $this->finalize($batchId, $state, $producer);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(string $batchId, int $viewerUserId = 0, bool $requireOwner = true): array
    {
        $state = $this->store->load($batchId);
        if ($state === null) {
            throw new RuntimeException('Document batch not found.');
        }
        if ($requireOwner && (int) ($state['owner_user_id'] ?? 0) !== max(0, $viewerUserId)) {
            throw new RuntimeException('Document batch access denied.');
        }
        return $this->publicStatus($state);
    }

    /**
     * @return array{path: string, filename: string, mime_type: string}|null
     */
    public function artifact(string $batchId, int $viewerUserId = 0): ?array
    {
        $state = $this->store->load($batchId);
        if ($state === null || (int) ($state['owner_user_id'] ?? 0) !== max(0, $viewerUserId)) {
            throw new RuntimeException('Document batch access denied.');
        }
        if (($state['status'] ?? null) !== 'done' || empty($state['artifact'])) {
            return null;
        }
        return [
            'path' => $this->store->artifactPath($batchId, (string) $state['artifact']),
            'filename' => (string) $state['artifact'],
            'mime_type' => ($state['delivery'] ?? 'pdf') === 'zip' ? 'application/zip' : 'application/pdf',
        ];
    }

    public function cancel(string $batchId, int $viewerUserId = 0): array
    {
        $state = $this->store->load($batchId);
        if ($state === null || (int) ($state['owner_user_id'] ?? 0) !== max(0, $viewerUserId)) {
            throw new RuntimeException('Document batch access denied.');
        }
        if (in_array($state['status'] ?? '', ['done', 'failed', 'cancelled'], true)) {
            return $this->publicStatus($state);
        }
        if (!empty($state['job_id'])) {
            JobQueue::cancelJob((int) $state['job_id']);
        }
        $state['status'] = 'cancelled';
        $state['updated_at'] = date('Y-m-d H:i:s');
        $this->store->save($batchId, $state);
        $this->publishJobEvent('JOB_FAILED', $state, ['status' => 'cancelled', 'reason' => 'user_cancelled']);
        FileLogger::write('document_generation', [
            'type' => 'document_batch_cancelled',
            'batch_id' => $batchId,
            'kind' => (string) ($state['kind'] ?? ''),
        ]);
        return $this->publicStatus($state);
    }

    /** Retention hook for the existing cleanup cron endpoint. */
    public function purgeExpired(int $hours = 48): int
    {
        return $this->store->purgeExpired($hours);
    }

    // ───────────────────────── internals ─────────────────────────

    /**
     * @param array<int, array{html: string, filename: string}> $group
     */
    private function flushGroup(string $batchId, array &$state, int $processed, array $group, float $startWall): int
    {
        if ($group === []) {
            return $processed;
        }
        $documents = [];
        foreach ($group as $index => $doc) {
            $documents[] = [
                'document_id' => 'doc-' . ($processed - count($group) + $index + 1),
                'html' => $doc['html'],
            ];
        }
        $result = $this->renderer->renderCombined($documents);
        $partFilename = $this->store->writePart($batchId, $processed, $result['pdf']);
        $state['parts'][] = $partFilename;
        $state['part_sizes'][] = strlen($result['pdf']);

        FileLogger::write('document_generation', [
            'type' => 'document_batch_part',
            'batch_id' => $batchId,
            'kind' => (string) ($state['kind'] ?? ''),
            'part' => $partFilename,
            'documents' => count($group),
            'pdf_bytes' => strlen($result['pdf']),
            'pages' => (int) $result['pages'],
            'duration_ms' => (int) round((microtime(true) - $startWall) * 1000),
        ]);
        return $processed;
    }

    private function finalize(string $batchId, array &$state, DocumentBatchProducer $producer): array
    {
        $parts = array_values($state['parts']);
        $sizes = array_values($state['part_sizes']);

        if ($parts === []) {
            return $this->finishSkipped($state, $batchId, $producer, 'No printable documents were produced.');
        }

        $estimatedBytes = (int) array_sum($sizes);
        $safeStamp = date('Ymd_His');
        $kindSlug = preg_replace('/[^A-Za-z0-9_]+/', '_', (string) $state['kind']);
        $baseName = $kindSlug . '_' . $safeStamp;

        if (count($parts) === 1) {
            $content = $this->store->readPart($batchId, $parts[0]);
            $state['artifact'] = $this->store->writeArtifact($batchId, $baseName . '.pdf', $content);
            $state['delivery'] = 'pdf';
            $state['artifact_bytes'] = strlen($content);
        } elseif ($estimatedBytes > self::AUTO_ZIP_ESTIMATED_BYTES) {
            $state['artifact'] = $this->store->writeArtifact($batchId, $baseName . '.zip', $this->buildZip($batchId, $parts));
            $state['delivery'] = 'zip';
            $state['artifact_bytes'] = null;
        } else {
            $merged = $this->mergeParts($batchId, $parts);
            $state['artifact'] = $this->store->writeArtifact($batchId, $baseName . '.pdf', $merged);
            $state['delivery'] = 'pdf';
            $state['artifact_bytes'] = strlen($merged);
        }

        $state['status'] = 'done';
        $state['updated_at'] = date('Y-m-d H:i:s');
        $this->publishArtifact($batchId, $state, $baseName);
        $this->store->save($batchId, $state);

        FileLogger::write('document_generation', [
            'type' => 'document_batch_completed',
            'batch_id' => $batchId,
            'kind' => (string) ($state['kind'] ?? ''),
            'processed' => (int) ($state['processed']),
            'total' => (int) ($state['total']),
            'skipped' => (int) ($state['skipped']),
            'parts' => count($parts),
            'delivery' => $state['delivery'],
            'artifact' => $state['artifact'],
            'download_path' => $state['download_path'] ?? null,
        ]);

        $this->publishJobEvent('JOB_COMPLETE', $state, ['progress' => 100, 'status' => 'completed']);

        return $this->publicStatus($state);
    }

    /**
     * Make the finished artifact downloadable through the existing print path
     * (signed temporary URL, file inside PRINT_OUTPUT_PATH). Requires the app
     * constants to be defined; the artifact remains purged with the batch.
     */
    private function publishArtifact(string $batchId, array &$state, string $baseName): void
    {
        if (!defined('PRINT_OUTPUT_PATH') || empty($state['artifact'])) {
            return;
        }
        try {
            $bytes = $this->store->readArtifact($batchId, (string) $state['artifact']);
            $extension = ($state['delivery'] ?? 'pdf') === 'zip' ? 'zip' : 'pdf';
            $outputPath = (new PrintService())->writeGeneratedFile(
                $baseName . '.' . $extension,
                $bytes
            );
            $downloads = new DownloadService();
            $state['download_path'] = $outputPath;
            $state['download_url'] = $extension === 'zip'
                ? $downloads->generatedDownloadUrlForAbsolutePath($outputPath)
                : $downloads->printUrlForAbsolutePath($outputPath);
        } catch (\Throwable $e) {
            $state['download_path'] = null;
            $state['download_url'] = null;
            FileLogger::write('document_generation', [
                'type' => 'document_batch_publish_failed',
                'batch_id' => $batchId,
                'kind' => (string) ($state['kind'] ?? ''),
                'error' => $this->safeError($e),
            ]);
        }
    }

    private function finishSkipped(array &$state, string $batchId, DocumentBatchProducer $producer, string $reason): array
    {
        FileLogger::write('document_generation', [
            'type' => 'document_batch_empty',
            'batch_id' => $batchId,
            'kind' => (string) ($state['kind'] ?? ''),
            'reason' => $reason,
        ]);
        $state['status'] = 'failed';
        $state['error'] = $reason;
        $state['updated_at'] = date('Y-m-d H:i:s');
        $this->store->save($batchId, $state);
        $this->publishJobEvent('JOB_FAILED', $state, ['status' => 'failed', 'reason' => 'empty_selection']);
        throw new RuntimeException($reason);
    }

    /**
     * Push a job-telemetry descriptor so the OWNER's browser renders live bulk
     * print progress and the polling caller can short-circuit its sleep. The
     * scope is the owner's own users:<id> channel: the person who started the
     * batch is the only one watching it, so every other staff browser stays
     * quiet. Descriptor-only (job_id/progress/status/domain),
     * fire-and-forget: a realtime outage never slows a batch.
     */
    private function publishJobEvent(string $type, array $state, array $extra = []): void
    {
        try {
            $ownerUserId = (int) ($state['owner_user_id'] ?? 0);
            if ($ownerUserId < 1) {
                return;
            }
            RealtimeGatewayPublisher::publish($type, 'users:' . $ownerUserId, array_merge([
                'job_id' => (int) ($state['job_id'] ?? 0),
                'domain' => 'documents',
                'targets' => ['documents'],
            ], $extra));
        } catch (\Throwable $error) {
            FileLogger::write('document_generation', [
                'type' => 'document_batch_realtime_failed',
                'error' => $this->safeError($error),
            ]);
        }
    }

    /**
     * Hierarchical PDF merge: memory is bounded to one chunk at a time.
     *
     * @param array<int, string> $parts part filenames in order
     */
    private function mergeParts(string $batchId, array $parts): string
    {
        $level = $parts;
        while (count($level) > 1) {
            $nextLevel = [];
            $chunk = [];
            $chunkBytes = 0;
            foreach ($level as $part) {
                $bytes = $this->store->readPart($batchId, $part);
                if ($chunk !== [] && $chunkBytes + strlen($bytes) > self::MERGE_CHUNK_BYTES) {
                    $nextLevel[] = $this->renderer->merge($chunk);
                    $chunk = [];
                    $chunkBytes = 0;
                }
                $chunk[] = $bytes;
                $chunkBytes += strlen($bytes);
            }
            if ($chunk !== []) {
                $nextLevel[] = count($chunk) > 1 ? $this->renderer->merge($chunk) : $chunk[0];
            }
            $level = $nextLevel;
        }
        return $level[0];
    }

    /**
     * @param array<int, string> $parts
     */
    private function buildZip(string $batchId, array $parts): string
    {
        $dir = $this->store->batchDirectory($batchId);
        $zipPath = $dir . '/_merge.tmp.zip';
        @unlink($zipPath);
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to open the batch ZIP archive.');
        }
        try {
            foreach ($parts as $index => $part) {
                $name = sprintf('part-%03d.pdf', $index + 1);
                $zip->addFile($dir . '/' . $part, $name);
            }
        } finally {
            $zip->close();
        }
        $bytes = @file_get_contents($zipPath);
        @unlink($zipPath);
        if ($bytes === false) {
            throw new RuntimeException('Unable to read the batch ZIP archive.');
        }
        return $bytes;
    }

    private function exceededBudget(float $startWall): bool
    {
        return ((microtime(true) - $startWall) * 1000) >= self::STEP_TIME_BUDGET_MS;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function publicStatus(array $state): array
    {
        $total = max(0, (int) ($state['total'] ?? 0));
        $processed = max(0, (int) ($state['processed'] ?? 0));
        return [
            'batch_id' => (string) ($state['batch_id'] ?? ''),
            'job_id' => (int) ($state['job_id'] ?? 0),
            'kind' => (string) ($state['kind'] ?? ''),
            'status' => (string) ($state['status'] ?? 'pending'),
            'total' => $total,
            'processed' => $processed,
            'skipped' => max(0, (int) ($state['skipped'] ?? 0)),
            'unit' => (string) ($state['unit'] ?? 'items'),
            'progress_pct' => $total > 0 ? (int) round(($processed / $total) * 100) : 0,
            'delivery' => $state['delivery'] ?? null,
            'artifact' => ($state['status'] ?? null) === 'done' && !empty($state['artifact'])
                ? ['filename' => (string) $state['artifact'], 'bytes' => $state['artifact_bytes'] ?? null]
                : null,
            'download_url' => ($state['status'] ?? null) === 'done' ? ($state['download_url'] ?? null) : null,
            'error' => $state['error'] ?? null,
            'created_at' => (string) ($state['created_at'] ?? ''),
            'updated_at' => (string) ($state['updated_at'] ?? ''),
            'expires_at' => (string) ($state['expires_at'] ?? ''),
        ];
    }

    private function journalSkippedOversize(string $batchId, string $kind, array $entity, int $bytes): void
    {
        FileLogger::write('document_generation', [
            'type' => 'document_batch_entity_oversize',
            'batch_id' => $batchId,
            'kind' => $kind,
            'entity_id' => (string) ($entity['id'] ?? ''),
            'html_bytes' => $bytes,
        ]);
    }

    private function safeError(\Throwable $e): string
    {
        return mb_strlen($e->getMessage()) > 300 ? mb_substr($e->getMessage(), 0, 300) : $e->getMessage();
    }
}