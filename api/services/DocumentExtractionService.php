<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PHP-owned extraction boundary for every managed upload.
 *
 * UploadService stages permitted bytes here after a successful upload. Only
 * metadata travels in the queue; the source bytes stay in a private 0600
 * artifact input until the worker sends them to the pinned Python extractor.
 * The parsed result is stored as a checksummed artifact and exposed only to
 * its uploader (or an administrator) through DocumentExtractController.
 *
 * Extraction is review-only: it never writes learner, staff, admission, exam
 * or finance records by itself. Staff must confirm the proposed fields/rows.
 */
class DocumentExtractionService
{
    public const JOB_TYPE = 'documents.extract';

    private const MAX_SOURCE_BYTES = 8 * 1024 * 1024;
    private const MAX_RESULT_BYTES = 4 * 1024 * 1024;
    private const MAX_ROWS = 5000;
    private const INPUT_TTL_SECONDS = 3600;

    /** @var list<string> */
    private const EXTRACTABLE_EXTENSIONS = [
        'pdf', 'docx', 'doc', 'xls', 'xlsx', 'ods', 'csv', 'tsv',
        'txt', 'md', 'jpg', 'jpeg', 'png', 'webp',
    ];

    /** @var array<string,string> */
    private const CATEGORY_PROFILES = [
        'student_document' => 'student',
        'admission_document' => 'admission',
        'staff_document' => 'staff',
        'career_cv' => 'staff',
        'academic_assessment' => 'results',
        'exam_paper' => 'results',
        'school_document' => 'generic',
        'teaching_material' => 'generic',
        'communication_attachment' => 'generic',
        'import_file' => 'generic',
    ];

    /** @var list<string> */
    private const PHOTO_ONLY_CATEGORIES = [
        'website_image', 'website_video', 'student_photo',
        'staff_photo', 'uniform_catalog_image',
    ];

    private PythonDocumentBridge $bridge;
    private string $storageRoot;

    public function __construct(?PythonDocumentBridge $bridge = null, ?string $storageRoot = null)
    {
        $this->bridge = $bridge ?? new PythonDocumentBridge();
        $this->storageRoot = rtrim($storageRoot ?? StorageRoot::path('php/document_extractions'), '/\\');
    }

