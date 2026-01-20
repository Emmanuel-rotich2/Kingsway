Finance managers audit

This file lists finance managers found under `api/modules/finance` and brief responsibilities inferred from filenames and code layout.

- `FinanceAPI.php`: Central coordinator delegating to specific managers and workflows.
- `FinanceService.php`: Shared helpers, transaction utilities, DB helpers.
- `FinancePaymentsAPI.php`: Payment-related API wrapper used by controllers.
- `PaymentManager.php`: Handles recording payments, allocations, and allocation details.
- `PaymentReconciliationAPI.php`: Lists and reconciles bank/mpesa transactions.
- `FeeManager.php`: Fee structure creation, applying discounts, generating obligations.
- `FeeApprovalWorkflow.php`: Review/approve/activate fee structures.
- `DepartmentBudgetManager.php`: Department budget proposals and allocations.
- `BudgetManager.php`: Central budget allocation and tracking.
- `BudgetApprovalWorkflow.php`: Budget review/approval process.
- `ExpenseManager.php`: Expense records and disbursements.
- `ExpenseApprovalWorkflow.php`: Expense approvals and checks.
- `PayrollWorkflow.php`: Payroll calculation, approval and disbursement flow.
- `FinancialPeriodAPI.php`: Manage financial periods and transitions.
- `ReportingManager.php`: Finance reporting utilities.

Notes:
- Most managers are expected to wrap DB writes in transactions; full method-level audit not performed here (would require reading each file).
- Next step (if requested): inspect top-changing methods in each file for `beginTransaction`/`commit` usage and produce a change list.
