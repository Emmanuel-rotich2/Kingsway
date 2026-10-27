<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use App\API\Services\ReadReplicaService;
use App\API\Services\FinancialPostingCoordinator;
use App\API\Services\ServiceContractBroker;
use PDO;
use RuntimeException;

/** Uniform sales collection. Uniforms are optional store revenue, not fees. */
class UniformPaymentService
{
    private $db;
    private $accounts;
    private $posting;
    public function __construct(PDO $db) { $this->db=$db; $this->accounts=new FinancialAccountService($db); $this->posting=new FinancialPostingCoordinator($db); }

    public function initiate(array $data, int $userId): array
    {
        $saleId=(int)($data['sale_id']??0); $method=CollectionMethods::normalize((string)($data['channel']??'')); $amount=(float)($data['amount']??0);
        if (!$saleId || !$amount) throw new RuntimeException('sale_id, payment method and amount are required');
        $sale=$this->sale($saleId); if (!$sale) throw new RuntimeException('Uniform sale not found'); $this->ensureWithinBalance($sale,$amount);
        $financialAccountId=(int)($data['financial_account_id']??0);
        if (CollectionMethods::isPrompt($method)) {
            // The client submits only the method. The rail is a system
            // decision: purpose + configured collection-point priority.
            $candidates=(new CollectionRailResolver($this->db))->candidates('uniforms',$financialAccountId);
            if ($candidates===[]) throw new RuntimeException('No active online collection rail is configured for uniforms.');
            $accountId=(int)$candidates[0]['financial_account_id']; $intentChannel=CollectionMethods::PROMPT;
        } else {
            $account=$this->accounts->requireFor($financialAccountId,'uniforms',$this->channelCode($method));
            $accountId=(int)$account['id']; $intentChannel=$method;
        }
        $ref='U-'.$sale['admission_no'].'-'.$saleId.'-'.strtoupper(bin2hex(random_bytes(3))); $status=CollectionMethods::isManual($method)?'manual_review':'pending';
        $i=$this->db->prepare("INSERT INTO uniform_payment_intents (sale_id,student_id,financial_account_id,channel,amount,idempotency_reference,status,request_payload,created_by) VALUES (?,?,?,?,?,?,?,?,?)"); $i->execute([$saleId,$sale['student_id'],$accountId,$intentChannel,$amount,$ref,$status,json_encode($data),$userId]); $id=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO payment_routing_references (reference,normalized_reference,purpose,student_id,uniform_sale_id,expires_at) VALUES (?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 30 DAY))")->execute([$ref,(new ReferenceNormalizer())->reference($ref),'uniforms',$sale['student_id'],$saleId]);
        if (CollectionMethods::isPrompt($method)) {
            $result=(new CollectionFallbackService($this->db))->initiate('uniforms',['amount'=>$amount,'phone'=>$data['phone']??$data['phone_number']??'','reference'=>$ref,'customer_name'=>$sale['student_name']??'','financial_account_id'=>$financialAccountId]);
            $this->updateProvider($id,$result,$result['request_id']);
            if (empty($result['accepted'])) throw new RuntimeException($result['message']?:'No configured collection rail accepted the uniform payment.');
            // Keep the method; only the resolving account is the system's business.
            $this->db->prepare('UPDATE uniform_payment_intents SET financial_account_id=? WHERE id=?')->execute([(int)$result['financial_account_id'],$id]);
        }
        return $this->get($id);
    }

    private function channelCode(string $method): string { return ['mobile_money'=>'safaricom_c2b','c2b_mpesa'=>'safaricom_c2b','bank_deposit'=>'bank_transfer','bank_transfer'=>'bank_transfer','cash'=>'cash','cheque'=>'cheque'][$method] ?? $method; }

    /** Create one STK/Buni request for all outstanding uniform sales of a learner. */
    public function initiateAccumulated(array $data, int $userId): array
    {
        $studentId=(int)($data['student_id']??0); $amount=(float)($data['amount']??0); $method=CollectionMethods::normalize((string)($data['channel']??'auto'));
        if(!$studentId||$amount<=0||!CollectionMethods::isPrompt($method))throw new RuntimeException('student_id, amount and a prompt payment method are required');
        $financialAccountId=(int)($data['financial_account_id']??0);
        $candidates=(new CollectionRailResolver($this->db))->candidates('uniforms',$financialAccountId);
        if ($candidates===[]) throw new RuntimeException('No active online collection rail is configured for uniforms.');
        $accountId=(int)$candidates[0]['financial_account_id']; $intentChannel=CollectionMethods::PROMPT;
        $s=$this->db->prepare("SELECT us.*,us.quantity*us.unit_price AS total_amount,COALESCE((SELECT SUM(amount) FROM uniform_payment_records upr WHERE upr.sale_id=us.id),0) paid_amount FROM uniform_sales us WHERE us.student_id=? AND us.payment_status IN ('pending','partial') ORDER BY us.sale_date,us.id FOR UPDATE");
        $s->execute([$studentId]);$sales=$s->fetchAll(PDO::FETCH_ASSOC);$outstanding=0;foreach($sales as $sale)$outstanding+=max(0,(float)$sale['total_amount']-(float)$sale['paid_amount']);if($amount>$outstanding+0.01)throw new RuntimeException('Amount exceeds accumulated uniform balance');
        $st=$this->db->prepare('SELECT admission_no FROM students WHERE id=?');$st->execute([$studentId]);$admission=(string)$st->fetchColumn();$ref='UC-'.$admission.'-'.strtoupper(bin2hex(random_bytes(4)));
        $parentId=(int)($data['parent_id']??0);
        if (!$parentId) throw new RuntimeException('A verified parent is required for accumulated checkout');
        $this->db->beginTransaction();
        try {
            $this->db->prepare("INSERT INTO uniform_catalog_orders(parent_id,student_id,order_reference,status,total_amount) VALUES(?,?,?,?,?)")
                ->execute([$parentId,$studentId,$ref,'pending_payment',$amount]);
            $orderId=(int)$this->db->lastInsertId();
            $remaining=$amount;
            $productLookup=$this->db->prepare("SELECT id FROM uniform_catalog_products WHERE item_id=? AND status='active' AND published=1 LIMIT 1");
            $sizeLookup=$this->db->prepare('SELECT id FROM uniform_sizes WHERE item_id=? AND size=? LIMIT 1');
            $lineInsert=$this->db->prepare('INSERT INTO uniform_catalog_order_items(order_id,product_id,size_id,sale_id,quantity,unit_price) VALUES(?,?,?,?,?,?)');
            foreach($sales as $sale){
                $due=max(0,(float)$sale['total_amount']-(float)$sale['paid_amount']);
                if($due<=0||$remaining<=0)continue;
                $line=min($remaining,$due);
                $sizeLookup->execute([$sale['item_id'],$sale['size']]);
                $sizeId=(int)$sizeLookup->fetchColumn();
                if(!$sizeId)throw new RuntimeException('Uniform size record is missing for sale '.$sale['id']);
                $productLookup->execute([$sale['item_id']]);
                $productId=(int)$productLookup->fetchColumn() ?: null;
                $lineInsert->execute([$orderId,$productId,$sizeId,$sale['id'],1,$line]);
                $remaining-=$line;
            }
            $this->db->prepare("INSERT INTO uniform_payment_intents(sale_id,order_id,student_id,financial_account_id,channel,amount,idempotency_reference,status,request_payload,created_by) VALUES(NULL,?,?,?,?,?,?,?,?,?)")
                ->execute([$orderId,$studentId,$accountId,$intentChannel,$amount,$ref,'pending',json_encode($data),$userId]);
            $intentId=(int)$this->db->lastInsertId();
            $this->db->prepare("INSERT INTO payment_routing_references(reference,normalized_reference,purpose,student_id,expires_at) VALUES(?,?,?, ?,DATE_ADD(NOW(),INTERVAL 30 DAY))")
                ->execute([$ref,(new ReferenceNormalizer())->reference($ref),'uniforms',$studentId]);
            $this->db->commit();
            if(CollectionMethods::isPrompt($method)){
                $result=(new CollectionFallbackService($this->db))->initiate('uniforms',['amount'=>$amount,'phone'=>$data['phone']??'','reference'=>$ref,'financial_account_id'=>$financialAccountId]);
                $this->updateProvider($intentId,$result,$result['request_id']);
                if(empty($result['accepted']))throw new RuntimeException($result['message']?:'No configured collection rail accepted the uniform payment.');
                // The method stays 'prompt'; only the resolving account changes.
                $this->db->prepare('UPDATE uniform_payment_intents SET financial_account_id=? WHERE id=?')->execute([(int)$result['financial_account_id'],$intentId]);
            }
            return $this->get($intentId);
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function reconcileReference(string $reference,float $amount,string $provider,string $providerReference, ?int $financialAccountId = null): bool
    {
        $reference = (new ReferenceNormalizer())->reference($reference);
        $commerce = new \App\API\Services\catalog\CatalogCommerceService($this->db);
        if ($commerce->confirmByReference($reference,$amount,$provider,$providerReference)) return true;
        // Money path: the intent and the sale are written moments earlier by
        // the initiation call, so they must be read from the authoritative
        // primary. A materialized projection is rebuilt on a schedule and
        // would report them as missing, returning the confirmed payment as
        // unmatched with the money already collected.
        $s=$this->db->prepare("SELECT i.* FROM " . ReadReplicaService::masterRef("uniform_payment_intents") . " i JOIN payment_routing_references r ON r.reference=? AND r.purpose='uniforms' WHERE i.idempotency_reference=? LIMIT 1"); $s->execute([$reference,$reference]); $intent=$s->fetch(PDO::FETCH_ASSOC);
        if (!$intent) { $r=$this->db->prepare("SELECT r.uniform_sale_id FROM payment_routing_references r WHERE r.reference=? AND r.purpose='uniforms' AND r.status IN ('active','consumed') AND (r.expires_at IS NULL OR r.expires_at>=NOW())"); $r->execute([$reference]); $saleId=(int)$r->fetchColumn(); if(!$saleId||!$financialAccountId)return false; $sale=$this->sale($saleId); if(!$sale)return false; $this->record($sale,$amount,$provider,$providerReference,0,$financialAccountId); return true; }
        if ($intent['status']==='confirmed') return true; if (abs((float)$intent['amount']-$amount)>0.01) return false;
        $financialAccountId=(int)$intent['financial_account_id']; if(!$financialAccountId)return false; if (!empty($intent['order_id'])) { $lines=$this->db->prepare('SELECT sale_id FROM uniform_catalog_order_items WHERE order_id=? AND sale_id IS NOT NULL ORDER BY id');$lines->execute([$intent['order_id']]);$remaining=$amount;foreach($lines->fetchAll(PDO::FETCH_COLUMN) as $saleId){if($remaining<=0)break;$sale=$this->sale((int)$saleId);$due=max(0,(float)$sale['unit_price']*(int)$sale['quantity']-(float)$this->paid((int)$saleId));$part=min($remaining,$due);if($part>0){$this->record($sale,$part,$provider,$providerReference.'-'.$saleId,0,$financialAccountId);$remaining-=$part;}}} else {$sale=$this->sale((int)$intent['sale_id']); $this->record($sale,$amount,$provider,$providerReference,0,$financialAccountId);}$this->db->prepare("UPDATE uniform_payment_intents SET status='confirmed',provider_transaction_id=?,confirmed_at=NOW() WHERE id=?")->execute([$providerReference,$intent['id']]); return true;
    }

    public function confirmManual(int $intentId,int $userId): array
    { $i=$this->get($intentId); if(!$i||$i['status']!=='manual_review')throw new RuntimeException('Uniform payment is not awaiting manual confirmation'); $sale=$this->sale((int)$i['sale_id']); $this->record($sale,(float)$i['amount'],'manual',$i['idempotency_reference'],$userId,(int)$i['financial_account_id']); $this->db->prepare("UPDATE uniform_payment_intents SET status='confirmed',provider_transaction_id=?,confirmed_at=NOW() WHERE id=?")->execute([$i['idempotency_reference'],$intentId]); return $this->get($intentId); }
    public function recordManualSale(int $saleId, float $amount, string $reference, int $userId, ?int $financialAccountId = null): array
    { $sale=$this->sale($saleId); if(!$sale||!$financialAccountId)throw new RuntimeException('Uniform sale and receiving account are required'); $this->record($sale,$amount,'manual',$reference ?: 'UNMATCHED-'.bin2hex(random_bytes(4)),$userId,$financialAccountId); return $sale; }
    public function get(int $id): array { $s=$this->db->prepare('SELECT * FROM uniform_payment_intents WHERE id=?');$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:[]; }
    // Keyed money-path read: the sale being paid is a row the initiation call
    // wrote seconds ago, so the authoritative primary is the only source that
    // can see it. See reconcileReference().
    private function sale(int $id): array { $s=$this->db->prepare("SELECT us.*,s.admission_no,CONCAT(p.first_name,' ',p.last_name) student_name FROM " . ReadReplicaService::masterRef("uniform_sales") . " us JOIN " . ReadReplicaService::masterRef("students") . " s ON s.id=us.student_id JOIN " . ReadReplicaService::masterRef("persons") . " p ON p.id=s.person_id WHERE us.id=?");$s->execute([$id]);return $s->fetch(PDO::FETCH_ASSOC)?:[]; }
    private function ensureWithinBalance(array $sale,float $amount): void { $total=(float)$sale['unit_price']*(int)$sale['quantity'];$s=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM uniform_payment_records WHERE sale_id=?');$s->execute([$sale['id']]);if($amount>max(0,$total-(float)$s->fetchColumn())+0.01)throw new RuntimeException('Uniform payment exceeds outstanding sale balance'); }
    private function paid(int $saleId): float { $s=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM uniform_payment_records WHERE sale_id=?');$s->execute([$saleId]);return (float)$s->fetchColumn(); }
    private function record(array $sale,float $amount,string $provider,string $reference,int $userId,int $financialAccountId): void { if(!$sale)throw new RuntimeException('Uniform sale not found');
        if ($reference !== '') { $duplicate=$this->db->prepare('SELECT id FROM uniform_payment_records WHERE reference_no=? LIMIT 1'); $duplicate->execute([$reference]); if ($duplicate->fetchColumn()) return; }
        $this->ensureWithinBalance($sale,$amount);$this->db->prepare('INSERT INTO uniform_payment_records (sale_id,financial_account_id,amount,payment_date,payment_method,reference_no,recorded_by,notes) VALUES (?,?,?,CURDATE(),?,?,?,?)')->execute([$sale['id'],$financialAccountId,$amount,in_array(ProviderCodes::canonical($provider),['safaricom_daraja','kcb_buni','payhero'],true)?'mpesa':$provider,$reference,$userId?:null,'Confirmed optional uniform purchase payment']);$recordId=(int)$this->db->lastInsertId();$s=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM uniform_payment_records WHERE sale_id=?');$s->execute([$sale['id']]);$paid=(float)$s->fetchColumn();$total=(float)$sale['unit_price']*(int)$sale['quantity'];$this->db->prepare('UPDATE uniform_sales SET payment_status=?,received_date=IF(? >= ?,CURDATE(),received_date),updated_at=NOW() WHERE id=?')->execute([$paid+0.01>=$total?'paid':($paid>0?'partial':'pending'),$paid,$total,$sale['id']]);$this->posting->postIncoming('uniform_payment_record',$recordId,$financialAccountId,'uniforms',(string)$amount,$userId,$reference);$this->notify($sale,$amount,$reference); }
    private function notify(array $sale,float $amount,string $reference): void { try{$s=$this->db->prepare("SELECT st.phone, st.email FROM " . ReadReplicaService::masterRef('person_directory') . " st WHERE st.student_id=?");$s->execute([$sale['student_id']]);$p=$s->fetch(PDO::FETCH_ASSOC)?:[];$m=ServiceContractBroker::contract('App\API\Modules\communications\CommunicationsManager', [], $this->db);$body='Kingsway uniform purchase payment received: KES '.number_format($amount,2).' for '.$sale['student_name'].'. Ref '.$reference.'.';foreach(['sms'=>'phone','whatsapp'=>'phone','email'=>'email'] as $type=>$field)if(!empty($p[$field]))$m->createCommunication(['sender_id'=>1,'subject'=>'Uniform payment received','body'=>$body,'type'=>$type,'status'=>'sent','priority'=>'normal','recipients'=>[$p[$field]]]);}catch(\Throwable $e){\App\API\Services\Logger::legacyError('[UniformPaymentService] notification failed: '.$e->getMessage());} }
    private function updateProvider(int $id,array $result,?string $request): void { $ok=($result['accepted']??false)||(($result['status']??'')==='pending');$this->db->prepare('UPDATE uniform_payment_intents SET status=?,provider_request_id=?,response_payload=? WHERE id=?')->execute([$ok?'accepted':'failed',$request,json_encode($result),$id]); }
}