    /**
     * Stage a successful upload for extraction. UploadService catches any
     * staging failure so a valid upload is never rolled back; the descriptor
     * returned there degrades to "needs_manual_entry".
     *
     * @param array<int,string> $permissions
     * @return array<string,mixed>|null
     */
    public function enqueue(
        string $absolutePath,
        string $category,
        string $context,
        string $originalName,
        ?int $uploaderId = null,
        array $permissions = [],
        string $requestId = '',
        ?string $expectedDocumentType = null
    ): ?array {
        if (!$this->isExtractable($absolutePath, $category, $context)) {
            return null;
        }

        $expectedType = strtolower(trim(str_replace(
            [' ', '-'],
            '_',
            (string) ($expectedDocumentType ?? '')
        )));
        $size = filesize($absolutePath);
        if ($size === false || $size <= 0 || $size > self::MAX_SOURCE_BYTES) {
            return $this->manualEntryDescriptor(
                $absolutePath,
                $category,
                $context,
                $originalName,
                $uploaderId,
                'The document exceeds the 8 MB extraction limit.',
                $expectedType
            );
        }

        $userId = $uploaderId ?? self::currentUserId();
        $permissions = array_values(array_filter(array_map('strval', $permissions)));
        if ($permissions === []) {
            $permissions = self::currentPermissions();
        }

        $fingerprint = hash('sha256', implode('|', [
            realpath($absolutePath) ?: $absolutePath,
            (string) $size,
            (string) (filemtime($absolutePath) ?: 0),
            basename($originalName),
            $category,
            $context,
            $expectedType,
        ]));
        $artifactId = substr($fingerprint, 0, 32);

        $existing = $this->descriptor($artifactId);
        if (is_array($existing) && !empty($existing['job_id'])) {
            return $this->publicDescriptor($existing);
        }

        $content = (string) file_get_contents($absolutePath);
        if ($content === '') {
            return $this->manualEntryDescriptor(
                $absolutePath,
                $category,
                $context,
                $originalName,
                $uploaderId,
                'The uploaded document is empty.',
                $expectedType
            );
        }

        $profile = $this->profileFor($category, $context);
        $input = [
            '_document_extraction_id' => $artifactId,
            'artifact_id' => $artifactId,
            'filename' => basename($originalName),
            'category' => $category,
            'context' => $context,
            'profile' => $profile,
            'expected_type' => $expectedType,
            'operator_id' => $userId,
            'permissions' => $permissions,
            'request_id' => substr($requestId, 0, 100),
            'content_base64' => base64_encode($content),
        ];

        $descriptor = [
            'artifact_id' => $artifactId,
            'operator_id' => $userId,
            'filename' => basename($originalName),
            'category' => $category,
            'context' => $context,
            'profile' => $profile,
            'expected_type' => $expectedType,
            'source_bytes' => $size,
            'source_sha256' => hash('sha256', $content),
            'status' => 'queued',
            'extraction_status' => 'uploaded',
            'preview_only' => true,
            'requires_staff_confirmation' => true,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        try {
            $this->write($this->inputPath($artifactId), (string) json_encode(
                $input,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ));
            $jobId = JobQueue::push(self::JOB_TYPE, [
                'artifact_id' => $artifactId,
                'operator_id' => $userId,
                'category' => $category,
                'context' => $context,
                'filename' => basename($originalName),
                'expected_type' => $expectedType,
                'idempotency_key' => 'docex:' . $userId . ':' . $artifactId,
            ]);
            $descriptor['job_id'] = $jobId;
            $this->writeMeta($artifactId, $descriptor);
        } catch (Throwable $error) {
            $descriptor['status'] = 'failed';
            $descriptor['extraction_status'] = 'failed';
            $descriptor['notice'] = 'The document could not be queued for extraction.';
            $this->writeMeta($artifactId, $descriptor);
            FileLogger::write('document_extraction', [
                'type' => 'document_extract_enqueue_failed',
                'artifact_id' => $artifactId,
                'operator_id' => $userId,
                'error_class' => $error::class,
            ]);
            throw $error;
        }

        FileLogger::write('document_extraction', [
            'type' => 'document_extract_queued',
            'artifact_id' => $artifactId,
            'job_id' => $jobId,
            'operator_id' => $userId,
            'category' => $category,
            'context' => $context,
            'profile' => $profile,
            'expected_type' => $expectedType,
            'source_bytes' => $size,
        ]);

        return $this->publicDescriptor($descriptor);
    }

    /**
     * Worker entry point. Failures are recorded as artifacts instead of
     * retrying forever: the upload itself has already succeeded.
     *
     * @return array<string,mixed>
     */
    public function process(array $payload, PDO $pdo): array
    {
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $artifactId) !== 1) {
            throw new RuntimeException('Malformed document extraction job payload.');
        }

        $descriptor = $this->descriptor($artifactId) ?? [
            'artifact_id' => $artifactId,
            'operator_id' => (int) ($payload['operator_id'] ?? 0),
            'status' => 'failed',
            'extraction_status' => 'failed',
        ];
        $jobId = (int) ($payload['_job_id'] ?? ($descriptor['job_id'] ?? 0));

