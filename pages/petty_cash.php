<?php
/**
 * Petty Cash Management Page
 *
 * Features:
 * - 4 KPI stat cards (Current Balance, Expenses This Month, Top-ups This Month, Last Reconciliation)
 * - Filters: search, category, type (expense/topup), date range
 * - Data table with CRUD actions
 * - Record expense/top-up modal
 * - Icon: fa-wallet
 */
?>


<div>
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-wallet me-2 text-success"></i>Petty Cash</h2>
            <p class="text-muted mb-0">Track petty cash expenses, top-ups and reconciliation</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" onclick="PettyCashController.exportCSV()">
                <i class="fas fa-download me-1"></i> Export CSV
            </button>
            <button class="btn btn-success" onclick="PettyCashController.showCreateModal()">
                <i class="fas fa-plus me-1"></i> Record Transaction
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3">
                            <i class="fas fa-wallet fa-lg text-success"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Current Balance</h6>
                            <h3 class="mb-0" id="kpiCurrentBalance">KES 0</h3>
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
                            <i class="fas fa-arrow-down fa-lg text-danger"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Expenses This Month</h6>
                            <h3 class="mb-0" id="kpiExpensesMonth">KES 0</h3>
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
                            <i class="fas fa-arrow-up fa-lg text-primary"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Top-ups This Month</h6>
                            <h3 class="mb-0" id="kpiTopupsMonth">KES 0</h3>
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
                            <i class="fas fa-calendar-check fa-lg text-warning"></i>
                        </div>
                        <div>
                            <h6 class="text-muted mb-1">Last Reconciliation</h6>
                            <h4 class="mb-0" id="kpiLastReconciliation">N/A</h4>
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
                <div class="col-md-3">
                    <label class="form-label small text-muted">Search</label>
                    <input type="text" class="form-control" id="pcSearch" placeholder="Search description, category..." oninput="PettyCashController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Category</label>
                    <select class="form-select" id="pcCategoryFilter" onchange="PettyCashController.filterData()">
                        <option value="">All Categories</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Transport">Transport</option>
                        <option value="Meals">Meals</option>
                        <option value="Repairs">Repairs</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Stationery">Stationery</option>
                        <option value="Cleaning">Cleaning</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Type</label>
                    <select class="form-select" id="pcTypeFilter" onchange="PettyCashController.filterData()">
                        <option value="">All Types</option>
                        <option value="Expense">Expense</option>
                        <option value="Top-up">Top-up</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Date From</label>
                    <input type="date" class="form-control" id="pcDateFrom" onchange="PettyCashController.filterData()">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted">Date To</label>
                    <input type="date" class="form-control" id="pcDateTo" onchange="PettyCashController.filterData()">
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" onclick="PettyCashController.clearFilters()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="pettyCashTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th class="text-center">Type</th>
                            <th class="text-end">Amount (KES)</th>
                            <th>Received By</th>
                            <th>Authorized By</th>
                            <th class="text-center" width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pettyCashTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4">
                                <div class="spinner-border text-success spinner-border-sm me-2" role="status"></div>
                                Loading petty cash records...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small" id="pcTableInfo">Showing 0 records</span>
            <nav>
                <ul class="pagination pagination-sm mb-0" id="pcPagination"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- Record Expense / Top-up Modal -->
<div class="modal fade" id="pettyCashModal" tabindex="-1" aria-labelledby="pettyCashModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="pettyCashModalLabel">
                    <i class="fas fa-wallet me-2"></i> Record Petty Cash Transaction
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="pettyCashForm">
                    <input type="hidden" id="pc_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="pc_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="pc_type" required>
                                <option value="">Select Type</option>
                                <option value="Expense">Expense</option>
                                <option value="Top-up">Top-up</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pc_description" placeholder="Brief description" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="pc_category" required>
                                <option value="">Select Category</option>
                                <option value="Office Supplies">Office Supplies</option>
                                <option value="Transport">Transport</option>
                                <option value="Meals">Meals</option>
                                <option value="Repairs">Repairs</option>
                                <option value="Utilities">Utilities</option>
                                <option value="Stationery">Stationery</option>
                                <option value="Cleaning">Cleaning</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pc_amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Received By</label>
                            <input type="text" class="form-control" id="pc_received_by" placeholder="Name of recipient">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Authorized By <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="pc_authorized_by" placeholder="Authorizing officer" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="pc_notes" rows="2" placeholder="Any additional notes"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="PettyCashController.saveRecord()">
                    <i class="fas fa-save me-1"></i> Save Record
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/petty_cash.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof PettyCashController !== 'undefined') {
            PettyCashController.init();
        }
    });
</script>
