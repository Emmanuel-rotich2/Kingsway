<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use DomainException;

/** Public-only FAQ assistant; it never receives authenticated school data. */
final class PublicAiAssistantService
{
    public const WORKFLOW = 'public.faq_assistant';

    public function __construct(private ?AiCompletionProvider $provider = null) {}

    public function ask(string $question, array $corpus, array $conversation = []): array
    {
        $question = trim($question);
        if ($question === '' || mb_strlen($question) > 500) {
            throw new DomainException('A valid question of up to 500 characters is required.', 422);
        }
        // Greetings are conversational intent, not a knowledge lookup. Handle
        // them deterministically so "hi" receives a natural one-line reply
        // instead of an unnecessarily long school overview from the model.
        if (preg_match('/^(?:hi|hello|hey|hiya|good\s+(?:morning|afternoon|evening)|howdy|thanks?|thank\s+you)[!.?,\s]*$/i', $question) === 1) {
            FileLogger::write('ai_generation', [
                'type' => 'public_assistant_greeting',
                'question_hash' => hash('sha256', $question),
            ]);
            return [
                'status' => 'answered',
                'title' => 'Hello',
                'body' => 'Hello! How can I help you with Kingsway Preparatory School?',
                'next_steps' => [],
                'suggested_questions' => [],
                'sources' => [],
                'human_referral' => null,
            ];
        }
        $safeCorpus = [];
        foreach (array_slice($corpus, 0, 40) as $source) {
            if (!is_array($source) || trim((string) ($source['id'] ?? '')) === '' || trim((string) ($source['content'] ?? '')) === '') continue;
            $safeCorpus[] = [
                'id' => mb_substr(trim((string) $source['id']), 0, 100),
                'title' => mb_substr(trim((string) ($source['title'] ?? '')), 0, 200),
                'content' => mb_substr(trim((string) $source['content']), 0, 4000),
            ];
        }
        if ($safeCorpus === []) {
            return ['status' => 'escalate', 'reason' => 'no_approved_public_source', 'message' => 'Please contact the school assistant for help.'];
        }
        $safeConversation = [];
        foreach (array_slice($conversation, -6) as $turn) {
            if (!is_array($turn)) continue;
            $role = (string) ($turn['role'] ?? '');
            $content = trim((string) ($turn['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') continue;
            $safeConversation[] = ['role' => $role, 'content' => mb_substr($content, 0, 300)];
        }
        $input = AiPromptPolicy::minimize(self::WORKFLOW, [
            'question' => $question,
            'audience' => 'public_visitor',
            'corpus' => json_encode($safeCorpus, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'conversation' => json_encode($safeConversation, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $template = (new AiPromptTemplateService())->resolve(self::WORKFLOW);
        $result = ($this->provider ?: new AiProviderGateway())->complete([
            ['role' => 'system', 'content' => $template['content']],
            ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ], [
            // Public FAQ is a strict application contract. Explicit JSON mode
            // prevents a provider's prose/markdown fallback from becoming a
            // user-facing 503 after the bounded repair attempt.
            'response_format' => 'json_object',
            'temperature' => 0.1,
            'max_tokens' => 1000,
        ]);
        if (!is_string($result['title'] ?? null) || !is_string($result['body'] ?? null)) {
            throw new DomainException('The public assistant returned an invalid answer.', 502);
        }
        $allowed = array_fill_keys(array_column($safeCorpus, 'id'), true);
        $sources = [];
        foreach ((array) ($result['sources'] ?? []) as $source) {
            $source = trim((string) $source);
            if ($source !== '' && isset($allowed[$source])) $sources[] = $source;
        }
        if ($sources === []) {
            return ['status' => 'escalate', 'reason' => 'answer_without_verified_source', 'message' => 'Please contact the school assistant so the question can be verified.'];
        }
        $escalate = filter_var($result['escalation_required'] ?? false, FILTER_VALIDATE_BOOLEAN);
        FileLogger::write('ai_generation', ['type' => 'public_assistant_answered', 'question_hash' => hash('sha256', $question), 'result_hash' => hash('sha256', json_encode($result)), 'source_count' => count($sources), 'escalated' => $escalate]);
        return [
            'status' => $escalate ? 'escalate' : 'answered',
            'title' => mb_substr(trim($result['title']), 0, 200),
            'body' => mb_substr(trim($result['body']), 0, 5000),
            'next_steps' => array_values(array_filter(array_map(
                static fn($value): string => mb_substr(trim((string) $value), 0, 300),
                array_slice((array) ($result['next_steps'] ?? []), 0, 5)
            ), static fn(string $value): bool => $value !== '')),
            'suggested_questions' => array_values(array_filter(array_map(
                static fn($value): string => mb_substr(trim((string) $value), 0, 140),
                array_slice((array) ($result['suggested_questions'] ?? []), 0, 3)
            ), static fn(string $value): bool => $value !== '')),
            'sources' => $sources,
            'human_referral' => $escalate ? 'Please contact the school assistant for confirmation or further help.' : null,
        ];
    }
}
