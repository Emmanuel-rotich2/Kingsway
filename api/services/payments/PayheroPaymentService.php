<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Includes\FileLogger;
use App\API\Services\PhoneNumberNormalizer;
use App\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * PayHero M-Pesa STK Push adapter.
 *
 * Mirrors the KCB Buni M-Pesa Express adapter: initiation only registers a
 * provider request (the STK prompt), and the callback is the authority for
 * money. Successful callbacks are routed through PaymentRoutingService so
 * fees / transport / uniforms land on the correct ledger and duplicates are
 * deduplicated by provider transaction id.
 *
 * PayHero does not sign callbacks, so a successful callback is optionally
 * confirmed with GET /api/v2/transaction-status before money is posted
 * (PAYHERO_VERIFY_CALLBACK_STATUS). A status endpoint outage never drops a
 * payment: the callback is routed and the event is journalled instead.
 */
final class PayheroPaymentService
{
    private PDO $db;
    private PayheroApiClient $client;

    public function __construct(?PDO $db = null, ?PayheroApiClient $client = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
        $this->client = $client ?: new PayheroApiClient();
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Initiate an STK push.
     *
     * @param array $data {phone_number, amount, external_reference, purpose?,
     *                    customer_name?, channel_id?, callback_url?}
     * @return array {status, accepted, provider, reference, checkout_request_id, message, response}
     */
    public function initiate(array $data): array
    {
        $phone = $this->canonicalPhone((string) ($data['phone_number'] ?? $data['phone'] ?? ''));
        if ($phone === '') {
            throw new RuntimeException('A valid M-Pesa phone number (2547XXXXXXXX) is required.');
        }
        $amount = (int) round((float) ($data['amount'] ?? 0));
        if ($amount <= 0) {
            throw new RuntimeException('The PayHero amount must be a positive whole number.');
        }
        $reference = trim((string) ($data['external_reference'] ?? ''));
        if ($reference === '') {
            throw new RuntimeException('An external reference is required for a PayHero payment.');
        }

        $purpose = strtolower(trim((string) ($data['purpose'] ?? '')));
        $channelId = (int) ($data['channel_id'] ?? 0) ?: $this->resolveChannelId($purpose);
        if ($channelId <= 0) {
            throw new RuntimeException('No PayHero payment channel is configured. Add a PayHero collection point in Payment Integration Settings or set PAYHERO_CHANNEL_ID.');
        }

        $callback = trim((string) ($data['callback_url'] ?? ''));
        if ($callback === '') {
            $callback = $this->defaultCallbackUrl();
        }

        $payload = array_filter([
            'amount' => $amount,
            'phone_number' => $phone,
            'channel_id' => $channelId,
            'provider' => (string) ($data['provider_network'] ?? 'm-pesa'),
            'external_reference' => $reference,
            'customer_name' => trim((string) ($data['customer_name'] ?? '')),
            'callback_url' => $callback,
        ], static fn ($value) => $value !== null && $value !== '');

        try {
            $response = $this->client->initiatePayment($payload);
        } catch (Throwable $e) {
            FileLogger::write('payments', [
                'type' => 'payhero_initiate_failed',
                'external_reference' => $reference,
                'amount' => $amount,
                'channel_id' => $channelId,
                'error' => $e->getMessage(),
            ]);
            return [
                'status' => 'failed',
                'accepted' => false,
                'provider' => 'payhero',
                'reference' => '',
                'checkout_request_id' => '',
                'message' => $e->getMessage(),
                'channel_id' => $channelId,
                'response' => [],
            ];
        }

        $body = is_array($response['data'] ?? null) ? $response['data'] : [];
        $accepted = !empty($body['success']) || strtoupper((string) ($body['status'] ?? '')) === 'QUEUED';

        $result = [
            'status' => $accepted ? 'pending' : 'failed',
            'accepted' => $accepted,
            'provider' => 'payhero',
            'reference' => (string) ($body['reference'] ?? ''),
            'checkout_request_id' => (string) ($body['CheckoutRequestID'] ?? $body['checkout_request_id'] ?? ''),
            'message' => $accepted
                ? 'M-Pesa prompt sent. Check the phone and enter the PIN.'
                : (string) ($body['error_message'] ?? $body['message'] ?? 'PayHero rejected the payment request.'),
            'channel_id' => $channelId,
            'callback_url' => $callback,
            'response' => $body,
        ];

        FileLogger::write('payments', [
            'type' => 'payhero_initiate',
            'accepted' => $accepted,
            'external_reference' => $reference,
            'reference' => $result['reference'],
            'amount' => $amount,
            'channel_id' => $channelId,
            'http_code' => $response['http_code'] ?? null,
        ]);

        return $result;
    }

    public const CHANNEL_TYPES = ['bank', 'paybill', 'till'];

    /**
     * Validate and normalise a channel-registration request into PayHero's
     * documented payload shape:
     * {channel_type, account_id, short_code, account_number, description}.
     *
     * @param array $data {channel_type: bank|paybill|till, short_code,
     *                    account_number?, description?, account_id?}
     * @return array{channel_type: string, account_id: int, short_code: int, account_number: string, description: string}
     */
    public static function normalizeChannelRegistration(array $data, int $accountId = 0): array
    {
        $type = strtolower(trim((string) ($data['channel_type'] ?? '')));
        if (!in_array($type, self::CHANNEL_TYPES, true)) {
            throw new RuntimeException('Choose a PayHero channel type: bank, paybill or till.');
        }

        $shortCode = preg_replace('/\s+/', '', trim((string) ($data['short_code'] ?? '')));
        if ($shortCode === null || preg_match('/^\d{4,10}$/', $shortCode) !== 1) {
            throw new RuntimeException('A numeric paybill, till or bank paybill number is required.');
        }

        $account = trim((string) ($data['account_number'] ?? ''));
        if ($type === 'bank' && $account === '') {
            throw new RuntimeException('The school bank account number is required for a bank channel.');
        }

        $description = trim((string) ($data['description'] ?? ''));
        if ($type === 'bank' && $description === '') {
            throw new RuntimeException("The bank name is required for a bank channel (PayHero uses it as the channel description).");
        }
        if ($description === '') {
            $description = ucfirst($type) . ' ' . $shortCode;
        }

        $resolvedAccountId = $accountId > 0 ? $accountId : (int) ($data['account_id'] ?? 0);
        if ($resolvedAccountId <= 0) {
            throw new RuntimeException('The PayHero account ID is not configured (PAYHERO_ACCOUNT_ID).');
        }

        return [
            'channel_type' => $type,
            'account_id' => $resolvedAccountId,
            'short_code' => (int) $shortCode,
            'account_number' => $account,
            'description' => $description,
        ];
    }

    /**
     * Register a paybill, till or bank channel on the school's PayHero
     * account and return the created channel (id is what collection points
     * store as their account_identifier).
     */
    public function registerChannel(array $data): array
    {
        $accountId = defined('PAYHERO_ACCOUNT_ID') ? (int) PAYHERO_ACCOUNT_ID : 0;
        $payload = self::normalizeChannelRegistration($data, $accountId);

        $response = $this->client->createPaymentChannel($payload);
        $body = is_array($response['data'] ?? null) ? $response['data'] : [];
        $channel = PayheroApiClient::normalizeChannel($body);

        if (empty($response['ok']) || $channel['id'] <= 0) {
            $error = trim((string) ($body['error_message'] ?? $body['message'] ?? ''));
            FileLogger::write('payments', [
                'type' => 'payhero_channel_register_failed',
                'payload' => $payload,
                'http_code' => $response['http_code'] ?? null,
                'error' => $error,
            ]);
            throw new RuntimeException($error !== ''
                ? 'PayHero rejected the channel: ' . $error
                : 'PayHero could not register the channel. Check the number and account details.');
        }

        FileLogger::write('payments', [
            'type' => 'payhero_channel_registered',
            'channel_id' => $channel['id'],
            'channel_type' => $channel['channel_type'],
            'short_code' => $channel['short_code'],
            'account_number' => $channel['account_number'],
        ]);

        return $channel;
    }

    /** Active PayHero channels for this account, newest API truth. */
    public function listChannels(): array
    {
        $response = $this->client->paymentChannels(true);
        $rows = (array) ($response['data']['payment_channels'] ?? []);
        $channels = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $channel = PayheroApiClient::normalizeChannel($row);
            if ($channel['id'] > 0) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    /** Bank paybills PayHero recognises, for the bank-channel picker. */
    public function bankPaybills(): array
    {
        $response = $this->client->bankPaybills();
        $rows = (array) ($response['data']['bank_paybills'] ?? []);
        $banks = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $paybill = trim((string) ($row['paybill'] ?? ''));
            if ($paybill === '') {
                continue;
            }
            $banks[] = [
                'id' => (string) ($row['id'] ?? ''),
                'name' => trim((string) ($row['name'] ?? '')),
                'paybill' => $paybill,
            ];
        }
        return $banks;
    }

    /** Query a PayHero transaction by its reference. */
    public function queryStatus(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new RuntimeException('A PayHero reference is required.');
        }
        $response = $this->client->transactionStatus($reference);
        $normalized = PayheroApiClient::normalizeTransactionStatus(is_array($response['data'] ?? null) ? $response['data'] : []);
        $normalized['ok'] = (bool) ($response['ok'] ?? false);
        $normalized['http_code'] = $response['http_code'] ?? null;
        return $normalized;
    }

