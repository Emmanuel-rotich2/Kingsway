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
 * FLUID CREDENTIALS (env-only, no DB):
 *   - Shared:     MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET, MPESA_SHORTCODE, MPESA_PASSKEY
 *   - Per-stream: MPESA_FEES_SHORTCODE, MPESA_FEES_CONSUMER_KEY, MPESA_FEES_PASSKEY, etc.
 *                 MPESA_TRN_*, MPESA_UNI_*
 *   Resolution: per-shortcode env → shared env. No code changes when Safaricom decides.
 */
class MpesaApiClient
{
    private $environment;
    private $sharedConsumerKey;
    private $sharedConsumerSecret;
    private $sharedShortcode;
    private $sharedPasskey;
    private $sharedInitiatorName;
    private $sharedInitiatorPassword;
    private $sharedSecurityCredential;

    /** @var array<string, array{token:string, expires_at:int}> Per-shortcode token cache */
    private static $tokenCache = [];

    /** @var array<string, array{consumer_key:string, consumer_secret:string, passkey:string, initiator_name:string, initiator_password:string, security_credential:string}> Resolved credentials cache */
    private static $credentialsCache = [];

    public function __construct()
    {
        $this->environment = defined('MPESA_ENVIRONMENT') ? MPESA_ENVIRONMENT : 'sandbox';
        $this->sharedConsumerKey = defined('MPESA_CONSUMER_KEY') ? MPESA_CONSUMER_KEY : '';
        $this->sharedConsumerSecret = defined('MPESA_CONSUMER_SECRET') ? MPESA_CONSUMER_SECRET : '';
        $this->sharedShortcode = defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : '';
        $this->sharedPasskey = defined('MPESA_PASSKEY') ? MPESA_PASSKEY : '';
        $this->sharedInitiatorName = defined('MPESA_INITIATOR_NAME') ? MPESA_INITIATOR_NAME : '';
        $this->sharedInitiatorPassword = defined('MPESA_INITIATOR_PASSWORD') ? MPESA_INITIATOR_PASSWORD : '';
        $this->sharedSecurityCredential = defined('MPESA_SECURITY_CREDENTIAL') ? MPESA_SECURITY_CREDENTIAL : '';
    }

