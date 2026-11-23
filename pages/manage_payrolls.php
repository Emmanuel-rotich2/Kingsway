<?php
/**
 * Manage Payrolls Page — Executive Finance Ledger
 * Enhanced with staff children fee deduction during payroll processing
 * All logic in js/pages/payroll_manager.js
 */
?>

<style>
/* Payroll: the quiet ledger look of Fee accounts (see css/kw-clean.css).
   Ink figures, colour only for meaning: paid, pending, cancelled. */
.director-payroll-page {
    --pr-ink: #1f2a44; --pr-muted: #667085; --pr-rule: #e3e6eb; --pr-wash: #f6f7f9;
    --pr-paper: #ffffff; --pr-success: #2f7d5b; --pr-danger: #a23b2a; --pr-warning: #9a6a00; --pr-info: #4c5ba8;
    /* names used by inline styles elsewhere on the page */
    --payroll-blue-soft: var(--pr-info); --payroll-success: var(--pr-success); --payroll-danger: var(--pr-danger);
    color: var(--pr-ink);
    background: var(--pr-paper);
    border: 1px solid var(--pr-rule);
    border-radius: 10px;
    padding: 1.25rem !important;
}
.director-payroll-page .payroll-hero { padding: 0 0 .9rem; border-bottom: 2px solid var(--pr-ink); margin-bottom: 0; background: none; }
.director-payroll-page .payroll-eyebrow { display: none; }
.director-payroll-page .payroll-title { font-size: 1.5rem; font-weight: 650; letter-spacing: -.01em; margin: 0; color: var(--pr-ink); }
.director-payroll-page .payroll-subtitle { color: var(--pr-muted); margin: .2rem 0 0; max-width: 60ch; }
.director-payroll-page .payroll-actions { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: flex-end; }
.director-payroll-page .payroll-action-btn {
    border: 1px solid var(--pr-rule); background: var(--pr-paper); color: var(--pr-ink);
    border-radius: 6px; font-size: .82rem; font-weight: 500; padding: .35rem .7rem; line-height: 1.3;
}
.director-payroll-page .payroll-action-btn:hover { border-color: var(--pr-ink); }
.director-payroll-page .payroll-action-btn.primary { background: var(--pr-ink); border-color: var(--pr-ink); color: #fff; }
.director-payroll-page .payroll-action-btn.primary:hover { background: #2c3a5c; }
.director-payroll-page .payroll-action-btn:disabled { opacity: .5; }

/* Figures: one ruled strip */
.director-payroll-page .payroll-board {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
    border-bottom: 1px solid var(--pr-rule); margin: 0 0 1.25rem; gap: 0;
}
.director-payroll-page .payroll-metric { padding: .8rem 1rem .9rem 0; background: none; border: 0; box-shadow: none; border-radius: 0; }
.director-payroll-page .payroll-metric + .payroll-metric { padding-left: 1rem; border-left: 1px solid var(--pr-rule); }
.director-payroll-page .payroll-metric.featured { background: var(--pr-wash); padding-left: 1rem; }
.director-payroll-page .metric-icon { display: none; }
.director-payroll-page .metric-label { font-size: .78rem; color: var(--pr-muted); font-weight: 500; letter-spacing: 0; text-transform: none; margin: 0; }
.director-payroll-page .metric-value { font-size: 1.35rem; font-weight: 650; font-variant-numeric: tabular-nums; color: var(--pr-ink); margin: .1rem 0; line-height: 1.2; }
.director-payroll-page .metric-note { font-size: .78rem; color: var(--pr-muted); margin: 0; }

/* Sections */
.director-payroll-page .payroll-panel { border: 0; border-radius: 0; box-shadow: none; background: none; margin-bottom: 1.25rem; }
.director-payroll-page .payroll-panel-header { display: flex; justify-content: space-between; align-items: center; gap: .75rem; padding: 0 0 .5rem; background: none; border-bottom: 0; }
.director-payroll-page .payroll-panel-header h5 { font-size: 1rem; font-weight: 600; margin: 0; color: var(--pr-ink); }
.director-payroll-page .payroll-panel-header h5 i { display: none; }
.director-payroll-page .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .6rem; }
.director-payroll-page .filter-group { margin: 0; }
.director-payroll-page .form-label { font-size: .78rem; color: var(--pr-muted); font-weight: 500; text-transform: none; letter-spacing: 0; margin-bottom: .2rem; }
.director-payroll-page :is(.form-select, .form-control) { border-color: var(--pr-rule); font-size: .875rem; background-color: var(--pr-paper); border-radius: 6px; }
.director-payroll-page :is(.form-select, .form-control):focus { border-color: var(--pr-info); box-shadow: 0 0 0 .15rem rgba(76, 91, 168, .15); }

/* The ledger table */
.director-payroll-page #payrollTable { font-size: .875rem; margin: 0; }
.director-payroll-page #payrollTable thead th {
    background: none; color: var(--pr-muted); font-size: .78rem; font-weight: 600; text-transform: none; letter-spacing: 0;
    border-bottom: 1px solid var(--pr-ink); white-space: nowrap; padding: .5rem .6rem;
}
.director-payroll-page #payrollTable tbody td { border-color: var(--pr-rule); padding: .6rem; vertical-align: middle; background: none; }
.director-payroll-page #payrollTable tbody tr:hover td { background: var(--pr-wash); }
.director-payroll-page .table-amount { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
.director-payroll-page .table-amount.negative { color: var(--pr-danger); }
.director-payroll-page .status-badge { background: none; padding: 0; font-size: .78rem; font-weight: 600; text-transform: none; letter-spacing: 0; color: var(--pr-muted); }
.director-payroll-page .status-badge.paid { color: var(--pr-success); }
.director-payroll-page .status-badge.pending { color: var(--pr-warning); }
.director-payroll-page .status-badge.processing { color: var(--pr-info); }
.director-payroll-page .status-badge.cancelled { color: var(--pr-danger); }
.director-payroll-page .table-action-btn { border: 0; background: none; color: var(--pr-muted); padding: .2rem .35rem; border-radius: 4px; }
.director-payroll-page .table-action-btn:hover { color: var(--pr-ink); background: var(--pr-wash); }
.director-payroll-page .table-action-btn.approve { color: var(--pr-success); }

/* Modals and the payroll form */
.director-payroll-page .step-header { display: flex; align-items: baseline; gap: .5rem; border-bottom: 1px solid var(--pr-rule); padding-bottom: .4rem; margin: 1rem 0 .75rem; }
.director-payroll-page .step-number { font-weight: 700; color: var(--pr-muted); }
.director-payroll-page .step-title { font-weight: 600; }
.director-payroll-page #staffInfoCard { border: 1px solid var(--pr-rule); box-shadow: none; }
.director-payroll-page .net-pay-banner { background: var(--pr-wash); border-top: 2px solid var(--pr-ink); border-radius: 0; padding: .75rem 1rem; color: var(--pr-ink); }
.director-payroll-page .net-pay-banner h5 { font-size: .9rem; color: var(--pr-muted); margin: 0; }
#viewPayslipModal .modal-header { border-bottom: 2px solid #1f2a44; }
#viewPayslipModal .modal-title { font-weight: 650; }
.director-payroll-page :is(.btn, button, a, .form-select, .form-control):focus-visible { outline: 2px solid var(--pr-info); outline-offset: 2px; }

/* Statutory decisions: four independent switches, never one master toggle. */
.director-payroll-page .statutory-choice-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    border-top: 1px solid var(--pr-ink);
    border-bottom: 1px solid var(--pr-rule);
}
.director-payroll-page .statutory-choice {
    min-height: 5.25rem;
    padding: .8rem .85rem;
    background: var(--pr-paper);
    border: 0;
    border-right: 1px solid var(--pr-rule);
}
.director-payroll-page .statutory-choice:last-child { border-right: 0; }
.director-payroll-page .statutory-choice-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .5rem;
    margin-bottom: .35rem;
}
.director-payroll-page .statutory-choice-label { font-size: .82rem; font-weight: 650; color: var(--pr-ink); }
.director-payroll-page .statutory-choice-amount {
    font-size: .8rem;
    color: var(--pr-muted);
    font-variant-numeric: tabular-nums;
}
.director-payroll-page .statutory-choice-note { font-size: .72rem; color: var(--pr-muted); margin: .3rem 0 0; }
.director-payroll-page .statutory-choice.is-on { box-shadow: inset 0 3px 0 var(--pr-info); }
.director-payroll-page .statutory-choice.is-on .statutory-choice-amount { color: var(--pr-ink); font-weight: 650; }

