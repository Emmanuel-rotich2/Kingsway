<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use RuntimeException;
use App\API\Services\FinancialPostingCoordinator;
use App\API\Services\ReadReplicaService;

/** Resolves incoming money to fees or transport without guessing. */
class PaymentRoutingService
{
    private $db;
    private ReferenceNormalizer $normalizer;
    private FinancialPostingCoordinator $posting;

    public function __construct(PDO $db) { $this->db = $db; $this->normalizer = new ReferenceNormalizer(); $this->posting = new FinancialPostingCoordinator($db); }

    public function generateReference(string $purpose, int $studentId, ?int $transportIntentId = null, ?int $uniformSaleId = null): array
    {
        if (!in_array($purpose, ['fees','transport','uniforms'], true) || !$studentId) throw new RuntimeException('purpose and student_id are required');
        if ($purpose === 'transport' && !$transportIntentId) throw new RuntimeException('transport_intent_id is required for a transport payment reference');
        if ($purpose === 'uniforms' && !$uniformSaleId) throw new RuntimeException('uniform_sale_id is required for a uniform payment reference');
        $s=$this->db->prepare('SELECT admission_no FROM students WHERE id=? AND status IN ("active","enrolled")'); $s->execute([$studentId]); $admission=$s->fetchColumn();
        if (!$admission) throw new RuntimeException('Active student not found');
        // Fees are intentionally paid with the learner's bare admission
        // number (for example KPS1). Transport and uniforms are the only
        // purpose-prefixed parent-facing references.
        if ($purpose === 'fees') {
            return ['reference'=>(new ReferenceNormalizer())->reference((string)$admission),'purpose'=>'fees','student_id'=>$studentId,'expires_at'=>date('Y-m-d H:i:s',time()+2592000)];
        }
        $prefix=$purpose==='transport'?'TRN':'U'; $reference=$prefix.'-'.$admission.'-'.strtoupper(bin2hex(random_bytes(4)));
        $normalizedReference = $this->normalizer->reference($reference);
        $i=$this->db->prepare('INSERT INTO payment_routing_references (reference,normalized_reference,purpose,student_id,transport_intent_id,uniform_sale_id,expires_at) VALUES (?,?,?,?,?,?,DATE_ADD(NOW(), INTERVAL 30 DAY))'); $i->execute([$reference,$normalizedReference,$purpose,$studentId,$transportIntentId,$uniformSaleId]);
        return ['reference'=>$reference,'purpose'=>$purpose,'student_id'=>$studentId,'expires_at'=>date('Y-m-d H:i:s',time()+2592000)];
    }

