<?php

namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\payments\ReferenceNormalizer;
use App\API\Services\ReadReplicaService;

/**
 * AccountsManager
 *
 * Bank account and bank transaction business logic.
 */
class AccountsManager extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('finance');
    }

    public function listFinancialAccounts(): array
    {
        $stmt = $this->db->query("SELECT a.*, k.code account_kind, p.code provider_code, c.account_code ledger_code,
            sa.account_name settlement_account_name, sa.account_identifier settlement_account_identifier,
            GROUP_CONCAT(DISTINCT r.collection_product ORDER BY r.collection_product SEPARATOR ',') collection_products,
            GROUP_CONCAT(DISTINCT r.reference_policy ORDER BY r.reference_policy SEPARATOR ',') reference_policies,
            GROUP_CONCAT(DISTINCT fp.code ORDER BY fp.code SEPARATOR ',') purposes
            FROM " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " a
            JOIN financial_account_kinds k ON k.id=a.account_kind_id
            LEFT JOIN payment_providers p ON p.id=a.provider_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("chart_of_accounts") . " c ON c.id=a.ledger_account_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " sa ON sa.id=a.settlement_financial_account_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("payment_collection_routes") . " r ON r.financial_account_id=a.id AND r.active=1
            LEFT JOIN school_financial_account_purposes ap ON ap.financial_account_id=a.id
            LEFT JOIN financial_account_purposes fp ON fp.id=ap.purpose_id
            GROUP BY a.id ORDER BY a.account_name");
        return formatResponse(true, ['accounts' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function financialAccountSetupOptions(): array
    {
        return formatResponse(true, [
            'kinds' => $this->db->query('SELECT code,name FROM financial_account_kinds ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            'purposes' => $this->db->query('SELECT code,name FROM financial_account_purposes ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            'channels' => $this->db->query('SELECT code,name FROM financial_channels ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            'providers' => $this->db->query("SELECT code,display_name,environment FROM payment_providers WHERE active=1 ORDER BY display_name")->fetchAll(PDO::FETCH_ASSOC),
            'ledger_accounts' => $this->db->query("SELECT account_code,account_name FROM chart_of_accounts WHERE status='active' AND is_postable=1 ORDER BY account_code")->fetchAll(PDO::FETCH_ASSOC),
            'settlement_accounts' => $this->db->query("SELECT a.id,a.account_name,a.account_identifier,k.code account_kind FROM " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " JOIN financial_account_kinds k ON k.id=a.account_kind_id WHERE a.status IN ('active','pending_verification') AND k.code IN ('bank','cash','clearing') ORDER BY a.account_name")->fetchAll(PDO::FETCH_ASSOC),
            'roles' => $this->db->query("SELECT id,name FROM roles WHERE id NOT IN (2) ORDER BY name")->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function updateFinancialAccount(int $id, array $data, int $userId): array
    {
        $name=trim((string)($data['account_name']??'')); $identifier=trim((string)($data['account_identifier']??''));
        $purposes=[]; $hasChannels=array_key_exists('channels',$data); $channels=array_values(array_filter((array)($data['channels']??[])));
        if ($id<=0 || $name==='' || $identifier==='') return formatResponse(false,null,'Account name, identifier and transaction purposes are required');
        try {
            $this->db->beginTransaction();
            $old=$this->db->prepare('SELECT * FROM school_financial_accounts WHERE id=? FOR UPDATE'); $old->execute([$id]); $before=$old->fetch(PDO::FETCH_ASSOC);
            if (!$before) throw new Exception('Financial account not found');
            $kindCodeStmt=$this->db->prepare('SELECT code FROM financial_account_kinds WHERE id=?'); $kindCodeStmt->execute([(int)$before['account_kind_id']]);
            $kindCode=(string)$kindCodeStmt->fetchColumn(); $purposes=$this->purposeCodesForAccount($data,$kindCode);
            if (!$purposes) throw new Exception('Select at least one transaction or collection purpose.');
            $ledger=$this->db->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active' AND is_postable=1"); $ledger->execute([(string)($data['ledger_code']??$before['ledger_account_id'])]);
            $ledgerId=(int)$ledger->fetchColumn(); if(!$ledgerId) throw new Exception('A valid postable ledger account is required');
            $providerId = $before['provider_id'];
            if (array_key_exists('provider_code', $data)) {
                $providerId = null;
                if ((string)$data['provider_code'] !== '') {
                    $provider = $this->db->prepare('SELECT id FROM payment_providers WHERE code=? AND active=1 ORDER BY id DESC LIMIT 1');
                    $provider->execute([(string)$data['provider_code']]);
                    $providerId = (int)$provider->fetchColumn() ?: null;
                    if (!$providerId) throw new Exception('Unknown payment provider');
                }
            }
            $settlementId=$this->settlementAccountId($data, $id, $kindCode);
            $this->db->prepare('UPDATE school_financial_accounts SET account_name=?,provider_id=?,settlement_financial_account_id=?,account_identifier=?,normalized_account_identifier=?,bank_name=?,currency=?,ledger_account_id=? WHERE id=?')
                ->execute([$name,$providerId,$settlementId,$identifier,(new ReferenceNormalizer())->accountIdentifier($identifier),$data['bank_name']??$before['bank_name'],$data['currency']??$before['currency'],$ledgerId,$id]);
            $this->db->prepare('DELETE FROM school_financial_account_purposes WHERE financial_account_id=?')->execute([$id]);
            $p=$this->db->prepare('SELECT id FROM financial_account_purposes WHERE code=?'); $pi=$this->db->prepare('INSERT INTO school_financial_account_purposes(financial_account_id,purpose_id) VALUES(?,?)');
            foreach($purposes as $code){$pid=$this->purposeId((string)$code,$data);$pi->execute([$id,$pid]);}
            if ($hasChannels) {
                $this->db->prepare('DELETE FROM school_financial_account_channels WHERE financial_account_id=?')->execute([$id]);
                $c=$this->db->prepare('SELECT id FROM financial_channels WHERE code=?'); $ci=$this->db->prepare('INSERT INTO school_financial_account_channels(financial_account_id,channel_id) VALUES(?,?)');
                foreach($channels as $code){$c->execute([$code]);$cid=(int)$c->fetchColumn();if(!$cid)throw new Exception('Unknown financial channel: '.$code);$ci->execute([$id,$cid]);}
                $this->db->prepare('DELETE rc FROM payment_collection_route_channels rc JOIN payment_collection_routes r ON r.id=rc.route_id LEFT JOIN school_financial_account_channels ac ON ac.financial_account_id=r.financial_account_id AND ac.channel_id=rc.channel_id WHERE r.financial_account_id=? AND ac.channel_id IS NULL')->execute([$id]);
            }
            // Collection points have their own CRUD workflow. Editing a real
            // bank/cash account must never remove or rewrite its routes.
            if (!empty($data['sync_collection_routes'])) {
                $data['collection_purposes'] = array_key_exists('collection_purposes', $data)
                    ? array_values(array_intersect(['fees','transport','uniforms'], (array)$data['collection_purposes']))
                    : array_values(array_intersect(['fees','transport','uniforms'], $purposes));
                $this->syncCollectionRoutes($id, $providerId, $identifier, $data['collection_purposes'], $settlementId, $data);
            }
            $this->syncFeeStructureDisplay($id, $data, $userId);
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,before_state,after_state) VALUES(?,?,?,?,?,?)')->execute([$userId,'updated','school_financial_account',$id,json_encode($before),json_encode($data)]);
            $this->db->commit(); return formatResponse(true,['id'=>$id],'Financial account updated; verification status unchanged.');
        } catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return $this->handleException($e);}
    }

    public function financialAccountPermissions(int $id): array
    {
        $s=$this->db->prepare('SELECT p.id AS role_id,p.name,COALESCE(ap.can_receive,0) can_receive,COALESCE(ap.can_disburse,0) can_disburse FROM ' . ReadReplicaService::masterRef('roles') . ' p LEFT JOIN school_financial_account_permissions ap ON ap.role_id=p.id AND ap.financial_account_id=? WHERE p.id NOT IN (2) ORDER BY p.name');
        $s->execute([$id]);
        return formatResponse(true,['permissions'=>$s->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function createFinancialAccount(array $data, int $userId): array
    {
        $name=trim((string)($data['account_name']??'')); $kind=trim((string)($data['account_kind']??''));
        $identifier=trim((string)($data['account_identifier']??'')); $purposes=[];
        $channels=array_values(array_filter((array)($data['channels']??[])));
        if ($name===''||$kind===''||$identifier==='') return formatResponse(false,null,'account_name, account_kind and account_identifier are required');
        $normalizer=new ReferenceNormalizer(); $normalized=$normalizer->accountIdentifier($identifier);
        $this->db->beginTransaction();
        try {
            $kindStmt=$this->db->prepare('SELECT id FROM financial_account_kinds WHERE code=?');$kindStmt->execute([$kind]);$kindId=(int)$kindStmt->fetchColumn();
            if(!$kindId)throw new Exception('Unknown financial account kind');
            $purposes=$this->purposeCodesForAccount($data,$kind);
            if (!$purposes) throw new Exception('Select at least one transaction or collection purpose.');
            $providerId=null;if(!empty($data['provider_code'])){$s=$this->db->prepare('SELECT id FROM payment_providers WHERE code=? AND active=1 ORDER BY id DESC LIMIT 1');$s->execute([(string)$data['provider_code']]);$providerId=(int)$s->fetchColumn()?:null;}
            $ledgerId=null;if(!empty($data['ledger_code'])){$s=$this->db->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active' AND is_postable=1");$s->execute([(string)$data['ledger_code']]);$ledgerId=(int)$s->fetchColumn()?:null;}
            if(!$ledgerId)throw new Exception('A valid postable ledger account is required');
            $i=$this->db->prepare("INSERT INTO school_financial_accounts(account_name,account_kind_id,provider_id,ledger_account_id,account_identifier,normalized_account_identifier,bank_name,currency,status,created_at) VALUES(?,?,?,?,?,?,?,?,'pending_verification',NOW())");
            $i->execute([$name,$kindId,$providerId,$ledgerId,$identifier,$normalized,$data['bank_name']??null,$data['currency']??'KES']);$id=(int)$this->db->lastInsertId();
            $settlementId=$this->settlementAccountId($data, $id, $kind);
            $this->db->prepare('UPDATE school_financial_accounts SET settlement_financial_account_id=? WHERE id=?')->execute([$settlementId,$id]);
            $p=$this->db->prepare('SELECT id FROM financial_account_purposes WHERE code=?');$pi=$this->db->prepare('INSERT INTO school_financial_account_purposes(financial_account_id,purpose_id) VALUES(?,?)');
            foreach($purposes as $code){$purposeId=$this->purposeId((string)$code,$data);$pi->execute([$id,$purposeId]);}
            $c=$this->db->prepare('SELECT id FROM financial_channels WHERE code=?');$ci=$this->db->prepare('INSERT INTO school_financial_account_channels(financial_account_id,channel_id) VALUES(?,?)');
            foreach($channels as $code){$c->execute([(string)$code]);$channelId=(int)$c->fetchColumn();if(!$channelId)throw new Exception('Unknown financial channel: '.$code);$ci->execute([$id,$channelId]);}
            $data['collection_purposes'] = array_key_exists('collection_purposes', $data)
                ? array_values(array_intersect(['fees','transport','uniforms'], (array)$data['collection_purposes']))
                : array_values(array_intersect(['fees','transport','uniforms'], $purposes));
            $this->syncCollectionRoutes($id, $providerId, $identifier, $data['collection_purposes'], $settlementId, $data);
            $this->syncFeeStructureDisplay($id, $data, $userId);
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,after_state) VALUES(?,?,?,?,?)')->execute([$userId,'created','school_financial_account',$id,json_encode(['name'=>$name,'kind'=>$kind,'purposes'=>$purposes,'channels'=>$channels])]);
            $this->db->commit();return formatResponse(true,['id'=>$id],'Financial account created and awaiting verification');
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();return $this->handleException($e);}
    }

    /** Keep the provider/account/purpose routes used by incoming payment callbacks in sync. */
    private function syncCollectionRoutes(int $accountId, ?int $providerId, string $identifier, array $purposes, ?int $settlementId = null, array $data = []): void
    {
        $normalizer = new ReferenceNormalizer();
        $normalized = $normalizer->accountIdentifier($identifier);
        if ($providerId) {
            // Remove legacy rows that pre-date financial_account_id as well as
            // rows belonging to this account, preventing duplicate-key clashes.
            $this->db->prepare('DELETE FROM payment_collection_routes WHERE financial_account_id=? OR (provider_id=? AND (normalized_account_identifier=? OR account_identifier=?))')->execute([$accountId,$providerId,$normalized,$identifier]);
        } else {
            $this->db->prepare('DELETE FROM payment_collection_routes WHERE financial_account_id=?')->execute([$accountId]);
            return;
        }
        $providerCode = '';
        if ($providerId) { $p=$this->db->prepare('SELECT code FROM payment_providers WHERE id=?'); $p->execute([$providerId]); $providerCode=(string)$p->fetchColumn(); }
        $product = trim((string)($data['collection_product'] ?? '')) ?: ($providerCode === 'mpesa_daraja' ? (($data['account_product'] ?? 'paybill') === 'till' ? 'till' : 'paybill') : ($providerCode === 'kcb_buni' ? 'buni' : 'bank_collection'));
        $route = $this->db->prepare('INSERT INTO payment_collection_routes (provider_id,financial_account_id,settlement_financial_account_id,account_identifier,normalized_account_identifier,collection_product,reference_policy,reference_label,purpose,reference_prefix) VALUES (?,?,?,?,?,?,?,?,?,?)');
        $routeChannel = $this->db->prepare('INSERT IGNORE INTO payment_collection_route_channels (route_id,channel_id) SELECT ?,fc.id FROM financial_channels fc WHERE fc.code=?');
        foreach (array_intersect(['fees','transport','uniforms'], $purposes) as $purpose) {
            $prefix = $purpose === 'transport' ? 'TRN' : ($purpose === 'uniforms' ? 'U' : 'FEE');
            $policy = trim((string)($data['reference_policy'] ?? '')) ?: ($purpose === 'fees' ? 'admission_no' : ($purpose === 'transport' ? 'transport_reference' : 'uniform_reference'));
            $label = trim((string)($data['reference_label'] ?? '')) ?: null;
            $route->execute([$providerId,$accountId,$settlementId ?: $accountId,$identifier,$normalizer->accountIdentifier($identifier),$product,$policy,$label,$purpose,$prefix]);
            $routeId = (int)$this->db->lastInsertId();
            foreach ($this->collectionChannelCodes($accountId, $purpose, $data) as $channelCode) {
                $routeChannel->execute([$routeId, $channelCode]);
            }
        }
    }

    /** Account channels are the maximum; cash is additionally controlled per collection purpose. */
    private function collectionChannelCodes(int $accountId, string $purpose, array $data): array
    {
        $s=$this->db->prepare('SELECT c.code FROM ' . ReadReplicaService::qualifiedRef('school_financial_account_channels') . ' ac JOIN financial_channels c ON c.id=ac.channel_id WHERE ac.financial_account_id=?');
        $s->execute([$accountId]); $codes=array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN));
        $overrides=(array)($data['collection_channel_overrides'] ?? []);
        if ($purpose === 'fees' || (array_key_exists($purpose,$overrides) && empty($overrides[$purpose]['cash']))) {
            $codes=array_values(array_diff($codes,['cash']));
        }
        if (!empty($overrides[$purpose]['cash']) && in_array('cash',$codes,true) === false) {
            // The account-level channel permission remains mandatory.
            $codes=array_values(array_diff($codes,['cash']));
        }
        return $codes;
    }

    private function settlementAccountId(array $data, int $accountId = 0, string $kindCode = ''): ?int
    {
        $requested = array_key_exists('settlement_financial_account_id', $data) ? (int)$data['settlement_financial_account_id'] : 0;
        if ($requested <= 0) {
            if ($kindCode === 'mobile_money') throw new Exception('A mobile-money Paybill or Till account must have a settlement financial account.');
            return $accountId > 0 ? $accountId : null;
        }
        $s=$this->db->prepare("SELECT a.id FROM " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " JOIN financial_account_kinds k ON k.id=a.account_kind_id WHERE a.id=? AND a.status IN ('active','pending_verification') AND k.code IN ('bank','cash','clearing')");
        $s->execute([$requested]);
        if (!$s->fetchColumn()) throw new Exception('Selected settlement financial account does not exist or is not usable.');
        return $requested;
    }

    private function syncFeeStructureDisplay(int $accountId, array $data, int $userId): void
    {
        $sql = 'INSERT INTO school_financial_account_fee_display
                (financial_account_id,show_on_fee_structure,display_order,display_title,reference_label,reference_value,instructions,updated_by)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE show_on_fee_structure=VALUES(show_on_fee_structure),
                display_order=VALUES(display_order),display_title=VALUES(display_title),
                reference_label=VALUES(reference_label),reference_value=VALUES(reference_value),
                instructions=VALUES(instructions),updated_by=VALUES(updated_by)';
        $this->db->prepare($sql)->execute([
            $accountId,
            !empty($data['show_on_fee_structure']) ? 1 : 0,
            max(0, (int)($data['fee_display_order'] ?? 0)),
            trim((string)($data['fee_display_title'] ?? '')) ?: null,
            trim((string)($data['fee_reference_label'] ?? '')) ?: null,
            trim((string)($data['fee_reference_value'] ?? '')) ?: null,
            trim((string)($data['fee_display_instructions'] ?? '')) ?: null,
            $userId ?: null,
        ]);
    }

    /** Persist exactly the purposes selected for this account. Collection points are separate. */
    private function purposeCodesForAccount(array $data, string $kindCode): array
    {
        return array_values(array_unique(array_filter(array_map('strval', (array)($data['purposes'] ?? [])))));
    }

    /** Allow administrators to add a named operational purpose without changing the schema. */
    private function purposeId(string $code, array $data): int
    {
        $stmt = $this->db->prepare('SELECT id FROM financial_account_purposes WHERE code=?');
        $stmt->execute([$code]);
        $id = (int)$stmt->fetchColumn();
        if ($id) return $id;
        $custom = (array)($data['custom_purposes'] ?? []);
        $name = trim((string)($custom[$code] ?? ''));
        if ($name === '' || !preg_match('/^[a-z][a-z0-9_]{1,39}$/', $code)) throw new Exception('Unknown account purpose: '.$code);
        $this->db->prepare('INSERT INTO financial_account_purposes(code,name) VALUES(?,?)')->execute([$code,$name]);
        return (int)$this->db->lastInsertId();
    }

    public function verifyFinancialAccount(int $id, int $userId, string $status = 'active'): array
    {
        if (!in_array($status, ['active', 'suspended', 'closed'], true)) {
            return formatResponse(false, null, 'Invalid financial account status.');
        }
        try {
            $this->db->beginTransaction();
            $q = $this->db->prepare('SELECT * FROM school_financial_accounts WHERE id = ? FOR UPDATE');
            $q->execute([$id]);
            $account = $q->fetch(PDO::FETCH_ASSOC);
            if (!$account) throw new Exception('Financial account not found.');
            $u = $this->db->prepare("UPDATE school_financial_accounts SET status=?, verified_by=?, verified_at=NOW() WHERE id=?");
            $u->execute([$status, $userId, $id]);
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,before_state,after_state) VALUES(?,?,?,?,?,?)')
                ->execute([$userId, 'status_changed', 'school_financial_account', $id, json_encode($account), json_encode(['status' => $status])]);
            $this->db->commit();
            return formatResponse(true, ['id' => $id, 'status' => $status], 'Financial account status updated.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    public function setFinancialAccountPermissions(int $id, array $permissions, int $userId): array
    {
        try {
            $this->db->beginTransaction();
            $check = $this->db->prepare('SELECT id FROM school_financial_accounts WHERE id=?');
            $check->execute([$id]);
            if (!$check->fetchColumn()) throw new Exception('Financial account not found.');
            $this->db->prepare('DELETE FROM school_financial_account_permissions WHERE financial_account_id=?')->execute([$id]);
            $insert = $this->db->prepare('INSERT INTO school_financial_account_permissions(financial_account_id,role_id,can_receive,can_disburse) VALUES(?,?,?,?)');
            foreach ($permissions as $permission) {
                $roleId = (int)($permission['role_id'] ?? 0);
                if (!$roleId) throw new Exception('Every account permission requires a role_id.');
                $insert->execute([$id, $roleId, !empty($permission['can_receive']) ? 1 : 0, !empty($permission['can_disburse']) ? 1 : 0]);
            }
            $this->db->prepare('INSERT INTO accounting_audit_events(actor_user_id,action,entity_type,entity_id,after_state) VALUES(?,?,?,?,?)')
                ->execute([$userId, 'permissions_replaced', 'school_financial_account', $id, json_encode($permissions)]);
            $this->db->commit();
            return formatResponse(true, ['id' => $id], 'Financial account permissions updated.');
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /** List normalized bank accounts; legacy bank_accounts is not a source of truth. */
    public function listBankAccounts()
    {
        // This endpoint is the read-only school bank-account view. Do not
        // join payment_collection_routes here: collection points and their
        // provider metadata belong to payment-integration configuration.
        $stmt = $this->db->query("SELECT a.id, a.account_name, a.account_identifier,
                a.normalized_account_identifier, a.bank_name, a.currency, a.status,
                a.is_primary, a.created_at, a.updated_at, k.code AS account_kind,
                c.account_code AS ledger_code,
                GROUP_CONCAT(DISTINCT fp.code ORDER BY fp.code SEPARATOR ',') AS purposes
            FROM " . ReadReplicaService::qualifiedRef("school_financial_accounts") . " a
            JOIN financial_account_kinds k ON k.id = a.account_kind_id
            LEFT JOIN " . ReadReplicaService::qualifiedRef("chart_of_accounts") . " c ON c.id = a.ledger_account_id
            LEFT JOIN school_financial_account_purposes ap ON ap.financial_account_id = a.id
            LEFT JOIN financial_account_purposes fp ON fp.id = ap.purpose_id
            WHERE k.code = 'bank'
            GROUP BY a.id
            ORDER BY a.account_name");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['account_number'] = $row['account_identifier'];
            $row['balance'] = 0.0;
        }
        unset($row);
        return formatResponse(true, ['bank_accounts' => $rows]);
    }

    /**
     * Create a new bank account.
     */
    public function createBankAccount($data)
    {
        return formatResponse(false, null, 'Legacy bank account writes are disabled. Use normalized financial accounts.');
    }

    /**
     * List bank transactions, optionally filtered by account number or bank name.
     */
    public function listBankTransactions($bankId = null)
    {
        try {
            if ($bankId) {
                $stmt = $this->db->prepare('SELECT bt.*, a.account_name, a.account_identifier FROM ' . ReadReplicaService::qualifiedRef('bank_transactions') . ' bt LEFT JOIN school_financial_accounts a ON a.id=bt.financial_account_id WHERE bt.financial_account_id = ? OR bt.account_number = ? ORDER BY bt.transaction_date DESC LIMIT 500');
                $stmt->execute([$bankId, $bankId]);
            } else {
                $stmt = $this->db->query('SELECT bt.*, a.account_name, a.account_identifier FROM ' . ReadReplicaService::qualifiedRef('bank_transactions') . ' bt LEFT JOIN school_financial_accounts a ON a.id=bt.financial_account_id ORDER BY bt.transaction_date DESC LIMIT 500');
            }
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return formatResponse(true, ['transactions' => $rows]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create a manual bank transaction entry.
     */
    public function createBankTransaction($data)
    {
        try {
            $amount = $data['amount'] ?? null;
            if ($amount === null || !is_numeric($amount)) {
                return formatResponse(false, null, 'A valid amount is required');
            }

            $transactionRef = $data['reference'] ?? $data['transaction_ref'] ?? 'BT-' . date('YmdHis');
            $bankName = $data['bank_name'] ?? null;
            $accountNumber = $data['account_number'] ?? null;
            $status = $data['status'] ?? 'pending';
            if (!in_array($status, ['pending', 'processed', 'failed'], true)) {
                $status = 'pending';
            }

            if (!empty($data['account_id'])) {
                $stmt = $this->db->prepare('SELECT bank_name, account_identifier AS account_no FROM school_financial_accounts WHERE id = ? LIMIT 1');
                $stmt->execute([(int) $data['account_id']]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($account) {
                    $bankName = $bankName ?? $account['bank_name'];
                    $accountNumber = $accountNumber ?? $account['account_no'];
                }
            }

            $stmt = $this->db->prepare(
                'INSERT INTO bank_transactions
                    (transaction_ref, amount, transaction_date, bank_name, account_number, narration,
                     sender_name, sender_account, source_type, status, reconciled, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, "manual_entry", ?, 0, NOW())'
            );
            $stmt->execute([
                $transactionRef,
                $amount,
                $data['date'] ?? $data['transaction_date'] ?? date('Y-m-d H:i:s'),
                $bankName,
                $accountNumber,
                $data['description'] ?? $data['narration'] ?? null,
                $data['sender_name'] ?? null,
                $data['sender_account'] ?? null,
                $status,
            ]);

            return formatResponse(true, ['id' => $this->db->lastInsertId()], 'Bank transaction recorded');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update a manual bank transaction, or mark it as reconciled.
     */
    public function updateBankTransaction($id, $data)
    {
        try {
            $id = (int) $id;

            if (!empty($data['reconciled'])) {
                // Only flip the reconciled flag. Do NOT set status='processed':
                // trg_bank_payment_processed fires on status='processed' with a
                // student_id and would credit the student fee balance a second time.
                $stmt = $this->db->prepare('UPDATE bank_transactions SET reconciled = 1, reconciled_at = NOW() WHERE id = ?');
                $stmt->execute([$id]);
                return formatResponse(true, ['id' => $id], 'Transaction reconciled');
            }

            $stmt = $this->db->prepare(
                'UPDATE bank_transactions
                    SET transaction_ref = ?, amount = ?, transaction_date = ?, bank_name = ?,
                        account_number = ?, narration = ?, sender_name = ?, sender_account = ?
                  WHERE id = ? AND source_type = "manual_entry"'
            );
            $stmt->execute([
                $data['reference'] ?? $data['transaction_ref'] ?? null,
                $data['amount'] ?? null,
                $data['date'] ?? $data['transaction_date'] ?? null,
                $data['bank_name'] ?? null,
                $data['account_number'] ?? null,
                $data['description'] ?? $data['narration'] ?? null,
                $data['sender_name'] ?? null,
                $data['sender_account'] ?? null,
                $id,
            ]);

            if ($stmt->rowCount() === 0) {
                return formatResponse(false, null, 'Transaction not found or not editable');
            }

            return formatResponse(true, ['id' => $id], 'Transaction updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete a manual bank transaction entry.
     */
    public function deleteBankTransaction($id)
    {
        try {
            $id = (int) $id;
            $stmt = $this->db->prepare('DELETE FROM bank_transactions WHERE id = ? AND source_type = "manual_entry"');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                return formatResponse(false, null, 'Transaction not found or not deletable');
            }

            return formatResponse(true, ['id' => $id], 'Transaction deleted');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
