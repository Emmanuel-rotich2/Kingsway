<?php
/**
 * Bank Transactions Management Page
 *
 * Features:
 * - 4 KPI stat cards (Total Transactions, Credits, Debits, Unreconciled)
 * - Filters: search, account, type, reconciled status, date range
 * - Data table with CRUD actions
 * - Add transaction modal
 * - Icon: fa-exchange-alt
 */
?>


<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-exchange-alt me-2 text-info"></i>Bank Transactions</h2>
            <p class="text-muted mb-0">Record, track and reconcile bank transactions</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="BankTransactionsController.exportCSV()">
                <i class="fas fa-download me-1"></i> Export CSV
            </button>
            <button class="btn btn-info text-white" onclick="BankTransactionsController.showCreateModal()">
                <i class="fas fa-plus me-1"></i> Add Transaction
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-info bg-opacity-10 p-3 me-3">
                            <i class="fas fa-exchange-alt fa-lg text-info"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Transactions</h6>
                            <h3 class="mb-0" id="kpiTotalTransactions">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-arrow-down fa-lg text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Credits (KES)</h6>
                            <h3 class="mb-0" id="kpiCredits">KES 0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-danger bg-opacity-10 p-3 me-3">
                            <i class="fas fa-arrow-up fa-lg text-danger"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Debits (KES)</h6>
                            <h3 class="mb-0" id="kpiDebits">KES 0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3">
                            <i class="fas fa-exclamation-triangle fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Unreconciled</h6>
                            <h3 class="mb-0" id="kpiUnreconciled">0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Row -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" id="btSearch" placeholder="Search reference, description..." oninput="BankTransactionsController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Account</label>
                    <select class="form-select" id="btAccountFilter" onchange="BankTransactionsController.filterData()">
                        <option value="">All Accounts</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Type</label>
                    <select class="form-select" id="btTypeFilter" onchange="BankTransactionsController.filterData()">
                        <option value="">All Types</option>
                        <option value="Credit">Credit</option>
                        <option value="Debit">Debit</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Reconciled</label>
                    <select class="form-select" id="btReconciledFilter" onchange="BankTransactionsController.filterData()">
                        <option value="">All</option>
                        <option value="Yes">Reconciled</option>
                        <option value="No">Unreconciled</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted">From</label>
                    <input type="date" class="form-control" id="btDateFrom" onchange="BankTransactionsController.filterData()">
                </div>
                <div class="col-md-1">
                    <label class="form-label small text-muted">To</label>
                    <input type="date" class="form-control" id="btDateTo" onchange="BankTransactionsController.filterData()">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="BankTransactionsController.clearFilters()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="bankTransactionsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Date</th>
                            <th>Account</th>
                            <th>Reference</th>
                            <th>Description</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Amount (KES)</th>
                            <th class="text-end">Balance After (KES)</th>
                            <th class="text-center">Reconciled</th>
                            <th class="text-center" width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bankTransactionsTableBody">
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="spinner-border text-info spinner-border-sm me-2" role="status"></div>
                                Loading bank transactions...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small" id="btTableInfo">Showing 0 records</span>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="btPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal fade" id="bankTransactionModal" tabindex="-1" aria-labelledby="bankTransactionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="bankTransactionModalLabel">
                    <i class="fas fa-exchange-alt me-2"></i> Add Bank Transaction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bankTransactionForm">
                    <input type="hidden" id="bt_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Account <span class="text-danger">*</span></label>
                            <select class="form-select" id="bt_account" required>
                                <option value="">Select Account</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="bt_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference</label>
                            <input type="text" class="form-control" id="bt_reference" placeholder="Transaction reference">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="bt_type" required>
                                <option value="">Select Type</option>
                                <option value="Credit">Credit</option>
                                <option value="Debit">Debit</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="bt_description" placeholder="Transaction description" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="bt_amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info text-white" onclick="BankTransactionsController.saveTransaction()">
                    <i class="fas fa-save me-1"></i> Save Transaction
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/bank_transactions.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof BankTransactionsController !== 'undefined') {
            BankTransactionsController.init();
        }
    });
</script>
