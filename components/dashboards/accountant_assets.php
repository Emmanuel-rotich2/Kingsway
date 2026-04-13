<?php /* Assets & Inventory Finance — fragment loaded by school_accountant_dashboard */ ?>
<div id="accountant-assets-dashboard" class="dashboard-fragment">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-building me-2 text-success"></i>Assets & Inventory</h5>
        <div>
            <select class="form-select form-select-sm d-inline-block w-auto me-2" id="filter-category">
                <option value="">All Categories</option>
                <option value="equipment">Equipment</option>
                <option value="furniture">Furniture</option>
                <option value="vehicles">Vehicles</option>
                <option value="electronics">Electronics</option>
            </select>
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
                    <h4 class="mb-0 text-primary" id="total-assets">—</h4>
                    <small class="text-muted">Total Assets</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success" id="total-asset-value">—</h4>
                    <small class="text-muted">Total Value</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-info" id="active-assets">—</h4>
                    <small class="text-muted">Active Assets</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-warning" id="annual-depreciation">—</h4>
                    <small class="text-muted">Annual Depreciation</small>
                </div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <span class="text-muted small">Net Book Value: </span>
        <strong id="net-book-value">—</strong>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-table me-2"></i>Asset Register</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Asset</th><th>Category</th><th>Purchase Date</th><th>Cost</th><th>Net Value</th><th>Status</th></tr></thead>
                    <tbody id="tbody_assets"><tr><td colspan="7" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
