<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;

/**
 * RealtimeGatewayPublisher - PHP/Python -> Node.js event publish path.
 *
 * Scaling masterplan (2026-10-04): Node.js 24 is the realtime data-update
 * layer. PHP (instant mutations) and Python (completed batch jobs) report
 * events through a non-blocking internal HTTP POST to the gateway:
 *
 *     {NODE_REALTIME_INTERNAL_BASE_URL or NODE_REALTIME_URL}/internal/publish
 *     Header: X-Kingsway-Worker-Secret: <NODE_REALTIME_PUBLISH_SECRET>
 *
 * Environment-agnostic by design: the address is resolved per request by
 * InterServiceClient — the loopback base URL + Host header is tried first, the
 * public HTTPS URL is the fallback — so the exact same code runs on localhost
 * and on the HostAfrica deployment; only .env values differ.
 *
 * Safety rules (unchanged regardless of environment):
 *  - Payloads carry change descriptors (`type`, `scope`, ids, version) only;
 *    never learner/payment/health/case record bodies.
 *  - The publish is fire-and-forget with a tight timeout: a realtime outage
 *    must never slow down or fail the owning database transaction.
 *  - Dormant until routing keys are configured in deployment env; publishing
 *    with no address configured is a no-op that returns false.
 *  - A real HTTP status (including 4xx/5xx) is an answer and is never retried
 *    against the other address (InterServiceClient contract).
 */
final class RealtimeGatewayPublisher
{
    private const SERVICE = 'realtime_node';
    private const PUBLISH_PATH = '/internal/publish';
    private const PUBLISH_TIMEOUT_SECONDS = 2;
    private const MAX_PAYLOAD_BYTES = 16384;

    /** @var callable|null Injectable config reader for hermetic tests. */
    private static $configReader = null;

    /** @var callable|null Injectable publisher for hermetic tests. */
    private static $transportOverride = null;

    /** Test hook: inject config reader/transport; pass null to restore. */
    public static function injectForTests(?callable $configReader = null, ?callable $transport = null): void
    {
        self::$configReader = $configReader;
        self::$transportOverride = $transport;
    }