    public function routeIncoming(string $providerCode, array $payload, string $providerTransactionId, float $amount, ?string $accountIdentifier = null, ?string $reference = null): array
    {
        if ($amount <= 0 || $providerTransactionId === '') throw new RuntimeException('Provider transaction ID and positive amount are required');
        $providerId=$this->providerId($providerCode); $accountIdentifier=$accountIdentifier ?: $this->extract($payload,['creditAccountIdentifier','ShortCode','BusinessShortCode','accountNumber','account_identifier','tillNumber']); $reference=$reference ?: $this->extract($payload,['BillRefNumber','customerReference','customer_reference','businessKey','invoiceNumber','invoice_number','accountReference','narration']);
        $rawReference=trim((string)$reference); $reference=$this->normalizer->reference($rawReference); $accountIdentifier=$this->normalizer->accountIdentifier($accountIdentifier);
        $routePurpose=$this->accountPurpose($providerId,$accountIdentifier);
        $referenceRow=$this->reference($rawReference);
        $referencePurpose=$referenceRow['purpose'] ?? $this->prefixPurpose($reference);
        if ($routePurpose && $referencePurpose && $routePurpose !== $referencePurpose) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'conflict','Collection account and reference identify different ledgers',$payload);
        $purpose=$referencePurpose ?: $routePurpose;
        if (!$referenceRow && $purpose === 'fees') {
            $student=$this->db->prepare('SELECT id FROM students WHERE admission_no=? AND status IN ("active","enrolled") LIMIT 1');
            $student->execute([$rawReference]);
            $studentId=(int)$student->fetchColumn();
            if ($studentId) $referenceRow=['id'=>0,'student_id'=>$studentId,'purpose'=>'fees'];
        }
        if (!$purpose || !$referenceRow) {
            // Separate transport collection accounts may use the student's
            // admission number as the customer reference. Same-account
            // routing still requires an explicit TRN reference.
            if ($purpose === 'transport' && preg_match('/^TRN-(.+)$/i', $reference)) {
                // T-<account> is a parent-facing alias. ReferenceNormalizer
                // has already canonicalized it to TRN-<account>.
                $admission = trim((string)preg_replace('/^TRN-/i', '', $reference));
                $s=$this->db->prepare('SELECT id FROM students WHERE admission_no=? AND status IN ("active","enrolled") LIMIT 1'); $s->execute([$admission]); $studentId=(int)$s->fetchColumn();
                if ($studentId) {
                    $e=$this->db->prepare("SELECT e.id FROM " . ReadReplicaService::qualifiedRef("student_transport_entitlements") . " JOIN transport_entitlement_periods p ON p.id=e.period_id WHERE e.student_id=? AND e.entitlement_status='active' AND p.period_start<=CURDATE() AND p.period_end>=CURDATE() ORDER BY DATEDIFF(p.period_end,p.period_start) ASC,e.id DESC LIMIT 1"); $e->execute([$studentId]); $entitlementId=(int)$e->fetchColumn();
                    $financialAccountId = $this->accountFinancialId($providerId, $accountIdentifier);
                    if ($entitlementId && $financialAccountId && (new TransportPaymentService($this->db))->reconcileEntitlement($entitlementId,$studentId,$amount,$providerCode,$providerTransactionId,$financialAccountId)) return ['status'=>'processed','purpose'=>'transport','student_id'=>$studentId,'reference'=>$reference,'provider_transaction_id'=>$providerTransactionId];
                }
            }
            return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,$purpose ?: 'unknown',$rawReference ? 'Reference is not registered' : 'Payment has no routable reference or account',$payload);
        }
        $channelCode = $this->channelCode($providerCode, $payload);
        if (!$this->routeAllowsChannel($providerId, $accountIdentifier, $purpose, $channelCode)) {
            return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,$purpose,'This collection route does not allow the payment channel '.$channelCode,$payload);
        }
        if ($routePurpose && $routePurpose !== $purpose) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'conflict','Routing conflict',$payload);
        if ($purpose==='transport') {
            $ok=(new TransportPaymentService($this->db))->reconcileIntentReference($reference,$amount,$providerCode,$providerTransactionId);
            if (!$ok) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'transport','Transport reference has no active payment intent',$payload);
        } elseif ($purpose==='uniforms') {
            $ok=(new UniformPaymentService($this->db))->reconcileReference($reference,$amount,$providerCode,$providerTransactionId,$this->accountFinancialId($providerId,$accountIdentifier));
            if (!$ok) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'uniforms','Uniform reference has no active sale/payment intent',$payload);
        } else {
            $financialAccountId=$this->accountFinancialId($providerId,$accountIdentifier);
            if (!$financialAccountId) return $this->unmatched($providerId,$providerTransactionId,$accountIdentifier,$amount,$rawReference,$reference,'fees','Fees collection account is not mapped to a financial ledger account',$payload);
            $this->recordFee($referenceRow['student_id'],$amount,$providerCode,$reference,$providerTransactionId,$payload,$financialAccountId);
        }
        if (!empty($referenceRow['id'])) {
            $this->db->prepare("UPDATE payment_routing_references SET status='consumed' WHERE id=? AND status='active'")->execute([(int)$referenceRow['id']]);
        }
        return ['status'=>'processed','purpose'=>$purpose,'student_id'=>(int)$referenceRow['student_id'],'reference'=>$reference,'provider_transaction_id'=>$providerTransactionId];
    }

    public function listRoutes(): array { return $this->db->query("SELECT r.*,p.code provider_code,sa.account_name settlement_account_name,sa.account_identifier settlement_account_identifier,(SELECT GROUP_CONCAT(DISTINCT fc.code ORDER BY fc.code SEPARATOR ',') FROM " . ReadReplicaService::qualifiedRef("payment_collection_route_channels") . " rch JOIN financial_channels fc ON fc.id=rch.channel_id WHERE rch.route_id=r.id) route_channels FROM " . ReadReplicaService::qualifiedRef("payment_collection_routes") . " r JOIN payment_providers p ON p.id=r.provider_id LEFT JOIN " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " sa ON sa.id=COALESCE(r.settlement_financial_account_id,r.financial_account_id) WHERE r.active=1 ORDER BY p.code,r.account_identifier,r.purpose")->fetchAll(PDO::FETCH_ASSOC); }

    public function isConfiguredAccount(string $providerCode, ?string $account): bool
    { if (!$account) return false; $pid=$this->providerId($providerCode); $normalized=$this->normalizer->accountIdentifier($account); $s=$this->db->prepare('SELECT 1 FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND active=1 LIMIT 1'); $s->execute([$pid,$normalized]); return (bool)$s->fetchColumn(); }

    public function saveRoute(array $data): array
    {
        $routeId=(int)($data['id']??0); $financialAccountId=(int)($data['financial_account_id']??0); $purpose=(string)($data['purpose']??'');
        if ($financialAccountId<=0 || !in_array($purpose,['fees','transport','uniforms'],true)) throw new RuntimeException('An existing school account and one incoming purpose are required.');
        $account=$this->db->prepare('SELECT a.account_identifier,a.normalized_account_identifier,a.provider_id,a.settlement_financial_account_id,p.code provider_code FROM ' . ReadReplicaService::masterRef('school_financial_accounts') . ' a LEFT JOIN payment_providers p ON p.id=a.provider_id WHERE a.id=? LIMIT 1'); $account->execute([$financialAccountId]); $accountRow=$account->fetch(PDO::FETCH_ASSOC);
        if (!$accountRow) throw new RuntimeException('Selected school account was not found.');
        $code=(string)($data['provider_code']??$accountRow['provider_code']??''); $accountIdentifier=trim((string)($data['account_identifier']??$accountRow['account_identifier']??'')); $displayName=trim((string)($data['display_name']??'')); $prefix=trim((string)($data['reference_prefix']??($purpose==='transport'?'TRN':($purpose==='uniforms'?'U':'FEE'))));
        if (!$code || $accountIdentifier==='' || $prefix==='') throw new RuntimeException('Provider, collection identifier and reference prefix are required.');
        $pid=$this->providerId($code); $normalized=$this->normalizer->accountIdentifier($accountIdentifier); $settlement=(int)($data['settlement_financial_account_id']??$accountRow['settlement_financial_account_id']??$financialAccountId); $product=(string)($data['collection_product']??'bank_collection'); $policy=(string)($data['reference_policy']??($purpose==='fees'?'admission_no':($purpose==='transport'?'transport_reference':'uniform_reference')));
        if (in_array($product,['paybill','till','buni'],true) && $settlement<=0) throw new RuntimeException('A Paybill, Till or Buni point must have a settlement school account.');
        $this->db->beginTransaction();
        try {
            if ($routeId) {
                $q=$this->db->prepare('UPDATE payment_collection_routes SET provider_id=?,display_name=?,show_on_fee_structure=?,display_order=?,display_title=?,display_reference_label=?,display_reference_value=?,display_instructions=?,display_updated_by=?,financial_account_id=?,settlement_financial_account_id=?,account_identifier=?,normalized_account_identifier=?,collection_product=?,reference_policy=?,reference_label=?,purpose=?,reference_prefix=?,active=1 WHERE id=?');
                $q->execute([$pid,$displayName?:null,!empty($data['show_on_fee_structure'])?1:0,max(0,(int)($data['display_order']??0)),trim((string)($data['display_title']??''))?:null,trim((string)($data['display_reference_label']??''))?:null,trim((string)($data['display_reference_value']??''))?:null,trim((string)($data['display_instructions']??''))?:null,(int)($data['updated_by']??0)?:null,$financialAccountId,$settlement,$accountIdentifier,$normalized,$product,$policy,trim((string)($data['reference_label']??''))?:null,$purpose,$prefix,$routeId]);
                $exists=$this->db->prepare('SELECT 1 FROM payment_collection_routes WHERE id=?'); $exists->execute([$routeId]); if (!$exists->fetchColumn()) throw new RuntimeException('Collection route not found.');
            } else {
                $q=$this->db->prepare('SELECT id FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND purpose=? LIMIT 1'); $q->execute([$pid,$normalized,$purpose]); $routeId=(int)$q->fetchColumn();
                if ($routeId) { $data['id']=$routeId; $this->db->commit(); return $this->saveRoute($data); }
                $q=$this->db->prepare('INSERT INTO payment_collection_routes (provider_id,display_name,show_on_fee_structure,display_order,display_title,display_reference_label,display_reference_value,display_instructions,display_updated_by,financial_account_id,settlement_financial_account_id,account_identifier,normalized_account_identifier,collection_product,reference_policy,reference_label,purpose,reference_prefix) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$pid,$displayName?:null,!empty($data['show_on_fee_structure'])?1:0,max(0,(int)($data['display_order']??0)),trim((string)($data['display_title']??''))?:null,trim((string)($data['display_reference_label']??''))?:null,trim((string)($data['display_reference_value']??''))?:null,trim((string)($data['display_instructions']??''))?:null,(int)($data['updated_by']??0)?:null,$financialAccountId,$settlement,$accountIdentifier,$normalized,$product,$policy,trim((string)($data['reference_label']??''))?:null,$purpose,$prefix]); $routeId=(int)$this->db->lastInsertId();
            }
            $this->db->prepare('DELETE FROM payment_collection_route_channels WHERE route_id=?')->execute([$routeId]);
            $channels=(array)($data['channels']??[]); if (!$channels) { $q=$this->db->prepare('SELECT c.code FROM ' . ReadReplicaService::masterRef('school_financial_account_channels') . ' ac JOIN financial_channels c ON c.id=ac.channel_id WHERE ac.financial_account_id=?'); $q->execute([$financialAccountId]); $channels=$q->fetchAll(PDO::FETCH_COLUMN); }
            if ($purpose==='fees') $channels=array_values(array_diff($channels,['cash']));
            $insert=$this->db->prepare('INSERT INTO payment_collection_route_channels(route_id,channel_id) SELECT ?,id FROM financial_channels WHERE code=?'); foreach (array_unique($channels) as $channel) $insert->execute([$routeId,$channel]);
            $this->db->commit(); return ['id'=>$routeId,'display_name'=>$displayName,'provider_code'=>$code,'financial_account_id'=>$financialAccountId,'account_identifier'=>$accountIdentifier,'purpose'=>$purpose,'collection_product'=>$product,'reference_policy'=>$policy,'route_channels'=>implode(',',array_unique($channels))];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function updateRoute(int $id, array $data): array { if ($id<=0) throw new RuntimeException('Collection route ID is required.'); $data['id']=$id; return $this->saveRoute($data); }
    public function deleteRoute(int $id): array { if ($id<=0) throw new RuntimeException('Collection route ID is required.'); $s=$this->db->prepare("UPDATE payment_collection_routes SET active=0 WHERE id=? AND active=1"); $s->execute([$id]); if (!$s->rowCount()) throw new RuntimeException('Collection route not found or already inactive.'); return ['id'=>$id,'status'=>'inactive']; }

    public function listUnmatchedCases(array $filters=[]): array
    { $sql='SELECT u.*,p.code provider_code FROM ' . ReadReplicaService::qualifiedRef('payment_unmatched_cases') . ' u JOIN payment_providers p ON p.id=u.provider_id WHERE u.status=? ORDER BY u.created_at DESC LIMIT 300'; $s=$this->db->prepare($sql); $s->execute([$filters['status']??'unmatched']); return $s->fetchAll(PDO::FETCH_ASSOC); }

    public function resolveCase(int $id, array $data, int $userId): array
    {
        $purpose=(string)($data['purpose']??''); $studentId=(int)($data['student_id']??0); $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("SELECT * FROM payment_unmatched_cases WHERE id=? AND status='unmatched' FOR UPDATE"); $s->execute([$id]); $c=$s->fetch(PDO::FETCH_ASSOC); if (!$c) throw new RuntimeException('Unmatched payment case not found');
            if ($purpose==='fees') { if (!$studentId || !(int)($data['financial_account_id']??0)) throw new RuntimeException('student_id and financial_account_id are required'); $providerTransactionId=(string)($c['provider_transaction_id'] ?: ('UNMATCHED-'.$id)); $this->recordFee($studentId,(float)$c['amount'],$c['provider_code']??'generic_bank',$c['reference_value'] ?: '',$providerTransactionId,json_decode($c['raw_payload'],true)?:[],(int)$data['financial_account_id']); }
            elseif ($purpose==='transport') { $entitlement=(int)($data['entitlement_id']??0); $financialAccountId=(int)($data['financial_account_id']??0); if (!$studentId||!$entitlement||!$financialAccountId) throw new RuntimeException('student_id, entitlement_id and financial_account_id are required'); (new TransportPaymentService($this->db))->reconcileEntitlement($entitlement,$studentId,(float)$c['amount'],$c['provider_code']??'manual',$c['provider_transaction_id'] ?: ('UNMATCHED-'.$id),$financialAccountId); }
            elseif ($purpose==='uniforms') { $saleId=(int)($data['uniform_sale_id']??0); $financialAccountId=(int)($data['financial_account_id']??0); if (!$saleId||!$financialAccountId) throw new RuntimeException('uniform_sale_id and financial_account_id are required'); (new UniformPaymentService($this->db))->recordManualSale($saleId,(float)$c['amount'],$c['provider_transaction_id'] ?: ('UNMATCHED-'.$id),$userId,$financialAccountId); }
            else throw new RuntimeException('purpose must be fees, transport or uniforms');
            $this->db->prepare("UPDATE payment_unmatched_cases SET status='resolved',purpose_candidate=?,resolved_student_id=?,resolved_by=?,resolved_at=NOW() WHERE id=?")->execute([$purpose,$studentId,$userId,$id]); $this->db->commit(); return ['id'=>$id,'status'=>'resolved','purpose'=>$purpose,'student_id'=>$studentId];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    private function recordFee(int $studentId,float $amount,string $provider,string $reference,string $providerTransactionId,array $payload,int $financialAccountId): void
    {
        $reference = $this->normalizer->reference($reference);
        // Admission numbers are reusable parent-facing references, not
        // idempotency keys. Use the provider transaction identity so the same
        // learner can make multiple legitimate fee payments.
        $receiptNo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $provider), 0, 8)) . '-' . strtoupper(substr(hash('sha256', $provider . '|' . $providerTransactionId), 0, 16));
        $s=$this->db->prepare('SELECT id FROM payments WHERE receipt_no=? LIMIT 1'); $s->execute([$receiptNo]); if ($s->fetchColumn()) return;
        // student_parents is a 4NF junction table with a composite key;
        // there is intentionally no surrogate id column to order by.
        $p=$this->db->prepare('SELECT parent_id FROM student_parents WHERE student_id=? ORDER BY is_primary_contact DESC,parent_id ASC LIMIT 1'); $p->execute([$studentId]); $parent=$p->fetchColumn() ?: null;
        $sp=$this->db->prepare('CALL sp_process_student_payment(?,?,?,?,?,?,?,?,?)'); $sp->execute([$studentId,$parent,$amount,$provider==='mpesa_daraja'?'mpesa':'bank_transfer',$reference,$receiptNo,1,date('Y-m-d H:i:s'),'Provider transaction '.$providerTransactionId.' confirmed; routed incoming payment: '.json_encode($payload)]); $sp->closeCursor();
        $find=$this->db->prepare('SELECT id FROM payments WHERE receipt_no=? ORDER BY id DESC LIMIT 1'); $find->execute([$receiptNo]); $paymentId=(int)$find->fetchColumn();
        if (!$paymentId) throw new RuntimeException('Fee payment procedure did not create a payment record.');
        $this->db->prepare('UPDATE payments SET financial_account_id=?,payment_purpose=? WHERE id=?')->execute([$financialAccountId,'fees',$paymentId]);
        $this->posting->postIncoming('payment',$paymentId,$financialAccountId,'fees',(string)$amount,0,$reference);
    }

    private function providerId(string $code): int { $s=$this->db->prepare('SELECT id FROM payment_providers WHERE code=? AND active=1 ORDER BY environment=IF(?="production", "production", "sandbox") DESC,id LIMIT 1'); $env=defined('APP_ENV')?(string)APP_ENV:'sandbox'; $s->execute([$code,$env]); $id=$s->fetchColumn(); if (!$id) throw new RuntimeException('Payment provider is not configured: '.$code); return (int)$id; }
    private function accountPurpose(int $providerId,string $account): ?string { if ($account==='') return null; $s=$this->db->prepare('SELECT purpose FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND active=1'); $s->execute([$providerId,$account]); $rows=$s->fetchAll(PDO::FETCH_COLUMN); if (count($rows)===1) return (string)$rows[0]; if ($this->providerCode($providerId)==='generic_bank') { $s=$this->db->prepare('SELECT purpose FROM payment_collection_routes WHERE normalized_account_identifier=? AND active=1'); $s->execute([$account]); $rows=$s->fetchAll(PDO::FETCH_COLUMN); } return count($rows)===1?(string)$rows[0]:null; }
    private function reference(string $ref): ?array { $normalized=$this->normalizer->reference($ref); if ($normalized==='') return null; $s=$this->db->prepare('SELECT * FROM payment_routing_references WHERE (normalized_reference=? OR reference=?) AND status IN ("active","consumed") AND (expires_at IS NULL OR expires_at>=NOW()) LIMIT 1'); $s->execute([$normalized,$ref]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
    private function prefixPurpose(string $ref): ?string { return $this->normalizer->purposeFromReference($ref); }
    private function accountFinancialId(int $providerId, string $account): ?int { $s=$this->db->prepare('SELECT COALESCE(settlement_financial_account_id,financial_account_id) FROM payment_collection_routes WHERE provider_id=? AND normalized_account_identifier=? AND active=1 ORDER BY id LIMIT 1'); $s->execute([$providerId,$account]); $id=$s->fetchColumn(); if (!$id && $this->providerCode($providerId)==='generic_bank') { $s=$this->db->prepare('SELECT COALESCE(settlement_financial_account_id,financial_account_id) FROM payment_collection_routes WHERE normalized_account_identifier=? AND active=1 ORDER BY id LIMIT 1'); $s->execute([$account]); $id=$s->fetchColumn(); } return $id ? (int)$id : null; }
    private function providerCode(int $providerId): string { $s=$this->db->prepare('SELECT code FROM payment_providers WHERE id=?'); $s->execute([$providerId]); return (string)$s->fetchColumn(); }
    private function channelCode(string $providerCode, array $payload): string { if (!empty($payload['channel'])) return (string)$payload['channel']; if ($providerCode==='mpesa_daraja') return 'mpesa_c2b'; if ($providerCode==='kcb_buni') return 'buni_ipn'; return 'bank_transfer'; }
    private function routeAllowsChannel(int $providerId, string $account, string $purpose, string $channel): bool
    {
        $s=$this->db->prepare('SELECT r.id FROM payment_collection_routes r WHERE r.provider_id=? AND r.normalized_account_identifier=? AND r.purpose=? AND r.active=1 LIMIT 1');
        $s->execute([$providerId,$account,$purpose]); $routeId=(int)$s->fetchColumn();
        if (!$routeId && $this->providerCode($providerId)==='generic_bank') { $s=$this->db->prepare('SELECT r.id FROM payment_collection_routes r WHERE r.normalized_account_identifier=? AND r.purpose=? AND r.active=1 LIMIT 1'); $s->execute([$account,$purpose]); $routeId=(int)$s->fetchColumn(); }
        if (!$routeId) return false;
        $count=$this->db->prepare('SELECT COUNT(*) FROM payment_collection_route_channels WHERE route_id=?'); $count->execute([$routeId]);
        if (!(int)$count->fetchColumn()) return true;
        $allowed=$this->db->prepare('SELECT 1 FROM ' . ReadReplicaService::masterRef('payment_collection_route_channels') . ' rc JOIN financial_channels c ON c.id=rc.channel_id WHERE rc.route_id=? AND c.code=? LIMIT 1'); $allowed->execute([$routeId,$channel]);
        return (bool)$allowed->fetchColumn();
    }
    private function extract(array $payload,array $keys): ?string { foreach ($keys as $key) if (isset($payload[$key]) && $payload[$key]!=='') return (string)$payload[$key]; return null; }
    private function unmatched(int $providerId,string $tx, string $account,float $amount,string $rawReference,string $normalizedReference,string $purpose,string $reason,array $payload): array { $s=$this->db->prepare('INSERT INTO payment_unmatched_cases (provider_id,provider_transaction_id,account_identifier,amount,reference_value,normalized_reference,purpose_candidate,reason,raw_payload) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason),normalized_reference=VALUES(normalized_reference),raw_payload=VALUES(raw_payload)'); $s->execute([$providerId,$tx,$account?:null,$amount,$rawReference?:null,$normalizedReference?:null,$purpose,$reason,json_encode($payload)]); return ['status'=>'unmatched','reason'=>$reason,'provider_transaction_id'=>$tx,'reference'=>$rawReference,'normalized_reference'=>$normalizedReference]; }
}
