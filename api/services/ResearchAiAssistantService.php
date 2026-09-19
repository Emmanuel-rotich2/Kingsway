<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use DomainException;

/** Answers from an approved source snapshot; it never fetches user URLs. */
final class ResearchAiAssistantService
{
    public const WORKFLOW = 'research.external_knowledge';

    public function __construct(private ?AiCompletionProvider $provider = null) {}

    public function ask(string $question, array $sources): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > 1000) {
            throw new DomainException('A valid research question of up to 1000 characters is required.', 422);
        }
        $safe = [];
        foreach (array_slice($sources, 0, 20) as $source) {
            if (!is_array($source)) continue;
            $id = trim((string) ($source['id'] ?? ''));
            $content = trim((string) ($source['content'] ?? ''));
            $url = trim((string) ($source['url'] ?? ''));
            if ($id === '' || $content === '' || !preg_match('#^https://#i', $url)) continue;
            $safe[] = ['id' => mb_substr($id, 0, 100), 'title' => mb_substr(trim((string) ($source['title'] ?? '')), 0, 200), 'date' => mb_substr(trim((string) ($source['date'] ?? '')), 0, 30), 'url' => mb_substr($url, 0, 500), 'content' => mb_substr($content, 0, 4500)];
        }
        if ($safe === []) return ['status' => 'escalate', 'reason' => 'no_approved_source', 'message' => 'No approved research source is available.'];
        $input = AiPromptPolicy::minimize(self::WORKFLOW, ['question' => $question, 'audience' => 'authorized_staff', 'sources' => json_encode($safe, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        $template = (new AiPromptTemplateService())->resolve(self::WORKFLOW);
        $result = ($this->provider ?: new AiProviderGateway())->complete([
            ['role' => 'system', 'content' => $template['content']],
            ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ]);
        if (!is_string($result['title'] ?? null) || !is_string($result['body'] ?? null)) throw new DomainException('The research assistant returned an invalid answer.', 502);
        $allowed = array_fill_keys(array_column($safe, 'id'), true);
        $citations = array_values(array_filter(array_map('strval', (array) ($result['sources'] ?? [])), static fn(string $id): bool => isset($allowed[$id])));
        $escalate = filter_var($result['escalation_required'] ?? false, FILTER_VALIDATE_BOOLEAN) || $citations === [];
        FileLogger::write('ai_generation', ['type' => 'research_assistant_answered', 'question_hash' => hash('sha256', $question), 'source_count' => count($citations), 'escalated' => $escalate]);
        return ['status' => $escalate ? 'escalate' : 'answered', 'title' => mb_substr(trim($result['title']), 0, 200), 'body' => mb_substr(trim($result['body']), 0, 6000), 'facts' => array_slice(array_map('strval', (array) ($result['facts'] ?? [])), 0, 10), 'inferences' => array_slice(array_map('strval', (array) ($result['inferences'] ?? [])), 0, 10), 'next_steps' => array_slice(array_map('strval', (array) ($result['next_steps'] ?? [])), 0, 5), 'sources' => $citations, 'human_referral' => $escalate ? 'Verify this with the relevant school or official source before acting.' : null];
    }
}
