<?php
namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;
use App\API\Services\ReadReplicaService;

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
                    p.id, p.student_id, p.receipt_no, p.amount, p.payment_date,
                    p.method AS source, p.reference AS transaction_ref, p.status,
                    s.admission_no AS admission_number,
                    CONCAT(pers.first_name, ' ', pers.last_name) AS student_name,
                    u.username as received_by_name
                FROM " . ReadReplicaService::qualifiedRef("payments") . " p
                LEFT JOIN " . ReadReplicaService::qualifiedRef("students") . " s ON p.student_id = s.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " pers ON pers.id = s.person_id
                LEFT JOIN users u ON p.received_by = u.id
                WHERE p.status = 'confirmed'
                AND p.id NOT IN (SELECT transaction_id FROM " . ReadReplicaService::qualifiedRef("payment_reconciliations") . ")
                ORDER BY p.payment_date DESC
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
        // Check transaction ownership before try block for catch scope
        $ownTransaction = !$this->db->inTransaction();

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

            // Only start transaction if one is not already active (allows nested calls)
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }

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

            $reconciliationId = $this->db->lastInsertId();

            // Fetch inserted reconciliation record
            $stmt = $this->db->prepare("SELECT pr.*, u.username as reconciled_by_name FROM " . ReadReplicaService::qualifiedRef("payment_reconciliations") . " LEFT JOIN users u ON pr.reconciled_by = u.id WHERE pr.id = ? LIMIT 1");
            $stmt->execute([$reconciliationId]);
            $reconRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            // Only commit if we started the transaction
            if ($ownTransaction) {
                $this->db->commit();
            }

            return $this->response([
                'status' => 'success',
                'message' => 'Transaction reconciled successfully',
                'data' => $reconRecord
            ]);
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
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
                    p.method AS source,
                    COUNT(*) as total_transactions,
                    COUNT(pr.id) as reconciled_count,
                    SUM(p.amount) as total_amount,
                    SUM(CASE WHEN pr.id IS NOT NULL THEN p.amount ELSE 0 END) as reconciled_amount
                FROM " . ReadReplicaService::qualifiedRef("payments") . "
                LEFT JOIN " . ReadReplicaService::qualifiedRef("payment_reconciliations") . " pr ON p.id = pr.transaction_id
                WHERE p.payment_date BETWEEN ? AND ?
                GROUP BY p.method
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