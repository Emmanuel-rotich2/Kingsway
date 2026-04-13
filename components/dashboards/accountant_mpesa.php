<?php /* M-Pesa & Mobile Money — fragment loaded by school_accountant_dashboard */ ?>
<div id="accountant-mpesa-dashboard" class="dashboard-fragment">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-phone me-2 text-success"></i>M-Pesa & Mobile Money</h5>
        <div>
            <input type="date" class="form-control form-control-sm d-inline-block w-auto me-2" id="filter-date">
            <button class="btn btn-sm btn-outline-warning me-1" id="load-unmatched"><i class="bi bi-exclamation-triangle me-1"></i>Unmatched</button>
            <small class="text-muted me-2" id="last-updated"></small>
            <button class="btn btn-sm btn-outline-secondary me-1" id="refreshDashboard"><i class="bi bi-arrow-clockwise"></i></button>
            <button class="btn btn-sm btn-outline-success me-1" id="exportDashboard"><i class="bi bi-download"></i> Export</button>
            <button class="btn btn-sm btn-outline-secondary" id="printDashboard"><i class="bi bi-printer"></i></button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success" id="mpesa-total-collected">—</h4>
                    <small class="text-muted">Total Collected</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary" id="mpesa-transaction-count">—</h4>
                    <small class="text-muted">Transactions</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-warning" id="mpesa-unmatched-count">—</h4>
                    <small class="text-muted">Unmatched</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-info" id="mpesa-today-total">—</h4>
                    <small class="text-muted">Today's Total</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2 text-warning"></i>Unmatched Payments</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Code</th><th>Phone</th><th>Amount</th><th>Narration</th><th>Action</th></tr></thead>
                    <tbody id="tbody_unmatched_payments"><tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
