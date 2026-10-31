<?php
namespace App\API\Services;

use App\Database\Database;
use PDO;
use Throwable;

/**
 * Posts the employee-authorised school-fee portion of a paid payslip.
 *
 * Salary disbursement and fee allocation are separate transactions.  This
 * service is deliberately idempotent: provider callbacks may be delivered
 * more than once, but a transfer row can only become posted once.
 */
class PayrollChildFeeTransferService
{
    private $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?: Database::getInstance()->getConnection();
    }

    public function postForPayslip(int $payslipId): array
    {
        $stmt = $this->db->prepare("SELECT id, staff_id, payroll_month, payroll_year, payment_status FROM payslips WHERE id = ? LIMIT 1");
        $stmt->execute([$payslipId]);
        $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$payslip) return ['posted' => 0, 'failed' => 0, 'message' => 'Payslip not found'];
        if ($payslip['payment_status'] !== 'paid') return ['posted' => 0, 'failed' => 0, 'message' => 'Salary is not paid'];

        $rows = $this->db->prepare("SELECT * FROM payroll_child_fee_transfers WHERE payslip_id = ? AND status = 'pending' ORDER BY id FOR UPDATE");
        $rows->execute([$payslipId]);
        $posted = 0; $failed = 0;

        while ($transfer = $rows->fetch(PDO::FETCH_ASSOC)) {
            try {
                $this->db->beginTransaction();
                $lock = $this->db->prepare("SELECT status, receipt_no, amount, student_id FROM payroll_child_fee_transfers WHERE id = ? FOR UPDATE");
                $lock->execute([(int) $transfer['id']]);
                $current = $lock->fetch(PDO::FETCH_ASSOC);
                if (!$current || $current['status'] !== 'pending') {
                    if ($this->db->inTransaction()) $this->db->commit();
                    continue;
                }

                $already = $this->db->prepare('SELECT id FROM payments WHERE receipt_no = ? LIMIT 1');
                $already->execute([$current['receipt_no']]);
                $alreadyId = $already->fetchColumn();
                if ($alreadyId) {
                    $this->db->prepare("UPDATE payroll_child_fee_transfers SET status='posted', payment_id=?, posted_at=COALESCE(posted_at,NOW()), updated_at=NOW() WHERE id=? AND status='pending'")
                        ->execute([(int) $alreadyId, (int) $transfer['id']]);
                    $this->db->commit();
                    $posted++;
                    continue;
                }

                $parent = $this->db->prepare('SELECT parent_id FROM student_parents WHERE student_id = ? ORDER BY is_primary DESC, id LIMIT 1');
                $parent->execute([(int) $current['student_id']]);
                $parentId = $parent->fetchColumn() ?: null;

                $sp = $this->db->prepare('CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?, ?, ?)');
                $sp->execute([
                    (int) $current['student_id'], $parentId ? (int) $parentId : null,
                    (float) $current['amount'], 'other', $current['receipt_no'],
                    $current['receipt_no'], 1, date('Y-m-d H:i:s'),
                    'Employee-authorised school-fee deduction from paid salary',
                ]);
                $sp->closeCursor();

                $payment = $this->db->prepare('SELECT id FROM payments WHERE receipt_no = ? LIMIT 1');
                $payment->execute([$current['receipt_no']]);
                $paymentId = $payment->fetchColumn();
                $this->db->prepare("UPDATE payroll_child_fee_transfers SET status='posted', payment_id=?, posted_at=NOW(), updated_at=NOW() WHERE id=? AND status='pending'")
                    ->execute([$paymentId ?: null, (int) $transfer['id']]);
                $this->db->commit();
                $posted++;
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                $failed++;
                $this->db->prepare("UPDATE payroll_child_fee_transfers SET status='failed', failure_reason=?, updated_at=NOW() WHERE id=? AND status='pending'")
                    ->execute([substr($e->getMessage(), 0, 500), (int) $transfer['id']]);
                \App\API\Services\Logger::legacyError('[PayrollChildFeeTransferService] ' . $e->getMessage());
            }
        }

        return ['posted' => $posted, 'failed' => $failed];
    }
}
