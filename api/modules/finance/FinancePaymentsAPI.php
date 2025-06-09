<?php
namespace App\API\Modules\finance;

use App\API\Includes\BaseAPI;
use Exception;

class FinancePaymentsAPI extends BaseAPI
{
    public function __construct()
    {
        parent::__construct('finance_payments');
    }

    /**
     * Record a bank transaction (callback from bank API)
     */
    public function recordBankTransaction($data)
    {
        $required = ['transaction_ref', 'amount', 'transaction_date'];
        $missing = $this->validateRequired($data, $required);
        if ($missing) {
            return $this->response(['status' => 'error', 'message' => 'Missing fields: ' . implode(', ', $missing)], 400);
        }
        $student_id = $data['student_id'] ?? null;
        $transaction_ref = $data['transaction_ref'];
        $amount = $data['amount'];
        $transaction_date = $data['transaction_date'];
        $bank_name = $data['bank_name'] ?? null;
        $account_number = $data['account_number'] ?? null;
        $narration = $data['narration'] ?? null;
        $status = $data['status'] ?? 'processed';

        $sql = "INSERT IGNORE INTO bank_transactions (transaction_ref, student_id, amount, transaction_date, bank_name, account_number, narration, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $transaction_ref,
                $student_id,
                $amount,
                $transaction_date,
                $bank_name,
                $account_number,
                $narration,
                $status
            ]);
            return $this->response(['message' => 'Bank transaction recorded']);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => 'Failed to record transaction', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Record an Mpesa transaction (callback from Mpesa API)
     */
    public function recordMpesaTransaction($data)
    {
        $required = ['mpesa_code', 'amount', 'transaction_date'];
        $missing = $this->validateRequired($data, $required);
        if ($missing) {
            return $this->response(['status' => 'error', 'message' => 'Missing fields: ' . implode(', ', $missing)], 400);
        }
        $student_id = $data['student_id'] ?? null;
        $mpesa_code = $data['mpesa_code'];
        $amount = $data['amount'];
        $transaction_date = $data['transaction_date'];
        $phone_number = $data['phone_number'] ?? null;
        $status = $data['status'] ?? 'processed';
        $raw_callback = json_encode($data);

        $sql = "INSERT IGNORE INTO mpesa_transactions (mpesa_code, student_id, amount, transaction_date, phone_number, status, raw_callback) VALUES (?, ?, ?, ?, ?, ?, ?)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $mpesa_code,
                $student_id,
                $amount,
                $transaction_date,
                $phone_number,
                $status,
                $raw_callback
            ]);
            return $this->response(['message' => 'Mpesa transaction recorded']);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => 'Failed to record transaction', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Record a manual/cash payment
     */
    public function recordCashPayment($data)
    {
        $required = ['student_id', 'amount', 'transaction_date'];
        $missing = $this->validateRequired($data, $required);
        if ($missing) {
            return $this->response(['status' => 'error', 'message' => 'Missing fields: ' . implode(', ', $missing)], 400);
        }
        $student_id = $data['student_id'];
        $amount = $data['amount'];
        $transaction_date = $data['transaction_date'];
        $details = $data['details'] ?? '';

        try {
            $stmt = $this->db->prepare("CALL sp_record_cash_payment(?, ?, ?, ?)");
            $stmt->execute([
                $student_id,
                $amount,
                $transaction_date,
                $details
            ]);
            return $this->response(['message' => 'Cash payment recorded']);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => 'Failed to record cash payment', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get all transactions for a student
     */
    public function getStudentTransactions($student_id)
    {
        if (!$student_id) {
            return $this->response(['status' => 'error', 'message' => 'Missing student_id'], 400);
        }
        $sql = "SELECT * FROM vw_all_school_payments WHERE student_id = ? ORDER BY transaction_date DESC";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$student_id]);
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $this->response(['transactions' => $transactions]);
        } catch (Exception $e) {
            return $this->response(['status' => 'error', 'message' => 'Failed to fetch transactions', 'details' => $e->getMessage()], 500);
        }
    }
}
