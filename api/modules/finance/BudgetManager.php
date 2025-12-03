<?php

namespace App\API\Modules\finance;

use App\Database\Database;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * Budget Management Class
 * 
 * Handles all budget-related operations:
 * - Budget creation and planning
 * - Budget allocation by department/category
 * - Budget tracking and monitoring
 * - Variance analysis (budget vs actual)
 * - Budget amendments and revisions
 * - Multi-year budget planning
 */
class BudgetManager
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Create a new budget
     * @param array $data Budget data
     * @return array Response with budget_id
     */
    public function createBudget($data)
    {
        try {
            $required = ['name', 'fiscal_year', 'total_amount'];
            $missing = array_diff($required, array_keys($data));

            if (!empty($missing)) {
                return formatResponse(false, null, 'Missing required fields: ' . implode(', ', $missing));
            }

            $this->db->beginTransaction();

            // Insert main budget record
            $stmt = $this->db->prepare("
                INSERT INTO budgets (
                    name, description, fiscal_year, start_date, end_date,
                    total_amount, status, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['fiscal_year'],
                $data['start_date'] ?? ($data['fiscal_year'] . '-01-01'),
                $data['end_date'] ?? ($data['fiscal_year'] . '-12-31'),
                $data['total_amount'],
                $data['status'] ?? 'draft',
                $data['created_by'] ?? null
            ]);

            $budgetId = $this->db->lastInsertId();

            // Insert budget line items if provided
            if (!empty($data['line_items'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO budget_line_items (
                        budget_id, category, department_id, 
                        description, allocated_amount
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($data['line_items'] as $item) {
                    $stmt->execute([
                        $budgetId,
                        $item['category'],
                        $item['department_id'] ?? null,
                        $item['description'] ?? null,
                        $item['allocated_amount']
                    ]);
                }
            }

            $this->db->commit();

            return formatResponse(true, [
                'budget_id' => $budgetId,
                'message' => 'Budget created successfully'
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to create budget: ' . $e->getMessage());
        }
    }

    /**
     * Update existing budget
     * @param int $budgetId Budget ID
     * @param array $data Updated data
     * @return array Response
     */
    public function updateBudget($budgetId, $data)
    {
        try {
            $this->db->beginTransaction();

            // Check if budget exists and is editable
            $stmt = $this->db->prepare("
                SELECT id, status FROM budgets WHERE id = ?
            ");
            $stmt->execute([$budgetId]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Budget not found');
            }

            if ($budget['status'] === 'approved' && !isset($data['amendment_reason'])) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Approved budgets require an amendment reason');
            }

            // Build update query dynamically
            $allowedFields = ['name', 'description', 'total_amount', 'status'];
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

            $params[] = $budgetId;
            $sql = "UPDATE budgets SET " . implode(', ', $updates) . " WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Log amendment if applicable
            if (!empty($data['amendment_reason'])) {
                $stmt = $this->db->prepare("
                    INSERT INTO budget_amendments (
                        budget_id, amendment_reason, old_amount, 
                        new_amount, amended_by
                    ) VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $budgetId,
                    $data['amendment_reason'],
                    $budget['total_amount'] ?? 0,
                    $data['total_amount'] ?? 0,
                    $data['amended_by'] ?? null
                ]);
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Budget updated successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to update budget: ' . $e->getMessage());
        }
    }

    /**
     * Get budget details with variance analysis
     * @param int $budgetId Budget ID
     * @return array Response with budget data
     */
    public function getBudget($budgetId)
    {
        try {
            // Get main budget details
            $stmt = $this->db->prepare("
                SELECT b.*, u.username as created_by_name
                FROM budgets b
                LEFT JOIN users u ON b.created_by = u.id
                WHERE b.id = ?
            ");

            $stmt->execute([$budgetId]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget) {
                return formatResponse(false, null, 'Budget not found');
            }

            // Get line items with actual spending
            $stmt = $this->db->prepare("
                SELECT bli.*,
                       d.name as department_name,
                       COALESCE(SUM(e.amount), 0) as actual_spent,
                       (bli.allocated_amount - COALESCE(SUM(e.amount), 0)) as variance,
                       CASE 
                           WHEN bli.allocated_amount > 0 THEN 
                               (COALESCE(SUM(e.amount), 0) / bli.allocated_amount * 100)
                           ELSE 0 
                       END as utilization_percentage
                FROM budget_line_items bli
                LEFT JOIN departments d ON bli.department_id = d.id
                LEFT JOIN expenses e ON e.budget_line_item_id = bli.id 
                    AND e.status = 'approved'
                WHERE bli.budget_id = ?
                GROUP BY bli.id
            ");

            $stmt->execute([$budgetId]);
            $budget['line_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate overall budget utilization
            $totalAllocated = array_sum(array_column($budget['line_items'], 'allocated_amount'));
            $totalSpent = array_sum(array_column($budget['line_items'], 'actual_spent'));

            $budget['summary'] = [
                'total_allocated' => $totalAllocated,
                'total_spent' => $totalSpent,
                'total_variance' => $totalAllocated - $totalSpent,
                'utilization_percentage' => $totalAllocated > 0 ? ($totalSpent / $totalAllocated * 100) : 0
            ];

            return formatResponse(true, $budget);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to retrieve budget: ' . $e->getMessage());
        }
    }

    /**
     * List budgets with filters
     * @param array $filters Filter criteria
     * @param int $page Page number
     * @param int $limit Records per page
     * @return array Response with budgets list
     */
    public function listBudgets($filters = [], $page = 1, $limit = 20)
    {
        try {
            $offset = ($page - 1) * $limit;

            $sql = "SELECT b.*,
                           u.username as created_by_name,
                           COUNT(DISTINCT bli.id) as line_item_count
                    FROM budgets b
                    LEFT JOIN users u ON b.created_by = u.id
                    LEFT JOIN budget_line_items bli ON b.id = bli.budget_id
                    WHERE 1=1";

            $params = [];

            if (!empty($filters['fiscal_year'])) {
                $sql .= " AND b.fiscal_year = ?";
                $params[] = $filters['fiscal_year'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND b.status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND (b.name LIKE ? OR b.description LIKE ?)";
                $search = '%' . $filters['search'] . '%';
                $params[] = $search;
                $params[] = $search;
            }

            $sql .= " GROUP BY b.id ORDER BY b.fiscal_year DESC, b.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count
            $countSql = "SELECT COUNT(DISTINCT b.id) as total FROM budgets b WHERE 1=1";
            $countParams = array_slice($params, 0, -2);

            if (!empty($filters['fiscal_year']))
                $countSql .= " AND b.fiscal_year = ?";
            if (!empty($filters['status']))
                $countSql .= " AND b.status = ?";
            if (!empty($filters['search']))
                $countSql .= " AND (b.name LIKE ? OR b.description LIKE ?)";

            $stmt = $this->db->prepare($countSql);
            $stmt->execute($countParams);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            return formatResponse(true, [
                'budgets' => $budgets,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to list budgets: ' . $e->getMessage());
        }
    }

    /**
     * Get budget variance report
     * @param int $budgetId Budget ID
     * @return array Response with variance analysis
     */
    public function getVarianceReport($budgetId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT bli.category,
                       d.name as department_name,
                       SUM(bli.allocated_amount) as allocated,
                       COALESCE(SUM(e.amount), 0) as spent,
                       (SUM(bli.allocated_amount) - COALESCE(SUM(e.amount), 0)) as variance,
                       CASE 
                           WHEN SUM(bli.allocated_amount) > 0 THEN
                               ((COALESCE(SUM(e.amount), 0) / SUM(bli.allocated_amount)) * 100)
                           ELSE 0
                       END as utilization_percentage
                FROM budget_line_items bli
                LEFT JOIN departments d ON bli.department_id = d.id
                LEFT JOIN expenses e ON e.budget_line_item_id = bli.id 
                    AND e.status = 'approved'
                WHERE bli.budget_id = ?
                GROUP BY bli.category, d.name
                ORDER BY variance DESC
            ");

            $stmt->execute([$budgetId]);
            $variances = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return formatResponse(true, ['variances' => $variances]);

        } catch (Exception $e) {
            return formatResponse(false, null, 'Failed to generate variance report: ' . $e->getMessage());
        }
    }

    /**
     * Approve budget
     * @param int $budgetId Budget ID
     * @param int $approvedBy User ID
     * @return array Response
     */
    public function approveBudget($budgetId, $approvedBy)
    {
        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("
                UPDATE budgets 
                SET status = 'approved',
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ? AND status = 'pending'
            ");

            $stmt->execute([$approvedBy, $budgetId]);

            if ($stmt->rowCount() === 0) {
                $this->db->rollBack();
                return formatResponse(false, null, 'Budget not found or not in pending status');
            }

            $this->db->commit();

            return formatResponse(true, ['message' => 'Budget approved successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to approve budget: ' . $e->getMessage());
        }
    }

    /**
     * Delete budget
     * @param int $budgetId Budget ID
     * @return array Response
     */
    public function deleteBudget($budgetId)
    {
        try {
            // Check if budget can be deleted (only draft budgets)
            $stmt = $this->db->prepare("SELECT status FROM budgets WHERE id = ?");
            $stmt->execute([$budgetId]);
            $budget = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$budget) {
                return formatResponse(false, null, 'Budget not found');
            }

            if ($budget['status'] !== 'draft') {
                return formatResponse(false, null, 'Only draft budgets can be deleted');
            }

            $this->db->beginTransaction();

            // Delete budget items first
            $stmt = $this->db->prepare("DELETE FROM budget_items WHERE budget_id = ?");
            $stmt->execute([$budgetId]);

            // Delete budget
            $stmt = $this->db->prepare("DELETE FROM budgets WHERE id = ?");
            $stmt->execute([$budgetId]);

            $this->db->commit();

            return formatResponse(true, ['message' => 'Budget deleted successfully']);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return formatResponse(false, null, 'Failed to delete budget: ' . $e->getMessage());
        }
    }
}
