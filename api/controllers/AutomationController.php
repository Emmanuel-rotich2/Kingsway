<?php

declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\automations\AutomationArtifacts;
use App\API\Services\AutomationRegistry;
use App\API\Services\JobQueue;
use App\API\Services\Logger;
use Throwable;

/**
 * Staff HTTP boundary for governed, non-AI automations run by the Python
 * engine.
 *
 * The controller validates the request shape, re-checks the operator's export
 * permission against the registry row, and pushes the job. The queue worker
 * re-resolves permissions at run time and only then contacts the engine, so
 * authz is never lost in the queue. All writes are artifact files plus file
 * journals — never log tables.
 */
class AutomationController extends BaseController
{
    private const JOB_TYPE = 'automation.run';

    private function artifacts(): AutomationArtifacts
    {
        return $this->contract(AutomationArtifacts::class);
    }

    /** GET /api/automation/catalogue — the automations this operator may run. */
    public function getCatalogue($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $available = array_values(array_filter(
            AutomationRegistry::catalogue(),
            fn (array $automation) => $this->userHasPermission((string) ($automation['permission'] ?? ''))
        ));

        return $this->success(['automations' => $available], 'Automation catalogue');
    }

    /**
     * POST /api/automation/enqueue {automation, format, columns, rows, title?}
     * — enqueue a bounded, already-authorized dataset for engine processing.
     */
    public function postEnqueue($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $userId = (int) $this->getUserId();
        if ($userId < 1) {
            return $this->unauthorized('A valid session is required');
        }

        $automationId = trim((string) ($data['automation'] ?? ''));
        if ($automationId === '') {
            return $this->badRequest('Choose the automation to run');
        }

        $input = [
            'format' => (string) ($data['format'] ?? 'csv'),
            'columns' => is_array($data['columns'] ?? null) ? array_values($data['columns']) : [],
            'rows' => is_array($data['rows'] ?? null) ? array_values($data['rows']) : [],
            'title' => substr(trim((string) ($data['title'] ?? 'export')), 0, 120),
        ];

        try {
            $result = $this->artifacts()->enqueue(
                $userId,
                $this->effectiveUserPermissions(),
                $automationId,
                $input,
                (string) ($this->requestId ?? '')
            );
        } catch (\RuntimeException $e) {
            $code = (int) $e->getCode();
            if ($code === 403) {
                return $this->respond(null, 'You are not permitted to run this automation', 403, false);
            }
            if ($code === 404) {
                return $this->respond(null, 'Unknown automation', 404, false);
            }
            return $this->badRequest($e->getMessage());
        } catch (Throwable $e) {
            Logger::legacyError('[AutomationController] enqueue failed: ' . $e->getMessage());
            return $this->serverError('The automation could not be queued');
        }

        return $this->success($result, 'Automation queued');
    }

    /**
     * Effective permissions of the authenticated caller, safe for both user
     * representations the base controller may hydrate (object or array).
     *
     * @return array<int,string>
     */
    private function effectiveUserPermissions(): array
    {
        if (is_object($this->user)) {
            $permissions = $this->user->permissions ?? [];
        } elseif (is_array($this->user)) {
            $permissions = $this->user['permissions'] ?? [];
        } else {
            $permissions = [];
        }
        return array_values(array_map('strval', (array) $permissions));
    }

