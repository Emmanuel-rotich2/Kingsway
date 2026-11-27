<?php
$rootId = 'accountantDashboard';
$periods = [
    ['key' => 'today', 'label' => 'Today'],
    ['key' => 'week', 'label' => 'This Week'],
    ['key' => 'month', 'label' => 'This Month'],
    ['key' => 'term', 'label' => 'This Term'],
    ['key' => 'year', 'label' => 'Current Academic Year'],
];
$default = 'year';
$appBase = htmlspecialchars($appBase ?? '', ENT_QUOTES, 'UTF-8');
?>
<div class="container-fluid py-4 role-dashboard dashboard-surface dashboard-finance" id="accountantDashboard" data-dashboard-layout="finance-classic-workbench">

    <?php require __DIR__ . '/partials/period_selector.php'; ?>

    <div class="dash-meta-bar finance-meta-bar">
        <span class="finance-meta-scope">Scope: <strong id="accountantDashboardScope">Current academic year</strong></span>
        <span class="finance-meta-source" id="accDataSource">Sources: learner fee ledger, confirmed payments, posted journal</span>
        <span class="finance-meta-updated">As of <strong id="accountantDashboardLastUpdated">&mdash;</strong> <span id="accDataAsOf">(server &mdash;)</span></span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="accountantDashboardRefresh" title="Refresh dashboard data">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Refresh
        </button>
    </div>

    <div id="accountantDashboardState" hidden></div>
    <span class="visually-hidden" id="accTableStatus" role="status" aria-live="polite"></span>

    <nav class="dashboard-action-strip mb-4" aria-label="Finance quick actions">
        <a href="<?= $appBase ?>/home.php?route=manage_payments"><i class="bi bi-receipt"></i><span>Payment register</span></a>
        <a href="<?= $appBase ?>/home.php?route=mpesa_reconciliation"><i class="bi bi-arrow-repeat"></i><span>M-Pesa reconciliation</span></a>
        <a href="<?= $appBase ?>/home.php?route=transaction_approvals"><i class="bi bi-check2-square"></i><span>Approvals queue</span></a>
        <a href="<?= $appBase ?>/home.php?route=finance_reports"><i class="bi bi-graph-up-arrow"></i><span>Financial reports</span></a>
    </nav>

    <div class="finance-workbench">
    <?php
    require_once __DIR__ . '/_dashboard_tabs.php';
    kw_dashboard_tabs_open('accountant');
    ?>

    <!-- NEEDS ATTENTION: the accountant's work queue, above every chart. -->
    <section class="finance-attention mb-4" aria-labelledby="accAttentionTitle">
        <div class="finance-attention-head">
            <h2 class="h6 mb-0" id="accAttentionTitle"><i class="bi bi-bell me-2" aria-hidden="true"></i>Needs attention</h2>
            <span class="small text-muted">Open items that change today's work</span>
        </div>
        <div class="finance-attention-grid" id="accNeedsAttention">
            <p class="text-muted small mb-0">Loading the action queue&hellip;</p>
        </div>
    </section>

    <!-- P0: one definition per KPI, all in the selected academic year. -->
    <div class="row g-3 mb-4 finance-classic-kpis">
        <div class="col-md-4"><div class="dash-stat dsc-blue">
            <i class="bi bi-file-earmark-text dash-stat-icon"></i>
            <div class="dash-stat-value" id="accBilled">&mdash;</div>
            <div class="dash-stat-label">Billed</div>
            <div class="dash-stat-sub" id="accBilledSub">&mdash;</div>
        </div></div>
        <div class="col-md-4"><div class="dash-stat dsc-green">
            <i class="bi bi-check2-circle dash-stat-icon"></i>
            <div class="dash-stat-value" id="accAllocated">&mdash;</div>
            <div class="dash-stat-label">Allocated to ledgers</div>
            <div class="dash-stat-sub" id="accAllocatedSub">&mdash;</div>
        </div></div>
        <div class="col-md-4"><div class="dash-stat dsc-teal">
            <i class="bi bi-cash-coin dash-stat-icon"></i>
            <div class="dash-stat-value" id="accCashReceived">&mdash;</div>
            <div class="dash-stat-label">Cash received</div>
            <div class="dash-stat-sub" id="accCashReceivedSub">&mdash;</div>
        </div></div>
        <div class="col-md-4"><div class="dash-stat dsc-red">
            <i class="bi bi-exclamation-circle dash-stat-icon"></i>
            <div class="dash-stat-value" id="accOutstanding">&mdash;</div>
            <div class="dash-stat-label">Outstanding</div>
            <div class="dash-stat-sub" id="accOutstandingSub">&mdash;</div>
        </div></div>
        <div class="col-md-4"><div class="dash-stat dsc-indigo">
            <i class="bi bi-wallet2 dash-stat-icon"></i>
            <div class="dash-stat-value" id="accCreditBalance">&mdash;</div>
            <div class="dash-stat-label">Credit balance</div>
            <div class="dash-stat-sub" id="accCreditBalanceSub">&mdash;</div>
        </div></div>
        <div class="col-md-4"><div class="dash-stat dsc-orange">
            <i class="bi bi-arrow-repeat dash-stat-icon"></i>
            <div class="dash-stat-value" id="accUnreconciled">&mdash;</div>
            <div class="dash-stat-label">Unreconciled M-Pesa</div>
            <div class="dash-stat-sub" id="accUnreconciledSub">&mdash;</div>
        </div></div>
    </div>
    <p class="finance-definition-note" id="accDefinitionNote"></p>

    <!-- P2: only charts that explain a decision. -->
    <div class="row g-3 mb-4 finance-classic-primary">
        <div class="col-lg-7 finance-collection-position">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Daily collections vs run-rate target</h6>
                    <small class="text-muted">Target = current-term billed obligations spread over 30 days</small>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accCollectionChart"></canvas>
                    <div id="accDailyTargetNote" class="finance-chart-note"></div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 finance-method-position">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0">
                    <h6 class="mb-0"><i class="bi bi-pie-chart me-2 text-success"></i>Receipts by payment method</h6>
                    <small class="text-muted" id="accMethodNote">Confirmed payment rows only</small>
                </div>
                <div class="card-body" style="position:relative;height:280px;">
                    <canvas id="accMethodChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-analysis">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-bar-chart-steps me-2 text-info"></i>Receivables ageing</h6>
                    <label class="small text-muted mb-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Filter ageing rows" data-table-search="accAgeingBody" aria-label="Filter receivables ageing rows">
                    </label>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 finance-table" id="accAgeingTable" data-export-title="Receivables ageing" data-export-module="finance">
                            <caption class="visually-hidden">Receivables ageing by number of days overdue</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accAgeingTable" command="--sort" data-sort-key="bucket">Ageing bucket</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accAgeingTable" command="--sort" data-sort-key="student_count">Learners</button></th>
                                    <th scope="col" class="text-end pe-3" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accAgeingTable" command="--sort" data-sort-key="outstanding">Outstanding</button></th>
                                </tr>
                            </thead>
                            <tbody id="accAgeingBody"><tr><td colspan="3" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-mortarboard me-2 text-primary"></i>Collection rate by class</h6>
                    <label class="small text-muted mb-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Filter classes" data-table-search="accClassBody" aria-label="Filter class collection rows">
                    </label>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 finance-table" id="accClassTable" data-export-title="Collection rate by class" data-export-module="finance">
                            <caption class="visually-hidden">Fee collection rate by class</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accClassTable" command="--sort" data-sort-key="class_name">Class</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accClassTable" command="--sort" data-sort-key="student_count">Learners</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accClassTable" command="--sort" data-sort-key="total_due">Billed</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accClassTable" command="--sort" data-sort-key="total_paid">Allocated</button></th>
                                    <th scope="col" class="text-end pe-3" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accClassTable" command="--sort" data-sort-key="collection_rate">Rate</button></th>
                                </tr>
                            </thead>
                            <tbody id="accClassBody"><tr><td colspan="5" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-ledgers">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-phone me-2 text-warning"></i>Unreconciled M-Pesa</h6>
                    <label class="small text-muted mb-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Filter M-Pesa rows" data-table-search="accUnreconciledBody" aria-label="Filter unreconciled M-Pesa rows">
                    </label>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 finance-table" id="accUnreconciledTable" data-export-title="Unreconciled M-Pesa payments" data-export-module="finance">
                            <caption class="visually-hidden">Unreconciled M-Pesa payments with triage actions</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accUnreconciledTable" command="--sort" data-sort-key="phone_number">Phone</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accUnreconciledTable" command="--sort" data-sort-key="amount">Amount</button></th>
                                    <th scope="col" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accUnreconciledTable" command="--sort" data-sort-key="age_days">Age</button></th>
                                    <th scope="col" data-type="date"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accUnreconciledTable" command="--sort" data-sort-key="transaction_date">Date</button></th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody id="accUnreconciledBody"><tr><td colspan="6" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-person-x me-2 text-danger"></i>Top defaulters</h6>
                    <label class="small text-muted mb-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Filter learners" data-table-search="accDefaultersBody" aria-label="Filter defaulter rows">
                    </label>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 finance-table" id="accDefaultersTable" data-export-title="Top fee defaulters" data-export-module="finance">
                            <caption class="visually-hidden">Learners with the largest outstanding fee balances</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accDefaultersTable" command="--sort" data-sort-key="student_name">Learner</button></th>
                                    <th scope="col"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accDefaultersTable" command="--sort" data-sort-key="class_name">Class</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accDefaultersTable" command="--sort" data-sort-key="amount_owed">Amount</button></th>
                                    <th scope="col" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accDefaultersTable" command="--sort" data-sort-key="days_overdue">Ageing</button></th>
                                    <th scope="col" class="pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody id="accDefaultersBody"><tr><td colspan="5" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-defaulters">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Recent payments</h6>
                    <label class="small text-muted mb-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Filter payments" data-table-search="accTransactionsBody" aria-label="Filter recent payment rows">
                    </label>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 finance-table" id="accTransactionsTable" data-export-title="Recent payments" data-export-module="finance">
                            <caption class="visually-hidden">Recently confirmed fee payments</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accTransactionsTable" command="--sort" data-sort-key="reference">Reference</button></th>
                                    <th scope="col"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accTransactionsTable" command="--sort" data-sort-key="student_name">Learner</button></th>
                                    <th scope="col"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accTransactionsTable" command="--sort" data-sort-key="class_name">Class</button></th>
                                    <th scope="col"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accTransactionsTable" command="--sort" data-sort-key="payment_method">Method</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accTransactionsTable" command="--sort" data-sort-key="amount">Amount</button></th>
                                    <th scope="col" class="pe-3" data-type="date"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accTransactionsTable" command="--sort" data-sort-key="payment_date">Date</button></th>
                                </tr>
                            </thead>
                            <tbody id="accTransactionsBody"><tr><td colspan="6" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 dash-card">
                <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-hourglass-split me-2 text-warning"></i>Expenses awaiting review</h6>
                    <label class="small text-muted mb-0">
                        <input type="search" class="form-control form-control-sm" placeholder="Filter expenses" data-table-search="accPendingExpensesBody" aria-label="Filter pending expense rows">
                    </label>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 finance-table" id="accPendingExpensesTable" data-export-title="Expenses awaiting review" data-export-module="finance">
                            <caption class="visually-hidden">Expenses awaiting review by an authorized approver</caption>
                            <thead class="table-light">
                                <tr>
                                    <th scope="col" class="ps-3"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accPendingExpensesTable" command="--sort" data-sort-key="expense_number">Number</button></th>
                                    <th scope="col"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accPendingExpensesTable" command="--sort" data-sort-key="description">Description</button></th>
                                    <th scope="col" class="text-end" data-type="number"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accPendingExpensesTable" command="--sort" data-sort-key="amount">Amount</button></th>
                                    <th scope="col" class="pe-3" data-type="date"><button type="button" class="btn btn-link btn-sm p-0" commandfor="accPendingExpensesTable" command="--sort" data-sort-key="created_at">Submitted</button></th>
                                </tr>
                            </thead>
                            <tbody id="accPendingExpensesBody"><tr><td colspan="4" class="text-center text-muted py-4">Loading&hellip;</td></tr></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4 finance-classic-operations">
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="mb-0"><i class="bi bi-speedometer me-2 text-info"></i>Budget utilization</h6></div>
            <div class="card-body"><div id="accBudgetUtil"><p class="text-muted small mb-0">No budget data available.</p></div></div>
        </div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="mb-0"><i class="bi bi-receipt-cutoff me-2 text-success"></i>Expense position</h6></div>
            <div class="card-body"><div id="accExpenses"><p class="text-muted small mb-0">Loading expense position&hellip;</p></div></div>
        </div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="mb-0"><i class="bi bi-check2-circle me-2 text-warning"></i>Reconciliation position</h6></div>
            <div class="card-body"><div id="accReconciliation"><p class="text-muted small mb-0">Loading reconciliation position&hellip;</p></div></div>
        </div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="mb-0"><i class="bi bi-bank me-2 text-primary"></i>Cash position</h6></div>
            <div class="card-body"><div id="accCashPosition"><p class="text-muted small mb-0">Loading posted balances&hellip;</p></div></div>
        </div></div>
        <div class="col-md-6"><div class="card border-0 shadow-sm h-100 dash-card">
            <div class="card-header bg-transparent border-0 pt-3 pb-0"><h6 class="mb-0"><i class="bi bi-people me-2 text-secondary"></i>Payroll snapshot</h6></div>
            <div class="card-body"><div id="accPayroll"><p class="text-muted small mb-0">Loading payroll snapshot&hellip;</p></div></div>
        </div></div>
    </div>

    <?php kw_dashboard_tabs_close('accountant', []); ?>
    </div>
</div>

<?php asset_script($appBase, 'js/dashboards/dashboard_base_controller.js'); ?>
<?php asset_script($appBase, 'js/dashboards/school_accountant_dashboard.js'); ?>