        try {
            $inputPath = $this->inputPath($artifactId);
            $mtime = is_file($inputPath) ? filemtime($inputPath) : false;
            if ($mtime === false || $mtime < time() - self::INPUT_TTL_SECONDS) {
                @unlink($inputPath);
                throw new RuntimeException('The staged document expired before extraction.');
            }

            $input = json_decode((string) file_get_contents($inputPath), true);
            if (!is_array($input) || !is_string($input['content_base64'] ?? null)) {
                throw new RuntimeException('The staged document input is unavailable.');
            }

            $operatorId = (int) ($input['operator_id'] ?? 0);
            if ($operatorId > 0) {
                $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([$operatorId]);
                if (($stmt->fetchColumn() ?: '') !== 'active') {
                    throw new RuntimeException('The uploader account is no longer active.');
                }
            }

            $raw = $this->bridge->extractUploadedDocument([
                'artifact_id' => $artifactId,
                'filename' => (string) ($input['filename'] ?? 'document'),
                'category' => (string) ($input['category'] ?? ''),
                'context' => (string) ($input['context'] ?? ''),
                'profile' => (string) ($input['profile'] ?? 'generic'),
                'expected_type' => (string) ($input['expected_type'] ?? ''),
                'content_base64' => (string) $input['content_base64'],
            ]);

            $result = $this->normaliseResult($artifactId, $input, $raw);
            $content = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($content) || strlen($content) > self::MAX_RESULT_BYTES) {
                $result['text'] = substr((string) ($result['text'] ?? ''), 0, 256 * 1024);
                $result['rows'] = array_slice((array) ($result['rows'] ?? []), 0, 500);
                $content = (string) json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            if (!is_string($content) || $content === '' || strlen($content) > self::MAX_RESULT_BYTES) {
                throw new RuntimeException('The extracted document exceeded the storage limit.');
            }

            $empty = ($result['row_count'] ?? 0) < 1 && ($result['fields'] ?? []) === [];
            $descriptor = array_merge($descriptor, [
                'status' => 'done',
                'extraction_status' => $empty ? 'needs_manual_entry' : 'parsed',
                'extraction_method' => (string) ($result['extraction_method'] ?? ''),
                'row_count' => (int) ($result['row_count'] ?? 0),
                'field_count' => count((array) ($result['fields'] ?? [])),
                'confidence' => (float) ($result['confidence'] ?? 0),
                'verification_decision' => (string) ($result['verification']['decision'] ?? ''),
                'verification_confidence' => (float) ($result['verification']['confidence'] ?? 0),
                'verification_reasons' => is_array($result['verification']['reasons'] ?? null)
                    ? array_values(array_slice($result['verification']['reasons'], 0, 4))
                    : [],
                'checksum_sha256' => hash('sha256', $content),
                'bytes' => strlen($content),
                'completed_at' => gmdate('Y-m-d H:i:s'),
                'notice' => $empty ? 'No structured fields were found; staff must enter the data manually.' : null,
            ]);
            $this->write($this->outputPath($artifactId), $content);
            $this->writeMeta($artifactId, $descriptor);
            @unlink($inputPath);

            FileLogger::write('document_extraction', [
                'type' => 'document_extract_completed',
                'artifact_id' => $artifactId,
                'job_id' => $jobId,
                'operator_id' => (int) ($descriptor['operator_id'] ?? 0),
                'method' => (string) ($descriptor['extraction_method'] ?? ''),
                'row_count' => (int) ($descriptor['row_count'] ?? 0),
                'field_count' => (int) ($descriptor['field_count'] ?? 0),
            ]);
        } catch (Throwable $error) {
            $descriptor['status'] = 'failed';
            $descriptor['extraction_status'] = 'failed';
            $descriptor['notice'] = 'The document could not be read automatically. Staff must enter the data manually.';
            if (($descriptor['expected_type'] ?? '') !== '') {
                $descriptor['verification_decision'] = 'needs_review';
                $descriptor['verification_confidence'] = 0.0;
                $descriptor['verification_reasons'] = [$descriptor['notice']];
            }
            $descriptor['failed_at'] = gmdate('Y-m-d H:i:s');
            $this->writeMeta($artifactId, $descriptor);
            FileLogger::write('document_extraction', [
                'type' => 'document_extract_failed',
                'artifact_id' => $artifactId,
                'job_id' => $jobId,
                'operator_id' => (int) ($descriptor['operator_id'] ?? 0),
                'error_class' => $error::class,
            ]);
        }

        if ($jobId > 0) {
            JobQueue::updateMeta($jobId, [
                'extraction_artifact_id' => $artifactId,
                'extraction_status' => (string) ($descriptor['extraction_status'] ?? 'failed'),
            ]);
        }

