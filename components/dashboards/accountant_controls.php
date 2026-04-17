<?php /* Financial Controls & Audit — fragment loaded by school_accountant_dashboard */ ?>
<div id="accountant-controls-dashboard" class="dashboard-fragment">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-shield-check me-2 text-primary"></i>Financial Controls & Audit</h5>
        <div>
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
                    <h4 class="mb-0 text-warning" id="pending-approvals">—</h4>
                    <small class="text-muted">Pending Approvals</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-info" id="budget-variance">—</h4>
                    <small class="text-muted">Budget Variance</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-success" id="compliance-rate">—</h4>
                    <small class="text-muted">Compliance Rate</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger" id="open-exceptions">—</h4>
                    <small class="text-muted">Open Exceptions</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-journal-text me-2"></i>Audit Log</h6></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>#</th><th>Date</th><th>Action</th><th>User</th><th>Status</th></tr></thead>
                    <tbody id="tbody_audit_log"><tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