/* Bulk review keeps the four decisions visible beside each employee. */
.director-payroll-page .bulk-statutory-grid { display: grid; grid-template-columns: repeat(2, minmax(5.5rem, 1fr)); gap: .25rem .45rem; }
.director-payroll-page .bulk-statutory-item {
    display: flex;
    align-items: center;
    gap: .3rem;
    font-size: .72rem;
    color: var(--pr-muted);
    white-space: nowrap;
}
.director-payroll-page .bulk-statutory-item.is-on { color: var(--pr-ink); font-weight: 650; }
.director-payroll-page .bulk-statutory-item input { margin: 0; }

/* Payout planning reads as a routing sheet rather than a generic card stack. */
.director-payroll-page .payout-plan-intro { border-left: 4px solid var(--pr-info); padding: .7rem .85rem; background: var(--pr-wash); color: var(--pr-ink); }
.director-payroll-page .payout-plan-list { display: grid; gap: .9rem; }
.director-payroll-page .payout-plan-person { border-top: 2px solid var(--pr-ink); padding-top: .65rem; }
.director-payroll-page .payout-plan-person-head { display: flex; justify-content: space-between; gap: .75rem; align-items: baseline; margin-bottom: .55rem; }
.director-payroll-page .payout-plan-person-name { font-weight: 700; color: var(--pr-ink); }
.director-payroll-page .payout-plan-person-meta { font-size: .78rem; color: var(--pr-muted); font-variant-numeric: tabular-nums; }
.director-payroll-page .payout-leg {
    display: grid;
    grid-template-columns: minmax(8rem, .8fr) minmax(12rem, 1.5fr) minmax(8rem, .8fr) minmax(10rem, 1.2fr) auto;
    gap: .5rem;
    align-items: end;
    padding: .55rem 0 .55rem .75rem;
    border-left: 2px solid var(--pr-rule);
}
.director-payroll-page .payout-leg + .payout-leg { border-top: 1px dashed var(--pr-rule); }
.director-payroll-page .payout-leg.is-invalid { border-left-color: var(--pr-danger); }
.director-payroll-page .payout-leg-total { font-size: .76rem; color: var(--pr-muted); font-variant-numeric: tabular-nums; }
.director-payroll-page .payout-plan-footer { display: flex; justify-content: space-between; gap: .75rem; align-items: center; margin-top: .55rem; }
.director-payroll-page .payout-plan-balance { font-weight: 700; font-variant-numeric: tabular-nums; }
.director-payroll-page .payout-plan-balance.is-balanced { color: var(--pr-success); }
.director-payroll-page .payout-plan-balance.is-unbalanced { color: var(--pr-danger); }

