<?php

namespace App\API\Services\payments;

use App\Database\Database;
use PDO;
use RuntimeException;
use App\API\Services\payments\MpesaB2CService;
use App\API\Services\ReadReplicaService;

/** Coordinates approved supplier/expense payouts through Buni Funds Transfer. */
class SupplierDisbursementService
{
    private $db;
    private $kcb;
    private $mpesa;
    private $financialAccounts;

    public function __construct(?PDO $db = null, ?KcbFundsTransferService $kcb = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
        $this->kcb = $kcb ?: new KcbFundsTransferService();
        $this->mpesa = new MpesaB2CService();
        $this->financialAccounts = new FinancialAccountService($this->db);
    }

    public function initiateExpensePayment(int $expenseId, int $userId, array $data = []): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name AS supplier_name
             FROM " . ReadReplicaService::qualifiedRef("expenses") . " JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON s.id = e.vendor_id
             WHERE e.id = ? AND e.status IN ('approved','payment_pending') LIMIT 1"
        );
        $stmt->execute([$expenseId]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expense) throw new RuntimeException('Expense is not approved or has no supplier.');

        $paidStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM supplier_payment_requests
             WHERE expense_id = ? AND status IN ('payment_pending','paid')"
        );
        $paidStmt->execute([$expenseId]);
        $outstanding = max(0, (float) $expense['amount'] - (float) $paidStmt->fetchColumn());
        $amount = isset($data['amount']) ? (float) $data['amount'] : $outstanding;
        if ($amount <= 0 || $amount > $outstanding + 0.01) {
            throw new RuntimeException('Payment amount must be greater than zero and not exceed the outstanding supplier balance.');
        }

        $channel = strtolower((string) ($data['channel'] ?? $data['payment_method'] ?? 'kcb_bank'));
        if (in_array($channel, ['mpesa', 'mpesa_b2c', 'mobile_money'], true)) {
            $source = $this->financialAccounts->requireFor((int) ($data['source_financial_account_id'] ?? 0), 'suppliers', 'mpesa_b2c', true, $userId);
            $data['source_financial_account_id'] = (int) $source['id'];
            $data['payment_purpose'] = 'suppliers';
            return $this->initiateMpesaPayment($expenseId, $userId, $data);
        }

        $source = $this->financialAccounts->requireFor((int) ($data['source_financial_account_id'] ?? 0), 'suppliers', 'buni_transfer', true, $userId);

        $accountId = (int) ($data['supplier_bank_account_id'] ?? 0);
        $sql = "SELECT * FROM supplier_bank_accounts WHERE supplier_id = ? AND active = 1";
        $params = [(int) $expense['vendor_id']];
        if ($accountId > 0) {
            $sql .= ' AND id = ?';
            $params[] = $accountId;
        } else {
            $sql .= ' ORDER BY is_primary DESC, id DESC LIMIT 1';
        }
        $accountStmt = $this->db->prepare($sql);
        $accountStmt->execute($params);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new RuntimeException('No active supplier bank account is configured.');
        if (($account['verification_status'] ?? 'unverified') !== 'verified') {
            throw new RuntimeException('Supplier bank account must be verified before payment.');
        }

        $reference = strtoupper(substr((string) ($data['payment_reference'] ?? ('SUP' . bin2hex(random_bytes(5)))), 0, 12));
        $insert = $this->db->prepare(
            "INSERT INTO disbursement_transactions
                (disbursement_type, expense_id, recipient_id, recipient_name,
                 payment_purpose, source_financial_account_id, idempotency_reference,
                 amount, account_number, bank_code, bank_name, channel, status)
             VALUES ('supplier', ?, ?, ?, 'suppliers', ?, ?, ?, ?, ?, ?, 'kcb_bank', 'pending')"
        );
        $insert->execute([
            $expenseId, $expense['vendor_id'], $expense['supplier_name'],
            (int) $source['id'], $reference, $amount, $account['account_number'], $account['bank_code'], $account['bank_name']
        ]);
        $disbursementId = (int) $this->db->lastInsertId();

        $request = $this->db->prepare(
            "INSERT INTO supplier_payment_requests
                (supplier_id, expense_id, supplier_bank_account_id, disbursement_id,
                 amount, payment_reference, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, 'payment_pending', ?)"
        );
        $request->execute([
            $expense['vendor_id'], $expenseId, $account['id'], $disbursementId,
            $amount, $reference, $userId
        ]);
        $supplierPaymentId = (int) $this->db->lastInsertId();

        $result = $this->kcb->transferFunds([
            'account_number' => $account['account_number'],
            'bank_name' => $account['bank_name'],
            'bank_code' => $account['bank_code'],
            'amount' => $amount,
            'narration' => substr('Supplier payment ' . $reference, 0, 35),
            'beneficiary_name' => $account['account_name'],
            'transaction_reference' => $reference,
            'debit_account_number' => $source['account_identifier'],
            'source_financial_account_id' => (int) $source['id'],
            'idempotency_reference' => $reference,
            'payment_purpose' => 'suppliers',
        ]);
        $accepted = in_array($result['status'] ?? '', ['success', 'pending'], true);
        $this->db->prepare(
            "UPDATE disbursement_transactions
             SET request_id = ?, transaction_ref = ?, status = ?, reconciliation_status = ?,
                 next_status_inquiry_at = IF(? = 'pending', DATE_ADD(NOW(), INTERVAL 2 MINUTE), NULL),
                 result_description = ?, callback_data = ?
             WHERE id = ?"
        )->execute([
            $result['request_id'] ?? null, $result['transaction_ref'] ?? $reference,
            $accepted ? 'pending' : 'failed',
            $accepted ? 'awaiting_callback' : 'manual_review',
            $accepted ? 'pending' : 'failed',
            $result['message'] ?? null,
            json_encode($result), $disbursementId
        ]);
        if (!$accepted) {
            (new KcbTransferReconciliationService($this->db, $this->kcb))
                ->flagSubmissionException($disbursementId, (string) ($result['message'] ?? 'Submission failed.'));
        }
        $this->db->prepare("UPDATE supplier_payment_requests SET status = ?, provider_reference = ? WHERE id = ?")
            ->execute([$accepted ? 'payment_pending' : 'failed', $result['transaction_ref'] ?? null, $supplierPaymentId]);
        $this->db->prepare("UPDATE expenses SET status = ?, payment_method = 'bank_transfer', reference_number = ?, paid_by = ? WHERE id = ?")
            ->execute([$accepted ? 'payment_pending' : 'approved', $result['transaction_ref'] ?? $reference, $userId, $expenseId]);

        return [
            'status' => $accepted ? 'pending' : 'failed',
            'disbursement_id' => $disbursementId,
            'transaction_ref' => $result['transaction_ref'] ?? $reference,
            'message' => $result['message'] ?? null,
        ];
    }

    private function initiateMpesaPayment(int $expenseId, int $userId, array $data): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.*, s.name AS supplier_name
             FROM " . ReadReplicaService::qualifiedRef("expenses") . " JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON s.id = e.vendor_id
             WHERE e.id = ? AND e.status IN ('approved','payment_pending') LIMIT 1"
        );
        $stmt->execute([$expenseId]);
        $expense = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$expense) throw new RuntimeException('Expense is not approved or has no supplier.');

        $paidStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM supplier_payment_requests
             WHERE expense_id = ? AND status IN ('payment_pending','paid')"
        );
        $paidStmt->execute([$expenseId]);
        $outstanding = max(0, (float) $expense['amount'] - (float) $paidStmt->fetchColumn());
        $amount = isset($data['amount']) ? (float) $data['amount'] : $outstanding;
        if ($amount <= 0 || $amount > $outstanding + 0.01) {
            throw new RuntimeException('Payment amount must be greater than zero and not exceed the outstanding supplier balance.');
        }

        $mobileId = (int) ($data['supplier_mobile_account_id'] ?? 0);
        $sql = "SELECT * FROM supplier_mobile_accounts WHERE supplier_id = ? AND active = 1";
        $params = [(int) $expense['vendor_id']];
        if ($mobileId > 0) {
            $sql .= ' AND id = ?';
            $params[] = $mobileId;
        } else {
            $sql .= ' ORDER BY is_primary DESC, id DESC LIMIT 1';
        }
        $accountStmt = $this->db->prepare($sql);
        $accountStmt->execute($params);
        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) throw new RuntimeException('No active supplier M-Pesa account is configured.');
        if (($account['verification_status'] ?? 'unverified') !== 'verified') {
            throw new RuntimeException('Supplier M-Pesa number must be verified before payment.');
        }

        $reference = strtoupper(substr((string) ($data['payment_reference'] ?? ('SUP' . bin2hex(random_bytes(5)))), 0, 12));
        $request = $this->db->prepare(
            "INSERT INTO supplier_payment_requests
                (supplier_id, expense_id, supplier_mobile_account_id, amount,
                 payment_reference, status, created_by)
             VALUES (?, ?, ?, ?, ?, 'payment_pending', ?)"
        );
        $request->execute([
            $expense['vendor_id'], $expenseId, $account['id'], $amount, $reference, $userId
        ]);
        $supplierPaymentId = (int) $this->db->lastInsertId();

        $result = $this->mpesa->sendPayment([
            'phone' => $account['phone_number'],
            'amount' => $amount,
            'command_id' => 'BusinessPayment',
            'remarks' => substr('Supplier payment ' . $reference, 0, 100),
            'occasion' => 'Supplier payment',
            'recipient_id' => $expense['vendor_id'],
            'recipient_name' => $account['account_name'],
            'disbursement_type' => 'supplier',
            'payment_purpose' => 'suppliers',
            'source_financial_account_id' => (int) ($data['source_financial_account_id'] ?? 0),
            'idempotency_reference' => $reference,
        ]);

        $conversation = $result['transaction_ref'] ?? null;
        $originator = $result['originator_conversation_id'] ?? null;
        $find = $this->db->prepare("SELECT id FROM disbursement_transactions WHERE conversation_id = ? OR originator_conversation_id = ? ORDER BY id DESC LIMIT 1");
        $find->execute([$conversation, $originator]);
        $disbursementId = (int) ($find->fetchColumn() ?: 0);
        if ($disbursementId) {
            $this->db->prepare("UPDATE supplier_payment_requests SET disbursement_id = ?, status = ? WHERE id = ?")
                ->execute([$disbursementId, ($result['status'] ?? '') === 'success' ? 'payment_pending' : 'failed', $supplierPaymentId]);
        }
        $accepted = in_array($result['status'] ?? '', ['success', 'pending', 'processing'], true);
        $this->db->prepare("UPDATE expenses SET status = ?, payment_method = 'mpesa', reference_number = ?, paid_by = ? WHERE id = ?")
            ->execute([$accepted ? 'payment_pending' : 'approved', $result['transaction_ref'] ?? $reference, $userId, $expenseId]);

        return [
            'status' => $accepted ? 'pending' : 'failed',
            'disbursement_id' => $disbursementId ?: null,
            'transaction_ref' => $result['transaction_ref'] ?? $reference,
            'message' => $result['message'] ?? null,
        ];
    }
}
