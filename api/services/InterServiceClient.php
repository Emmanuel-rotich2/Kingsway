<?php

declare(strict_types=1);

namespace App\API\Services;

use App\Config\Config;

/**
 * Endpoint-to-endpoint calls between the co-located services on this host.
 *
 * The PHP application and the Python AI platform are two separate business
 * logics that call each other's endpoints. Both are reachable through their
 * public hostnames, but an internal call does not need to travel the public
 * edge: the local webserver already knows how to route to the other vhost.
 *
 * Candidates are tried in order and the first one that answers is remembered
 * for the rest of the request:
 *
 *   1. internal loopback base URL + Host header (co-located, no public edge)
 *   2. the public HTTPS base URL
 *
 * "Answers" means a real HTTP status, not a 2xx. A 500 or 401 is a genuine
 * reply from the other logic and must not trigger a second attempt against a
 * different address; only a connection-level failure falls forward to the next
 * candidate. That keeps a genuine error visible instead of turning it into a
 * confusing "works internally, fails publicly" split.
 *
 * Unix domain sockets are deliberately not attempted. Passenger does not expose
 * a socket an application is allowed to connect to, so a raw socket client
 * would be dead code on this host. The candidate list is the extension point if
 * the Python platform is ever moved off the Python Selector.
 */
final class InterServiceClient
{
    /** @var array<string, array{base:string, host:?string, transport:string}> Winner per service, per request. */
    private static $resolved = [];

    /** @var callable|null Injectable transport for hermetic tests. */
    private $transport;

    /** @var callable Config reader; defaults to Config::get. */
    private $config;

    /** @var callable Production detector; defaults to Config::isProduction. */
    private $isProduction;

    public function __construct(?callable $transport = null, ?callable $config = null, ?callable $isProduction = null)
    {
        $this->transport = $transport;
        // Config::get() prefers $_ENV over runtime values, so a hermetic test
        // cannot override these keys with Config::set(). Reading through an
        // injectable callable keeps the resolver testable on its own.
        $this->config = $config ?? static fn (string $key, $default = null) => Config::get($key, $default);
        $this->isProduction = $isProduction ?? static fn (): bool => Config::isProduction();
    }

    private function read(string $key, $default = null)
    {
        return call_user_func($this->config, $key, $default);
    }

    /**
     * Whether any address is configured for the service, without validating it.
     *
     * Discovery is deliberately separate from validation: an insecure production
     * address must surface as a loud failure when the call is actually made,
     * not be reported here as "not configured" and replaced with a misleading
     * "the platform is not configured" message.
     */
    public function isConfigured(string $service): bool
    {
        return $this->discover($this->normalise($service)) !== [];
    }

    /**
     * Resolve the address to use for a service, without contacting it.
     *
     * Streaming callers need the URL before the body is written, so they resolve
     * up front and add hostHeader() to their own header list.
     *
     * @return array{base:string, host:?string, transport:string}
     */
    public function resolve(string $service): array
    {
        $service = $this->normalise($service);
        if (isset(self::$resolved[$service])) {
            return self::$resolved[$service];
        }

        foreach ($this->candidates($service) as $candidate) {
            self::$resolved[$service] = $candidate;
            return $candidate;
        }

        throw new \RuntimeException(sprintf('No address is configured for the "%s" service.', $service));
    }

    /** Host header a streaming caller must send so the webserver picks the right vhost. */
    public function hostHeader(string $service): ?string
    {
        return $this->resolve($service)['host'];
    }

    /**
     * Which link carried the last successful call, for journalling only.
     * Never throws, because it is called from logging paths.
     */
    public function linkInUse(string $service): string
    {
        return self::$resolved[$this->normalise($service)]['transport'] ?? 'unresolved';
    }

    /**
     * POST to a service endpoint, falling forward across candidates on a
     * connection-level failure.
     *
     * @return array{0:string|false,1:int,2:string} [body, httpStatus, transportError]
     */
    public function post(string $service, string $path, array $headers, string $body, int $timeout): array
    {
        $service = $this->normalise($service);
        $candidates = isset(self::$resolved[$service])
            ? array_values($this->reorder($service))
            : $this->candidates($service);

        if ($candidates === []) {
            throw new \RuntimeException(sprintf('No address is configured for the "%s" service.', $service));
        }

        $last = [false, 0, 'no address configured'];
        foreach ($candidates as $candidate) {
            $url = rtrim($candidate['base'], '/') . $path;
            $sent = $candidate['host'] === null
                ? $headers
                : array_merge($headers, ['Host: ' . $candidate['host']]);

            $last = $this->send($url, 'POST', $sent, $body, $timeout, $candidate['base']);

            if ($last[1] > 0) {
                self::$resolved[$service] = $candidate;
                return $last;
            }

            // Nothing answered on this address. Try the next candidate rather
            // than reporting a transport failure the caller cannot act on.
            unset(self::$resolved[$service]);
        }

        return $last;
    }

