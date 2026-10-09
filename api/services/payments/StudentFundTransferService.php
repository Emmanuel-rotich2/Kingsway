<?php
declare(strict_types=1);

namespace App\API\Services\payments;

use PDO;
use RuntimeException;
use App\API\Services\ReadReplicaService;

/** Posts controlled internal movements between fee and transport credits. */
class StudentFundTransferService
{
    private $db;

    public function __construct(PDO $db) { $this->db = $db; }

    public function create(array $data, int $userId): array
    {
        $sourceStudent = (int)($data['source_student_id'] ?? 0);
        $destinationStudent = (int)($data['destination_student_id'] ?? 0);
        $sourceType = strtolower((string)($data['source_account_type'] ?? ''));
        $destinationType = strtolower((string)($data['destination_account_type'] ?? ''));
        $amount = (float)($data['amount'] ?? 0);
        if (!$sourceStudent || !$destinationStudent || !in_array($sourceType, ['fees','transport'], true) || !in_array($destinationType, ['fees','transport'], true) || $amount <= 0 || empty($data['reason'])) {
            throw new RuntimeException('Both students, account types, amount and reason are required');
        }
        if ($sourceType === 'fees' && !(int)($data['source_credit_note_id'] ?? 0)) throw new RuntimeException('A source fee credit note is required');
        if ($sourceType === 'transport' && !(int)($data['source_entitlement_id'] ?? 0)) throw new RuntimeException('A source transport entitlement is required');
        if ($destinationType === 'transport' && !(int)($data['destination_entitlement_id'] ?? 0)) throw new RuntimeException('A destination transport entitlement is required');
        $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
        if ($parentId) {
            $p = $this->db->prepare('SELECT 1 FROM student_parents WHERE parent_id=? AND student_id IN (?,?) LIMIT 1');
            $p->execute([$parentId, $sourceStudent, $destinationStudent]);
            if (!$p->fetchColumn()) throw new RuntimeException('The parent is not linked to either student');
        }
        $this->validateDestination($destinationType, $destinationStudent, (int)($data['destination_entitlement_id'] ?? 0));
        $reference = trim((string)($data['parent_request_reference'] ?? '')) ?: null;
        $stmt = $this->db->prepare("INSERT INTO student_fund_transfers
            (source_student_id,source_account_type,source_credit_note_id,source_entitlement_id,destination_student_id,destination_account_type,destination_entitlement_id,amount,parent_id,parent_request_reference,reason,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$sourceStudent,$sourceType,(int)($data['source_credit_note_id'] ?? 0) ?: null,(int)($data['source_entitlement_id'] ?? 0) ?: null,$destinationStudent,$destinationType,(int)($data['destination_entitlement_id'] ?? 0) ?: null,$amount,$parentId,$reference,trim((string)$data['reason']),$userId]);
        return $this->get((int)$this->db->lastInsertId());
    }

    public function decide(int $id, string $decision, int $userId): array
    {
        if (!in_array($decision, ['approve','reject'], true)) throw new RuntimeException('Decision must be approve or reject');
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $s = $this->db->prepare("UPDATE student_fund_transfers SET status=?, approved_by=?, approved_at=NOW() WHERE id=? AND status='pending_approval'");
        $s->execute([$status,$userId,$id]);
        if (!$s->rowCount()) throw new RuntimeException('Transfer is not awaiting approval');
        return $this->get($id);
    }