    /**
     * Whether the gateway has any configured address. Publishing is dormant
     * (returns false, never throws) until it is.
     */
    public static function configured(): bool
    {
        try {
            return (new InterServiceClient(null, self::reader()))->isConfigured(self::SERVICE);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Publish a domain event to connected browsers.
     *
     * @param string $type    Event family, e.g. CACHE_INVALIDATE, DATA_CHANGED,
     *                        JOB_PROGRESS, JOB_COMPLETE, PAYMENT_ALERT.
     * @param string $scope   Routing scope descriptor, e.g. "users:12", "all",
     *                        "stream:45". Node matches it against the PHP-issued
     *                        channel capability on each SSE connection.
     * @param array  $payload Minimal change descriptor (ids, versions). No
     *                        learner/payment/health/case record bodies.
     */
    public static function publish(string $type, string $scope, array $payload = []): bool
    {
        if (preg_match('/^[A-Z][A-Z0-9_]{1,63}$/', $type) !== 1 || trim($scope) === '') {
            return false;
        }

        $body = json_encode([
            'type' => $type,
            'scope' => trim($scope),
            'payload' => $payload,
            'emitted_at' => gmdate('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body) || strlen($body) > self::MAX_PAYLOAD_BYTES) {
            return false;
        }

        try {
            if (self::$transportOverride !== null) {
                return (bool) call_user_func(self::$transportOverride, $type, $scope, $payload);
            }
            $configured = (new InterServiceClient(null, self::reader()))->isConfigured(self::SERVICE);
            if (!$configured) {
                return false;
            }
            $secret = self::workerSecret();
            [$raw, $status, $error] = (new InterServiceClient(null, self::reader()))->post(
                self::SERVICE,
                self::PUBLISH_PATH,
                [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'X-Kingsway-Worker-Secret: ' . $secret,
                ],
                $body,
                self::PUBLISH_TIMEOUT_SECONDS
            );
            $ok = $status >= 200 && $status < 300;
            if (!$ok) {
                \App\API\Includes\FileLogger::write('realtime', [
                    'event' => 'gateway.publish.failed',
                    'type' => $type,
                    'scope' => $scope,
                    'http_status' => $status,
                    'transport_error' => $error === '' ? null : $error,
                ]);
            }
            return $ok;
        } catch (\Throwable $error) {
            // Realtime is an enhancement layer: it must never bubble a failure
            // into the owning transaction.
            \App\API\Includes\FileLogger::write('realtime', [
                'event' => 'gateway.publish.error',
                'type' => $type,
                'scope' => $scope,
                'exception' => get_class($error),
            ]);
            return false;
        }
    }

    private static function reader(): callable
    {
        return self::$configReader ?? static fn (string $key, $default = null) => Config::get($key, $default);
    }

    private static function workerSecret(): string
    {
        $reader = self::reader();
        $secret = (string) $reader('NODE_REALTIME_PUBLISH_SECRET', '');
        if ($secret === '') {
            $secret = (string) $reader('KINGSWAY_WORKER_SECRET', '');
        }
        return $secret;
    }

    /**
     * The browser-facing gateway origin that PHP pages may hand to EventSource.
     *
     * Environment-agnostic: no hostname lives in code. Candidates are read
     * from deployment config in this order:
     *   1. NODE_REALTIME_PUBLIC_URL — the browser-facing gateway origin;
     *   2. NODE_REALTIME_URL — the internal publish address. It may only be
     *      handed to a browser when the requesting client shares the host
     *      with PHP (loopback development), because a remote browser pointed
     *      at a loopback address would connect to its OWN machine (and be
     *      blocked by mixed-content / Local Network Access policy).
     *
     * Plain-HTTP candidates are refused for remote clients on an HTTPS page
     * (mixed content). A loopback client keeps plain HTTP because the packet
     * never leaves the machine.
     *
     * @return string Origin (`scheme://host[:port]`), or '' when no safe
     *                browser-facing address exists for this request.
     */
    public static function browserStreamBase(?string $clientIp = null, ?bool $pageIsHttps = null): string
    {
        $ip = $clientIp ?? (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $clientIsLocal = self::isPrivateNetworkAddress($ip);
        $https = $pageIsHttps ?? self::pageIsHttps();
        $read = self::reader();

        foreach (['NODE_REALTIME_PUBLIC_URL', 'NODE_REALTIME_URL'] as $key) {
            $candidate = trim((string) $read($key, ''));
            if ($candidate === '') {
                continue;
            }
            $origin = self::originOfUrl($candidate);
            if ($origin === '') {
                continue;
            }
            $scheme = strtolower((string) (parse_url($origin, PHP_URL_SCHEME) ?? ''));
            $candidateIsLocal = self::isPrivateNetworkAddress((string) (parse_url($origin, PHP_URL_HOST) ?? ''));
            if ($candidateIsLocal && !$clientIsLocal) {
                continue;
            }
            if ($https && $scheme !== 'https' && !$clientIsLocal) {
                continue;
            }
            return $origin;
        }

        return '';
    }

    /** Loopback / RFC1918 / link-local / localhost-style host detection. */
    private static function isPrivateNetworkAddress(string $value): bool
    {
        $value = trim($value, '[]');
        if ($value === '') {
            return false;
        }
        if (filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return (bool) filter_var(
                $value,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false;
        }
        $host = strtolower($value);
        return $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local');
    }

    private static function pageIsHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? '')) === 'https';
    }

    /** Normalize a URL to its origin; rejects userinfo, paths-only, non-http(s). */
    private static function originOfUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts)) {
            return '';
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return '';
        }
        if (isset($parts['user'], $parts['pass'])) {
            return '';
        }
        return $scheme . '://' . $host . (isset($parts['port']) ? ':' . (int) $parts['port'] : '');
    }
}
