<?php

declare(strict_types=1);

namespace App\API\Services\automations;

use App\API\Includes\FileLogger;
use App\API\Services\AutomationBridge;
use App\API\Services\AutomationRegistry;
use App\API\Services\JobQueue;
use PDO;
use RuntimeException;

/**
 * Queue-facing execution for governed, non-AI automations run by the Python
 * engine.
 *
 * Two phases, separated by the job queue so a big export never holds an HTTP
 * request open:
 *
 *   enqueue() — validate against AutomationRegistry with the operator's
 *               permissions, store the bounded input on disk, push the
 *               automation.run job with a content-hash idempotency key.
 *
 *   execute() — worker-side: re-resolve the operator's permissions at run
 *               time (grants may have changed since enqueue), re-authorize,
 *               call the engine, store the artifact with a SHA-256 checksum,
 *               journal the whole lifecycle to the file journals.
 *
 * Artifacts are derivative working files, never official records: they live
 * under storage/automations/ (access-denied at the webserver by
 * storage/.htaccess) and every event is in the file journals, never a log
 * table.
 */
final class AutomationArtifacts
{
    private const INPUT_DIR = 'storage/automations/in';
    private const OUTPUT_DIR = 'storage/automations/out';
    private const MAX_INPUT_JSON_BYTES = 6 * 1024 * 1024;

    private ?AutomationBridge $bridge;
    private ?string $storageRoot;

    public function __construct(?AutomationBridge $bridge = null, ?string $storageRoot = null)
    {
        $this->bridge = $bridge;
        $this->storageRoot = $storageRoot;
    }

    /**
     * Enqueue an automation for a permitted operator.
     *
     * @return array{job_id:int, artifact_id:string, idempotent_reuse:bool}
     */
    public function enqueue(int $userId, array $effectivePermissions, string $automationId, array $input, string $requestId = ''): array
    {
        $this->purgeExpired();
        $definition = AutomationRegistry::resolve($automationId);
        if ($definition === null) {
            throw new RuntimeException('Unknown automation requested.', 404);
        }
        if (!AutomationRegistry::isPermitted($effectivePermissions, $automationId)) {
            throw new RuntimeException('You are not permitted to run this automation.', 403);
        }
        $this->assertBounds($automationId, $input);

        $fingerprint = hash('sha256', $automationId . '|' . json_encode($input));
        // The queue dedupes on this stable key: identical input from the same
        // operator returns the in-flight or finished job, never a duplicate.
        $idempotencyKey = sprintf('auto:%d:%s', $userId, $fingerprint);
        $artifactId = substr(hash('sha256', $idempotencyKey), 0, 32);

        $this->storeInput($artifactId, $automationId, $input);

        $before = $this->artifactStatus($artifactId);
        $jobId = JobQueue::push('automation.run', [
            'automation' => $automationId,
            'artifact_id' => $artifactId,
            'user_id' => $userId,
            'request_id' => substr($requestId, 0, 100),
            'idempotency_key' => $idempotencyKey,
        ]);

        FileLogger::write('automation', [
            'type' => 'automation_enqueued',
            'automation' => $automationId,
            'operator_id' => $userId,
            'job_id' => $jobId,
            'artifact_id' => $artifactId,
            'request_id' => $requestId,
        ]);

        return [
            'job_id' => $jobId,
            'artifact_id' => $artifactId,
            'idempotent_reuse' => $before !== null,
        ];
    }

