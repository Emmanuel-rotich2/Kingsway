<?php

namespace App\API\Services\payments;

use Exception;

/**
 * KCB Buni funds-transfer client.
 *
 * This class owns transport and authentication only. Payment/disbursement
 * persistence remains in DisbursementManager and callback processing remains
 * in PaymentsAPI so a provider retry cannot create a second business payment.
 *
 * Transport runs on the Python platform relay first with a PHP stream
 * fallback via PaymentGatewayTransport; curl is never used because this
 * shared host's PHP runtime does not load the curl extension.
 */
class KcbFundsTransferService
{
    private $consumerKey;
    private $consumerSecret;
    private $apiKey;
    private $baseUrl;
    private $tokenEndpoint;
    private $revokeEndpoint;
    private $debitAccount;
    private $companyCode;
    private $transactionType;

    /** @var array<string, array{token:string, expires_at:int}> */
    private static $tokenCache = [];

    public function __construct()
    {
        $this->consumerKey = defined('KCB_CONSUMER_KEY') ? (string) KCB_CONSUMER_KEY : '';
        $this->consumerSecret = defined('KCB_CONSUMER_SECRET') ? (string) KCB_CONSUMER_SECRET : '';
        $this->apiKey = defined('KCB_API_KEY') ? (string) KCB_API_KEY : '';
        $this->baseUrl = rtrim(defined('KCB_BASE_URL') ? (string) KCB_BASE_URL : '', '/');
        $this->tokenEndpoint = defined('KCB_TOKEN_ENDPOINT') ? (string) KCB_TOKEN_ENDPOINT : $this->baseUrl . '/token';
        $this->revokeEndpoint = defined('KCB_REVOKE_ENDPOINT') ? (string) KCB_REVOKE_ENDPOINT : $this->baseUrl . '/revoke';
        // The source account is transaction-specific. Configuration is never
        // used as an implicit debit account; callers must pass the verified
        // financial-account identifier selected for this transaction.
        $this->debitAccount = '';
        $this->companyCode = defined('KCB_COMPANY_CODE') ? (string) KCB_COMPANY_CODE : '';
        $this->transactionType = defined('KCB_FUNDS_TRANSFER_TRANSACTION_TYPE')
            ? (string) KCB_FUNDS_TRANSFER_TRANSACTION_TYPE
            : 'IF';
    }

