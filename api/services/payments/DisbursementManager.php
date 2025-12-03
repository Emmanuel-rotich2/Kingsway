<?php
namespace App\API\Services\payments;

use App\Database\Database;
use Exception;

/**
 * DisbursementManager
 * 
 * Manages all outgoing payments (staff salaries, supplier payments, refunds)
 * Handles bulk disbursements via M-Pesa B2C and Bank transfers
 */
class DisbursementManager
{
    private $db;
    private $mpesaB2C;
    private $kcbTransfer;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->mpesaB2C = new MpesaB2CService();
        $this->kcbTransfer = new KcbFundsTransferService();
    }

    /**
     * Process payroll disbursement
     * Called when payroll is approved and ready for payment (24th-30th)
     */
    public function processPayrollDisbursement($payrollId, $approvedBy)
    {
        try {
            $this->db->beginTransaction();

            // Get payroll details
            $payroll = $this->db->fetchOne(
                "SELECT * FROM payrolls WHERE id = ? AND status = 'approved'",
                [$payrollId]
            );

            if (!$payroll) {
                throw new Exception("Payroll not found or not approved");
            }

            // Check if already processing
            if ($payroll['disbursement_status'] === 'processing') {
                throw new Exception("Payroll disbursement already in progress");
            }

            // Get all staff payment records for this payroll
            $staffPayments = $this->db->fetchAll(
                "SELECT sp.*, s.first_name, s.last_name, s.phone_number, 
                        s.bank_account_number, s.bank_name, s.payment_method
                 FROM staff_payments sp
                 JOIN staff s ON sp.staff_id = s.id
                 WHERE sp.payroll_id = ? AND sp.status = 'pending'",
                [$payrollId]
            );

            if (empty($staffPayments)) {
                throw new Exception("No pending payments found for this payroll");
            }

            // Check total amount vs available balance
            $totalAmount = array_sum(array_column($staffPayments, 'net_salary'));
            $canProceed = $this->verifyAvailableBalance($totalAmount);

            if (!$canProceed) {
                throw new Exception("Insufficient balance to process payroll. Required: KES " . number_format($totalAmount, 2));
            }

            // Update payroll disbursement status
            $this->db->query(
                "UPDATE payrolls SET disbursement_status = 'processing', 
                 disbursement_started_at = NOW(), disbursement_initiated_by = ?
                 WHERE id = ?",
                [$approvedBy, $payrollId]
            );

            $this->db->commit();

            // Process disbursements asynchronously (each payment one by one)
            $results = $this->processBulkDisbursements($staffPayments, $payrollId);

            return [
                'success' => true,
                'payroll_id' => $payrollId,
                'total_staff' => count($staffPayments),
                'total_amount' => $totalAmount,
                'results' => $results
            ];

        } catch (Exception $e) {
            $this->db->rollback();
            $this->logError("Payroll disbursement failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Process bulk disbursements (loop through staff)
     */
    private function processBulkDisbursements($staffPayments, $payrollId)
    {
        $results = [
            'successful' => 0,
            'failed' => 0,
            'pending' => 0,
            'details' => []
        ];

        foreach ($staffPayments as $payment) {
            try {
                $result = $this->processSingleDisbursement($payment);

                if ($result['status'] === 'success' || $result['status'] === 'pending') {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }

                $results['details'][] = [
                    'staff_id' => $payment['staff_id'],
                    'staff_name' => $payment['first_name'] . ' ' . $payment['last_name'],
                    'amount' => $payment['net_salary'],
                    'method' => $payment['payment_method'],
                    'status' => $result['status'],
                    'transaction_ref' => $result['transaction_ref'] ?? null,
                    'message' => $result['message'] ?? ''
                ];

            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'staff_id' => $payment['staff_id'],
                    'staff_name' => $payment['first_name'] . ' ' . $payment['last_name'],
                    'amount' => $payment['net_salary'],
                    'status' => 'failed',
                    'message' => $e->getMessage()
                ];

                $this->logError("Failed to disburse to staff {$payment['staff_id']}: " . $e->getMessage());
            }

            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 second delay
        }

        // Update overall payroll status
        $this->updatePayrollDisbursementStatus($payrollId, $results);

        return $results;
    }

    /**
     * Process single staff payment
     */
    private function processSingleDisbursement($payment)
    {
        $method = strtolower($payment['payment_method']);

        switch ($method) {
            case 'mpesa':
            case 'm-pesa':
                return $this->disburseMpesa($payment);

            case 'bank':
            case 'bank_transfer':
                return $this->disburseBank($payment);

            case 'cash':
                return $this->disburseCash($payment);

            default:
                throw new Exception("Unknown payment method: {$method}");
        }
    }

    /**
     * Disburse via M-Pesa B2C
     */
    private function disburseMpesa($payment)
    {
        // Validate phone number
        $phone = $this->formatPhoneNumber($payment['phone_number']);

        if (!$phone) {
            throw new Exception("Invalid phone number for staff {$payment['staff_id']}");
        }

        // Call M-Pesa B2C API
        $result = $this->mpesaB2C->sendPayment([
            'phone' => $phone,
            'amount' => $payment['net_salary'],
            'remarks' => "Salary payment for " . date('F Y'),
            'occasion' => "Staff Salary"
        ]);

        // Update staff_payment record
        $this->db->query(
            "UPDATE staff_payments 
             SET status = ?, transaction_ref = ?, provider_response = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $result['status'] === 'success' ? 'processing' : 'failed',
                $result['transaction_ref'] ?? null,
                json_encode($result),
                $payment['id']
            ]
        );

        return $result;
    }

    /**
     * Disburse via KCB Bank Transfer
     */
    private function disburseBank($payment)
    {
        // Validate bank details
        if (empty($payment['bank_account_number']) || empty($payment['bank_name'])) {
            throw new Exception("Missing bank details for staff {$payment['staff_id']}");
        }

        // Call KCB Funds Transfer API
        $result = $this->kcbTransfer->transferFunds([
            'account_number' => $payment['bank_account_number'],
            'bank_name' => $payment['bank_name'],
            'amount' => $payment['net_salary'],
            'narration' => "Salary payment for " . date('F Y'),
            'beneficiary_name' => $payment['first_name'] . ' ' . $payment['last_name']
        ]);

        // Update staff_payment record
        $this->db->query(
            "UPDATE staff_payments 
             SET status = ?, transaction_ref = ?, provider_response = ?, updated_at = NOW()
             WHERE id = ?",
            [
                $result['status'] === 'success' ? 'processing' : 'failed',
                $result['transaction_ref'] ?? null,
                json_encode($result),
                $payment['id']
            ]
        );

        return $result;
    }

    /**
     * Mark as cash payment (manual collection)
     */
    private function disburseCash($payment)
    {
        $this->db->query(
            "UPDATE staff_payments 
             SET status = 'pending_collection', updated_at = NOW()
             WHERE id = ?",
            [$payment['id']]
        );

        return [
            'status' => 'pending_collection',
            'message' => 'Marked for cash collection'
        ];
    }

    /**
     * Retry failed payment
     */
    public function retryFailedPayment($staffPaymentId)
    {
        $payment = $this->db->fetchOne(
            "SELECT sp.*, s.first_name, s.last_name, s.phone_number, 
                    s.bank_account_number, s.bank_name, s.payment_method
             FROM staff_payments sp
             JOIN staff s ON sp.staff_id = s.id
             WHERE sp.id = ? AND sp.status = 'failed'",
            [$staffPaymentId]
        );

        if (!$payment) {
            throw new Exception("Payment not found or not in failed status");
        }

        // Increment retry count
        $this->db->query(
            "UPDATE staff_payments SET retry_count = retry_count + 1 WHERE id = ?",
            [$staffPaymentId]
        );

        // Retry the disbursement
        return $this->processSingleDisbursement($payment);
    }

    /**
     * Verify school has sufficient balance
     */
    private function verifyAvailableBalance($requiredAmount)
    {
        // Check M-Pesa balance
        $mpesaBalance = $this->mpesaB2C->checkAccountBalance();

        // Check KCB balance
        $kcbBalance = $this->kcbTransfer->checkAccountBalance();

        $totalAvailable = $mpesaBalance + $kcbBalance;

        return $totalAvailable >= $requiredAmount;
    }

    /**
     * Update payroll overall status after disbursement
     */
    private function updatePayrollDisbursementStatus($payrollId, $results)
    {
        $status = 'completed';

        if ($results['failed'] > 0) {
            $status = $results['successful'] > 0 ? 'partial' : 'failed';
        }

        $this->db->query(
            "UPDATE payrolls 
             SET disbursement_status = ?, 
                 disbursement_completed_at = NOW(),
                 total_disbursed = ?,
                 failed_count = ?
             WHERE id = ?",
            [$status, $results['successful'], $results['failed'], $payrollId]
        );
    }

    /**
     * Get disbursement report
     */
    public function getDisbursementReport($payrollId)
    {
        return $this->db->fetchAll(
            "SELECT sp.*, s.first_name, s.last_name, s.employee_number,
                    s.payment_method, s.phone_number, s.bank_name
             FROM staff_payments sp
             JOIN staff s ON sp.staff_id = s.id
             WHERE sp.payroll_id = ?
             ORDER BY sp.status DESC, s.last_name ASC",
            [$payrollId]
        );
    }

    /**
     * Get failed payments for retry
     */
    public function getFailedPayments($payrollId)
    {
        return $this->db->fetchAll(
            "SELECT sp.*, s.first_name, s.last_name
             FROM staff_payments sp
             JOIN staff s ON sp.staff_id = s.id
             WHERE sp.payroll_id = ? AND sp.status = 'failed'",
            [$payrollId]
        );
    }

    /**
     * Format phone number to international format
     */
    private function formatPhoneNumber($phone)
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            return '254' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            return $phone;
        }

        return null;
    }

    /**
     * Log error
     */
    private function logError($message)
    {
        $logFile = __DIR__ . '/../../../logs/disbursement_errors.log';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[$timestamp] $message\n", 3, $logFile);
    }
}
