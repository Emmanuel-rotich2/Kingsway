<?php

namespace App\API\Modules\Finance;

use App\API\Includes\BaseAPI;
use App\API\Modules\Communications\CommunicationsAPI;
use App\API\Modules\Finance\FeeManager;
use App\API\Modules\Finance\PaymentManager;
use App\API\Modules\Finance\BudgetManager;
use App\API\Modules\Finance\ExpenseManager;
use App\API\Modules\Finance\ReportingManager;
use App\API\Modules\Finance\FeeApprovalWorkflow;
use App\API\Modules\Finance\BudgetApprovalWorkflow;
use App\API\Modules\Finance\ExpenseApprovalWorkflow;
use App\API\Modules\Finance\PayrollWorkflow;
use App\API\Services\Payments\DisbursementManager;
use App\API\Services\Payments\MpesaB2CService;
use App\API\Services\Payments\MpesaPaymentService;
use App\API\Services\Payments\KcbFundsTransferService;
use App\API\Services\Workflows\PayrollApprovalWorkflow;
use PDO;
use Exception;
use function App\API\Includes\formatResponse;

/**
 * FinanceAPI - Central coordinator for all financial operations
 * 
 * Delegates ALL operations to specialized managers and workflows.
 * NO direct database operations - pure coordination layer.
 * 
 * MANAGERS (Business Logic):
 * - FeeManager: Fee structures, student fees, discounts, carryovers (14 methods)
 * - PaymentManager: Payment processing, M-Pesa/bank integration, reconciliation (9 methods)
 * - BudgetManager: Budget planning, tracking, variance analysis (6 methods)
 * - ExpenseManager: Expense recording, approval, tracking (7 methods)
 * - ReportingManager: Dashboards, analytics, financial reports (7 methods)
 * - DisbursementManager: Staff salary disbursements via M-Pesa B2C / Bank transfers
 * 
 * WORKFLOWS (State Management):
 * - FeeApprovalWorkflow: draft → review → approval → activation
 * - BudgetApprovalWorkflow: draft → dept review → finance review → director approval
 * - ExpenseApprovalWorkflow: submission → validation → approval → payment
 * - PayrollApprovalWorkflow: draft → pending → approved → processing → completed (15th-30th cycle)
 * 
 * PAYMENT SERVICES (Integration):
 * - MpesaPaymentService: M-Pesa STK Push, C2B Paybill (incoming student fees)
 * - MpesaB2CService: M-Pesa B2C (outgoing - staff salaries, refunds)
 * - KcbFundsTransferService: KCB Bank transfers (incoming & outgoing)
 * 
 * DATABASE SCHEMA (Actual Tables):
 * - fee_structures, fee_types, fee_structures_detailed
 * - student_fee_obligations, student_fee_balances, student_fee_carryover
 * - fee_discounts_waivers, fee_reminders, fee_transition_history
 * - payment_transactions, payment_allocations, payment_allocations_detailed, payment_reconciliations
 * - payrolls, staff_payments (managed by PayrollApprovalWorkflow & DisbursementManager)
 * - mpesa_transactions, bank_transactions, payment_webhooks_log
 */

class FinanceAPI extends BaseAPI
{
    // Managers
    private $feeManager;
    private $paymentManager;
    private $budgetManager;
    private $expenseManager;
    private $reportingManager;
    private $disbursementManager;
    private $departmentBudgetManager;

    // Workflows
    private $feeWorkflow;
    private $budgetWorkflow;
    private $expenseWorkflow;
    private $payrollWorkflow;
    private $payrollApprovalWorkflow;

    // Payment Services
    private $mpesaB2C;
    private $mpesaPayment;
    private $kcbTransfer;

    // Communications
    private $communicationsApi;


