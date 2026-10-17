<?php

declare(strict_types=1);

namespace App\API\Services\sms;

/**
 * Transport for the TALKSASA-family provider gateways (SMS + WhatsApp).
 *
 * Execution order, matching the platform architecture:
 *   1. Python platform relay (POST /api/sms/execute raw passthrough) — the
 *      primary executor of every provider HTTP exchange on this host.
 *   2. PHP stream fallback (file_get_contents + stream context) when the
 *      relay is not configured or cannot be reached at the connection level.
 *
 * curl is never used: the PHP runtime on this shared host does not load the
 * curl extension. A real HTTP status from the relay is authoritative and
 * never falls back, so a genuine provider or authorization error stays
 * visible instead of being retried against a second transport.
 */
trait SmsProviderTransport
{
    private const PROVIDER_TIMEOUT_SECONDS = 30;
    private const MAX_RAW_BYTES = 2 * 1024 * 1024;

    /**
     * @return array{ok:bool,http:int,status:string,data:mixed,message:string,raw:string}
     */
    protected function providerTransport(array $config, string $method, string $path, ?array $body): array
    {
        $relay = new SmsPythonRelay();
        if ($relay->available()) {
            $relayed = $relay->executeRaw($config, $method, $path, $body);
            if ($relayed !== null) {
                return $relayed;
            }
        }
        return $this->streamRequest($config, $method, $path, $body);
    }

    /**
     * @return array{ok:bool,http:int,status:string,data:mixed,message:string,raw:string}
     */
    private function streamRequest(array $config, string $method, string $path, ?array $body): array
    {
        $baseUrl = rtrim((string) ($config['api_url'] ?? 'https://bulksms.talksasa.com/api/v3/'), '/');
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            return ['ok' => false, 'http' => 0, 'status' => 'error', 'data' => null, 'message' => 'Messaging API key is not configured.', 'raw' => ''];
        }

        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", [
                    'Authorization: Bearer ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ]),
                'content' => $body === null ? '' : (string) json_encode($body, JSON_UNESCAPED_SLASHES),
                'timeout' => self::PROVIDER_TIMEOUT_SECONDS,
                'ignore_errors' => true,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);
        $raw = @file_get_contents($baseUrl . $path, false, $context);
        $http = 0;
        foreach (($http_response_header ?? []) as $responseHeader) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $responseHeader, $match)) {
                $http = (int) $match[1];
                break;
            }
        }
        if ($raw === false && $http === 0) {
            return ['ok' => false, 'http' => 0, 'status' => 'error', 'data' => null, 'message' => 'Messaging request failed: the provider transport is unavailable.', 'raw' => ''];
        }

        $raw = is_string($raw) ? substr($raw, 0, self::MAX_RAW_BYTES) : '';
        $decoded = $raw !== '' ? json_decode($raw, true) : null;
        $status = strtolower((string) (is_array($decoded) ? ($decoded['status'] ?? $decoded['result'] ?? '') : ''));
        $ok = $http >= 200 && $http < 300
            && !(is_array($decoded) && (isset($decoded['error']) || isset($decoded['errors']) || $status === 'error'));

        $message = is_array($decoded) ? (string) ($decoded['message'] ?? '') : '';
        if ($message === '' && is_array($decoded) && isset($decoded['error'])) {
            $message = is_array($decoded['error']) ? json_encode($decoded['error']) : (string) $decoded['error'];
        }
        if ($message === '' && !$ok) {
            $message = $raw !== '' ? mb_substr($raw, 0, 300) : 'Messaging request failed.';
        }

        return [
            'ok' => $ok,
            'http' => $http,
            'status' => $status !== '' ? $status : ($ok ? 'success' : 'error'),
            'data' => is_array($decoded) ? ($decoded['data'] ?? $decoded) : $decoded,
            'message' => $message,
            'raw' => $raw,
        ];
    }
}