    public function post(int $id, int $userId): array
    {
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare('SELECT * FROM student_fund_transfers WHERE id=? FOR UPDATE'); $s->execute([$id]); $t=$s->fetch(PDO::FETCH_ASSOC);
            if (!$t || $t['status'] !== 'approved') throw new RuntimeException('Only an approved transfer can be posted');
            $this->currentTransferId = $id;
            $amount=(float)$t['amount'];
            if ($t['source_account_type'] === 'fees') $this->debitFeeCredit((int)$t['source_credit_note_id'],$amount,(int)$t['source_student_id']);
            else $this->debitTransport((int)$t['source_entitlement_id'],$amount,(int)$t['source_student_id']);
            if ($t['destination_account_type'] === 'fees') $destinationId=$this->creditFeeAccount((int)$t['destination_student_id'],$amount,$id,(int)$t['created_by']);
            else $destinationId=$this->creditTransport((int)$t['destination_entitlement_id'],(int)$t['destination_student_id'],$amount,$id);
            $this->db->prepare("UPDATE student_fund_transfers SET status='posted',posted_by=?,posted_at=NOW() WHERE id=?")->execute([$userId,$id]);
            $this->db->commit();
            return $this->get($id)+['destination_record_id'=>$destinationId];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function list(array $filters=[]): array
    {
        $sql='SELECT t.*, CONCAT(ps.first_name," ",ps.last_name) source_student_name, CONCAT(pd.first_name," ",pd.last_name) destination_student_name FROM student_fund_transfers t JOIN ' . ReadReplicaService::qualifiedRef('students') . ' ss ON ss.id=t.source_student_id JOIN ' . ReadReplicaService::qualifiedRef('persons') . ' ps ON ps.id=ss.person_id JOIN ' . ReadReplicaService::qualifiedRef('students') . ' sd ON sd.id=t.destination_student_id JOIN ' . ReadReplicaService::qualifiedRef('persons') . ' pd ON pd.id=sd.person_id';
        $params=[]; if (!empty($filters['status'])) { $sql.=' WHERE t.status=?'; $params[]=$filters['status']; } $sql.=' ORDER BY t.created_at DESC';
        $s=$this->db->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_ASSOC);
    }

    public function sources(): array
    {
        $fees=$this->db->query("SELECT c.id AS source_id,c.student_id,(SELECT sp.parent_id FROM " . ReadReplicaService::qualifiedRef("student_parents") . " sp WHERE sp.student_id=c.student_id ORDER BY sp.is_primary_contact DESC,sp.parent_id LIMIT 1) AS parent_id,'fees' AS account_type,c.credit_number AS reference,c.remaining_amount AS available_amount,CONCAT(p.first_name,' ',p.last_name) AS student_name FROM " . ReadReplicaService::qualifiedRef("fee_credit_notes") . " c JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id=c.student_id JOIN " . ReadReplicaService::qualifiedRef("persons") . " p ON p.id=s.person_id WHERE c.status IN ('available','partially_applied') AND c.remaining_amount>0 ORDER BY student_name")->fetchAll(PDO::FETCH_ASSOC);
        $transport=$this->db->query("SELECT e.id AS source_id,e.student_id,(SELECT sp.parent_id FROM " . ReadReplicaService::qualifiedRef("student_parents") . " sp WHERE sp.student_id=e.student_id ORDER BY sp.is_primary_contact DESC,sp.parent_id LIMIT 1) AS parent_id,'transport' AS account_type,CONCAT(COALESCE(ep.label,ep.period_type),' · ',ep.period_start,' to ',ep.period_end) AS reference, GREATEST(0,COALESCE((SELECT SUM(a.amount) FROM " . ReadReplicaService::qualifiedRef("transport_entitlement_payment_allocations") . " a JOIN transport_entitlement_payments p ON p.id=a.payment_id WHERE a.entitlement_id=e.id AND p.payment_status='confirmed'),0)-COALESCE((SELECT SUM(amount) FROM student_fund_transfer_postings WHERE posting_type='transport_debit' AND source_record_id=e.id),0)) AS available_amount,CONCAT(per.first_name,' ',per.last_name) AS student_name FROM " . ReadReplicaService::qualifiedRef("student_transport_entitlements") . " e JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON s.id=e.student_id JOIN " . ReadReplicaService::qualifiedRef("persons") . " per ON per.id=s.person_id JOIN transport_entitlement_periods ep ON ep.id=e.period_id WHERE e.entitlement_status='active' HAVING available_amount>0 ORDER BY student_name")->fetchAll(PDO::FETCH_ASSOC);
        return ['sources'=>array_merge($fees,$transport)];
    }

    public function get(int $id): array
    { $s=$this->db->prepare('SELECT * FROM student_fund_transfers WHERE id=?'); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: []; }

    private function debitFeeCredit(int $creditId, float $amount, int $studentId): void
    {
        $s=$this->db->prepare("SELECT * FROM fee_credit_notes WHERE id=? AND student_id=? AND status IN ('available','partially_applied') FOR UPDATE"); $s->execute([$creditId,$studentId]); $c=$s->fetch(PDO::FETCH_ASSOC);
        if (!$c || (float)$c['remaining_amount'] < $amount) throw new RuntimeException('Insufficient available fee credit');
        $new=(float)$c['applied_amount']+$amount; $status=$new >= (float)$c['credit_amount'] ? 'fully_applied' : 'partially_applied';
        $this->db->prepare('UPDATE fee_credit_notes SET applied_amount=?,status=?,updated_at=NOW() WHERE id=?')->execute([$new,$status,$creditId]);
        $this->db->prepare("INSERT INTO student_fund_transfer_postings (transfer_id,posting_type,source_record_id,amount) VALUES (?,?,?,?)")->execute([$this->currentTransferId,'fee_credit_debit',$creditId,$amount]);
    }