    /**
     * Worker-side execution of one automation.run job.
     *
     * @return array<string,mixed> the stored artifact descriptor
     */
    public function execute(array $payload, PDO $pdo): array
    {
        $this->purgeExpired();
        $automationId = (string) ($payload['automation'] ?? '');
        $userId = (int) ($payload['user_id'] ?? 0);
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        if ($automationId === '' || $userId < 1 || !preg_match('/^[a-f0-9]{32}$/', $artifactId)) {
            throw new RuntimeException('Malformed automation job payload.');
        }

        // Re-authorize at run time; grants may have changed since enqueue.
        $permissions = $this->effectivePermissions($pdo, $userId);
        if (!AutomationRegistry::isPermitted($permissions, $automationId)) {
            throw new RuntimeException('The operator is not permitted to run this automation.');
        }

        $inputPath=$this->inputPath($artifactId);
        if ($automationId === AutomationRegistry::EXAM_DOCUMENT_PREVIEW && is_file($inputPath)
            && filemtime($inputPath) !== false && filemtime($inputPath) < time() - 3600) {
            @unlink($inputPath);
            throw new RuntimeException('The uploaded exam document expired before it could be processed. Queue a new preview.');
        }
        $input = $this->loadInput($artifactId);
        $result = ($this->bridge ?? new AutomationBridge())->run(
            $automationId,
            ['user_id' => $userId, 'permissions' => $permissions],
            $input
        );

        $content = (string) ($result['content'] ?? '');
        if ($content === '' || strlen($content) > AutomationRegistry::MAX_RESULT_BYTES) {
            throw new RuntimeException('The engine produced an unusable artifact.');
        }
        $descriptor = $this->storeOutput($artifactId, $automationId, $userId, $result, $content);

        FileLogger::write('automation', [
            'type' => 'automation_completed',
            'automation' => $automationId,
            'operator_id' => $userId,
            'artifact_id' => $artifactId,
            'checksum' => $descriptor['checksum_sha256'] ?? '',
            'bytes' => $descriptor['bytes'] ?? 0,
        ]);

        return $descriptor;
    }

    /**
     * Authorize again and return one bounded input to the private Python worker.
     * The PHP process remains responsible for permission resolution and storage.
     */
    public function preparePythonJob(array $payload, PDO $pdo): array
    {
        $this->purgeExpired();
        $automationId = (string) ($payload['automation'] ?? '');
        $userId = (int) ($payload['user_id'] ?? 0);
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        if ($automationId === '' || $userId < 1 || !preg_match('/^[a-f0-9]{32}$/', $artifactId)) {
            throw new RuntimeException('Malformed automation job payload.');
        }
        $definition = AutomationRegistry::resolve($automationId);
        if ($definition === null) throw new RuntimeException('Unknown automation requested.');
        $permissions = $this->effectivePermissions($pdo, $userId);
        if (!AutomationRegistry::isPermitted($permissions, $automationId)) {
            throw new RuntimeException('The operator is no longer permitted to run this automation.');
        }
        $inputPath = $this->inputPath($artifactId);
        if ($automationId === AutomationRegistry::EXAM_DOCUMENT_PREVIEW && is_file($inputPath)
            && filemtime($inputPath) !== false && filemtime($inputPath) < time() - 3600) {
            @unlink($inputPath);
            throw new RuntimeException('The uploaded exam document expired before processing. Queue a new preview.');
        }
        $input = $this->loadInput($artifactId);
        $this->assertBounds($automationId, $input);
        unset($input['_automation_id']);
        return [
            'automation' => $automationId,
            'operator' => ['user_id' => $userId, 'permissions' => $permissions],
            'payload' => $input,
            'request_id' => substr((string) ($payload['request_id'] ?? ''), 0, 100),
        ];
    }

    /** Validate and stage one Python artifact while leaving its input retryable. */
    public function stagePythonResult(array $payload, array $result, PDO $pdo): array
    {
        $automationId = (string) ($payload['automation'] ?? '');
        $userId = (int) ($payload['user_id'] ?? 0);
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        $definition = AutomationRegistry::resolve($automationId);
        if ($definition === null || $userId < 1 || !preg_match('/^[a-f0-9]{32}$/', $artifactId)) {
            throw new RuntimeException('Malformed automation result target.');
        }
        $permissions = $this->effectivePermissions($pdo, $userId);
        if (!AutomationRegistry::isPermitted($permissions, $automationId)) {
            throw new RuntimeException('The operator is no longer permitted to receive this artifact.');
        }
        $format = strtolower((string) ($result['format'] ?? ''));
        $content = $result['content'] ?? null;
        if (!is_string($content) && is_string($result['content_base64'] ?? null)) {
            $content = base64_decode($result['content_base64'], true);
        }
        if (!is_string($content) || $content === '' || strlen($content) > AutomationRegistry::MAX_RESULT_BYTES
            || !in_array($format, (array) ($definition['formats'] ?? []), true)) {
            throw new RuntimeException('The Python worker returned an invalid artifact envelope.');
        }
        $normalized = [
            'format' => $format,
            'row_count' => max(0, min(AutomationRegistry::MAX_ROWS, (int) ($result['row_count'] ?? 0))),
            'column_count' => max(0, min(AutomationRegistry::MAX_COLUMNS, (int) ($result['column_count'] ?? 0))),
        ];
        return $this->storeOutput($artifactId, $automationId, $userId, $normalized, $content, false);
    }

