<?php
namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class PaymentReconciliationAPI extends BaseAPI {
    public function __construct() {
        parent::__construct('finance');
    }

    /**
     * List unreconciled transactions
     */
    public function listUnreconciled($params = []) {
        try {
            $sql = "
                SELECT 
                    st.*,
                    s.admission_number,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    u.username as received_by_name
                FROM school_transactions st
                LEFT JOIN students s ON st.student_id = s.id
                LEFT JOIN users u ON st.received_by = u.id
                WHERE st.id NOT IN (SELECT transaction_id FROM payment_reconciliations)
                ORDER BY st.transaction_date DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => $transactions
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Reconcile a transaction
     */
    public function reconcileTransaction($data) {
        try {
            $required = ['transaction_id', 'bank_statement_ref'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            $this->db->beginTransaction();

            $sql = "
                INSERT INTO payment_reconciliations (
                    transaction_id,
                    reconciled_by,
                    bank_statement_ref,
                    notes
                ) VALUES (?, ?, ?, ?)
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['transaction_id'],
                $this->getCurrentUserId(),
                $data['bank_statement_ref'],
                $data['notes'] ?? null
            ]);

            // Update transaction status
            $sql = "
                UPDATE school_transactions 
                SET status = 'reconciled'
                WHERE id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['transaction_id']]);

            $this->db->commit();

            return $this->response([
                'status' => 'success',
                'message' => 'Transaction reconciled successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            return $this->handleException($e);
        }
    }

    /**
     * Get reconciliation report
     */
    public function getReconciliationReport($params = []) {
        try {
            $startDate = $params['start_date'] ?? date('Y-m-01');
            $endDate = $params['end_date'] ?? date('Y-m-t');

            $sql = "
                SELECT 
                    st.source,
                    COUNT(*) as total_transactions,
                    COUNT(pr.id) as reconciled_count,
                    SUM(st.amount) as total_amount,
                    SUM(CASE WHEN pr.id IS NOT NULL THEN st.amount ELSE 0 END) as reconciled_amount
                FROM school_transactions st
                LEFT JOIN payment_reconciliations pr ON st.id = pr.transaction_id
                WHERE st.transaction_date BETWEEN ? AND ?
                GROUP BY st.source
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'period' => [
                        'start_date' => $startDate,
                        'end_date' => $endDate
                    ],
                    'summary' => $summary
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
} 