    /** POST multipart /api/automation/exam-document-preview: private, review-only import extraction. */
    public function postExamDocumentPreview($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        if (!$this->userHasAny(['academic_manage'], [1, 4], ['system administrator', 'school administrator'])) {
            return $this->forbidden('Only the School Administrator may prepare an exam-document import.');
        }
        $file = $_FILES['file'] ?? null;
        $kind = strtolower(trim((string)($_POST['document_kind'] ?? $data['document_kind'] ?? '')));
        if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $this->badRequest('Choose a timetable or results file to preview.');
        if (!in_array($kind, ['timetable', 'results'], true)) return $this->badRequest('Choose timetable or results as the document type.');

        $storedPath = null;
        $permissions = $this->effectiveUserPermissions();
        try {
            $stored = $this->uploadManaged($file, 'import_file', ['subdirectory' => 'exam_preview', 'prefix' => 'exam_document']);
            $storedPath = (string)($stored['absolute_path'] ?? '');
            if ($storedPath === '' || !is_file($storedPath)) throw new \RuntimeException('The uploaded document could not be stored.');
            $bytes = filesize($storedPath);
            if ($bytes === false || $bytes > 4 * 1024 * 1024) throw new \RuntimeException('Exam documents must be smaller than 4 MB.', 422);
            $content = file_get_contents($storedPath);
            if (!is_string($content) || $content === '') throw new \RuntimeException('The uploaded document is empty.', 422);
            $result = $this->artifacts()->enqueue(
                (int)$this->getUserId(),
                $permissions,
                AutomationRegistry::EXAM_DOCUMENT_PREVIEW,
                ['filename' => basename((string)($file['name'] ?? 'document')), 'document_kind' => $kind, 'content_base64' => base64_encode($content)],
                (string)($this->requestId ?? '')
            );
            return $this->accepted($result, 'Document preview queued. It will not change an exam or learner result.');
        } catch (\RuntimeException $error) {
            $status = (int)$error->getCode();
            return $this->respond(null, $error->getMessage(), in_array($status, [403, 404, 409, 422], true) ? $status : 422, false);
        } catch (Throwable $error) {
            Logger::legacyError('[AutomationController] exam document preview enqueue failed: ' . $error->getMessage());
            return $this->serverError('The document could not be queued for preview.');
        } finally {
            if ($storedPath !== null && is_file($storedPath)) $this->deleteManagedFile($storedPath);
        }
    }

    /**
     * GET /api/automation/status/{jobId} — poll for job completion and the
     * artifact descriptor (no content; download separately).
     */
    public function getStatus($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $jobId = (int) ($id ?? 0);
        if ($jobId < 1) {
            return $this->badRequest('A job id is required');
        }

        $job = JobQueue::fetchJob($jobId);
        if ($job === null || ($job['job_type'] ?? '') !== self::JOB_TYPE) {
            return $this->notFound('Unknown automation job');
        }

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        if ((int) ($payload['user_id'] ?? 0) !== (int) $this->getUserId()) {
            return $this->respond(null, 'You are not permitted to view this automation', 403, false);
        }
        $artifact = ($job['status'] ?? '') === JobQueue::STATUS_DONE
            ? $this->artifacts()->artifactForJobPayload($payload)
            : null;
        $currentUserId = (int) $this->getUserId();
        if ($artifact !== null && (int) ($artifact['operator_id'] ?? 0) !== $currentUserId) {
            return $this->respond(null, 'You are not permitted to view this automation', 403, false);
        }

        return $this->success([
            'job_id' => $jobId,
            'status' => (string) ($job['status'] ?? 'unknown'),
            'attempts' => (int) ($job['attempts'] ?? 0),
            'artifact' => $artifact === null ? null : [
                'id' => (string) ($artifact['artifact_id'] ?? ''),
                'format' => (string) ($artifact['format'] ?? ''),
                'filename' => (string) ($artifact['filename'] ?? ''),
                'bytes' => (int) ($artifact['bytes'] ?? 0),
                'checksum_sha256' => (string) ($artifact['checksum_sha256'] ?? ''),
                'created_at' => (string) ($artifact['created_at'] ?? ''),
            ],
        ], 'Automation status');
    }

