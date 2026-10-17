<?php

namespace App\API\Services\payments;

use Exception;

/**
 * MpesaApiClient
 *
 * Thin, official-docs-driven HTTP layer for Safaricom Daraja 3.0.
 *
 * - OAuth token acquisition with static in-process caching (tokens live ~1hr;
 *   we refresh 5 minutes before expiry instead of requesting on every call).
 * - Shared helpers: timestamps (Africa/Nairobi), STK Push password,
 *   SecurityCredential (from config or generated from the M-Pesa cert), and
 *   transaction-date formatting.
 * - Single request() transport used by every API so behaviour (timeouts,
 *   JSON decoding, error visibility) is consistent. Provider HTTP exchanges
 *   run on the Python platform relay first with a PHP stream fallback via
 *   PaymentGatewayTransport; curl is never used because this shared host's
 *   PHP runtime does not load the curl extension.
 *
 * No database access lives here — this class is pure M-Pesa communication.
 */
class MpesaApiClient
{
    private $consumerKey;
    private $consumerSecret;
    private $shortcode;
    private $passkey;
    private $environment;
    private $initiatorName;
    private $initiatorPassword;
    private $securityCredential;

    /** @var array|null Static token cache: ['token' => string, 'expires_at' => int] */
    private static $tokenCache = null;