/* Monthly transaction ledger. */
.director-payroll-page #payoutLedgerTable { font-size: .82rem; margin: 0; }
.director-payroll-page #payoutLedgerTable thead th { background: none; border-bottom: 1px solid var(--pr-ink); color: var(--pr-muted); font-size: .74rem; font-weight: 650; padding: .45rem .55rem; }
.director-payroll-page #payoutLedgerTable tbody td { border-color: var(--pr-rule); padding: .5rem .55rem; vertical-align: middle; }
.director-payroll-page .ledger-method { font-weight: 650; color: var(--pr-ink); }
.director-payroll-page .ledger-reference { font-size: .74rem; color: var(--pr-muted); }
.director-payroll-page .ledger-action-btn { border: 1px solid var(--pr-rule); background: var(--pr-paper); color: var(--pr-ink); border-radius: 4px; padding: .2rem .4rem; font-size: .72rem; }
.director-payroll-page .ledger-action-btn:hover { border-color: var(--pr-ink); }
.director-payroll-page .ledger-action-btn.retry { color: var(--pr-danger); }
@media (max-width: 768px) {
    .director-payroll-page .payroll-actions { justify-content: flex-start; }
    .director-payroll-page .payroll-metric + .payroll-metric { border-left: 0; padding-left: 0; border-top: 1px solid var(--pr-rule); }
    .director-payroll-page .statutory-choice-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .director-payroll-page .statutory-choice:nth-child(2) { border-right: 0; }
    .director-payroll-page .statutory-choice:nth-child(-n+2) { border-bottom: 1px solid var(--pr-rule); }
    .director-payroll-page .payout-leg { grid-template-columns: 1fr; border-left: 0; border-top: 1px solid var(--pr-rule); padding-left: 0; }
}
</style>

