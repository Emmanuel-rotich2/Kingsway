<?php
namespace App\API\Controllers;

use App\API\Modules\finance\FinanceAPI;
use App\API\Modules\finance\PaymentReconciliationAPI;
use App\API\Modules\finance\ExpenseManager;
use Exception;
use App\Database\Database;

/**
 * FinanceController - REST endpoints for all finance operations
 * Handles fees, payments, payrolls, budgets, expenses, and financial reporting
 * 
 * All methods follow signature: methodName($id = null, $data = [], $segments = [])
 * Router calls with: $controller->methodName($id, $data, $segments)
 */

class FinanceController extends BaseController
{
    private FinanceAPI $api;
    private ExpenseManager $expenseManager;

    public function __construct() {
        parent::__construct();
        $this->api = new FinanceAPI();
        $this->expenseManager = new ExpenseManager();
    }

    public function index()
    {
        return $this->success(['message' => 'Finance API is running']);
    }

    /**
     * Guard: Director role (3) or any finance approval permission required.
     * Returns a forbidden response when access is denied, null when granted.
     */
    private function requireApprovalAccess(string $action = 'perform this approval'): ?array
    {
        if ($this->userHasAny(['finance_approve', 'payroll_approve', 'budget_approve',
                               'fee_structure_approve', 'expense_approve', 'finance.approve'],
                              [3], ['director'])) {
            return null;
        }
        return $this->forbidden("Insufficient permissions to $action");
    }

    // ========================================
    // SECTION X: Department Budget Workflows
    // ========================================

