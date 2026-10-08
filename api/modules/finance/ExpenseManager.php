<?php

namespace App\API\Modules\finance;

use App\Database\Database;
use App\API\Services\NotificationService;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;
use App\API\Services\ReadReplicaService;

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
     * Map legacy expense status values to the live expenses.status enum.
     */
    private function mapExpenseStatus($status)
    {
        $map = [
            'pending' => 'draft',
            'pending_approval' => 'pending_approval',
            'pending_validation' => 'pending_approval',
            'approved_for_payment' => 'approved',
        ];
        $live = ['draft', 'pending_approval', 'approved', 'paid', 'rejected', 'cancelled'];
        $value = $map[$status] ?? $status;
        return in_array($value, $live, true) ? $value : 'draft';
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

            // Resolve category name to live expense_categories id
            $categoryId = null;
            if (!empty($data['expense_category'])) {
                $catStmt = $this->db->prepare(
                    "SELECT id FROM expense_categories WHERE name = ? AND status = 'active' LIMIT 1"
                );
                $catStmt->execute([$data['expense_category']]);
                $categoryId = $catStmt->fetchColumn() ?: null;
            }

            // Resolve vendor name to live suppliers id
            $vendorId = null;
            if (!empty($data['vendor_name'])) {
                $vendorStmt = $this->db->prepare(
                    "SELECT id FROM " . ReadReplicaService::qualifiedRef("budget_line_items") . " name = ? AND status = 'active' LIMIT 1"
                );
                $vendorStmt->execute([$data['vendor_name']]);
                $vendorId = $vendorStmt->fetchColumn() ?: null;
            }

            // Validate budget line item if provided
            if (!empty($data['budget_line_item_id'])) {
                $stmt = $this->db->prepare("
                    SELECT bli.allocated_amount,
                           COALESCE(SUM(e.amount), 0) as spent
                    FROM " . ReadReplicaService::qualifiedRef("budget_line_items") . "
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
                    description, amount, category_id, expense_date,
                    budget_line_item_id, department_id, vendor_id,
                    receipt_number, payment_method, notes,
                    created_by, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['description'],
                $data['amount'],
                $categoryId,
                $data['expense_date'],
                $data['budget_line_item_id'] ?? null,
                $data['department_id'] ?? null,
                $vendorId,
                $data['receipt_number'] ?? null,
                $data['payment_method'] ?? 'cash',
                $data['notes'] ?? null,
                $data['recorded_by'] ?? null,
                $this->mapExpenseStatus($data['status'] ?? 'draft')
            ]);

            $expenseId = $this->db->lastInsertId();

            // Mirror bank/cheque expenses into bank_transactions so school
            // expenses paid from the bank surface on the bank transactions
            // screen. Petty cash is skipped (it stays an expense-only record).
            $isPettyCash = strtolower((string)($data['expense_category'] ?? '')) === 'petty_cash';
            $expenseMethod = strtolower((string)($data['payment_method'] ?? ''));
            if (!$isPettyCash && in_array($expenseMethod, ['bank', 'bank_transfer', 'cheque'], true)) {
                try {
                    $bt = $this->db->prepare("
                        INSERT INTO bank_transactions
                          (transaction_ref, amount, transaction_date, narration, source_type, status, reconciled, created_at)
                        VALUES (?, ?, ?, ?, 'manual_entry', 'pending', 0, NOW())
                    ");
                    $bt->execute([
                        $data['receipt_number'] ?: ('EXP-' . date('YmdHis') . '-' . $expenseId),
                        $data['amount'],
                        $data['expense_date'] . ' 00:00:00',
                        'Expense: ' . $data['description'],
                    ]);
                } catch (Exception $e) {
                    \App\API\Services\Logger::legacyError("ExpenseManager: could not mirror bank_transaction: " . $e->getMessage());
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'expense_id' => $expenseId,
                'message' => 'Expense recorded successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
                'expense_date',
                'category_id',
                'vendor_id',
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

            // Accept legacy expense_category name by resolving to category_id
            if (isset($data['expense_category'])) {
                $catStmt = $this->db->prepare(
                    "SELECT id FROM expense_categories WHERE name = ? AND status = 'active' LIMIT 1"
                );
                $catStmt->execute([$data['expense_category']]);
                $resolvedCategoryId = $catStmt->fetchColumn() ?: null;
                if ($resolvedCategoryId !== null) {
                    $updates[] = "category_id = ?";
                    $params[] = $resolvedCategoryId;
                }
            }

            // Accept legacy vendor_name by resolving to vendor_id
            if (isset($data['vendor_name'])) {
                $vendorStmt = $this->db->prepare(
                    "SELECT id FROM suppliers WHERE name = ? AND status = 'active' LIMIT 1"
                );
                $vendorStmt->execute([$data['vendor_name']]);
                $resolvedVendorId = $vendorStmt->fetchColumn() ?: null;
                if ($resolvedVendorId !== null) {
                    $updates[] = "vendor_id = ?";
                    $params[] = $resolvedVendorId;
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
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
                       ec.name as budget_category,
                       bli.allocated_amount as budget_allocated,
                       u.username as recorded_by_name,
                       a.username as approved_by_name
                FROM " . ReadReplicaService::qualifiedRef("expenses") . " e
                LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON e.department_id = d.id
                LEFT JOIN " . ReadReplicaService::qualifiedRef("budget_line_items") . " bli ON e.budget_line_item_id = bli.id
                LEFT JOIN expense_categories ec ON ec.id = bli.category_id
                LEFT JOIN users u ON e.created_by = u.id
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
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
                           ec.name as budget_category,
                           u.username as recorded_by_name
                    FROM " . ReadReplicaService::qualifiedRef("expenses") . " e
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("departments") . " d ON e.department_id = d.id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("budget_line_items") . " bli ON e.budget_line_item_id = bli.id
                    LEFT JOIN expense_categories ec ON ec.id = bli.category_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON e.vendor_id = s.id
                    LEFT JOIN users u ON e.created_by = u.id
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['status'])) {
                $sql .= " AND e.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['expense_category'])) {
                $sql .= " AND ec.name = ?";
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
                $sql .= " AND (e.description LIKE ? OR s.name LIKE ? OR e.receipt_number LIKE ?)";
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
            $countSql = "SELECT COUNT(*) as total FROM " . ReadReplicaService::qualifiedRef("expenses") . " e
                         LEFT JOIN " . ReadReplicaService::qualifiedRef("budget_line_items") . " bli ON e.budget_line_item_id = bli.id
                         LEFT JOIN expense_categories ec ON ec.id = bli.category_id
                         LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON e.vendor_id = s.id
                         WHERE 1=1";

            $countParams = array_slice($params, 0, -2);

            if (!empty($filters['status']))
                $countSql .= " AND e.status = ?";
            if (!empty($filters['expense_category']))
                $countSql .= " AND ec.name = ?";
            if (!empty($filters['department_id']))
                $countSql .= " AND e.department_id = ?";
            if (!empty($filters['date_from']))
                $countSql .= " AND e.expense_date >= ?";
            if (!empty($filters['date_to']))
                $countSql .= " AND e.expense_date <= ?";
            if (!empty($filters['search']))
                $countSql .= " AND (e.description LIKE ? OR s.name LIKE ? OR e.receipt_number LIKE ?)";

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
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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

            $expense = $this->db->prepare("SELECT id, description, created_by FROM expenses WHERE id = ?");
            $expense->execute([$expenseId]);
            $expenseRow = $expense->fetch(PDO::FETCH_ASSOC);

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

            $this->notifyExpenseStatus($expenseRow, true, (int) $approvedBy);

            return formatResponse(true, ['message' => 'Expense approved successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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

            $expense = $this->db->prepare("SELECT id, description, created_by FROM expenses WHERE id = ?");
            $expense->execute([$expenseId]);
            $expenseRow = $expense->fetch(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare("
                UPDATE expenses
                SET status = 'rejected',
                    rejected_by = ?,
                    rejected_at = NOW(),
                    rejection_reason = ?
                WHERE id = ? AND status = 'pending'
            ");

            $stmt->execute([$rejectedBy, $reason, $expenseId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Expense not found or not in pending status');
            }

            $this->db->commit();

            $this->notifyExpenseStatus($expenseRow, false, (int) $rejectedBy, (string) $reason);

            return formatResponse(true, ['message' => 'Expense rejected']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Push a user-facing notification about an expense decision to its
     * requester (expenses.created_by is a user id).
     */
    private function notifyExpenseStatus($expenseRow, bool $approved, int $actorUserId, string $reason = '')
    {
        if (!$expenseRow || empty($expenseRow['created_by'])) {
            return;
        }
        try {
            $service = new NotificationService($this->db);
            $requester = (int) $expenseRow['created_by'];
            $actor = $service->userName($actorUserId) ?: 'the approver';

            $label = 'expense request';
            if (!empty($expenseRow['description'])) {
                $label .= ' for ' . mb_substr((string) $expenseRow['description'], 0, 60);
            }

            $title = $approved ? 'Expense approved' : 'Expense declined';
            $message = $approved
                ? NotificationService::approvedText($label, $actor)
                : NotificationService::deniedText($label, $actor, $reason);

            $service->push([$requester], 'expense', $title, $message, 'medium');
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ExpenseManager] Notification push failed: ' . $e->getMessage());
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
                        COALESCE(ec.name, 'Uncategorized') as expense_category,
                        COUNT(*) as transaction_count,
                        SUM(amount) as total_amount,
                        AVG(amount) as average_amount,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
                    FROM " . ReadReplicaService::qualifiedRef("expenses") . "
                    LEFT JOIN expense_categories ec ON ec.id = e.category_id
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['date_from'])) {
                $sql .= " AND e.expense_date >= ?";
                $params[] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $sql .= " AND e.expense_date <= ?";
                $params[] = $filters['date_to'];
            }

            if (!empty($filters['department_id'])) {
                $sql .= " AND e.department_id = ?";
                $params[] = $filters['department_id'];
            }

            $sql .= " GROUP BY ec.id, ec.name ORDER BY total_amount DESC";

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
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
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
            // If table doesn't exist, treat as not found
            if (
                strpos($e->getMessage(), "doesn't exist") !== false ||
                strpos($e->getMessage(), "Base table or view not found") !== false
            ) {
                return formatResponse(false, null, 'Expense not found');
            }
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * Fetch a single expense joined with category and user names.
     */
    public function getExpenseDetailed($expenseId)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT e.*, ec.name AS category_name, ec.type AS category_type,
                        COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS recorded_by_name,
                        COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), a.username) AS approved_by_name,
                        s.name AS vendor_name
                 FROM " . ReadReplicaService::qualifiedRef("expenses") . " e
                 LEFT JOIN expense_categories ec ON ec.id = e.category_id
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON e.vendor_id = s.id
                 LEFT JOIN users u ON u.id = e.created_by
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " up ON up.id = u.person_id
                 LEFT JOIN users a ON a.id = e.approved_by
                 LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " ap ON ap.id = a.person_id
                 WHERE e.id = ? AND e.deleted_at IS NULL"
            );
            $stmt->execute([$expenseId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return formatResponse(true, $row ?: null);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }

    /**
     * List expenses with filters plus aggregate stats.
     */
    public function listExpensesWithStats($filters = [])
    {
        try {
            $where = ['e.deleted_at IS NULL'];
            $params = [];

            if (!empty($filters['status']))       { $where[] = 'e.status = ?';          $params[] = $filters['status']; }
            if (!empty($filters['category_id']))  { $where[] = 'e.category_id = ?';     $params[] = $filters['category_id']; }
            if (!empty($filters['department_id'])){ $where[] = 'e.department_id = ?';   $params[] = $filters['department_id']; }
            if (!empty($filters['date_from']))    { $where[] = 'e.expense_date >= ?';   $params[] = $filters['date_from']; }
            if (!empty($filters['date_to']))      { $where[] = 'e.expense_date <= ?';   $params[] = $filters['date_to']; }
            if (!empty($filters['academic_year'])){ $where[] = 'e.academic_year = ?';   $params[] = $filters['academic_year']; }
            if (!empty($filters['search'])) {
                $where[] = '(e.description LIKE ? OR s.name LIKE ? OR e.expense_number LIKE ?)';
                $s = '%' . $filters['search'] . '%';
                array_push($params, $s, $s, $s);
            }

            $sql = "SELECT e.*, ec.name AS category_name, ec.type AS category_type,
                           COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.username) AS recorded_by_name,
                           COALESCE(CONCAT(ap.first_name, ' ', ap.last_name), a.username) AS approved_by_name,
                           s.name AS vendor_name
                    FROM " . ReadReplicaService::qualifiedRef("expenses") . " e
                    LEFT JOIN expense_categories ec ON ec.id = e.category_id
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("suppliers") . " s ON e.vendor_id = s.id
                    LEFT JOIN users u ON u.id = e.created_by
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " up ON up.id = u.person_id
                    LEFT JOIN users a ON a.id = e.approved_by
                    LEFT JOIN " . ReadReplicaService::qualifiedRef("persons") . " ap ON ap.id = a.person_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY e.expense_date DESC LIMIT 200";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->query(
                "SELECT COUNT(*) AS total_count, COALESCE(SUM(amount),0) AS total_amount,
                        COALESCE(SUM(CASE WHEN status='pending_approval' THEN amount END),0) AS pending_amount,
                        COALESCE(SUM(CASE WHEN status='approved' THEN amount END),0) AS approved_amount,
                        COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) AS paid_amount,
                        COALESCE(SUM(CASE WHEN MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE()) THEN amount END),0) AS this_month
                 FROM expenses WHERE deleted_at IS NULL"
            );
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return formatResponse(true, ['expenses' => $rows, 'stats' => $stats]);
        } catch (Exception $e) {
            \App\API\Services\Logger::legacyError('[ExpenseManager] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return formatResponse(false, null, 'An internal error occurred.');
        }
    }
}