<div class="container-fluid director-payroll-page">
    <!-- Executive Header -->
    <section class="payroll-hero">
        <div class="row g-4 align-items-end">
            <div class="col-lg-7">
                <div class="payroll-eyebrow"><i class="bi bi-shield-lock"></i> Director Payroll Control</div>
                <h1 class="payroll-title">Payroll Governance</h1>
                <p class="payroll-subtitle">Approve staff pay, child-fee deductions, and payment status from one executive ledger.</p>
            </div>
            <div class="col-lg-5">
                <div class="payroll-actions">
                    <button class="payroll-action-btn ghost" data-kw-onclick="PayrollManagerController.exportCsv()">
                        <i class="bi bi-file-csv me-1"></i> Export CSV
                    </button>
                    <button class="payroll-action-btn ghost" data-kw-onclick="PayrollManagerController.printPayrollReport()">
                        <i class="bi bi-printer me-1"></i> Print PDF
                    </button>
                    <button class="payroll-action-btn ghost" data-kw-onclick="PayrollManagerController.refresh()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                    <button class="payroll-action-btn primary" id="paySelectedPayrollBtn" data-payment-control hidden disabled data-kw-onclick="PayrollManagerController.paySelected()">
                        <i class="bi bi-signpost-split me-1"></i> Plan Payout
                    </button>
                    <button class="payroll-action-btn primary" data-kw-onclick="PayrollManagerController.showBulkPayrollModal()">
                        <i class="bi bi-people-cog me-1"></i> Bulk Payroll
                    </button>
                    <button class="payroll-action-btn ghost" data-permission="staff.payroll.manage" data-kw-onclick="PayrollManagerController.showCompensationModal()">
                        <i class="bi bi-sliders me-1"></i> Salary &amp; Awards
                    </button>
                    <button class="payroll-action-btn primary" data-kw-onclick="PayrollManagerController.showProcessPayrollModal()">
                        <i class="bi bi-plus-lg-circle me-1"></i> Single Payroll
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Director Payroll Board -->
    <div class="payroll-board">
        <div class="payroll-metric featured" data-payroll-card="net">
            <div class="metric-icon"><i class="bi bi-coin"></i></div>
            <div class="metric-label">This Month's Net Pay</div>
            <div class="metric-value" id="statThisMonthNet">KES --</div>
            <div class="metric-note">Total payroll exposure for the selected period.</div>
        </div>
        <div class="payroll-metric" data-payroll-card="staff">
            <div class="metric-icon"><i class="bi bi-people"></i></div>
            <div class="metric-label">Total Staff</div>
            <div class="metric-value" id="statTotalStaff">--</div>
            <div class="metric-note">Eligible payroll staff</div>
        </div>
        <div class="payroll-metric" data-payroll-card="children_staff">
            <div class="metric-icon"><i class="bi bi-person-bounding-box"></i></div>
            <div class="metric-label">Staff With Children</div>
            <div class="metric-value" id="statStaffWithChildren">--</div>
            <div class="metric-note">Fee deduction candidates</div>
        </div>
        <div class="payroll-metric" data-payroll-card="children_fees">
            <div class="metric-icon"><i class="bi bi-mortarboard"></i></div>
            <div class="metric-label">Children Fees Deducted</div>
            <div class="metric-value" id="statChildrenFees">KES --</div>
            <div class="metric-note">Offset against student balances</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="payroll-panel">
        <div class="filter-row">
            <div class="filter-group">
                <label class="form-label">Month from</label>
                <select id="filterMonthFrom" class="form-select" data-kw-onchange="PayrollManagerController.applyFilters()">
                    <option value="">Any</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Year from</label>
                <select id="filterYearFrom" class="form-select" data-kw-onchange="PayrollManagerController.applyFilters()">
                    <option value="">Any</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Month to</label>
                <select id="filterMonthTo" class="form-select" data-kw-onchange="PayrollManagerController.applyFilters()">
                    <option value="">Any</option>
                    <option value="1">January</option>
                    <option value="2">February</option>
                    <option value="3">March</option>
                    <option value="4">April</option>
                    <option value="5">May</option>
                    <option value="6">June</option>
                    <option value="7">July</option>
                    <option value="8">August</option>
                    <option value="9">September</option>
                    <option value="10">October</option>
                    <option value="11">November</option>
                    <option value="12">December</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Year to</label>
                <select id="filterYearTo" class="form-select" data-kw-onchange="PayrollManagerController.applyFilters()">
                    <option value="">Any</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Status</label>
                <select id="filterStatus" class="form-select" data-kw-onchange="PayrollManagerController.applyFilters()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="processing">Processing</option>
                    <option value="approved">Approved</option>
                    <option value="paid">Paid</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Search Staff</label>
                <input type="text" id="searchStaff" class="form-control" placeholder="Name or ID..."
                       data-kw-oninput="PayrollManagerController.applyFilters()">
            </div>
        </div>
    </div>

    <!-- Payroll Records Table -->
    <div class="payroll-panel">
        <div class="payroll-panel-header">
            <h5><i class="bi bi-list-ul me-2" style="color: var(--payroll-gold)"></i>Payroll Records</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted" id="payrollPaymentSelectionSummary">No staff selected</span>
                <span class="status-badge pending" id="payrollCount">0 records</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="payrollTable">
                <thead>
	                    <tr id="payrollTableHeader">
	                        <th scope="col">Staff</th>
	                        <th scope="col">Period</th>
	                        <th class="text-end">Basic Salary</th>
	                        <th class="text-end">Allowances</th>
	                        <th class="text-end">Statutory Ded.</th>
	                        <th class="text-end">Children Fees</th>
	                        <th class="text-end">Other Ded.</th>
	                        <th class="text-end">Net Pay</th>
	                        <th class="text-center">Status</th>
	                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="payrollTableBody">
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="spinner-border" style="color: var(--payroll-gold); width: 2rem; height: 2rem;" role="status"></div>
                            <p style="color: #8895a7; margin-top: 12px; font-weight: 600;">Loading payroll records...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <nav>
                <ul class="pagination mb-0 justify-content-center" id="payrollPagination"></ul>
            </nav>
        </div>
    </div>

    <!-- Monthly payout ledger: one row per plan leg or provider transaction. -->
    <div class="payroll-panel" id="payrollPayoutLedgerPanel">
        <div class="payroll-panel-header">
            <div>
                <h5>Payout Ledger</h5>
                <div class="small text-muted" id="payoutLedgerScope">Monthly provider and cash movements</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="payroll-action-btn" data-kw-onclick="PayrollManagerController.exportPayoutLedgerCsv()">
                    <i class="bi bi-file-csv me-1"></i> Export CSV
                </button>
                <button type="button" class="payroll-action-btn" data-kw-onclick="PayrollManagerController.printPayrollReport()">
                    <i class="bi bi-printer me-1"></i> Print Monthly Roll
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="payoutLedgerTable">
                <thead>
                    <tr>
                        <th scope="col">Staff</th>
                        <th scope="col">Method</th>
                        <th scope="col">Source Account</th>
                        <th scope="col" class="text-end">Amount</th>
                        <th scope="col">Reference</th>
                        <th scope="col">Provider Reference</th>
                        <th scope="col">Attempt</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody id="payoutLedgerTableBody">
                    <tr><td colspan="9" class="text-center py-4 text-muted">Loading payout ledger…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="processPayrollModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-credit-card me-2"></i><span id="processPayrollModalTitle">Prepare Staff Payroll</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Select Staff & Period -->
                <div id="payrollStep1">
                    <div class="step-header">
                        <div class="step-number">1</div>
                        <div class="step-title">Select Staff Member & Pay Period</div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff Member <span class="text-danger">*</span></label>
                            <select id="payrollStaffSelect" class="form-select" data-kw-onchange="PayrollManagerController.onStaffSelected()">
                                <option value="">-- Select Staff --</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Month <span class="text-danger">*</span></label>
                            <select id="payrollMonth" class="form-select">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Year <span class="text-danger">*</span></label>
                            <input type="number" id="payrollYear" class="form-control" value="<?= date('Y') ?>">
                        </div>
                    </div>

                    <!-- Staff Info Card -->
                    <div id="staffInfoCard" class="card d-none mb-3">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 style="color: #8895a7; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;">Staff Details</h6>
                                    <p class="mb-1"><strong>Name:</strong> <span id="staffInfoName">-</span></p>
                                    <p class="mb-1"><strong>Position:</strong> <span id="staffInfoPosition">-</span></p>
                                    <p class="mb-1"><strong>Department:</strong> <span id="staffInfoDept">-</span></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 style="color: #8895a7; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;">Salary Info</h6>
                                    <p class="mb-1"><strong>Basic Salary:</strong> <span id="staffInfoSalary" style="color: var(--payroll-success); font-weight: 700;">KES 0.00</span></p>
                                <p class="mb-1" data-review-only><strong>Children in School:</strong> <span id="staffInfoChildrenCount" class="status-badge processing">0</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Independent statutory decisions -->
                <div id="payrollStatutoryStep" class="d-none">
                    <hr style="border-color: var(--payroll-line); margin: 20px 0;">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <div class="step-title">Statutory Deductions</div>
                        <span class="status-badge pending" id="statutorySelectionSummary">All items off</span>
                    </div>
                    <div class="alert alert-light border-0 mb-3 py-2" style="border-left: 3px solid var(--pr-info) !important; color: var(--pr-muted); font-size: .82rem;">
                        Choose each deduction for this employee. Every item starts off; a missing KRA PIN, NSSF number or SHIF number does not prevent payroll drafting.
                    </div>
                    <div class="statutory-choice-grid" id="statutoryChoiceGrid">
                        <label class="statutory-choice" data-statutory-card="paye">
                            <span class="statutory-choice-head">
                                <span class="statutory-choice-label">PAYE</span>
                                <input type="checkbox" class="form-check-input" id="statutoryPayeToggle" data-statutory-key="paye" data-kw-onchange="PayrollManagerController.onStatutoryOptionChanged()">
                            </span>
                            <span class="statutory-choice-amount" id="statutoryPayeAmount">KES 0.00</span>
                            <span class="statutory-choice-note">Employee income tax</span>
                        </label>
                        <label class="statutory-choice" data-statutory-card="nssf">
                            <span class="statutory-choice-head">
                                <span class="statutory-choice-label">NSSF</span>
                                <input type="checkbox" class="form-check-input" id="statutoryNssfToggle" data-statutory-key="nssf" data-kw-onchange="PayrollManagerController.onStatutoryOptionChanged()">
                            </span>
                            <span class="statutory-choice-amount" id="statutoryNssfAmount">KES 0.00</span>
                            <span class="statutory-choice-note">Employee contribution</span>
                        </label>
                        <label class="statutory-choice" data-statutory-card="shif">
                            <span class="statutory-choice-head">
                                <span class="statutory-choice-label">SHIF</span>
                                <input type="checkbox" class="form-check-input" id="statutoryShifToggle" data-statutory-key="shif" data-kw-onchange="PayrollManagerController.onStatutoryOptionChanged()">
                            </span>
                            <span class="statutory-choice-amount" id="statutoryShifAmount">KES 0.00</span>
                            <span class="statutory-choice-note">Employee contribution</span>
                        </label>
                        <label class="statutory-choice" data-statutory-card="housing_levy">
                            <span class="statutory-choice-head">
                                <span class="statutory-choice-label">Housing levy</span>
                                <input type="checkbox" class="form-check-input" id="statutoryHousingToggle" data-statutory-key="housing_levy" data-kw-onchange="PayrollManagerController.onStatutoryOptionChanged()">
                            </span>
                            <span class="statutory-choice-amount" id="statutoryHousingAmount">KES 0.00</span>
                            <span class="statutory-choice-note">Employee and school contribution</span>
                        </label>
                    </div>
                </div>

                <!-- Step 3: Children & Fee Deductions -->
                <div id="payrollStep2" class="d-none" data-review-only>
                    <hr style="border-color: var(--payroll-line); margin: 20px 0;">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <div class="step-title">Children Fee Deductions</div>
                        <span class="status-badge processing" id="childrenCountBadge">0 children</span>
                    </div>

                    <div style="background: rgba(201, 168, 76, 0.08); border: 1px solid rgba(201, 168, 76, 0.2); border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;">
                        <i class="bi bi-info-circle me-2" style="color: var(--payroll-gold)"></i>
                        <span style="font-size: 0.88rem; color: var(--payroll-ink);">Configure how much to deduct from salary for each child's school fees.</span>
                    </div>

                    <div id="childrenFeesList"></div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div style="border: 1px solid rgba(58, 90, 140, 0.15); border-radius: 14px; padding: 18px; text-align: center; background: rgba(58, 90, 140, 0.04);">
                                <div style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #8895a7;">Total Fees Outstanding</div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--payroll-blue-soft); margin-top: 4px;" id="totalChildrenFees">KES 0.00</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="border: 1px solid rgba(26, 122, 76, 0.15); border-radius: 14px; padding: 18px; text-align: center; background: rgba(26, 122, 76, 0.04);">
                                <div style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #8895a7;">Total to Deduct This Month</div>
                                <div style="font-size: 1.5rem; font-weight: 700; color: var(--payroll-success); margin-top: 4px;" id="totalDeductionAmount">KES 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4: Salary Calculation -->
                <div id="payrollStep3" class="d-none">
                    <hr style="border-color: var(--payroll-line); margin: 20px 0;">
                    <div class="step-header">
                        <div class="step-number">4</div>
                        <div class="step-title">Salary Breakdown</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 style="color: var(--payroll-success); font-weight: 700; font-size: 0.82rem; margin-bottom: 12px;">
                                <i class="bi bi-arrow-up me-1"></i> Earnings
                            </h6>
                            <table class="table table-sm" style="font-size: 0.88rem;">
                                <tr>
                                    <td style="color: #6b7a8d;">Basic Salary</td>
                                    <td class="text-end" style="font-weight: 700;" id="calcBasicSalary">0.00</td>
                                </tr>
                                <tr data-review-only>
                                    <td style="color: #6b7a8d;">House Allowance</td>
                                    <td class="text-end">
                                        <input type="number" id="houseAllowance" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" data-kw-onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr data-review-only>
                                    <td style="color: #6b7a8d;">Transport Allowance</td>
                                    <td class="text-end">
                                        <input type="number" id="transportAllowance" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" data-kw-onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr data-review-only>
                                    <td style="color: #6b7a8d;">Other Allowances</td>
                                    <td class="text-end">
                                        <input type="number" id="otherAllowances" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" data-kw-onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr data-review-only>
                                    <td style="color: #6b7a8d;">One-off Bonus</td>
                                    <td class="text-end">
                                        <input type="number" id="bonusAllowance" class="form-control form-control-sm text-end"
                                               value="0" min="0" step="0.01" data-kw-onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr style="background: rgba(26, 122, 76, 0.06);">
                                    <td style="font-weight: 800; color: var(--payroll-success);">Gross Salary</td>
                                    <td class="text-end" style="font-weight: 800; color: var(--payroll-success);" id="calcGrossSalary">0.00</td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h6 style="color: var(--payroll-danger); font-weight: 700; font-size: 0.82rem; margin-bottom: 12px;">
                                <i class="bi bi-arrow-down me-1"></i> Deductions
                            </h6>
                            <table class="table table-sm" style="font-size: 0.88rem;">
                                <tr>
                                    <td style="color: #6b7a8d;">NSSF</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcNSSF">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">SHIF (employee)</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcSHIF">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">PAYE Tax</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcPAYE">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">Housing Levy (1.5%)</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcHousingLevy">0.00</td>
                                </tr>
                                <tr data-accountant-hidden>
                                    <td style="color: #6b7a8d;">Employer NSSF (school cost)</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcEmployerNSSF">0.00</td>
                                </tr>
                                <tr data-accountant-hidden>
                                    <td style="color: #6b7a8d;">Employer Housing Levy (school cost)</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcEmployerHousingLevy">0.00</td>
                                </tr>
                                <tr style="background: rgba(201, 168, 76, 0.08);" data-review-only>
                                    <td style="font-weight: 700; color: #9a7d2e;"><i class="bi bi-mortarboard me-1"></i>Children Fees</td>
                                    <td class="text-end" style="font-weight: 700; color: #9a7d2e;" id="calcChildrenFees">0.00</td>
                                </tr>
                                <tr data-review-only>
                                    <td style="color: #6b7a8d;">Other Deductions</td>
                                    <td class="text-end">
                                        <input type="number" id="otherDeductions" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" data-kw-onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr style="background: rgba(192, 57, 43, 0.06);">
                                    <td style="font-weight: 800; color: var(--payroll-danger);">Total Deductions</td>
                                    <td class="text-end" style="font-weight: 800; color: var(--payroll-danger);" id="calcTotalDeductions">0.00</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Net Pay -->
                    <div class="net-pay-banner">
                        <h5>Net Pay</h5>
                        <div class="amount" id="calcNetPay">KES 0.00</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--payroll-line); padding: 16px 28px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Cancel</button>
                <button type="button" class="btn" id="processPayrollBtn" data-kw-onclick="PayrollManagerController.submitPayroll()" disabled
                        style="background: var(--payroll-gold); color: #0B1426; border: none; border-radius: 10px; font-weight: 700; padding: 10px 24px;" data-year-write>
                    <i class="bi bi-check-lg-circle me-1"></i><span id="processPayrollButtonLabel">Prepare Draft</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Payroll Modal -->
