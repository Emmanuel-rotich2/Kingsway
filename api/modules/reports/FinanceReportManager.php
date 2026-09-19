<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class FinanceReportManager extends BaseAPI
{
    /**
     * Fee summary per class, filtered by term (terms.id).
     * Current term fallback: latest academic_year_terms with status 'current'.
     */
    public function getFeeSummary($filters = [])
    {
        $termId = $filters['academic_term_id'] ?? null;

        if (!$termId) {
            $termRow = $this->db->query(
                "SELECT term_id FROM academic_year_terms WHERE status = 'current' ORDER BY id DESC LIMIT 1"
            )->fetch(\PDO::FETCH_ASSOC);
            $termId = $termRow['term_id'] ?? null;
        }

        $where = [];
        $params = [];
        if ($termId) {
            $where[] = '(fb.term_id = ? OR fb.term_id = (SELECT ayt.term_id FROM academic_year_terms ayt WHERE ayt.id = ?))';
            $params[] = (int) $termId;
            $params[] = (int) $termId;
        }
        if (!empty($filters['class_id'])) {
            $where[] = 'ayc.class_id = ?';
            $params[] = (int) $filters['class_id'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT
                    c.name AS class_name,
                    COUNT(DISTINCT fb.student_id) AS student_count,
                    COALESCE(SUM(fb.amount_due), 0) AS total_fees,
                    COALESCE(SUM(fb.amount_paid), 0) AS total_paid,
                    COALESCE(SUM(fb.balance), 0) AS total_outstanding
                FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " fb
                LEFT JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                $whereSql
                GROUP BY c.id, c.name
                ORDER BY c.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $totals = [
            'class_name'        => 'TOTAL',
            'student_count'     => array_sum(array_column($rows, 'student_count')),
            'total_fees'        => array_sum(array_column($rows, 'total_fees')),
            'total_paid'        => array_sum(array_column($rows, 'total_paid')),
            'total_outstanding' => array_sum(array_column($rows, 'total_outstanding')),
        ];

        return ['rows' => $rows, 'totals' => $totals, 'term_id' => $termId];
    }

    /**
     * Monthly payment trends from the live payment view.
     */
    public function getFeePaymentTrends($filters = [])
    {
        $sql = "SELECT YEAR(payment_date) AS year, MONTH(payment_date) AS month, SUM(amount_paid) AS total_paid
                FROM vw_payment_transactions_with_amount
                WHERE status = 'confirmed'
                GROUP BY year, month
                ORDER BY year DESC, month DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Discount/waiver stats — fee_discounts_waivers exists in the live schema.
     */
    public function getDiscountStats($filters = [])
    {
        $sql = "SELECT discount_type, COUNT(*) AS count, COALESCE(SUM(discount_value), 0) AS total_value
                FROM fee_discounts_waivers
                WHERE status = 'active'
                GROUP BY discount_type";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Arrears per class from the live fee-balance view (class via enrollments).
     */
    public function getArrearsStats($filters = [])
    {
        $where = ['fb.balance > 0'];
        $params = [];
        if (!empty($filters['academic_term_id'])) {
            $where[] = '(fb.term_id = ? OR fb.term_id = (SELECT ayt.term_id FROM academic_year_terms ayt WHERE ayt.id = ?))';
            $params[] = (int) $filters['academic_term_id'];
            $params[] = (int) $filters['academic_term_id'];
        }
        if (!empty($filters['class_id'])) {
            $where[] = 'ayc.class_id = ?';
            $params[] = (int) $filters['class_id'];
        }
        $sql = "SELECT
                    c.id AS class_id,
                    c.name AS class_name,
                    COUNT(DISTINCT fb.student_id) AS students_in_arrears,
                    COALESCE(SUM(fb.balance), 0) AS total_arrears
                FROM " . \App\API\Services\ReadReplicaService::qualifiedRef('student_fee_balances') . " fb
                LEFT JOIN student_academic_enrollments sae ON sae.id = fb.student_academic_enrollment_id
                LEFT JOIN academic_year_class_streams aycs ON aycs.id = sae.academic_year_class_stream_id
                LEFT JOIN academic_year_classes ayc ON ayc.id = aycs.academic_year_class_id
                LEFT JOIN classes c ON c.id = ayc.class_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY c.id, c.name
                ORDER BY total_arrears DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Audit trail of financial actions (payments + approval workflows) —
     * read from the file-based audit log.
     */
    public function getFinancialTransactionsSummary($filters = [])
    {
        $entries = \App\API\Includes\FileLogger::recent('audit', 500);
        $rows = [];
        foreach ($entries as $e) {
            if (!in_array($e['entity'] ?? null, ['payments', 'workflow'], true)) {
                continue;
            }
            $rows[] = [
                'id' => null,
                'created_at' => $e['timestamp'] ?? null,
                'action' => $e['action'] ?? null,
                'entity' => $e['entity'] ?? null,
                'entity_id' => $e['entity_id'] ?? null,
                'details' => isset($e['details']) ? json_encode($e['details']) : null,
                'ip_address' => $e['ip'] ?? $e['ip_address'] ?? null,
                'user_name' => null,
            ];
        }
        return $rows;
    }

    /**
     * Transaction types by volume/value — grouped from the live payment view.
     */
    public function getBankTransactionsSummary($filters = [])
    {
        $sql = "SELECT
                    payment_method AS transaction_type,
                    COUNT(*) AS transaction_count,
                    COALESCE(SUM(amount), 0) AS total_amount,
                    CASE WHEN payment_method IN ('bank_transfer', 'cheque') THEN 1 ELSE 0 END AS is_bank_type
                FROM vw_payment_transactions_with_amount
                WHERE status = 'confirmed'
                GROUP BY payment_method
                ORDER BY is_bank_type DESC, total_amount DESC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Fee structure change log — read from the file-based finance log.
     */
    public function getFeeStructureChangeLog($filters = [])
    {
        $entries = \App\API\Includes\FileLogger::recent('finance', 100);
        $rows = [];
        foreach ($entries as $e) {
            $action = $e['action'] ?? null;
            if (!in_array($action, ['fee_structure_create', 'fee_structure_update', 'fee_structure_delete'], true)) {
                continue;
            }
            $rows[] = [
                'id' => null,
                'action' => $action,
                'entity' => $e['entity'] ?? null,
                'entity_id' => $e['entity_id'] ?? null,
                'user_id' => $e['user_id'] ?? null,
                'details' => isset($e['details']) ? json_encode($e['details']) : null,
                'changed_at' => $e['timestamp'] ?? null,
            ];
        }
        return $rows;
    }
}
