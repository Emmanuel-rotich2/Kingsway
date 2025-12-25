<?php
/**
 * Manage Expenses Page
 * HTML structure only - logic will be in js/pages/expenses.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-danger text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-receipt"></i> Expense Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addExpenseBtn" data-permission="expenses_create">
                    <i class="bi bi-plus-circle"></i> Add Expense
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportExpensesBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Expense Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expenses</h6>
                        <h3 class="text-danger mb-0" id="totalExpenses">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending Approval</h6>
                        <h3 class="text-warning mb-0" id="pendingExpenses">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Approved</h6>
                        <h3 class="text-success mb-0" id="approvedExpenses">0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">This Month</h6>
                        <h3 class="text-info mb-0" id="monthExpenses">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-2">
                <input type="text" class="form-control" id="expenseSearch" placeholder="Search...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                    <option value="salary">Salary</option>
                    <option value="utilities">Utilities</option>
                    <option value="supplies">Supplies</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="transport">Transport</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateTo">
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary w-100" id="clearFilters">Clear</button>
            </div>
        </div>

        <!-- Expenses Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="expensesTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                        <th>Requested By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="expensesPagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Expense Modal -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Expense Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="expenseForm">
                    <input type="hidden" id="expense_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category*</label>
                            <select class="form-select" id="expense_category" required>
                                <option value="">Select Category</option>
                                <option value="salary">Salary</option>
                                <option value="utilities">Utilities</option>
                                <option value="supplies">Supplies</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="transport">Transport</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Amount (KES)*</label>
                            <input type="number" class="form-control" id="expense_amount" step="0.01" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date*</label>
                            <input type="date" class="form-control" id="expense_date" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method*</label>
                            <select class="form-select" id="payment_method" required>
                                <option value="">Select Method</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description*</label>
                        <textarea class="form-control" id="expense_description" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Receipt/Invoice</label>
                        <input type="file" class="form-control" id="expense_document">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveExpenseBtn">Save Expense</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize expenses management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement expensesManagementController in js/pages/expenses.js
        console.log('Expense Management page loaded');
    });
</script>