    /**
     * Obtain a Buni OAuth client-credentials token.
     *
     * @throws Exception
     */
    public function getAccessToken(): string
    {
        if ($this->consumerKey === '' || $this->consumerSecret === '' || $this->baseUrl === '') {
            throw new Exception('KCB Buni credentials or base URL are not configured.');
        }

        $cacheKey = hash('sha256', $this->baseUrl . '|' . $this->consumerKey);
        $cached = self::$tokenCache[$cacheKey] ?? null;
        if (is_array($cached) && $cached['expires_at'] > time() + 60) {
            return $cached['token'];
        }

        $response = $this->requestAbsolute($this->tokenEndpoint, 'POST', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret),
        ], http_build_query(['grant_type' => 'client_credentials']));

        $token = (string) ($response['access_token'] ?? '');
        if ($token === '') {
            throw new Exception('KCB Buni did not return an access token.');
        }

        self::$tokenCache[$cacheKey] = [
            'token' => $token,
            'expires_at' => time() + (int) ($response['expires_in'] ?? 3600),
        ];

        return $token;
    }

    /**
     * Shared authenticated JSON request for the other Buni adapters.
     * The operation-specific services own their payload and response mapping.
     */
    public function requestJson(string $path, array $payload, array $headers = []): array
    {
        $requestHeaders = array_merge($this->jsonHeaders($this->getAccessToken()), $headers);
        return $this->request($path, 'POST', $this->headerMap($requestHeaders), (string) json_encode($payload));
    }

    /**
     * Initiate a transfer. This method does not mark a payment complete;
     * final state comes from the KCB callback/reconciliation path.
     */
    public function transferFunds(array $data): array
    {
        try {
            $accountNumber = preg_replace('/\s+/', '', (string) ($data['account_number'] ?? ''));
            $bankName = trim((string) ($data['bank_name'] ?? ''));
            $amount = (float) ($data['amount'] ?? 0);

            if ($accountNumber === '' || !preg_match('/^[0-9]{6,10}$/', $accountNumber)) {
                throw new Exception('A valid beneficiary account number is required.');
            }
            if ($bankName === '') {
                throw new Exception('Beneficiary bank is required.');
            }
            if ($amount <= 0) {
                throw new Exception('Transfer amount must be greater than zero.');
            }
            $debitAccount = preg_replace('/\s+/', '', (string) ($data['debit_account_number'] ?? $data['source_account_number'] ?? ''));
            if ($debitAccount === '' || $this->companyCode === '') {
                throw new Exception('KCB debit account is not configured.');
            }

            $reference = strtoupper(substr((string) ($data['transaction_reference'] ?? $this->generateReference()), 0, 12));
            $payload = [
                'companyCode' => $this->companyCode,
                'transactionType' => $this->transactionType,
                'debitAccountNumber' => $debitAccount,
                'creditAccountNumber' => $accountNumber,
                'debitAmount' => $amount,
                'paymentDetails' => substr(trim((string) ($data['narration'] ?? 'Payment')), 0, 35),
                'transactionReference' => $reference,
                'currency' => 'KES',
                'beneficiaryDetails' => substr(trim((string) ($data['beneficiary_name'] ?? '')), 0, 35),
            ];

            $normalizedBank = strtoupper($bankName);
            // FT v1.4 expects beneficiaryBankCode for IF/EFT/RTGS. KCB's
            // participant code is 01; callers may override it explicitly.
            $payload['beneficiaryBankCode'] = trim((string) ($data['bank_code'] ?? '')) !== ''
                ? trim((string) $data['bank_code'])
                : (($normalizedBank === 'KCB' || $normalizedBank === 'KCB BANK') ? '01' : $this->getBankCode($bankName));

            $path = defined('KCB_FUNDS_TRANSFER_PATH')
                ? (string) KCB_FUNDS_TRANSFER_PATH
                : '/fundstransfer/1.0.0/api/v1/transfer';
            $response = $this->request($path, 'POST', $this->headerMap($this->jsonHeaders($this->getAccessToken())), (string) json_encode($payload));

            $this->logTransaction($accountNumber, $amount, $response);

            // FT v1.4 returns statusCode/merchantID/retrievalRefNumber at the
            // top level. Accept the older nested-header envelope as well.
            $header = is_array($response['header'] ?? null) ? $response['header'] : $response;
            $statusCode = (string) ($header['statusCode'] ?? '1');
            if ($statusCode === '0') {
                return [
                    'status' => 'pending',
                    'message' => 'KCB transfer accepted for processing.',
                    'transaction_ref' => $header['retrievalRefNumber'] ?? $reference,
                    // KCB FT callbacks correlate with merchantId. Keep the
                    // gateway message ID as a fallback for older responses.
                    'request_id' => $header['merchantID'] ?? ($header['merchantId'] ?? ($header['messageID'] ?? null)),
                    'response' => $response,
                ];
            }

            throw new Exception((string) ($header['statusDescription'] ?? $header['statusMessage'] ?? 'KCB transfer was rejected.'));
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[KcbFundsTransferService] ' . $e->getMessage());
            $this->logError($e->getMessage());
            return ['status' => 'error', 'message' => 'KCB transfer could not be initiated.'];
        }
    }

    /**
     * Query Buni for the final state of a previously accepted transfer.
     *
     * The status-inquiry product is a separately subscribed wildcard API. KCB
     * may return different envelopes by subscription, so normalization is
     * deliberately conservative: an unfamiliar response is never treated as
     * a failure and therefore can never unlock an unsafe retry.
     */
    public function getTransferStatus(array $data): array
    {
        $transactionReference = trim((string) ($data['transaction_reference'] ?? $data['transactionReference'] ?? ''));
        $merchantId = trim((string) ($data['merchant_id'] ?? $data['merchantId'] ?? ''));
        if ($transactionReference === '' && $merchantId === '') {
            throw new Exception('A KCB transaction reference or merchant ID is required for status inquiry.');
        }

        $payload = ['transactionReference' => $transactionReference];
        if ($merchantId !== '') {
            $payload['merchantId'] = $merchantId;
        }
        if ($this->companyCode !== '') {
            $payload['companyCode'] = $this->companyCode;
        }

        $path = defined('KCB_TRANSFER_STATUS_PATH')
            ? (string) KCB_TRANSFER_STATUS_PATH
            : '/kcb/bi/ips/p2p/transfer/status/inquiry/1.0.0';
        $response = $this->requestJson($path, $payload);
        $normalized = $this->normalizeTransferStatusResponse($response);
        $normalized['request_payload'] = $payload;
        $normalized['response'] = $response;
        return $normalized;
    }

    /** @return array{normalized_status:string,provider_status:string,message:string,transaction_reference:?string,transaction_id:?string,charges:float} */
    public function normalizeTransferStatusResponse(array $response): array
    {
        $flat = $this->flattenResponse($response);
        $providerStatus = strtoupper(trim((string) (
            $flat['transactionStatus']
            ?? $flat['transactionstatus']
            ?? $flat['statusDescription']
            ?? $flat['statusdescription']
            ?? $flat['statusMessage']
            ?? $flat['statusmessage']
            ?? $flat['status']
            ?? ''
        )));
        // Do not interpret a generic envelope statusCode/resultCode as the
        // transfer result; it may only mean that the inquiry itself succeeded.
        $statusCode = strtoupper(trim((string) ($flat['transactionStatusCode'] ?? '')));

        $successValues = ['SUCCESS', 'SUCCESSFUL', 'COMPLETED', 'COMPLETE', 'PAID', 'PROCESSED'];
        $failureValues = ['FAILED', 'FAILURE', 'REJECTED', 'DECLINED', 'CANCELLED', 'CANCELED', 'REVERSED'];
        $pendingValues = ['PENDING', 'PROCESSING', 'IN PROGRESS', 'IN_PROGRESS', 'QUEUED', 'ACCEPTED', 'INITIATED'];
        if (in_array($providerStatus, $successValues, true) || $statusCode === '0') {
            $normalized = 'successful';
        } elseif (in_array($providerStatus, $failureValues, true)) {
            $normalized = 'failed';
        } elseif (in_array($providerStatus, $pendingValues, true)) {
            $normalized = 'pending';
        } else {
            $normalized = 'unknown';
        }

        return [
            'normalized_status' => $normalized,
            'provider_status' => $providerStatus,
            'message' => (string) ($flat['transactionMessage'] ?? $flat['statusDescription'] ?? $flat['statusMessage'] ?? ''),
            'transaction_reference' => isset($flat['transactionReference']) ? (string) $flat['transactionReference'] : null,
            'transaction_id' => isset($flat['ftReference']) ? (string) $flat['ftReference'] : (isset($flat['transactionId']) ? (string) $flat['transactionId'] : null),
            'charges' => (float) ($flat['charges'] ?? 0),
        ];
    }

    /**
     * Revoke the currently cached client-credentials token. This is intended
     * for credential rotation/security incidents, not after every API call.
     */
    public function revokeAccessToken(): array
    {
        $token = $this->getAccessToken();
        $this->requestAbsoluteAllowEmpty($this->revokeEndpoint, 'POST', [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode($this->consumerKey . ':' . $this->consumerSecret),
        ], http_build_query(['token' => $token, 'token_type_hint' => 'access_token']));
        $cacheKey = hash('sha256', $this->baseUrl . '|' . $this->consumerKey);
        unset(self::$tokenCache[$cacheKey]);
        return ['revoked' => true, 'revoked_at' => gmdate('c')];
    }

    /**
     * Transport-backed request against the configured Buni base URL.
     *
     * @param array<string,string> $headers Header name => value map.
     * @throws Exception
     */
    private function request(string $path, string $method, array $headers, ?string $body = null): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($this->apiKey !== '') {
            // Buni is backed by WSO2 API Manager; its API-key scheme uses
            // the standard `apikey` header, not a database/API key payload.
            $headers['apikey'] = $this->apiKey;
        }
        return $this->sendJson($url, $method, $headers, $body, 'KCB request');
    }

    /**
     * @param array<string,string> $headers Header name => value map.
     * @throws Exception
     */
    private function requestAbsolute(string $url, string $method, array $headers, ?string $body = null): array
    {
        return $this->sendJson($url, $method, $headers, $body, 'KCB request');
    }

    private function requestAbsoluteAllowEmpty(string $url, string $method, array $headers, ?string $body = null): void
    {
        $result = PaymentGatewayTransport::send($method, $url, $headers, $body, 30);
        if ($result['error'] !== '' || $result['http_code'] <= 0) {
            throw new Exception(
                'KCB token revocation failed: ' .
                ($result['error'] !== '' ? $result['error'] : 'no response from provider')
            );
        }
        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            throw new Exception('KCB token revocation returned HTTP ' . $result['http_code'] . '.');
        }
    }

    /**
     * @param array<string,string> $headers Header name => value map.
     * @throws Exception
     */
    private function sendJson(string $url, string $method, array $headers, ?string $body, string $label): array
    {
        $result = PaymentGatewayTransport::send($method, $url, $headers, $body);
        if ($result['error'] !== '' || $result['http_code'] <= 0) {
            throw new Exception(
                $label . ' failed: ' .
                ($result['error'] !== '' ? $result['error'] : 'no response from provider')
            );
        }
        if (!is_array($result['data'])) {
            throw new Exception('KCB returned a non-JSON response (HTTP ' . $result['http_code'] . ').');
        }
        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            throw new Exception('KCB returned HTTP ' . $result['http_code'] . '.');
        }
        return $result['data'];
    }

    /**
     * Accept both "Name: value" header lines and name => value maps.
     *
     * @param array<int|string,string> $headers
     * @return array<string,string>
     */
    private function headerMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                $parts = explode(':', (string) $value, 2);
                if (count($parts) === 2) {
                    $map[trim($parts[0])] = trim($parts[1]);
                }
                continue;
            }
            $map[trim((string) $key)] = trim((string) $value);
        }
        return $map;
    }

    /** Flatten provider envelopes while preserving the first occurrence of a key. */
    private function flattenResponse(array $value): array
    {
        $flat = [];
        $walk = function (array $node) use (&$walk, &$flat): void {
            foreach ($node as $key => $item) {
                if (is_array($item)) {
                    $walk($item);
                } elseif (!array_key_exists((string) $key, $flat)) {
                    $flat[(string) $key] = $item;
                }
            }
        };
        $walk($value);
        return $flat;
    }

    private function jsonHeaders(string $token): array
    {
        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];
    }

    private function generateReference(): string
    {
        return 'KWA' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 9));
    }

    private function getBankCode(string $bankName): string
    {
        $key = strtoupper(trim(preg_replace('/\s+/', ' ', $bankName)));
        $bankCodes = [
            'EQUITY' => '68', 'EQUITY BANK' => '68',
            'CO-OPERATIVE' => '11', 'COOP' => '11', 'COOPERATIVE BANK' => '11',
            'ABSA' => '03', 'BARCLAYS' => '03', 'NCBA' => '07', 'STANBIC' => '31',
            'STANDARD CHARTERED' => '02', 'I&M' => '57', 'FAMILY BANK' => '70',
            'DTB' => '63', 'DIAMOND TRUST' => '63',
        ];
        if (!isset($bankCodes[$key])) {
            throw new Exception('Unsupported beneficiary bank; configure its official KCB bank code first.');
        }
        return $bankCodes[$key];
    }

    private function logTransaction(string $account, float $amount, array $response): void
    {
        \App\API\Services\Logger::legacyError(
            '[' . date('Y-m-d H:i:s') . '] KCB Transfer - Account: ' . $account .
            ', Amount: ' . $amount . ', Response: ' . json_encode($response) . "\n",
            3,
            __DIR__ . '/../../../logs/kcb_transfers.log'
        );
    }

    private function logError(string $message): void
    {
        \App\API\Services\Logger::legacyError(
            '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n",
            3,
            __DIR__ . '/../../../logs/kcb_transfer_errors.log'
        );
    }
}