    /** Finalize local lifecycle once the PHP queue has accepted completion. */
    public function finishPythonJob(array $payload, array $descriptor): void
    {
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $artifactId) !== 1) return;
        @unlink($this->inputPath($artifactId));
        FileLogger::write('automation', [
            'type' => 'automation_completed',
            'automation' => (string) ($payload['automation'] ?? ''),
            'operator_id' => (int) ($payload['user_id'] ?? 0),
            'artifact_id' => $artifactId,
            'checksum' => (string) ($descriptor['checksum_sha256'] ?? ''),
            'bytes' => (int) ($descriptor['bytes'] ?? 0),
        ]);
    }

    /** Path of the input file for one artifact identity. */
    public function inputPath(string $artifactId): string
    {
        return $this->root() . '/' . self::INPUT_DIR . '/' . $artifactId . '.json';
    }

    /** @return array<int,string> the operator's effective permission codes */
    private function effectivePermissions(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT permission_code FROM v_user_permissions_effective WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return array_values(array_filter(array_map(
            static fn ($value) => is_string($value) ? $value : null,
            $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []
        )));
    }

    private function assertBounds(string $automationId, array $input): void
    {
        if ($automationId === AutomationRegistry::EXAM_DOCUMENT_PREVIEW) {
            $filename = strtolower(basename((string) ($input['filename'] ?? '')));
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $encoded = $input['content_base64'] ?? null;
            if (!in_array($extension, ['pdf', 'csv', 'xlsx', 'ods'], true) || !is_string($encoded)) {
                throw new RuntimeException('Choose a PDF, CSV, XLSX, or ODS exam document.', 422);
            }
            if (strlen($encoded) > 5592408 || base64_decode($encoded, true) === false || strlen((string) base64_decode($encoded, true)) > 4194304) {
                throw new RuntimeException('Exam documents must be smaller than 4 MB.', 422);
            }
            if (!in_array(($input['document_kind'] ?? ''), ['timetable', 'results'], true)) {
                throw new RuntimeException('Choose whether this is a timetable or results document.', 422);
            }
            return;
        }
        $rows = $input['rows'] ?? [];
        $columns = $input['columns'] ?? [];
        if (!is_array($rows) || !is_array($columns)) {
            throw new RuntimeException('rows and columns must be arrays.', 422);
        }
        if (count($columns) < 1 || count($columns) > AutomationRegistry::MAX_COLUMNS) {
            throw new RuntimeException('columns out of bounds.', 422);
        }
        if (count($rows) > AutomationRegistry::MAX_ROWS) {
            throw new RuntimeException('rows out of bounds.', 422);
        }
    }

    private function storeInput(string $artifactId, string $automationId, array $input): void
    {
        $input['_automation_id'] = $automationId;
        $json = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('The automation input could not be encoded.');
        }
        if (strlen($json) > self::MAX_INPUT_JSON_BYTES) {
            throw new RuntimeException('Automation input exceeds the 6 MB processing limit.', 413);
        }
        $this->write($this->inputPath($artifactId), $json);
    }

    /** @return array<string,mixed> */
    private function loadInput(string $artifactId): array
    {
        $path = $this->inputPath($artifactId);
        $raw = is_file($path) ? (string) file_get_contents($path) : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new RuntimeException('The automation input is no longer available.');
        }
        return $decoded;
    }

    /** @return array<string,mixed> */
    private function storeOutput(string $artifactId, string $automationId, int $userId, array $result, string $content, bool $removeInput = true): array
    {
        $format = strtolower((string) ($result['format'] ?? 'csv'));
        $extension = $format === 'json' ? 'json' : 'csv';
        $path = $this->root() . '/' . self::OUTPUT_DIR . '/' . $artifactId . '.' . $extension;
        $this->write($path, $content);

        $descriptor = [
            'artifact_id' => $artifactId,
            'automation' => $automationId,
            'operator_id' => $userId,
            'format' => $format,
            'filename' => $artifactId . '.' . $extension,
            'row_count' => (int) ($result['row_count'] ?? 0),
            'column_count' => (int) ($result['column_count'] ?? 0),
            'bytes' => strlen($content),
            'checksum_sha256' => hash('sha256', $content),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => $automationId === AutomationRegistry::EXAM_DOCUMENT_PREVIEW ? gmdate('Y-m-d H:i:s', time() + 3600) : null,
        ];
        $this->write(
            $this->root() . '/' . self::OUTPUT_DIR . '/' . $artifactId . '.meta.json',
            json_encode($descriptor, JSON_UNESCAPED_SLASHES)
        );
        // The input was a scaffold; remove it so storage stays bounded.
        if ($removeInput) @unlink($this->inputPath($artifactId));

        return $descriptor;
    }

    /** @return array<string,mixed>|null */
    public function artifactStatus(string $artifactId): ?array
    {
        $path = $this->root() . '/' . self::OUTPUT_DIR . '/' . $artifactId . '.meta.json';
        if (!is_file($path)) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($path), true);
        if (is_array($meta) && !empty($meta['expires_at']) && strtotime((string)$meta['expires_at']) <= time()) {
            $format = strtolower((string)($meta['format'] ?? 'csv'));
            @unlink($this->outputPath($artifactId, $format));
            @unlink($path);
            return null;
        }
        return is_array($meta) ? $meta : null;
    }

    public function outputPath(string $artifactId, string $format): string
    {
        $extension = $format === 'json' ? 'json' : 'csv';
        return $this->root() . '/' . self::OUTPUT_DIR . '/' . $artifactId . '.' . $extension;
    }

    /** @return array<string,mixed>|null meta for a job whose payload is known */
    public function artifactForJobPayload(array $payload): ?array
    {
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        return preg_match('/^[a-f0-9]{32}$/', $artifactId) ? $this->artifactStatus($artifactId) : null;
    }

    /** Delete stale queued source documents and expired artifacts lazily. */
    private function purgeExpired(): void
    {
        $base = $this->root() . '/storage/automations';
        foreach ([$base . '/in', $base . '/out'] as $directory) {
            if (!is_dir($directory)) continue;
            foreach (glob($directory . '/*') ?: [] as $path) {
                if (!is_file($path)) continue;
                if (str_ends_with($path, '.meta.json')) {
                    $meta = json_decode((string)@file_get_contents($path), true);
                    if (is_array($meta) && ($meta['automation'] ?? '') === AutomationRegistry::EXAM_DOCUMENT_PREVIEW
                        && !empty($meta['expires_at']) && strtotime((string)$meta['expires_at']) <= time()) {
                        $id = (string)($meta['artifact_id'] ?? '');
                        if (preg_match('/^[a-f0-9]{32}$/', $id)) @unlink($this->outputPath($id, (string)($meta['format'] ?? 'csv')));
                        @unlink($path);
                        continue;
                    }
                }
                if (str_ends_with($directory, '/in')) {
                    $input = json_decode((string)@file_get_contents($path), true);
                    $mtime = filemtime($path);
                    if (is_array($input) && ($input['_automation_id'] ?? '') === AutomationRegistry::EXAM_DOCUMENT_PREVIEW
                        && $mtime !== false && $mtime < time() - 3600) @unlink($path);
                    elseif ($mtime !== false && $mtime < time() - 86400) @unlink($path);
                }
            }
        }
    }

    private function root(): string
    {
        if ($this->storageRoot !== null) {
            return rtrim($this->storageRoot, '/');
        }
        return dirname(__DIR__, 3);
    }

    private function write(string $path, string $contents): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('The automation storage directory could not be prepared.');
        }
        if (@file_put_contents($path, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The automation artifact could not be stored.');
        }
        // The queue's writer (web) and the next reader (CLI) may be different
        // users on shared hosting, so artifacts must be world-readable. They
        // are still protected by storage/.htaccess from direct HTTP access.
        @chmod($path, 0644);
    }
}
