<?php

namespace App\API\Modules\finance;

use App\Database\Database;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Expense Management Class
 * 
 * Handles all expense-related operations:
 * - Expense recording and categorization
 * - Expense approval workflow integration
 * - Budget tracking (expenses against budget)
 * - Expense reporting and analytics
 * - Vendor/supplier management
 * - Receipt/document management
 */
class ExpenseManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Record a new expense
     * @param array $data Expense data
     * @return array Response with expense_id
     */
    public function recordExpense($data)
    {
        try {
            $required = ['description', 'amount', 'expense_category', 'expense_date'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Validate budget line item if provided
            if (!empty($data['budget_line_item_id'])) {
                $stmt = $this->db->prepare("
                    SELECT bli.allocated_amount,
                           COALESCE(SUM(e.amount), 0) as spent
                    FROM budget_line_items bli
                    LEFT JOIN expenses e ON e.budget_line_item_id = bli.id 
                        AND e.status != 'rejected'
                    WHERE bli.id = ?
                    GROUP BY bli.id
                ");
                $stmt->execute([$data['budget_line_item_id']]);
                $budgetLine = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$budgetLine) {
                    $this->db->rollBack();
                    return formatResponse(false, null, 'Invalid budget line item');
                }

                // Check if expense exceeds budget
                $newTotal = $budgetLine['spent'] + $data['amount'];
                if ($newTotal > $budgetLine['allocated_amount']) {
                    $this->db->rollBack();
                    return formatResponse(false, null, 'Expense exceeds budget allocation');
                }
            }

            // Insert expense record
            $stmt = $this->db->prepare("
                INSERT INTO expenses (
                    description, amount, expense_category, expense_date,
                    budget_line_item_id, department_id, vendor_name,
                    receipt_number, payment_method, notes,
                    recorded_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['description'],
                $data['amount'],
                $data['expense_category'],
                $data['expense_date'],
                $data['budget_line_item_id'] ?? null,
                $data['department_id'] ?? null,
                $data['vendor_name'] ?? null,
                $data['receipt_number'] ?? null,
                $data['payment_method'] ?? 'cash',
                $data['notes'] ?? null,
                $data['recorded_by'] ?? null,
                $data['status'] ?? 'pending'
            ]);

            $expenseId = $this->db->lastInsertId();

            $this->db->commit();

            return formatResponse(true, [
                'expense_id' => $expenseId,
                'message' => 'Expense recorded successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to record expense: ' . $e->getMessage());
        }
    }

    /**
     * Update existing expense
     * @param int $expenseId Expense ID
     * @param array $data Updated data
     * @return array Response
     */
    public function updateExpense($expenseId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if expense exists and is editable
            $stmt = $this->db->prepare("
                SELECT id, status FROM expenses WHERE id = ?
            ");
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$expense) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Expense not found');
            }

            if (in_array($expense['status'], ['approved', 'paid'])) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Cannot update approved or paid expenses');
            }

            // Build update query dynamically
            $allowedFields = [
                'description',
                'amount',
                'expense_category',
                'expense_date',
                'vendor_name',
                'receipt_number',
                'payment_method',
                'notes'
            ];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                $this->db->rollBack();
                return formatResponse(false, null, 'No valid fields to update');
            }

            $params[] = $expenseId;
            $sql = "UPDATE expenses SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->db->commit();

            return formatResponse(true, ['message' => 'Expense updated successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to update expense: ' . $e->getMessage());
        }
    }

    /**
     * Get expense details
     * @param int $expenseId Expense ID
     * @return array Response with expense data
     */
    public function getExpense($expenseId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT e.*,
                       d.name as department_name,
                       bli.category as budget_category,
                       bli.allocated_amount as budget_allocated,
                       u.username as recorded_by_name,
                       a.username as approved_by_name
                FROM expenses e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN budget_line_items bli ON e.budget_line_item_id = bli.id
                LEFT JOIN users u ON e.recorded_by = u.id
                LEFT JOIN users a ON e.approved_by = a.id
                WHERE e.id = ?
            ");

            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$expense) {
                return formatResponse(false, null, 'Expense not found');
            }

            return formatResponse(true, $expense);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to retrieve expense: ' . $e->getMessage());
        }
    }

    /**
     * List expenses with filters
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Response with expenses list
     */
    public function listExpenses($filters = [], $page = 1, $limit = 20)
    {
        try {
            $offset = ($page - 1) * $limit;

            $sql = "SELECT e.*,
                           d.name as department_name,
                           bli.category as budget_category,
                           u.username as recorded_by_name
                    FROM expenses e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN budget_line_items bli ON e.budget_line_item_id = bli.id
                    LEFT JOIN users u ON e.recorded_by = u.id
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['status'])) {
                $sql .= " AND e.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['expense_category'])) {
                $sql .= " AND e.expense_category = ?";
                $params[] = $filters['expense_category'];
            }

            if (!empty($filters['department_id'])) {
                $sql .= " AND e.department_id = ?";
                $params[] = $filters['department_id'];
            }

            if (!empty($filters['date_from'])) {
                $sql .= " AND e.expense_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND e.expense_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (e.description LIKE ? OR e.vendor_name LIKE ? OR e.receipt_number LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }

            $sql .= " ORDER BY e.expense_date DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM expenses e WHERE 1=1";
            $countParams = array_slice($params, 0, -2);

            if (!empty($filters['status']))
                $countSql .= " AND e.status = ?";
            if (!empty($filters['expense_category']))
                $countSql .= " AND e.expense_category = ?";
            if (!empty($filters['department_id']))
                $countSql .= " AND e.department_id = ?";
            if (!empty($filters['date_from']))
                $countSql .= " AND e.expense_date >= ?";
            if (!empty($filters['date_to']))
                $countSql .= " AND e.expense_date <= ?";
            if (!empty($filters['search']))
                $countSql .= " AND (e.description LIKE ? OR e.vendor_name LIKE ? OR e.receipt_number LIKE ?)";

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return formatResponse(true, [
                'expenses' => $expenses,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to list expenses: ' . $e->getMessage());
        }
    }

    /**
     * Approve expense
     * @param int $expenseId Expense ID
     * @param int $approvedBy User ID
     * @param string $notes Approval notes
     * @return array Response
     */
    public function approveExpense($expenseId, $approvedBy, $notes = null)
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE expenses 
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ? AND status = 'pending'
            ");

            $stmt->execute([$approvedBy, $notes, $expenseId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Expense not found or not in pending status');
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Expense approved successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to approve expense: ' . $e->getMessage());
        }
    }

    /**
     * Reject expense
     * @param int $expenseId Expense ID
     * @param int $rejectedBy User ID
     * @param string $reason Rejection reason
     * @return array Response
     */
    public function rejectExpense($expenseId, $rejectedBy, $reason)
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE expenses 
                SET status = 'rejected',
                    approved_by = ?,
                    approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ? AND status = 'pending'
            ");

            $stmt->execute([$rejectedBy, $reason, $expenseId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Expense not found or not in pending status');
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Expense rejected']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to reject expense: ' . $e->getMessage());
        }
    }

    /**
     * Get expense summary by category
     * @param array $filters Filter criteria
     * @return array Response with summary data
     */
    public function getExpenseSummary($filters = [])
    {
        try {
            $sql = "SELECT 
                        expense_category,
                        COUNT(*) as transaction_count,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
                    FROM expenses
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['date_from'])) {
                $sql .= " AND expense_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND expense_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['department_id'])) {
                $sql .= " AND department_id = ?";
                $params[] = $filters['department_id'];
            }

            $sql .= " GROUP BY expense_category ORDER BY total_amount DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get overall totals
            $totalAmount = array_sum(array_column($summary, 'total_amount'));
            $totalCount = array_sum(array_column($summary, 'transaction_count'));

            return formatResponse(true, [
                'by_category' => $summary,
                'overall' => [
                    'total_amount' => $totalAmount,
                    'total_transactions' => $totalCount,
                    'average_transaction' => $totalCount > 0 ? $totalAmount / $totalCount : 0
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to get expense summary: ' . $e->getMessage());
        }
    }

    /**
     * Delete expense
     * @param int $expenseId Expense ID
     * @return array Response
     */
    public function deleteExpense($expenseId)
    {
        try {
            // Check if expense can be deleted (only pending expenses)
            $stmt = $this->db->prepare("SELECT status FROM expenses WHERE id = ?");
            $stmt->execute([$expenseId]);
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$expense) {
                return formatResponse(false, null, 'Expense not found');
            }

            if (!in_array($expense['status'], ['pending', 'rejected'])) {
                return formatResponse(false, null, 'Only pending or rejected expenses can be deleted');
            }

            $this->db->beginTransaction();

            // Delete expense
            $stmt = $this->db->prepare("DELETE FROM expenses WHERE id = ?");
            $stmt->execute([$expenseId]);

            $this->db->commit();

            return formatResponse(true, ['message' => 'Expense deleted successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to delete expense: ' . $e->getMessage());
        }
    }
}
