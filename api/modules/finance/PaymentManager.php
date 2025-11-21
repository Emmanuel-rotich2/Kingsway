<?php

namespace App\API\Modules\Finance;

use App\Config\Database;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Payment Management Class
 * 
 * Handles all payment-related operations:
 * - Payment processing (cash, bank, M-Pesa)
 * - Payment allocation to fee types
 * - Payment reconciliation
 * - Refunds and reversals
 * - Payment tracking and reporting
 * 
 * Integrates with stored procedures:
 * - sp_process_student_payment
 * - sp_allocate_payment
 * - sp_record_cash_payment
 * 
 * Integrates with tables:
 * - payment_transactions
 * - payment_allocations_detailed
 * - mpesa_transactions
 * - bank_transactions
 * - payment_reconciliations
 */
class PaymentManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Process a student payment
     * @param array $data Payment data
     * @return array Response with payment_id
     */
    public function processPayment($data)
    {
        try {
            $required = ['student_id', 'amount', 'payment_method'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Validate student exists
            $stmt = $this->db->prepare("SELECT id FROM students WHERE id = ?");
            $stmt->execute([$data['student_id']]);

            if (!$stmt->fetch()) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Student not found');
            }

            // Call stored procedure to process payment
            $stmt = $this->db->prepare("
                CALL sp_process_student_payment(?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['student_id'],
                $data['amount'],
                $data['payment_method'], // 'cash', 'bank', 'mpesa', 'cheque'
                $data['transaction_ref'] ?? null,
                $data['payment_date'] ?? date('Y-m-d H:i:s'),
                $data['received_by'] ?? null,
                $data['notes'] ?? null
            ]);

            // Get the payment ID from the procedure result
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $paymentId = $result['payment_id'] ?? $this->db->lastInsertId();

            // If M-Pesa payment, record M-Pesa transaction details
            if ($data['payment_method'] === 'mpesa' && !empty($data['mpesa_data'])) {
                $this->recordMpesaTransaction($paymentId, $data['mpesa_data']);
            }

            // If bank payment, record bank transaction details
            if ($data['payment_method'] === 'bank' && !empty($data['bank_data'])) {
                $this->recordBankTransaction($paymentId, $data['bank_data']);
            }

            $this->db->commit();

            return formatResponse(true, [
                'payment_id' => $paymentId,
                'message' => 'Payment processed successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Record M-Pesa transaction details
     * @param int $paymentId Payment transaction ID
     * @param array $mpesaData M-Pesa data
     * @return bool Success status
     */
    private function recordMpesaTransaction($paymentId, $mpesaData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO mpesa_transactions (
                payment_id, transaction_id, phone_number, 
                amount, transaction_date, status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $paymentId,
            $mpesaData['transaction_id'],
            $mpesaData['phone_number'],
            $mpesaData['amount'],
            $mpesaData['transaction_date'] ?? date('Y-m-d H:i:s'),
            $mpesaData['status'] ?? 'completed'
        ]);
    }

    /**
     * Record bank transaction details
     * @param int $paymentId Payment transaction ID
     * @param array $bankData Bank data
     * @return bool Success status
     */
    private function recordBankTransaction($paymentId, $bankData)
    {
        $stmt = $this->db->prepare("
            INSERT INTO bank_transactions (
                payment_id, transaction_ref, amount, 
                transaction_date, bank_name, account_number, narration, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([
            $paymentId,
            $bankData['transaction_ref'],
            $bankData['amount'],
            $bankData['transaction_date'] ?? date('Y-m-d H:i:s'),
            $bankData['bank_name'] ?? null,
            $bankData['account_number'] ?? null,
            $bankData['narration'] ?? null,
            $bankData['status'] ?? 'processed'
        ]);
    }

    /**
     * Allocate payment to specific fee types
     * @param int $paymentId Payment transaction ID
     * @param array $allocations Array of allocations
     * @return array Response
     */
    public function allocatePayment($paymentId, $allocations)
    {
        try {
            if (empty($allocations)) {
                return formatResponse(false, null, 'No allocations provided');
            }

            $this->db->beginTransaction();

            // Verify payment exists
            $stmt = $this->db->prepare("
                SELECT id, amount, student_id FROM payment_transactions WHERE id = ?
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Payment not found');
            }

            // Call stored procedure for each allocation
            $stmt = $this->db->prepare("CALL sp_allocate_payment(?, ?, ?, ?)");

            foreach ($allocations as $allocation) {
                $stmt->execute([
                    $paymentId,
                    $payment['student_id'],
                    $allocation['fee_type_id'],
                    $allocation['amount']
                ]);
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Payment allocated successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to allocate payment: ' . $e->getMessage());
        }
    }

    /**
     * Get payment details
     * @param int $paymentId Payment ID
     * @return array Response with payment data
     */
    public function getPayment($paymentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT pt.*, s.student_no, CONCAT(s.first_name, ' ', s.last_name) as student_name,
                       u.username as received_by_name
                FROM payment_transactions pt
                INNER JOIN students s ON pt.student_id = s.id
                LEFT JOIN users u ON pt.received_by = u.id
                WHERE pt.id = ?
            ");

            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                return formatResponse(false, null, 'Payment not found');
            }

            // Get payment allocations
            $stmt = $this->db->prepare("
                SELECT pad.*, ft.name as fee_type_name, ft.code as fee_type_code
                FROM payment_allocations_detailed pad
                LEFT JOIN fee_types ft ON pad.fee_type_id = ft.id
                WHERE pad.payment_id = ?
            ");

            $stmt->execute([$paymentId]);
            $payment['allocations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get M-Pesa details if applicable
            if ($payment['payment_method'] === 'mpesa') {
                $stmt = $this->db->prepare("
                    SELECT * FROM mpesa_transactions WHERE payment_id = ?
                ");
                $stmt->execute([$paymentId]);
                $payment['mpesa_details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Get bank details if applicable
            if ($payment['payment_method'] === 'bank') {
                $stmt = $this->db->prepare("
                    SELECT * FROM bank_transactions WHERE payment_id = ?
                ");
                $stmt->execute([$paymentId]);
                $payment['bank_details'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return formatResponse(true, $payment);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to retrieve payment: ' . $e->getMessage());
        }
    }

    /**
     * List payments with filters
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Response with payments list
     */
    public function listPayments($filters = [], $page = 1, $limit = 20)
    {
        try {
            $offset = ($page - 1) * $limit;

            // Use the view for comprehensive payment data
            $sql = "SELECT * FROM vw_all_school_payments WHERE 1=1";
            $params = [];

            if (!empty($filters['student_id'])) {
                $sql .= " AND student_id = ?";
                $params[] = $filters['student_id'];
            }

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['payment_method'])) {
                $sql .= " AND payment_method = ?";
                $params[] = $filters['payment_method'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (student_no LIKE ? OR student_name LIKE ? OR transaction_ref LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }

            $sql .= " ORDER BY payment_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM vw_all_school_payments WHERE 1=1";
            $countParams = array_slice($params, 0, -2); // Remove limit and offset

            if (!empty($filters['student_id']))
                $countSql .= " AND student_id = ?";
            if (!empty($filters['academic_year']))
                $countSql .= " AND academic_year = ?";
            if (!empty($filters['payment_method']))
                $countSql .= " AND payment_method = ?";
            if (!empty($filters['status']))
                $countSql .= " AND status = ?";
            if (!empty($filters['date_from']))
                $countSql .= " AND payment_date >= ?";
            if (!empty($filters['date_to']))
                $countSql .= " AND payment_date <= ?";
            if (!empty($filters['search']))
                $countSql .= " AND (student_no LIKE ? OR student_name LIKE ? OR transaction_ref LIKE ?)";

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return formatResponse(true, [
                'payments' => $payments,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to list payments: ' . $e->getMessage());
        }
    }

    /**
     * Reverse/refund a payment
     * @param int $paymentId Payment ID
     * @param array $data Reversal data
     * @return array Response
     */
    public function reversePayment($paymentId, $data)
    {
        try {
            $required = ['reason', 'reversed_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Verify payment exists and is not already reversed
            $stmt = $this->db->prepare("
                SELECT * FROM payment_transactions WHERE id = ? AND status != 'reversed'
            ");
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Payment not found or already reversed');
            }

            // Update payment status to reversed
            $stmt = $this->db->prepare("
                UPDATE payment_transactions 
                SET status = 'reversed', 
                    reversal_reason = ?,
                    reversed_by = ?,
                    reversed_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $data['reason'],
                $data['reversed_by'],
                $paymentId
            ]);

            // Reverse allocations - update student fee balances
            $stmt = $this->db->prepare("
                SELECT * FROM payment_allocations_detailed WHERE payment_id = ?
            ");
            $stmt->execute([$paymentId]);
            $allocations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allocations as $allocation) {
                // Add back the amount to student's balance
                $stmt = $this->db->prepare("
                    UPDATE student_fee_balances 
                    SET balance = balance + ?,
                        total_paid = total_paid - ?
                    WHERE student_id = ? AND academic_year = ?
                ");

                $stmt->execute([
                    $allocation['amount'],
                    $allocation['amount'],
                    $payment['student_id'],
                    $payment['academic_year'] ?? date('Y')
                ]);
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Payment reversed successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to reverse payment: ' . $e->getMessage());
        }
    }

    /**
     * Reconcile payments with bank statement
     * @param array $data Reconciliation data
     * @return array Response
     */
    public function reconcilePayments($data)
    {
        try {
            $required = ['reconciliation_date', 'bank_statement_file'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Create reconciliation record
            $stmt = $this->db->prepare("
                INSERT INTO payment_reconciliations (
                    reconciliation_date, bank_statement_file, 
                    reconciled_by, status, notes
                ) VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['reconciliation_date'],
                $data['bank_statement_file'],
                $data['reconciled_by'] ?? null,
                'pending',
                $data['notes'] ?? null
            ]);

            $reconciliationId = $this->db->lastInsertId();

            // Match payments to bank transactions
            if (!empty($data['matches'])) {
                foreach ($data['matches'] as $match) {
                    $stmt = $this->db->prepare("
                        UPDATE payment_transactions 
                        SET reconciliation_id = ?,
                            reconciliation_status = 'matched'
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $reconciliationId,
                        $match['payment_id']
                    ]);
                }
            }

            // Update reconciliation status
            $stmt = $this->db->prepare("
                UPDATE payment_reconciliations 
                SET status = 'completed',
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$reconciliationId]);

            $this->db->commit();

            return formatResponse(true, [
                'reconciliation_id' => $reconciliationId,
                'message' => 'Payments reconciled successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to reconcile payments: ' . $e->getMessage());
        }
    }

    /**
     * Get payment summary statistics
     * @param array $filters Filter criteria
     * @return array Response with summary data
     */
    public function getPaymentSummary($filters = [])
    {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_transactions,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        payment_method,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'reversed' THEN 1 END) as reversed_count
                    FROM payment_transactions
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " GROUP BY payment_method";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get overall totals
            $totalAmount = array_sum(array_column($summary, 'total_amount'));
            $totalTransactions = array_sum(array_column($summary, 'total_transactions'));

            return formatResponse(true, [
                'by_payment_method' => $summary,
                'overall' => [
                    'total_amount' => $totalAmount,
                    'total_transactions' => $totalTransactions,
                    'average_transaction' => $totalTransactions > 0 ? $totalAmount / $totalTransactions : 0
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get payment summary: ' . $e->getMessage());
        }
    }

    /**
     * Record cash payment using stored procedure
     * @param array $data Cash payment data
     * @return array Response
     */
    public function recordCashPayment($data)
    {
        try {
            $required = ['student_id', 'amount', 'received_by'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Call stored procedure sp_record_cash_payment
            $stmt = $this->db->prepare("CALL sp_record_cash_payment(?, ?, ?, ?)");
            $stmt->execute([
                $data['student_id'],
                $data['amount'],
                $data['received_by'],
                $data['notes'] ?? null
            ]);

            $this->db->commit();

            return formatResponse(true, [
                'message' => 'Cash payment recorded successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to record cash payment: ' . $e->getMessage());
        }
    }

    /**
     * Get parent payment activity using view
     * @param int $parentId Parent ID
     * @param array $filters Optional filters
     * @return array Response
     */
    public function getParentPaymentActivity($parentId, $filters = [])
    {
        try {
            $sql = "SELECT * FROM vw_parent_payment_activity WHERE parent_id = ?";
            $params = [$parentId];

            if (!empty($filters['academic_year'])) {
                $sql .= " AND academic_year = ?";
                $params[] = $filters['academic_year'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " ORDER BY payment_date DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'activity' => $activity,
                'total_payments' => count($activity),
                'total_amount' => array_sum(array_column($activity, 'amount'))
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get parent payment activity: ' . $e->getMessage());
        }
    }

    /**
     * Get student payment status using enhanced view
     * @param int $studentId Student ID
     * @return array Response
     */
    public function getStudentPaymentStatus($studentId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM vw_student_payment_status_enhanced 
                WHERE student_id = ?
            ");
            $stmt->execute([$studentId]);
            $status = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$status) {
                return formatResponse(false, null, 'Student payment status not found');
            }

            return formatResponse(true, $status);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get student payment status: ' . $e->getMessage());
        }
    }
}
