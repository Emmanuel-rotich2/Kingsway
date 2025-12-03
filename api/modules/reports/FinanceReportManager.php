<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class FinanceReportManager extends BaseAPI
{
    public function getFeeSummary($filters = [])
    {
        // Get fee summary for a term
        $termId = $filters['academic_term_id'] ?? null;
        $sql = "SELECT s.id as student_id, s.first_name, s.last_name, SUM(fb.balance) as outstanding_balance
                FROM students s
                JOIN student_fee_balances fb ON s.id = fb.student_id
                WHERE fb.academic_term_id = ?
                GROUP BY s.id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$termId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getFeePaymentTrends($filters = [])
    {
        // Example: Sum payments per month
        $sql = "SELECT YEAR(payment_date) as year, MONTH(payment_date) as month, SUM(amount) as total_paid
                FROM payments
                WHERE status = 'confirmed'
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getDiscountStats($filters = [])
    {
        // Example: Count and sum discounts by type
        $sql = "SELECT discount_type, COUNT(*) as count, SUM(discount_value) as total_value
                FROM fee_discounts_waivers
                WHERE status = 'active'
                GROUP BY discount_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getArrearsStats($filters = [])
    {
        // Example: Sum outstanding balances by class
        $sql = "SELECT cs.class_id, SUM(fb.balance) as total_arrears
                FROM student_fee_balances fb
                JOIN students s ON fb.student_id = s.id
                JOIN class_streams cs ON s.stream_id = cs.id
                WHERE fb.balance > 0
                GROUP BY cs.class_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getFinancialTransactionsSummary($filters = [])
    {
        // Example: Sum transactions by type
        $sql = "SELECT transaction_type, SUM(amount) as total
                FROM financial_transactions
                GROUP BY transaction_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getBankTransactionsSummary($filters = [])
    {
        // Example: Sum transactions by bank account
        $sql = "SELECT bank_account_id, SUM(amount) as total
                FROM financial_transactions
                WHERE transaction_type = 'bank'
                GROUP BY bank_account_id";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getFeeStructureChangeLog($filters = [])
    {
        // Example: Get fee structure change log
        $sql = "SELECT * FROM fee_structure_change_log ORDER BY changed_at DESC LIMIT 100";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