<div class="modal fade" id="bulkPayrollModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-people-cog me-2"></i>Prepare Bulk Payroll</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select id="bulkPayrollMonth" class="form-select" data-kw-onchange="PayrollManagerController.prepareBulkPayrollRows()">
                            <option value="1">January</option>
                            <option value="2">February</option>
                            <option value="3">March</option>
                            <option value="4">April</option>
                            <option value="5">May</option>
                            <option value="6">June</option>
                            <option value="7">July</option>
                            <option value="8">August</option>
                            <option value="9">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Year</label>
                        <input type="number" id="bulkPayrollYear" class="form-control" value="<?= date('Y') ?>" data-kw-onchange="PayrollManagerController.prepareBulkPayrollRows()">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="alert alert-info mb-0 w-100" style="font-size: 0.85rem;">
                            <i class="bi bi-info-circle me-1"></i>
                            Bulk payroll uses each staff member's approved statutory configuration. Review staff-child fee instructions in Single Payroll before processing deductions.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-dark" data-kw-onclick="PayrollManagerController.toggleBulkStaffSelection(true)">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-kw-onclick="PayrollManagerController.toggleBulkStaffSelection(false)">Clear</button>
                    </div>
                    <strong id="bulkPayrollSummary">0 selected · KES 0.00 net</strong>
                </div>

                <div class="table-responsive" style="max-height: 55vh; overflow: auto; border: 1px solid rgba(11,20,38,0.08); border-radius: 12px;">
                    <table class="table table-sm mb-0">
                        <thead style="position: sticky; top: 0; z-index: 1; background: #0B1426; color: #fff;">
                            <tr>
                                <th style="width: 42px;"></th>
                                <th scope="col">Staff</th>
                                <th scope="col">Position</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allowances</th>
                                <th class="text-end">Statutory Ded.</th>
                                <th scope="col">Statutory Items</th>
                                <th class="text-end">Other Ded.</th>
                                <th class="text-end">Housing Levy</th>
                                <th class="text-end">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody id="bulkPayrollTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn" data-kw-onclick="PayrollManagerController.submitBulkPayroll()"
                        style="background: var(--payroll-gold); color: #0B1426; border: none; border-radius: 10px; font-weight: 700;" data-year-write>
                    <i class="bi bi-check-lg-circle me-1"></i> Process Selected Payrolls
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Payslip Modal -->
<div class="modal fade" id="viewPayslipModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-invoice me-2"></i>Detailed Payslip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Payslip content loaded here -->
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--payroll-line); padding: 16px 28px;">
                <button type="button" class="btn" data-kw-onclick="PayrollManagerController.printPayslip()"
                        style="background: transparent; border: 1px solid var(--payroll-navy); color: var(--payroll-navy); border-radius: 10px; font-weight: 600;">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
                <button type="button" class="btn" data-kw-onclick="PayrollManagerController.downloadPayslip()"
                        style="background: transparent; border: 1px solid var(--payroll-success); color: var(--payroll-success); border-radius: 10px; font-weight: 600;">
                    <i class="bi bi-download me-1"></i> Download PDF
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payroll Confirmation Modal -->
<div class="modal fade" id="payrollConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0" id="payrollConfirmHeader" style="background: linear-gradient(135deg, #0d4f2a, #198754); color: white;">
                <h5 class="modal-title" id="payrollConfirmTitle">
                    <i class="bi bi-question-circle-fill"></i>
                    <span id="payrollConfirmTitleText">Confirm</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p id="payrollConfirmMessage" class="mb-0" style="font-size: 1rem;"></p>
            </div>
            <div class="modal-footer border-0" style="background: #f5f5dc;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn text-white" id="payrollConfirmOk" style="background: #0d4f2a;" data-year-write>
                    <i class="bi bi-check-circle"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mixed payout plan and release -->