    /**
     * POST /api/finance/department-budgets/propose
     * Department submits a budget proposal
     */
    public function postDepartmentBudgetsPropose($id = null, $data = [], $segments = [])
    {
        $result = $this->api->proposeDepartmentBudget($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/department-budgets/proposals
     * View all department budget proposals (optionally filter by department/status)
     */
    public function getDepartmentBudgetsProposals($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listDepartmentBudgetProposals($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/approve
     * Approve or reject a department budget proposal.
     * Accepts: proposal_id (or budget_id alias), status (default: approved), reviewed_by
     */
    public function postDepartmentBudgetsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve department budgets')) return $denied;
        $proposalId = $data['proposal_id'] ?? $data['budget_id'] ?? null;
        if (!$proposalId) {
            return $this->badRequest('proposal_id (or budget_id) is required');
        }
        $status     = $data['status']      ?? 'approved';
        $reviewedBy = $data['reviewed_by'] ?? $this->getCurrentUserId();
        $result = $this->api->updateDepartmentBudgetProposalStatus($proposalId, $status, $reviewedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/allocate
     * Allocate funds to a department budget
     */
    public function postDepartmentBudgetsAllocate($id = null, $data = [], $segments = [])
    {
        // Expecting: $data['department_id'], $data['amount'], $data['allocated_by']
        $departmentId = $data['department_id'] ?? null;
        $amount = $data['amount'] ?? null;
        $allocatedBy = $data['allocated_by'] ?? $this->getCurrentUserId();
        if (!$departmentId || !$amount) {
            return $this->badRequest('department_id and amount are required');
        }
        $result = $this->api->allocateDepartmentBudget($departmentId, $amount, $allocatedBy);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/department-budgets/request-funds
     * Department requests funds from allocated budget
     */
    public function postDepartmentBudgetsRequestFunds($id = null, $data = [], $segments = [])
    {
        $result = $this->api->requestDepartmentFunds($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/department-budgets/summary
     * Quick summary of department budget utilization
     */
    public function getDepartmentBudgetsSummary($id = null, $data = [], $segments = [])
    {
        $departmentId = $_GET['department_id'] ?? $data['department_id'] ?? $id ?? null;
        // department_id is optional — null returns all departments
        $result = $this->api->getDepartmentBudgetSummary($departmentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/expenses/approve
     */
    public function postExpensesApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve expenses')) return $denied;
        $expenseId = $data['expense_id'] ?? $id ?? null;
        if (!$expenseId) {
            return $this->badRequest('expense_id is required');
        }
        $result = $this->expenseManager->approveExpense(
            $expenseId,
            $this->getCurrentUserId(),
            $data['notes'] ?? $data['comments'] ?? null
        );
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/expenses/reject
     */
    public function postExpensesReject($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('reject expenses')) return $denied;
        $expenseId = $data['expense_id'] ?? $id ?? null;
        if (!$expenseId) {
            return $this->badRequest('expense_id is required');
        }
        if (empty($data['reason'])) {
            return $this->badRequest('reason is required when rejecting an expense');
        }
        $result = $this->expenseManager->rejectExpense($expenseId, $this->getCurrentUserId(), $data['reason']);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 1: Base CRUD Operations
    // ========================================

    /**
     * GET /api/finance - List all finance records
     * GET /api/finance/{id} - Get single finance record
     */
    public function get($id = null, $data = [], $segments = [])
    {
        if ($id !== null && empty($segments)) {
            $result = $this->api->get($id);
            return $this->handleResponse($result);
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedGet($resource, $id, $data, $segments);
        }
        
        $result = $this->api->list($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance - Create new finance record
     */
    public function post($id = null, $data = [], $segments = [])
    {
        if ($id !== null) {
            $data['id'] = $id;
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPost($resource, $id, $data, $segments);
        }
        
        $result = $this->api->create($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/{id} - Update finance record
     */
    public function put($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Finance record ID is required for update');
        }
        
        if (!empty($segments)) {
            $resource = array_shift($segments);
            return $this->routeNestedPut($resource, $id, $data, $segments);
        }
        
        $result = $this->api->update($id, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/{id} - Delete finance record
     */
    public function delete($id = null, $data = [], $segments = [])
    {
        if ($id === null) {
            return $this->badRequest('Finance record ID is required for deletion');
        }
        
        $result = $this->api->delete($id);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2: Payroll Operations
    // ========================================

    /**
     * GET /api/finance/payrolls — alias for list
     */
    public function getPayrolls($id = null, $data = [], $segments = [])
    {
        return $this->getPayrollsList($id, $data, $segments);
    }

    /**
     * GET /api/finance/payrolls/list
     */
    public function getPayrollsList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listPayrolls($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/get
     */
    public function getPayrollsGet($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['id'])) {
            $id = $data['id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->getPayroll($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/staff-payments
     */
    public function getPayrollsStaffPayments($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->listStaffPayments($id);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/create-draft
     */
    public function postPayrollsCreateDraft($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createPayrollDraft($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/calculate
     */
    public function postPayrollsCalculate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->calculatePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/recalculate
     */
    public function postPayrollsRecalculate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->recalculatePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/verify
     */
    public function postPayrollsVerify($id = null, $data = [], $segments = [])
    {
        $result = $this->api->verifyPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/approve
     */
    public function postPayrollsApprove($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve payroll')) return $denied;
        $data['user_id'] = $data['user_id'] ?? $this->getCurrentUserId();
        $result = $this->api->approvePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/reject
     */
    public function postPayrollsReject($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('reject payroll')) return $denied;
        $data['user_id'] = $data['user_id'] ?? $this->getCurrentUserId();
        $result = $this->api->rejectPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/process
     */
    public function postPayrollsProcess($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processPayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/disburse
     */
    public function postPayrollsDisburse($id = null, $data = [], $segments = [])
    {
        $result = $this->api->disbursePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/{id}/cancel
     */
    public function postPayrollsCancel($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->cancelPayroll($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/{id}/status
     */
    public function getPayrollsStatus($id = null, $data = [], $segments = [])
    {
        if ($id === null && isset($data['payroll_id'])) {
            $id = $data['payroll_id'];
        }
        
        if ($id === null) {
            return $this->badRequest('Payroll ID is required');
        }
        
        $result = $this->api->getPayrollStatus($id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/staff-payments/get
     */
    public function getPayrollsStaffPaymentsGet($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStaffPayments($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/summary
     */
    public function getPayrollsSummary($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPayrollSummary($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payrolls/history
     */
    public function getPayrollsHistory($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPayrollHistory($data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2B: Enhanced Payroll with Children Fees
    // ========================================

    /**
     * GET /api/finance/staff-for-payroll
     * Get list of staff available for payroll processing with children count
     */
    public function getStaffForPayroll($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getStaffForPayroll();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/staff-payroll-details?staff_id=X
     * Get detailed staff info including children and fee balances
     */
    public function getStaffPayrollDetails($id = null, $data = [], $segments = [])
    {
        $staffId = $_GET['staff_id'] ?? $data['staff_id'] ?? $id ?? null;

        if (!$staffId) {
            return $this->badRequest('Staff ID is required');
        }

        $result = $this->api->getStaffPayrollDetails($staffId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/process-payroll-with-deductions
     * Process payroll including children school fee deductions
     */
    public function postProcessPayrollWithDeductions($id = null, $data = [], $segments = [])
    {
        $result = $this->api->processPayrollWithDeductions($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/detailed-payslip?payroll_id=X
     * Get detailed payslip with children fee deductions breakdown
     */
    public function getDetailedPayslip($id = null, $data = [], $segments = [])
    {
        $payrollId = $_GET['payroll_id'] ?? $data['payroll_id'] ?? $id ?? null;

        if (!$payrollId) {
            return $this->badRequest('Payroll ID is required');
        }

        $result = $this->api->getDetailedPayslip($payrollId);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payroll-stats?month=X&year=Y
     * Get payroll statistics for dashboard
     */
    public function getPayrollStats($id = null, $data = [], $segments = [])
    {
        $month = $_GET['month'] ?? $data['month'] ?? date('n');
        $year = $_GET['year'] ?? $data['year'] ?? date('Y');

        $result = $this->api->getPayrollStats($month, $year);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/payroll-list
     * Get filtered payroll records
     */
    public function getPayrollList($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $result = $this->api->getPayrollList($filters);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/mark-payroll-paid
     * Mark payroll as paid and record children fee payments
     */
    public function postMarkPayrollPaid($id = null, $data = [], $segments = [])
    {
        $payrollId = $data['payroll_id'] ?? $id ?? null;

        if (!$payrollId) {
            return $this->badRequest('Payroll ID is required');
        }

        $paymentRef = $data['payment_reference'] ?? '';
        $result = $this->api->markPayrollPaid($payrollId, $paymentRef);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 2C: Fee Invoice Generation
    // ========================================

    /**
     * POST /api/finance/fee-invoices/generate
     */
    public function postFeeInvoicesGenerate($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateFeeInvoice($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-invoices/generate-batch
     */
    public function postFeeInvoicesGenerateBatch($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generateFeeInvoicesBatch($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-invoices/get?student_id=X
     */
    public function getFeeInvoicesGet($id = null, $data = [], $segments = [])
    {
        $params = array_merge($_GET, $data);
        $result = $this->api->getFeeInvoice($params);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 3: Payment & Receipt Operations
    // ========================================

    /**
     * POST /api/finance/payments/generate-receipt
     */
    public function postPaymentsGenerateReceipt($id = null, $data = [], $segments = [])
    {
        $paymentId = $data['payment_id'] ?? $id ?? null;
        
        if ($paymentId === null) {
            return $this->badRequest('Payment ID is required');
        }
        
        $result = $this->api->generateReceipt($paymentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payments/generate-payslip
     */
    public function postPaymentsGeneratePayslip($id = null, $data = [], $segments = [])
    {
        $staffPaymentId = $data['staff_payment_id'] ?? $id ?? null;
        
        if ($staffPaymentId === null) {
            return $this->badRequest('Staff payment ID is required');
        }
        
        $result = $this->api->generatePayslip($staffPaymentId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payments/send-notification
     */
    public function postPaymentsSendNotification($id = null, $data = [], $segments = [])
    {
        $paymentId = $data['payment_id'] ?? null;
        $recipient = $data['recipient'] ?? null;
        $method = $data['method'] ?? 'email';

        if ($paymentId === null || $recipient === null) {
            return $this->badRequest('Payment ID and recipient are required');
        }

        $result = $this->api->sendPaymentNotification($paymentId, $recipient, $method);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 4: Fee Structure Operations
    // ========================================

    /**
     * GET /api/finance/fee-types-list
     */
    public function getFeeTypesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listFeeTypes();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/student-types-list
     */
    public function getStudentTypesList($id = null, $data = [], $segments = [])
    {
        $result = $this->api->listStudentTypes();
        return $this->handleResponse($result);
    }

    /**
        
     * POST /api/finance/fees/create-annual-structure
     */
    public function postFeesCreateAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/review-structure
     */
    public function postFeesReviewStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->reviewFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/approve-structure
     */
    public function postFeesApproveStructure($id = null, $data = [], $segments = [])
    {
        if ($denied = $this->requireApprovalAccess('approve fee structures')) return $denied;
        $data['approved_by'] = $data['approved_by'] ?? $this->getCurrentUserId();
        $result = $this->api->approveFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/activate-structure
     */
    public function postFeesActivateStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->activateFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/rollover-structure
     */
    public function postFeesRolloverStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->rolloverFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/update-annual-structure
     */
    public function postFeesUpdateAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->updateAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees/delete-annual-structure
     */
    public function postFeesDeleteAnnualStructure($id = null, $data = [], $segments = [])
    {
        $result = $this->api->deleteAnnualFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/term-breakdown
     */
    public function getFeesTermBreakdown($id = null, $data = [], $segments = [])
    {
        $academicYear = $_GET['academic_year_id'] ?? $data['academic_year_id'] ?? null;
        $term = $_GET['term'] ?? $data['term'] ?? null;

        if ($academicYear === null || $term === null) {
            return $this->badRequest('Academic year ID and term are required');
        }

        $result = $this->api->getTermBreakdown($academicYear, $term);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/pending-reviews
     */
    public function getFeesPendingReviews($id = null, $data = [], $segments = [])
    {
        $result = $this->api->getPendingReviews();
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees/annual-summary
     */
    public function getFeesAnnualSummary($id = null, $data = [], $segments = [])
    {
        $academicYear = $_GET['academic_year_id'] ?? $data['academic_year_id'] ?? null;
        
        if ($academicYear === null) {
            return $this->badRequest('Academic year ID is required');
        }

        $result = $this->api->getAnnualFeeSummary($academicYear);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-structures/list
     * Get fee structures with permission-aware filtering
     */
    public function getFeeStructuresList($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET, $data);
        $page = $filters['page'] ?? 1;
        $limit = $filters['limit'] ?? 20;

        $result = $this->api->listFeeStructures($filters, $page, $limit);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fee-structures/{id}
     * Get a specific fee structure with details
     */
    public function getFeeStructuresGet($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->getFeeStructure($structureId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-structures
     * Create a new fee structure
     */
    public function postFeesStructures($id = null, $data = [], $segments = [])
    {
        $result = $this->api->createFeeStructure($data);
        return $this->handleResponse($result);
    }

    /**
     * PUT /api/finance/fee-structures/{id}
     * Update a fee structure
     */
    public function putFeeStructures($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->updateFeeStructure($structureId, $data);
        return $this->handleResponse($result);
    }

    /**
     * DELETE /api/finance/fee-structures/{id}
     * Delete a fee structure
     */
    public function deleteFeeStructures($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->deleteFeeStructure($structureId);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fee-structures/{id}/duplicate
     * Duplicate a fee structure for a new academic year
     */
    public function postFeeStructuresDuplicate($id = null, $data = [], $segments = [])
    {
        $structureId = $id ?? $data['id'] ?? null;

        if ($structureId === null) {
            return $this->badRequest('Fee structure ID is required');
        }

        $result = $this->api->duplicateFeeStructure($structureId, $data);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 5: Student Payment History & Fee Statement
    // ========================================

    /**
     * GET /api/finance/students/payment-history
     */
    public function getStudentsPaymentHistory($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? $id ?? null;
        $academicYear = $data['academic_year'] ?? null;
        
        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }
        
        $result = $this->api->getStudentPaymentHistory($studentId, $academicYear);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/payment-status
     * List student payment status with filters
     */
    public function getStudentsPaymentStatus($id = null, $data = [], $segments = [])
    {
        $filters = array_merge($_GET ?? [], $data ?? []);

        if ($id !== null) {
            $filters['student_id'] = $id;
        }

        $result = $this->api->listStudentPaymentStatus($filters);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/fee-statement/{id}
     * Get complete fee statement for a student
     */
    public function getStudentsFeeStatement($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;
        $academicYear = $data['academic_year'] ?? $_GET['academic_year'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        // If no academic year provided, get current one
        if ($academicYear === null) {
            $academicYear = $this->getCurrentAcademicYear();
        }

        $result = $this->api->handleCustomGet($studentId, 'statement', ['academic_year' => $academicYear]);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/students/balance/{id}
     * Get current fee balance for a student
     */
    public function getStudentsBalance($id = null, $data = [], $segments = [])
    {
        $studentId = $id ?? $data['student_id'] ?? null;

        if ($studentId === null) {
            return $this->badRequest('Student ID is required');
        }

        $result = $this->api->handleCustomGet($studentId, 'balance', []);
        return $this->handleResponse($result);
    }

    /**
     * Helper: Get current academic year
     */
    private function getCurrentAcademicYear()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT year_code FROM academic_years WHERE is_current = 1 LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['year_code'] ?? date('Y');
        } catch (\Exception $e) {
            return date('Y');
        }
    }

    // ========================================
    // SECTION 6: Reporting Operations
    // ========================================

    /**
     * GET /api/finance/reports — summary of available reports + recent totals
     */
    public function getReports($id = null, $data = [], $segments = [])
    {
        try {
            $db = $this->db ?? \App\Database\Database::getInstance();
            // Return basic financial summary for the reports page
            $stmt = $db->query(
                "SELECT
                    (SELECT COALESCE(SUM(amount),0) FROM fee_payments WHERE YEAR(payment_date)=YEAR(CURDATE())) AS total_collected_ytd,
                    (SELECT COALESCE(SUM(total_fees - paid_amount),0) FROM student_fees WHERE academic_year_id=(SELECT id FROM academic_years WHERE is_current=1 LIMIT 1)) AS total_outstanding,
                    (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE YEAR(expense_date)=YEAR(CURDATE()) AND status='approved') AS total_expenses_ytd,
                    (SELECT COUNT(*) FROM fee_payments WHERE DATE(payment_date)=CURDATE()) AS payments_today"
            );
            $summary = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            return $this->success(['summary' => $summary, 'report_types' => [
                'collections', 'fee_defaulters', 'expenses', 'payroll', 'balance_sheet'
            ]]);
        } catch (\Exception $e) {
            return $this->success(['summary' => [], 'report_types' => [
                'collections', 'fee_defaulters', 'expenses', 'payroll', 'balance_sheet'
            ]]);
        }
    }

    /**
     * POST /api/finance/reports/generate-payroll
     */
    public function postReportsGeneratePayroll($id = null, $data = [], $segments = [])
    {
        $result = $this->api->generatePayrollReport($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/reports/compare-yearly-collections
     */
    public function getReportsCompareYearlyCollections($id = null, $data = [], $segments = [])
    {
        $year1 = $data['year1'] ?? null;
        $year2 = $data['year2'] ?? null;
        
        if ($year1 === null || $year2 === null) {
            return $this->badRequest('Both years are required for comparison');
        }
        
        $result = $this->api->compareYearlyCollections($year1, $year2);
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 7: Helper Methods
    // ========================================

    /**
     * GET /api/finance/reconciliation/unreconciled
     * Wrapper to list unreconciled transactions for accountant dashboard
     */
    public function getReconciliationUnreconciled($id = null, $data = [], $segments = [])
    {
        // Require authentication + permission
        $user = $_SERVER['auth_user'] ?? null;
        if (!$user)
            return $this->unauthorized('Authentication required');
        if (
            !$this->userHasAny(
                ['finance.reconcile', 'finance_reconcile', 'finance.view', 'finance_view'],
                [10],
                ['accountant', 'finance', 'admin']
            )
        ) {
            return $this->forbidden('Insufficient permissions');
        }

        try {
            $recon = new PaymentReconciliationAPI();
            $result = $recon->listUnreconciled($data);
            return $this->handleResponse($result);
        } catch (Exception $e) {
            return $this->error('Failed to fetch unreconciled transactions: ' . $e->getMessage());
        }
    }

    /**
     * Route nested POST requests to appropriate methods
     */
    private function routeNestedPost($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'post' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested GET requests to appropriate methods
     */
    private function routeNestedGet($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'get' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Route nested PUT requests to appropriate methods
     */
    private function routeNestedPut($resource, $id, $data, $segments)
    {
        $action = !empty($segments) ? $this->toCamelCase(implode('-', $segments)) : null;
        $methodName = 'put' . ucfirst($this->toCamelCase($resource));
        if ($action) {
            $methodName .= ucfirst($action);
        }

        if (method_exists($this, $methodName)) {
            if ($id !== null) {
                $data['id'] = $id;
            }
            return $this->$methodName($id, $data, []);
        }

        return $this->notFound("Method '{$methodName}' not found");
    }

    /**
     * Convert kebab-case to camelCase
     */
    private function toCamelCase($string)
    {
        return lcfirst(str_replace('-', '', ucwords($string, '-')));
    }

    /**
     * Handle API response and format appropriately
     */
    private function handleResponse($result)
    {
        if (is_array($result)) {
            // Check if result is from formatResponse (has 'code' and 'status' keys)
            if (isset($result['code']) && isset($result['status'])) {
                $code = $result['code'];
                $message = $result['message'] ?? 'Operation completed';
                $data = $result['data'] ?? null;

                // Route based on HTTP status code
                if ($code >= 200 && $code < 300) {
                    return $this->success($data, $message);
                } elseif ($code === 404) {
                    return $this->notFound($message);
                } elseif ($code === 401) {
                    return $this->unauthorized($message);
                } elseif ($code === 403) {
                    return $this->forbidden($message);
                } elseif ($code >= 500) {
                    return $this->serverError($message);
                } else {
                    return $this->badRequest($message);
                }
            }

            // Legacy format with 'success' key
            if (isset($result['success'])) {
                if ($result['success']) {
                    return $this->success($result['data'] ?? null, $result['message'] ?? 'Success');
                } else {
                    $message = $result['error'] ?? $result['message'] ?? 'Operation failed';
                    if (stripos($message, 'not found') !== false) {
                        return $this->notFound($message);
                    }
                    return $this->badRequest($message);
                }
            }
            
            return $this->success($result);
        }

        return $this->success($result);
    }

    /**
     * Get current authenticated user ID
     */
    private function getCurrentUserId()
    {
        return $this->user['id'] ?? null;
    }

    // ========================================
    // SECTION 8: Fee Bundle Workflow
    // ========================================

    /**
     * POST /api/finance/fees-bundle-submit
     * Accountant submits a fee structure bundle for director review
     */
    public function postFeesBundleSubmit($id = null, $data = [], $segments = [])
    {
        if (empty($data['level_id']) || empty($data['academic_year']) || empty($data['term_id']) || empty($data['student_type_id'])) {
            return $this->badRequest('level_id, academic_year, term_id, student_type_id are required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['submitted_by'] = $userId;
        $result = $this->api->submitFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-bundle-review/{id}
     * Finance manager reviews a submitted bundle
     */
    public function postFeesBundleReview($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('approval_id required');
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['approval_id'] = $id;
        $data['reviewed_by'] = $userId;
        if (empty($data['action'])) return $this->badRequest('action (approve|reject) required');
        $result = $this->api->reviewFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-bundle-approve/{id}
     * Director approves or rejects a fee structure bundle.
     * On approval, automatically generates student_fee_obligations for all affected students.
     */
    public function postFeesBundleApprove($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('approval_id required');
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $data['approval_id'] = $id;
        $data['approved_by'] = $userId;
        if (empty($data['action'])) return $this->badRequest('action (approve|reject) required');
        $result = $this->api->approveFeeStructureBundle($data);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/fees-bundle-list
     * List all fee structure bundles with status, for director review queue
     * Query params: status, academic_year, term_id, level_id, page, limit
     */
    public function getFeesBundleList($id = null, $data = [], $segments = [])
    {
        $filters = [
            'status'        => $data['status'] ?? $_GET['status'] ?? null,
            'academic_year' => $data['academic_year'] ?? $_GET['academic_year'] ?? null,
            'term_id'       => $data['term_id'] ?? $_GET['term_id'] ?? null,
            'level_id'      => $data['level_id'] ?? $_GET['level_id'] ?? null,
            'page'          => (int)($data['page'] ?? $_GET['page'] ?? 1),
            'limit'         => (int)($data['limit'] ?? $_GET['limit'] ?? 20),
        ];
        $result = $this->api->getFeeStructureBundles($filters);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/fees-activate-generate-obligations
     * Manually trigger obligation generation for an approved bundle
     */
    public function postFeesActivateGenerateObligations($id = null, $data = [], $segments = [])
    {
        if (empty($data['level_id']) || empty($data['academic_year']) || empty($data['term_id']) || empty($data['student_type_id'])) {
            return $this->badRequest('level_id, academic_year, term_id, student_type_id are required');
        }
        $userId = $this->user['user_id'] ?? $this->user['id'] ?? null;
        $result = $this->api->activateAndGenerateObligations(
            $data['level_id'], $data['academic_year'], $data['term_id'], $data['student_type_id'], $userId
        );
        return $this->handleResponse($result);
    }

    // ========================================
    // SECTION 9: Student Billing History
    // ========================================

    /**
     * GET /api/finance/students-billing-history/{id}
     * Full billing history for a student across all years and terms
     */
    public function getStudentsBillingHistory($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('student_id required');
        $result = $this->api->getStudentBillingHistory((int)$id);
        return $this->handleResponse($result);
    }

    /**
     * GET /api/finance/class-billing-report/{id}
     * Class-level billing report — all students, their balances and payment status
     * Query params: academic_year_id (required), term_id (optional)
     */
    public function getClassBillingReport($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('class_id required');
        $academicYearId = $data['academic_year_id'] ?? $_GET['academic_year_id'] ?? null;
        if (!$academicYearId) return $this->badRequest('academic_year_id required');
        $termId = $data['term_id'] ?? $_GET['term_id'] ?? null;
        $result = $this->api->getClassBillingReport((int)$id, (int)$academicYearId, $termId ? (int)$termId : null);
        return $this->handleResponse($result);
    }
}
