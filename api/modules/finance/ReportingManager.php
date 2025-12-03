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

            // Get fee collection summary
            $stmt = $this->db->prepare("
                SELECT 
                    SUM(sfo.amount_due) as total_fees_due,
                    SUM(sfb.total_paid) as total_collected,
                    SUM(sfb.balance) as total_outstanding,
                    COUNT(DISTINCT sfo.student_id) as total_students
                FROM student_fee_obligations sfo
                LEFT JOIN student_fee_balances sfb ON sfo.student_id = sfb.student_id 
                    AND sfo.academic_year = sfb.academic_year
                WHERE sfo.academic_year = ?
            ");
            $stmt->execute([$academicYear]);
            $feeData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get payment statistics
            $stmt = $this->db->prepare("
                SELECT 
                    payment_method,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM payment_transactions
                WHERE academic_year = ? AND status = 'completed'
                GROUP BY payment_method
            ");
            $stmt->execute([$academicYear]);
            $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

            // Get budget utilization
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

            // Calculate key metrics
            $collectionRate = $feeData['total_fees_due'] > 0
                ? ($feeData['total_collected'] / $feeData['total_fees_due'] * 100)
                : 0;

            $budgetUtilization = ($budgetData['total_budget'] ?? 0) > 0
                ? (($budgetData['total_spent'] ?? 0) / $budgetData['total_budget'] * 100)
                : 0;

            return formatResponse(true, [
                'fees' => [
                    'total_due' => (float) ($feeData['total_fees_due'] ?? 0),
                    'total_collected' => (float) ($feeData['total_collected'] ?? 0),
                    'total_outstanding' => (float) ($feeData['total_outstanding'] ?? 0),
                    'collection_rate' => round($collectionRate, 2),
                    'student_count' => (int) ($feeData['total_students'] ?? 0)
                ],
                'payments' => [
                    'by_method' => $paymentMethods,
                    'total_transactions' => array_sum(array_column($paymentMethods, 'transaction_count')),
                    'total_amount' => array_sum(array_column($paymentMethods, 'total_amount'))
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
                'academic_year' => $academicYear
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
            $sql = "SELECT 
                        DATE_FORMAT(payment_date, '%Y-%m') as month,
                        COUNT(*) as transaction_count,
                        SUM(amount) as total_collected,
                        AVG(amount) as average_amount
                    FROM payment_transactions
                    WHERE status = 'completed'";

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
     * Get outstanding fees aging report
     * @param int $academicYear Academic year
     * @return array Response with aging data
     */
    public function getOutstandingFeesAging($academicYear)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    CASE 
                        WHEN DATEDIFF(NOW(), sfo.due_date) <= 30 THEN '0-30 days'
                        WHEN DATEDIFF(NOW(), sfo.due_date) <= 60 THEN '31-60 days'
                        WHEN DATEDIFF(NOW(), sfo.due_date) <= 90 THEN '61-90 days'
                        ELSE 'Over 90 days'
                    END as aging_bracket,
                    COUNT(DISTINCT sfo.student_id) as student_count,
                    SUM(sfb.balance) as total_outstanding
                FROM student_fee_obligations sfo
                INNER JOIN student_fee_balances sfb ON sfo.student_id = sfb.student_id
                WHERE sfo.academic_year = ? AND sfb.balance > 0
                GROUP BY aging_bracket
                ORDER BY 
                    CASE aging_bracket
                        WHEN '0-30 days' THEN 1
                        WHEN '31-60 days' THEN 2
                        WHEN '61-90 days' THEN 3
                        ELSE 4
                    END
            ");

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
                    SUM(amount) as total_inflow,
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
}

