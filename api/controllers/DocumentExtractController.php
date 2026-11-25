<?php

declare(strict_types=1);

namespace App\API\Controllers;

use App\API\Services\DocumentExtractionService;
use App\API\Services\JobQueue;

/**
 * Private read boundary for document extraction artifacts.
 *
 * Upload responses carry the job and artifact ids. Staff can poll the job or
 * read the checksum-verified result here; the parsed values remain review-only
 * and are never saved into school records by this controller.
 */
class DocumentExtractController extends BaseController
{
    private function extractions(): DocumentExtractionService
    {
        return $this->contract(DocumentExtractionService::class);
    }

    /** GET /api/document-extract/status/{jobId} */
    public function getStatus($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $jobId = (int) ($id ?? 0);
        $job = $jobId > 0 ? JobQueue::fetchJob($jobId) : null;
        if ($job === null || ($job['job_type'] ?? '') !== DocumentExtractionService::JOB_TYPE) {
            return $this->notFound('Unknown document extraction job');
        }
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $artifactId = (string) ($payload['artifact_id'] ?? '');
        $descriptor = $this->extractions()->descriptor($artifactId);
        if ($descriptor === null) {
            return $this->notFound('Document extraction artifact is unavailable');
        }
        if (!$this->mayView($descriptor)) {
            return $this->forbidden('You are not permitted to view this extraction.');
        }

        return $this->success([
            'job_id' => $jobId,
            'job_status' => (string) ($job['status'] ?? 'unknown'),
            'extraction' => $this->publicDescriptor($descriptor),
        ], 'Document extraction status');
    }

    /** GET /api/document-extract/result/{artifactId} */
    public function getResult($id = null, $data = [], $segments = [])
    {
        if (!$this->user) {
            return $this->unauthorized('Authentication required');
        }
        $artifactId = strtolower(trim((string) ($id ?? '')));
        $descriptor = $this->extractions()->descriptor($artifactId);
        if ($descriptor === null) {
            return $this->notFound('Unknown document extraction artifact');
        }
        if (!$this->mayView($descriptor)) {
            return $this->forbidden('You are not permitted to view this extraction.');
        }
        $result = $this->extractions()->result($artifactId);
        if ($result === null) {
            return $this->conflict('The extraction result is not ready or failed its integrity check.');
        }

        return $this->success([
            'extraction' => $this->publicDescriptor($descriptor),
            'result' => $result,
        ], 'Review the extracted values before saving them anywhere.');
    }

    /** @param array<string,mixed> $descriptor */
    private function mayView(array $descriptor): bool
    {
        $operatorId = (int) ($descriptor['operator_id'] ?? 0);
        if ($operatorId === (int) $this->getUserId()) {
            return true;
        }
        return $this->userHasAny(
            ['academic_manage', 'students_manage', 'staff_manage', 'system_configure'],
            [1, 4],
            ['system administrator', 'school administrator']
        );
    }

    /** @param array<string,mixed> $descriptor @return array<string,mixed> */
    private function publicDescriptor(array $descriptor): array
    {
        return [
            'artifact_id' => (string) ($descriptor['artifact_id'] ?? ''),
            'status' => (string) ($descriptor['status'] ?? ''),
            'extraction_status' => (string) ($descriptor['extraction_status'] ?? 'uploaded'),
            'filename' => (string) ($descriptor['filename'] ?? ''),
            'category' => (string) ($descriptor['category'] ?? ''),
            'context' => (string) ($descriptor['context'] ?? ''),
            'row_count' => (int) ($descriptor['row_count'] ?? 0),
            'field_count' => (int) ($descriptor['field_count'] ?? 0),
            'checksum_sha256' => (string) ($descriptor['checksum_sha256'] ?? ''),
            'expected_type' => (string) ($descriptor['expected_type'] ?? ''),
            'verification_decision' => (string) ($descriptor['verification_decision'] ?? ''),
            'verification_confidence' => (float) ($descriptor['verification_confidence'] ?? 0),
            'verification_reasons' => is_array($descriptor['verification_reasons'] ?? null)
                ? array_values($descriptor['verification_reasons'])
                : [],
            'notice' => $descriptor['notice'] ?? null,
            'preview_only' => true,
            'requires_staff_confirmation' => true,
        ];
    }
}