    /**
     * Resolve credentials for a shortcode from .env only.
     * Order: per-stream env (MPESA_FEES_*, MPESA_TRN_*, MPESA_UNI_*) → shared env.
     */
    private function resolveCredentials(string $shortcode): array
    {
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $shortcode));
        if (isset(self::$credentialsCache[$normalized])) {
            return self::$credentialsCache[$normalized];
        }

        // Map shortcode to stream prefix
        $streamPrefix = $this->streamPrefixForShortcode($normalized);

        $creds = [
            'consumer_key' => '',
            'consumer_secret' => '',
            'passkey' => '',
            'initiator_name' => '',
            'initiator_password' => '',
            'security_credential' => '',
        ];

        // Try per-stream env first
        if ($streamPrefix !== '') {
            $creds['consumer_key'] = defined("MPESA_{$streamPrefix}_CONSUMER_KEY") ? constant("MPESA_{$streamPrefix}_CONSUMER_KEY") : '';
            $creds['consumer_secret'] = defined("MPESA_{$streamPrefix}_CONSUMER_SECRET") ? constant("MPESA_{$streamPrefix}_CONSUMER_SECRET") : '';
            $creds['passkey'] = defined("MPESA_{$streamPrefix}_PASSKEY") ? constant("MPESA_{$streamPrefix}_PASSKEY") : '';
            $creds['initiator_name'] = defined("MPESA_{$streamPrefix}_INITIATOR_NAME") ? constant("MPESA_{$streamPrefix}_INITIATOR_NAME") : '';
            $creds['initiator_password'] = defined("MPESA_{$streamPrefix}_INITIATOR_PASSWORD") ? constant("MPESA_{$streamPrefix}_INITIATOR_PASSWORD") : '';
            $creds['security_credential'] = defined("MPESA_{$streamPrefix}_SECURITY_CREDENTIAL") ? constant("MPESA_{$streamPrefix}_SECURITY_CREDENTIAL") : '';
        }

        // Fall back to shared env if per-stream not fully configured
        if ($creds['consumer_key'] === '' || $creds['consumer_secret'] === '') {
            $creds['consumer_key'] = $this->sharedConsumerKey;
            $creds['consumer_secret'] = $this->sharedConsumerSecret;
            $creds['passkey'] = $creds['passkey'] ?: $this->sharedPasskey;
            $creds['initiator_name'] = $creds['initiator_name'] ?: $this->sharedInitiatorName;
            $creds['initiator_password'] = $creds['initiator_password'] ?: $this->sharedInitiatorPassword;
            $creds['security_credential'] = $creds['security_credential'] ?: $this->sharedSecurityCredential;
        }

        self::$credentialsCache[$normalized] = $creds;
        return $creds;
    }

    /**
     * Map a shortcode to its stream prefix (FEES, TRN, UNI).
     * Checks .env for explicit shortcode-to-stream mapping.
     */
    private function streamPrefixForShortcode(string $shortcode): string
    {
        // Check explicit shortcode mappings in env
        foreach (['FEES', 'TRN', 'UNI'] as $prefix) {
            $envShortcode = defined("MPESA_{$prefix}_SHORTCODE") ? constant("MPESA_{$prefix}_SHORTCODE") : '';
            if ($envShortcode !== '' && strtoupper(preg_replace('/[^A-Z0-9]/', '', $envShortcode)) === $shortcode) {
                return $prefix;
            }
        }
        // Fallback: infer from shared shortcode
        if ($shortcode === strtoupper(preg_replace('/[^A-Z0-9]/', '', $this->sharedShortcode))) {
            return 'FEES'; // default stream for shared shortcode
        }
        return '';
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getShortcode(?string $override = null): string
    {
        return $override !== null && trim($override) !== '' ? trim($override) : $this->sharedShortcode;
    }

    public function getInitiatorName(?string $shortcode = null): string
    {
        $creds = $this->resolveCredentials($shortcode ?? $this->sharedShortcode);
        return $creds['initiator_name'] ?? '';
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
     * Uses per-shortcode passkey if available, else shared.
     */
    public function lipaNaMpesaPassword(?string $timestamp = null, ?string $shortcode = null): string
    {
        $ts = $timestamp ?: $this->timestamp();
        $sc = $this->getShortcode($shortcode);
        $creds = $this->resolveCredentials($sc);
        return base64_encode($sc . ($creds['passkey'] ?? '') . $ts);
    }

    /**
     * B2C/B2B/status/balance/reversal SecurityCredential.
     * Uses per-shortcode credentials if available, else shared.
     */
    public function securityCredential(?string $shortcode = null): string
    {
        $creds = $this->resolveCredentials($shortcode ?? $this->sharedShortcode);
        if (!empty($creds['security_credential'])) {
            return $creds['security_credential'];
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
            $initiatorPassword = $creds['initiator_password'] ?? '';
            if (openssl_public_encrypt($initiatorPassword, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
                return base64_encode($encrypted);
            }
        }

        // Sandbox fallback: base64 of the initiator password.
        return base64_encode($creds['initiator_password'] ?? '');
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
     * OAuth access token (client_credentials), cached ~55 minutes per shortcode.
     *
     * @throws Exception
     */
    public function getAccessToken(?string $shortcode = null): string
    {
        $sc = $this->getShortcode($shortcode);
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sc));
        $now = time();

        if (isset(self::$tokenCache[$normalized]) && self::$tokenCache[$normalized]['expires_at'] > $now + 300) {
            return self::$tokenCache[$normalized]['token'];
        }

        $creds = $this->resolveCredentials($sc);
        $consumerKey = $creds['consumer_key'] ?? '';
        $consumerSecret = $creds['consumer_secret'] ?? '';

        if ($consumerKey === '' || $consumerSecret === '') {
            throw new Exception('M-Pesa credentials not configured for shortcode: ' . $sc);
        }

        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $basic = base64_encode($consumerKey . ':' . $consumerSecret);

        $response = $this->request('GET', $url, [
            'Authorization' => 'Basic ' . $basic,
            'Content-Type' => 'application/json',
        ]);

        $httpCode = $response['http_code'];
        $data = $response['data'];

        if ($httpCode !== 200 || empty($data['access_token'])) {
            \App\API\Services\Logger::legacyError(
                '[MpesaApiClient] Access token failed for ' . $sc . ' (HTTP ' . $httpCode . '): ' .
                json_encode($data)
            );
            throw new Exception(
                'Failed to obtain M-Pesa access token for ' . $sc . ' (HTTP ' . $httpCode . ')'
            );
        }

        self::$tokenCache[$normalized] = [
            'token' => $data['access_token'],
            'expires_at' => $now + (int) ($data['expires_in'] ?? 3600),
        ];

        return self::$tokenCache[$normalized]['token'];
    }

    /**
     * Authenticated POST to a Daraja business endpoint.
     *
     * @throws Exception
     */
    public function post(string $path, array $payload, ?string $shortcode = null): array
    {
        $token = $this->getAccessToken($shortcode);
        $encoded = (string) json_encode($payload);
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];

        $response = $this->request('POST', $this->getBaseUrl() . $path, $headers, $encoded);

        $data = $response['data'];
        if ($response['http_code'] === 401) {
            // Token invalidated server-side — clear cache for this shortcode and retry once.
            $sc = $this->getShortcode($shortcode);
            $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sc));
            unset(self::$tokenCache[$normalized]);
            $token = $this->getAccessToken($shortcode);
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
