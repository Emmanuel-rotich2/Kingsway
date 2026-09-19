<?php

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use RuntimeException;

/**
 * Small OpenAI-compatible provider adapter. It is disabled unless explicitly
 * enabled in environment configuration and never logs prompt contents.
 */
class AiProviderClient implements AiCompletionProvider
{
    /** @var callable|null Injectable HTTP transport for hermetic adapter tests. */
    private $transport;
    /** @var array Provider-specific configuration overrides. */
    private array $overrides;
    private ?LocalSqliteBuffer $healthBuffer;

    public function __construct(?callable $transport = null, array $overrides = [], ?LocalSqliteBuffer $healthBuffer = null)
    {
        $this->transport = $transport;
        $this->overrides = $overrides;
        $this->healthBuffer = $healthBuffer;
    }

    public function complete(array $messages, array $options = []): array
    {
        if (!$this->enabled()) {
            throw new AiProviderException('AI assistance is disabled by configuration.');
        }
        if ($messages === []) {
            throw new AiProviderException('AI request requires at least one message.');
        }

        $baseUrl = rtrim((string) ($options['base_url'] ?? $this->overrides['base_url'] ?? Config::get('AI_PROVIDER_BASE_URL', '')), '/');
        $model = (string) ($options['model'] ?? $this->overrides['model'] ?? Config::get('AI_MODEL', ''));
        $apiKey = (string) ($options['api_key'] ?? $this->overrides['api_key'] ?? Config::get('AI_API_KEY', ''));
        if ($baseUrl === '' || $model === '') {
            throw new AiProviderException('AI provider is not fully configured.');
        }
        if (Config::isProduction() && stripos($baseUrl, 'https://') !== 0) {
            throw new AiProviderException('Production AI providers must use HTTPS.');
        }
        if (Config::isProduction() && $apiKey === '') {
            throw new AiProviderException('Production AI provider credentials are missing.');
        }

        $providerKind = $this->providerKind($baseUrl, array_merge($this->overrides, $options));
        $allowVision = filter_var($options['vision_enabled'] ?? Config::get('AI_VISION_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        $cleanMessages = $this->sanitizeMessages($messages, $allowVision);
        $maxTokens = min(4096, max(64, (int) ($options['max_tokens'] ?? Config::get('AI_MAX_TOKENS', 1200))));
        $temperature = isset($options['temperature']) ? (float) $options['temperature'] : 0.2;
        $responseFormat = (string) ($options['response_format'] ?? Config::get('AI_RESPONSE_FORMAT', ''));
        $reasoning = (string) ($options['reasoning_effort'] ?? Config::get('AI_REASONING_EFFORT', ''));
        [$method, $url, $headers, $payload] = $this->buildRequest(
            $providerKind, $baseUrl, $model, $apiKey, $cleanMessages, $maxTokens,
            $temperature, $responseFormat, $reasoning
        );
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new AiProviderException('AI request could not be encoded.');
        }

        $promptHash = hash('sha256', $body);
        $timeout = min(60, max(5, (int) ($options['timeout'] ?? Config::get('AI_TIMEOUT', 25))));
        $started = microtime(true);
        $retryLimit = min(3, max(0, (int)($options['_provider_retries'] ?? Config::get('AI_PROVIDER_RETRIES', 2))));
        $retryDelayMs = min(2000, max(0, (int)($options['_provider_retry_delay_ms'] ?? Config::get('AI_PROVIDER_RETRY_DELAY_MS', 250))));
        $attempt = 0;
        do {
            [$raw, $status, $curlError] = $this->request($url, $method, $headers, $body, $timeout);
            $transient = $curlError !== '' || in_array($status, [408, 425, 429], true) || $status >= 500;
            if (!$transient || $attempt >= $retryLimit) break;
            if ($retryDelayMs > 0) usleep($retryDelayMs * (2 ** $attempt) * 1000);
            $attempt++;
        } while (true);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        // NVIDIA NIM may acknowledge a long-running request with 202 and a
        // requestId. Poll its documented status endpoint with the same bearer
        // credential, but keep the wait bounded for web requests/workers.
        if ($status === 202 && $providerKind === 'nvidia' && is_array($decoded)) {
            $requestId = (string) ($decoded['requestId'] ?? $decoded['request_id'] ?? '');
            if (preg_match('/^[A-Za-z0-9-]{1,36}$/', $requestId) !== 1) {
                throw new AiProviderException('AI provider returned an invalid pending request.');
            }
            for ($attempt = 0; $attempt < 5; $attempt++) {
                usleep(250000);
                [$raw, $status, $curlError] = $this->request($baseUrl . '/v1/status/' . rawurlencode($requestId), 'GET', $headers, '', $timeout);
                $decoded = is_string($raw) ? json_decode($raw, true) : null;
                if ($status !== 202) break;
            }
        }

        FileLogger::write('ai_generation', [
            'type' => 'provider_call',
            'model' => $model,
            'prompt_hash' => $promptHash,
            'http_status' => $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        if ($raw === false || $curlError !== '') {
            throw new AiProviderException('AI provider request failed.');
        }
        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            throw new AiProviderException('AI provider returned an invalid response.');
        }
        $content = $this->responseContent($providerKind, $decoded);
        if (!is_string($content) || trim($content) === '') {
            throw new AiProviderException('AI provider returned no usable content.');
        }
        $json = $this->decodeJsonContent($content);
        if (!is_array($json) && empty($options['_json_repair'])) {
            // A few otherwise valid providers/models ignore JSON mode. Give
            // them one bounded repair attempt while preserving the original
            // governed prompt and never evaluating model output as code.
            array_unshift($messages, [
                'role' => 'system',
                'content' => 'Return only one valid JSON object or array. Do not include markdown, prose, or code fences.',
            ]);
            $options['_json_repair'] = true;
            $options['temperature'] = 0;
            return $this->complete($messages, $options);
        }
        if (!is_array($json)) {
            throw new AiProviderException('AI provider returned non-JSON content.');
        }
        return $json;
    }

    private function decodeJsonContent(string $content): ?array
    {
        $content = trim($content);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) return $decoded;
        // Models frequently wrap otherwise valid structured output in a
        // markdown fence or a short explanatory sentence. Only decode a
        // bounded JSON object/array; never execute or evaluate model text.
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $content) ?? $content;
        $decoded = json_decode(trim($content), true);
        if (is_array($decoded)) return $decoded;
        $startObject = strpos($content, '{');
        $startArray = strpos($content, '[');
        $starts = array_values(array_filter([$startObject, $startArray], static fn ($value): bool => $value !== false));
        if ($starts === []) return null;
        $start = min($starts);
        $candidate = substr($content, $start);
        $decoded = json_decode($candidate, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function providerKind(string $baseUrl, array $options): string
    {
        $kind = strtolower(trim((string) ($options['provider_kind'] ?? Config::get('AI_PROVIDER_KIND', 'generic'))));
        if ($kind !== 'generic') return $kind;
        $host = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        if (str_contains($host, 'anthropic.com')) return 'anthropic';
        if (str_contains($host, 'generativelanguage.googleapis.com') || str_contains($host, 'googleapis.com')) return 'google';
        if (preg_match('/(^|\.)nvidia\.com$/i', $host)) return 'nvidia';
        return 'openai';
    }

    private function buildRequest(string $kind, string $baseUrl, string $model, string $apiKey, array $messages, int $maxTokens, float $temperature, string $responseFormat, string $reasoning): array
    {
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        if ($kind === 'anthropic') {
            if ($apiKey !== '') $headers[] = 'x-api-key: ' . $apiKey;
            $headers[] = 'anthropic-version: 2023-06-01';
            $system = [];
            $chat = [];
            foreach ($messages as $message) {
                if ($message['role'] === 'system') $system[] = $message['content'];
                else $chat[] = ['role' => $message['role'], 'content' => $message['content']];
            }
            $payload = ['model' => $model, 'max_tokens' => $maxTokens, 'messages' => $chat, 'temperature' => $temperature];
            if ($system !== []) $payload['system'] = implode("\n\n", $system);
            return ['POST', $this->joinEndpoint($baseUrl, '/v1/messages'), $headers, $payload];
        }
        if ($kind === 'google') {
            if ($apiKey !== '') $headers[] = 'x-goog-api-key: ' . $apiKey;
            $contents = [];
            $systemParts = [];
            foreach ($messages as $message) {
                $parts = is_array($message['content']) ? $message['content'] : [['type' => 'text', 'text' => $message['content']]];
                $mapped = [];
                foreach ($parts as $part) {
                    if ($part['type'] === 'text') $mapped[] = ['text' => $part['text']];
                    elseif ($part['type'] === 'image_url') $mapped[] = ['fileData' => ['fileUri' => $part['image_url']['url']]];
                }
                if ($message['role'] === 'system') $systemParts = array_merge($systemParts, $mapped);
                else $contents[] = ['role' => $message['role'] === 'assistant' ? 'model' : 'user', 'parts' => $mapped];
            }
            $payload = ['contents' => $contents, 'generationConfig' => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens]];
            if ($systemParts !== []) $payload['systemInstruction'] = ['parts' => $systemParts];
            if ($responseFormat === 'json_object') $payload['generationConfig']['responseMimeType'] = 'application/json';
            return ['POST', $this->joinEndpoint($baseUrl, '/models/' . rawurlencode($model) . ':generateContent'), $headers, $payload];
        }
        $payload = ['model' => $model, 'messages' => $messages, 'temperature' => $temperature, 'max_tokens' => $maxTokens];
        if ($responseFormat === 'json_object') $payload['response_format'] = ['type' => 'json_object'];
        if (in_array($reasoning, ['low', 'high', 'max'], true)) $payload['reasoning_effort'] = $reasoning;
        if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;
        return ['POST', $this->joinEndpoint($baseUrl, '/v1/chat/completions'), $headers, $payload];
    }

    private function joinEndpoint(string $baseUrl, string $path): string
    {
        if (str_ends_with($baseUrl, '/v1') && str_starts_with($path, '/v1/')) {
            return $baseUrl . substr($path, 3);
        }
        if (str_ends_with($baseUrl, '/v1beta') && str_starts_with($path, '/models/')) {
            return $baseUrl . $path;
        }
        return $baseUrl . $path;
    }

    private function responseContent(string $kind, array $decoded): ?string
    {
        if ($kind === 'anthropic') {
            $parts = array_filter((array) ($decoded['content'] ?? []), static fn ($part): bool => is_array($part) && is_string($part['text'] ?? null));
            return $parts === [] ? null : implode('', array_map(static fn (array $part): string => $part['text'], $parts));
        }
        if ($kind === 'google') {
            $parts = (array) ($decoded['candidates'][0]['content']['parts'] ?? []);
            $texts = array_filter($parts, static fn ($part): bool => is_array($part) && is_string($part['text'] ?? null));
            return $texts === [] ? null : implode('', array_map(static fn (array $part): string => $part['text'], $texts));
        }
        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    public function enabled(): bool
    {
        return filter_var(Config::get('AI_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Verify provider reachability and whether a model is advertised.
     * This performs no completion and never returns response bodies or keys.
     * Anthropic does not expose a generally available model-list endpoint, so
     * its result is `reachable` rather than an unverified model assertion.
     */
    public function checkModelAvailability(string $model = ''): array
    {
        $baseUrl = rtrim((string) ($this->overrides['base_url'] ?? Config::get('AI_PROVIDER_BASE_URL', '')), '/');
        $model = trim($model !== '' ? $model : (string) ($this->overrides['model'] ?? Config::get('AI_MODEL', '')));
        $metadata = ['provider_name' => trim((string) ($this->overrides['name'] ?? Config::get('AI_PROVIDER_NAME', 'primary'))) ?: 'primary'];
        if ($baseUrl === '' || $model === '') return $metadata + ['status' => 'misconfigured', 'model' => $model];
        $kind = $this->providerKind($baseUrl, $this->overrides);
        $cacheKey = hash('sha256', $baseUrl . "\0" . $kind . "\0" . $model);
        $cached = $this->readHealthCache($cacheKey);
        if ($cached !== null) return $cached;
        $apiKey = (string) ($this->overrides['api_key'] ?? Config::get('AI_API_KEY', ''));
        $headers = ['Accept: application/json'];
        if ($kind === 'anthropic') {
            // HEAD /v1/messages is not a supported Anthropic API operation
            // and reports a healthy provider as unreachable. The Models API
            // is the provider's read-only availability contract.
            if ($apiKey !== '') $headers[] = 'x-api-key: ' . $apiKey;
            $headers[] = 'anthropic-version: 2023-06-01';
            [$raw, $status, $error] = $this->request($this->joinEndpoint($baseUrl, '/v1/models'), 'GET', $headers, '', 10);
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            if ($error !== '' || $status < 200 || $status >= 300 || !is_array($decoded)) {
                return $this->cacheHealth($cacheKey, $metadata + ['status' => 'unreachable', 'provider_kind' => $kind, 'model' => $model, 'http_status' => $status]);
            }
            $models = (array) ($decoded['data'] ?? []);
            $available = false;
            foreach ($models as $entry) {
                $id = is_array($entry) ? (string) ($entry['id'] ?? '') : '';
                if ($id === $model || str_ends_with($id, '/' . $model)) { $available = true; break; }
            }
            return $this->cacheHealth($cacheKey, $metadata + ['status' => $available ? 'available' : 'not_advertised', 'provider_kind' => $kind, 'model' => $model, 'http_status' => $status]);
        }
        if ($kind === 'google') {
            $headers[] = 'x-goog-api-key: ' . $apiKey;
            $url = $this->joinEndpoint($baseUrl, '/v1beta/models');
        } else {
            if ($apiKey !== '') $headers[] = 'Authorization: Bearer ' . $apiKey;
            $url = $this->joinEndpoint($baseUrl, '/v1/models');
        }
        [$raw, $status, $error] = $this->request($url, 'GET', $headers, '', 10);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($error !== '' || $status < 200 || $status >= 300 || !is_array($decoded)) return $this->cacheHealth($cacheKey, $metadata + ['status' => 'unreachable', 'provider_kind' => $kind, 'model' => $model, 'http_status' => $status]);
        $models = (array) ($decoded['data'] ?? $decoded['models'] ?? []);
        $available = false;
        foreach ($models as $entry) {
            $id = is_array($entry) ? (string) ($entry['id'] ?? $entry['name'] ?? '') : '';
            if ($id === $model || str_ends_with($id, '/' . $model)) { $available = true; break; }
        }
        return $this->cacheHealth($cacheKey, $metadata + ['status' => $available ? 'available' : 'not_advertised', 'provider_kind' => $kind, 'model' => $model, 'http_status' => $status]);
    }

    /** Health metadata is derivable and non-sensitive; prompts/completions are never cached here. */
    private function readHealthCache(string $key): ?array
    {
        if (!$this->healthBuffer) return null;
        try {
            $value = $this->healthBuffer->get('ai.provider.health', $key);
            return is_array($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function cacheHealth(string $key, array $value): array
    {
        // Do not cache outage results: a provider can recover before the
        // normal positive-health TTL and health checks are our recovery probe.
        if ($this->healthBuffer && ($value['status'] ?? null) !== 'unreachable') {
            try {
                $this->healthBuffer->put('ai.provider.health', $key, $value, max(15, min(300, (int) Config::get('AI_HEALTH_CACHE_TTL', 60))));
            } catch (\Throwable) {
                // Health checks remain authoritative when the optional cache is unavailable.
            }
        }
        return $value;
    }

    private function sanitizeMessages(array $messages, bool $allowMultimodal = false): array
    {
        $clean = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                throw new AiProviderException('AI messages must be objects.');
            }
            $role = (string) ($message['role'] ?? '');
            $content = $message['content'] ?? null;
            if (!in_array($role, ['system', 'user', 'assistant'], true) || (!is_string($content) && !is_array($content))) {
                throw new AiProviderException('AI message format is invalid.');
            }
            if (is_string($content) && strlen($content) > 30000) {
                throw new AiProviderException('AI message exceeds the safe context limit.');
            }
            if (is_array($content)) {
                if (!$allowMultimodal || $role !== 'user' || count($content) > 8) {
                    throw new AiProviderException('Multimodal AI content is not enabled for this request.');
                }
                $parts = [];
                foreach ($content as $part) {
                    if (!is_array($part)) throw new AiProviderException('AI content parts must be objects.');
                    $type = (string) ($part['type'] ?? '');
                    if ($type === 'text' && is_string($part['text'] ?? null)) {
                        $parts[] = ['type' => 'text', 'text' => mb_substr($part['text'], 0, 10000)];
                    } elseif ($type === 'image_url' && is_array($part['image_url'] ?? null) && is_string($part['image_url']['url'] ?? null)) {
                        $url = (string) $part['image_url']['url'];
                        if (strlen($url) > 2000000 || (stripos($url, 'https://') !== 0 && stripos($url, 'data:image/') !== 0)) {
                            throw new AiProviderException('AI image content must use a bounded HTTPS URL or image data URI.');
                        }
                        $parts[] = ['type' => 'image_url', 'image_url' => ['url' => $url]];
                    } else {
                        throw new AiProviderException('Unsupported multimodal AI content part.');
                    }
                }
                $clean[] = ['role' => $role, 'content' => $parts];
                continue;
            }
            $clean[] = ['role' => $role, 'content' => $content];
        }
        return $clean;
    }

    /** @return array{0:string|false,1:int,2:string} */
    private function request(string $url, string $method, array $headers, string $body, int $timeout): array
    {
        if ($this->transport !== null) {
            $result = call_user_func($this->transport, $url, $method, $headers, $body, $timeout);
            return [is_string($result[0] ?? null) ? $result[0] : false, (int) ($result[1] ?? 0), (string) ($result[2] ?? '')];
        }
        // HostAfrica/shared-hosting PHP builds may omit ext-curl even when
        // outbound HTTPS is available. Keep the provider contract usable with
        // PHP's verified stream transport instead of failing at curl_init().
        if (!function_exists('curl_init')) {
            $context = stream_context_create([
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'content' => $body,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'protocol_version' => 1.1,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            $status = 0;
            foreach (($http_response_header ?? []) as $responseHeader) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', (string) $responseHeader, $match)) {
                    $status = (int) $match[1];
                    break;
                }
            }
            return [$raw === false ? false : $raw, $status, $raw === false ? 'stream HTTPS request failed' : ''];
        }
        $ch = curl_init($url);
        if ($ch === false) return [false, 0, 'curl unavailable'];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, max(1, (int) Config::get('AI_CONNECT_TIMEOUT', 5))),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        // Some school-network routers advertise an unusable IPv6 resolver.
        // Prefer IPv4 by default; production can opt into dual-stack once
        // IPv6 DNS and routing are verified.
        if (filter_var(Config::get('AI_FORCE_IPV4', true), FILTER_VALIDATE_BOOLEAN)) {
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        }
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$raw, $status, $error];
    }
}
