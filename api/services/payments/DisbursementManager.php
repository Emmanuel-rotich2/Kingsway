<?php

namespace App\API\Services\payments;

use App\Database\Database;
use PDO;
use Exception;
use App\API\Services\Logger;
use App\API\Services\DataScopeService;

/**
 * DisbursementManager
 *
 * Coordinates all outgoing payments (staff salaries, supplier payments,
 * refunds) across M-Pesa B2C, bank transfer and cash.
 *
 * Rebuilt against the live KingsWayAcademy schema:
 *  - payroll_runs / payslips / staff / persons
 *  - disbursement_transactions (callback matching)
 *  - mpesa_transactions (transaction_type B2C)
 *
 * Every initiation is recorded so the M-Pesa B2C Result callback and KCB
 * transfer callback can be matched back to the payslip and applied.
 */
class DisbursementManager
{
    /** @var PDO */
    private $db;

    /** @var MpesaB2CService */
    private $mpesaB2C;

    /** @var KcbFundsTransferService */
    private $kcbTransfer;

    /** @var MpesaPaymentService */
    private $mpesaPayment;
    private $financialAccounts;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->mpesaB2C = new MpesaB2CService();
        $this->kcbTransfer = new KcbFundsTransferService();
        $this->mpesaPayment = new MpesaPaymentService();
        $this->financialAccounts = new FinancialAccountService($this->db);
    }

    /** Assign source accounts to one or more unpaid payslips in a payroll run. */
    public function assignPayrollSourceAccounts(int $payrollId, array $allocations, int $userId): array
    {
        DataScopeService::requireLiveExternalAction('Payroll source-account allocation');
        if ($payrollId <= 0 || !$allocations) throw new Exception('Payroll and at least one source allocation are required.');
        $run = $this->db->prepare("SELECT id,month,year,status FROM payroll_runs WHERE id=? AND data_scope='live' LIMIT 1");
        $run->execute([$payrollId]); $period = $run->fetch(PDO::FETCH_ASSOC);
        if (!$period) {
            $run = $this->db->prepare("SELECT pr.id,pr.month,pr.year,pr.status FROM payroll_runs pr JOIN payslips ps ON ps.payroll_month=pr.month AND ps.payroll_year=pr.year WHERE ps.id=? AND pr.data_scope='live' AND ps.data_scope='live' LIMIT 1");
            $run->execute([$payrollId]); $period = $run->fetch(PDO::FETCH_ASSOC);
        }
        if (!$period) throw new Exception('Payroll run not found.');
        if (in_array($period['status'], ['processing','completed','cancelled'], true)) throw new Exception('Source accounts cannot be changed after payroll disbursement has started.');
        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare("UPDATE payslips SET source_financial_account_id=?, updated_at=NOW() WHERE id=? AND payroll_month=? AND payroll_year=? AND data_scope='live' AND payslip_status='approved' AND payment_status IN ('pending','failed')");
            $changed = 0;
            foreach ($allocations as $allocation) {
                $accountId = (int)($allocation['source_financial_account_id'] ?? 0);
                $payslipIds = array_values(array_filter(array_map('intval', (array)($allocation['payslip_ids'] ?? []))));
                if ($accountId <= 0 || !$payslipIds) throw new Exception('Each allocation requires a source account and payslip IDs.');
                foreach ($payslipIds as $payslipId) {
                    $q = $this->db->prepare("SELECT payment_method FROM payslips WHERE id=? AND payroll_month=? AND payroll_year=? AND data_scope='live' AND payslip_status='approved' AND payment_status IN ('pending','failed') LIMIT 1");
                    $q->execute([$payslipId,$period['month'],$period['year']]); $method = (string)$q->fetchColumn();
                    if (!$method) throw new Exception('One or more selected payslips are not eligible for source allocation.');
                    $channel = in_array(strtolower($method), ['mpesa','mobile_money','mpesa_b2c'], true) ? 'mpesa_b2c' : 'buni_transfer';
                    $this->financialAccounts->requireFor($accountId, 'payroll', $channel, true, $userId);
                    $update->execute([$accountId,$payslipId,$period['month'],$period['year']]);
                    if ($update->rowCount() !== 1) throw new Exception('A selected payslip could not be updated.');
                    $changed++;
                }
            }
            $this->db->commit();
            return ['payroll_id'=>$payrollId,'updated'=>$changed];
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function payrollSourceAllocationRows(int $payrollId): array
    {
        $scope = DataScopeService::current();
        $run = $this->db->prepare('SELECT id,month,year,status FROM payroll_runs WHERE id=? AND data_scope=? LIMIT 1');
        $run->execute([$payrollId,$scope]); $period = $run->fetch(PDO::FETCH_ASSOC);
        if (!$period) {
            $run = $this->db->prepare('SELECT pr.id,pr.month,pr.year,pr.status FROM payroll_runs pr JOIN payslips ps ON ps.payroll_month=pr.month AND ps.payroll_year=pr.year WHERE ps.id=? AND pr.data_scope=? AND ps.data_scope=? LIMIT 1');
            $run->execute([$payrollId,$scope,$scope]); $period = $run->fetch(PDO::FETCH_ASSOC);
        }
        if (!$period) throw new Exception('Payroll run not found.');
        $q=$this->db->prepare("SELECT ps.id,ps.staff_id,ps.net_salary,ps.payment_method,ps.payment_status,ps.payslip_status,ps.source_financial_account_id,CONCAT(p.first_name,' ',p.last_name) staff_name FROM payslips ps JOIN staff s ON s.id=ps.staff_id JOIN persons p ON p.id=s.person_id WHERE ps.payroll_month=? AND ps.payroll_year=? AND ps.data_scope=? ORDER BY p.last_name,p.first_name");
        $q->execute([$period['month'],$period['year'],$scope]); return ['payroll_id'=>(int)$period['id'],'run_status'=>$period['status'] ?? null,'rows'=>$q->fetchAll(PDO::FETCH_ASSOC)];
    }

    /**
     * Process payroll disbursement — called once a payroll is approved.
     */
    public function processPayrollDisbursement($payrollId, $approvedBy, array $data = [])
    {
        try {
            DataScopeService::requireLiveExternalAction('Payroll disbursement');
            $payroll = $this->db->prepare(
                "SELECT * FROM payroll_runs WHERE id = ? AND status = 'approved' AND data_scope='live' LIMIT 1"
            );
            $payroll->execute([$payrollId]);
            $payrollRow = $payroll->fetch(PDO::FETCH_ASSOC);

            if (!$payrollRow) {
                throw new Exception("Payroll not found or not approved");
            }
            $fallbackSourceAccountId = (int) ($data['source_financial_account_id'] ?? $payrollRow['source_financial_account_id'] ?? 0);
            $selectedPayslipIds = array_values(array_filter(array_map('intval', (array)($data['payslip_ids'] ?? []))));
            if ($payrollRow['status'] === 'processing') {
                throw new Exception("Payroll disbursement already in progress");
            }

            $paymentSql =
                "SELECT ps.*, p.first_name, p.last_name, p.phone AS phone_number,
                        spp.bank_account AS bank_account_number, spp.bank_name,
                        ps.payment_method
                 FROM payslips ps
                 JOIN staff st ON ps.staff_id = st.id
                 JOIN persons p ON p.id = st.person_id
                 LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = st.id
                 WHERE ps.payroll_month = ? AND ps.payroll_year = ?
                   AND ps.payslip_status = 'approved'
                   AND ps.payment_status IN ('pending', 'failed')
                   AND ps.data_scope = 'live' AND st.data_scope = 'live'";
            $paymentParams = [$payrollRow['month'], $payrollRow['year']];
            if ($selectedPayslipIds) {
                $paymentSql .= ' AND ps.id IN (' . implode(',', array_fill(0, count($selectedPayslipIds), '?')) . ')';
                $paymentParams = array_merge($paymentParams, $selectedPayslipIds);
            }
            $staffPayments = $this->db->prepare($paymentSql);
            $staffPayments->execute($paymentParams);
            $staffPayments = $staffPayments->fetchAll(PDO::FETCH_ASSOC);
            foreach ($staffPayments as &$staffPayment) {
                $staffPayment['_source_financial_account_id'] = (int)($staffPayment['source_financial_account_id'] ?? $fallbackSourceAccountId);
                $staffPayment['_source_actor_user_id'] = (int)$approvedBy;
                if ($staffPayment['_source_financial_account_id'] <= 0) throw new Exception('Every payroll transaction must have a source financial account, or the payroll run must have a default account.');
            }
            unset($staffPayment);

            if (empty($staffPayments)) {
                throw new Exception("No pending payments found for this payroll");
            }

            // Validate every transaction's selected source before any provider
            // call begins; mixed-account payrolls must fail atomically at the
            // validation boundary rather than halfway through the batch.
            foreach ($staffPayments as $staffPayment) {
                $channel = in_array(strtolower((string)($staffPayment['payment_method'] ?? '')), ['mpesa','mobile_money','mpesa_b2c'], true) ? 'mpesa_b2c' : 'buni_transfer';
                $this->financialAccounts->requireFor((int)$staffPayment['_source_financial_account_id'], 'payroll', $channel, true, (int)$approvedBy);
            }

            $totalAmount = array_sum(array_column($staffPayments, 'net_salary'));

            if (!$this->verifyAvailableBalance($totalAmount)) {
                throw new Exception(
                    "Insufficient balance to process payroll. Required: KES " . number_format($totalAmount, 2)
                );
            }

            $this->db->prepare(
                "UPDATE payroll_runs SET status = 'processing' WHERE id = ?"
            )->execute([$payrollId]);

            $results = $this->processBulkDisbursements($staffPayments, $payrollId);

            $this->updatePayrollDisbursementStatus($payrollId, $results);

            return [
                'success' => true,
                'payroll_id' => $payrollId,
                'total_staff' => count($staffPayments),
                'total_amount' => $totalAmount,
                'results' => $results,
            ];
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[DisbursementManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    /**
     * Process bulk disbursements (loop through staff).
     */
    private function processBulkDisbursements($staffPayments, $payrollId)
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'pending' => 0,
            'details' => [],
        ];

        foreach ($staffPayments as $payment) {
            try {
                $result = $this->processSingleDisbursement($payment, $payrollId);

                if ($result['status'] === 'success') {
                    $results['successful']++;
                } elseif ($result['status'] === 'pending') {
                    $results['pending']++;
                } else {
                    $results['failed']++;
                }

                $results['details'][] = [
                    'staff_id' => $payment['staff_id'],
                    'staff_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                    'amount' => $payment['net_salary'],
                    'method' => $payment['payment_method'],
                    'status' => $result['status'],
                    'transaction_ref' => $result['transaction_ref'] ?? null,
                    'message' => $result['message'] ?? '',
                ];
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'staff_id' => $payment['staff_id'],
                    'staff_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
                    'amount' => $payment['net_salary'],
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];
                $this->logError("Failed to disburse to staff {$payment['staff_id']}: " . $e->getMessage());
            }

            usleep(500000);
        }

        return $results;
    }

    /**
     * Process a single staff payment by payment method.
     */
    private function processSingleDisbursement($payment, $payrollId = null)
    {
        $method = strtolower((string) ($payment['payment_method'] ?? 'bank'));

        switch ($method) {
            case 'mpesa':
            case 'm-pesa':
            case 'mobile_money':
                return $this->disburseMpesa($payment, $payrollId);

            case 'bank':
            case 'bank_transfer':
                return $this->disburseBank($payment, $payrollId);

            case 'cash':
            case 'check':
                return $this->disburseCash($payment);

            default:
                throw new Exception("Unknown payment method: {$method}");
        }
    }

    /**
     * Disburse via M-Pesa B2C.
     */
    private function disburseMpesa($payment, $payrollId = null)
    {
        $source = $this->financialAccounts->requireFor((int) ($payment['_source_financial_account_id'] ?? 0), 'payroll', 'mpesa_b2c', true, (int)($payment['_source_actor_user_id'] ?? 0));
        $phone = $this->formatPhoneNumber((string) ($payment['phone_number'] ?? ''));
        if (!$phone) {
            throw new Exception("Invalid phone number for staff {$payment['staff_id']}");
        }

        $result = $this->mpesaB2C->sendPayment([
            'phone' => $phone,
            'amount' => (float) $payment['net_salary'],
            'command_id' => 'SalaryPayment',
            'remarks' => 'Salary payment for ' . date('F Y'),
            'occasion' => 'Staff Salary',
            'recipient_id' => $payment['staff_id'],
            'recipient_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
            'payroll_id' => $payrollId,
            'payslip_id' => $payment['id'],
            'disbursement_type' => 'salary',
            'payment_purpose' => 'payroll',
            'source_financial_account_id' => (int) $source['id'],
            'idempotency_reference' => 'PAYROLL-' . (int) $payrollId . '-STAFF-' . (int) $payment['id'],
        ]);

        $status = in_array($result['status'] ?? '', ['success', 'pending'], true) ? 'processing' : 'failed';
        $this->db->prepare(
            "UPDATE payslips
             SET payment_status = ?, payment_reference = ?, paid_at = paid_at,
                 notes = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $status,
            $result['transaction_ref'] ?? null,
            json_encode($result),
            $payment['id'],
        ]);

        $result['status'] = $status === 'processing' ? 'pending' : 'failed';
        return $result;
    }

    /**
     * Disburse via KCB bank transfer.
     */
    private function disburseBank($payment, $payrollId = null)
    {
        $source = $this->financialAccounts->requireFor((int) ($payment['_source_financial_account_id'] ?? 0), 'payroll', 'buni_transfer', true, (int)($payment['_source_actor_user_id'] ?? 0));
        if (empty($payment['bank_account_number']) || empty($payment['bank_name'])) {
            throw new Exception("Missing bank details for staff {$payment['staff_id']}");
        }

        // Persist the correlation row before calling Buni. The provider may
        // complete asynchronously, so its callback must always find a local
        // disbursement by request/reference.
        $insert = $this->db->prepare(
            "INSERT INTO disbursement_transactions
                (disbursement_type, payment_purpose, payroll_id, payslip_id, source_financial_account_id, idempotency_reference, recipient_id,
                 recipient_name, amount, account_number, bank_name, channel,
                 status, created_at)
             VALUES (?, 'payroll', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'kcb_bank', 'pending', NOW())"
        );
        $insert->execute([
            'salary',
            $payrollId,
            $payment['id'],
            (int) $source['id'],
            'PAYROLL-' . (int) $payrollId . '-STAFF-' . (int) $payment['id'],
            $payment['staff_id'],
            trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
            (float) $payment['net_salary'],
            $payment['bank_account_number'],
            $payment['bank_name'],
        ]);
        $disbursementId = (int) $this->db->lastInsertId();

        $result = $this->kcbTransfer->transferFunds([
            'account_number' => $payment['bank_account_number'],
            'bank_name' => $payment['bank_name'],
            'amount' => (float) $payment['net_salary'],
            'narration' => 'Salary payment for ' . date('F Y'),
            'beneficiary_name' => trim(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')),
            'recipient_id' => $payment['staff_id'],
            'payroll_id' => $payrollId,
            'payslip_id' => $payment['id'],
            'disbursement_type' => 'salary',
            'payment_purpose' => 'payroll',
            'debit_account_number' => $source['account_identifier'],
            'source_financial_account_id' => (int) $source['id'],
            'idempotency_reference' => 'PAYROLL-' . (int) $payrollId . '-STAFF-' . (int) $payment['id'],
        ]);

        $status = in_array($result['status'] ?? '', ['success', 'pending'], true) ? 'processing' : 'failed';
        $this->db->prepare(
            "UPDATE disbursement_transactions
             SET request_id = ?, transaction_ref = ?, status = ?,
                 reconciliation_status = ?, next_status_inquiry_at = IF(? = 'pending', DATE_ADD(NOW(), INTERVAL 2 MINUTE), NULL),
                 result_description = ?, callback_data = ?,
                 failed_at = IF(? = 'failed', NOW(), failed_at)
             WHERE id = ?"
        )->execute([
            $result['request_id'] ?? null,
            $result['transaction_ref'] ?? null,
            $status === 'processing' ? 'pending' : 'failed',
            $status === 'processing' ? 'awaiting_callback' : 'manual_review',
            $status === 'processing' ? 'pending' : 'failed',
            $result['message'] ?? null,
            json_encode($result),
            $status,
            $disbursementId,
        ]);
        if ($status === 'failed') {
            (new KcbTransferReconciliationService($this->db, $this->kcbTransfer))
                ->flagSubmissionException($disbursementId, (string) ($result['message'] ?? 'Submission failed.'));
        }
        $this->db->prepare(
            "UPDATE payslips
             SET payment_status = ?, payment_reference = ?, paid_at = paid_at,
                 notes = ?, updated_at = NOW()
             WHERE id = ?"
        )->execute([
            $status,
            $result['transaction_ref'] ?? null,
            json_encode($result),
            $payment['id'],
        ]);

        $result['status'] = $status === 'processing' ? 'pending' : 'failed';
        return $result;
    }

    /**
     * Mark as cash/cheque payment (manual collection).
     */
    private function disburseCash($payment)
    {
        $this->db->prepare(
            "UPDATE payslips SET payment_status = 'pending', updated_at = NOW() WHERE id = ?"
        )->execute([$payment['id']]);

        return [
            'status' => 'pending',
            'message' => 'Marked for manual collection',
            'transaction_ref' => null,
        ];
    }

    /**
     * Retry a failed payment.
     */
    public function retryFailedPayment($staffPaymentId, int $actorUserId = 0)
    {
        DataScopeService::requireLiveExternalAction('Payroll payment retry');
        $kcb = $this->db->prepare("SELECT id FROM disbursement_transactions WHERE payslip_id=? AND channel='kcb_bank' ORDER BY id DESC LIMIT 1");
        $kcb->execute([$staffPaymentId]);
        $kcbDisbursementId = (int) ($kcb->fetchColumn() ?: 0);
        if ($kcbDisbursementId > 0) {
            if ($actorUserId <= 0) throw new Exception('An authorized finance user is required for a KCB retry.');
            return (new KcbTransferReconciliationService($this->db, $this->kcbTransfer))->retry($kcbDisbursementId, $actorUserId);
        }
        $stmt = $this->db->prepare(
            "SELECT ps.*, p.first_name, p.last_name, p.phone AS phone_number,
                    spp.bank_account AS bank_account_number, spp.bank_name, ps.payment_method
             FROM payslips ps
             JOIN staff st ON ps.staff_id = st.id
             JOIN persons p ON p.id = st.person_id
             LEFT JOIN staff_payroll_profiles spp ON spp.staff_id = st.id
             WHERE ps.id = ? AND ps.payment_status = 'failed'
               AND ps.data_scope='live' AND st.data_scope='live'"
        );
        $stmt->execute([$staffPaymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            throw new Exception("Payment not found or not in failed status");
        }

        $this->db->prepare(
            "UPDATE payslips SET notes = CONCAT(COALESCE(notes, ''), '\n[RETRY] ', NOW()), updated_at = NOW() WHERE id = ?"
        )->execute([$staffPaymentId]);

        return $this->processSingleDisbursement($payment);
    }

    /**
     * Best-effort balance gate. The M-Pesa account balance is delivered
     * asynchronously (AccountBalance API result callback); we only hard-block
     * when a recorded balance result exists and is below the required amount.
     * When no balance record is available we log a warning and proceed —
     * disbursements are still reconciled by the B2C callback.
     */
    private function verifyAvailableBalance($requiredAmount)
    {
        try {
            // Latest M-Pesa result callbacks are mirrored into the file-based
            // payments log (previously payment_webhooks_log, which was dropped).
            $latestBalance = null;
            foreach (\App\API\Includes\FileLogger::recent('payments', 200) as $entry) {
                if (($entry['source'] ?? null) !== 'mpesa_result') {
                    continue;
                }
                $decoded = $entry['webhook_data'] ?? null;
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                if (!is_array($decoded)) {
                    continue;
                }
                $params = $decoded['Result']['ResultParameters']['ResultParameter'] ?? [];
                foreach ($params as $param) {
                    if (($param['Key'] ?? '') === 'AccountBalance') {
                        // Daraja returns AccountBalance as
                        // AccountType|Currency|TotalBalance|AvailableBalance|...
                        // Some older fixtures used ampersands, so retain
                        // compatibility while selecting the documented
                        // available-balance field when it is present.
                        $rawBalance = (string) $param['Value'];
                        $parts = strpos($rawBalance, '|') !== false
                            ? explode('|', $rawBalance)
                            : explode('&', $rawBalance);
                        $latestBalance = count($parts) >= 4
                            ? (float) $parts[3]
                            : (float) ($parts[0] ?? 0);
                        break 2;
                    }
                }
            }

            if ($latestBalance === null) {
                \App\API\Services\Logger::legacyError('[DisbursementManager] Balance not yet known (async result). Proceeding with disbursement.');
                return true;
            }
            return $latestBalance >= $requiredAmount;
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[DisbursementManager] balance check error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Update the payroll status after disbursement.
     * payroll_runs.status enum: draft | processing | approved | paid.
     */
    private function updatePayrollDisbursementStatus($payrollId, $results)
    {
        if ($results['failed'] === 0 && $results['pending'] === 0) {
            $status = 'paid';
        } else {
            $status = 'processing';
        }

        $this->db->prepare(
            "UPDATE payroll_runs SET status = ?, workflow = ? WHERE id = ? AND data_scope='live'"
        )->execute([$status, $status === 'paid' ? 'completed' : 'processing', $payrollId]);
    }

    /**
     * Disbursement report for a payroll.
     */
    public function getDisbursementReport($payrollId)
    {
        $scope = DataScopeService::current();
        $stmt = $this->db->prepare(
            "SELECT ps.*, p.first_name, p.last_name, st.staff_no AS employee_number,
                    ps.payment_method, ps.payment_status AS status
             FROM payslips ps
             JOIN staff st ON ps.staff_id = st.id
             JOIN persons p ON p.id = st.person_id
             WHERE ps.payroll_month = (SELECT month FROM payroll_runs WHERE id = ? AND data_scope=?)
               AND ps.payroll_year = (SELECT year FROM payroll_runs WHERE id = ? AND data_scope=?)
               AND ps.data_scope=? AND st.data_scope=?
             ORDER BY ps.payment_status DESC, p.last_name ASC"
        );
        $stmt->execute([$payrollId, $scope, $payrollId, $scope, $scope, $scope]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Failed payments for retry.
     */
    public function getFailedPayments($payrollId)
    {
        $scope = DataScopeService::current();
        $stmt = $this->db->prepare(
            "SELECT ps.*, p.first_name, p.last_name
             FROM payslips ps
             JOIN staff st ON ps.staff_id = st.id
             JOIN persons p ON p.id = st.person_id
             WHERE ps.payroll_month = (SELECT month FROM payroll_runs WHERE id = ? AND data_scope=?)
               AND ps.payroll_year = (SELECT year FROM payroll_runs WHERE id = ? AND data_scope=?)
               AND ps.data_scope=? AND st.data_scope=?
               AND ps.payment_status = 'failed'"
        );
        $stmt->execute([$payrollId, $scope, $payrollId, $scope, $scope, $scope]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Format a phone number to 254XXXXXXXXX, or null if invalid.
     */
    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/\D/', '', (string) $phone);

        if (strlen($phone) === 10 && $phone[0] === '0') {
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            return '254' . $phone;
        } elseif (strlen($phone) === 12 && strpos($phone, '254') === 0) {
            return $phone;
        }

        return null;
    }

    private function logError($message)
    {
        Logger::error('payments', 'Disbursement processing failed', [
            'component' => 'DisbursementManager',
            'error' => (string) $message,
        ]);
    }
}
