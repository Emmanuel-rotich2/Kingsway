<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class FinanceReportManager extends BaseAPI
{
    public function getFeeSummary($filters = [])
    {
        $termId = $filters['academic_term_id'] ?? null;

        if (!$termId) {
            $termRow = $this->db->query(
                "SELECT id FROM academic_terms WHERE status = 'current' ORDER BY id DESC LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId = $termRow['id'] ?? null;
        }

        $where  = $termId ? 'WHERE fo.term_id = ?' : '';
        $params = $termId ? [$termId] : [];

        $sql = "SELECT
                    c.name AS class_name,
                    COUNT(DISTINCT fo.student_id) AS student_count,
                    COALESCE(SUM(fo.amount_due), 0) AS total_fees,
                    COALESCE(SUM(fo.amount_paid), 0) AS total_paid,
                    COALESCE(SUM(fo.balance), 0) AS total_outstanding
                FROM student_fee_obligations fo
                JOIN students s ON s.id = fo.student_id AND s.status = 'active'
                LEFT JOIN class_streams cs ON cs.id = s.stream_id
                LEFT JOIN classes c ON c.id = cs.class_id
                $where
                GROUP BY c.id, c.name
                ORDER BY c.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Compute totals
        $totals = [
            'class_name'        => 'TOTAL',
            'student_count'     => array_sum(array_column($rows, 'student_count')),
            'total_fees'        => array_sum(array_column($rows, 'total_fees')),
            'total_paid'        => array_sum(array_column($rows, 'total_paid')),
            'total_outstanding' => array_sum(array_column($rows, 'total_outstanding')),
        ];

        return ['rows' => $rows, 'totals' => $totals, 'term_id' => $termId];
    }
    public function getFeePaymentTrends($filters = [])
    {
        $amountExpr = \App\API\Includes\sql_coalesce_existing_columns('payments', ['amount_paid', 'amount'], '0', 300, true);
        $sql = "SELECT YEAR(payment_date) as year, MONTH(payment_date) as month, SUM($amountExpr) as total_paid
                FROM payment_transactions
                WHERE status = 'confirmed'
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getDiscountStats($filters = [])
    {
        try {
            $sql = "SELECT discount_type, COUNT(*) as count, COALESCE(SUM(discount_value), 0) as total_value
                    FROM fee_discounts_waivers
                    WHERE status = 'active'
                    GROUP BY discount_type";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function getArrearsStats($filters = [])
    {
        // Sum outstanding balances by class with class name
        $sql = "SELECT
                    c.id AS class_id,
                    c.name AS class_name,
                    COUNT(DISTINCT fb.student_id) AS students_in_arrears,
                    COALESCE(SUM(fb.balance), 0) AS total_arrears
                FROM student_fee_balances fb
                JOIN students s ON fb.student_id = s.id AND s.status = 'active'
                JOIN class_streams cs ON s.stream_id = cs.id
                LEFT JOIN classes c ON c.id = cs.class_id
                WHERE fb.balance > 0
                GROUP BY c.id, c.name
                ORDER BY total_arrears DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getFinancialTransactionsSummary($filters = [])
    {
        $sql = "SELECT transaction_type, SUM(amount) as total
                FROM financial_transactions
                GROUP BY transaction_type";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getBankTransactionsSummary($filters = [])
    {
        // All transaction types; bank-related types shown first
        $sql = "SELECT
                    transaction_type,
                    COUNT(*) AS transaction_count,
                    COALESCE(SUM(amount), 0) AS total_amount,
                    CASE WHEN transaction_type IN ('bank','bank_transfer','cheque','rtgs','eft')
                         THEN 1 ELSE 0 END AS is_bank_type
                FROM financial_transactions
                GROUP BY transaction_type
                ORDER BY is_bank_type DESC, total_amount DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getFeeStructureChangeLog($filters = [])
    {
        // Get fee structure change log; fall back gracefully if table doesn't exist
        try {
            $sql = "SELECT * FROM fee_structure_change_log ORDER BY changed_at DESC LIMIT 100";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Table may not exist in all environments
            return [];
        }
    }
}
