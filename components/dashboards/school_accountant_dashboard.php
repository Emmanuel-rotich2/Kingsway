
<?php $rootId = 'accountantDashboard'; ?>
<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-finance" id="accountantDashboard" data-dashboard-layout="finance-classic-workbench">

    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <nav class="dashboard-action-strip mb-4" aria-label="Finance quick actions">
        <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=manage_payments"><i class="bi bi-receipt"></i><span>Payment register</span></a>
        <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=mpesa_reconciliation"><i class="bi bi-arrow-repeat"></i><span>M-Pesa reconciliation</span></a>
        <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=kcb_disbursement_reconciliation"><i class="bi bi-bank"></i><span>KCB transfer queue</span></a>
        <a href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/home.php?route=finance_reports"><i class="bi bi-graph-up-arrow"></i><span>Financial reports</span></a>
    </nav>

    <div id="accountantDashboardState" hidden></div>

    <div class="row g-3 mb-4 finance-classic-kpis">
        <div class="col-md-4">
            <div class="dash-stat dsc-green">
                <i class="bi bi-cash-coin dash-stat-icon"></i>
                <div class="dash-stat-value" id="accCollected">&mdash;</div>
                <div class="dash-stat-label">Fees Collected</div>
                <div class="dash-stat-sub" id="accCollectedSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-red">
                <i class="bi bi-exclamation-circle dash-stat-icon"></i>
                <div class="dash-stat-value" id="accOutstanding">&mdash;</div>
                <div class="dash-stat-label">Outstanding Balances</div>
                <div class="dash-stat-sub" id="accOutstandingSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-blue">
                <i class="bi bi-phone dash-stat-icon"></i>
                <div class="dash-stat-value" id="accMpesa">&mdash;</div>
                <div class="dash-stat-label">M-Pesa Collections</div>
                <div class="dash-stat-sub" id="accMpesaSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-indigo">
                <i class="bi bi-bank dash-stat-icon"></i>
                <div class="dash-stat-value" id="accBank">&mdash;</div>
                <div class="dash-stat-label">Bank Collections</div>
                <div class="dash-stat-sub" id="accBankSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-teal">
                <i class="bi bi-cash dash-stat-icon"></i>
                <div class="dash-stat-value" id="accCash">&mdash;</div>
                <div class="dash-stat-label">Cash Collections</div>
                <div class="dash-stat-sub" id="accCashSub">&mdash;</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dash-stat dsc-orange">
                <i class="bi bi-exclamation-triangle dash-stat-icon"></i>
                <div class="dash-stat-value" id="accUnreconciled">&mdash;</div>
                <div class="dash-stat-label">Unreconciled</div>
                <div class="dash-stat-sub" id="accUnreconciledSub">&mdash;</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-primary">
        <div class="col-lg-6 finance-collection-position">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Collection Trend</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accCollectionChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6 finance-method-position">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-success"></i>Payment Methods</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 finance-classic-level">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-bar-chart me-2 text-info"></i>Collections by Period</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accLevelChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-analysis">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-pie-chart-fill me-2 text-success"></i>Collected vs Outstanding</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accFeeStatusChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-bar-chart-steps me-2 text-primary"></i>Recent Payment Amount Distribution</h6>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accPaymentHistogram"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-ledgers">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Recent Payments</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Reference</th>
                                    <th>Student</th>
                                    <th>Class</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount</th>
                                    <th class="pe-3">Date</th>
                                </tr>
                            </thead>
                            <tbody id="accTransactionsBody">
                                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-phone me-2 text-warning"></i>Unreconciled M-Pesa</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Phone</th>
                                    <th class="text-end">Amount</th>
                                    <th>Date</th>
                                    <th class="pe-3">Status</th>
                                </tr>
                            </thead>
                            <tbody id="accUnreconciledBody">
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row g-3 mb-4 finance-classic-defaulters">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-person-x me-2 text-danger"></i>Top Defaulters</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Student</th>
                                    <th>Class</th>
                                    <th class="text-end">Amount</th>
                                    <th class="pe-3">Days</th>
                                </tr>
                            </thead>
                            <tbody id="accDefaultersBody">
                                <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Loading&hellip;</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-operations">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-speedometer me-2 text-info"></i>Budget Utilization</h6>
                </div>
                <div class="card-body">
                    <div id="accBudgetUtil">
                        <p class="text-muted small mb-0">No budget data available.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Expense Position</h6>
                </div>
                <div class="card-body">
                    <div id="accExpenses">
                        <p class="text-muted small mb-0">Loading expense position&hellip;</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-check2-circle me-2 text-warning"></i>Reconciliation Position</h6>
                </div>
                <div class="card-body">
                    <div id="accReconciliation">
                        <p class="text-muted small mb-0">Loading reconciliation position&hellip;</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/school_accountant_dashboard.js'); ?>