    /** Every configured address for a service, internal first, unvalidated. */
    private function discover(string $service): array
    {
        $prefix = $this->configPrefix($service);
        $candidates = [];

        $internal = rtrim((string) $this->read($prefix . '_INTERNAL_BASE_URL', ''), '/');
        if ($internal !== '') {
            $host = trim((string) $this->read($prefix . '_INTERNAL_HOST', ''));
            $candidates[] = [
                'base' => $internal,
                'host' => $host === '' ? null : $host,
                'transport' => 'internal',
            ];
        }

        foreach ([$prefix . '_URL', $prefix . '_PUBLIC_URL', $prefix . '_BASE_URL'] as $key) {
            $public = rtrim((string) $this->read($key, ''), '/');
            if ($public !== '') {
                $candidates[] = ['base' => $public, 'host' => null, 'transport' => 'public'];
                break;
            }
        }

        return $candidates;
    }

    /** Discovery plus the production TLS rule for addresses that leave the host. */
    private function candidates(string $service): array
    {
        $candidates = $this->discover($service);
        foreach ($candidates as $candidate) {
            // Production requires TLS, but only for traffic that can actually
            // leave this machine. A loopback call never reaches the network, so
            // it keeps plain HTTP and is identified by the bearer secret instead.
            if ($candidate['transport'] === 'public'
                && call_user_func($this->isProduction)
                && stripos($candidate['base'], 'https://') !== 0) {
                throw new \RuntimeException(
                    sprintf('The "%s" address must use HTTPS in production.', $candidate['base'])
                );
            }
        }

        return $candidates;
    }

    /** Put the already-selected winner first so the common case stays cheap. */
    private function reorder(string $service): array
    {
        $all = $this->candidates($service);
        $winner = self::$resolved[$service] ?? null;
        if ($winner === null) {
            return $all;
        }

        $ordered = [$winner];
        foreach ($all as $candidate) {
            if ($candidate['transport'] !== $winner['transport']) {
                $ordered[] = $candidate;
            }
        }

        return $ordered;
    }

    /**
     * @return array{0:string|false,1:int,2:string}
     */
    private function send(string $url, string $method, array $headers, string $body, int $timeout, string $base): array
    {
        if ($this->transport !== null) {
            $result = call_user_func($this->transport, $url, $method, $headers, $body, $timeout);
            return [
                is_string($result[0] ?? null) ? $result[0] : false,
                (int) ($result[1] ?? 0),
                (string) ($result[2] ?? ''),
            ];
        }

        $isSecure = stripos($base, 'https://') === 0;

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
                    'verify_peer' => $isSecure,
                    'verify_peer_name' => $isSecure,
                    'allow_self_signed' => false,
                ],
            ]);
            $raw = @file_get_contents($url, false, $context);
            $status = 0;
            foreach (($http_response_header ?? []) as $responseHeader) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $responseHeader, $match)) {
                    $status = (int) $match[1];
                    break;
                }
            }
            if ($raw === false) {
                return [false, 0, 'stream request failed'];
            }
            return [(string) $raw, $status, ''];
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
            CURLOPT_NOSIGNAL => true,
            CURLOPT_SSL_VERIFYPEER => $isSecure,
            CURLOPT_SSL_VERIFYHOST => $isSecure ? 2 : 0,
        ]);
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $status <= 0) {
            return [false, $status, $error !== '' ? $error : 'request failed'];
        }

        return [(string) $raw, $status, ''];
    }

    private function normalise(string $service): string
    {
        return strtolower(trim($service));
    }

    /** Config key prefix for a logical service, e.g. python_ai -> AI_PYTHON. */
    private function configPrefix(string $service): string
    {
        $map = [
            'python_ai' => 'AI_PYTHON',
            'php_app' => 'KINGSWAY_PHP',
            // Node.js realtime data-update layer (scaling masterplan 2026-10-04).
            // Env keys: NODE_REALTIME_INTERNAL_BASE_URL + NODE_REALTIME_INTERNAL_HOST
            // (loopback, Host header picks the vhost) tried first, then the
            // public NODE_REALTIME_URL / NODE_REALTIME_PUBLIC_URL as fallback.
            'realtime_node' => 'NODE_REALTIME',
        ];
        if (isset($map[$service])) {
            return $map[$service];
        }

        return strtoupper(str_replace(['-', '.', '/', ' '], '_', $service));
    }
}