    private function debitTransport(int $entitlementId, float $amount, int $studentId): void
    {
        $s=$this->db->prepare('SELECT id FROM student_transport_entitlements WHERE id=? AND student_id=? AND entitlement_status="active" FOR UPDATE'); $s->execute([$entitlementId,$studentId]);
        if (!$s->fetchColumn()) throw new RuntimeException('Source transport entitlement is invalid');
        $s=$this->db->prepare("SELECT COALESCE(SUM(a.amount),0) FROM " . ReadReplicaService::qualifiedRef("transport_entitlement_payment_allocations") . " JOIN transport_entitlement_payments p ON p.id=a.payment_id WHERE a.entitlement_id=? AND p.payment_status='confirmed'"); $s->execute([$entitlementId]); $credited=(float)$s->fetchColumn();
        $s=$this->db->prepare("SELECT COALESCE(SUM(amount),0) FROM student_fund_transfer_postings WHERE posting_type='transport_debit' AND source_record_id=?"); $s->execute([$entitlementId]); $debited=(float)$s->fetchColumn();
        if (($credited-$debited) < $amount) throw new RuntimeException('Insufficient available transport credit');
        $this->db->prepare("INSERT INTO student_fund_transfer_postings (transfer_id,posting_type,source_record_id,amount) VALUES (?,?,?,?)")->execute([$this->currentTransferId,'transport_debit',$entitlementId,$amount]);
    }

    private function creditFeeAccount(int $studentId, float $amount, int $transferId, int $createdBy): int
    {
        $year=(int)date('Y'); $s=$this->db->query('SELECT year FROM academic_years WHERE is_current=1 LIMIT 1'); $year=(int)($s->fetchColumn() ?: $year);
        $ref='TRF-'.$transferId.'-FEE'; $i=$this->db->prepare("INSERT INTO fee_credit_notes (credit_number,student_id,academic_year,credit_amount,credit_reason,status,applied_amount,notes,created_by) VALUES (?,?,?,?,'error_correction','available',0,?,?)"); $i->execute([$ref,$studentId,$year,$amount,'Internal fund transfer credit',$createdBy]); $id=(int)$this->db->lastInsertId();
        $this->db->prepare("INSERT INTO student_fund_transfer_postings (transfer_id,posting_type,destination_record_id,amount) VALUES (?,?,?,?)")->execute([$transferId,'fee_credit_credit',$id,$amount]); return $id;
    }

    private function creditTransport(int $entitlementId, int $studentId, float $amount, int $transferId): int
    {
        $this->validateDestination('transport',$studentId,$entitlementId); $i=$this->db->prepare("INSERT INTO transport_entitlement_payments (student_id,amount,payment_method,provider_name,provider_reference,payment_status,payment_date,received_by,notes) VALUES (?,?, 'internal_transfer','internal_transfer',?,'confirmed',CURDATE(),NULL,?)"); $i->execute([$studentId,$amount,'TRF-'.$transferId,'Transfer from student transport/fee credit']); $paymentId=(int)$this->db->lastInsertId(); $this->db->prepare('INSERT INTO transport_entitlement_payment_allocations (payment_id,entitlement_id,amount) VALUES (?,?,?)')->execute([$paymentId,$entitlementId,$amount]); $this->db->prepare("INSERT INTO student_fund_transfer_postings (transfer_id,posting_type,destination_record_id,amount) VALUES (?,?,?,?)")->execute([$transferId,'transport_credit',$entitlementId,$amount]); return $paymentId;
    }

    private function validateDestination(string $type,int $studentId,int $entitlementId): void
    { if ($type==='transport') { $s=$this->db->prepare('SELECT 1 FROM student_transport_entitlements WHERE id=? AND student_id=? AND entitlement_status="active"'); $s->execute([$entitlementId,$studentId]); if (!$s->fetchColumn()) throw new RuntimeException('Destination transport entitlement is invalid'); } }

    private $currentTransferId;
}
