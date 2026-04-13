<?php /* Accounts & Cash Flow — fragment loaded by school_accountant_dashboard */ ?>
<div id="accountant-accounts-cash" class="dashboard-fragment">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Accounts & Cash Flow</h5>
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
                    <h4 class="mb-0 text-success" id="total-inflow">—</h4>
                    <small class="text-muted">Total Inflow</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-danger" id="total-outflow">—</h4>
                    <small class="text-muted">Total Outflow</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-primary" id="net-cash-flow">—</h4>
                    <small class="text-muted">Net Cash Flow</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-3">
                    <h4 class="mb-0 text-info" id="bank-balance">—</h4>
                    <small class="text-muted">Bank Balance</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-bank me-2"></i>Bank Accounts</h6></div>
                <div class="card-body" id="bankAccountsList">
                    <div class="text-center text-muted py-3 small">Loading...</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Petty Cash</h6></div>
                <div class="card-body">
                    <div class="text-center mb-2">
                        <h3 class="text-success" id="cash-on-hand">—</h3>
                        <small class="text-muted">Cash on Hand</small>
                    </div>
                    <hr class="my-2">
                    <div class="text-center">
                        <h5 id="petty-cash">—</h5>
                        <small class="text-muted">Petty Cash Balance</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
