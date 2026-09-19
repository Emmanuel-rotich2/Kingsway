<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use PDO;
use DomainException;

/** Generates and persists reviewable drafts for approved domain adapters. */
class AiDraftService
{
    /** @var AiCompletionProvider */
    private $provider;

    /** @var AiPromptTemplateService */
    private $templates;

    public function __construct(?AiCompletionProvider $provider = null, ?AiPromptTemplateService $templates = null)
    {
        $this->provider = $provider ?: new AiProviderGateway();
        $this->templates = $templates ?: new AiPromptTemplateService();
    }

    public function create(PDO $pdo, string $workflowId, array $context, array $input, array $metadata = []): array
    {
        $workflow = (new AiWorkflowService())->authorize($workflowId, $context);
        $clean = AiPromptPolicy::minimize($workflowId, $input);
        $prompt = $this->templates->resolve($workflowId);
        $messages = [
            ['role' => 'system', 'content' => $prompt['content']],
            ['role' => 'user', 'content' => json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];
        $draft = $this->provider->complete($messages);
        $validated = $this->validateDraft($draft, $workflowId, $clean);
        $inputHash = hash('sha256', json_encode($clean));
        $stmt = $pdo->prepare(
            'INSERT INTO ai_workflow_drafts
                (workflow_id, operator_id, subject_type, subject_id, metadata_json, input_hash, draft_json, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'pending_approval\', DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))'
        );
        $stmt->execute([
            $workflowId,
            (int) ($context['user_id'] ?? 0),
            (string) ($metadata['subject_type'] ?? ''),
            (int) ($metadata['subject_id'] ?? 0),
            json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $inputHash,
            json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $id = (int) $pdo->lastInsertId();
        $sessionId = (int) ($metadata['session_id'] ?? 0);
        if ($sessionId > 0) {
            $assistantMessage = trim((string) ($validated['summary'] ?? ''));
            if ($assistantMessage === '') {
                $assistantMessage = json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $messageStmt = $pdo->prepare(
                "INSERT INTO ai_timetable_planning_messages
                    (session_id, actor_type, actor_user_id, message_text, ai_draft_id)
                 VALUES (?, 'assistant', ?, ?, ?)"
            );
            $messageStmt->execute([
                $sessionId,
                (int) ($context['user_id'] ?? 0),
                substr($assistantMessage, 0, 10000),
                $id,
            ]);
        }
        FileLogger::write('ai_generation', [
            'type' => 'draft_created',
            'workflow_id' => $workflowId,
            'draft_id' => $id,
            'operator_id' => (int) ($context['user_id'] ?? 0),
            'subject_type' => (string) ($metadata['subject_type'] ?? ''),
            'subject_id' => (int) ($metadata['subject_id'] ?? 0),
            'input_hash' => $inputHash,
            'result_hash' => hash('sha256', json_encode($validated)),
            'prompt_version' => $prompt['version'],
            'prompt_hash' => hash('sha256', $prompt['content']),
            'status' => 'pending_approval',
        ]);
        return ['draft_id' => $id, 'workflow_id' => $workflowId, 'status' => 'pending_approval', 'draft' => $validated];
    }

    public function queue(string $workflowId, array $context, array $input, array $metadata = []): array
    {
        $workflow = (new AiWorkflowService())->authorize($workflowId, $context);
        $clean = AiPromptPolicy::minimize($workflowId, $input);
        $inputHash = hash('sha256', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $operatorId = (int) ($context['user_id'] ?? 0);
        $idempotencyKey = 'ai-draft:' . hash('sha256', $workflowId . ':' . $operatorId . ':' . $inputHash);
        $jobId = JobQueue::push('ai.workflow.draft', [
            'workflow_id' => $workflowId,
            'input' => $clean,
            'user_id' => $operatorId,
            'permissions' => array_values(array_map('strval', (array) ($context['permissions'] ?? []))),
            'request_id' => (string) ($context['request_id'] ?? ''),
            'idempotency_key' => $idempotencyKey,
            'metadata' => [
                'subject_type' => (string) ($metadata['subject_type'] ?? ''),
                'subject_id' => (int) ($metadata['subject_id'] ?? 0),
                'channel' => (string) ($metadata['channel'] ?? ''),
                'audience' => (string) ($metadata['audience'] ?? ''),
                'scope' => (string) ($metadata['scope'] ?? ''),
                'provider' => (string) ($metadata['provider'] ?? ''),
                'amount_band' => (string) ($metadata['amount_band'] ?? ''),
                'currency' => (string) ($metadata['currency'] ?? ''),
                'reconciliation_rule' => (string) ($metadata['reconciliation_rule'] ?? ''),
                'report_code' => (string) ($metadata['report_code'] ?? ''),
                'session_id' => (int) ($metadata['session_id'] ?? 0),
            ],
        ], 0, 3, 30);
        FileLogger::write('ai_generation', [
            'type' => 'draft_queued',
            'workflow_id' => $workflowId,
            'job_id' => $jobId,
            'operator_id' => $operatorId,
            'subject_type' => (string) ($metadata['subject_type'] ?? ''),
            'subject_id' => (int) ($metadata['subject_id'] ?? 0),
            'input_hash' => $inputHash,
            'idempotency_key' => $idempotencyKey,
        ]);
        return ['job_id' => $jobId, 'workflow_id' => $workflowId, 'status' => 'queued', 'action_level' => $workflow['action_level']];
    }

    public function approve(PDO $pdo, int $draftId, int $approverId, ?string $expectedWorkflow = null): array
    {
        if ($draftId < 1 || $approverId < 1) {
            throw new DomainException('A valid draft and approver are required.', 422);
        }
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM ai_workflow_drafts WHERE id = ? AND expires_at > UTC_TIMESTAMP() FOR UPDATE');
            $stmt->execute([$draftId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || (int) ($row['operator_id'] ?? 0) === $approverId) {
                throw new DomainException('Draft is missing, expired, or belongs to the approving operator.', 409);
            }
            if ($expectedWorkflow !== null && (string) ($row['workflow_id'] ?? '') !== $expectedWorkflow) {
                throw new DomainException('This draft does not belong to the requested AI workflow.', 409);
            }
            if (($row['status'] ?? '') === 'pending_approval') {
                $update = $pdo->prepare(
                    "UPDATE ai_workflow_drafts
                     SET status = 'approved', approved_by = ?, approved_at = UTC_TIMESTAMP()
                     WHERE id = ? AND status = 'pending_approval'"
                );
                $update->execute([$approverId, $draftId]);
            } elseif (($row['status'] ?? '') !== 'approved') {
                throw new DomainException('Draft is no longer available for approval.', 409);
            }
            if ($startedTransaction) $pdo->commit();
            FileLogger::write('ai_generation', [
                'type' => 'draft_approved',
                'draft_id' => $draftId,
                'approver_id' => $approverId,
            ]);
            $decodedDraft = json_decode((string) ($row['draft_json'] ?? ''), true);
            $decodedMetadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
            return [
                'draft_id' => $draftId,
                'status' => 'approved',
                'workflow_id' => (string) ($row['workflow_id'] ?? ''),
                'operator_id' => (int) ($row['operator_id'] ?? 0),
                'draft' => is_array($decodedDraft) ? $decodedDraft : [],
                'metadata' => is_array($decodedMetadata) ? $decodedMetadata : [],
            ];
        } catch (\Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markMaterialized(PDO $pdo, int $draftId, int $communicationId): void
    {
        if ($draftId < 1 || $communicationId < 1) {
            throw new DomainException('A valid AI draft and communication are required.', 422);
        }
        $stmt = $pdo->prepare('SELECT metadata_json FROM ai_workflow_drafts WHERE id = ? LIMIT 1');
        $stmt->execute([$draftId]);
        $metadata = json_decode((string) $stmt->fetchColumn(), true);
        if (!is_array($metadata)) $metadata = [];
        if (!empty($metadata['materialized_communication_id'])) return;
        $metadata['materialized_communication_id'] = $communicationId;
        $pdo->prepare('UPDATE ai_workflow_drafts SET metadata_json = ? WHERE id = ?')
            ->execute([json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $draftId]);
    }

    public function listForReview(PDO $pdo, int $userId, bool $approver = false, ?string $workflowPrefix = null): array
    {
        if ($userId < 1) {
            throw new DomainException('A valid authenticated user is required to list AI drafts.', 401);
        }
        $sql = 'SELECT id, workflow_id, operator_id, subject_type, subject_id, metadata_json, draft_json, status, expires_at, created_at
                FROM ai_workflow_drafts WHERE expires_at > UTC_TIMESTAMP()';
        $params = [];
        if ($workflowPrefix !== null && $workflowPrefix !== '') {
            $sql .= ' AND workflow_id LIKE ?';
            $params[] = rtrim($workflowPrefix, '.') . '.%';
        }
        if (!$approver) {
            $sql .= ' AND operator_id = ?';
            $params[] = $userId;
        } else {
            $sql .= " AND status = 'pending_approval' AND operator_id <> ?";
            $params[] = $userId;
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $decoded = json_decode((string) $row['draft_json'], true);
            $row['draft'] = is_array($decoded) ? $decoded : [];
            $metadata = json_decode((string) ($row['metadata_json'] ?? ''), true);
            $row['metadata'] = is_array($metadata) ? $metadata : [];
            unset($row['metadata_json']);
            unset($row['draft_json']);
        }
        return $rows;
    }

    private function validateDraft(array $draft, string $workflowId = '', array $input = []): array
    {
        foreach (['title', 'body', 'next_steps'] as $field) {
            if (!array_key_exists($field, $draft)) {
                throw new DomainException('AI response is missing a required draft field.', 502);
            }
        }
        if ($workflowId === 'academics.assessment_draft' && is_string($draft['next_steps'])) {
            // Some approved assessment models emit one concise next step as a
            // scalar despite the JSON contract. Normalize only this known
            // workflow shape; unrelated workflows remain fail-closed.
            $draft['next_steps'] = [$draft['next_steps']];
        }
        if (!is_string($draft['title']) || !is_string($draft['body']) || !is_array($draft['next_steps'])) {
            throw new DomainException('AI response failed the draft contract.', 502);
        }
        $result = [
            'title' => mb_substr(trim($draft['title']), 0, 200),
            'body' => mb_substr(trim($draft['body']), 0, 10000),
            'next_steps' => array_values(array_map(static fn($item): string => mb_substr(trim((string) $item), 0, 500), array_slice($draft['next_steps'], 0, 10))),
        ];
        if (isset($draft['items']) && is_array($draft['items'])) {
            $result['items'] = array_values(array_filter(array_map(static function ($item): ?array {
                if (!is_array($item) || trim((string) ($item['question'] ?? '')) === '') return null;
                return ['question' => mb_substr(trim((string) $item['question']), 0, 1000), 'type' => mb_substr(trim((string) ($item['type'] ?? 'short_answer')), 0, 80), 'marks' => max(0, (int) ($item['marks'] ?? 0))];
            }, array_slice($draft['items'], 0, 20))));
        }
        if ($workflowId === 'academics.timetable_planning') {
            foreach (['questions', 'suggestions', 'unresolved_constraints'] as $field) {
                if (!isset($draft[$field])) continue;
                if (!is_array($draft[$field])) throw new DomainException('AI timetable response contains an invalid list field.', 502);
                $result[$field] = array_values(array_map(
                    static fn($item): string => mb_substr(trim((string)$item), 0, 500),
                    array_slice($draft[$field], 0, 20)
                ));
            }
            if (isset($draft['assignments'])) {
                if (!is_array($draft['assignments'])) throw new DomainException('AI timetable assignments must be a list.', 502);
                $result['assignments'] = TimetableAssignmentDraftValidator::normalize($draft['assignments']);
                $candidateKeys = [];
                foreach ((array) ($input['assignment_candidates'] ?? []) as $candidate) {
                    if (!is_array($candidate)) continue;
                    $candidateKeys[implode(':', [
                        (int) ($candidate['academic_year_class_stream_id'] ?? 0),
                        (int) ($candidate['learning_area_id'] ?? 0),
                        (int) ($candidate['teacher_id'] ?? 0),
                        (int) ($candidate['day_of_week'] ?? 0),
                        (int) ($candidate['time_slot_id'] ?? 0),
                    ])] = true;
                }
                if ($candidateKeys !== []) {
                    foreach ($result['assignments'] as $assignment) {
                        $key = implode(':', [
                            (int) ($assignment['academic_year_class_stream_id'] ?? 0),
                            (int) ($assignment['learning_area_id'] ?? 0),
                            (int) ($assignment['teacher_id'] ?? 0),
                            (int) ($assignment['day_of_week'] ?? 0),
                            (int) ($assignment['time_slot_id'] ?? 0),
                        ]);
                        if (!isset($candidateKeys[$key])) {
                            throw new DomainException('AI timetable assignments must use only supplied authorized candidates.', 502);
                        }
                    }
                }
            }
        }
        return $result;
    }
}