    public function __construct()
    {
        $this->consumerKey = defined('MPESA_CONSUMER_KEY') ? MPESA_CONSUMER_KEY : '';
        $this->consumerSecret = defined('MPESA_CONSUMER_SECRET') ? MPESA_CONSUMER_SECRET : '';
        $this->shortcode = defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : '';
        $this->passkey = defined('MPESA_PASSKEY') ? MPESA_PASSKEY : '';
        $this->environment = defined('MPESA_ENVIRONMENT') ? MPESA_ENVIRONMENT : 'sandbox';
        $this->initiatorName = defined('MPESA_INITIATOR_NAME') ? MPESA_INITIATOR_NAME : '';
        $this->initiatorPassword = defined('MPESA_INITIATOR_PASSWORD') ? MPESA_INITIATOR_PASSWORD : '';
        $this->securityCredential = defined('MPESA_SECURITY_CREDENTIAL') ? MPESA_SECURITY_CREDENTIAL : '';
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getShortcode(?string $override = null): string
    {
        return $override !== null && trim($override) !== '' ? trim($override) : $this->shortcode;
    }

    public function getInitiatorName(): string
    {
        return $this->initiatorName;
    }

    public function isSandbox(): bool
    {
        return $this->environment !== 'production';
    }

    public function getBaseUrl(): string
    {
        return $this->environment === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Official timestamp format used by Daraja: yyyyMMddHHmmss (Africa/Nairobi).
     */
    public function timestamp(): string
    {
        date_default_timezone_set('Africa/Nairobi');
        return date('YmdHis');
    }

    /**
     * STK Push password: base64(Shortcode . Passkey . Timestamp).
     */
    public function lipaNaMpesaPassword(?string $timestamp = null, ?string $shortcode = null): string
    {
        $ts = $timestamp ?: $this->timestamp();
        return base64_encode($this->getShortcode($shortcode) . $this->passkey . $ts);
    }

    /**
     * B2C/B2B/status/balance/reversal SecurityCredential.
     * Uses the pre-encrypted value from config when provided; otherwise
     * encrypts the initiator password with the M-Pesa public certificate
     * (config/mpesa_*.cer) when present.
     */
    public function securityCredential(): string
    {
        if (!empty($this->securityCredential)) {
            return $this->securityCredential;
        }

        $certPath = defined('MPESA_CERTIFICATE_PATH') && MPESA_CERTIFICATE_PATH !== ''
            ? (string) MPESA_CERTIFICATE_PATH
            : dirname(__DIR__, 3) . '/config/' . ($this->isSandbox() ? 'mpesa_sandbox_cert.cer' : 'mpesa_production_cert.cer');
        if (!file_exists($certPath)) {
            $fallbackCert = dirname(__DIR__, 3) . '/config/mpesa_production_cert.cer';
            $certPath = file_exists($fallbackCert) ? $fallbackCert : $certPath;
        }
        if (file_exists($certPath)) {
            $publicKey = file_get_contents($certPath);
            $encrypted = null;
            if (openssl_public_encrypt($this->initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
                return base64_encode($encrypted);
            }
        }

        // Sandbox fallback: base64 of the initiator password.
        return base64_encode($this->initiatorPassword);
    }

    /**
     * Convert a Daraja transaction timestamp (yyyyMMddHHmmss) to SQL datetime.
     */
    public function formatTransactionDate($transactionDate): string
    {
        if (is_string($transactionDate) && strlen($transactionDate) === 14 && ctype_digit($transactionDate)) {
            return substr($transactionDate, 0, 4) . '-' .
                substr($transactionDate, 4, 2) . '-' .
                substr($transactionDate, 6, 2) . ' ' .
                substr($transactionDate, 8, 2) . ':' .
                substr($transactionDate, 10, 2) . ':' .
                substr($transactionDate, 12, 2);
        }
        date_default_timezone_set('Africa/Nairobi');
        return date('Y-m-d H:i:s');
    }

    /**
     * OAuth access token (client_credentials), cached ~55 minutes.
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        $now = time();
        if (self::$tokenCache !== null && self::$tokenCache['expires_at'] > $now + 300) {
            return self::$tokenCache['token'];
        }

        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $basic = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $response = $this->request('GET', $url, [
            'Authorization' => 'Basic ' . $basic,
            'Content-Type' => 'application/json',
        ]);

        $httpCode = $response['http_code'];
        $data = $response['data'];

        if ($httpCode !== 200 || empty($data['access_token'])) {
            \App\API\Services\Logger::legacyError(
                '[MpesaApiClient] Access token failed (HTTP ' . $httpCode . '): ' .
                json_encode($data)
            );
            throw new Exception(
                'Failed to obtain M-Pesa access token (HTTP ' . $httpCode . ')'
            );
        }

        self::$tokenCache = [
            'token' => $data['access_token'],
            'expires_at' => $now + (int) ($data['expires_in'] ?? 3600),
        ];

        return self::$tokenCache['token'];
    }

    /**
     * Authenticated POST to a Daraja business endpoint.
     *
     * @throws Exception
     */
    public function post(string $path, array $payload): array
    {
        $token = $this->getAccessToken();
        $encoded = (string) json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = $this->request('POST', $this->getBaseUrl() . $path, $headers, $encoded);

        $data = $response['data'];
        if ($response['http_code'] === 401) {
            // Token invalidated server-side — clear cache and retry once.
            self::$tokenCache = null;
            $token = $this->getAccessToken();
            $headers['Authorization'] = 'Bearer ' . $token;
            $response = $this->request('POST', $this->getBaseUrl() . $path, $headers, $encoded);
            $data = $response['data'];
        }

        if ($response['http_code'] >= 500) {
            \App\API\Services\Logger::legacyError(
                '[MpesaApiClient] ' . $path . ' server error (HTTP ' .
                $response['http_code'] . '): ' . json_encode($data)
            );
            throw new Exception(
                'M-Pesa service temporarily unavailable (HTTP ' . $response['http_code'] . ')'
            );
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Single transport helper — never touches the filesystem/DB, always
     * returns both the HTTP status and the decoded body. Provider HTTP
     * exchanges run on the Python platform relay first with a PHP stream
     * fallback; curl is never used because this host's PHP runtime does not
     * load the curl extension.
     *
     * @param array<string,string> $headers Header name => value map.
     * @return array{http_code:int,data:array,raw:string}
     * @throws Exception on connection-level failure
     */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $result = PaymentGatewayTransport::send($method, $url, $headers, $body);
        if ($result['error'] !== '' || $result['http_code'] <= 0) {
            throw new Exception(
                'M-Pesa network error: ' .
                ($result['error'] !== '' ? $result['error'] : 'no response from provider')
            );
        }

        return [
            'http_code' => $result['http_code'],
            'data' => is_array($result['data']) ? $result['data'] : ['raw' => $result['body']],
            'raw' => $result['body'],
        ];
    }
}
