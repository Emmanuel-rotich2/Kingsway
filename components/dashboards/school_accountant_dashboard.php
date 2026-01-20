<div id="school-accountant-dashboard">

    <?php
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ?>

    <!-- Header -->
    <div class="row mb-3 align-items-center">
        <div class="col-md-8">
            <h4 class="mb-1">
                <i class="bi bi-wallet2 me-2 text-primary"></i>School Accountant Dashboard
            </h4>
            <p class="text-muted mb-0">
                Fees, payments, reconciliations, budgets, and financial alerts
            </p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="d-flex align-items-center justify-content-md-end gap-2">
                <!-- Auto-refresh toggle -->
                <div class="form-check form-switch me-2" title="Auto-refresh every 15 seconds">
                    <input class="form-check-input" type="checkbox" id="autoRefreshToggle" checked>
                    <label class="form-check-label small text-muted" for="autoRefreshToggle">
                        <i class="bi bi-arrow-repeat" id="autoRefreshIcon"></i> Auto
                    </label>
                </div>
                <!-- Last updated indicator -->
                <small class="text-muted">
                    <span id="lastRefreshTime">--:--:--</span>
                </small>
                <!-- Manual refresh button -->
                <button id="refreshDashboard" class="btn btn-sm btn-primary" title="Refresh now">
                    <i class="bi bi-arrow-clockwise" id="refreshIcon"></i>
                </button>
            </div>
            <!-- Connection status indicator -->
            <div class="mt-1">
                <small id="connectionStatus" class="badge bg-success">
                    <i class="bi bi-wifi"></i> Live
                </small>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body py-2 d-flex flex-wrap gap-2">
                    <a href="#" class="btn btn-sm btn-outline-primary dashboard-action"
                        data-route="school_accountant_payments">
                        <i class="bi bi-plus-circle"></i> Record Payment
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-secondary dashboard-action"
                        data-route="school_accountant_unmatched_payments">
                        <i class="bi bi-question-circle"></i> Reconcile Payments
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-success dashboard-action"
                        data-route="school_accountant_fee_structure">
                        <i class="bi bi-list-check"></i> Fee Structures
                    </a>
                    <a href="#" class="btn btn-sm btn-outline-dark dashboard-action"
                        data-route="school_accountant_reports">
                        <i class="bi bi-graph-up"></i> Financial Reports
                    </a>
                </div>
            </div>
        </div>
    </div>



    <!-- KPI Cards - Row 1: Time-Based Collections -->
    <div class="row mb-2">
        <div class="col-12">
            <h6 class="text-muted mb-2"><i class="bi bi-clock-history me-1"></i> Collections Overview</h6>
        </div>
    </div>
    <div id="summaryCards" class="row">
        <?php
        $kpisRow1 = [
            ['id' => 'today_total', 'label' => "Today's Collections", 'icon' => 'bi-calendar-day', 'color' => 'success'],
            ['id' => 'week_total', 'label' => 'This Week', 'icon' => 'bi-calendar-week', 'color' => 'primary'],
            ['id' => 'month_total', 'label' => 'This Month', 'icon' => 'bi-calendar-month', 'color' => 'info'],
            ['id' => 'term_collected', 'label' => 'Current Term', 'icon' => 'bi-calendar3', 'color' => 'warning'],
        ];
        foreach ($kpisRow1 as $kpi): ?>
                        <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                            <div class="card shadow-sm h-100 border-start border-<?= $kpi['color'] ?> border-3">
                                <div class="card-body py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <small class="text-muted d-block"><?= $kpi['label'] ?></small>
                                            <h4 id="kpi_<?= $kpi['id'] ?>" class="mb-0 mt-1 fw-bold">--</h4>
                                        </div>
                                        <div class="bg-<?= $kpi['color'] ?> bg-opacity-10 rounded-circle p-2">
                                            <i class="bi <?= $kpi['icon'] ?> text-<?= $kpi['color'] ?> fs-5"></i>
                                        </div>
                                    </div>
                                    <small id="kpi_<?= $kpi['id'] ?>_change" class="mt-1 d-block">
                                        <span class="text-muted">vs previous period</span>
                                    </small>
                                </div>
                            </div>
                        </div>
        <?php endforeach; ?>
    </div>

    <!-- KPI Cards - Row 2: Fee Obligations -->
    <div class="row mb-2 mt-2">
        <div class="col-12">
            <h6 class="text-muted mb-2"><i class="bi bi-cash-stack me-1"></i> Fee Obligations (Full Year)</h6>
        </div>
    </div>
    <div id="feeCards" class="row">
        <?php
        $kpisRow2 = [
            ['id' => 'fees_due', 'label' => 'Total Fees Due', 'icon' => 'bi-cash', 'color' => 'secondary'],
            ['id' => 'collected', 'label' => 'Total Collected', 'icon' => 'bi-cash-stack', 'color' => 'success'],
            ['id' => 'outstanding', 'label' => 'Outstanding Balance', 'icon' => 'bi-exclamation-triangle', 'color' => 'danger'],
            ['id' => 'collection_rate', 'label' => 'Collection Rate', 'icon' => 'bi-percent', 'color' => 'info'],
        ];
        foreach ($kpisRow2 as $kpi): ?>
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-muted d-block"><?= $kpi['label'] ?></small>
                                <h4 id="kpi_<?= $kpi['id'] ?>" class="mb-0 mt-1 fw-bold">--</h4>
                            </div>
                            <div class="bg-<?= $kpi['color'] ?> bg-opacity-10 rounded-circle p-2">
                                <i class="bi <?= $kpi['icon'] ?> text-<?= $kpi['color'] ?> fs-5"></i>
                            </div>
                        </div>
                        <small class="text-muted" id="kpi_<?= $kpi['id'] ?>_sub">—</small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- KPI Cards - Row 3: Operational Metrics -->
    <div class="row mb-2 mt-2">
        <div class="col-12">
            <h6 class="text-muted mb-2"><i class="bi bi-gear me-1"></i> Operational Metrics</h6>
        </div>
    </div>
    <div id="operationalCards" class="row">
        <?php
        $kpisRow3 = [
            ['id' => 'unreconciled', 'label' => 'Unreconciled Payments', 'icon' => 'bi-question-circle', 'color' => 'warning'],
            ['id' => 'defaulters_count', 'label' => 'Fee Defaulters', 'icon' => 'bi-person-x', 'color' => 'danger'],
            ['id' => 'full_payment_count', 'label' => 'Fully Paid Students', 'icon' => 'bi-person-check', 'color' => 'success'],
            ['id' => 'avg_payment_amount', 'label' => 'Average Payment', 'icon' => 'bi-calculator', 'color' => 'primary'],
        ];
        foreach ($kpisRow3 as $kpi): ?>
            <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6 mb-3">
                <div class="card shadow-sm h-100">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-muted d-block"><?= $kpi['label'] ?></small>
                                <h4 id="kpi_<?= $kpi['id'] ?>" class="mb-0 mt-1 fw-bold">--</h4>
                            </div>
                            <div class="bg-<?= $kpi['color'] ?> bg-opacity-10 rounded-circle p-2">
                                <i class="bi <?= $kpi['icon'] ?> text-<?= $kpi['color'] ?> fs-5"></i>
                            </div>
                        </div>
                        <small class="text-muted" id="kpi_<?= $kpi['id'] ?>_sub">—</small>
                </div>
            </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Main Row -->
    <div class="row mt-3" id="accountantMainRow">

        <!-- Left -->
        <div class="col-lg-8">
            <!-- Chart with Filters and Export -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">📈 Monthly Fee Collection Trends</h6>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="chartExportPng"
                                title="Export as PNG">
                                <i class="bi bi-image"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="chartExportCsv"
                                title="Export as CSV">
                                <i class="bi bi-file-earmark-csv"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Date Range Filter -->
                    <div class="row mb-3 small">
                        <div class="col-auto">
                            <label class="form-label">Date Range:</label>
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" id="chartDateRange" style="width: 150px;">
                                <option value="6">Last 6 Months</option>
                                <option value="12">Last 12 Months</option>
                                <option value="3">Last 3 Months</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-auto" id="customDateRangeFields" style="display: none;">
                            <input type="date" class="form-control form-control-sm" id="chartDateFrom"
                                style="width: 140px;">
                            <input type="date" class="form-control form-control-sm" id="chartDateTo"
                                style="width: 140px;">
                            <button class="btn btn-sm btn-primary" id="chartApplyDateRange">Apply</button>
                        </div>
                    </div>

                    <!-- Comparison Toggle -->
                    <div class="row mb-3 small">
                        <div class="col-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chartShowComparison" value="">
                                <label class="form-check-label" for="chartShowComparison">
                                    Show Year-over-Year Comparison
                                </label>
                            </div>
                        </div>
                    </div>

                    <canvas id="chart_monthly_trends" height="120"></canvas>
                </div>
            </div>

            <!-- Transactions Table with Filters and Export -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">💳 Recent Transactions</h6>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="tableExportCsv"
                                title="Export as CSV">
                                <i class="bi bi-file-earmark-csv"></i> CSV
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="tableExportExcel"
                                title="Export as Excel">
                                <i class="bi bi-file-earmark-excel"></i> Excel
                            </button>
                        </div>
                    </div>

                    <!-- Table Filters -->
                    <div class="row mb-3 g-2 small">
                        <div class="col-auto">
                            <input type="date" class="form-control form-control-sm" id="transactionDateFrom"
                                placeholder="From" style="width: 140px;">
                        </div>
                        <div class="col-auto">
                            <input type="date" class="form-control form-control-sm" id="transactionDateTo"
                                placeholder="To" style="width: 140px;">
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" id="transactionStatus" style="width: 130px;">
                                <option value="">All Status</option>
                                <option value="completed">Completed</option>
                                <option value="pending">Pending</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <select class="form-select form-select-sm" id="transactionMethod" style="width: 130px;">
                                <option value="">All Methods</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cash">Cash</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-sm btn-primary" id="applyTransactionFilters">Filter</button>
                            <button class="btn btn-sm btn-outline-secondary" id="clearTransactionFilters">Clear</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Reference</th>
                                    <th>Student</th>
                                    <th>Method</th>
                                    <th class="text-end">Amount</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tbody_recent_transactions">
                                <tr class="no-data">
                                    <td colspan="7" class="text-center text-muted py-3">No recent transactions</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right -->
        <div class="col-lg-4">

            <!-- Finance Alerts with Custom Rules -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">🚨 Finance Alerts</h6>
                        <button class="btn btn-sm btn-outline-secondary" id="configureAlerts"
                            title="Configure Alert Rules">
                            <i class="bi bi-gear"></i>
                        </button>
                    </div>
                    <div id="accountantAlerts" class="list-group list-group-flush small">
                        <div class="text-muted text-center py-2">Loading alerts…</div>
                    </div>
                </div>
            </div>

            <!-- Accounts & Cash: populated by JS (bank accounts list + balances) -->
            <div class="card mb-3 shadow-sm">
                <div class="card-body">
                    <h6 class="card-title">🏦 Accounts & Cash</h6>
                    <div id="bankAccountsList" class="small text-muted">Loading bank accounts…</div>
                    <div id="accountBalances" class="mt-2 small text-muted">Select a bank account to view balances</div>
                </div>
            </div>

            <!-- Unmatched Payments -->
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="card-title mb-0">❓ Unmatched Payments</h6>
                        <button class="btn btn-sm btn-outline-secondary" id="unmatchedExportCsv" title="Export as CSV">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Mpesa Code</th>
                                    <th>Phone</th>
                                    <th class="text-end">Amount</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tbody_unmatched_payments"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Pivot Tables Section -->
    <div class="row mt-4">
        <div class="col-12">
            <h5 class="mb-3"><i class="bi bi-table me-2"></i>Analysis & Pivot Tables</h5>
        </div>
    </div>

    <div class="row">
        <!-- Pivot: Collections by Class -->
        <div class="col-lg-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-mortarboard me-2"></i>Collections by Class</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="exportPivotClass" title="Export">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Class</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-end">Due</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance</th>
                                    <th class="text-center">Rate</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_pivot_class">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>


    </div>

    <div class="row">
        <!-- Pivot: Collections by Student Type -->
        <div class="col-lg-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-people me-2"></i>Collections by Student Type</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="exportPivotType" title="Export">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th class="text-center">Students</th>
                                    <th class="text-end">Due</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance</th>
                                    <th class="text-center">Rate</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_pivot_type">
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-3">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pivot: Collections by Payment Method -->
        <div class="col-lg-8 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-credit-card me-2"></i>By Payment Method</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="exportPivotMethod" title="Export">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Method</th>
                                    <th class="text-center">Txns</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Avg</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_pivot_method">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>



        <!-- Pivot: Daily Collections This Month -->
        <div class="col-lg-4 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-calendar-date me-2"></i>Daily (This Month)</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="exportPivotDaily" title="Export">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th class="text-center">Txns</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_pivot_daily">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Pivot: Collections by Fee Type -->
        <div class="col-lg-12 mb-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>By Fee Type</h6>
                    <button class="btn btn-sm btn-outline-secondary" id="exportPivotFeeType" title="Export">
                        <i class="bi bi-download"></i>
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Fee Type</th>
                                    <th class="text-end">Due</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-center">Rate</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_pivot_fee_type">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Defaulters Table -->
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-exclamation-diamond text-danger me-2"></i>Top Fee Defaulters</h6>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary me-1" id="exportDefaulters" title="Export">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <a href="#" class="btn btn-sm btn-outline-primary dashboard-action" data-route="fee_defaulters">
                            View All <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Admission No</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Type</th>
                                    <th class="text-end">Total Due</th>
                                    <th class="text-end">Paid</th>
                                    <th class="text-end">Balance</th>
                                    <th class="text-center">Days Overdue</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="tbody_top_defaulters">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-3">Loading...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ======================== MODALS FOR DEFAULTER ACTIONS ======================== -->

