<?php

declare(strict_types=1);

namespace App\API\Services\sms;

use App\API\Services\InterServiceClient;
use App\Config\Config;

/**
 * Relay TALKSASA provider calls to the Python platform.
 *
 * PHP remains the configuration and consent authority; the Python platform
 * owns the provider transport because this shared host cannot load the PHP
 * curl extension and Python already hosts the sibling provider clients. The
 * payload carries the provider credentials for the single call after the
 * enqueue/authorization decision has already happened in PHP.
 *
 * Response contract mirrors SMSGateway::request(): a normalized array with
 * ok/http/status/data/message/raw. A null return means the relay was not
 * configured or could not be reached at the connection level, and the caller
 * may fall back to its own transport. A real HTTP status — including an
 * error — is authoritative and is never retried elsewhere.
 */
final class SmsPythonRelay
{
    private const ENDPOINT = '/api/sms/execute';
    private const TIMEOUT_SECONDS = 40;
    private const MAX_BODY_BYTES = 65536;

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
     * Relay a raw provider request. Endpoint paths stay owned by the PHP
     * gateways so the provider surface never needs duplicating here.
     *
     * @return array{ok:bool,http:int,status:string,data:mixed,message:string,raw:string}|null
     */
    public function executeRaw(array $providerConfig, string $method, string $path, ?array $body): ?array
    {
        return $this->execute($providerConfig, 'raw', [
            'method' => strtoupper($method),
            'path' => $path,
            'body' => $body,
        ]);
    }

    /**
     * Relay one named provider action (balance, groups, contacts, ...).
     *
     * @return array{ok:bool,http:int,status:string,data:mixed,message:string,raw:string}|null
     */
    public function execute(array $providerConfig, string $action, array $params): ?array
    {
        $apiKey = (string) ($providerConfig['api_key'] ?? '');
        if ($apiKey === '' || $this->secret === '') {
            return null;
        }

        $encoded = json_encode([
            'action' => $action,
            'params' => $params,
            'config' => [
                'api_key' => $apiKey,
                'api_url' => (string) ($providerConfig['api_url'] ?? ''),
                'sender_id' => (string) ($providerConfig['sender_id'] ?? ''),
            ],
        ], JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BODY_BYTES) {
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
            // the caller keeps its own transport as the resilience path.
            return null;
        }
        if ($status <= 0) {
            return null;
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $outcome = is_array($decoded) ? ($decoded['data']['outcome'] ?? null) : null;
        if (is_array($outcome)) {
            return [
                'ok' => (bool) ($outcome['ok'] ?? false),
                'http' => (int) ($outcome['http'] ?? $status),
                'status' => (string) ($outcome['status'] ?? ''),
                'data' => $outcome['data'] ?? null,
                'message' => (string) ($outcome['message'] ?? ''),
                'raw' => (string) ($outcome['raw'] ?? ''),
            ];
        }

        // A real HTTP reply without a relayed outcome is authoritative too.
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        if ($message === '') {
            $message = is_string($raw) && $raw !== ''
                ? mb_substr($raw, 0, 300)
                : 'Python provider relay rejected the request.';
        }
        return [
            'ok' => false,
            'http' => $status,
            'status' => 'error',
            'data' => null,
            'message' => $message,
            'raw' => '',
        ];
    }
}