<div class="modal fade" id="payrollPayoutPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0" style="background: var(--pr-ink); color: white;">
                <div>
                    <h5 class="modal-title"><i class="bi bi-signpost-split me-2"></i>Plan Payroll Payout</h5>
                    <div class="small" id="payoutPlanScope" style="color: rgba(255,255,255,.76);"></div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="payout-plan-intro mb-3">
                    Route each employee's net salary through one or more cash, bank or M-Pesa legs. Each leg keeps its own source account, amount, reference and status.
                </div>
                <div class="payout-plan-list" id="payoutPlanList"></div>
            </div>
            <div class="modal-footer border-0" style="background: var(--pr-wash);">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-primary" id="savePayrollPayoutPlansBtn" data-kw-onclick="PayrollManagerController.savePayoutPlans()">
                    <i class="bi bi-check2-square me-1"></i> Save payout routes
                </button>
                <button type="button" class="btn btn-primary" id="releasePayrollPayoutsBtn" data-kw-onclick="PayrollManagerController.releaseSelectedPayouts()">
                    <i class="bi bi-send-check me-1"></i> Release selected payouts
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Manual cash receipt confirmation -->
<div class="modal fade" id="payrollCashConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Confirm cash receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="cashConfirmStaffName"></p>
                <label class="form-label" for="cashConfirmReferenceInput">Receipt or cheque reference</label>
                <input type="text" class="form-control" id="cashConfirmReferenceInput" maxlength="150" placeholder="Enter the receipt or cheque number" autocomplete="off">
                <div class="form-text">The confirmation is recorded against this cash leg and cannot be reused for another payment.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="cashConfirmSubmitBtn" data-kw-onclick="PayrollManagerController.confirmCashPayout()">
                    Confirm receipt
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="compensationModal" tabindex="-1" aria-labelledby="compensationModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title" id="compensationModalTitle">Salary &amp; payroll awards</h5><div class="small text-muted">Role rates are defaults. Individual amounts are exceptions. Awards apply to each selected staff member in each selected month.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#roleSalaryTab" type="button">Role salary rates</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#individualSalaryTab" type="button">Individual salary</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#payrollAwardsTab" type="button">Department awards &amp; deductions</button></li>
      </ul>
      <div class="tab-content">
        <section class="tab-pane fade show active" id="roleSalaryTab">
          <form id="roleSalaryRateForm" class="row g-3"><div class="col-md-5"><label class="form-label" for="salaryRateRole">Primary role</label><select class="form-select" id="salaryRateRole" required></select></div><div class="col-md-3"><label class="form-label" for="salaryRateAmount">Monthly gross salary (KES)</label><input class="form-control" id="salaryRateAmount" type="number" min="0.01" step="0.01" required></div><div class="col-md-2"><label class="form-label" for="salaryRateFrom">Effective from</label><input class="form-control" id="salaryRateFrom" type="date" required></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100" type="submit" data-year-write>Save rate</button></div></form>
          <div class="table-responsive mt-4"><table class="table table-sm"><thead><tr><th>Role</th><th>Current configured rate</th><th>Effective from</th><th>To</th></tr></thead><tbody id="roleSalaryRatesBody"></tbody></table></div>
        </section>
        <section class="tab-pane fade" id="individualSalaryTab">
          <form id="individualSalaryForm" class="row g-3 align-items-end"><div class="col-md-5"><label class="form-label" for="individualSalaryStaff">Staff member</label><select class="form-select" id="individualSalaryStaff" required></select></div><div class="col-md-3"><label class="form-label" for="individualSalaryAmount">Individual gross salary (KES)</label><input class="form-control" id="individualSalaryAmount" type="number" min="0.01" step="0.01" required></div><div class="col-md-2"><label class="form-label" for="individualSalaryFrom">Effective from</label><input class="form-control" id="individualSalaryFrom" type="date" required></div><div class="col-md-2"><button class="btn btn-primary w-100" type="submit" data-year-write>Save amount</button></div><div class="col-12"><button class="btn btn-outline-secondary btn-sm" type="button" id="clearIndividualSalaryOverride">End override from selected date</button><span class="small text-muted ms-2" id="individualSalaryRoleHint"></span></div></form>
          <div class="table-responsive mt-4"><table class="table table-sm"><thead><tr><th>Staff</th><th>Individual monthly salary</th><th>Effective from</th><th>To</th></tr></thead><tbody id="individualSalaryHistoryBody"></tbody></table></div>
        </section>
        <section class="tab-pane fade" id="payrollAwardsTab">
          <form id="compensationAwardForm" class="row g-3">
            <div class="col-md-3"><label class="form-label" for="awardKind">Award action</label><select class="form-select" id="awardKind"><option value="allowance">Add allowance / bonus</option><option value="deduction">Deduct amount</option></select></div>
            <div class="col-md-3"><label class="form-label" for="awardName">Description</label><input class="form-control" id="awardName" maxlength="160" required></div>
            <div class="col-md-3"><label class="form-label" for="awardType">Category</label><select class="form-select" id="awardType"><option value="other">Other</option><option value="bonus">Bonus</option><option value="responsibility">Responsibility</option><option value="hardship">Hardship</option><option value="transport">Transport</option><option value="housing">Housing</option><option value="other_deduction">Other deduction</option></select></div>
            <div class="col-md-3"><label class="form-label" for="awardAmount">Amount per person, per month (KES)</label><input class="form-control" id="awardAmount" type="number" min="0.01" step="0.01" required></div>
            <div class="col-md-4"><label class="form-label" for="awardSelectionMode">Recipients</label><select class="form-select" id="awardSelectionMode"><option value="department_all">All active members of a department</option><option value="selected_staff">Selected staff members</option></select></div>
            <div class="col-md-4" id="awardDepartmentWrap"><label class="form-label" for="awardDepartment">Department</label><select class="form-select" id="awardDepartment"></select></div>
            <div class="col-md-4 d-none" id="awardStaffWrap"><label class="form-label" for="awardStaff">Select staff</label><select class="form-select" id="awardStaff" multiple size="4"></select></div>
            <div class="col-12"><label class="form-label d-block">Affected months</label><div id="awardMonths" class="d-flex flex-wrap gap-2"></div><label class="form-label mt-2" for="awardYear">Payroll year</label><select class="form-select" id="awardYear" style="max-width:180px"></select></div>
            <div class="col-12"><button class="btn btn-primary" type="submit" data-year-write>Schedule award for selected months</button><span class="small text-muted ms-2">Recipients are snapshotted when scheduled. Changes affect only the selected months.</span></div>
          </form>
          <div class="table-responsive mt-4"><table class="table table-sm"><thead><tr><th>Type</th><th>Description</th><th>Recipients</th><th>Department</th><th>Amount / month</th><th>Months</th><th>Status</th><th></th></tr></thead><tbody id="compensationAwardHistoryBody"></tbody></table></div>
        </section>
      </div>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/payroll_manager.js'); ?>
