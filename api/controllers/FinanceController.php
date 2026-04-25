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

    // ========================================
    // SECTION 10: Expense Management
    // ========================================

    /** GET /api/finance/expenses — list all expenses with filters */
    public function getExpenses($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $row = $this->db->query(
                "SELECT e.*, ec.name AS category_name, ec.type AS category_type,
                        u.full_name AS recorded_by_name, a.full_name AS approved_by_name
                 FROM expenses e
                 LEFT JOIN expense_categories ec ON ec.id = e.category_id
                 LEFT JOIN users u ON u.id = e.created_by
                 LEFT JOIN users a ON a.id = e.approved_by
                 WHERE e.id = ? AND e.deleted_at IS NULL",
                [$id]
            )->fetch();
            return $row ? $this->success($row) : $this->notFound('Expense not found');
        }
        $where = ['e.deleted_at IS NULL'];
        $params = [];
        if (!empty($data['status']))       { $where[] = 'e.status = ?';            $params[] = $data['status']; }
        if (!empty($data['category_id']))  { $where[] = 'e.category_id = ?';       $params[] = $data['category_id']; }
        if (!empty($data['department_id'])){ $where[] = 'e.department_id = ?';     $params[] = $data['department_id']; }
        if (!empty($data['date_from']))    { $where[] = 'e.expense_date >= ?';      $params[] = $data['date_from']; }
        if (!empty($data['date_to']))      { $where[] = 'e.expense_date <= ?';      $params[] = $data['date_to']; }
        if (!empty($data['academic_year'])){ $where[] = 'e.academic_year = ?';      $params[] = $data['academic_year']; }
        if (!empty($data['search'])) {
            $where[] = '(e.description LIKE ? OR e.vendor_name LIKE ? OR e.expense_number LIKE ?)';
            $s = '%'.$data['search'].'%';
            $params = array_merge($params, [$s, $s, $s]);
        }
        $sql = "SELECT e.*, ec.name AS category_name, ec.type AS category_type,
                       u.full_name AS recorded_by_name, a.full_name AS approved_by_name
                FROM expenses e
                LEFT JOIN expense_categories ec ON ec.id = e.category_id
                LEFT JOIN users u ON u.id = e.created_by
                LEFT JOIN users a ON a.id = e.approved_by
                WHERE " . implode(' AND ', $where) . " ORDER BY e.expense_date DESC LIMIT 200";
        $rows = $this->db->query($sql, $params)->fetchAll();

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total_count, COALESCE(SUM(amount),0) AS total_amount,
                    COALESCE(SUM(CASE WHEN status='pending_approval' THEN amount END),0) AS pending_amount,
                    COALESCE(SUM(CASE WHEN status='approved' THEN amount END),0) AS approved_amount,
                    COALESCE(SUM(CASE WHEN status='paid' THEN amount END),0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN MONTH(expense_date)=MONTH(CURDATE()) AND YEAR(expense_date)=YEAR(CURDATE()) THEN amount END),0) AS this_month
             FROM expenses WHERE deleted_at IS NULL"
        )->fetch();
        return $this->success(['expenses' => $rows, 'stats' => $stats]);
    }

    /** POST /api/finance/expenses — create expense */
    public function postExpenses($id = null, $data = [], $segments = [])
    {
        if (empty($data['description']) || empty($data['amount']) || empty($data['expense_date'])) {
            return $this->badRequest('description, amount, expense_date are required');
        }
        $userId = $this->getCurrentUserId();
        $expNo  = 'EXP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $this->db->query(
            "INSERT INTO expenses (expense_number, category_id, description, amount, expense_date,
                payment_method, reference_number, vendor_id, vendor_name, receipt_number,
                budget_line_item_id, department_id, academic_year, term, notes, attachment_path,
                status, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,NOW())",
            [
                $expNo,
                $data['category_id'] ?? null,
                $data['description'],
                $data['amount'],
                $data['expense_date'],
                $data['payment_method'] ?? 'cash',
                $data['reference_number'] ?? null,
                $data['vendor_id'] ?? null,
                $data['vendor_name'] ?? null,
                $data['receipt_number'] ?? null,
                $data['budget_line_item_id'] ?? null,
                $data['department_id'] ?? null,
                $data['academic_year'] ?? date('Y'),
                $data['term'] ?? null,
                $data['notes'] ?? null,
                $data['attachment_path'] ?? null,
                $userId,
            ]
        );
        $newId = $this->db->lastInsertId();
        return $this->success(['id' => $newId, 'expense_number' => $expNo], 'Expense recorded successfully');
    }

    /** PUT /api/finance/expenses/{id} — update or change status */
    public function putExpenses($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Expense ID required');
        $userId = $this->getCurrentUserId();

        if (isset($data['status'])) {
            if ($data['status'] === 'approved') return $this->postExpensesApprove($id, $data, $segments);
            if ($data['status'] === 'rejected')  return $this->postExpensesReject($id, $data, $segments);
            if ($data['status'] === 'pending_approval') {
                $this->db->query("UPDATE expenses SET status='pending_approval', updated_at=NOW() WHERE id=?", [$id]);
                return $this->success(null, 'Expense submitted for approval');
            }
        }

        $fields = [];
        $params = [];
        $allowed = ['category_id','description','amount','expense_date','payment_method',
                    'reference_number','vendor_id','vendor_name','receipt_number',
                    'budget_line_item_id','department_id','academic_year','term','notes'];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $data)) { $fields[] = "$f=?"; $params[] = $data[$f]; }
        }
        if (empty($fields)) return $this->badRequest('Nothing to update');
        $fields[] = 'updated_at=NOW()';
        $params[]  = $id;
        $this->db->query("UPDATE expenses SET ".implode(',',$fields)." WHERE id=?", $params);
        return $this->success(null, 'Expense updated');
    }

    /** DELETE /api/finance/expenses/{id} — soft delete */
    public function deleteExpenses($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Expense ID required');
        $this->db->query("UPDATE expenses SET deleted_at=NOW() WHERE id=?", [$id]);
        return $this->success(null, 'Expense deleted');
    }

    /** GET /api/finance/expense-categories — list all expense categories */
    public function getExpenseCategories($id = null, $data = [], $segments = [])
    {
        $rows = $this->db->query(
            "SELECT * FROM expense_categories WHERE status='active' ORDER BY type, name"
        )->fetchAll();
        return $this->success($rows);
    }

    // ========================================
    // SECTION 11: Petty Cash
    // ========================================

    /** GET /api/finance/petty-cash — list transactions + fund summary */
    public function getPettyCash($id = null, $data = [], $segments = [])
    {
        $fundId = $data['fund_id'] ?? 1;
        $fund = $this->db->query("SELECT * FROM petty_cash_funds WHERE id=?", [$fundId])->fetch();
        $where = ['fund_id = ?'];
        $params = [$fundId];
        if (!empty($data['type']))      { $where[] = 'type=?';                $params[] = $data['type']; }
        if (!empty($data['date_from'])) { $where[] = 'transaction_date>=?';   $params[] = $data['date_from']; }
        if (!empty($data['date_to']))   { $where[] = 'transaction_date<=?';   $params[] = $data['date_to']; }
        if (!empty($data['category_id'])){ $where[] = 'category_id=?';       $params[] = $data['category_id']; }
        $txns = $this->db->query(
            "SELECT t.*, ec.name AS category_name, u.full_name AS recorded_by_name
             FROM petty_cash_transactions t
             LEFT JOIN expense_categories ec ON ec.id = t.category_id
             LEFT JOIN users u ON u.id = t.recorded_by
             WHERE " . implode(' AND ', $where) . " ORDER BY transaction_date DESC, id DESC LIMIT 200",
            $params
        )->fetchAll();

        $stats = $this->db->query(
            "SELECT COALESCE(SUM(CASE WHEN type='expense' AND MONTH(transaction_date)=MONTH(CURDATE()) THEN amount END),0) AS expenses_this_month,
                    COALESCE(SUM(CASE WHEN type='top_up' AND MONTH(transaction_date)=MONTH(CURDATE()) THEN amount END),0) AS topups_this_month
             FROM petty_cash_transactions WHERE fund_id=?",
            [$fundId]
        )->fetch();

        return $this->success(['fund' => $fund, 'transactions' => $txns, 'stats' => $stats]);
    }

    /** POST /api/finance/petty-cash — record a petty cash transaction */
    public function postPettyCash($id = null, $data = [], $segments = [])
    {
        if (empty($data['type']) || empty($data['amount']) || empty($data['description'])) {
            return $this->badRequest('type, amount, description are required');
        }
        $fundId = $data['fund_id'] ?? 1;
        $userId = $this->getCurrentUserId();
        $fund   = $this->db->query("SELECT current_balance FROM petty_cash_funds WHERE id=?", [$fundId])->fetch();
        if (!$fund) return $this->notFound('Petty cash fund not found');
        $balanceAfter = ($data['type'] === 'expense')
            ? $fund['current_balance'] - $data['amount']
            : $fund['current_balance'] + $data['amount'];
        if ($data['type'] === 'expense' && $balanceAfter < 0) {
            return $this->badRequest('Insufficient petty cash balance');
        }
        $this->db->query(
            "INSERT INTO petty_cash_transactions (fund_id,type,category_id,description,amount,balance_after,
              transaction_date,receipt_number,vendor_name,notes,recorded_by) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $fundId, $data['type'], $data['category_id'] ?? null, $data['description'],
                $data['amount'], $balanceAfter,
                $data['transaction_date'] ?? date('Y-m-d'),
                $data['receipt_number'] ?? null, $data['vendor_name'] ?? null,
                $data['notes'] ?? null, $userId
            ]
        );
        return $this->success(['balance_after' => $balanceAfter], 'Petty cash transaction recorded');
    }

    // ========================================
    // SECTION 12: Cash Reconciliation
    // ========================================

    /** GET /api/finance/cash-reconciliation — list sessions or get one by date */
    public function getCashReconciliation($id = null, $data = [], $segments = [])
    {
        if (!empty($data['date'])) {
            $session = $this->db->query(
                "SELECT s.*, u.full_name AS cashier_name, a.full_name AS approved_by_name
                 FROM cash_reconciliation_sessions s
                 LEFT JOIN users u ON u.id = s.cashier_id
                 LEFT JOIN users a ON a.id = s.approved_by
                 WHERE s.reconciliation_date=?",
                [$data['date']]
            )->fetch();
            return $this->success($session ?: null);
        }
        $rows = $this->db->query(
            "SELECT s.*, u.full_name AS cashier_name
             FROM cash_reconciliation_sessions s
             LEFT JOIN users u ON u.id = s.cashier_id
             ORDER BY s.reconciliation_date DESC LIMIT 60"
        )->fetchAll();
        return $this->success($rows);
    }

    /** POST /api/finance/cash-reconciliation — submit a daily cash count */
    public function postCashReconciliation($id = null, $data = [], $segments = [])
    {
        if (empty($data['reconciliation_date']) || !isset($data['system_cash_total']) || !isset($data['physical_cash_count'])) {
            return $this->badRequest('reconciliation_date, system_cash_total, physical_cash_count are required');
        }
        $userId = $this->getCurrentUserId();
        $existing = $this->db->query(
            "SELECT id FROM cash_reconciliation_sessions WHERE reconciliation_date=? AND cashier_id=?",
            [$data['reconciliation_date'], $userId]
        )->fetch();
        if ($existing) {
            $this->db->query(
                "UPDATE cash_reconciliation_sessions SET physical_cash_count=?, variance_reason=?, notes=?, status='draft' WHERE id=?",
                [$data['physical_cash_count'], $data['variance_reason'] ?? null, $data['notes'] ?? null, $existing['id']]
            );
            return $this->success(['id' => $existing['id']], 'Reconciliation updated');
        }
        $this->db->query(
            "INSERT INTO cash_reconciliation_sessions (reconciliation_date,system_cash_total,physical_cash_count,variance_reason,cashier_id,notes,status)
             VALUES (?,?,?,?,?,'draft')",
            [$data['reconciliation_date'], $data['system_cash_total'], $data['physical_cash_count'],
             $data['variance_reason'] ?? null, $userId, $data['notes'] ?? null]
        );
        return $this->success(['id' => $this->db->lastInsertId()], 'Cash reconciliation submitted');
    }

    // ========================================
    // SECTION 13: Financial Adjustments
    // ========================================

    /** GET /api/finance/adjustments — list all adjustments */
    public function getAdjustments($id = null, $data = [], $segments = [])
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($data['status']))     { $where[] = 'fa.status=?';       $params[] = $data['status']; }
        if (!empty($data['student_id'])) { $where[] = 'fa.student_id=?';   $params[] = $data['student_id']; }
        $rows = $this->db->query(
            "SELECT fa.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    u.full_name AS requested_by_name, a.full_name AS approved_by_name
             FROM financial_adjustments fa
             LEFT JOIN students s ON s.id = fa.student_id
             LEFT JOIN users u ON u.id = fa.requested_by
             LEFT JOIN users a ON a.id = fa.approved_by
             WHERE " . implode(' AND ', $where) . " ORDER BY fa.created_at DESC LIMIT 200",
            $params
        )->fetchAll();

        $stats = $this->db->query(
            "SELECT COUNT(CASE WHEN status='pending' THEN 1 END) AS pending_count,
                    COALESCE(SUM(CASE WHEN status='pending' THEN amount END),0) AS pending_amount,
                    COUNT(CASE WHEN status='approved' AND MONTH(approved_at)=MONTH(CURDATE()) THEN 1 END) AS approved_this_month,
                    COALESCE(SUM(CASE WHEN status='applied' THEN amount END),0) AS total_applied,
                    COUNT(CASE WHEN status='rejected' THEN 1 END) AS rejected_count
             FROM financial_adjustments"
        )->fetch();
        return $this->success(['adjustments' => $rows, 'stats' => $stats]);
    }

    /** POST /api/finance/adjustments — create adjustment */
    public function postAdjustments($id = null, $data = [], $segments = [])
    {
        if (empty($data['type']) || empty($data['amount']) || empty($data['reason'])) {
            return $this->badRequest('type, amount, reason are required');
        }
        $userId = $this->getCurrentUserId();
        $adjNo  = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $this->db->query(
            "INSERT INTO financial_adjustments (adjustment_number,type,student_id,amount,reason,
              reference_payment_id,academic_year,term,notes,status,requested_by,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,'pending',?,NOW())",
            [
                $adjNo, $data['type'], $data['student_id'] ?? null, $data['amount'], $data['reason'],
                $data['reference_payment_id'] ?? null, $data['academic_year'] ?? date('Y'),
                $data['term'] ?? null, $data['notes'] ?? null, $userId
            ]
        );
        return $this->success(['id' => $this->db->lastInsertId(), 'adjustment_number' => $adjNo], 'Adjustment submitted');
    }

    /** PUT /api/finance/adjustments/{id} — approve/reject/apply */
    public function putAdjustments($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Adjustment ID required');
        $userId = $this->getCurrentUserId();
        $status = $data['status'] ?? null;
        if ($status === 'approved') {
            $this->db->query(
                "UPDATE financial_adjustments SET status='approved', approved_by=?, approved_at=NOW() WHERE id=?",
                [$userId, $id]
            );
            return $this->success(null, 'Adjustment approved');
        }
        if ($status === 'rejected') {
            if (empty($data['rejection_reason'])) return $this->badRequest('rejection_reason required');
            $this->db->query(
                "UPDATE financial_adjustments SET status='rejected', rejected_by=?, rejected_at=NOW(), rejection_reason=? WHERE id=?",
                [$userId, $data['rejection_reason'], $id]
            );
            return $this->success(null, 'Adjustment rejected');
        }
        return $this->badRequest('Unknown status: '.$status);
    }

    // ========================================
    // SECTION 14: Exception Reports
    // ========================================

    /** GET /api/finance/exception-reports — list flagged exceptions */
    public function getExceptionReports($id = null, $data = [], $segments = [])
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($data['status']))   { $where[] = 'status=?';   $params[] = $data['status']; }
        if (!empty($data['severity'])) { $where[] = 'severity=?'; $params[] = $data['severity']; }
        $rows = $this->db->query(
            "SELECT fe.*, u.full_name AS resolved_by_name
             FROM finance_exceptions fe
             LEFT JOIN users u ON u.id = fe.resolved_by
             WHERE " . implode(' AND ', $where) . " ORDER BY FIELD(severity,'critical','high','medium','low'), created_at DESC LIMIT 200",
            $params
        )->fetchAll();

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total,
                    COUNT(CASE WHEN status='open' THEN 1 END) AS open_count,
                    COUNT(CASE WHEN severity='critical' AND status='open' THEN 1 END) AS critical_count,
                    COUNT(CASE WHEN severity='high'     AND status='open' THEN 1 END) AS high_count
             FROM finance_exceptions WHERE status != 'dismissed'"
        )->fetch();
        return $this->success(['exceptions' => $rows, 'stats' => $stats]);
    }

    /** PUT /api/finance/exception-reports/{id} — update status */
    public function putExceptionReports($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Exception ID required');
        $userId = $this->getCurrentUserId();
        $this->db->query(
            "UPDATE finance_exceptions SET status=?, resolved_by=?, resolved_at=NOW(), resolution_notes=? WHERE id=?",
            [$data['status'] ?? 'under_review', $userId, $data['resolution_notes'] ?? null, $id]
        );
        return $this->success(null, 'Exception status updated');
    }

    // ========================================
    // SECTION 15: Budgets CRUD
    // ========================================

    /** GET /api/finance/budgets — list all budgets */
    public function getBudgets($id = null, $data = [], $segments = [])
    {
        if ($id) {
            $budget = $this->db->query("SELECT * FROM budgets WHERE id=?", [$id])->fetch();
            if (!$budget) return $this->notFound('Budget not found');
            $lines  = $this->db->query(
                "SELECT bl.*, ec.name AS category_name FROM budget_line_items bl
                 LEFT JOIN expense_categories ec ON ec.id = bl.category_id WHERE bl.budget_id=?",
                [$id]
            )->fetchAll();
            return $this->success(['budget' => $budget, 'line_items' => $lines]);
        }
        $rows = $this->db->query(
            "SELECT b.*, u.full_name AS created_by_name,
                    COALESCE(SUM(bl.spent_amount),0) AS total_spent,
                    COALESCE(SUM(bl.allocated_amount),0) AS total_allocated
             FROM budgets b
             LEFT JOIN users u ON u.id = b.created_by
             LEFT JOIN budget_line_items bl ON bl.budget_id = b.id
             GROUP BY b.id ORDER BY b.academic_year DESC, b.term"
        )->fetchAll();
        return $this->success($rows);
    }

    /** POST /api/finance/budgets — create budget */
    public function postBudgets($id = null, $data = [], $segments = [])
    {
        if (empty($data['name']) || empty($data['academic_year'])) {
            return $this->badRequest('name and academic_year are required');
        }
        $userId = $this->getCurrentUserId();
        $this->db->query(
            "INSERT INTO budgets (name, academic_year, term, total_amount, description, status, created_by)
             VALUES (?,?,?,?,?,'draft',?)",
            [$data['name'], $data['academic_year'], $data['term'] ?? null,
             $data['total_amount'] ?? 0, $data['description'] ?? null, $userId]
        );
        $budgetId = $this->db->lastInsertId();
        if (!empty($data['line_items']) && is_array($data['line_items'])) {
            foreach ($data['line_items'] as $li) {
                $this->db->query(
                    "INSERT INTO budget_line_items (budget_id, category_id, description, allocated_amount) VALUES (?,?,?,?)",
                    [$budgetId, $li['category_id'] ?? null, $li['description'] ?? null, $li['allocated_amount'] ?? 0]
                );
            }
        }
        return $this->success(['id' => $budgetId], 'Budget created');
    }

    /** PUT /api/finance/budgets/{id} — update or approve/submit budget */
    public function putBudgets($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->badRequest('Budget ID required');
        $userId = $this->getCurrentUserId();
        $status = $data['status'] ?? null;
        if ($status) {
            $extra = '';
            $extraParams = [];
            if ($status === 'submitted')   { $extra = ', submitted_by=?, submitted_at=NOW()'; $extraParams = [$userId]; }
            if ($status === 'approved')    { $extra = ', approved_by=?, approved_at=NOW()';   $extraParams = [$userId]; }
            if ($status === 'active')      { $extra = ', activated_at=NOW()'; }
            $this->db->query(
                "UPDATE budgets SET status=?$extra, updated_at=NOW() WHERE id=?",
                array_merge([$status], $extraParams, [$id])
            );
            return $this->success(null, 'Budget status updated to '.$status);
        }
        $this->db->query(
            "UPDATE budgets SET name=?, total_amount=?, description=?, updated_at=NOW() WHERE id=?",
            [$data['name'] ?? '', $data['total_amount'] ?? 0, $data['description'] ?? null, $id]
        );
        return $this->success(null, 'Budget updated');
    }

    // ========================================
    // SECTION 16: Fee Waivers / Discounts
    // ========================================

    /** GET /api/finance/fee-waivers — list all discounts/waivers */
    public function getFeeWaivers($id = null, $data = [], $segments = [])
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($data['student_id'])) { $where[] = 'fdw.student_id=?'; $params[] = $data['student_id']; }
        if (!empty($data['status']))     { $where[] = 'fdw.status=?';     $params[] = $data['status']; }
        if (!empty($data['academic_year'])){ $where[] = 'fdw.academic_year=?'; $params[] = $data['academic_year']; }
        $rows = $this->db->query(
            "SELECT fdw.*, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    s.admission_number, c.name AS class_name,
                    u.full_name AS approved_by_name
             FROM fee_discounts_waivers fdw
             JOIN students s ON s.id = fdw.student_id
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN users u ON u.id = fdw.approved_by
             WHERE " . implode(' AND ', $where) . " ORDER BY fdw.created_at DESC",
            $params
        )->fetchAll();

        $stats = $this->db->query(
            "SELECT COUNT(*) AS total, COUNT(CASE WHEN status='active' THEN 1 END) AS active_count,
                    COALESCE(SUM(CASE WHEN status='active' THEN discount_value END),0) AS total_waived
             FROM fee_discounts_waivers"
        )->fetch();
        return $this->success(['waivers' => $rows, 'stats' => $stats]);
    }

    /** POST /api/finance/fee-waivers — create waiver/discount */
    public function postFeeWaivers($id = null, $data = [], $segments = [])
    {
        if (empty($data['student_id']) || empty($data['discount_type']) || !isset($data['discount_value'])) {
            return $this->badRequest('student_id, discount_type, discount_value are required');
        }
        if (empty($data['reason'])) return $this->badRequest('reason is required');
        $userId = $this->getCurrentUserId();
        $this->db->query(
            "INSERT INTO fee_discounts_waivers (student_id, student_fee_obligation_id, discount_type, discount_value,
              discount_percentage, reason, academic_year, term_id, approved_by, approved_date, status, valid_until)
             VALUES (?,?,?,?,?,?,?,?,?,NOW(),'active',?)",
            [
                $data['student_id'], $data['obligation_id'] ?? null,
                $data['discount_type'], $data['discount_value'],
                $data['discount_percentage'] ?? null, $data['reason'],
                $data['academic_year'] ?? date('Y'), $data['term_id'] ?? null,
                $userId, $data['valid_until'] ?? null
            ]
        );
        return $this->success(['id' => $this->db->lastInsertId()], 'Waiver created successfully');
    }

    // ========================================
    // SECTION 17: Sponsored Students
    // ========================================

    /** GET /api/finance/sponsored-students — list sponsored students */
    public function getSponsoredStudents($id = null, $data = [], $segments = [])
    {
        $rows = $this->db->query(
            "SELECT s.id, s.admission_number, CONCAT(s.first_name,' ',s.last_name) AS student_name,
                    s.is_sponsored, s.sponsor_name, s.sponsor_type, s.sponsor_waiver_percentage,
                    c.name AS class_name,
                    COALESCE(SUM(o.amount_due),0) AS total_fees,
                    COALESCE(SUM(o.amount_waived),0) AS total_waived,
                    COALESCE(SUM(o.amount_paid),0) AS total_paid,
                    COALESCE(SUM(o.balance),0) AS outstanding_balance
             FROM students s
             LEFT JOIN classes c ON c.id = s.class_id
             LEFT JOIN student_fee_obligations o ON o.student_id = s.id
                   AND o.academic_year = YEAR(CURDATE())
             WHERE s.is_sponsored = 1 AND s.status = 'active'
             GROUP BY s.id ORDER BY s.last_name, s.first_name"
        )->fetchAll();
        return $this->success($rows);
    }

    // ==================== FEE CREDIT NOTES ====================

    public function getFeeCredits($id = null, $data = [], $segments = [])
    {
        $studentId = $_GET['student_id'] ?? null;
        $status    = $_GET['status']     ?? null;
        $where = ['1=1']; $params = [];

        if ($studentId) { $where[] = 'fcn.student_id = ?'; $params[] = $studentId; }
        if ($status)    { $where[] = 'fcn.status = ?';     $params[] = $status; }

        $rows = $this->db->query(
            "SELECT fcn.id, fcn.credit_number, fcn.academic_year,
                    fcn.credit_amount, fcn.applied_amount, fcn.remaining_amount,
                    fcn.credit_reason, fcn.status, fcn.expiry_date, fcn.created_at,
                    CONCAT(s.first_name,' ',s.last_name) AS student_name, s.admission_no,
                    t.name AS term_name, u.name AS created_by_name
             FROM fee_credit_notes fcn
             JOIN students s ON s.id = fcn.student_id
             LEFT JOIN academic_terms t ON t.id = fcn.term_id
             LEFT JOIN users u ON u.id = fcn.created_by
             WHERE " . implode(' AND ', $where) . "
             ORDER BY fcn.created_at DESC",
            $params
        )->fetchAll();

        $stats = [
            'total_credits'    => array_sum(array_column($rows, 'credit_amount')),
            'total_available'  => array_sum(array_column(array_filter($rows, fn($r) => in_array($r['status'], ['available','partially_applied'])), 'remaining_amount')),
            'total_applied'    => array_sum(array_column($rows, 'applied_amount')),
        ];
        return $this->success(['credits' => $rows, 'stats' => $stats]);
    }

    public function postFeeCredits($id = null, $data = [], $segments = [])
    {
        $studentId = $data['student_id'] ?? null;
        $amount    = $data['credit_amount'] ?? null;
        if (!$studentId || !$amount) return $this->error('student_id and credit_amount required');

        $creditNum = 'CRD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $this->db->query(
            "INSERT INTO fee_credit_notes
             (credit_number, student_id, academic_year, term_id, source_transaction_id,
              credit_amount, credit_reason, expiry_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 2 YEAR), ?, ?)",
            [
                $creditNum, $studentId,
                $data['academic_year'] ?? date('Y'),
                $data['term_id']       ?? null,
                $data['source_transaction_id'] ?? null,
                $amount,
                $data['credit_reason'] ?? 'overpayment',
                $data['notes']         ?? null,
                $this->user['id']      ?? null,
            ]
        );
        return $this->success(['credit_number' => $creditNum, 'id' => $this->db->lastInsertId()], 201);
    }

    public function putFeeCredits($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('id required');
        $action = $data['action'] ?? 'apply';
        $credit = $this->db->query("SELECT * FROM fee_credit_notes WHERE id = ?", [$id])->fetch();
        if (!$credit) return $this->error('Credit note not found', 404);

        if ($action === 'refund') {
            $this->db->query("UPDATE fee_credit_notes SET status = 'refunded' WHERE id = ?", [$id]);
            return $this->success(['refunded' => true]);
        }

        $applyAmount = min((float)($data['apply_amount'] ?? 0), (float)$credit['remaining_amount']);
        if ($applyAmount <= 0) return $this->error('No credit remaining');

        $this->db->query(
            "UPDATE fee_credit_notes
             SET applied_amount = applied_amount + ?,
                 applied_to_year = ?, applied_to_term_id = ?, applied_at = NOW(),
                 status = CASE WHEN (applied_amount + ?) >= credit_amount THEN 'fully_applied' ELSE 'partially_applied' END
             WHERE id = ?",
            [$applyAmount, $data['to_year'] ?? date('Y'), $data['to_term_id'] ?? null, $applyAmount, $id]
        );

        if (!empty($data['obligation_id'])) {
            $this->db->query(
                "UPDATE student_fee_obligations SET amount_waived = amount_waived + ? WHERE id = ?",
                [$applyAmount, $data['obligation_id']]
            );
        }
        return $this->success(['applied' => $applyAmount]);
    }

    // ==================== SALARY ADVANCES ====================

    public function getSalaryAdvances($id = null, $data = [], $segments = [])
    {
        $staffId = $_GET['staff_id'] ?? null;
        $status  = $_GET['status']   ?? null;
        $where = ['1=1']; $params = [];

        if ($staffId) { $where[] = 'sa.staff_id = ?'; $params[] = $staffId; }
        if ($status)  { $where[] = 'sa.status = ?';   $params[] = $status; }

        $rows = $this->db->query(
            "SELECT sa.id, sa.advance_number, sa.requested_amount, sa.approved_amount,
                    sa.request_date, sa.deduction_schedule, sa.deduction_start_month,
                    sa.amount_per_deduction, sa.amount_deducted, sa.balance_remaining,
                    sa.status, sa.approval_date, sa.reason,
                    CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.employee_number,
                    u.name AS approved_by_name
             FROM staff_salary_advances sa
             JOIN staff s ON s.id = sa.staff_id
             LEFT JOIN users u ON u.id = sa.approved_by
             WHERE " . implode(' AND ', $where) . "
             ORDER BY sa.request_date DESC",
            $params
        )->fetchAll();

        $stats = [
            'total_advances'    => count($rows),
            'total_issued'      => array_sum(array_column(array_filter($rows, fn($r) => $r['approved_amount']), 'approved_amount')),
            'total_outstanding' => array_sum(array_column(array_filter($rows, fn($r) => $r['status'] === 'active'), 'balance_remaining')),
            'pending_approval'  => count(array_filter($rows, fn($r) => $r['status'] === 'pending')),
        ];
        return $this->success(['advances' => $rows, 'stats' => $stats]);
    }

    public function postSalaryAdvances($id = null, $data = [], $segments = [])
    {
        $staffId = $data['staff_id'] ?? null;
        $amount  = $data['requested_amount'] ?? null;
        if (!$staffId || !$amount) return $this->error('staff_id and requested_amount required');

        // GUARD: Cannot exceed 1 month salary and no active advance allowed simultaneously
        $existing = (float)$this->db->query(
            "SELECT COALESCE(SUM(balance_remaining),0) FROM staff_salary_advances
             WHERE staff_id = ? AND status = 'active'",
            [$staffId]
        )->fetchColumn();

        $salary = (float)($this->db->query("SELECT basic_salary FROM staff WHERE id = ?", [$staffId])->fetchColumn() ?? 0);
        if ($salary > 0 && ($existing + (float)$amount) > $salary) {
            return $this->error(
                "Advance exceeds limit. Active balance: KES " . number_format($existing, 2) .
                ". Max (1 month salary): KES " . number_format($salary, 2), 422
            );
        }

        $advNum = 'ADV-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        $this->db->query(
            "INSERT INTO staff_salary_advances
             (advance_number, staff_id, requested_amount, request_date, reason, deduction_schedule, status)
             VALUES (?, ?, ?, CURDATE(), ?, ?, 'pending')",
            [$advNum, $staffId, $amount, $data['reason'] ?? null, $data['deduction_schedule'] ?? 'single_month']
        );
        return $this->success(['advance_number' => $advNum, 'id' => $this->db->lastInsertId()], 201);
    }

    public function putSalaryAdvances($id = null, $data = [], $segments = [])
    {
        if (!$id) return $this->error('id required');
        $action = $data['action'] ?? null;
        $userId = $this->user['id'] ?? null;

        $advance = $this->db->query("SELECT * FROM staff_salary_advances WHERE id = ?", [$id])->fetch();
        if (!$advance) return $this->error('Advance not found', 404);

        if ($action === 'approve') {
            $approved = $data['approved_amount'] ?? $advance['requested_amount'];
            $months   = ['single_month' => 1, 'two_months' => 2, 'three_months' => 3][$advance['deduction_schedule']] ?? 1;
            $perDed   = round($approved / $months, 2);
            $start    = $data['deduction_start_month'] ?? date('Y-m-01', strtotime('first day of next month'));
            $this->db->query(
                "UPDATE staff_salary_advances
                 SET status = 'active', approved_amount = ?, amount_per_deduction = ?,
                     deduction_start_month = ?, balance_remaining = ?, approved_by = ?, approval_date = NOW()
                 WHERE id = ?",
                [$approved, $perDed, $start, $approved, $userId, $id]
            );
            return $this->success(['approved' => true, 'per_deduction' => $perDed]);
        }

        if ($action === 'reject') {
            $this->db->query(
                "UPDATE staff_salary_advances SET status = 'rejected', rejection_reason = ? WHERE id = ?",
                [$data['reason'] ?? null, $id]
            );
            return $this->success(['rejected' => true]);
        }

        if ($action === 'record_deduction') {
            $amt        = min((float)($data['amount'] ?? $advance['amount_per_deduction']), (float)$advance['balance_remaining']);
            $newBalance = max(0, (float)$advance['balance_remaining'] - $amt);
            $newStatus  = $newBalance <= 0 ? 'fully_deducted' : 'active';
            $this->db->query(
                "UPDATE staff_salary_advances
                 SET amount_deducted = amount_deducted + ?, balance_remaining = ?, status = ?
                 WHERE id = ?",
                [$amt, $newBalance, $newStatus, $id]
            );
            return $this->success(['deducted' => $amt, 'remaining' => $newBalance]);
        }

        return $this->error('Unknown action');
    }
}
