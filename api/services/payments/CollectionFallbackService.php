<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Includes\FileLogger;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Initiates online collections through the configured rails in priority order.
 *
 * ARCHITECTURE:
 *   - PayHero = PRIMARY STK Push aggregator (all purposes: fees, transport, uniforms)
 *   - Daraja Express (LIPA_NA_MPESA_ONLINE) = STK FALLBACK (all purposes)
 *   - KCB Buni = C2B/IPN ONLY (Lipa na KCB / Lipa Karo) — NO STK
 *   - Daraja C2B = Real-time validation + confirmation (Uniforms primary, Fees/Transport fallback)
 *
 * Failover is initiation-time only: the next rail is attempted only when the
 * previous rail refused or could not be reached. Once a provider has accepted
 * (an STK prompt is queued on the payer's phone), the transaction is never
 * re-attempted on another rail — completion arrives via that provider's
 * callback and is routed by PaymentRoutingService.
 *
 * The receiving/settling accounts are DB-driven: each candidate rail carries
 * them from payment_collection_routes, so nothing is bound to a fixed account.
 */
final class CollectionFallbackService
{
    private PDO $db;
    private CollectionRailResolver $resolver;

    /** @var callable|null function(array $candidate, array $context): array{accepted:bool, request_id:?string, message:string, status?:string} */
    private $dispatcher;

    public function __construct(PDO $db, ?CollectionRailResolver $resolver = null, ?callable $dispatcher = null)
    {
        $this->db = $db;
        $this->resolver = $resolver ?? new CollectionRailResolver($db);
        $this->dispatcher = $dispatcher;
    }

    public function resolver(): CollectionRailResolver
    {
        return $this->resolver;
    }

    /**
     * Attempt a collection, walking the ordered rails until one accepts.
     *
     * Context contract:
     *   purpose                (string, required) fees | transport | uniforms
     *   amount                 (numeric, required)
     *   phone                  (string, required) canonical 2547XXXXXXXX
     *   reference              (string, required) routing reference (admission no, TRN-…, U-…)
     *   customer_name          (string, optional)
     *   description            (string, optional)
     *   financial_account_id   (int, optional) restrict rails to one account (payer's choice)
     *   provider_code          (string, optional) restrict to one provider code
     *
     * @return array{
     *   accepted:bool, provider_code:?string, channel:?string, route_id:?int,
     *   financial_account_id:?int, settlement_financial_account_id:?int,
     *   request_id:?string, message:string, fallback_used:bool,
     *   attempts:array<int,array>, result:?array
     * }
     */
    public function initiate(string $purpose, array $context): array
    {
        $purpose = strtolower(trim($purpose));
        $providerCode = ProviderCodes::canonical((string) ($context['provider_code'] ?? ''));
        $context['purpose'] = $purpose;

        $candidates = $this->resolver->candidates(
            $purpose,
            (int) ($context['financial_account_id'] ?? 0),
            $providerCode !== '' ? $providerCode : null
        );

        if ($candidates === []) {
            throw new RuntimeException($providerCode !== ''
                ? "No active collection rail is configured for {$purpose} on provider {$providerCode}."
                : "No active online collection rail is configured for {$purpose}.");
        }

        // A mistyped reference cannot be refused by the provider: Safaricom's
        // STK Push carries no account validation, and Buni accepts any
        // non-empty invoiceNumber. The money would leave the payer's line and
        // then match no learner, landing in payment_unmatched_cases with no
        // ledger posting. Refusing it here is cheap; undoing it afterwards is
        // not. Checked once for the whole attempt, not per rail.
        $routing = $this->referenceIsRoutable((string) ($context['reference'] ?? ''), $purpose);
        if (!$routing['routable']) {
            $this->journal($context, $candidates[0], false, $routing['message'], 'reference_unroutable');
            return [
                'accepted' => false,
                'reason' => 'reference_unroutable',
                'message' => $routing['message'],
                'provider_code' => null,
                'channel' => null,
                'route_id' => null,
                'financial_account_id' => null,
                'settlement_financial_account_id' => null,
                'request_id' => null,
                'fallback_used' => false,
                'attempts' => [],
            ];
        }

        $attempts = [];
        foreach ($candidates as $candidate) {
            try {
                $result = $this->dispatch($candidate, $context);
            } catch (Throwable $e) {
                $attempts[] = $this->attemptRecord($candidate, false, $e->getMessage());
                $this->journal($context, $candidate, false, $e->getMessage());
                continue;
            }

            $accepted = !empty($result['accepted']);
            $message = (string) ($result['message'] ?? '');
            $attempts[] = $this->attemptRecord($candidate, $accepted, $message);
            $this->journal($context, $candidate, $accepted, $message);

            if ($accepted) {
                return [
                    'accepted' => true,
                    'provider_code' => $candidate['provider_code'],
                    'channel' => $candidate['channel'],
                    'route_id' => $candidate['route_id'],
                    'financial_account_id' => $candidate['financial_account_id'],
                    'settlement_financial_account_id' => $candidate['settlement_financial_account_id'],
                    'request_id' => $result['request_id'] ?? null,
                    'message' => $message,
                    'fallback_used' => count($attempts) > 1,
                    'attempts' => $attempts,
                    'result' => $result,
                ];
            }
        }

        return [
            'accepted' => false,
            'provider_code' => null,
            'channel' => null,
            'route_id' => null,
            'financial_account_id' => null,
            'settlement_financial_account_id' => null,
            'request_id' => null,
            'message' => $this->failureMessage($attempts),
            'fallback_used' => count($attempts) > 1,
            'attempts' => $attempts,
            'result' => null,
        ];
    }

    /**
     * Dispatch one candidate rail to its provider service.
     *
     * @return array{accepted:bool, request_id:?string, message:string, status?:string}
     */
    public function dispatch(array $candidate, array $context): array
    {
        if ($this->dispatcher !== null) {
            return ($this->dispatcher)($candidate, $context);
        }

        $phone = (string) ($context['phone'] ?? '');
        $amount = (float) ($context['amount'] ?? 0);
        $reference = (string) ($context['reference'] ?? '');
        $purpose = (string) ($context['purpose'] ?? '');
        $description = (string) ($context['description'] ?? ucfirst($purpose) . ' payment');

        switch ($candidate['provider_code']) {
            case 'kcb_buni':
                $base = defined('KCB_CALLBACK_BASE_URL') && KCB_CALLBACK_BASE_URL !== ''
                    ? KCB_CALLBACK_BASE_URL
                    : (defined('BASE_URL') ? BASE_URL : '');
                $callback = (string) ($context['callback_url'] ?? '');
                if ($callback === '') {
                    $callback = rtrim((string) $base, '/') . '/api/payments/kcb-mpesa-express-callback';
                }
                $result = (new KcbMpesaExpressService())->initiate([
                    'phone_number' => $phone,
                    'amount' => $amount,
                    'invoice_number' => $reference,
                    'description' => $description,
                    'callback_url' => $callback,
                    'shared_short_code' => (bool) ($context['shared_short_code'] ?? true),
                    'org_short_code' => (string) ($context['org_short_code'] ?? ''),
                    'org_pass_key' => (string) ($context['org_pass_key'] ?? ''),
                ]);

                return [
                    'accepted' => !empty($result['accepted']),
                    'request_id' => (string) ($result['checkout_request_id'] ?? '') !== ''
                        ? (string) $result['checkout_request_id']
                        : ($result['message_id'] ?? null),
                    'message' => (string) ($result['message'] ?? ''),
                    'status' => (string) ($result['status'] ?? ''),
                    'provider' => $result,
                ];

            case 'payhero':
                $result = (new PayheroPaymentService($this->db))->initiate([
                    'phone_number' => $phone,
                    'amount' => $amount,
                    'external_reference' => $reference,
                    'purpose' => $purpose,
                    'customer_name' => (string) ($context['customer_name'] ?? ''),
                    'channel_id' => (int) $candidate['account_identifier'],
                    'callback_url' => (string) ($context['callback_url'] ?? ''),
                ]);

                return [
                    'accepted' => !empty($result['accepted']),
                    'request_id' => (string) ($result['reference'] ?? '') !== ''
                        ? (string) $result['reference']
                        : ($result['checkout_request_id'] ?? null),
                    'message' => (string) ($result['message'] ?? ''),
                    'status' => (string) ($result['status'] ?? ''),
                    'provider' => $result,
                ];

            case 'safaricom_daraja':
            case 'mpesa_daraja':
                // Daraja Express STK Push (LIPA_NA_MPESA_ONLINE) - FALLBACK only
                $result = (new MpesaPaymentService())->initiateSTKPush(
                    $reference,
                    $phone,
                    $amount,
                    $description,
                    (int) $candidate['financial_account_id'],
                    (int) $candidate['route_id']
                );

                return [
                    'accepted' => !empty($result['success']),
                    'request_id' => $result['data']['checkout_request_id'] ?? null,
                    'message' => (string) ($result['message'] ?? ''),
                    'status' => (string) ($result['status'] ?? ''),
                    'provider' => $result,
                ];
        }

        throw new RuntimeException("Provider {$candidate['provider_code']} cannot initiate an online collection. Only PayHero (primary) and Daraja Express (fallback) support STK Push.");
    }

    private function attemptRecord(array $candidate, bool $accepted, string $message): array
    {
        return [
            'provider_code' => $candidate['provider_code'],
            'route_id' => $candidate['route_id'],
            'financial_account_id' => $candidate['financial_account_id'],
            'accepted' => $accepted,
            'message' => $message,
        ];
    }

    /**
     * Whether a routing reference identifies a payable target, checked before
     * any prompt leaves the building.
     *
     * Fees use the bare admission number, so an active learner must own it.
     * Transport (TRN-…) and uniforms (U-…) use a routed reference, so an
     * unexpired payment_routing_references row for this purpose must exist.
     * An empty reference is the payer's own omission and is reported as such.
     *
     * This check Fails open on a database error: an unreadable lookup must
     * never become the reason a parent's legit payment is refused. When the
     * lookup fails the prompt proceeds and the existing unmatched-case safety
     * net catches a genuinely wrong reference afterwards.
     *
     * @return array{routable:bool,message:string}
     */
    private function referenceIsRoutable(string $reference, string $purpose): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return ['routable' => false, 'message' => 'A payment reference is required.'];
        }

        try {
            if ($purpose === 'fees') {
                $learner = $this->db->prepare(
                    'SELECT admission_no FROM students WHERE admission_no = ? AND status IN ("active","enrolled") LIMIT 1'
                );
                $learner->execute([$reference]);
                $admissionNo = (string) $learner->fetchColumn();
                if ($admissionNo === '') {
                    return [
                        'routable' => false,
                        'message' => 'No active learner has admission number ' . $reference . '. Check the number and try again.',
                    ];
                }
                return ['routable' => true, 'message' => ''];
            }

            if ($purpose === 'transport' || $purpose === 'uniforms') {
                // The expiry comparison takes the clock from the application
                // rather than the database: a routed reference is read from the
                // replica, and NOW() would bind it to the read node's clock.
                $row = $this->db->prepare(
                    'SELECT id FROM payment_routing_references
                      WHERE reference = ? AND purpose = ? AND status IN ("active","consumed")
                        AND (expires_at IS NULL OR expires_at >= ?)
                      LIMIT 1'
                );
                $row->execute([$reference, $purpose, date('Y-m-d H:i:s')]);
                if (!$row->fetchColumn()) {
                    return [
                        'routable' => false,
                        'message' => 'This ' . $purpose . ' reference is not active or has expired. Request a new payment reference.',
                    ];
                }
                return ['routable' => true, 'message' => ''];
            }
        } catch (Throwable $error) {
            FileLogger::write('payments', [
                'type' => 'reference_routing_check',
                'status' => 'failed_open',
                'purpose' => $purpose,
                'error' => $error->getMessage(),
            ], 'warning');
            return ['routable' => true, 'message' => ''];
        }

        // An unknown purpose is rejected upstream by the rail resolver, which
        // refuses to select any candidate for it.
        return ['routable' => true, 'message' => ''];
    }

    private function failureMessage(array $attempts): string
    {
        $parts = [];
        foreach ($attempts as $attempt) {
            if (($attempt['message'] ?? '') !== '') {
                $parts[] = $attempt['provider_code'] . ': ' . $attempt['message'];
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'No configured collection rail accepted the payment.';
    }

    private function journal(array $context, array $candidate, bool $accepted, string $message, string $reason = ''): void
    {
        try {
            FileLogger::write('payments', [
                'type' => 'collection_rail_attempt',
                'purpose' => $context['purpose'] ?? '',
                'reference' => (string) ($context['reference'] ?? ''),
                'amount' => (float) ($context['amount'] ?? 0),
                'provider_code' => $candidate['provider_code'],
                'route_id' => $candidate['route_id'],
                'financial_account_id' => $candidate['financial_account_id'],
                'accepted' => $accepted,
                'message' => $message,
                'reason' => $reason,
            ]);
        } catch (Throwable $e) {
            // journalling must never break a payment attempt
        }
    }
}
