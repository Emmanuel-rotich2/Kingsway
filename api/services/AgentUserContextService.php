<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;

class AgentUserContextService
{
    private const MAX_RECENT = 10;
    private const MAX_TEXT = 500;

    private function baseDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/ai_context';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        return $dir;
    }

    private function pathFor(int $userId, int $roleId): string
    {
        $safeUser = max(0, $userId);
        $safeRole = max(0, $roleId);
        return $this->baseDir() . '/user_' . $safeUser . '_role_' . $safeRole . '.json';
    }

    private function redactSensitive(string $text): string
    {
        if ($text === '') return '';
        $patterns = [
            '/(?i)\b(bearer\s+[a-z0-9\-\._~\+\/]+=*)\b/' => '[REDACTED_TOKEN]',
            '/(?i)\b(password|passwd|pwd|secret|api[_-]?key|token|csrf|session[_-]?id)\s*[:=]\s*[^\s,;]+/' => '$1=[REDACTED]',
            '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i' => '[REDACTED_EMAIL]',
            '/\b(?:\+?\d[\d\s\-()]{7,}\d)\b/' => '[REDACTED_PHONE]',
        ];
        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }
        return $text;
    }

    private function sanitizeText($value, int $max = self::MAX_TEXT): string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return '';
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = $this->redactSensitive($text);
        if (mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max);
        }
        return $text;
    }

    private function sanitizeRecent(array $recent): array
    {
        $out = [];
        foreach (array_slice($recent, -self::MAX_RECENT) as $item) {
            if (!is_array($item)) continue;
            $route = $this->sanitizeText($item['route'] ?? '', 120);
            $q = $this->sanitizeText($item['q'] ?? $item['prompt'] ?? '', 160);
            $at = isset($item['at']) && is_numeric($item['at']) ? (int) $item['at'] : time();
            if ($route === '' && $q === '') continue;
            $out[] = ['route' => $route, 'q' => $q, 'at' => $at];
        }
        return array_slice($out, -self::MAX_RECENT);
    }

    public function get(int $userId, int $roleId): array
    {
        $path = $this->pathFor($userId, $roleId);
        if (!is_file($path)) return [];
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') return [];
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        $decoded['recent'] = $this->sanitizeRecent(is_array($decoded['recent'] ?? null) ? $decoded['recent'] : []);
        $decoded['last_route'] = $this->sanitizeText($decoded['last_route'] ?? '', 120);
        $decoded['last_prompt'] = $this->sanitizeText($decoded['last_prompt'] ?? '', 200);
        $decoded['last_answer'] = $this->sanitizeText($decoded['last_answer'] ?? '', 400);
        return $decoded;
    }

    public function merge(int $userId, int $roleId, array $incoming, array $meta = []): array
    {
        $current = $this->get($userId, $roleId);
        $recent = is_array($current['recent'] ?? null) ? $current['recent'] : [];
        $incomingRecent = is_array($incoming['recent'] ?? null) ? $incoming['recent'] : [];
        foreach ($incomingRecent as $item) {
            $recent[] = $item;
        }
        $metaRoute = $this->sanitizeText($meta['route'] ?? '', 120);
        $metaPrompt = $this->sanitizeText($meta['prompt'] ?? '', 200);
        $metaAnswer = $this->sanitizeText($meta['answer'] ?? '', 400);
        $merged = [
            'last_route' => $metaRoute !== '' ? $metaRoute : $this->sanitizeText($incoming['last_route'] ?? $current['last_route'] ?? '', 120),
            'last_prompt' => $metaPrompt !== '' ? $metaPrompt : $this->sanitizeText($incoming['last_prompt'] ?? $current['last_prompt'] ?? '', 200),
            'last_answer' => $metaAnswer !== '' ? $metaAnswer : $this->sanitizeText($incoming['last_answer'] ?? $current['last_answer'] ?? '', 400),
            'recent' => $this->sanitizeRecent($recent),
            'updated_at' => time(),
        ];
        $path = $this->pathFor($userId, $roleId);
        @file_put_contents($path, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($path, 0660);
        FileLogger::write('ai_generation', [
            'event' => 'ai_user_context_saved',
            'user_id' => $userId,
            'role_id' => $roleId,
            'route' => $merged['last_route'],
            'recent_count' => count($merged['recent']),
        ], 'info');
        return $merged;
    }

    public function clear(int $userId, int $roleId): bool
    {
        $path = $this->pathFor($userId, $roleId);
        $existed = is_file($path);
        if ($existed) @unlink($path);
        FileLogger::write('ai_generation', [
            'event' => 'ai_user_context_cleared',
            'user_id' => $userId,
            'role_id' => $roleId,
            'existed' => $existed,
        ], 'info');
        return true;
    }
}