<!-- SMS Reminder Modal -->
<div class="modal fade" id="smsReminderModal" tabindex="-1" aria-labelledby="smsReminderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="smsReminderModalLabel">
                    <i class="bi bi-chat-dots me-2"></i>Send SMS Fee Reminder
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="smsReminderForm">
                    <input type="hidden" id="sms_student_id" name="student_id">
                    <input type="hidden" id="sms_balance" name="balance">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Student</label>
                        <p id="sms_student_name" class="form-control-plaintext">--</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Outstanding Balance</label>
                        <p id="sms_balance_display" class="form-control-plaintext text-danger fw-bold">--</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sms_phone" class="form-label fw-bold">Parent Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="sms_phone" name="phone" required pattern="^0[0-9]{9}$|^254[0-9]{9}$|^\+254[0-9]{9}$">
                        <div class="form-text">Format: 0712345678 or 254712345678</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sms_template" class="form-label fw-bold">Message Template</label>
                        <select class="form-select" id="sms_template">
                            <option value="default">Default Fee Reminder</option>
                            <option value="urgent">Urgent Payment Required</option>
                            <option value="gentle">Gentle Reminder</option>
                            <option value="custom">Custom Message</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="sms_message" class="form-label fw-bold">Message Preview</label>
                        <textarea class="form-control" id="sms_message" name="message" rows="4" readonly></textarea>
                        <div class="form-text"><span id="sms_char_count">0</span>/160 characters</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSendSmsReminder">
                    <i class="bi bi-send me-1"></i>Send SMS
                </button>
            </div>
        </div>
    </div>
