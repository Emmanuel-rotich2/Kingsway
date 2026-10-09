<?php

namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use App\API\Services\payments\UniformPaymentService;
use PDO;
use RuntimeException;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

/** Configuration and controlled capture of uniform-store card terminals. */
class PaymentTerminalManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('finance');
    }

    public function listTerminals(): array
    {
        $sql = "SELECT t.*, a.account_name settlement_account_name, a.account_identifier settlement_account_identifier
                FROM " . ReadReplicaService::qualifiedRef("payment_pos_terminals") . "
                JOIN " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " a ON a.id=t.settlement_financial_account_id
                ORDER BY t.terminal_name";
        return formatResponse(true, ['terminals' => $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function createTerminal(array $data, int $userId): array
    {
        $name=trim((string)($data['terminal_name']??''));
        $provider=trim((string)($data['provider_name']??''));
        $terminal=trim((string)($data['terminal_id']??''));
        $settlement=(int)($data['settlement_financial_account_id']??0);
        if ($name==='' || $provider==='' || $settlement<=0) return formatResponse(false,null,'Terminal name, provider and settlement account are required.');
        $this->assertSettlement($settlement);
        try {
            $s=$this->db->prepare('INSERT INTO payment_pos_terminals(terminal_name,provider_name,merchant_id,terminal_id,purpose,settlement_financial_account_id,store_location,credential_profile,status,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $s->execute([$name,$provider,trim((string)($data['merchant_id']??''))?:null,$terminal?:null,'uniforms',$settlement,trim((string)($data['store_location']??''))?:null,trim((string)($data['credential_profile']??''))?:null,'pending_verification',$userId?:null]);
            return formatResponse(true,['id'=>(int)$this->db->lastInsertId()],'POS terminal created and awaiting verification.');
        } catch (\Throwable $e) { return $this->handleException($e); }
    }

    public function updateTerminal(int $id, array $data, int $userId): array
    {
        $settlement=(int)($data['settlement_financial_account_id']??0);
        if ($id<=0 || $settlement<=0) return formatResponse(false,null,'Terminal and settlement account are required.');
        $this->assertSettlement($settlement);
        try {
            $s=$this->db->prepare('UPDATE payment_pos_terminals SET terminal_name=?,provider_name=?,merchant_id=?,terminal_id=?,settlement_financial_account_id=?,store_location=?,credential_profile=?,status=? WHERE id=?');
            $s->execute([trim((string)($data['terminal_name']??'')),trim((string)($data['provider_name']??'')),trim((string)($data['merchant_id']??''))?:null,trim((string)($data['terminal_id']??''))?:null,$settlement,trim((string)($data['store_location']??''))?:null,trim((string)($data['credential_profile']??''))?:null,in_array(($data['status']??'pending_verification'),['active','inactive','pending_verification'],true)?$data['status']:'pending_verification',$id]);
            return formatResponse(true,['id'=>$id],'POS terminal updated.');
        } catch (\Throwable $e) { return $this->handleException($e); }
    }

    public function verifyTerminal(int $id, int $userId): array
    {
        $s=$this->db->prepare("UPDATE payment_pos_terminals SET status='active' WHERE id=?"); $s->execute([$id]);
        if (!$s->rowCount()) return formatResponse(false,null,'POS terminal not found or already active.');
        return formatResponse(true,['id'=>$id,'status'=>'active'],'POS terminal activated.');
    }

    public function recordTransaction(array $data, int $userId): array
    {
        $terminal=(int)($data['terminal_id']??0); $amount=(float)($data['amount']??0); $reference=trim((string)($data['terminal_reference']??''));
        if ($terminal<=0 || $amount<=0 || $reference==='') return formatResponse(false,null,'Terminal, positive amount and terminal reference are required.');
        try {
            $q=$this->db->prepare("SELECT * FROM payment_pos_terminals WHERE id=? AND status='active' LIMIT 1"); $q->execute([$terminal]); $row=$q->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('POS terminal is not active.');
            $intent=(int)($data['uniform_payment_intent_id']??0) ?: null;
            $s=$this->db->prepare('INSERT INTO payment_pos_transactions(terminal_id,uniform_payment_intent_id,purpose,amount,currency,terminal_reference,status,transaction_date,raw_payload,created_by) VALUES(?,?,?,?,?,?,?,COALESCE(?,NOW()),?,?)');
            $s->execute([$terminal,$intent,'uniforms',$amount,$data['currency']??'KES',$reference,($data['status']??'approved'),$data['transaction_date']??null,$data['raw_payload']??null,$userId?:null]);
            $id=(int)$this->db->lastInsertId();
            if ($intent && ($data['status']??'approved') === 'approved') {
                $i=$this->db->prepare('SELECT idempotency_reference FROM uniform_payment_intents WHERE id=?'); $i->execute([$intent]); $intentReference=(string)$i->fetchColumn();
                if ($intentReference && !(new UniformPaymentService($this->db))->reconcileReference($intentReference,$amount,'card_pos',$reference)) throw new RuntimeException('POS transaction recorded but uniform payment intent could not be reconciled.');
                $this->db->prepare("UPDATE payment_pos_transactions SET status='reconciled' WHERE id=?")->execute([$id]);
            }
            return formatResponse(true,['id'=>$id,'status'=>'reconciled'],'POS transaction recorded.');
        } catch (\Throwable $e) { return $this->handleException($e); }
    }

    private function assertSettlement(int $id): void
    {
        $s=$this->db->prepare("SELECT a.id FROM " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " JOIN financial_account_kinds k ON k.id=a.account_kind_id WHERE a.id=? AND a.status='active' AND k.code IN ('bank','cash','clearing') LIMIT 1"); $s->execute([$id]);
        if (!$s->fetchColumn()) throw new RuntimeException('Settlement account must be an active school financial account.');
    }
}
