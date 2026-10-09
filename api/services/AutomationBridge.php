<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;

/**
 * Bridges governed, non-AI automations to the Python engine.
 *
 * Identical transport contract to AiPythonBridge but for queue-driven business
 * automations rather than the staff assistant: bearer identification, internal
 * loopback preferred via InterServiceClient, bounded payload, output guard
 * applied (an automation answer is still an untrusted second side).
 */
final class AutomationBridge
{
    public const ENDPOINT_RUN = '/api/automations/run';

    /** @var InterServiceClient */
    private $client;

    /** @var callable */
    private $secretProvider;

    public function __construct(
        ?callable $transport = null,
        ?InterServiceClient $client = null,
        ?callable $secretProvider = null
    )
    {
        $this->client = $client ?? new InterServiceClient($transport);
        $this->secretProvider = $secretProvider ?? static fn (): string => (string) Config::get(
            'AI_PYTHON_SECRET',
            (string) Config::get('AI_API_KEY', '')
        );
    }

    public function available(): bool
    {
        return $this->client->isConfigured('python_ai')
            && trim((string) call_user_func($this->secretProvider)) !== '';
    }

    /**
     * Run one allowlisted automation. $payload must already be the bounded,
     * callee-owned representation of the caller's authorized input.
     *
     * @return array<string,mixed>
     */
    public function run(string $automationId, array $operator, array $payload): array
    {
        $automation = AutomationRegistry::resolve($automationId);
        if ($automation === null) {
            throw new AiProviderException('Unknown automation requested.');
        }
        $permissions = array_values(array_map('strval', (array) ($operator['permissions'] ?? [])));
        if (!AutomationRegistry::isPermitted($permissions, $automationId)) {
            throw new AiProviderException('The operator is not permitted to run this automation.');
        }

        $secret = trim((string) call_user_func($this->secretProvider));
        if (!$this->available()) {
            throw new AiProviderException('The automation engine is not configured.');
        }

        $body = json_encode([
            'automation' => $automationId,
            'operator' => [
                'user_id' => (int) ($operator['user_id'] ?? 0),
                // A routing hint only; the engine never authorizes on it.
                'permissions' => $permissions,
            ],
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new AiProviderException('The automation request could not be encoded.');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $secret,
        ];
        [$raw, $status, $error] = $this->client->post('python_ai', self::ENDPOINT_RUN, $headers, $body, 120);

        if ($raw === false || (int) $status < 200 || (int) $status >= 300) {
            throw new AiProviderException('The automation engine did not complete the request.');
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || !($decoded['success'] ?? false) || !is_array($decoded['data'] ?? null)) {
            throw new AiProviderException('The automation engine rejected the request.');
        }

        // Artifacts are data products, not model prose. AiOutputGuard's
        // conversational string limits intentionally truncate long text and
        // would corrupt CSV/JSON payloads. Validate the artifact envelope and
        // preserve the bounded content bytes so PHP can checksum them.
        $data = $decoded['data'];
        $content = $data['content'] ?? null;
        $format = strtolower((string)($data['format'] ?? ''));
        if (!is_string($content) || $content === '' || strlen($content) > AutomationRegistry::MAX_RESULT_BYTES
            || !in_array($format, ['csv', 'json'], true)) {
            throw new AiProviderException('The automation engine returned an invalid artifact.');
        }
        return [
            'content' => $content,
            'format' => $format,
            'row_count' => max(0, min(AutomationRegistry::MAX_ROWS + 1, (int)($data['row_count'] ?? 0))),
            'column_count' => max(0, min(AutomationRegistry::MAX_COLUMNS, (int)($data['column_count'] ?? 0))),
        ];
    }
}
