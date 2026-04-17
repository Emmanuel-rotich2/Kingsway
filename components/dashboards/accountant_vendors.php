<?php /* Vendors & Suppliers — fragment loaded by school_accountant_dashboard */ ?>
<div id="accountant-vendors-dashboard" class="dashboard-fragment">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-shop me-2 text-info"></i>Vendors & Suppliers</h5>
        <div>
            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filter-vendor-status">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button class="btn btn-sm btn-primary me-2" id="add-vendor"><i class="bi bi-plus me-1"></i>Add Vendor</button>
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
                    <h4 class="mb-0 text-primary" id="total-vendors">—</h4>
                    <small class="text-muted">Total Vendors</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success" id="active-vendors">—</h4>
                    <small class="text-muted">Active Vendors</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-warning" id="pending-vendor-payments">—</h4>
                    <small class="text-muted">Pending Payments</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger" id="overdue-invoices">—</h4>
                    <small class="text-muted">Overdue Invoices</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-table me-2"></i>Vendor List</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Name</th><th>Contact</th><th>Category</th><th>Outstanding</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="tbody_vendors"><tr><td colspan="7" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