        return $this->publicDescriptor($descriptor);
    }

    /** @return array<string,mixed>|null */
    public function descriptor(string $artifactId): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $artifactId) !== 1) {
            return null;
        }
        $path = $this->metaPath($artifactId);
        if (!is_file($path)) {
            return null;
        }
        $meta = json_decode((string) file_get_contents($path), true);
        return is_array($meta) ? $meta : null;
    }

    /** @return array<string,mixed>|null checksum-verified result */
    public function result(string $artifactId): ?array
    {
        $meta = $this->descriptor($artifactId);
        if ($meta === null || ($meta['status'] ?? '') !== 'done') {
            return null;
        }
        $path = $this->outputPath($artifactId);
        if (!is_file($path)) {
            return null;
        }
        $content = (string) file_get_contents($path);
        if (!hash_equals((string) ($meta['checksum_sha256'] ?? ''), hash('sha256', $content))) {
            FileLogger::write('document_extraction', [
                'type' => 'document_extract_checksum_mismatch',
                'artifact_id' => $artifactId,
            ]);
            return null;
        }
        $result = json_decode($content, true);
        return is_array($result) ? $result : null;
    }

    /** @return array<string,mixed> */
    private function normaliseResult(string $artifactId, array $input, array $raw): array
    {
        $text = (string) ($raw['text'] ?? '');
        if (strlen($text) > self::MAX_RESULT_BYTES) {
            $text = substr($text, 0, self::MAX_RESULT_BYTES);
        }
        $rows = is_array($raw['rows'] ?? null) ? array_values($raw['rows']) : [];
        $rows = array_slice($rows, 0, self::MAX_ROWS);
        $fields = is_array($raw['fields'] ?? null)
            ? array_slice($raw['fields'], 0, 100)
            : [];
        $classification = is_array($raw['classification'] ?? null) ? $raw['classification'] : [];
        $verification = is_array($raw['verification'] ?? null) ? $raw['verification'] : [];
        $verification = [
            'expected_type' => substr((string) ($verification['expected_type'] ?? ($input['expected_type'] ?? '')), 0, 60),
            'expected_label' => substr((string) ($verification['expected_label'] ?? ''), 0, 120),
            'detected_type' => substr((string) ($verification['detected_type'] ?? ''), 0, 60),
            'detected_label' => substr((string) ($verification['detected_label'] ?? ''), 0, 120),
            'decision' => substr((string) ($verification['decision'] ?? ''), 0, 30),
            'confidence' => max(0.0, min(1.0, (float) ($verification['confidence'] ?? 0))),
            'identity_subtype' => substr((string) ($verification['identity_subtype'] ?? ''), 0, 40),
            'reasons' => is_array($verification['reasons'] ?? null)
                ? array_values(array_slice(array_map(
                    static fn ($reason) => substr((string) $reason, 0, 240),
                    $verification['reasons']
                ), 0, 4))
                : [],
            'requires_human_review' => !empty($verification['requires_human_review']),
            'auto_reject' => !empty($verification['auto_reject']),
        ];

        return [
            'artifact_id' => $artifactId,
            'filename' => basename((string) ($input['filename'] ?? 'document')),
            'category' => (string) ($input['category'] ?? ''),
            'context' => (string) ($input['context'] ?? ''),
            'profile' => (string) ($input['profile'] ?? 'generic'),
            'extraction_method' => substr((string) ($raw['extraction_method'] ?? ''), 0, 60),
            'headers' => is_array($raw['headers'] ?? null) ? array_slice($raw['headers'], 0, 40) : [],
            'suggested_mapping' => is_array($raw['suggested_mapping'] ?? null) ? $raw['suggested_mapping'] : [],
            'rows' => $rows,
            'row_count' => count($rows),
            'text' => $text,
            'fields' => $fields,
            'classification' => $classification,
            'verification' => $verification,
            'confidence' => max(0.0, min(1.0, (float) ($raw['confidence'] ?? 0))),
            'preview_only' => true,
            'requires_staff_confirmation' => true,
            'stored_at' => gmdate('Y-m-d H:i:s'),
        ];
    }

    private function isExtractable(string $absolutePath, string $category, string $context): bool
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return false;
        }
        if (in_array($category, self::PHOTO_ONLY_CATEGORIES, true)) {
            return false;
        }
        $photoContexts = ['gallery', 'profile', 'profile_picture', 'student_photo', 'staff_photo', 'uniform', 'website_image'];
        $lowerContext = strtolower($context);
        foreach ($photoContexts as $photoContext) {
            if (str_contains($lowerContext, $photoContext)) {
                return false;
            }
        }
        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        return in_array($extension, self::EXTRACTABLE_EXTENSIONS, true);
    }

    private function profileFor(string $category, string $context): string
    {
        $lowerContext = strtolower($context);
        if (str_contains($lowerContext, 'health') || str_contains($lowerContext, 'medical')) {
            return 'health';
        }
        if (str_contains($lowerContext, 'admission')) {
            return 'admission';
        }
        if (str_contains($lowerContext, 'staff')) {
            return 'staff';
        }
        if (str_contains($lowerContext, 'student') || str_contains($lowerContext, 'learner')) {
            return 'student';
        }
        return self::CATEGORY_PROFILES[$category] ?? 'generic';
    }

    /**
     * @return array<string,mixed>
     */
    private function manualEntryDescriptor(
        string $absolutePath,
        string $category,
        string $context,
        string $originalName,
        ?int $uploaderId,
        string $notice,
        string $expectedType = ''
    ): array {
        $artifactId = substr(hash('sha256', implode('|', [
            realpath($absolutePath) ?: $absolutePath,
            (string) (filemtime($absolutePath) ?: 0),
            basename($originalName),
            $category,
            $context,
            $expectedType,
        ])), 0, 32);
        $descriptor = [
            'artifact_id' => $artifactId,
            'operator_id' => $uploaderId ?? self::currentUserId(),
            'filename' => basename($originalName),
            'category' => $category,
            'context' => $context,
            'expected_type' => $expectedType,
            'status' => 'done',
            'extraction_status' => 'needs_manual_entry',
            'verification_decision' => $expectedType !== '' ? 'needs_review' : '',
            'verification_confidence' => 0.0,
            'verification_reasons' => $expectedType !== '' ? [$notice] : [],
            'notice' => $notice,
            'preview_only' => true,
            'requires_staff_confirmation' => true,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];
        $this->writeMeta($artifactId, $descriptor);
        return $this->publicDescriptor($descriptor);
    }

    /** @param array<string,mixed> $descriptor @return array<string,mixed> */
    private function publicDescriptor(array $descriptor): array
    {
        return [
            'extraction_artifact_id' => (string) ($descriptor['artifact_id'] ?? ''),
            'extraction_job_id' => isset($descriptor['job_id']) ? (int) $descriptor['job_id'] : null,
            'extraction_status' => (string) ($descriptor['extraction_status'] ?? 'uploaded'),
            'extraction_state' => (string) ($descriptor['status'] ?? 'queued'),
            'expected_type' => (string) ($descriptor['expected_type'] ?? ''),
            'verification_decision' => (string) ($descriptor['verification_decision'] ?? ''),
            'verification_confidence' => (float) ($descriptor['verification_confidence'] ?? 0),
            'verification_reasons' => is_array($descriptor['verification_reasons'] ?? null)
                ? array_values($descriptor['verification_reasons'])
                : [],
            'requires_staff_confirmation' => true,
            'preview_only' => true,
        ];
    }

    private function inputPath(string $artifactId): string
    {
        return $this->storageRoot . '/in/' . $artifactId . '.json';
    }

    private function outputPath(string $artifactId): string
    {
        return $this->storageRoot . '/out/' . $artifactId . '.json';
    }

    private function metaPath(string $artifactId): string
    {
        return $this->storageRoot . '/out/' . $artifactId . '.meta.json';
    }

    private function writeMeta(string $artifactId, array $descriptor): void
    {
        $this->write($this->metaPath($artifactId), (string) json_encode(
            $descriptor,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));
    }

    private function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('The document extraction storage directory could not be prepared.');
        }
        $temporary = $path . '.tmp.' . bin2hex(random_bytes(8));
        if (@file_put_contents($temporary, $contents, LOCK_EX) === false) {
            throw new RuntimeException('The document extraction artifact could not be stored.');
        }
        @chmod($temporary, 0600);
        if (!@rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('The document extraction artifact could not be finalized.');
        }
        @chmod($path, 0600);
    }

    private static function currentUserId(): int
    {
        return (int) ($_SERVER['auth_user']['user_id']
            ?? $_SERVER['auth_user']['id']
            ?? $_REQUEST['user']['id']
            ?? 0);
    }

    /** @return array<int,string> */
    private static function currentPermissions(): array
    {
        $permissions = $_SERVER['auth_user']['effective_permissions'] ?? [];
        if (!is_array($permissions)) {
            return [];
        }
        return array_values(array_filter(array_map('strval', $permissions)));
    }
}