    public function __construct()
    {
        parent::__construct('finance');

        // Initialize Managers
        $this->feeManager = new FeeManager();
        $this->paymentManager = new PaymentManager();
        $this->budgetManager = new BudgetManager();
        $this->expenseManager = new ExpenseManager();
        $this->reportingManager = new ReportingManager();
        $this->disbursementManager = new DisbursementManager();
        $this->departmentBudgetManager = new DepartmentBudgetManager($this->db);

        // Initialize Workflows
        $this->feeWorkflow = new FeeApprovalWorkflow('FEE_APPROVAL');
        $this->budgetWorkflow = new BudgetApprovalWorkflow('BUDGET_APPROVAL');
        $this->expenseWorkflow = new ExpenseApprovalWorkflow('EXPENSE_APPROVAL');
        $this->payrollWorkflow = new PayrollWorkflow('PAYROLL');
        $this->payrollApprovalWorkflow = new PayrollApprovalWorkflow('PAYROLL_APPROVAL');

        // Initialize Payment Services
        $this->mpesaB2C = new MpesaB2CService();
        $this->mpesaPayment = new MpesaPaymentService();
        $this->kcbTransfer = new KcbFundsTransferService();

        // Initialize Communications
        $this->communicationsApi = new CommunicationsAPI();
    }

