<?php

namespace App\API\Modules\finance;

use App\API\Modules\finance\FeeManager;
use App\API\Modules\finance\PaymentManager;
use App\API\Modules\finance\BudgetManager;
use App\API\Modules\finance\ExpenseManager;
use App\API\Modules\finance\ReportingManager;
use App\API\Modules\finance\PayrollWorkflow;
use App\API\Modules\finance\FeeApprovalWorkflow;
use App\API\Modules\finance\BudgetApprovalWorkflow;
use App\API\Modules\finance\ExpenseApprovalWorkflow;
use App\API\Modules\staff\StaffPayrollManager;

/**
 * Finance Service - Central Integration Layer
 * 
 * Provides unified access to all finance module components:
 * - Fee Management
 * - Payment Processing
 * - Budget Management
 * - Expense Tracking
 * - Financial Reporting
 * - Payroll Integration
 * 
 * This service instantiates all managers and workflows,
 * providing a single point of access for the Finance API.
 * 
 * Usage:
 * $financeService = new FinanceService();
 * $feeManager = $financeService->getFeeManager();
 * $result = $feeManager->calculateStudentFees($studentId, $year, $term);
 */
class FinanceService
{
    private $feeManager;
    private $paymentManager;
    private $budgetManager;
    private $expenseManager;
    private $reportingManager;
    private $payrollWorkflow;
    private $feeApprovalWorkflow;
    private $budgetApprovalWorkflow;
    private $expenseApprovalWorkflow;
    private $staffPayrollManager;

    public function __construct()
    {
        // Initialize all managers
        $this->feeManager = new FeeManager();
        $this->paymentManager = new PaymentManager();
        $this->budgetManager = new BudgetManager();
        $this->expenseManager = new ExpenseManager();
        $this->reportingManager = new ReportingManager();

        // Initialize workflows
        $this->payrollWorkflow = new PayrollWorkflow();
        $this->feeApprovalWorkflow = new FeeApprovalWorkflow();
        $this->budgetApprovalWorkflow = new BudgetApprovalWorkflow();
        $this->expenseApprovalWorkflow = new ExpenseApprovalWorkflow();

        // Initialize payroll manager for integration
        $this->staffPayrollManager = new StaffPayrollManager();
    }

    /**
     * Get Fee Manager instance
     * @return FeeManager
     */
    public function getFeeManager()
    {
        return $this->feeManager;
    }

    /**
     * Get Payment Manager instance
     * @return PaymentManager
     */
    public function getPaymentManager()
    {
        return $this->paymentManager;
    }

    /**
     * Get Budget Manager instance
     * @return BudgetManager
     */
    public function getBudgetManager()
    {
        return $this->budgetManager;
    }

    /**
     * Get Expense Manager instance
     * @return ExpenseManager
     */
    public function getExpenseManager()
    {
        return $this->expenseManager;
    }

    /**
     * Get Reporting Manager instance
     * @return ReportingManager
     */
    public function getReportingManager()
    {
        return $this->reportingManager;
    }

    /**
     * Get Payroll Workflow instance
     * @return PayrollWorkflow
     */
    public function getPayrollWorkflow()
    {
        return $this->payrollWorkflow;
    }

    /**
     * Get Fee Approval Workflow instance
     * @return FeeApprovalWorkflow
     */
    public function getFeeApprovalWorkflow()
    {
        return $this->feeApprovalWorkflow;
    }

    /**
     * Get Budget Approval Workflow instance
     * @return BudgetApprovalWorkflow
     */
    public function getBudgetApprovalWorkflow()
    {
        return $this->budgetApprovalWorkflow;
    }

    /**
     * Get Expense Approval Workflow instance
     * @return ExpenseApprovalWorkflow
     */
    public function getExpenseApprovalWorkflow()
    {
        return $this->expenseApprovalWorkflow;
    }

    /**
     * Get Staff Payroll Manager instance
     * @return StaffPayrollManager
     */
    public function getStaffPayrollManager()
    {
        return $this->staffPayrollManager;
    }
}
