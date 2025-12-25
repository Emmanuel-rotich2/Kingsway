<?php
/**
 * Manage Finance Page
 * HTML structure only - logic will be in js/pages/finance.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-coins"></i> Finance Management</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="addTransactionBtn" data-permission="finance_create">
                    <i class="bi bi-plus-circle"></i> Add Transaction
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportFinanceBtn">
                    <i class="bi bi-download"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Finance Overview Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Revenue</h6>
                        <h3 class="text-success mb-0" id="totalRevenue">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expenses</h6>
                        <h3 class="text-danger mb-0" id="totalExpenses">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Net Balance</h6>
                        <h3 class="text-primary mb-0" id="netBalance">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Pending</h6>
                        <h3 class="text-warning mb-0" id="pendingTransactions">0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="row mb-3">
            <div class="col-md-3">
                <input type="text" class="form-control" id="financeSearch" placeholder="Search transactions...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="transactionTypeFilter">
                    <option value="">All Types</option>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateFromFilter">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="dateToFilter">
            </div>
            <div class="col-md-1">
                <button class="btn btn-secondary w-100" id="clearFiltersBtn">Clear</button>
            </div>
        </div>

        <!-- Finance Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="financeTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
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
            <ul class="pagination justify-content-center" id="financePagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Transaction Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="transactionForm">
                    <input type="hidden" id="transaction_id">

                    <div class="mb-3">
                        <label class="form-label">Type*</label>
                        <select class="form-select" id="transaction_type" required>
                            <option value="">Select Type</option>
                            <option value="income">Income</option>
                            <option value="expense">Expense</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Category*</label>
                        <select class="form-select" id="transaction_category" required></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount (KES)*</label>
                        <input type="number" class="form-control" id="transaction_amount" step="0.01" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Date*</label>
                        <input type="date" class="form-control" id="transaction_date" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description*</label>
                        <textarea class="form-control" id="transaction_description" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Receipt/Document</label>
                        <input type="file" class="form-control" id="transaction_document">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveTransactionBtn">Save Transaction</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize finance management when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement financeManagementController in js/pages/finance.js
        console.log('Finance Management page loaded');
    });
</script>