<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\InterServiceClient;
use App\Config\Config;

/**
 * Relay PayHero API calls to the Python platform.
 *
 * PHP remains the payment orchestration and configuration authority; the
 * Python platform owns the provider transport because this shared host
 * cannot load the PHP curl extension and Python already hosts the sibling
 * provider clients. The payload carries the PayHero credentials for the
 * single call after the route/callback decision has already happened in PHP.
 *
 * Response contract mirrors PayheroApiClient::request(): a normalized array
 * with ok/http_code/data/raw. A null return means the relay was not
 * configured or could not be reached at the connection level, and the caller
 * falls back to its own stream transport. A real HTTP status — including an
 * error — is authoritative and is never retried on another transport, since
 * repeated failed STK pushes can get a phone number blocked by PayHero.
 */
final class PayheroPythonRelay
{
    private const ENDPOINT = '/api/payhero/execute';
    private const TIMEOUT_SECONDS = 40;
    private const MAX_BODY_BYTES = 131072;

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
     * Relay one raw PayHero API request. Paths stay owned by the PHP client
     * so the provider surface never needs duplicating here.
     *
     * @return array{ok:bool,http_code:int,data:array,raw:string,message:string}|null
     */
    public function executeRaw(
        string $baseUrl,
        string $basicAuth,
        string $method,
        string $path,
        array $query = [],
        array $body = []
    ): ?array {
        if ($basicAuth === '' || $this->secret === '') {
            return null;
        }

        $encoded = json_encode([
            'action' => 'raw',
            'params' => [
                'method' => strtoupper($method),
                'path' => $path,
                'body' => $body ?: null,
                'query' => $query ?: null,
            ],
            'config' => [
                'base_url' => $baseUrl,
                'basic_auth' => $basicAuth,
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
            // the caller keeps its stream transport as the resilience path.
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
            // is the correct fallback rather than a false provider 404.
            return null;
        }
        $outcome = $decoded['data']['outcome'] ?? null;
        if (is_array($outcome)) {
            return [
                'ok' => (bool) ($outcome['ok'] ?? false),
                'http_code' => (int) ($outcome['http'] ?? $status),
                'data' => is_array($outcome['data'] ?? null) ? $outcome['data'] : [],
                'raw' => (string) ($outcome['raw'] ?? ''),
                'message' => (string) ($outcome['message'] ?? ''),
            ];
        }

        // A real HTTP reply without a relayed outcome is authoritative too.
        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        if ($message === '') {
            $message = is_string($raw) && $raw !== ''
                ? mb_substr($raw, 0, 300)
                : 'Python PayHero relay rejected the request.';
        }
        return [
            'ok' => false,
            'http_code' => $status,
            'data' => [],
            'raw' => '',
            'message' => $message,
        ];
    }
}
