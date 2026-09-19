<?php

namespace App\API\Services\payments;

use App\API\Services\ServiceContractBroker;
use App\Database\Database;
use PDO;
use RuntimeException;

/**
 * Reconciles outgoing KCB transfers through callbacks and status inquiries.
 *
 * Unknown provider responses never become a terminal failure. A retry is only
 * possible after an explicit provider failure (or an audited manual resolution)
 * and is created as a new, linked disbursement with a new idempotency key.
 */
class KcbTransferReconciliationService
{
    private $db;
    private $client;
    private $finalizer;

    public function __construct(?PDO $db = null, ?KcbFundsTransferService $client = null, ?callable $finalizer = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
        $this->client = $client ?: new KcbFundsTransferService();
        $this->finalizer = $finalizer ?: static function (array $payload): array {
            return ServiceContractBroker::contract('App\API\Modules\payments\PaymentsAPI')->processKcbTransferCallback($payload, ['__source' => 'status_inquiry']);
        };
    }

    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if (in_array($status, ['pending', 'completed', 'failed', 'timeout', 'cancelled'], true)) {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['exceptions_only'])) {
            $where[] = "r.exception_status = 'open'";
        }
        $limit = max(1, min(500, (int) ($filters['limit'] ?? 100)));
        $sql = 'SELECT r.*, CASE WHEN r.status = \'failed\' AND r.reconciliation_status = \'confirmed_failure\'
                    AND NOT EXISTS (SELECT 1 FROM disbursement_transactions child
                                    WHERE child.retry_of_disbursement_id = r.disbursement_id
                                      AND child.status IN (\'pending\',\'completed\'))
                    THEN 1 ELSE 0 END AS retry_allowed
                FROM vw_kcb_disbursement_reconciliation r
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY CASE WHEN r.exception_status = \'open\' THEN 0 WHEN r.status = \'pending\' THEN 1 ELSE 2 END,
                         r.created_at DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function inquire(int $disbursementId, ?int $actorUserId, string $source = 'manual'): array
    {
        if (!in_array($source, ['manual', 'worker', 'retry_guard'], true)) {
            throw new RuntimeException('Invalid inquiry source.');
        }
        $row = $this->find($disbursementId);
        if (!$row || $row['channel'] !== 'kcb_bank') {
            throw new RuntimeException('KCB disbursement not found.');
        }
        if ($row['status'] === 'completed') {
            return ['disbursement_id' => $disbursementId, 'status' => 'completed', 'reconciliation_status' => 'confirmed_success', 'already_final' => true];
        }
        if ($row['status'] === 'failed' && $row['reconciliation_status'] === 'confirmed_failure') {
            return ['disbursement_id' => $disbursementId, 'status' => 'failed', 'reconciliation_status' => 'confirmed_failure', 'already_final' => true];
        }
        if (empty($row['transaction_ref']) && empty($row['request_id'])) {
            $this->raiseException($disbursementId, 'missing_provider_reference', 'The transfer has no KCB reference that can be queried. Reconcile it against the bank statement before retrying.');
            throw new RuntimeException('This transfer has no KCB reference. Manual statement reconciliation is required.');
        }

        $lock = $this->db->prepare("UPDATE disbursement_transactions
            SET reconciliation_lock_until = DATE_ADD(NOW(), INTERVAL 90 SECOND)
            WHERE id = ? AND (reconciliation_lock_until IS NULL OR reconciliation_lock_until < NOW())");
        $lock->execute([$disbursementId]);
        if ($lock->rowCount() !== 1) {
            throw new RuntimeException('A status inquiry is already running for this transfer.');
        }

        $request = [
            'transaction_reference' => (string) ($row['transaction_ref'] ?? ''),
            'merchant_id' => (string) ($row['request_id'] ?? ''),
        ];
        $result = null;
        try {
            $result = $this->client->getTransferStatus($request);
            $this->recordInquiry($row, $actorUserId, $source, $result, null);
            $this->applyInquiryResult($row, $result, $actorUserId, $source);
        } catch (\Throwable $e) {
            $this->recordInquiry($row, $actorUserId, $source, null, $e->getMessage());
            $this->scheduleNext($row, 'error', $e->getMessage());
            throw new RuntimeException('KCB status inquiry could not be completed. The transfer remains protected from retry.');
        } finally {
            $this->db->prepare('UPDATE disbursement_transactions SET reconciliation_lock_until = NULL WHERE id = ?')->execute([$disbursementId]);
        }

        $fresh = $this->find($disbursementId);
        return [
            'disbursement_id' => $disbursementId,
            'status' => $fresh['status'],
            'reconciliation_status' => $fresh['reconciliation_status'],
            'provider_status' => $result['provider_status'] ?? '',
            'message' => $result['message'] ?? '',
            'retry_allowed' => $fresh['status'] === 'failed' && $fresh['reconciliation_status'] === 'confirmed_failure',
        ];
    }

    public function pollDue(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->query("SELECT id FROM disbursement_transactions
            WHERE channel = 'kcb_bank' AND status = 'pending'
              AND reconciliation_status IN ('awaiting_callback','manual_review')
              AND (next_status_inquiry_at IS NULL OR next_status_inquiry_at <= NOW())
              AND (reconciliation_lock_until IS NULL OR reconciliation_lock_until < NOW())
            ORDER BY created_at ASC LIMIT " . $limit);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $summary = ['selected' => count($ids), 'completed' => 0, 'failed' => 0, 'pending' => 0, 'exceptions' => 0, 'errors' => 0, 'results' => []];
        foreach ($ids as $id) {
            try {
                $result = $this->inquire($id, null, 'worker');
                $summary['results'][] = $result;
                if ($result['status'] === 'completed') $summary['completed']++;
                elseif ($result['status'] === 'failed') $summary['failed']++;
                elseif ($result['reconciliation_status'] === 'exception') $summary['exceptions']++;
                else $summary['pending']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
                $summary['results'][] = ['disbursement_id' => $id, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        return $summary;
    }

    /** Put an ambiguous/failed provider submission into the staff exception queue. */
    public function flagSubmissionException(int $disbursementId, string $message): void
    {
        $reason = 'KCB did not cleanly accept the transfer submission. Verify its reference and the bank statement before retrying. ' . $message;
        $this->raiseException($disbursementId, 'submission_not_accepted', $reason);
        $this->audit($disbursementId, null, 'submission_exception', null, 'manual_review', ['message' => $message]);
    }

    public function retry(int $disbursementId, int $actorUserId): array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT d.*, a.account_identifier AS source_account_identifier
                FROM disbursement_transactions d
                JOIN school_financial_accounts a ON a.id = d.source_financial_account_id
                WHERE d.id = ? AND d.channel = 'kcb_bank' FOR UPDATE");
            $stmt->execute([$disbursementId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException('KCB disbursement not found.');
            if ($row['status'] !== 'failed' || $row['reconciliation_status'] !== 'confirmed_failure') {
                throw new RuntimeException('Retry is blocked until KCB explicitly confirms that the previous transfer failed.');
            }
            $children = $this->db->prepare("SELECT COUNT(*) FROM disbursement_transactions
                WHERE retry_of_disbursement_id = ? AND status IN ('pending','completed')");
            $children->execute([$disbursementId]);
            if ((int) $children->fetchColumn() > 0) {
                throw new RuntimeException('A retry is already pending or completed for this transfer.');
            }
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM disbursement_transactions WHERE retry_of_disbursement_id = ?');
            $countStmt->execute([$disbursementId]);
            $attempt = (int) $countStmt->fetchColumn() + 1;
            $idempotency = substr((string) $row['idempotency_reference'], 0, 140) . '-R' . $attempt;
            $providerReference = strtoupper(substr('KR' . base_convert((string) $disbursementId, 10, 36) . $attempt . bin2hex(random_bytes(3)), 0, 12));

            $insert = $this->db->prepare("INSERT INTO disbursement_transactions
                (disbursement_type,payment_purpose,source_financial_account_id,payroll_id,payslip_id,
                 refund_request_id,expense_id,statutory_remittance_attempt_id,recipient_id,recipient_name,
                 amount,currency,idempotency_reference,account_number,bank_code,bank_name,channel,status,
                 reconciliation_status,retry_of_disbursement_id,submitted_by,approved_by,approved_at,released_at,
                 next_status_inquiry_at,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending','awaiting_callback',?,?,?,NOW(),NOW(),
                        DATE_ADD(NOW(), INTERVAL 2 MINUTE),NOW())");
            $insert->execute([
                $row['disbursement_type'], $row['payment_purpose'], $row['source_financial_account_id'],
                $row['payroll_id'], $row['payslip_id'], $row['refund_request_id'], $row['expense_id'],
                $row['statutory_remittance_attempt_id'], $row['recipient_id'], $row['recipient_name'],
                $row['amount'], $row['currency'], $idempotency, $row['account_number'], $row['bank_code'],
                $row['bank_name'], 'kcb_bank', $disbursementId, $actorUserId, $actorUserId,
            ]);
            $newId = (int) $this->db->lastInsertId();
            $this->audit($disbursementId, $actorUserId, 'retry_authorized', $row['status'], 'retry_created', ['retry_disbursement_id' => $newId, 'idempotency_key' => $idempotency]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }

        $result = $this->client->transferFunds([
            'account_number' => $row['account_number'],
            'bank_name' => $row['bank_name'],
            'bank_code' => $row['bank_code'],
            'amount' => (float) $row['amount'],
            'narration' => 'Authorized retry ' . $providerReference,
            'beneficiary_name' => $row['recipient_name'],
            'transaction_reference' => $providerReference,
            'debit_account_number' => $row['source_account_identifier'],
            'idempotency_reference' => $idempotency,
        ]);
        $accepted = in_array($result['status'] ?? '', ['success', 'pending'], true);
        $this->db->prepare("UPDATE disbursement_transactions SET request_id=?,transaction_ref=?,status=?,
            reconciliation_status=?,result_description=?,callback_data=?,failed_at=IF(?='failed',NOW(),NULL)
            WHERE id=?")->execute([
                $result['request_id'] ?? null, $result['transaction_ref'] ?? $providerReference,
                $accepted ? 'pending' : 'failed', $accepted ? 'awaiting_callback' : 'manual_review',
                $result['message'] ?? null, json_encode($result), $accepted ? 'pending' : 'failed', $newId,
            ]);
        $this->syncBusinessRecordForRetry($row, $newId, $accepted, $result['transaction_ref'] ?? $providerReference);
        if (!$accepted) {
            $this->raiseException($newId, 'retry_submission_uncertain', 'The retry was not accepted cleanly. Verify the bank statement before another attempt.');
        }
        $this->resolveException($disbursementId, $actorUserId, 'A safely linked retry was created as disbursement #' . $newId . '.');
        $this->audit($newId, $actorUserId, 'retry_submitted', null, $accepted ? 'pending' : 'manual_review', ['parent_disbursement_id' => $disbursementId, 'provider_response' => $result]);
        return ['status' => $accepted ? 'pending' : 'manual_review', 'disbursement_id' => $newId, 'retry_of_disbursement_id' => $disbursementId, 'transaction_ref' => $result['transaction_ref'] ?? $providerReference];
    }

    public function resolveManually(int $disbursementId, string $outcome, string $evidence, int $actorUserId): array
    {
        if (!in_array($outcome, ['confirmed_success', 'confirmed_failure'], true)) {
            throw new RuntimeException('Manual outcome must be confirmed_success or confirmed_failure.');
        }
        if (mb_strlen(trim($evidence)) < 12) {
            throw new RuntimeException('Provide the bank statement/reference evidence used for manual reconciliation.');
        }
        $row = $this->find($disbursementId);
        if (!$row || $row['channel'] !== 'kcb_bank') throw new RuntimeException('KCB disbursement not found.');
        if ($outcome === 'confirmed_success') {
            if ($row['status'] === 'failed') throw new RuntimeException('A failed transfer cannot be manually changed to success without provider support review.');
            $payload = $this->completionPayload($row, ['normalized_status' => 'successful', 'provider_status' => 'MANUALLY_RECONCILED', 'message' => $evidence]);
            call_user_func($this->finalizer, $payload);
        } else {
            if ($row['status'] === 'completed') throw new RuntimeException('A completed transfer cannot be changed to failure.');
            $payload = $this->completionPayload($row, ['normalized_status' => 'failed', 'provider_status' => 'MANUALLY_CONFIRMED_FAILED', 'message' => $evidence]);
            call_user_func($this->finalizer, $payload);
        }
        $this->db->prepare('UPDATE disbursement_transactions SET reconciliation_status=?,provider_status=?,next_status_inquiry_at=NULL WHERE id=?')
            ->execute([$outcome, strtoupper($outcome), $disbursementId]);
        $this->resolveException($disbursementId, $actorUserId, $evidence);
        $this->audit($disbursementId, $actorUserId, 'manual_resolution', $row['reconciliation_status'], $outcome, ['evidence' => $evidence]);
        return ['disbursement_id' => $disbursementId, 'reconciliation_status' => $outcome];
    }

    private function applyInquiryResult(array $row, array $result, ?int $actorUserId, string $source): void
    {
        $normalized = (string) ($result['normalized_status'] ?? 'unknown');
        if (in_array($normalized, ['successful', 'failed'], true)) {
            $payload = $this->completionPayload($row, $result);
            call_user_func($this->finalizer, $payload);
            $newReconciliation = $normalized === 'successful' ? 'confirmed_success' : 'confirmed_failure';
            $this->db->prepare('UPDATE disbursement_transactions SET reconciliation_status=?,provider_status=?,last_status_inquiry_at=NOW(),status_inquiry_count=status_inquiry_count+1,next_status_inquiry_at=NULL WHERE id=?')
                ->execute([$newReconciliation, $result['provider_status'] ?? null, $row['id']]);
            $this->resolveException((int) $row['id'], $actorUserId, 'KCB status inquiry returned a terminal state.');
            $this->audit((int) $row['id'], $actorUserId, 'status_inquiry_finalized', $row['reconciliation_status'], $newReconciliation, ['source' => $source, 'provider_status' => $result['provider_status'] ?? null]);
            return;
        }
        $this->scheduleNext($row, $normalized, (string) ($result['message'] ?? ''));
        $this->audit((int) $row['id'], $actorUserId, 'status_inquiry_nonterminal', $row['reconciliation_status'], $normalized, ['source' => $source, 'provider_status' => $result['provider_status'] ?? null]);
    }

    private function scheduleNext(array $row, string $normalized, string $message): void
    {
        $attempt = (int) $row['status_inquiry_count'] + 1;
        $delays = [2, 5, 15, 30, 60, 120];
        $delayMinutes = $delays[min($attempt - 1, count($delays) - 1)];
        $maxAttempts = defined('KCB_STATUS_POLL_MAX_ATTEMPTS') ? (int) KCB_STATUS_POLL_MAX_ATTEMPTS : 5;
        $exceptionMinutes = defined('KCB_STATUS_EXCEPTION_AFTER_MINUTES') ? (int) KCB_STATUS_EXCEPTION_AFTER_MINUTES : 60;
        $tooOld = strtotime((string) $row['created_at']) <= time() - ($exceptionMinutes * 60);
        $isException = $attempt >= $maxAttempts || $tooOld;
        $reconciliation = $isException ? 'exception' : 'awaiting_callback';
        $next = $isException ? null : date('Y-m-d H:i:s', time() + ($delayMinutes * 60));
        $this->db->prepare('UPDATE disbursement_transactions SET reconciliation_status=?,provider_status=?,last_status_inquiry_at=NOW(),next_status_inquiry_at=?,status_inquiry_count=status_inquiry_count+1 WHERE id=?')
            ->execute([$reconciliation, strtoupper($normalized), $next, $row['id']]);
        if ($isException) {
            $this->raiseException((int) $row['id'], 'status_unresolved', 'KCB did not return a terminal transfer status after bounded inquiries. ' . substr($message, 0, 300));
        }
    }

    private function completionPayload(array $row, array $result): array
    {
        return [
            'merchantId' => $row['request_id'],
            'transactionReference' => $result['transaction_reference'] ?? $row['transaction_ref'],
            'amount' => (float) $row['amount'],
            'transactionStatus' => ($result['normalized_status'] ?? '') === 'successful' ? 'COMPLETED' : 'FAILED',
            'transactionMessage' => $result['message'] ?? $result['provider_status'] ?? 'Status inquiry reconciliation',
            'ftReference' => $result['transaction_id'] ?? $row['transaction_id'] ?? $row['transaction_ref'],
            'charges' => (float) ($result['charges'] ?? 0),
            'reconciliationSource' => 'status_inquiry',
        ];
    }

    private function recordInquiry(array $row, ?int $actorUserId, string $source, ?array $result, ?string $error): void
    {
        $requestPayload = $result['request_payload'] ?? ['transactionReference' => $row['transaction_ref'], 'merchantId' => $row['request_id']];
        $stmt = $this->db->prepare("INSERT INTO kcb_transfer_status_inquiries
            (disbursement_id,actor_user_id,trigger_source,provider_reference,provider_request_id,
             request_payload,response_payload,normalized_status,provider_status,provider_message)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $row['id'], $actorUserId ?: null, $source, $row['transaction_ref'], $row['request_id'],
            json_encode($requestPayload), $result ? json_encode($result['response'] ?? []) : null,
            $error !== null ? 'error' : ($result['normalized_status'] ?? 'unknown'),
            $result['provider_status'] ?? null, $error ?? ($result['message'] ?? null),
        ]);
    }

    private function raiseException(int $disbursementId, string $code, string $reason): void
    {
        $stmt = $this->db->prepare("INSERT INTO kcb_disbursement_exceptions
            (disbursement_id,exception_code,reason,status,first_detected_at,last_detected_at)
            VALUES (?,?,?,'open',NOW(),NOW())
            ON DUPLICATE KEY UPDATE exception_code=VALUES(exception_code),reason=VALUES(reason),status='open',last_detected_at=NOW(),resolved_by=NULL,resolved_at=NULL,resolution_notes=NULL");
        $stmt->execute([$disbursementId, $code, substr($reason, 0, 500)]);
        $this->db->prepare("UPDATE disbursement_transactions SET reconciliation_status='exception',next_status_inquiry_at=NULL WHERE id=? AND status NOT IN ('completed','failed')")
            ->execute([$disbursementId]);
    }

    private function resolveException(int $disbursementId, ?int $actorUserId, string $notes): void
    {
        $this->db->prepare("UPDATE kcb_disbursement_exceptions SET status='resolved',resolved_by=?,resolved_at=NOW(),resolution_notes=? WHERE disbursement_id=? AND status='open'")
            ->execute([$actorUserId ?: null, substr($notes, 0, 500), $disbursementId]);
    }

    private function audit(int $disbursementId, ?int $actorUserId, string $event, ?string $previous, ?string $new, array $details = []): void
    {
        $this->db->prepare('INSERT INTO kcb_disbursement_audit_events (disbursement_id,actor_user_id,event_type,previous_status,new_status,details) VALUES (?,?,?,?,?,?)')
            ->execute([$disbursementId, $actorUserId ?: null, $event, $previous, $new, json_encode($details)]);
    }

    private function syncBusinessRecordForRetry(array $row, int $newId, bool $accepted, string $reference): void
    {
        if (!empty($row['payslip_id'])) {
            $this->db->prepare("UPDATE payslips SET payment_status=?,payment_reference=?,notes=CONCAT(COALESCE(notes,''),'\nKCB safe retry #',?),updated_at=NOW() WHERE id=?")
                ->execute([$accepted ? 'processing' : 'failed', $reference, $newId, $row['payslip_id']]);
        }
        if (!empty($row['expense_id'])) {
            $this->db->prepare("UPDATE supplier_payment_requests SET disbursement_id=?,status=?,provider_reference=? WHERE disbursement_id=?")
                ->execute([$newId, $accepted ? 'payment_pending' : 'failed', $reference, $row['id']]);
            $this->db->prepare("UPDATE expenses SET status=? WHERE id=?")
                ->execute([$accepted ? 'payment_pending' : 'approved', $row['expense_id']]);
        }
        if (!empty($row['refund_request_id'])) {
            $this->db->prepare("UPDATE parent_refund_requests SET disbursement_id=?,status=?,provider_reference=?,updated_at=NOW() WHERE id=?")
                ->execute([$newId, $accepted ? 'processing' : 'failed', $reference, $row['refund_request_id']]);
        }
        if (!empty($row['statutory_remittance_attempt_id'])) {
            $this->db->prepare("UPDATE statutory_remittance_attempts SET status=?,provider_reference=? WHERE id=?")
                ->execute([$accepted ? 'pending' : 'failed', $reference, $row['statutory_remittance_attempt_id']]);
        }
    }

    private function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM disbursement_transactions WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
