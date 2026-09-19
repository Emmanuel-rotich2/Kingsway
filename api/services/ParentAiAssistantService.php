<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Modules\parent\ParentPortalManager;
use App\API\Includes\FileLogger;
use DomainException;

/** Read-only, linked-family assistant for the parent portal. */
final class ParentAiAssistantService
{
    public const WORKFLOW = 'communications.parent_portal_assistant';

    public function __construct(private ?AiCompletionProvider $provider = null) {}

    public function ask(ParentPortalManager $portal, int $parentId, string $question): array
    {
        (new AiWorkflowService())->authorize(self::WORKFLOW, [
            'user_id' => $parentId,
            'permissions' => [],
            'audience' => 'parent',
        ]);
        $question = trim($question);
        if ($parentId < 1 || $question === '' || mb_strlen($question) > 500) {
            throw new DomainException('A valid question of up to 500 characters is required.', 422);
        }

        $dashboard = $portal->getDashboard();
        $children = $dashboard['data']['children'] ?? [];
        if (!is_array($children) || $children === []) {
            return ['status' => 'cannot_answer', 'reason' => 'no_linked_children', 'message' => 'No linked learner is available in this family account.'];
        }

        $summaries = [];
        foreach (array_slice($children, 0, 10) as $index => $child) {
            $id = (int) ($child['id'] ?? 0);
            if ($id < 1) continue;
            $fees = $portal->getFeeBalance($id);
            $attendance = $portal->getStudentAttendance($id);
            $feeData = is_array($fees['data'] ?? null) ? $fees['data'] : [];
            $attData = is_array($attendance['data'] ?? null) ? $attendance['data'] : [];
            $summary = is_array($attData['summary'] ?? null) ? $attData['summary'] : [];
            $summaries[] = [
                'child' => 'linked_child_' . ($index + 1),
                'class' => (string) ($child['class_name'] ?? ''),
                'current_balance' => (string) ($child['current_balance'] ?? 0),
                'total_balance' => (string) ($feeData['total_balance'] ?? 0),
                'attendance_total_days' => (string) ($summary['total_days'] ?? 0),
                'attendance_present_days' => (string) ($summary['days_present'] ?? 0),
                'attendance_absent_days' => (string) ($summary['days_absent'] ?? 0),
                'attendance_percentage' => (string) ($attData['percentage'] ?? 0),
            ];
        }
        $input = AiPromptPolicy::minimize('communications.parent_portal_assistant', [
            'question' => $question,
            'audience' => 'authenticated_parent',
            'context' => json_encode(['linked_children' => $summaries], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $template = (new AiPromptTemplateService())->resolve('communications.parent_portal_assistant');
        $result = ($this->provider ?: new AiProviderGateway())->complete([
            ['role' => 'system', 'content' => $template['content']],
            ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ]);
        if (!isset($result['title'], $result['body']) || !is_string($result['title']) || !is_string($result['body'])) {
            throw new DomainException('The assistant returned an invalid answer.', 502);
        }
        $next = is_array($result['next_steps'] ?? null) ? $result['next_steps'] : [];
        FileLogger::write('ai_generation', ['type' => 'parent_assistant_answered', 'parent_id' => $parentId, 'question_hash' => hash('sha256', $question), 'result_hash' => hash('sha256', json_encode($result))]);
        return ['status' => 'answered', 'title' => mb_substr(trim($result['title']), 0, 200), 'body' => mb_substr(trim($result['body']), 0, 5000), 'next_steps' => array_values(array_map(static fn($v): string => mb_substr(trim((string) $v), 0, 300), array_slice($next, 0, 5)))];
    }
}