    /**
     * Process a PayHero callback. Never throws: the endpoint always answers
     * 2xx so PayHero does not retry a poison payload forever.
     */
    public function processCallback(array $payload): array
    {
        $normalized = self::normalizeCallback($payload);

        FileLogger::write('payments', [
            'type' => 'webhook',
            'source' => 'payhero',
            'status' => $normalized['success'] ? 'processed' : 'failed',
            'external_reference' => $normalized['external_reference'],
            'reference' => $normalized['reference'],
            'receipt' => $normalized['receipt'],
            'amount' => $normalized['amount'],
            'result_code' => $normalized['result_code'],
            'webhook_data' => $payload,
        ]);

        if (!$normalized['success']) {
            return [
                'processed' => false,
                'provider_status' => 'failed',
                'reason' => $normalized['result_description'] !== '' ? $normalized['result_description'] : 'PayHero reported an unsuccessful payment',
                'normalized' => $normalized,
            ];
        }
        if ($normalized['amount'] <= 0 || $normalized['external_reference'] === '') {
            return [
                'processed' => false,
                'provider_status' => 'unroutable',
                'reason' => 'PayHero callback is missing the amount or external reference',
                'normalized' => $normalized,
            ];
        }

        $verification = $this->verifySuccess($normalized);
        if ($verification === false) {
            return [
                'processed' => false,
                'provider_status' => 'verification_failed',
                'reason' => 'PayHero status inquiry did not confirm this payment',
                'normalized' => $normalized,
            ];
        }

        $providerTransactionId = $normalized['receipt'] !== ''
            ? $normalized['receipt']
            : ($normalized['reference'] !== '' ? $normalized['reference'] : $normalized['checkout_request_id']);
        if ($providerTransactionId === '') {
            return [
                'processed' => false,
                'provider_status' => 'unroutable',
                'reason' => 'PayHero callback has no receipt, reference or checkout request id',
                'normalized' => $normalized,
            ];
        }

        try {
            $routed = (new PaymentRoutingService($this->db))->routeIncoming(
                'payhero',
                $payload,
                $providerTransactionId,
                $normalized['amount'],
                $this->resolveCallbackAccountIdentifier($normalized['external_reference']),
                $normalized['external_reference']
            );
        } catch (Throwable $e) {
            FileLogger::write('payments', [
                'type' => 'webhook',
                'source' => 'payhero',
                'event' => 'routing_error',
                'reference' => $normalized['reference'],
                'error' => $e->getMessage(),
            ]);
            return [
                'processed' => false,
                'provider_status' => 'error',
                'reason' => $e->getMessage(),
                'normalized' => $normalized,
            ];
        }

        return [
            'processed' => ($routed['status'] ?? '') === 'processed',
            'provider_status' => $routed['status'] ?? 'unknown',
            'routing' => $routed,
            'normalized' => $normalized,
        ];
    }

