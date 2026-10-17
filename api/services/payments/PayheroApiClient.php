<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use RuntimeException;

/**
 * PayHero API v2 client.
 *
 * PayHero is a Kenyan M-Pesa aggregator: one HTTP Basic credential pair and
 * one registered payment channel (paybill / till / bank) per collection point.
 * All calls are JSON over HTTPS against https://backend.payhero.co.ke.
 *
 * Transport order (platform architecture): the Python platform executes the
 * provider HTTP calls first because this shared host cannot load the PHP curl
 * extension; PHP's own stream transport is the resilience fallback when the
 * relay is not configured or unreachable. A real HTTP status from either
 * transport is authoritative — provider calls are never double-sent.
 *
 * Credentials come from .env through the PAYHERO_* constants so no secret is
 * ever stored in the database or returned to the browser.
 */
final class PayheroApiClient
{
    private string $baseUrl;
    private string $basicAuth;
    private int $timeout;

    public function __construct(array $options = [])
    {
        $this->baseUrl = rtrim((string) ($options['base_url'] ?? (defined('PAYHERO_BASE_URL') ? PAYHERO_BASE_URL : 'https://backend.payhero.co.ke')), '/');
        $this->basicAuth = (string) ($options['basic_auth'] ?? (defined('PAYHERO_BASIC_AUTH') ? PAYHERO_BASIC_AUTH : ''));
        $this->timeout = max(5, (int) ($options['timeout'] ?? (defined('PAYHERO_TIMEOUT_SECONDS') ? PAYHERO_TIMEOUT_SECONDS : 30)));
        if ($this->basicAuth === '' && isset($options['username'], $options['password'])) {
            $this->basicAuth = self::encodeBasicAuth((string) $options['username'], (string) $options['password']);
        }
    }

    public function isConfigured(): bool
    {
        return $this->basicAuth !== '';
    }

    public static function encodeBasicAuth(string $username, string $password): string
    {
        return base64_encode($username . ':' . $password);
    }

    /** POST /api/v2/payments — initiate an STK push. */
    public function initiatePayment(array $payload): array
    {
        return $this->request('POST', $this->path('PAYHERO_PAYMENTS_PATH', '/api/v2/payments'), $payload);
    }

    /** GET /api/v2/transaction-status?reference= */
    public function transactionStatus(string $reference): array
    {
        return $this->request('GET', $this->path('PAYHERO_TRANSACTION_STATUS_PATH', '/api/v2/transaction-status'), [], ['reference' => $reference]);
    }

    /** GET /api/v2/wallets?wallet_type=service_wallet */
    public function walletBalance(): array
    {
        return $this->request('GET', $this->path('PAYHERO_WALLET_PATH', '/api/v2/wallets'), [], ['wallet_type' => 'service_wallet']);
    }

    /** GET /api/v2/payment_channels */
    public function paymentChannels(bool $activeOnly = false): array
    {
        return $this->request('GET', $this->path('PAYHERO_PAYMENT_CHANNELS_PATH', '/api/v2/payment_channels'), [], $activeOnly ? ['is_active' => 'true'] : []);
    }

    /**
     * POST /api/v2/payment_channels — register a paybill, till or bank
     * channel. Payload (all documented as required):
     * {channel_type, account_id, short_code, account_number, description}.
     */
    public function createPaymentChannel(array $payload): array
    {
        return $this->request('POST', $this->path('PAYHERO_PAYMENT_CHANNELS_PATH', '/api/v2/payment_channels'), $payload);
    }

    /** GET /api/v2/bank_paybills — PayHero's registered bank paybill directory. */
    public function bankPaybills(): array
    {
        return $this->request('GET', $this->path('PAYHERO_BANK_PAYBILLS_PATH', '/api/v2/bank_paybills'));
    }

    /**
     * Normalise a channel object (registration response or list row) into the
     * small stable shape the settings screen reasons about.
     */
    public static function normalizeChannel(array $body): array
    {
        return [
            'id' => (int) ($body['id'] ?? 0),
            'channel_type' => strtolower(trim((string) ($body['channel_type'] ?? ''))),
            'account_id' => (int) ($body['account_id'] ?? 0),
            'short_code' => trim((string) ($body['short_code'] ?? '')),
            'account_number' => trim((string) ($body['account_number'] ?? '')),
            'description' => trim((string) ($body['description'] ?? '')),
            'is_active' => !empty($body['is_active']),
        ];
    }

    /**
     * Normalise the transaction-status body into the small, stable shape the
     * application reasons about (QUEUED / SUCCESS / FAILED are PayHero's
     * documented status values).
     */
    public static function normalizeTransactionStatus(array $body): array
    {
        $status = strtoupper(trim((string) ($body['status'] ?? '')));
        return [
            'status' => $status,
            'successful' => $status === 'SUCCESS',
            'failed' => $status === 'FAILED',
            'reference' => (string) ($body['reference'] ?? ''),
            'provider_reference' => (string) ($body['provider_reference'] ?? $body['third_party_reference'] ?? ''),
            'amount' => isset($body['amount']) && is_numeric($body['amount']) ? (float) $body['amount'] : null,
            'phone' => (string) ($body['phone_number'] ?? ''),
            'raw' => $body,
        ];
    }

    public function request(string $method, string $path, array $body = [], array $query = []): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('PayHero credentials are not configured.');
        }

        $method = strtoupper($method);

        // Python platform first: it is the primary executor of provider HTTP.
        $relay = new PayheroPythonRelay();
        if ($relay->available()) {
            $relayed = $relay->executeRaw($this->baseUrl, $this->basicAuth, $method, $path, $query, $body);
            if ($relayed !== null) {
                return [
                    'ok' => (bool) ($relayed['ok'] ?? false),
                    'http_code' => (int) ($relayed['http_code'] ?? 0),
                    'data' => is_array($relayed['data'] ?? null) ? $relayed['data'] : [],
                    'raw' => (string) ($relayed['raw'] ?? ''),
                ];
            }
        }

        // PHP stream fallback: no curl extension exists on this shared host.
        return $this->streamRequest($method, $path, $body, $query);
    }

    /**
     * PHP stream transport (the secondary executor). Returns the same shape
     * as the relay so callers never branch on which transport answered.
     *
     * @return array{ok: bool, http_code: int, data: array, raw: string}
     */
    private function streamRequest(string $method, string $path, array $body, array $query): array
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", [
                    'Authorization: Basic ' . $this->basicAuth,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => ($body && $method !== 'GET') ? (string) json_encode($body) : '',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new RuntimeException('PayHero request failed: network error');
        }

        $httpCode = 0;
        foreach ($http_response_header ?? [] as $headerLine) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $headerLine, $matches) === 1) {
                $httpCode = (int) $matches[1];
            }
        }

        $decoded = json_decode((string) $raw, true);

        return [
            'ok' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'data' => is_array($decoded) ? $decoded : [],
            'raw' => (string) $raw,
        ];
    }

    private function path(string $constant, string $default): string
    {
        return (string) (defined($constant) ? constant($constant) : $default);
    }
}