</div>

<!-- WhatsApp Reminder Modal -->
<div class="modal fade" id="whatsappReminderModal" tabindex="-1" aria-labelledby="whatsappReminderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="whatsappReminderModalLabel">
                    <i class="bi bi-whatsapp me-2"></i>Send WhatsApp Fee Reminder
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="whatsappReminderForm">
                    <input type="hidden" id="wa_student_id" name="student_id">
                    <input type="hidden" id="wa_balance" name="balance">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Student</label>
                        <p id="wa_student_name" class="form-control-plaintext">--</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Outstanding Balance</label>
                        <p id="wa_balance_display" class="form-control-plaintext text-danger fw-bold">--</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wa_phone" class="form-label fw-bold">Parent Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="wa_phone" name="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wa_message" class="form-label fw-bold">Message</label>
                        <textarea class="form-control" id="wa_message" name="message" rows="4"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSendWhatsApp">
                    <i class="bi bi-whatsapp me-1"></i>Open WhatsApp
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment History Modal -->
<div class="modal fade" id="paymentHistoryModal" tabindex="-1" aria-labelledby="paymentHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="paymentHistoryModalLabel">
                    <i class="bi bi-clock-history me-2"></i>Payment History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Student Info Summary -->
                <div class="card mb-3 bg-light">
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Student:</strong> <span id="ph_student_name">--</span><br>
                                <strong>Admission No:</strong> <span id="ph_admission_no">--</span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>Class:</strong> <span id="ph_class">--</span><br>
                                <strong>Total Paid:</strong> <span id="ph_total_paid" class="text-success fw-bold">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment History Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Date</th>
                                <th>Receipt No</th>
                                <th>Reference</th>
                                <th>Method</th>
                                <th class="text-end">Amount</th>
                                <th>Term</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="tbody_payment_history">
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Loading payment history...
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-secondary">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total:</td>
                                <td id="ph_footer_total" class="text-end fw-bold text-success">--</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnPrintPaymentHistory">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button type="button" class="btn btn-outline-success" id="btnExportPaymentHistory">
                    <i class="bi bi-file-earmark-excel me-1"></i>Export
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Record Quick Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="recordPaymentModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Record Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Student Info Summary -->
                <div class="card mb-3 bg-light">
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Student:</strong> <span id="rp_student_name">--</span><br>
                                <strong>Admission No:</strong> <span id="rp_admission_no">--</span>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <strong>Outstanding:</strong> <span id="rp_balance" class="text-danger fw-bold">--</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <form id="recordPaymentForm">
                    <input type="hidden" id="rp_student_id" name="student_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="rp_amount" class="form-label fw-bold">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Ksh</span>
                                <input type="number" class="form-control" id="rp_amount" name="amount" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="rp_payment_date" class="form-label fw-bold">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="rp_payment_date" name="payment_date" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="rp_method" class="form-label fw-bold">Payment Method <span class="text-danger">*</span></label>
                            <select class="form-select" id="rp_method" name="payment_method" required>
                                <option value="">Select method...</option>
                                <option value="cash">Cash</option>
                                <option value="mpesa">M-Pesa</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                            </select>
                            <small class="text-muted d-block mt-1">Select the method used for payment</small>
                        </div>
                        <div class="col-md-6 mb-3" id="rp_reference_group" style="display: none;">
                            <label for="rp_reference" class="form-label fw-bold">Reference Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="rp_reference" name="reference_no" placeholder="e.g., M-Pesa code, cheque #, transaction ID">
                            <small class="text-muted d-block mt-1">Required for electronic payments</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rp_term" class="form-label fw-bold">Apply to Term</label>
                        <select class="form-select" id="rp_term" name="term_id">
                            <option value="">Current Term (Auto)</option>
                            <!-- Will be populated dynamically -->
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="rp_notes" class="form-label fw-bold">Notes</label>
                        <textarea class="form-control" id="rp_notes" name="notes" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSubmitPayment">
                    <i class="bi bi-check-circle me-1"></i>Record Payment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fee Statement Modal -->