    /**
     * GET /api/automation/artifact/{jobId} — download the computed artifact
     * once the operator has been verified against the artifact descriptor.
     */
    public function getArtifact($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $jobId = (int) ($id ?? 0);
        if ($jobId < 1) {
            return $this->badRequest('A job id is required');
        }

        $job = JobQueue::fetchJob($jobId);
        if ($job === null || ($job['job_type'] ?? '') !== self::JOB_TYPE) {
            return $this->notFound('Unknown automation job');
        }
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        if ((int) ($payload['user_id'] ?? 0) !== (int) $this->getUserId()) {
            return $this->respond(null, 'You are not permitted to download this artifact', 403, false);
        }
        if (($job['status'] ?? '') !== JobQueue::STATUS_DONE) {
            return $this->respond(null, 'The artifact is not ready yet', 409, false);
        }
        $meta = $this->artifacts()->artifactForJobPayload($payload);
        if ($meta === null) {
            return $this->respond(null, 'The artifact is not ready yet', 409, false);
        }
        if ((int) ($meta['operator_id'] ?? 0) !== (int) $this->getUserId()) {
            return $this->respond(null, 'You are not permitted to download this artifact', 403, false);
        }

        $format = strtolower((string) ($meta['format'] ?? 'csv'));
        $path = $this->artifacts()->outputPath((string) ($meta['artifact_id'] ?? ''), $format);
        if (!is_file($path)) {
            return $this->respond(null, 'The artifact content is no longer available', 410, false);
        }

        $content = (string) file_get_contents($path);
        if ($content === '') {
            return $this->serverError('The artifact could not be read');
        }
        // Verify the checksum before delivery; a tampered artifact must never
        // be delivered just because it still exists.
        if (!hash_equals((string) ($meta['checksum_sha256'] ?? ''), hash('sha256', $content))) {
            Logger::legacyError('[AutomationController] artifact checksum mismatch for job ' . $jobId);
            return $this->serverError('The artifact failed integrity verification');
        }

        http_response_code(200);
        header('Content-Type: ' . ($format === 'json' ? 'application/json; charset=utf-8' : 'text/csv; charset=utf-8'));
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', (string) ($meta['filename'] ?? 'export')) . '"');
        header('X-Checksum-SHA256: ' . (string) ($meta['checksum_sha256'] ?? ''));
        header('X-Content-Type-Options: nosniff');
        echo $content;
        return null;
    }

    /** GET /api/automation/exam-document-preview/{jobId}: checksum-verified JSON preview for its owner. */
    public function getExamDocumentPreview($id = null, $data = [], $segments = [])
    {
        if (!$this->user) return $this->unauthorized('Authentication required');
        $jobId = (int)($id ?? 0);
        $job = $jobId > 0 ? JobQueue::fetchJob($jobId) : null;
        if ($job === null || ($job['job_type'] ?? '') !== self::JOB_TYPE) return $this->notFound('Unknown automation job');
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        if (($payload['automation'] ?? '') !== AutomationRegistry::EXAM_DOCUMENT_PREVIEW) return $this->notFound('Unknown exam-document preview');
        if ((int)($payload['user_id'] ?? 0) !== (int)$this->getUserId()) return $this->forbidden('You are not permitted to view this preview.');
        if (($job['status'] ?? '') !== JobQueue::STATUS_DONE) return $this->success(['status' => (string)($job['status'] ?? 'unknown'), 'preview' => null], 'Preview is still processing.');
        $meta = $this->artifacts()->artifactForJobPayload($payload);
        if ($meta === null) return $this->success(['status' => (string)($job['status'] ?? 'unknown'), 'preview' => null], 'Preview is still processing.');
        $path = $this->artifacts()->outputPath((string)$meta['artifact_id'], 'json');
        $content = is_file($path) ? (string)file_get_contents($path) : '';
        if ($content === '' || !hash_equals((string)($meta['checksum_sha256'] ?? ''), hash('sha256', $content))) return $this->conflict('The preview artifact is unavailable or failed its integrity check.');
        $preview = json_decode($content, true);
        if (!is_array($preview) || empty($preview['preview_only']) || empty($preview['requires_staff_confirmation'])) return $this->serverError('The document preview has an invalid response shape.');
        return $this->success(['status' => (string)($job['status'] ?? 'completed'), 'preview' => $preview], 'Review the extracted rows before using them. Nothing has been saved.');
    }
}
