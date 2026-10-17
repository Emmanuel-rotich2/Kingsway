<?php

declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\InterServiceClient;
use App\Config\Config;

/**
 * Relay Daraja / KCB Buni HTTP exchanges to the Python platform.
 *
 * PHP remains the payment orchestration and configuration authority (tokens,
 * payload shaping, ledger handoff); the Python platform executes the provider
 * HTTP exchange because this shared host cannot load the PHP curl extension
 * and Python is the primary executor of external API integrations. The
 * destination host must be on the platform allowlist; this relay only carries
 * one already-authorized, already-shaped request.
 *
 * Response contract: a normalized array with ok/http_code/data/raw/message.
 * A null return means the relay was not configured or could not be reached at
 * the connection level, and the caller falls back to its own PHP stream
 * transport. A real HTTP status — including an error — is authoritative and
 * is never retried on another transport, because a repeated provider
 * submission can create a duplicate business side effect.
 */
final class PaymentGatewayPythonRelay
{
    private const ENDPOINT = '/api/payment-gateway/execute';
    private const TIMEOUT_SECONDS = 55;
    private const MAX_BODY_BYTES = 262144;

    private InterServiceClient $client;
    private string $secret;

    public function __construct(?InterServiceClient $client = null, ?string $secret = null)
    {
        $this->client = $client ?? new InterServiceClient();
        $this->secret = $secret ?? (string) Config::get(
            'AI_PYTHON_SECRET',
            (string) Config::get('AI_API_KEY', '')
        );
    }

    /** Whether the platform is configured well enough to attempt a relay. */
    public function available(): bool
    {
        try {
            return $this->secret !== '' && $this->client->isConfigured('python_ai');
        } catch (\Throwable $error) {
            return false;
        }
    }

    /**
     * Relay one gateway HTTP exchange.
     *
     * @param array<string,string> $headers Header name => value map.
     * @return array{ok:bool,http_code:int,data:?array,raw:string,message:string}|null
     */
    public function execute(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $timeout = null
    ): ?array {
        if ($this->secret === '' || $url === '') {
            return null;
        }

        $encoded = json_encode([
            'params' => [
                'method' => strtoupper($method),
                'url' => $url,
                'headers' => $headers ?: null,
                'body' => $body,
                'timeout' => $timeout,
            ],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '' || strlen($encoded) > self::MAX_BODY_BYTES) {
            return null;
        }

        try {
            [$raw, $status, $error] = $this->client->post('python_ai', self::ENDPOINT, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->secret,
            ], $encoded, self::TIMEOUT_SECONDS);
        } catch (\Throwable $error) {
            // Misconfigured/unresolvable relay is a connection-level failure:
            // the caller keeps its PHP stream transport as the resilience path.
            return null;
        }
        if ($status <= 0) {
            return null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            // The relay itself is not healthy (stale worker serving a route
            // that no longer exists, HTML error page, truncated body). The
            // provider call never happened, so the caller's stream transport
            // is the correct fallback rather than a false provider status.
            return null;
        }
        $outcome = $decoded['data']['outcome'] ?? null;
        if (is_array($outcome)) {
            return [
                'ok' => (bool) ($outcome['ok'] ?? false),
                'http_code' => (int) ($outcome['http'] ?? $status),
                'data' => is_array($outcome['data'] ?? null) ? $outcome['data'] : null,
                'raw' => (string) ($outcome['raw'] ?? ''),
                'message' => (string) ($outcome['message'] ?? ''),
            ];
        }

        // A real HTTP reply without a relayed outcome is authoritative too.
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        if ($message === '') {
            $message = is_string($raw) && $raw !== ''
                ? mb_substr($raw, 0, 300)
                : 'Python payment gateway relay rejected the request.';
        }
        return [
            'ok' => false,
            'http_code' => $status,
            'data' => null,
            'raw' => '',
            'message' => $message,
        ];
    }
}
