<?php

namespace App\API\Modules\finance;

use App\Database\Database;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Financial Reporting Manager
 * 
 * Handles comprehensive financial reporting and analytics:
 * - Fee collection reports
 * - Payment analysis and trends
 * - Budget vs actual reports
 * - Expense analysis
 * - Cash flow statements
 * - Financial dashboards
 * - Custom report generation
 * 
 * Integrates with stored procedures:
 * - sp_get_outstanding_fees_report
 * - sp_get_fee_collection_rate
 * - sp_send_fee_reminder
 * 
 * Uses database views:
 * - vw_all_school_payments
 * - vw_outstanding_fees
 */
class ReportingManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Get comprehensive financial dashboard
     * @param array $filters Filter criteria (academic_year, date_from, date_to)
     * @return array Response with dashboard data
     */
    public function getFinancialDashboard($filters = [])
    {
        try {
            $academicYear = $filters['academic_year'] ?? date('Y');

            // Get current term
            $stmt = $this->db->query("SELECT id, name, term_number FROM academic_terms WHERE status = 'current' LIMIT 1");
            $currentTerm = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentTermId = $currentTerm['id'] ?? null;
            $currentTermName = $currentTerm['name'] ?? 'N/A';

            // Get fee structure summary (total fees due and amount paid from obligations) - FULL YEAR
            $stmt = $this->db->prepare(
                "SELECT 
                    SUM(sfo.amount_due) as total_fees_due,
                    SUM(sfo.amount_paid) as total_allocated,
                    SUM(sfo.balance) as total_balance,
                    COUNT(DISTINCT sfo.student_id) as total_students
                FROM student_fee_obligations sfo
                WHERE sfo.academic_year = ?"
            );
            $stmt->execute([$academicYear]);
            $feeStructure = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get CURRENT TERM fee summary
            $termFees = ['total_due' => 0, 'total_collected' => 0, 'outstanding' => 0];
            if ($currentTermId) {
                $stmt = $this->db->prepare(
                    "SELECT 
                        COALESCE(SUM(sfo.amount_due), 0) as total_due,
                        COALESCE(SUM(sfo.amount_paid), 0) as total_collected,
                        COALESCE(SUM(sfo.balance), 0) as outstanding
                    FROM student_fee_obligations sfo
                    WHERE sfo.academic_year = ? AND sfo.term_id = ?"
                );
                $stmt->execute([$academicYear, $currentTermId]);
                $termFees = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // Get ACTUAL cash collected from payment_transactions (source of truth for cash received)
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total_cash_collected
                FROM payment_transactions
                WHERE status = 'confirmed' 
                  AND (academic_year = ? OR YEAR(created_at) = ?)"
            );
            $stmt->execute([$academicYear, $academicYear]);
            $actualCollected = $stmt->fetch(PDO::FETCH_ASSOC);

            // TODAY's collections
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total,
                    COUNT(*) as count
                FROM payment_transactions
                WHERE status = 'confirmed' AND DATE(payment_date) = CURDATE()"
            );
            $stmt->execute();
            $todayCollections = $stmt->fetch(PDO::FETCH_ASSOC);

            // THIS WEEK's collections (Monday to Sunday)
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total,
                    COUNT(*) as count
                FROM payment_transactions
                WHERE status = 'confirmed' 
                  AND YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)"
            );
            $stmt->execute();
            $weekCollections = $stmt->fetch(PDO::FETCH_ASSOC);

            // THIS MONTH's collections
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total,
                    COUNT(*) as count
                FROM payment_transactions
                WHERE status = 'confirmed' 
                  AND YEAR(payment_date) = YEAR(CURDATE())
                  AND MONTH(payment_date) = MONTH(CURDATE())"
            );
            $stmt->execute();
            $monthCollections = $stmt->fetch(PDO::FETCH_ASSOC);

            // YESTERDAY's collections (for comparison)
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total,
                    COUNT(*) as count
                FROM payment_transactions
                WHERE status = 'confirmed' AND DATE(payment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
            );
            $stmt->execute();
            $yesterdayCollections = $stmt->fetch(PDO::FETCH_ASSOC);

            // LAST WEEK's collections (for comparison)
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total,
                    COUNT(*) as count
                FROM payment_transactions
                WHERE status = 'confirmed' 
                  AND YEARWEEK(payment_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)"
            );
            $stmt->execute();
            $lastWeekCollections = $stmt->fetch(PDO::FETCH_ASSOC);

            // LAST MONTH's collections (for comparison)
            $stmt = $this->db->prepare(
                "SELECT 
                    COALESCE(SUM(amount_paid), 0) as total,
                    COUNT(*) as count
                FROM payment_transactions
                WHERE status = 'confirmed' 
                  AND YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                  AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))"
            );
            $stmt->execute();
            $lastMonthCollections = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fee defaulters count (students with overdue balances)
            $stmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT student_id) as count
                FROM student_fee_obligations
                WHERE academic_year = ? AND balance > 0 AND due_date < CURDATE()"
            );
            $stmt->execute([$academicYear]);
            $defaulters = $stmt->fetch(PDO::FETCH_ASSOC);

            // Students with full payment (balance = 0 for current term)
            $fullPaymentCount = 0;
            if ($currentTermId) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(DISTINCT student_id) as count
                    FROM student_fee_obligations
                    WHERE academic_year = ? AND term_id = ?
                    GROUP BY student_id
                    HAVING SUM(balance) = 0"
                );
                $stmt->execute([$academicYear, $currentTermId]);
                $fullPaymentCount = $stmt->rowCount();
            }

            // Use obligations data for consistent fee tracking
            $totalDue = (float) ($feeStructure['total_fees_due'] ?? 0);
            $totalAllocated = (float) ($feeStructure['total_allocated'] ?? 0);
            $totalCashCollected = (float) ($actualCollected['total_cash_collected'] ?? 0);
            $totalOutstanding = (float) ($feeStructure['total_balance'] ?? 0);

            // Credit balance = cash received but not yet allocated (advance payments for future terms)
            $creditBalance = $totalCashCollected - $totalAllocated;

            // Get payment statistics (use 'confirmed' which is the actual status in the ENUM)
            $stmt = $this->db->prepare("
                SELECT 
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(amount_paid) as total_amount
                FROM payment_transactions
                WHERE academic_year = ? AND status IN ('confirmed', 'completed')
                GROUP BY payment_method
            ");
            $stmt->execute([$academicYear]);
            $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Average payment & counts for reconciliation (include 'confirmed' status)
            $stmt = $this->db->prepare("SELECT AVG(amount_paid) as avg_amount, COUNT(*) as completed_count FROM payment_transactions WHERE status IN ('confirmed', 'completed') AND academic_year = ?");
            $stmt->execute([$academicYear]);
            $avgRow = $stmt->fetch(PDO::FETCH_ASSOC);

            // Unmatched MPESA summary (within academic year period, excluding reconciled ones)
            $startDate = $academicYear . '-01-01';
            $endDate = $academicYear . '-12-31';
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as unmatched_count, COALESCE(SUM(mt.amount),0) as unmatched_total
                FROM mpesa_transactions mt
                LEFT JOIN payment_transactions pt ON mt.mpesa_code = pt.reference_no
                WHERE pt.reference_no IS NULL 
                  AND (mt.status IS NULL OR mt.status NOT IN ('reconciled', 'matched'))
                  AND mt.transaction_date BETWEEN ? AND ?
            ");
            $stmt->execute([$startDate, $endDate]);
            $um = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalCompleted = array_sum(array_column($paymentMethods, 'transaction_count'));
            $reconciliationRate = 0;
            if ($totalCompleted > 0) {
                $reconciliationRate = (1 - ($um['unmatched_count'] / $totalCompleted)) * 100;
                if ($reconciliationRate < 0)
                    $reconciliationRate = 0;
            }

            // Get expense summary
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_expenses,
                    SUM(amount) as total_expense_amount,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_expenses
                FROM expenses
                WHERE YEAR(expense_date) = ?
            ");
            $stmt->execute([$academicYear]);
            $expenseData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get budget utilization (optional - table may not exist in some environments)
            try {
                $stmt = $this->db->prepare("
                    SELECT 
                        SUM(bli.allocated_amount) as total_budget,
                        SUM(COALESCE(e.amount, 0)) as total_spent
                    FROM budgets b
                    INNER JOIN budget_line_items bli ON b.id = bli.budget_id
                    LEFT JOIN expenses e ON e.budget_line_item_id = bli.id 
                        AND e.status = 'approved'
                    WHERE b.fiscal_year = ? AND b.status = 'approved'
                ");
                $stmt->execute([$academicYear]);
                $budgetData = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Budgets table may be missing in light-weight or test DBs. Use safe defaults and continue.
                $budgetData = ['total_budget' => 0, 'total_spent' => 0];
                error_log('ReportingManager: budgets query failed - continuing with defaults: ' . $e->getMessage());
            }

            // Calculate key metrics - use allocated amount for collection rate
            $collectionRate = $totalDue > 0
                ? ($totalAllocated / $totalDue * 100)
                : 0;

            $budgetUtilization = ($budgetData['total_budget'] ?? 0) > 0
                ? (($budgetData['total_spent'] ?? 0) / $budgetData['total_budget'] * 100)
                : 0;

            // Calculate term collection rate
            $termCollectionRate = (float) ($termFees['total_due'] ?? 0) > 0
                ? ((float) ($termFees['total_collected'] ?? 0) / (float) $termFees['total_due'] * 100)
                : 0;

            // Calculate percentage changes
            $todayChange = (float) ($yesterdayCollections['total'] ?? 0) > 0
                ? (((float) ($todayCollections['total'] ?? 0) - (float) $yesterdayCollections['total']) / (float) $yesterdayCollections['total'] * 100)
                : 0;
            $weekChange = (float) ($lastWeekCollections['total'] ?? 0) > 0
                ? (((float) ($weekCollections['total'] ?? 0) - (float) $lastWeekCollections['total']) / (float) $lastWeekCollections['total'] * 100)
                : 0;
            $monthChange = (float) ($lastMonthCollections['total'] ?? 0) > 0
                ? (((float) ($monthCollections['total'] ?? 0) - (float) $lastMonthCollections['total']) / (float) $lastMonthCollections['total'] * 100)
                : 0;

            return formatResponse(true, [
                'fees' => [
                    // Year totals
                    'total_due' => $totalDue,
                    'total_collected' => $totalAllocated,
                    'total_cash_received' => $totalCashCollected,
                    'total_outstanding' => $totalOutstanding,
                    'credit_balance' => $creditBalance,
                    'collection_rate' => round($collectionRate, 2),
                    'student_count' => (int) ($feeStructure['total_students'] ?? 0),
                    // Current term
                    'term_due' => (float) ($termFees['total_due'] ?? 0),
                    'term_collected' => (float) ($termFees['total_collected'] ?? 0),
                    'term_outstanding' => (float) ($termFees['outstanding'] ?? 0),
                    'term_collection_rate' => round($termCollectionRate, 2),
                    'current_term_name' => $currentTermName,
                    // Student metrics
                    'defaulters_count' => (int) ($defaulters['count'] ?? 0),
                    'full_payment_count' => $fullPaymentCount
                ],
                'collections' => [
                    // Today
                    'today_total' => (float) ($todayCollections['total'] ?? 0),
                    'today_count' => (int) ($todayCollections['count'] ?? 0),
                    'today_change' => round($todayChange, 1),
                    // This week
                    'week_total' => (float) ($weekCollections['total'] ?? 0),
                    'week_count' => (int) ($weekCollections['count'] ?? 0),
                    'week_change' => round($weekChange, 1),
                    // This month
                    'month_total' => (float) ($monthCollections['total'] ?? 0),
                    'month_count' => (int) ($monthCollections['count'] ?? 0),
                    'month_change' => round($monthChange, 1),
                    // Yesterday (for reference)
                    'yesterday_total' => (float) ($yesterdayCollections['total'] ?? 0)
                ],
                'payments' => [
                    'by_method' => $paymentMethods,
                    'total_transactions' => array_sum(array_column($paymentMethods, 'transaction_count')),
                    'total_amount' => array_sum(array_column($paymentMethods, 'total_amount')),
                    'avg_amount' => (float) ($avgRow['avg_amount'] ?? 0),
                    'unreconciled_count' => (int) ($um['unmatched_count'] ?? 0),
                    'unreconciled_total' => (float) ($um['unmatched_total'] ?? 0),
                    'reconciliation_rate' => round($reconciliationRate, 2)
                ],
                'expenses' => [
                    'total_count' => (int) ($expenseData['total_expenses'] ?? 0),
                    'total_amount' => (float) ($expenseData['total_expense_amount'] ?? 0),
                    'pending_count' => (int) ($expenseData['pending_expenses'] ?? 0)
                ],
                'budget' => [
                    'total_allocated' => (float) ($budgetData['total_budget'] ?? 0),
                    'total_spent' => (float) ($budgetData['total_spent'] ?? 0),
                    'utilization_rate' => round($budgetUtilization, 2)
                ],
                'academic_year' => $academicYear,
                'current_term_id' => $currentTermId
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate dashboard: ' . $e->getMessage());
        }
    }

    /**
     * Get fee collection trends over time
     * @param array $filters Filter criteria
     * @return array Response with trend data
     */
    public function getFeeCollectionTrends($filters = [])
    {
        try {
            // Accept both 'completed' and 'confirmed' statuses (confirmed is used in production)
            $sql = "SELECT 
                        DATE_FORMAT(payment_date, '%Y-%m') as month,
                        COUNT(*) as transaction_count,
                        SUM(amount_paid) as total_collected,
                        AVG(amount_paid) as average_amount
                    FROM payment_transactions
                    WHERE status IN ('completed', 'confirmed')";

            $params = [];

            if (!empty($filters['date_from'])) {
                $sql .= " AND payment_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND payment_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " GROUP BY DATE_FORMAT(payment_date, '%Y-%m') ORDER BY month ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['trends' => $trends]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get trends: ' . $e->getMessage());
        }
    }

    /**
     * Get recent payment transactions
     * @param int $limit Number of recent transactions to return
     * @return array Response with recent transactions
     */
    public function getRecentTransactions($limit = 10)
    {
        try {
            $limit = (int) $limit;
            // Accept both 'completed' and 'confirmed' statuses (confirmed is used in production)
            $stmt = $this->db->prepare("SELECT pt.id, pt.reference_no as reference, pt.payment_date, pt.payment_method as method, pt.amount_paid as amount, CONCAT(COALESCE(s.first_name,''),' ',COALESCE(s.last_name,'')) as student_name FROM payment_transactions pt LEFT JOIN students s ON s.id = pt.student_id WHERE pt.status IN ('completed', 'confirmed') ORDER BY pt.payment_date DESC LIMIT ?");
            $stmt->bindValue(1, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['recent_transactions' => $rows]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get recent transactions: ' . $e->getMessage());
        }
    }

    /**
     * Get outstanding fees aging report
     * @param int $academicYear Academic year
     * @return array Response with aging data
     */
    public function getOutstandingFeesAging($academicYear)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    CASE 
                        WHEN DATEDIFF(NOW(), sfo.due_date) <= 30 THEN '0-30 days'
                        WHEN DATEDIFF(NOW(), sfo.due_date) <= 60 THEN '31-60 days'
                        WHEN DATEDIFF(NOW(), sfo.due_date) <= 90 THEN '61-90 days'
                        ELSE 'Over 90 days'
                    END as aging_bracket,
                    COUNT(DISTINCT sfo.student_id) as student_count,
                    SUM(sfo.balance) as total_outstanding
                FROM student_fee_obligations sfo
                WHERE sfo.academic_year = ? AND sfo.balance > 0
                GROUP BY aging_bracket
                ORDER BY 
                    CASE aging_bracket
                        WHEN '0-30 days' THEN 1
                        WHEN '31-60 days' THEN 2
                        WHEN '61-90 days' THEN 3
                        ELSE 4
                    END"
            );

            $stmt->execute([$academicYear]);
            $aging = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['aging_report' => $aging]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate aging report: ' . $e->getMessage());
        }
    }

    /**
     * Get budget vs actual comparison report
     * @param int $budgetId Budget ID
     * @return array Response with comparison data
     */
    public function getBudgetVsActualReport($budgetId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    bli.category,
                    d.name as department,
                    bli.allocated_amount,
                    COALESCE(SUM(e.amount), 0) as actual_spent,
                    (bli.allocated_amount - COALESCE(SUM(e.amount), 0)) as variance,
                    CASE 
                        WHEN bli.allocated_amount > 0 THEN
                            (COALESCE(SUM(e.amount), 0) / bli.allocated_amount * 100)
                        ELSE 0
                    END as utilization_percentage,
                    CASE
                        WHEN COALESCE(SUM(e.amount), 0) > bli.allocated_amount THEN 'Over Budget'
                        WHEN COALESCE(SUM(e.amount), 0) = bli.allocated_amount THEN 'On Budget'
                        ELSE 'Under Budget'
                    END as status
                FROM budget_line_items bli
                LEFT JOIN departments d ON bli.department_id = d.id
                LEFT JOIN expenses e ON e.budget_line_item_id = bli.id 
                    AND e.status = 'approved'
                WHERE bli.budget_id = ?
                GROUP BY bli.id, bli.category, d.name, bli.allocated_amount
                ORDER BY variance DESC
            ");

            $stmt->execute([$budgetId]);
            $comparison = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate summary
            $totalAllocated = array_sum(array_column($comparison, 'allocated_amount'));
            $totalSpent = array_sum(array_column($comparison, 'actual_spent'));

            return formatResponse(true, [
                'line_items' => $comparison,
                'summary' => [
                    'total_allocated' => $totalAllocated,
                    'total_spent' => $totalSpent,
                    'total_variance' => $totalAllocated - $totalSpent,
                    'overall_utilization' => $totalAllocated > 0 ? ($totalSpent / $totalAllocated * 100) : 0
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate budget report: ' . $e->getMessage());
        }
    }

    /**
     * Get expense breakdown by category
     * @param array $filters Filter criteria
     * @return array Response with expense breakdown
     */
    public function getExpenseBreakdown($filters = [])
    {
        try {
            $sql = "SELECT 
                        expense_category,
                        d.name as department,
                        COUNT(*) as transaction_count,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        MIN(amount) as min_amount,
                        MAX(amount) as max_amount
                    FROM expenses e
                    LEFT JOIN departments d ON e.department_id = d.id
                    WHERE status = 'approved'";

            $params = [];

            if (!empty($filters['date_from'])) {
                $sql .= " AND expense_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND expense_date <= ?";
                $params[] = $filters['date_to'];
            }

            $sql .= " GROUP BY expense_category, d.name ORDER BY total_amount DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['expense_breakdown' => $breakdown]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate expense breakdown: ' . $e->getMessage());
        }
    }

    /**
     * Get cash flow statement
     * @param array $filters Filter criteria
     * @return array Response with cash flow data
     */
    public function getCashFlowStatement($filters = [])
    {
        try {
            $dateFrom = $filters['date_from'] ?? date('Y-01-01');
            $dateTo = $filters['date_to'] ?? date('Y-m-d');

            // Calculate total inflows (payments received)
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(amount_paid) as total_inflow,
                    COUNT(*) as inflow_count
                FROM payment_transactions
                WHERE payment_date BETWEEN ? AND ?
                    AND status = 'completed'
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $inflows = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate total outflows (expenses)
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(amount) as total_outflow,
                    COUNT(*) as outflow_count
                FROM expenses
                WHERE expense_date BETWEEN ? AND ?
                    AND status = 'approved'
            ");
            $stmt->execute([$dateFrom, $dateTo]);
            $outflows = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate net cash flow
            $netCashFlow = ($inflows['total_inflow'] ?? 0) - ($outflows['total_outflow'] ?? 0);

            return formatResponse(true, [
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ],
                'inflows' => [
                    'total' => (float) ($inflows['total_inflow'] ?? 0),
                    'count' => (int) ($inflows['inflow_count'] ?? 0)
                ],
                'outflows' => [
                    'total' => (float) ($outflows['total_outflow'] ?? 0),
                    'count' => (int) ($outflows['outflow_count'] ?? 0)
                ],
                'net_cash_flow' => $netCashFlow,
                'cash_flow_status' => $netCashFlow >= 0 ? 'Positive' : 'Negative'
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate cash flow statement: ' . $e->getMessage());
        }
    }

    /**
     * Export financial report to CSV
     * @param string $reportType Type of report
     * @param array $data Report data
     * @return array Response with file path
     */
    public function exportReport($reportType, $data)
    {
        try {
            $filename = $reportType . '_' . date('YmdHis') . '.csv';
            $filepath = __DIR__ . '/../../../temp/' . $filename;

            $fp = fopen($filepath, 'w');

            if (!$fp) {
                return formatResponse(false, null, 'Failed to create export file');
            }

            // Write headers
            if (!empty($data) && is_array($data[0])) {
                fputcsv($fp, array_keys($data[0]));
            }

            // Write data rows
            foreach ($data as $row) {
                fputcsv($fp, $row);
            }

            fclose($fp);

            return formatResponse(true, [
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/temp/' . $filename
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to export report: ' . $e->getMessage());
        }
    }

    /**
     * Get outstanding fees report using stored procedure
     * @param array $filters Optional filters
     * @return array Response with outstanding fees report
     */
    public function getOutstandingFeesDetailedReport($filters = [])
    {
        try {
            // Call stored procedure sp_get_outstanding_fees_report
            $stmt = $this->db->prepare("CALL sp_get_outstanding_fees_report(?, ?)");
            $stmt->execute([
                $filters['class_id'] ?? null,
                $filters['academic_year'] ?? null
            ]);

            $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            $totalOutstanding = array_sum(array_column($report, 'outstanding_amount'));
            $totalStudents = count($report);

            return formatResponse(true, [
                'report' => $report,
                'summary' => [
                    'total_students' => $totalStudents,
                    'total_outstanding' => $totalOutstanding,
                    'average_per_student' => $totalStudents > 0 ? $totalOutstanding / $totalStudents : 0
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get outstanding fees report: ' . $e->getMessage());
        }
    }

    /**
     * Get fee collection summary by class and term
     * @param int $classId Class ID
     * @param string $term Term
     * @return array Response
     */
    public function getClassFeeCollectionReport($classId, $term = null)
    {
        try {
            // Use sp_get_class_fee_schedule for detailed class fee breakdown
            $stmt = $this->db->prepare("CALL sp_get_class_fee_schedule(?, ?)");
            $stmt->execute([$classId, $term]);

            $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, [
                'class_id' => $classId,
                'term' => $term,
                'fee_schedule' => $schedule,
                'total_expected' => array_sum(array_column($schedule, 'amount'))
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get class fee collection report: ' . $e->getMessage());
        }
    }

    /**
     * Get pivot table: Collections by Class
     * @param int $academicYear
     * @param int|null $termId
     * @return array
     */
    public function getPivotByClass($academicYear = null, $termId = null)
    {
        try {
            $academicYear = $academicYear ?? date('Y');

            $sql = "SELECT 
                        c.name as class_name,
                        sl.name as level_name,
                        COUNT(DISTINCT s.id) as student_count,
                        COALESCE(SUM(sfo.amount_due), 0) as total_due,
                        COALESCE(SUM(sfo.amount_paid), 0) as total_paid,
                        COALESCE(SUM(sfo.balance), 0) as balance,
                        ROUND(COALESCE(SUM(sfo.amount_paid), 0) / NULLIF(COALESCE(SUM(sfo.amount_due), 0), 0) * 100, 1) as collection_rate
                    FROM students s
                    JOIN class_streams cs ON s.stream_id = cs.id
                    JOIN classes c ON cs.class_id = c.id
                    JOIN school_levels sl ON c.level_id = sl.id
                    LEFT JOIN student_fee_obligations sfo ON s.id = sfo.student_id AND sfo.academic_year = ?";

            $params = [$academicYear];

            if ($termId) {
                $sql .= " AND sfo.term_id = ?";
                $params[] = $termId;
            }

            $sql .= " WHERE s.status = 'active'
                      GROUP BY c.id, c.name, sl.name
                      ORDER BY sl.id, c.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['pivot_by_class' => $data]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get pivot by class: ' . $e->getMessage());
        }
    }

    /**
     * Get pivot table: Collections by Payment Method
     * @param int $academicYear
     * @param int|null $termId
     * @return array
     */
    public function getPivotByPaymentMethod($academicYear = null, $termId = null)
    {
        try {
            $academicYear = $academicYear ?? date('Y');

            $sql = "SELECT 
                        COALESCE(pt.payment_method, 'Unknown') as payment_method,
                        COUNT(*) as transaction_count,
                        COALESCE(SUM(pt.amount_paid), 0) as total_amount,
                        ROUND(AVG(pt.amount_paid), 2) as avg_amount,
                        MIN(pt.amount_paid) as min_amount,
                        MAX(pt.amount_paid) as max_amount
                    FROM payment_transactions pt
                    WHERE pt.status = 'confirmed'
                      AND (pt.academic_year = ? OR YEAR(pt.created_at) = ?)";

            $params = [$academicYear, $academicYear];

            if ($termId) {
                $sql .= " AND pt.term_id = ?";
                $params[] = $termId;
            }

            $sql .= " GROUP BY pt.payment_method ORDER BY total_amount DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['pivot_by_method' => $data]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get pivot by method: ' . $e->getMessage());
        }
    }

    /**
     * Get pivot table: Collections by Student Type (Day/Boarder)
     * @param int $academicYear
     * @param int|null $termId
     * @return array
     */
    public function getPivotByStudentType($academicYear = null, $termId = null)
    {
        try {
            $academicYear = $academicYear ?? date('Y');

            $sql = "SELECT 
                        st.name as student_type,
                        COUNT(DISTINCT s.id) as student_count,
                        COALESCE(SUM(sfo.amount_due), 0) as total_due,
                        COALESCE(SUM(sfo.amount_paid), 0) as total_paid,
                        COALESCE(SUM(sfo.balance), 0) as balance,
                        ROUND(COALESCE(SUM(sfo.amount_paid), 0) / NULLIF(COALESCE(SUM(sfo.amount_due), 0), 0) * 100, 1) as collection_rate
                    FROM students s
                    JOIN student_types st ON s.student_type_id = st.id
                    LEFT JOIN student_fee_obligations sfo ON s.id = sfo.student_id AND sfo.academic_year = ?";

            $params = [$academicYear];

            if ($termId) {
                $sql .= " AND sfo.term_id = ?";
                $params[] = $termId;
            }

            $sql .= " WHERE s.status = 'active'
                      GROUP BY st.id, st.name
                      ORDER BY st.id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['pivot_by_type' => $data]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get pivot by student type: ' . $e->getMessage());
        }
    }

    /**
     * Get pivot table: Daily Collections for current month
     * @return array
     */
    public function getPivotDailyCollections()
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT 
                    DATE(pt.payment_date) as date,
                    DAYNAME(pt.payment_date) as day_name,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(pt.amount_paid), 0) as total_amount
                FROM payment_transactions pt
                WHERE pt.status = 'confirmed'
                  AND YEAR(pt.payment_date) = YEAR(CURDATE())
                  AND MONTH(pt.payment_date) = MONTH(CURDATE())
                GROUP BY DATE(pt.payment_date), DAYNAME(pt.payment_date)
                ORDER BY date DESC"
            );
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['pivot_daily' => $data]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get daily collections: ' . $e->getMessage());
        }
    }

    /**
     * Get pivot table: Fee Type Breakdown
     * @param int $academicYear
     * @param int|null $termId
     * @return array
     */
    public function getPivotByFeeType($academicYear = null, $termId = null)
    {
        try {
            $academicYear = $academicYear ?? date('Y');

            $sql = "SELECT 
                        ft.name as fee_type,
                        COUNT(DISTINCT sfo.student_id) as student_count,
                        COALESCE(SUM(sfo.amount_due), 0) as total_due,
                        COALESCE(SUM(sfo.amount_paid), 0) as total_paid,
                        COALESCE(SUM(sfo.balance), 0) as balance,
                        ROUND(COALESCE(SUM(sfo.amount_paid), 0) / NULLIF(COALESCE(SUM(sfo.amount_due), 0), 0) * 100, 1) as collection_rate
                    FROM student_fee_obligations sfo
                    JOIN fee_structures_detailed fsd ON sfo.fee_structure_detail_id = fsd.id
                    JOIN fee_types ft ON fsd.fee_type_id = ft.id
                    WHERE sfo.academic_year = ?";

            $params = [$academicYear];

            if ($termId) {
                $sql .= " AND sfo.term_id = ?";
                $params[] = $termId;
            }

            $sql .= " GROUP BY ft.id, ft.name ORDER BY total_due DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['pivot_by_fee_type' => $data]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get pivot by fee type: ' . $e->getMessage());
        }
    }

    /**
     * Get top fee defaulters
     * @param int $limit
     * @param int $academicYear
     * @return array
     */
    public function getTopDefaulters($limit = 20, $academicYear = null)
    {
        try {
            $academicYear = $academicYear ?? date('Y');
            $limit = (int) $limit;

            $stmt = $this->db->prepare(
                "SELECT 
                    s.id as student_id,
                    s.admission_no,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name,
                    c.name as class_name,
                    st.name as student_type,
                    SUM(sfo.amount_due) as total_due,
                    SUM(sfo.amount_paid) as total_paid,
                    SUM(sfo.balance) as balance,
                    MIN(sfo.due_date) as oldest_due_date,
                    DATEDIFF(CURDATE(), MIN(sfo.due_date)) as days_overdue,
                    -- Parent/Guardian contact information
                    p.id as parent_id,
                    CONCAT(p.first_name, ' ', p.last_name) as parent_name,
                    p.phone_1 as parent_phone,
                    p.phone_2 as parent_phone_alt,
                    p.email as parent_email,
                    sp.relationship as parent_relationship,
                    sp.is_primary_contact
                FROM students s
                JOIN class_streams cs ON s.stream_id = cs.id
                JOIN classes c ON cs.class_id = c.id
                JOIN student_types st ON s.student_type_id = st.id
                JOIN student_fee_obligations sfo ON s.id = sfo.student_id
                -- Join with parent information (get primary contact first, then any parent)
                LEFT JOIN student_parents sp ON s.id = sp.student_id
                LEFT JOIN parents p ON sp.parent_id = p.id AND p.status = 'active'
                WHERE s.status = 'active'
                  AND sfo.academic_year = ?
                  AND sfo.balance > 0
                GROUP BY s.id, s.admission_no, s.first_name, s.last_name, c.name, st.name,
                         p.id, p.first_name, p.last_name, p.phone_1, p.phone_2, p.email,
                         sp.relationship, sp.is_primary_contact
                HAVING balance > 0
                ORDER BY balance DESC, is_primary_contact DESC
                LIMIT ?"
            );
            $stmt->bindValue(1, $academicYear, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['top_defaulters' => $data]);
        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get top defaulters: ' . $e->getMessage());
        }
    }
}