<div class="modal fade" id="feeStatementModal" tabindex="-1" aria-labelledby="feeStatementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="feeStatementModalLabel">
                    <i class="bi bi-file-text me-2"></i>Fee Statement
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="feeStatementContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading fee statement...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnPrintFeeStatement">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button type="button" class="btn btn-outline-primary" id="btnDownloadFeeStatement">
                    <i class="bi bi-download me-1"></i>Download PDF
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Fee Structure Modal -->
<div class="modal fade" id="feeStructureModal" tabindex="-1" aria-labelledby="feeStructureModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title" id="feeStructureModalLabel">
                    <i class="bi bi-list-check me-2"></i>Fee Structure
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="feeStructureContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading fee structure...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="btnPrintFeeStructure">
                    <i class="bi bi-printer me-1"></i>Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Student Profile Modal -->
<div class="modal fade" id="studentProfileModal" tabindex="-1" aria-labelledby="studentProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="studentProfileModalLabel">
                    <i class="bi bi-person me-2"></i>Student Profile
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="studentProfileContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading student profile...</p>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-outline-primary" id="btnViewFullProfile" target="_blank">
                    <i class="bi bi-box-arrow-up-right me-1"></i>View Full Profile
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ======================== END MODALS ======================== -->

<!-- Load the Dashboard Base Controller first -->
<script src="/Kingsway/js/dashboards/dashboard_base_controller.js"></script>
<!-- Load the School Accountant Dashboard Controller -->
<script src="/Kingsway/js/dashboards/school_accountant_dashboard.js"></script>
<script>
    // Initialize the dashboard when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        console.log('🎯 Initializing School Accountant Dashboard...');

        // Check if controller exists
        if (typeof schoolAccountantDashboardController !== 'undefined') {
            schoolAccountantDashboardController.init();
        } else {
            console.error('❌ schoolAccountantDashboardController not found');
        }
    });
