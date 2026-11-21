<?php
/**
 * Bank Accounts Management Page
 *
 * Features:
 * - 4 KPI stat cards (Total Accounts, Combined Balance, Active Accounts, Last Reconciled)
 * - Filters: search, bank and status
 * - Read-only data table of normalized school bank accounts
 * - Icon: fa-university
 */
?>


<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="bi bi-bank me-2 text-dark"></i>School Bank Accounts</h2>
            <p class="text-muted mb-0">View the school bank accounts that have been added and their balances</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" data-kw-onclick="BankAccountsController.exportCSV()">
                <i class="bi bi-download me-1"></i> Export CSV
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
                            <i class="bi bi-bank fa-lg text-dark"></i>
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
                            <i class="bi bi-coin fa-lg text-success"></i>
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
                            <i class="bi bi-check-lg-double fa-lg text-primary"></i>
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
                            <i class="bi bi-calendar-alt fa-lg text-warning"></i>
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
                    <input type="text" class="form-control" id="baSearch" placeholder="Search bank, account name, number..." data-kw-oninput="BankAccountsController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Bank</label>
                    <select class="form-select" id="baBankFilter" data-kw-onchange="BankAccountsController.filterData()">
                        <option value="">All Banks</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Status</label>
                    <select class="form-select" id="baStatusFilter" data-kw-onchange="BankAccountsController.filterData()">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Pending_verification">Pending verification</option>
                        <option value="Suspended">Suspended</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-kw-onclick="BankAccountsController.clearFilters()">
                        <i class="bi bi-x-lg me-1"></i> Clear
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
                            <th scope="col">Bank Name</th>
                            <th scope="col">Account Name</th>
                            <th scope="col">Account Number</th>
                            <th class="text-end">Balance (KES)</th>
                            <th class="text-center">Status</th>
                            <th scope="col">Last Transaction</th>
                            <th class="text-center" width="100">View</th>
                        </tr>
                    </thead>
                    <tbody id="bankAccountsTableBody">
                        <tr>
                            <td colspan="8" class="text-center py-4">
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

<?php asset_script($appBase, 'js/pages/bank_accounts.js'); ?>