    /**
     * Normalise the two callback shapes PayHero documents into one stable
     * structure: the nested envelope ({"response": {...}, "status": true})
     * used by at least one Lipwa-style callback and the flat v2 payload.
     */
    public static function normalizeCallback(array $payload): array
    {
        $envelope = is_array($payload['response'] ?? null) ? $payload['response'] : [];
        $flat = $payload;
        if ($envelope !== []) {
            $flat = array_merge($payload, $envelope);
            unset($flat['response']);
        }

        $first = static function (array $source, array $keys): string {
            foreach ($keys as $key) {
                if (isset($source[$key]) && is_scalar($source[$key]) && (string) $source[$key] !== '') {
                    return trim((string) $source[$key]);
                }
            }
            return '';
        };

        $resultCodeRaw = $flat['ResultCode'] ?? $flat['result_code'] ?? null;
        $resultCode = ($resultCodeRaw !== null && $resultCodeRaw !== '' && is_numeric($resultCodeRaw)) ? (int) $resultCodeRaw : null;
        $statusRaw = $flat['Status'] ?? $flat['status'] ?? null;

        $success = false;
        if ($resultCode !== null) {
            $success = $resultCode === 0;
        } elseif (is_bool($statusRaw)) {
            $success = $statusRaw;
        } elseif ($statusRaw !== null) {
            $success = in_array(strtolower(trim((string) $statusRaw)), ['success', 'successful', 'true', '1', 'completed'], true);
        }

        $amountRaw = $flat['Amount'] ?? $flat['amount'] ?? 0;

        return [
            'success' => $success,
            'external_reference' => $first($flat, ['external_reference', 'ExternalReference', 'externalReference']),
            'reference' => $first($flat, ['reference', 'Reference']),
            'checkout_request_id' => $first($flat, ['CheckoutRequestID', 'checkout_request_id']),
            'receipt' => $first($flat, ['MpesaReceiptNumber', 'mpesa_receipt_number', 'MpesaReceipt']),
            'amount' => is_numeric($amountRaw) ? (float) $amountRaw : 0.0,
            'phone' => $first($flat, ['Phone', 'phone_number', 'PhoneNumber', 'phone']),
            'result_code' => $resultCode,
            'result_description' => $first($flat, ['ResultDesc', 'result_description', 'message', 'error_message']),
        ];
    }

