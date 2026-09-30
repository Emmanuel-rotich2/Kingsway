<?php

declare(strict_types=1);

namespace App\API\Services;

use App\API\Includes\FileLogger;
use App\Config\Config;
use Throwable;

/**
 * Service-to-service bridge from the PHP edge to the Python AI platform.
 *
 * Architecture (the "Python is the AI engine" model):
 *   - PHP keeps ONLY authentication, RBAC, row scope, queues and the
 *     governed data tools; it relays the already-authorized operator
 *     envelope to Python and returns the structured answer verbatim.
 *   - Python (ai_platform/, Passenger WSGI app) owns all agent logic:
 *     provider calls, triage, tool loops, prompts, behaviour study and
 *     digest automations. Every data tool Python needs is executed back
 *     through POST /api/dashboard/agent-tool, which re-authorizes the
 *     recorded operator inside PHP - the model never touches school data.
 *   - The PHP-native AiAgentService remains as the offline/resilience
 *     path when the Python platform is not configured or is redeploying.
 *
 * Transport is curl (shared-hosting verified), bearer-authenticated with
 * the shared AI_PYTHON_SECRET, bounded timeouts, and never logs payloads.
 */
final class AiPythonBridge
{
    public const ENDPOINT_ASSIST = '/api/agents/assist';
    public const ENDPOINT_DIGEST = '/api/agents/digest';

    /** @var callable|null Injectable transport for hermetic tests: fn(string $url, string $method, array $headers, string $body, int $timeout): array{0:string|false,1:int,2:string} */
    private $transport;

    public function __construct(?callable $transport = null)
    {
        $this->transport = $transport;
    }

    public function available(): bool
    {
        return trim((string) Config::get('AI_PYTHON_URL', '')) !== '';
    }

    /**
     * Full governed agent run in the Python platform. Returns the same
     * answer contract as AiAgentService::assist so controllers and the
     * frontend never know which engine ran.
     *
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public function assist(array $context, string $question): array
    {
        return $this->call(self::ENDPOINT_ASSIST, [
            'context' => [
                'user_id' => (int) ($context['user_id'] ?? 0),
                'permissions' => array_values(array_unique(array_map('strval', array_merge(
                    (array) ($context['effective_permissions'] ?? []),
                    (array) ($context['permissions'] ?? [])
                )))),
                'audience' => 'staff',
                'route' => mb_substr((string) ($context['route'] ?? ''), 0, 120),
                'module' => mb_substr((string) ($context['module'] ?? 'dashboard'), 0, 60),
                'request_id' => mb_substr((string) ($context['request_id'] ?? 'ai-python-bridge'), 0, 100),
            ],
            'question' => $question,
        ]);
    }

    /**
     * @param array<int,int|string> $permissions
     * @return array<string,mixed>
     */
    public function digest(int $userId, array $permissions, string $cadence = 'daily', string $requestId = 'ai-python-bridge', bool $broadcast = false): array
    {
        return $this->call(self::ENDPOINT_DIGEST, [
            'context' => [
                'user_id' => $userId,
                'permissions' => array_values(array_map('strval', $permissions)),
                'audience' => 'staff',
                'route' => '',
                'module' => 'dashboard',
                'request_id' => mb_substr($requestId, 0, 100),
            ],
            'cadence' => $cadence,
            'broadcast' => $broadcast,
        ]);
    }

    /**
     * Execute an ai.agent.run queue job through the Python platform and
     * cache the result under the shared insight key so page loads and the
     * PHP-native engine observe one consistent contract.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function runJob(array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $permissions = array_values(array_map('strval', (array) ($payload['permissions'] ?? [])));
        $requestId = mb_substr((string) ($payload['request_id'] ?? 'ai-agent-run'), 0, 100);
        if ((string) ($payload['mode'] ?? 'digest') === 'assist') {
            $result = $this->assist([
                'user_id' => $userId,
                'permissions' => $permissions,
                'effective_permissions' => $permissions,
                'request_id' => $requestId,
                'audience' => 'staff',
                'route' => (string) ($payload['route'] ?? ''),
                'module' => (string) ($payload['module'] ?? 'dashboard'),
            ], (string) ($payload['question'] ?? ''));
        } else {
            $result = $this->digest($userId, $permissions, (string) ($payload['cadence'] ?? 'daily'), $requestId, !empty($payload['broadcast']));
        }
        if ($userId > 0) {
            try {
                (new SharedCache())->set(AiAgentService::CACHE_PREFIX . $userId, $result, AiInsightOrchestrator::BRIEF_TTL);
            } catch (Throwable $e) {
                // Best-effort cache only.
            }
        }
        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function call(string $endpoint, array $payload): array
    {
        $baseUrl = rtrim((string) Config::get('AI_PYTHON_URL', ''), '/');
        $secret = (string) Config::get('AI_PYTHON_SECRET', (string) Config::get('AI_API_KEY', ''));
        if ($baseUrl === '' || $secret === '') {
            throw new AiProviderException('The Python AI platform is not configured.');
        }
        if (Config::isProduction() && stripos($baseUrl, 'https://') !== 0) {
            throw new AiProviderException('The Python AI platform must use HTTPS in production.');
        }
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new AiProviderException('The AI relay request could not be encoded.');
        }
        $url = $baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $secret,
        ];
        $timeout = min(60, max(5, (int) Config::get('AI_PYTHON_TIMEOUT', 45)));
        $started = microtime(true);

        [$raw, $status, $error] = $this->request($url, 'POST', $headers, $body, $timeout);

        FileLogger::write('ai_generation', [
            'type' => 'python_bridge_call',
            'endpoint' => $endpoint,
            'http_status' => (int) $status,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        if ($raw === false || $error !== '') {
            throw new AiProviderException('The Python AI platform did not respond.');
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || (int) $status < 200 || (int) $status >= 300 || (($decoded['success'] ?? null) === false)) {
            throw new AiProviderException('The Python AI platform rejected the request.');
        }
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            throw new AiProviderException('The Python AI platform returned an unusable response.');
        }
        return $data;
    }

    /**
     * @return array{0:string|false,1:int,2:string}
     */
    private function request(string $url, string $method, array $headers, string $body, int $timeout): array
    {
        if ($this->transport !== null) {
            $result = call_user_func($this->transport, $url, $method, $headers, $body, $timeout);
            return [is_string($result[0] ?? null) ? $result[0] : false, (int) ($result[1] ?? 0), (string) ($result[2] ?? '')];
        }
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
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false],
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
        if ($ch === false) {
            return [false, 0, 'curl unavailable'];
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$raw, $status, $error];
    }
}
