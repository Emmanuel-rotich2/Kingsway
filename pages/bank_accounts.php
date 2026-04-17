<?php
/**
 * Bank Accounts Management Page
 *
 * Features:
 * - 4 KPI stat cards (Total Accounts, Combined Balance, Active Accounts, Last Reconciled)
 * - Filters: search, bank, type, status
 * - Data table with CRUD actions
 * - Add/Edit account modal
 * - Icon: fa-university
 */
?>


<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-university me-2 text-dark"></i>Bank Accounts</h2>
            <p class="text-muted mb-0">Manage school bank accounts, balances and reconciliation</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="BankAccountsController.exportCSV()">
                <i class="fas fa-download me-1"></i> Export CSV
            </button>
            <button class="btn btn-dark" onclick="BankAccountsController.showCreateModal()">
                <i class="fas fa-plus me-1"></i> Add Account
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-dark bg-opacity-10 p-3 me-3">
                            <i class="fas fa-university fa-lg text-dark"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Total Accounts</h6>
                            <h3 class="mb-0" id="kpiTotalAccounts">0</h3>
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
                            <i class="fas fa-coins fa-lg text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Combined Balance</h6>
                            <h3 class="mb-0" id="kpiCombinedBalance">KES 0</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3">
                            <i class="fas fa-check-double fa-lg text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Active Accounts</h6>
                            <h3 class="mb-0" id="kpiActiveAccounts">0</h3>
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
                            <i class="fas fa-calendar-alt fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Last Reconciled</h6>
                            <h4 class="mb-0" id="kpiLastReconciled">N/A</h4>
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
                <div class="col-md-4">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" id="baSearch" placeholder="Search bank, account name, number..." oninput="BankAccountsController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Bank</label>
                    <select class="form-select" id="baBankFilter" onchange="BankAccountsController.filterData()">
                        <option value="">All Banks</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Type</label>
                    <select class="form-select" id="baTypeFilter" onchange="BankAccountsController.filterData()">
                        <option value="">All Types</option>
                        <option value="Current">Current</option>
                        <option value="Savings">Savings</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" id="baStatusFilter" onchange="BankAccountsController.filterData()">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="BankAccountsController.clearFilters()">
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
                <table class="table table-hover align-middle mb-0" id="bankAccountsTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Bank Name</th>
                            <th>Account Name</th>
                            <th>Account Number</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Balance (KES)</th>
                            <th class="text-center">Status</th>
                            <th>Last Transaction</th>
                            <th class="text-center" width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bankAccountsTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border text-dark spinner-border-sm me-2" role="status"></div>
                                Loading bank accounts...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small" id="baTableInfo">Showing 0 records</span>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="baPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Add/Edit Account Modal -->
<div class="modal fade" id="bankAccountModal" tabindex="-1" aria-labelledby="bankAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="bankAccountModalLabel">
                    <i class="fas fa-university me-2"></i> Add Bank Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bankAccountForm">
                    <input type="hidden" id="ba_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Bank Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ba_bank_name" placeholder="e.g. KCB, Equity, Co-op" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Branch</label>
                            <input type="text" class="form-control" id="ba_branch" placeholder="Branch name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ba_account_name" placeholder="Account holder name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ba_account_number" placeholder="Account number" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Account Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="ba_type" required>
                                <option value="">Select Type</option>
                                <option value="Current">Current</option>
                                <option value="Savings">Savings</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Opening Balance (KES)</label>
                            <input type="number" class="form-control" id="ba_opening_balance" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-dark" onclick="BankAccountsController.saveAccount()">
                    <i class="fas fa-save me-1"></i> Save Account
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/bank_accounts.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof BankAccountsController !== 'undefined') {
            BankAccountsController.init();
        }
    });
</script>