    /** Whether a PayHero collection point exists for a purpose. */
    public function supportsPurpose(string $purpose): bool
    {
        try {
            return $this->routeForPurpose($purpose) !== null;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function verifySuccess(array $normalized): ?bool
    {
        if (!(defined('PAYHERO_VERIFY_CALLBACK_STATUS') ? (bool) PAYHERO_VERIFY_CALLBACK_STATUS : true)) {
            return null;
        }
        if ($normalized['reference'] === '') {
            return null;
        }
        try {
            $status = $this->queryStatus($normalized['reference']);
        } catch (Throwable $e) {
            FileLogger::write('payments', [
                'type' => 'webhook',
                'source' => 'payhero',
                'event' => 'status_verification_unavailable',
                'reference' => $normalized['reference'],
                'error' => $e->getMessage(),
            ]);
            return null;
        }
        if (empty($status['ok'])) {
            return null;
        }
        if (!empty($status['failed'])) {
            return false;
        }
        return true;
    }

    private function resolveChannelId(string $purpose): int
    {
        $route = $purpose !== '' ? $this->routeForPurpose($purpose) : null;
        if ($route !== null) {
            $digits = (int) preg_replace('/\D+/', '', (string) $route['account_identifier']);
            if ($digits > 0) {
                return $digits;
            }
        }
        if (defined('PAYHERO_CHANNEL_ID') && (int) PAYHERO_CHANNEL_ID > 0) {
            return (int) PAYHERO_CHANNEL_ID;
        }
        try {
            $response = $this->client->paymentChannels(true);
            $channels = (array) ($response['data']['payment_channels'] ?? []);
            $active = array_values(array_filter($channels, static fn ($channel) => is_array($channel) && !empty($channel['is_active'])));
            if (count($active) === 1) {
                return (int) ($active[0]['id'] ?? 0);
            }
        } catch (Throwable $e) {
            // No resolvable channel; the caller raises a configuration error.
        }
        return 0;
    }

    private function resolveCallbackAccountIdentifier(string $reference): ?string
    {
        $purpose = null;
        try {
            $normalizer = new ReferenceNormalizer();
            $purpose = $normalizer->purposeFromReference($normalizer->reference($reference));
        } catch (Throwable $e) {
            $purpose = null;
        }

        if ($purpose !== null) {
            $route = $this->routeForPurpose($purpose);
            if ($route !== null) {
                return (string) $route['account_identifier'];
            }
        }

        if (defined('PAYHERO_CHANNEL_ID') && (int) PAYHERO_CHANNEL_ID > 0) {
            return (string) (int) PAYHERO_CHANNEL_ID;
        }

        $routes = $this->activeRoutes();
        if (count($routes) === 1) {
            return (string) $routes[0]['account_identifier'];
        }

        return null;
    }

    /** @return array<int, array{account_identifier: string, purpose: string}> */
    private function activeRoutes(): array
    {
        $stmt = $this->db->query(
            "SELECT r.account_identifier, r.purpose
               FROM payment_collection_routes r
               JOIN payment_providers p ON p.id = r.provider_id
              WHERE p.code = 'payhero' AND r.active = 1
              ORDER BY r.purpose, r.id"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function routeForPurpose(string $purpose): ?array
    {
        foreach ($this->activeRoutes() as $route) {
            if ((string) $route['purpose'] === $purpose) {
                return $route;
            }
        }
        return null;
    }

    private function defaultCallbackUrl(): string
    {
        $base = defined('PAYHERO_CALLBACK_BASE_URL') && PAYHERO_CALLBACK_BASE_URL !== ''
            ? PAYHERO_CALLBACK_BASE_URL
            : (defined('BASE_URL') ? BASE_URL : '');
        return rtrim((string) $base, '/') . '/api/payments/payhero-callback';
    }

    private function canonicalPhone(string $phone): string
    {
        $normalized = PhoneNumberNormalizer::normalize($phone);
        return ($normalized !== null && preg_match('/^2547\d{8}$/', $normalized) === 1) ? $normalized : '';
    }
}
