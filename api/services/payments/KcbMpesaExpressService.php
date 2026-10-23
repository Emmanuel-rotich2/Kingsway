<?php

namespace App\API\Services\payments;

use Exception;

/**
 * KCB Buni M-Pesa Express adapter (DEPRECATED).
 *
 * This is intentionally separate from Daraja STK Push: both initiate an
 * M-Pesa prompt but they have different authentication, headers, endpoint,
 * and acknowledgement envelopes.
 *
 * @deprecated Buni STK Push is deprecated per architecture decision.
 *   PayHero is primary STK aggregator; Daraja Express is fallback.
 *   Buni now only handles C2B/IPN (Lipa na KCB / Lipa Karo) via
 *   /api/payments/kcb-notification.
 */
class KcbMpesaExpressService
{
    private $client;
    private $path;
    private $routeCode;
    private $operation;

    public function __construct(?KcbFundsTransferService $client = null)
    {
        $this->client = $client ?: new KcbFundsTransferService();
        $this->path = defined('KCB_MPESA_EXPRESS_PATH')
            ? (string) KCB_MPESA_EXPRESS_PATH . '/stkpush'
            : '/mm/api/request/1.0.0/stkpush';
        $this->routeCode = defined('KCB_MPESA_ROUTE_CODE') ? (string) KCB_MPESA_ROUTE_CODE : '207';
        $this->operation = defined('KCB_MPESA_OPERATION') ? (string) KCB_MPESA_OPERATION : 'STKPush';
    }

    public function initiate(array $data): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($data['phone_number'] ?? $data['phoneNumber'] ?? ''));
        $amount = (int) ($data['amount'] ?? 0);
        $invoice = trim((string) ($data['invoice_number'] ?? $data['invoiceNumber'] ?? ''));
        $callback = trim((string) ($data['callback_url'] ?? $data['callbackUrl'] ?? ''));

        if (!preg_match('/^2547\d{8}$/', $phone)) {
            throw new Exception('A Safaricom number in 2547XXXXXXXX format is required.');
        }
        if ($amount <= 0) {
            throw new Exception('The M-Pesa Express amount must be a positive whole number.');
        }
        if ($invoice === '' || $callback === '') {
            throw new Exception('Invoice number and callback URL are required.');
        }
        if (!preg_match('#^https://#i', $callback)) {
            throw new Exception('Buni callback URL must use HTTPS and be publicly reachable.');
        }

        $messageId = substr(preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($data['message_id'] ?? 'KWA' . bin2hex(random_bytes(8)))), 0, 32);
        $payload = [
            'phoneNumber' => $phone,
            'amount' => (string) $amount,
            'invoiceNumber' => substr($invoice, 0, 24),
            'sharedShortCode' => (bool) ($data['shared_short_code'] ?? true),
            'orgShortCode' => (string) ($data['org_short_code'] ?? (defined('MPESA_SHORTCODE') ? MPESA_SHORTCODE : '')),
            'orgPassKey' => (string) ($data['org_pass_key'] ?? (defined('MPESA_PASSKEY') ? MPESA_PASSKEY : '')),
            'callbackUrl' => $callback,
            'transactionDescription' => substr(trim((string) ($data['description'] ?? 'School fees')), 0, 13),
        ];

        $response = $this->client->requestJson($this->path, $payload, [
            'Access-Control-Allow-Origin: *',
            'routeCode: ' . $this->routeCode,
            'operation: ' . $this->operation,
            'messageId: ' . $messageId,
        ]);

        $header = is_array($response['header'] ?? null) ? $response['header'] : [];
        $body = is_array($response['response'] ?? null) ? $response['response'] : [];
        $success = (string) ($header['statusCode'] ?? '') === '0'
            || (int) ($body['ResponseCode'] ?? -1) === 0;

        return [
            'status' => $success ? 'pending' : 'failed',
            'accepted' => $success,
            'message_id' => $messageId,
            'checkout_request_id' => $body['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $body['MerchantRequestID'] ?? null,
            'message' => $body['CustomerMessage'] ?? $header['statusDescription'] ?? 'Buni M-Pesa Express response received.',
            'callback_url' => $callback,
            'response' => $response,
        ];
    }
}