    /**
     * Department Budget: Submit a new proposal
     */
    public function proposeDepartmentBudget($data)
    {
        try {
            $proposalId = $this->departmentBudgetManager->submitProposal($data);
            return formatResponse(true, ['proposal_id' => $proposalId], 'Proposal submitted');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Department Budget: List proposals
     */
    public function listDepartmentBudgetProposals($filters = [])
    {
        try {
            $proposals = $this->departmentBudgetManager->listProposals($filters);
            return formatResponse(true, $proposals);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Department Budget: Approve/Reject proposal
     */
    public function updateDepartmentBudgetProposalStatus($proposalId, $status, $reviewedBy)
    {
        try {
            $result = $this->departmentBudgetManager->updateProposalStatus($proposalId, $status, $reviewedBy);
            return formatResponse(true, ['rows_affected' => $result], 'Proposal status updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Department Budget: Allocate funds
     */
    public function allocateDepartmentBudget($departmentId, $amount, $allocatedBy)
    {
        try {
            $allocationId = $this->departmentBudgetManager->allocateFunds($departmentId, $amount, $allocatedBy);
            return formatResponse(true, ['allocation_id' => $allocationId], 'Funds allocated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Department Budget: Request funds (loan/overdraft)
     */
    public function requestDepartmentFunds($data)
    {
        try {
            $requestId = $this->departmentBudgetManager->requestFund($data);
            return formatResponse(true, ['request_id' => $requestId], 'Fund request submitted');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Department Budget: List fund requests
     */
    public function listDepartmentFundRequests($filters = [])
    {
        try {
            $requests = $this->departmentBudgetManager->listFundRequests($filters);
            return formatResponse(true, $requests);
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Department Budget: Approve/Reject fund request
     */
    public function updateDepartmentFundRequestStatus($requestId, $status, $reviewedBy)
    {
        try {
            $result = $this->departmentBudgetManager->updateFundRequestStatus($requestId, $status, $reviewedBy);
            return formatResponse(true, ['rows_affected' => $result], 'Fund request status updated');
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * List records - delegates to appropriate manager
     */
    public function list($params = [])
    {
        try {
            $type = $params['type'] ?? $_GET['type'] ?? 'fees';

            switch ($type) {
                // FEE OPERATIONS
                case 'fees':
                case 'fee-structures':
                    return $this->feeManager->listFeeStructures($params);

                // PAYMENT OPERATIONS
                case 'payments':
                    return $this->paymentManager->listPayments($params);

                // BUDGET OPERATIONS
                case 'budgets':
                    return $this->budgetManager->listBudgets($params);

                // EXPENSE OPERATIONS
                case 'expenses':
                    return $this->expenseManager->listExpenses($params);

                // PAYROLL OPERATIONS
                case 'payrolls':
                    return $this->listPayrolls($params);

                case 'staff-payments':
                    $payrollId = $params['payroll_id'] ?? $_GET['payroll_id'] ?? null;
                    return $this->listStaffPayments($payrollId);

                default:
                    throw new Exception("Invalid type: $type");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Get single record - delegates to appropriate manager
     */
    public function get($id)
    {
        try {
            $type = $_GET['type'] ?? 'fee';

            switch ($type) {
                // FEE OPERATIONS
                case 'fee':
                case 'fee-structure':
                    return $this->feeManager->getFeeStructure($id);

                case 'student-balance':
                    return $this->feeManager->getStudentFeeBalance($id);

                // PAYMENT OPERATIONS
                case 'payment':
                    return $this->paymentManager->getPayment($id);

                // BUDGET OPERATIONS
                case 'budget':
                    return $this->budgetManager->getBudget($id);

                // EXPENSE OPERATIONS
                case 'expense':
                    return $this->expenseManager->getExpense($id);

                // PAYROLL OPERATIONS
                case 'payroll':
                    return $this->getPayroll($id);

                default:
                    throw new Exception("Invalid type: $type");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Create new record - delegates to appropriate manager
     */
    public function create($data)
    {
        try {
            $type = $data['type'] ?? $_POST['type'] ?? null;
            if (!$type) {
                throw new Exception('Type is required');
            }

            switch ($type) {
                // FEE OPERATIONS
                case 'fee-structure':
                    $result = $this->feeManager->createFeeStructure($data);
                    if ($result['status'] === 'success') {
                        $this->logAction('create_fee_structure', $result['data']['fee_structure_id'] ?? null, 'Created fee structure');
                    }
                    return $result;

                case 'discount':
                    $result = $this->feeManager->applyDiscount($data['student_id'], $data);
                    if ($result['status'] === 'success') {
                        $this->logAction('apply_discount', $data['student_id'], 'Applied discount to student');
                    }
                    return $result;

                // PAYMENT OPERATIONS
                case 'payment':
                    $result = $this->paymentManager->processPayment($data);
                    if ($result['status'] === 'success') {
                        $this->logAction('record_payment', $result['data']['payment_id'] ?? null, 'Recorded payment');
                        if (isset($data['student_id']) && isset($data['amount'])) {
                            $this->sendPaymentNotification($data['student_id'], $data['amount']);
                        }
                    }
                    return $result;

                // BUDGET OPERATIONS
                case 'budget':
                    $result = $this->budgetManager->createBudget($data);
                    if ($result['status'] === 'success') {
                        $this->logAction('create_budget', $result['data']['budget_id'] ?? null, 'Created budget');
                    }
                    return $result;

                // EXPENSE OPERATIONS
                case 'expense':
                    $result = $this->expenseManager->recordExpense($data);
                    if ($result['status'] === 'success') {
                        $this->logAction('record_expense', $result['data']['expense_id'] ?? null, 'Recorded expense');
                    }
                    return $result;

                // PAYROLL OPERATIONS
                case 'payroll':
                    $result = $this->createPayrollDraft($data);
                    if ($result['status'] === 'success') {
                        $this->logAction('create_payroll', $result['data']['payroll_id'] ?? null, 'Created payroll draft');
                    }
                    return $result;

                default:
                    throw new Exception("Invalid type: $type");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Update existing record - delegates to appropriate manager
     */
    public function update($id, $data)
    {
        try {
            $type = $data['type'] ?? $_POST['type'] ?? null;
            if (!$type) {
                throw new Exception('Type is required');
            }

            switch ($type) {
                // FEE OPERATIONS
                case 'fee-structure':
                    $result = $this->feeManager->updateFeeStructure($id, $data);
                    if ($result['status'] === 'success') {
                        $this->logAction('update_fee_structure', $id, 'Updated fee structure');
                    }
                    return $result;

                // BUDGET OPERATIONS
                case 'budget':
                    $result = $this->budgetManager->updateBudget($id, $data);
                    if ($result['status'] === 'success') {
                        $this->logAction('update_budget', $id, 'Updated budget');
                    }
                    return $result;

                // EXPENSE OPERATIONS
                case 'expense':
                    $result = $this->expenseManager->updateExpense($id, $data);
                    if ($result['status'] === 'success') {
                        $this->logAction('update_expense', $id, 'Updated expense');
                    }
                    return $result;

                default:
                    throw new Exception("Invalid type: $type");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Delete record - delegates to appropriate manager
     */
    public function delete($id)
    {
        try {
            $type = $_GET['type'] ?? $_POST['type'] ?? null;
            if (!$type) {
                throw new Exception('Type is required');
            }

            switch ($type) {
                // BUDGET OPERATIONS
                case 'budget':
                    $result = $this->budgetManager->deleteBudget($id);
                    if ($result['status'] === 'success') {
                        $this->logAction('delete_budget', $id, 'Deleted budget');
                    }
                    return $result;

                // EXPENSE OPERATIONS
                case 'expense':
                    $result = $this->expenseManager->deleteExpense($id);
                    if ($result['status'] === 'success') {
                        $this->logAction('delete_expense', $id, 'Deleted expense');
                    }
                    return $result;

                default:
                    throw new Exception("Invalid type: $type");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle custom GET actions - routes to managers/workflows
     */
    public function handleCustomGet($id, $action, $params)
    {
        try {
            switch ($action) {
                // FEE OPERATIONS
                case 'balance':
                    return $this->feeManager->getStudentFeeBalance($id);

                case 'statement':
                    return $this->feeManager->getStudentFeeStatement($id, $params['academic_year'] ?? null);

                case 'outstanding':
                    return $this->feeManager->getOutstandingFeesReport($params);

                // PAYMENT OPERATIONS
                case 'receipt':
                    return $this->generateReceipt($id);

                case 'payment-status':
                    return $this->paymentManager->getStudentPaymentStatus($id);

                // BUDGET OPERATIONS
                case 'budget-variance':
                    return $this->reportingManager->getBudgetVsActualReport($id);

                // PAYROLL OPERATIONS
                case 'payslip':
                    return $this->generatePayslip($id);

                case 'disbursement-report':
                    return $this->disbursementManager->getDisbursementReport($id);

                case 'failed-payments':
                    return $this->disbursementManager->getFailedPayments($id);

                // REPORTING
                case 'dashboard':
                    return $this->reportingManager->getFinancialDashboard($params);

                case 'fee-collection-report':
                    return $this->reportingManager->getFinancialDashboard($params);

                case 'payroll-report':
                    return $this->generatePayrollReport($params);

                default:
                    throw new Exception("Invalid action: $action");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * Handle custom POST actions - routes to managers/workflows
     */
    public function handleCustomPost($id, $action, $data)
    {
        try {
            switch ($action) {
                // FEE WORKFLOW ACTIONS
                case 'submit-fee-for-approval':
                    $result = $this->feeWorkflow->submitForApproval($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('submit_fee_approval', $id, 'Submitted fee for approval');
                    }
                    return $result;

                case 'approve-fee':
                    $result = $this->feeWorkflow->approve($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('approve_fee', $id, 'Approved fee');
                    }
                    return $result;

                case 'reject-fee':
                    $result = $this->feeWorkflow->reject($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('reject_fee', $id, 'Rejected fee');
                    }
                    return $result;

                case 'activate-fee':
                    $result = $this->feeWorkflow->activate($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('activate_fee', $id, 'Activated fee');
                    }
                    return $result;

                // PAYMENT ACTIONS
                case 'allocate':
                    $result = $this->paymentManager->allocatePayment($id, $data);
                    if ($result['status'] === 'success') {
                        $this->logAction('allocate_payment', $id, 'Allocated payment');
                    }
                                        return $result;

                case 'reconcile':
                    $result = $this->paymentManager->reconcilePayments($data);
                    if ($result['status'] === 'success') {
                        $this->logAction('reconcile_payments', null, 'Reconciled payments');
                    }
                    return $result;

                // BUDGET WORKFLOW ACTIONS
                case 'submit-budget':
                    $result = $this->budgetWorkflow->submitForDepartmentalReview($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('submit_budget', $id, 'Submitted budget for review');
                    }
                    return $result;

                case 'approve-budget-dept':
                    $result = $this->budgetWorkflow->approveDepartmental($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('approve_budget_dept', $id, 'Approved budget (Dept)');
                    }
                    return $result;

                case 'approve-budget-finance':
                    $result = $this->budgetWorkflow->approveFinance($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('approve_budget_finance', $id, 'Approved budget (Finance)');
                    }
                    return $result;

                case 'approve-budget-director':
                    $result = $this->budgetWorkflow->approveDirector($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('approve_budget_director', $id, 'Approved budget (Director)');
                    }
                    return $result;

                case 'reject-budget':
                    $result = $this->budgetWorkflow->reject($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('reject_budget', $id, 'Rejected budget');
                    }
                    return $result;

                // EXPENSE WORKFLOW ACTIONS
                case 'submit-expense':
                    $result = $this->expenseWorkflow->submitForApproval($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('submit_expense', $id, 'Submitted expense for approval');
                    }
                    return $result;

                case 'approve-expense':
                    $result = $this->expenseWorkflow->approve($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('approve_expense', $id, 'Approved expense');
                    }
                    return $result;

                case 'reject-expense':
                    $result = $this->expenseWorkflow->reject($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('reject_expense', $id, 'Rejected expense');
                    }
                    return $result;

                case 'process-expense-payment':
                    $result = $this->expenseWorkflow->processPayment($id, $data, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('process_expense_payment', $id, 'Processed expense payment');
                    }
                    return $result;

                // PAYROLL WORKFLOW ACTIONS
                case 'submit-payroll':
                    $result = $this->payrollApprovalWorkflow->submitForApproval($id, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        $this->logAction('submit_payroll', $id, 'Submitted payroll for approval');
                    }
                    return $result;

                case 'approve-payroll':
                    $result = $this->payrollApprovalWorkflow->approve($id, $this->getCurrentUserId(), $data['comments'] ?? '');
                    if ($result['status'] === 'success') {
                        $this->logAction('approve_payroll', $id, 'Approved payroll');
                    }
                    return $result;

                case 'reject-payroll':
                    $result = $this->payrollApprovalWorkflow->reject($id, $this->getCurrentUserId(), $data['reason'] ?? '');
                    if ($result['status'] === 'success') {
                        $this->logAction('reject_payroll', $id, 'Rejected payroll');
                    }
                    return $result;

                case 'disburse-payroll':
                    $result = $this->payrollApprovalWorkflow->startDisbursement($id, $this->getCurrentUserId());
                    if ($result['status'] === 'success') {
                        // Actual disbursement happens here
                        $this->disbursementManager->processPayrollDisbursement($id, $this->getCurrentUserId());
                    }
                    return $result;

                case 'retry-failed-payment':
                    $result = $this->disbursementManager->retryFailedPayment($id);
                    if ($result['status'] === 'success') {
                        $this->logAction('retry_payment', $id, 'Retried failed payment');
                    }
                    return $result;

                // FEE CARRYOVER
                case 'carryover':
                    $result = $this->feeManager->carryoverBalance($data['student_id'], $data['from_year'], $data['to_year']);
                    if ($result['status'] === 'success') {
                        $this->logAction('carryover_fees', null, 'Carried over fee balance');
                    }
                    return $result;

                default:
                    throw new Exception("Invalid action: $action");
            }
        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    // ============================================================================
    // PAYROLL-SPECIFIC METHODS
    // ============================================================================

    public function listPayrolls($params = [])
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 20;
        $offset = ($page - 1) * $limit;

        $sql = "SELECT 
                    p.*,
                    COUNT(sp.id) as staff_count,
                    SUM(sp.gross_salary) as total_gross,
                    SUM(sp.total_deductions) as total_deductions,
                    SUM(sp.net_salary) as total_net,
                    SUM(CASE WHEN sp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN sp.payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM payrolls p
                LEFT JOIN staff_payments sp ON p.id = sp.payroll_id
                GROUP BY p.id
                ORDER BY p.month DESC, p.year DESC
                LIMIT ? OFFSET ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit, $offset]);
        $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) FROM payrolls";
        $total = $this->db->query($countSql)->fetchColumn();

        return formatResponse(true, [
            'payrolls' => $payrolls,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ], 'Payrolls retrieved successfully');
    }

    public function listStaffPayments($payrollId)
    {
        $sql = "SELECT 
                    sp.*,
                    s.first_name,
                    s.last_name,
                    s.staff_number
                FROM staff_payments sp
                JOIN staff s ON sp.staff_id = s.id
                WHERE sp.payroll_id = ?
                ORDER BY s.last_name, s.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payrollId]);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return formatResponse(true, ['staff_payments' => $payments], 'Staff payments retrieved successfully');
    }

    public function getPayroll($id)
    {
        $sql = "SELECT * FROM payrolls WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payroll = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payroll) {
            return formatResponse(false, null, 'Payroll not found', 404);
        }

        $sql = "SELECT 
                    sp.*,
                    s.first_name,
                    s.last_name,
                    s.staff_number
                FROM staff_payments sp
                JOIN staff s ON sp.staff_id = s.id
                WHERE sp.payroll_id = ?
                ORDER BY s.last_name, s.first_name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $payroll['staff_payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return formatResponse(true, ['payroll' => $payroll], 'Payroll retrieved successfully');
    }

    public function createPayrollDraft($data)
    {
        return $this->payrollApprovalWorkflow->initiateDraft($data);
    }

    public function calculatePayroll($data)
    {
        // Calculate payroll (usually part of draft creation, but can be separate)
        $payrollId = $data['payroll_id'] ?? null;
        if (!$payrollId) {
            return formatResponse(false, null, 'Payroll ID required');
        }

        // Re-calculate based on current staff data
        return $this->createPayrollDraft($data);
    }

    public function recalculatePayroll($data)
    {
        return $this->calculatePayroll($data);
    }

    public function verifyPayroll($data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (!$payrollId || !$userId) {
            return formatResponse(false, null, 'Payroll ID and User ID required');
        }

        return $this->payrollApprovalWorkflow->submitForApproval($payrollId, $userId);
    }

    public function approvePayroll($data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $comments = $data['comments'] ?? '';

        if (!$payrollId || !$userId) {
            return formatResponse(false, null, 'Payroll ID and User ID required');
        }

        return $this->payrollApprovalWorkflow->approve($payrollId, $userId, $comments);
    }

    public function rejectPayroll($data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $reason = $data['reason'] ?? '';

        if (!$payrollId || !$userId) {
            return formatResponse(false, null, 'Payroll ID and User ID required');
        }

        return $this->payrollApprovalWorkflow->reject($payrollId, $userId, $reason);
    }

    public function processPayroll($data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (!$payrollId || !$userId) {
            return formatResponse(false, null, 'Payroll ID and User ID required');
        }

        // Process payroll (mark as ready for disbursement)
        return $this->payrollApprovalWorkflow->startDisbursement($payrollId, $userId);
    }

    public function disbursePayroll($data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        $userId = $data['user_id'] ?? null;

        if (!$payrollId || !$userId) {
            return formatResponse(false, null, 'Payroll ID and User ID required');
        }

        // Disburse via DisbursementManager
        return $this->disbursementManager->processPayrollDisbursement($payrollId, $userId);
    }

    public function cancelPayroll($payrollId)
    {
        if (!$payrollId) {
            return formatResponse(false, null, 'Payroll ID required');
        }

        // Cancel/delete payroll
        $sql = "UPDATE payrolls SET status = 'cancelled' WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payrollId]);

        return formatResponse(true, null, 'Payroll cancelled successfully');
    }

    public function getPayrollStatus($payrollId)
    {
        if (!$payrollId) {
            return formatResponse(false, null, 'Payroll ID required');
        }

        $sql = "SELECT p.*, COUNT(sp.id) as staff_count,
                SUM(CASE WHEN sp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN sp.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN sp.payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM payrolls p
                LEFT JOIN staff_payments sp ON p.id = sp.payroll_id
                WHERE p.id = ?
                GROUP BY p.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$payrollId]);
        $status = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$status) {
            return formatResponse(false, null, 'Payroll not found', 404);
        }

        return formatResponse(true, ['status' => $status], 'Payroll status retrieved successfully');
    }

    public function getStaffPayments($data)
    {
        $payrollId = $data['payroll_id'] ?? null;
        if (!$payrollId) {
            return formatResponse(false, null, 'Payroll ID required');
        }

        return $this->listStaffPayments($payrollId);
    }

    public function getPayrollSummary($data)
    {
        return $this->generatePayrollReport($data);
    }

    public function getPayrollHistory($data)
    {
        $staffId = $data['staff_id'] ?? null;

        $sql = "SELECT sp.*, p.month, p.year, p.status as payroll_status
                FROM staff_payments sp
                JOIN payrolls p ON sp.payroll_id = p.id
                WHERE 1=1";

        $bindings = [];
        if ($staffId) {
            $sql .= " AND sp.staff_id = ?";
            $bindings[] = $staffId;
        }

        $sql .= " ORDER BY p.year DESC, p.month DESC, sp.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return formatResponse(true, ['history' => $history], 'Payroll history retrieved successfully');
    }

    // ============================================================================
    // REPORTING METHODS
    // ============================================================================

    public function generateReceipt($paymentId)
    {
        $payment = $this->paymentManager->getPayment($paymentId);
        if ($payment['status'] !== 'success') {
            return $payment;
        }

        return formatResponse(true, [
            'payment' => $payment['data'],
            'receipt_number' => 'RCT-' . str_pad($paymentId, 8, '0', STR_PAD_LEFT),
            'generated_at' => date('Y-m-d H:i:s')
        ], 'Receipt generated successfully');
    }

    public function generatePayslip($staffPaymentId)
    {
        $sql = "SELECT 
                    sp.*,
                    s.first_name,
                    s.last_name,
                    s.staff_number,
                    s.bank_account_number,
                    s.bank_name,
                    p.month,
                    p.year
                FROM staff_payments sp
                JOIN staff s ON sp.staff_id = s.id
                JOIN payrolls p ON sp.payroll_id = p.id
                WHERE sp.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$staffPaymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$payment) {
            return formatResponse(false, null, 'Staff payment not found', 404);
        }

        return formatResponse(true, [
            'payment' => $payment,
            'payslip_number' => 'PAY-' . str_pad($staffPaymentId, 8, '0', STR_PAD_LEFT),
            'generated_at' => date('Y-m-d H:i:s')
        ], 'Payslip generated successfully');
    }

    public function generatePayrollReport($params)
    {
        $sql = "SELECT 
                    p.*,
                    COUNT(sp.id) as staff_count,
                    SUM(sp.gross_salary) as total_gross,
                    SUM(sp.total_deductions) as total_deductions,
                    SUM(sp.net_salary) as total_net,
                    SUM(CASE WHEN sp.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count
                FROM payrolls p
                LEFT JOIN staff_payments sp ON p.id = sp.payroll_id
                WHERE 1=1";

        $bindings = [];
        if (!empty($params['start_date'])) {
            $sql .= " AND p.created_at >= ?";
            $bindings[] = $params['start_date'];
        }
        if (!empty($params['end_date'])) {
            $sql .= " AND p.created_at <= ?";
            $bindings[] = $params['end_date'];
        }

        $sql .= " GROUP BY p.id ORDER BY p.year DESC, p.month DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
        $report = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return formatResponse(true, ['report' => $report], 'Payroll report generated successfully');
    }

    // ============================================================================
    // NOTIFICATION METHODS
    // ============================================================================

    public function sendPaymentNotification($studentId, $amount)
    {
        try {
            $sql = "SELECT 
                        s.first_name,
                        s.last_name,
                        s.admission_no,
                        p.phone as parent_phone,
                        p.email as parent_email
                    FROM students s
                    LEFT JOIN student_parents sp ON s.id = sp.student_id
                    LEFT JOIN parents p ON sp.parent_id = p.id
                    WHERE s.id = ? LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student || empty($student['parent_phone'])) {
                return;
            }

            // Use the communications template system
            $recipients = [$student['parent_phone']];
            $variables = [
                'amount' => number_format($amount, 2),
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'admission_no' => $student['admission_no']
            ];
            // Category must match the template definition, e.g., 'fee_payment_received'
            $category = 'fee_payment_received';
            $type = 'sms';
            if (method_exists($this->communicationsApi, 'sendTemplateSMS')) {
                $this->communicationsApi->sendTemplateSMS($recipients, $variables, $category, $type);
            }
        } catch (Exception $e) {
            error_log('Failed to send payment notification: ' . $e->getMessage());
        }
    }

    
    // ============================================================================
    // ANNUAL FEE STRUCTURE MANAGEMENT (Academic Year Integration)
    // ============================================================================

    public function createAnnualFeeStructure($data)
    {
        return $this->feeManager->createAnnualFeeStructure($data);
    }

    public function reviewFeeStructure($data)
    {
        return $this->feeManager->reviewFeeStructure($data);
    }

    public function approveFeeStructure($data)
    {
        return $this->feeManager->approveFeeStructure($data);
    }

    public function activateFeeStructure($data)
    {
        return $this->feeManager->activateFeeStructure($data);
    }

    public function rolloverFeeStructure($data)
    {
        return $this->feeManager->rolloverFeeStructure($data);
    }

    public function getTermBreakdown($academicYear, $levelId)
    {
        return $this->feeManager->getTermBreakdown($academicYear, $levelId);
    }

    public function getStudentPaymentHistory($studentId, $academicYear = null)
    {
        return $this->feeManager->getStudentPaymentHistory($studentId, $academicYear);
    }

    public function compareYearlyCollections($year1, $year2)
    {
        return $this->feeManager->compareYearlyCollections($year1, $year2);
    }

    public function getPendingReviews()
    {
        return $this->feeManager->getPendingReviews();
    }

    public function getAnnualFeeSummary($academicYear, $levelId = null)
    {
        return $this->feeManager->getAnnualFeeSummary($academicYear, $levelId);
    }
}
