<?php

declare(strict_types=1);

namespace App\API\Services\payments;

/**
 * Shared transport for Daraja (M-Pesa) and KCB Buni HTTP exchanges.
 *
 * Execution order, matching the platform architecture:
 *   1. Python platform relay (POST /api/payment-gateway/execute) — the
 *      primary executor of provider HTTP exchanges on this host.
 *   2. PHP stream fallback (file_get_contents + stream context) when the
 *      relay is not configured or cannot be reached at the connection level.
 *
 * curl is never used: the PHP runtime on this shared host does not load the
 * curl extension, so neither curl_init() nor the CURLOPT_* constants exist.
 * A real HTTP status from the relay is authoritative and never falls back,
 * so a genuine provider or authorization error stays visible instead of
 * being retried against a second transport.
 */
final class PaymentGatewayTransport
{
    private const DEFAULT_TIMEOUT_SECONDS = 45;
    private const MAX_RAW_BYTES = 2 * 1024 * 1024;

    /**
     * Execute one provider HTTP exchange.
     *
     * @param array<string,string> $headers Header name => value map.
     * @return array{http_code:int,body:string,data:?array,error:string}
     */
    public static function send(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        ?int $timeout = null
    ): array {
        $method = strtoupper($method);
        $timeout = $timeout !== null ? max(5, min(90, $timeout)) : self::DEFAULT_TIMEOUT_SECONDS;

        $relay = new PaymentGatewayPythonRelay();
        if ($relay->available()) {
            $relayed = $relay->execute($method, $url, $headers, $body, $timeout);
            if ($relayed !== null) {
                return [
                    'http_code' => $relayed['http_code'],
                    'body' => (string) $relayed['raw'],
                    'data' => is_array($relayed['data']) ? $relayed['data'] : null,
                    'error' => '',
                ];
            }
        }

        return self::streamRequest($method, $url, $headers, $body, $timeout);
    }

    /**
     * PHP stream fallback. Parses the status line from $http_response_header
     * because ignore_errors returns 4xx/5xx bodies instead of false.
     *
     * @param array<string,string> $headers
     * @return array{http_code:int,body:string,data:?array,error:string}
     */
    private static function streamRequest(
        string $method,
        string $url,
        array $headers,
        ?string $body,
        int $timeout
    ): array {
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $name = trim((string) $name);
            if ($name !== '') {
                $headerLines[] = $name . ': ' . (string) $value;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $body ?? '',
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
        $httpCode = 0;
        foreach (($http_response_header ?? []) as $responseHeader) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $responseHeader, $match)) {
                $httpCode = (int) $match[1];
                break;
            }
        }

        if ($raw === false && $httpCode === 0) {
            return [
                'http_code' => 0,
                'body' => '',
                'data' => null,
                'error' => 'Provider transport is unavailable.',
            ];
        }

        $text = is_string($raw) ? substr($raw, 0, self::MAX_RAW_BYTES) : '';
        $decoded = $text !== '' ? json_decode($text, true) : null;

        return [
            'http_code' => $httpCode,
            'body' => $text,
            'data' => is_array($decoded) ? $decoded : null,
            'error' => '',
        ];
    }
}
