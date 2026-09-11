<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\FinancialPostingCoordinator;
use App\API\Services\ServiceContractBroker;
use PDO;
use RuntimeException;

/** Transport collection boundary. It never posts transport money to the fee ledger. */
class TransportPaymentService
{
    private $db;
    private $entitlements;
    private $accounts;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->entitlements = ServiceContractBroker::contract('App\API\Modules\transport\StudentTransportEntitlementManager', [], $db);
        $this->accounts = new FinancialAccountService($db);
    }

    public function initiate(array $data, int $userId): array
    {
        $entitlementId = (int)($data['entitlement_id'] ?? 0);
        $channel = strtolower(trim((string)($data['channel'] ?? '')));
        $amount = (float)($data['amount'] ?? 0);
        $allowed = ['daraja_mpesa', 'buni_mpesa', 'c2b_mpesa', 'bank_transfer', 'cash', 'cheque'];
        if (!$entitlementId || !in_array($channel, $allowed, true) || $amount <= 0) {
            throw new RuntimeException('entitlement_id, a valid channel and a positive amount are required');
        }
        $e = $this->entitlements->getEntitlement($entitlementId);
        if (!$e) throw new RuntimeException('Transport entitlement not found');
        $account = $this->accounts->requireFor((int)($data['financial_account_id'] ?? 0), 'transport', $this->channelCode($channel));
        $reference = trim((string)($data['idempotency_reference'] ?? ''));
        if ($reference === '') $reference = 'TRN-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $phone = preg_replace('/\D+/', '', (string)($data['phone_number'] ?? $data['phone'] ?? ''));
        $status = in_array($channel, ['cash', 'bank_transfer', 'cheque'], true) ? 'manual_review' : 'pending';

        $stmt = $this->db->prepare("INSERT INTO transport_payment_intents
            (entitlement_id,student_id,financial_account_id,channel,amount,phone_number,idempotency_reference,status,request_payload,created_by)
            VALUES (?,?,?,?,?,?,?,?,?, ?)");
        $payload = ['channel' => $channel, 'amount' => $amount, 'phone_number' => $phone ?: null];
        $stmt->execute([$entitlementId, (int)$e['student_id'], (int)$account['id'], $channel, $amount, $phone ?: null, $reference, $status, json_encode($payload), $userId]);
        $intentId = (int)$this->db->lastInsertId();
        try {
            $routingReference='TRN-' . $intentId;
            $this->db->prepare("INSERT INTO payment_routing_references (reference,normalized_reference,purpose,student_id,transport_intent_id,expires_at) VALUES (?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))")
                ->execute([$routingReference, (new ReferenceNormalizer())->reference($routingReference), 'transport', (int)$e['student_id'], $intentId]);
        } catch (\Throwable $routingError) { \App\API\Services\Logger::legacyError('[TransportPaymentService] routing reference registration failed: '.$routingError->getMessage()); }

        if ($channel === 'daraja_mpesa') {
            $result = (new MpesaPaymentService())->initiateSTKPush('TRN-' . $intentId, $phone, $amount, 'Transport payment', (int)$account['id']);
            $this->updateProviderResult($intentId, $result, $result['data']['checkout_request_id'] ?? $result['checkout_request_id'] ?? null);
        } elseif ($channel === 'buni_mpesa') {
            $base = defined('KCB_CALLBACK_BASE_URL') ? KCB_CALLBACK_BASE_URL : (defined('BASE_URL') ? BASE_URL : '');
            $result = (new KcbMpesaExpressService())->initiate([
                'phone_number' => $phone,
                'amount' => $amount,
                'invoice_number' => 'TRN-' . $intentId,
                'org_short_code' => (string)$account['account_identifier'],
                'description' => 'Transport payment',
                'callback_url' => rtrim($base, '/') . '/api/payments/kcb-mpesa-express-callback',
            ]);
            $requestId = $result['checkout_request_id'] ?? $result['message_id'] ?? null;
            $this->updateProviderResult($intentId, $result, $requestId);
        }
        return $this->getIntent($intentId);
    }

    public function confirmManual(int $intentId, int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_payment_intents WHERE id=? FOR UPDATE");
        $this->db->beginTransaction();
        try {
            $stmt->execute([$intentId]); $intent = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$intent) throw new RuntimeException('Payment intent not found');
            if ($intent['status'] === 'confirmed') { $this->db->commit(); return $intent; }
            if ($intent['status'] !== 'manual_review') throw new RuntimeException('Only bank, cash or cheque intents awaiting review may be confirmed');
            $this->db->commit();
            $payment = $this->recordConfirmed((int)$intent['entitlement_id'], [
                'amount' => (float)$intent['amount'], 'payment_method' => $intent['channel'] === 'bank_transfer' ? 'bank_transfer' : $intent['channel'],
                'provider_name' => 'manual', 'provider_reference' => $intent['idempotency_reference'], 'notes' => 'Confirmed from transport payment intent', 'financial_account_id' => (int)$intent['financial_account_id'],
            ], $userId, (int)$intent['financial_account_id']);
            $this->markConfirmed($intentId, $intent['idempotency_reference']);
            $this->notifyParent((int)$intent['student_id'], (float)$intent['amount'], $intent['idempotency_reference']);
            return $this->getIntent($intentId) + ['allocation' => $payment];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function reconcileDaraja(string $checkoutId, ?string $providerReference, float $amount): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM transport_payment_intents WHERE channel='daraja_mpesa' AND (provider_request_id=? OR idempotency_reference=?) AND status IN ('pending','accepted') LIMIT 1");
        $stmt->execute([$checkoutId, $checkoutId]); $intent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$intent) return false;
        if (abs((float)$intent['amount'] - $amount) > 0.01) throw new RuntimeException('Transport payment amount mismatch');
        $payment = $this->recordConfirmed((int)$intent['entitlement_id'], ['amount'=>$amount,'payment_method'=>'mpesa','provider_name'=>'mpesa_daraja','provider_reference'=>$providerReference ?: $checkoutId,'verified_provider_callback'=>true,'financial_account_id'=>(int)$intent['financial_account_id']], 0, (int)$intent['financial_account_id']);
        $this->markConfirmed((int)$intent['id'], $providerReference ?: $checkoutId);
        $this->notifyParent((int)$intent['student_id'], $amount, $providerReference ?: $checkoutId);
        return (bool)$payment;
    }

    /** Accept the documented Buni acknowledgement/callback envelope. */
    public function reconcileBuni(array $callback): bool
    {
        $flat = $this->flatten($callback);
        $rawInvoice = (string)($flat['invoiceNumber'] ?? $flat['invoice_number'] ?? $flat['billRefNumber'] ?? $flat['transactionReference'] ?? '');
        $invoice = (new ReferenceNormalizer())->reference($rawInvoice);
        if (!preg_match('/^TRN-(\d+)$/', $invoice, $m)) return false;
        $amount = (float)($flat['amount'] ?? $flat['transactionAmount'] ?? $flat['debitAmount'] ?? 0);
        $reference = (string)($flat['transactionReference'] ?? $flat['transactionID'] ?? $flat['transactionId'] ?? $flat['messageID'] ?? $invoice);
        $success = in_array(strtolower((string)($flat['status'] ?? $flat['statusCode'] ?? $flat['statusDescription'] ?? 'success')), ['0','success','successful','completed','confirmed'], true);
        if (!$success || $amount <= 0) return false;
        $intent = $this->getIntent((int)$m[1]);
        if (!$intent || $intent['channel'] !== 'buni_mpesa' || in_array($intent['status'], ['confirmed','failed','cancelled'], true)) return $intent && $intent['status'] === 'confirmed';
        if (abs((float)$intent['amount'] - $amount) > 0.01) throw new RuntimeException('Buni transport payment amount mismatch');
        $payment = $this->recordConfirmed((int)$intent['entitlement_id'], ['amount'=>$amount,'payment_method'=>'mpesa','provider_name'=>'kcb_buni','provider_reference'=>$reference,'verified_provider_callback'=>true,'financial_account_id'=>(int)$intent['financial_account_id']], 0, (int)$intent['financial_account_id']);
        $this->markConfirmed((int)$intent['id'], $reference); $this->notifyParent((int)$intent['student_id'], $amount, $reference);
        return (bool)$payment;
    }

    public function reconcileIntentReference(string $reference, float $amount, string $provider, string $providerReference): bool
    {
        $reference = (new ReferenceNormalizer())->reference($reference);
        $s=$this->db->prepare("SELECT i.* FROM transport_payment_intents i JOIN payment_routing_references r ON r.transport_intent_id=i.id WHERE r.reference=? AND r.purpose='transport' LIMIT 1"); $s->execute([$reference]); $intent=$s->fetch(PDO::FETCH_ASSOC);
        if (!$intent) return false;
        if ($intent['status']==='confirmed') return true;
        if (!in_array($intent['status'], ['pending','accepted','manual_review'], true) || abs((float)$intent['amount']-$amount)>0.01) return false;
        $payment=$this->recordConfirmed((int)$intent['entitlement_id'], ['amount'=>$amount,'payment_method'=>'mpesa','provider_name'=>$provider,'provider_reference'=>$providerReference,'verified_provider_callback'=>true,'financial_account_id'=>(int)$intent['financial_account_id']],0,(int)$intent['financial_account_id']);
        $this->markConfirmed((int)$intent['id'],$providerReference); $this->notifyParent((int)$intent['student_id'],$amount,$providerReference); return (bool)$payment;
    }

    public function reconcileEntitlement(int $entitlementId, int $studentId, float $amount, string $provider, string $providerReference, ?int $financialAccountId = null): bool
    {
        if (!$financialAccountId) throw new RuntimeException('A transport receiving account is required for legacy payment references.');
        $payment=$this->recordConfirmed($entitlementId,['amount'=>$amount,'payment_method'=>'mpesa','provider_name'=>$provider,'provider_reference'=>$providerReference,'verified_provider_callback'=>true,'financial_account_id'=>$financialAccountId],0,$financialAccountId);
        $this->notifyParent($studentId,$amount,$providerReference); return (bool)$payment;
    }

    public function getIntent(int $id): array
    {
        $s = $this->db->prepare('SELECT * FROM transport_payment_intents WHERE id=?'); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function updateProviderResult(int $id, array $result, ?string $requestId): void
    {
        $accepted = ($result['accepted'] ?? false) || (($result['status'] ?? '') === 'pending');
        $s = $this->db->prepare('UPDATE transport_payment_intents SET status=?, provider_request_id=?, response_payload=? WHERE id=?');
        $s->execute([$accepted ? 'accepted' : 'failed', $requestId, json_encode($result), $id]);
        if ($requestId) {
            $this->db->prepare("UPDATE mpesa_transactions SET student_id=?, bill_ref_number=? WHERE checkout_request_id=? OR mpesa_code=?")
                ->execute([(int)$this->getIntent($id)['student_id'], 'TRN-' . $id, $requestId, $requestId]);
        }
    }

    private function markConfirmed(int $id, string $reference): void
    { $this->db->prepare("UPDATE transport_payment_intents SET status='confirmed',provider_transaction_id=?,confirmed_at=NOW() WHERE id=? AND status <> 'confirmed'")->execute([$reference,$id]); }

    private function recordConfirmed(int $entitlementId, array $data, int $userId, int $financialAccountId): array
    {
        $payment = $this->entitlements->recordPayment($entitlementId, $data, $userId);
        // StudentTransportEntitlementManager records the payment and posts
        // its journal in the same transaction. Keep one posting boundary so
        // a verified callback cannot create a second accounting attempt.
        return $payment;
    }

    private function channelCode(string $channel): string
    {
        return ['daraja_mpesa'=>'mpesa_stk','buni_mpesa'=>'buni_ipn','c2b_mpesa'=>'mpesa_c2b','bank_transfer'=>'bank_transfer','cash'=>'cash','cheque'=>'cheque'][$channel] ?? $channel;
    }

    private function notifyParent(int $studentId, float $amount, string $reference): void
    {
        try {
            $s=$this->db->prepare("SELECT p.phone,p.email FROM students st JOIN persons p ON p.id=st.person_id WHERE st.id=?"); $s->execute([$studentId]); $p=$s->fetch(PDO::FETCH_ASSOC) ?: [];
            $body='Kingsway transport payment received: KES '.number_format($amount,2).' (Ref '.$reference.'). View your parent portal for the transport receipt.';
            $m=ServiceContractBroker::contract('App\API\Modules\communications\CommunicationsManager', [], $this->db);
            foreach (['sms'=>'phone','whatsapp'=>'phone','email'=>'email'] as $type=>$field) if (!empty($p[$field])) $m->createCommunication(['sender_id'=>1,'subject'=>'Transport payment received','body'=>$body,'type'=>$type,'status'=>'queued','priority'=>'normal','recipients'=>[$p[$field]]]);
        } catch (\Throwable $e) { \App\API\Services\Logger::legacyError('[TransportPaymentService] notification failed: '.$e->getMessage()); }
    }

    private function flatten(array $value): array
    {
        $out = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) $out += $this->flatten($item);
            else $out[$key] = $item;
        }
        return $out;
    }
}