</script>

<style>
    /* Spinner animation for refresh button */
    .btn i.spinner {
        display: inline-block;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* Dashboard card styling */
    #school-accountant-dashboard .card {
        transition: box-shadow 0.3s ease;
    }

    #school-accountant-dashboard .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }

    /* Table row hover effect */
    #school-accountant-dashboard .table-hover tbody tr:hover {
        background-color: rgba(0, 0, 0, 0.02);
    }

    /* KPI card styling */
    #summaryCards .card {
        border-left: 4px solid transparent;
    }

    #summaryCards .card:nth-child(1) {
        border-left-color: #dc3545;
    }

    #summaryCards .card:nth-child(2) {
        border-left-color: #28a745;
    }

    #summaryCards .card:nth-child(3) {
        border-left-color: #ffc107;
    }

    #summaryCards .card:nth-child(4) {
        border-left-color: #6c757d;
    }

    #summaryCards .card:nth-child(5) {
        border-left-color: #17a2b8;
    }

    #summaryCards .card:nth-child(6) {
        border-left-color: #007bff;
    }

    /* Enhancement Features Styling */

    /* Export Buttons */
    .btn-group .btn-outline-secondary:hover {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }

    /* Filter Controls */
    .form-control-sm,
    .form-select-sm {
        font-size: 0.875rem;
        height: 31px;
    }

    #customDateRangeFields {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;
    }

    #customDateRangeFields input {
        width: auto;
    }

    /* Filter Row Responsive */
    @media (max-width: 768px) {
        .row.mb-3.g-2 {
            flex-direction: column;
        }

        .row.mb-3.g-2>.col-auto {
            width: 100% !important;
        }

        .form-control-sm,
        .form-select-sm {
            width: 100%;
        }
    }

    /* Modal Styling */
    .modal-header {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }

    .modal .table-responsive {
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
    }

    /* Chart Container */
    #chart_monthly_trends {
        max-height: 300px;
        min-height: 250px;
    }

    /* Table Styling */
    .table-responsive {
        border-radius: 0.25rem;
    }

    .table tbody tr:hover {
        background-color: #f8f9fa;
    }

    .table-sm td {
        padding: 0.4rem 0.75rem;
        vertical-align: middle;
    }

    /* Badge Styling */
    .badge {
        font-size: 0.75rem;
        padding: 0.35rem 0.65rem;
    }

    /* Button Styling */
    .btn-sm {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
    }

    .btn-outline-primary:hover {
        background-color: #0d6efd;
        border-color: #0d6efd;
        color: white;
    }

    /* Spinner Animation */
    .spinner {
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* Alert Alert Styling */
    .alert {
        border-left: 4px solid;
        margin-bottom: 0.5rem;
    }

    .alert-info {
        border-left-color: #17a2b8;
        background-color: #d1ecf1;
        color: #0c5460;
    }

    .list-group-item {
        border-left: 3px solid transparent;
        transition: all 0.2s ease;
    }

    .list-group-item:hover {
        background-color: #f8f9fa;
        border-left-color: #007bff;
    }

    /* Comparison Toggle */
    .form-check {
        margin-bottom: 0;
    }

    .form-check-input:checked {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    /* Card Shadows & Borders */
    .card {
        border: 1px solid #e9ecef;
        transition: box-shadow 0.3s ease;
    }

    .card:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
    }

    /* Responsive Adjustments */
    @media (max-width: 992px) {

        .col-lg-8,
        .col-lg-4 {
            margin-bottom: 1rem;
        }

        .card-body {
            padding: 1rem;
        }
    }

    /* No Data Message */
    .no-data {
        background-color: #f8f9fa;
    }

    .text-muted {
        color: #6c757d !important;
    }

    /* Loading State */
    #accountant-dashboard-loading {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 400px;
    }

    /* Smooth Transitions */
    button,
    select,
    input {
        transition: all 0.2s ease;
    }

    button:active {
        transform: translateY(1px);
    }

    /* Focus States for Accessibility */
    .btn:focus,
    select:focus,
    input:focus {
        outline: 2px solid #0d6efd;
        outline-offset: 2px;
    }

    /* Print Styles */
    @media print {

        .btn-group,
        .form-control-sm,
        .form-select-sm,
        #refreshDashboard {
            display: none !important;
        }

        .card {
            page-break-inside: avoid;
            box-shadow: none;
            border: 1px solid #dee2e6;
        }
    }
</style>