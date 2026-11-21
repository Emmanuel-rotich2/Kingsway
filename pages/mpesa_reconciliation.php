<?php
/**
 * M-Pesa Reconciliation Page
 * Purpose: Match unmatched M-Pesa transactions to student fee accounts
 * Features:
 * - List unmatched M-Pesa transactions
 * - Search students by phone number for matching
 * - Reconcile and allocate to student fees
 * - View reconciliation history
 */
?>
<div>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-phone me-2 text-success"></i>M-Pesa Reconciliation</h4>
            <p class="text-muted mb-0">Match unmatched M-Pesa transactions to student fee accounts</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success" data-kw-onclick="MpesaReconciliationController.refresh()">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h2 id="kpiUnmatched">--</h2>
                    <p class="mb-0">Unmatched Transactions</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2 id="kpiTotalAmount">KES 0</h2>
                    <p class="mb-0">Total Unmatched Amount</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h2 id="kpiReconciledToday">--</h2>
                    <p class="mb-0">Reconciled Today</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" id="searchTxn" placeholder="Search by M-Pesa code, phone, or name...">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="filterDateFrom">
                </div>
                <div class="col-md-3">
                    <input type="date" class="form-control" id="filterDateTo">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" data-kw-onclick="MpesaReconciliationController.clearFilters()">
                        <i class="bi bi-x-lg"></i> Clear
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="reconTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>M-Pesa Code</th>
                            <th>Date</th>
                            <th>Payer Name</th>
                            <th>Phone</th>
                            <th class="text-end">Amount (KES)</th>
                            <th>Bill Ref</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-success me-2"></div>Loading unmatched transactions...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/pages/mpesa_reconciliation.js'); ?>
