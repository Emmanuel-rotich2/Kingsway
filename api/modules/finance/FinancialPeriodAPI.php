<?php
namespace App\API\Modules\finance;

use App\API\Services\ReadReplicaService;

use App\API\Includes\BaseAPI;
use PDO;
use Exception;

class FinancialPeriodAPI extends BaseAPI {
    public function __construct() {
        parent::__construct('finance');
    }

    /**
     * Create a new financial period
     */
    public function create($data) {
        try {
            $required = ['name', 'start_date', 'end_date'];
            $missing = $this->validateRequired($data, $required);
            if (!empty($missing)) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Missing required fields',
                    'fields' => $missing
                ], 400);
            }

            // Validate dates
            if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'End date must be after start date'
                ], 400);
            }

            // Check for overlapping periods
            $sql = "
                SELECT COUNT(*) 
                FROM financial_periods 
                WHERE (? BETWEEN start_date AND end_date 
                OR ? BETWEEN start_date AND end_date)
                AND status = 'active'
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data['start_date'], $data['end_date']]);
            if ($stmt->fetchColumn() > 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Date range overlaps with existing financial period'
                ], 400);
            }

            $sql = "
                INSERT INTO financial_periods (
                    name,
                    start_date,
                    end_date,
                    status
                ) VALUES (?, ?, ?, 'active')
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['name'],
                $data['start_date'],
                $data['end_date']
            ]);

            return $this->response([
                'status' => 'success',
                'message' => 'Financial period created successfully',
                'data' => ['id' => $this->db->lastInsertId()]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Close a financial period
     */
    public function close($id) {
        try {
            // Check if period exists and is active
            $sql = "SELECT * FROM financial_periods WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $period = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$period) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Financial period not found'
                ], 404);
            }

            if ($period['status'] !== 'active') {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Financial period is already closed'
                ], 400);
            }

            // Check for unreconciled transactions
            $sql = "
                SELECT COUNT(*) 
                FROM school_transactions st
                WHERE st.transaction_date BETWEEN ? AND ?
                AND st.id NOT IN (SELECT transaction_id FROM " . ReadReplicaService::qualifiedRef("payment_reconciliations") . ")
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$period['start_date'], $period['end_date']]);
            if ($stmt->fetchColumn() > 0) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Cannot close period: There are unreconciled transactions'
                ], 400);
            }

            // Close the period
            $sql = "
                UPDATE financial_periods 
                SET status = 'closed',
                    closed_by = ?,
                    closed_at = NOW()
                WHERE id = ?
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$this->getCurrentUserId(), $id]);

            return $this->response([
                'status' => 'success',
                'message' => 'Financial period closed successfully'
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get financial period report
     */
    public function getPeriodReport($id) {
        try {
            // Get period details
            $sql = "SELECT * FROM financial_periods WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $period = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$period) {
                return $this->response([
                    'status' => 'error',
                    'message' => 'Financial period not found'
                ], 404);
            }

            // Get transaction summary
            $sql = "
                SELECT 
                    source,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM school_transactions
                WHERE transaction_date BETWEEN ? AND ?
                GROUP BY source
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$period['start_date'], $period['end_date']]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get fee collection summary
            $sql = "
                SELECT 
                    'School Fees' AS name,
                    COUNT(DISTINCT sae.student_id) as student_count,
                    SUM(sfo.amount_due - COALESCE(sfo.sponsored_waiver_amount, 0)
                        - COALESCE(fdw.total_discount, 0)) as total_balance
                FROM " . ReadReplicaService::qualifiedRef("student_fee_obligations") . " sfo
                JOIN " . ReadReplicaService::qualifiedRef("academic_year_fee_schedules") . " ays ON sfo.academic_year_fee_schedule_id = ays.id
                JOIN " . ReadReplicaService::qualifiedRef("student_academic_enrollments") . " sae ON sfo.student_academic_enrollment_id = sae.id
                LEFT JOIN (
                    SELECT student_fee_obligation_id, SUM(discount_value) AS total_discount
                    FROM fee_discounts_waivers
                    WHERE status = 'active'
                    GROUP BY student_fee_obligation_id
                ) fdw ON fdw.student_fee_obligation_id = sfo.id
                WHERE sfo.updated_at BETWEEN ? AND ?
                GROUP BY name
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$period['start_date'], $period['end_date']]);
            $feeCollection = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->response([
                'status' => 'success',
                'data' => [
                    'period' => $period,
                    'transactions' => $transactions,
                    'fee_collection' => $feeCollection
                ]
            ]);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }
}
