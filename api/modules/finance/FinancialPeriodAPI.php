<?php
namespace App\API\Modules\finance;

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
                AND st.id NOT IN (SELECT transaction_id FROM payment_reconciliations)
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
                    fs.name,
                    COUNT(DISTINCT sfb.student_id) as student_count,
                    SUM(sfb.balance) as total_balance
                FROM student_fee_balances sfb
                JOIN fee_structures fs ON sfb.fee_structure_id = fs.id
                WHERE sfb.last_updated BETWEEN ? AND ?
                GROUP BY fs.id
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