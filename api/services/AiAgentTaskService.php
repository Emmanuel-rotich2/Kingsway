<?php

declare(strict_types=1);

namespace App\API\Services;

use DomainException;
use PDO;

/** Durable, user-owned task facade over the canonical PHP JobQueue. */
final class AiAgentTaskService
{
    public const JOB_TYPE = 'ai.agent.task';

    public function create(int $userId, array $permissions, array $data, string $requestId): array
    {
        $question = trim((string) ($data['question'] ?? ''));
        $tool = trim((string) ($data['tool'] ?? 'assistant'));
        if ($userId < 1 || $question === '' || mb_strlen($question) > 500) {
            throw new DomainException('A question of up to 500 characters is required.', 422);
        }
        if (mb_strlen($tool) > 100 || preg_match('/^[a-zA-Z0-9_.:-]+$/', $tool) !== 1) {
            throw new DomainException('The requested assistant capability is invalid.', 422);
        }

        $permissionHint = AiAgentService::permissionHint($permissions, 60);
        $requestId = mb_substr(trim($requestId), 0, 100);
        $idempotencyKey = 'ai-task:' . hash('sha256', $userId . ':' . ($requestId !== '' ? $requestId : bin2hex(random_bytes(16))));
        $payload = [
            'user_id' => $userId,
            'permissions' => $permissionHint['permissions'],
            'mode' => 'assist',
            'task' => true,
            'tool' => $tool,
            'question' => $question,
            'route' => mb_substr(trim((string) ($data['route'] ?? 'dashboard')), 0, 120),
            'module' => mb_substr(trim((string) ($data['module'] ?? 'dashboard')), 0, 60),
            'session_id' => mb_substr(trim((string) ($data['session_id'] ?? '')), 0, 64),
            'assistant_domain' => in_array('system_admin', $permissions, true) ? 'system_admin' : 'staff',
            'request_id' => $requestId,
            'idempotency_key' => $idempotencyKey,
        ];
        $activeId = JobQueue::findActiveByTypeAndPayload(self::JOB_TYPE, 'idempotency_key', $idempotencyKey);
        if ($activeId !== null) {
            $existing = JobQueue::fetchJob($activeId);
            if ($existing !== null) return $this->publicTask($existing);
        }
        $id = JobQueue::push(self::JOB_TYPE, $payload, 0, 2, 20, JobQueue::PRIORITY_NORMAL);
        return $this->publicTask(JobQueue::fetchJob($id) ?? ['id' => $id, 'status' => JobQueue::STATUS_PENDING, 'payload' => $payload]);
    }

    /** Execute in the existing worker. Permissions are freshly resolved here. */
    public function execute(PDO $pdo, array $payload): void
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $jobId = (int) ($payload['_job_id'] ?? 0);
        if ($userId < 1 || $jobId < 1 || trim((string) ($payload['question'] ?? '')) === '') {
            throw new DomainException('The assistant task is incomplete.', 422);
        }
        $currentJob = JobQueue::fetchJob($jobId);
        if (is_array($currentJob['payload']['task_result'] ?? null)) {
            // If execution completed but the worker lost its lease before
            // acknowledging the queue row, do not repeat model/tool work.
            return;
        }

        $agent = new AiAgentService();
        $permissions = $agent->resolveEffectivePermissions($pdo, $userId);
        if ($permissions === []) {
            throw new DomainException('The task owner has no currently resolved permissions.', 403);
        }
        $domain = in_array('system_admin', $permissions, true) ? 'system_admin' : 'staff';
        $context = [
            'user_id' => $userId,
            'permissions' => $permissions,
            'effective_permissions' => $permissions,
            'audience' => 'staff',
            'assistant_domain' => $domain,
            'system_admin' => $domain === 'system_admin',
            'route' => (string) ($payload['route'] ?? ''),
            'module' => (string) ($payload['module'] ?? 'dashboard'),
            'session_id' => (string) ($payload['session_id'] ?? ''),
            'request_id' => (string) ($payload['request_id'] ?? 'ai-agent-task'),
        ];

        $bridge = new AiPythonBridge();
        $result = $bridge->available()
            ? $bridge->assist($context, (string) $payload['question'])
            : $agent->assist($pdo, $context, (string) $payload['question']);

        JobQueue::updateMeta($jobId, ['task_result' => $result]);
    }

    public function get(int $taskId, int $userId): ?array
    {
        $job = JobQueue::fetchJob($taskId);
        if ($job === null || ($job['job_type'] ?? '') !== self::JOB_TYPE || (int) ($job['payload']['user_id'] ?? 0) !== $userId) {
            return null;
        }
        return $this->publicTask($job);
    }

    public function list(int $userId, int $limit = 30): array
    {
        return array_map(fn(array $job): array => $this->publicTask($job), JobQueue::listOwnedByType(self::JOB_TYPE, $userId, $limit));
    }

    public function cancel(int $taskId, int $userId): bool
    {
        if ($this->get($taskId, $userId) === null) return false;
        return JobQueue::cancelJob($taskId);
    }

    private function publicTask(array $job): array
    {
        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $status = match ((string) ($job['status'] ?? 'pending')) {
            JobQueue::STATUS_DONE => 'completed',
            JobQueue::STATUS_FAILED => 'failed',
            JobQueue::STATUS_CANCELLED => 'cancelled',
            JobQueue::STATUS_PROCESSING => 'processing',
            default => 'queued',
        };
        return [
            'task_id' => (string) ($job['id'] ?? ''),
            'status' => $status,
            'progress' => $status === 'completed' ? 100 : ($status === 'processing' ? 50 : 0),
            'message' => match ($status) {
                'completed' => 'Completed',
                'failed' => 'The assistant could not complete this task.',
                'cancelled' => 'Cancelled',
                'processing' => 'Working on your request',
                default => 'Queued for the assistant',
            },
            'result' => $status === 'completed' && is_array($payload['task_result'] ?? null) ? $payload['task_result'] : null,
            'error' => $status === 'failed' ? 'The assistant could not complete this task.' : null,
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
        ];
    }
}
