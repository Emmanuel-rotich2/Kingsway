<?php
namespace App\API\Controllers;

use App\API\Modules\finance\FinanceAPI;
use Exception;

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

    public function __construct() {
        parent::__construct();
        $this->api = new FinanceAPI();
    }

    public function index()
    {
        return $this->success(['message' => 'Finance API is running']);
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
     * Approve or reject a department budget proposal
     */
    public function postDepartmentBudgetsApprove($id = null, $data = [], $segments = [])
    {
        // Expecting: $data['proposal_id'], $data['status'], $data['reviewed_by']
        $proposalId = $data['proposal_id'] ?? null;
        $status = $data['status'] ?? null;
        $reviewedBy = $data['reviewed_by'] ?? $this->getCurrentUserId();
        if (!$proposalId || !$status) {
            return $this->badRequest('proposal_id and status are required');
        }
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
        $result = $this->api->approvePayroll($data);
        return $this->handleResponse($result);
    }

    /**
     * POST /api/finance/payrolls/reject
     */
    public function postPayrollsReject($id = null, $data = [], $segments = [])
    {
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

    // ========================================
    // SECTION 5: Student Payment History
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

    // ========================================
    // SECTION 6: Reporting Operations
    // ========================================

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
}
