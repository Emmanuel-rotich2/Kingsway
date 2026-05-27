<?php
/**
 * Manage Payrolls Page — Executive Finance Ledger
 * Enhanced with staff children fee deduction during payroll processing
 * All logic in js/pages/payroll_manager.js
 */
?>

<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;800;900&family=DM+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    .director-payroll-page {
        --payroll-ink: #1a1f2e;
        --payroll-navy: #0B1426;
        --payroll-deep: #101d36;
        --payroll-gold: #C9A84C;
        --payroll-gold-light: #e8d48b;
        --payroll-paper: #faf8f4;
        --payroll-warm: #f5f1ea;
        --payroll-line: rgba(11, 20, 38, 0.08);
        --payroll-blue-soft: #3a5a8c;
        --payroll-success: #1a7a4c;
        --payroll-danger: #c0392b;
        font-family: 'DM Sans', sans-serif;
        padding: 0;
        background: var(--payroll-paper);
        color: var(--payroll-ink);
        min-height: 100vh;
    }

    /* Grain overlay */
    .director-payroll-page::before {
        content: '';
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
        pointer-events: none;
        z-index: 0;
    }

    .director-payroll-page > * {
        position: relative;
        z-index: 1;
    }

    /* ---- HERO ---- */
    .director-payroll-page .payroll-hero {
        border-radius: 0 0 28px 28px;
        padding: 36px 32px 32px;
        background:
            radial-gradient(ellipse at 85% 15%, rgba(201, 168, 76, 0.18), transparent 45%),
            linear-gradient(165deg, #0B1426 0%, #132244 55%, #1a3060 100%);
        color: #fff;
        box-shadow: 0 20px 60px rgba(11, 20, 38, 0.25);
        margin-bottom: 28px;
    }

    .director-payroll-page .payroll-eyebrow {
        display: inline-flex;
        gap: 8px;
        align-items: center;
        padding: 6px 14px;
        border-radius: 999px;
        background: rgba(201, 168, 76, 0.15);
        border: 1px solid rgba(201, 168, 76, 0.35);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: var(--payroll-gold-light);
    }

    .director-payroll-page .payroll-title {
        margin: 16px 0 6px;
        font-family: 'Playfair Display', Georgia, serif;
        font-size: clamp(2rem, 4.5vw, 3.8rem);
        font-weight: 800;
        line-height: 1.0;
        letter-spacing: -0.03em;
        background: linear-gradient(135deg, #fff 60%, var(--payroll-gold-light));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .director-payroll-page .payroll-subtitle {
        font-family: 'DM Sans', sans-serif;
        color: rgba(255, 255, 255, 0.55);
        font-size: 0.92rem;
        font-weight: 500;
        margin: 0;
    }

    .director-payroll-page .payroll-actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: flex-end;
        gap: 10px;
        align-items: center;
    }

    .director-payroll-page .payroll-action-btn {
        border: 0;
        border-radius: 12px;
        padding: 10px 18px;
        font-family: 'DM Sans', sans-serif;
        font-weight: 700;
        font-size: 0.82rem;
        letter-spacing: 0.02em;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .director-payroll-page .payroll-action-btn.primary {
        background: var(--payroll-gold);
        color: #0B1426;
        box-shadow: 0 6px 20px rgba(201, 168, 76, 0.35);
    }

    .director-payroll-page .payroll-action-btn.primary:hover {
        background: var(--payroll-gold-light);
        transform: translateY(-1px);
        box-shadow: 0 8px 25px rgba(201, 168, 76, 0.45);
    }

    .director-payroll-page .payroll-action-btn.ghost {
        background: rgba(255, 255, 255, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(4px);
    }

    .director-payroll-page .payroll-action-btn.ghost:hover {
        background: rgba(255, 255, 255, 0.14);
        border-color: rgba(255, 255, 255, 0.3);
        color: #fff;
    }

    /* ---- METRIC BOARD ---- */
    .director-payroll-page .payroll-board {
        display: grid;
        grid-template-columns: minmax(280px, 1.4fr) repeat(3, minmax(180px, 0.8fr));
        gap: 16px;
        margin: 0 28px 24px;
    }

    .director-payroll-page .payroll-metric {
        min-height: 130px;
        border: 1px solid var(--payroll-line);
        border-radius: 18px;
        padding: 22px 24px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(11, 20, 38, 0.04);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .director-payroll-page .payroll-metric::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, transparent, var(--payroll-gold), transparent);
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .director-payroll-page .payroll-metric:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 28px rgba(11, 20, 38, 0.08);
    }

    .director-payroll-page .payroll-metric:hover::after {
        opacity: 1;
    }

    .director-payroll-page .payroll-metric.featured {
        background: linear-gradient(155deg, #0B1426 0%, #152747 60%, #1a3465 100%);
        color: #fff;
        border-color: rgba(201, 168, 76, 0.2);
        box-shadow: 0 8px 30px rgba(11, 20, 38, 0.2);
    }

    .director-payroll-page .payroll-metric.featured::after {
        background: linear-gradient(90deg, transparent, var(--payroll-gold), transparent);
        opacity: 0.6;
    }

    .director-payroll-page .metric-icon {
        width: 40px;
        height: 40px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        background: rgba(201, 168, 76, 0.1);
        color: var(--payroll-gold);
        margin-bottom: 12px;
    }

    .director-payroll-page .featured .metric-icon {
        background: rgba(201, 168, 76, 0.2);
        color: var(--payroll-gold-light);
    }

    .director-payroll-page .metric-label {
        font-family: 'DM Sans', sans-serif;
        color: #8895a7;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
    }

    .director-payroll-page .featured .metric-label {
        color: rgba(255, 255, 255, 0.6);
    }

    .director-payroll-page .metric-value {
        margin-top: 6px;
        font-family: 'Playfair Display', Georgia, serif;
        font-size: clamp(1.6rem, 2.8vw, 2.4rem);
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--payroll-ink);
    }

    .director-payroll-page .featured .metric-value {
        color: #fff;
        font-size: clamp(1.8rem, 3.2vw, 2.8rem);
    }

    .director-payroll-page .metric-note {
        font-size: 0.75rem;
        color: #a0aab5;
        margin-top: 4px;
    }

    .director-payroll-page .featured .metric-note {
        color: rgba(255, 255, 255, 0.45);
    }

    /* ---- PANELS ---- */
    .director-payroll-page .payroll-panel {
        border: 1px solid var(--payroll-line);
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 4px 16px rgba(11, 20, 38, 0.04);
        overflow: hidden;
        margin: 0 28px 20px;
    }

    .director-payroll-page .payroll-panel-header {
        padding: 18px 24px;
        background: linear-gradient(90deg, var(--payroll-warm), #fff);
        border-bottom: 1px solid var(--payroll-line);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .director-payroll-page .payroll-panel-header h5 {
        font-family: 'Playfair Display', Georgia, serif;
        font-weight: 700;
        font-size: 1.05rem;
        color: var(--payroll-ink);
        margin: 0;
    }

    /* ---- FILTERS ---- */
    .director-payroll-page .filter-row {
        padding: 20px 24px;
        display: flex;
        gap: 14px;
        flex-wrap: wrap;
        align-items: flex-end;
    }

    .director-payroll-page .filter-group {
        flex: 1;
        min-width: 160px;
    }

    .director-payroll-page .form-label {
        font-family: 'DM Sans', sans-serif;
        color: #6b7a8d;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .director-payroll-page .form-control,
    .director-payroll-page .form-select {
        border-color: rgba(11, 20, 38, 0.1);
        border-radius: 10px;
        min-height: 42px;
        background-color: var(--payroll-paper);
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--payroll-ink);
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .director-payroll-page .form-control:focus,
    .director-payroll-page .form-select:focus {
        border-color: var(--payroll-gold);
        box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.12);
    }

    /* ---- TABLE ---- */
    .director-payroll-page #payrollTable {
        margin: 0;
    }

    .director-payroll-page #payrollTable thead th {
        background: var(--payroll-deep);
        color: rgba(255, 255, 255, 0.82);
        border: 0;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        padding: 14px 14px;
        white-space: nowrap;
    }

    .director-payroll-page #payrollTable thead th:first-child {
        padding-left: 24px;
    }

    .director-payroll-page #payrollTable thead th:last-child {
        padding-right: 24px;
    }

    .director-payroll-page #payrollTable tbody td {
        padding: 13px 14px;
        border-color: rgba(11, 20, 38, 0.05);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.85rem;
        font-weight: 500;
        vertical-align: middle;
    }

    .director-payroll-page #payrollTable tbody td:first-child {
        padding-left: 24px;
        font-weight: 700;
    }

    .director-payroll-page #payrollTable tbody td:last-child {
        padding-right: 24px;
    }

    .director-payroll-page #payrollTable tbody tr:nth-child(even) {
        background: rgba(245, 241, 234, 0.5);
    }

    .director-payroll-page #payrollTable tbody tr:hover {
        background: rgba(201, 168, 76, 0.06);
    }

    .director-payroll-page .table-amount {
        font-variant-numeric: tabular-nums;
        text-align: right;
        font-weight: 600;
    }

    .director-payroll-page .table-amount.negative {
        color: var(--payroll-danger);
    }

    /* Status badges */
    .director-payroll-page .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .director-payroll-page .status-badge.paid {
        background: rgba(26, 122, 76, 0.1);
        color: #1a7a4c;
    }

    .director-payroll-page .status-badge.pending {
        background: rgba(201, 168, 76, 0.12);
        color: #9a7d2e;
    }

    .director-payroll-page .status-badge.processing {
        background: rgba(58, 90, 140, 0.1);
        color: #3a5a8c;
    }

    .director-payroll-page .status-badge.cancelled {
        background: rgba(192, 57, 43, 0.08);
        color: #c0392b;
    }

    /* Action buttons in table */
    .director-payroll-page .table-action-btn {
        border: none;
        background: transparent;
        padding: 5px 8px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.82rem;
        transition: background 0.15s ease;
        color: var(--payroll-blue-soft);
    }

    .director-payroll-page .table-action-btn:hover {
        background: rgba(58, 90, 140, 0.1);
    }

    .director-payroll-page .table-action-btn.approve {
        color: var(--payroll-success);
    }

    .director-payroll-page .table-action-btn.approve:hover {
        background: rgba(26, 122, 76, 0.1);
    }

    /* ---- MODALS ---- */
    .director-payroll-page .modal-content,
    .modal-content {
        border: none;
        border-radius: 18px;
        box-shadow: 0 20px 60px rgba(11, 20, 38, 0.2);
        overflow: hidden;
    }

    .director-payroll-page .modal-header,
    #processPayrollModal .modal-header,
    #viewPayslipModal .modal-header {
        background: linear-gradient(155deg, #0B1426 0%, #152747 100%);
        border: none;
        padding: 20px 28px;
    }

    .director-payroll-page .modal-title,
    #processPayrollModal .modal-title,
    #viewPayslipModal .modal-title {
        font-family: 'Playfair Display', Georgia, serif;
        font-weight: 700;
        font-size: 1.15rem;
        color: #fff;
    }

    #processPayrollModal .modal-body,
    #viewPayslipModal .modal-body {
        padding: 28px;
    }

    /* Step headers in modal */
    .director-payroll-page .step-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }

    .director-payroll-page .step-number {
        width: 32px;
        height: 32px;
        border-radius: 10px;
        background: var(--payroll-navy);
        color: var(--payroll-gold);
        font-family: 'Playfair Display', serif;
        font-weight: 800;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .director-payroll-page .step-title {
        font-family: 'DM Sans', sans-serif;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--payroll-ink);
    }

    /* Staff info card */
    .director-payroll-page #staffInfoCard {
        border: 1px solid rgba(201, 168, 76, 0.2);
        border-radius: 14px;
        background: linear-gradient(135deg, var(--payroll-warm), #fff);
    }

    .director-payroll-page #staffInfoCard .card-body {
        padding: 20px 24px;
    }

    /* Net pay banner */
    .director-payroll-page .net-pay-banner {
        background: linear-gradient(135deg, #0B1426, #1a3465);
        border-radius: 14px;
        padding: 20px;
        text-align: center;
        color: #fff;
        margin-top: 16px;
    }

    .director-payroll-page .net-pay-banner h5 {
        font-family: 'DM Sans', sans-serif;
        font-weight: 700;
        font-size: 0.75rem;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 4px;
    }

    .director-payroll-page .net-pay-banner .amount {
        font-family: 'Playfair Display', Georgia, serif;
        font-size: 2rem;
        font-weight: 800;
        color: var(--payroll-gold-light);
    }

    /* ---- PAGINATION ---- */
    .director-payroll-page .card-footer {
        background: var(--payroll-warm);
        border-top: 1px solid var(--payroll-line);
        padding: 14px 24px;
    }

    .director-payroll-page .pagination .page-link {
        border: none;
        border-radius: 8px;
        margin: 0 2px;
        font-family: 'DM Sans', sans-serif;
        font-weight: 600;
        font-size: 0.82rem;
        color: var(--payroll-ink);
        background: transparent;
        padding: 6px 12px;
    }

    .director-payroll-page .pagination .page-item.active .page-link {
        background: var(--payroll-navy);
        color: #fff;
    }

    .director-payroll-page .pagination .page-link:hover {
        background: rgba(201, 168, 76, 0.12);
        color: var(--payroll-ink);
    }

    /* ---- RESPONSIVE ---- */
    @media (max-width: 1100px) {
        .director-payroll-page .payroll-board {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin: 0 20px 20px;
        }
        .director-payroll-page .payroll-panel {
            margin: 0 20px 16px;
        }
    }

    @media (max-width: 720px) {
        .director-payroll-page .payroll-hero {
            padding: 24px 18px;
            border-radius: 0 0 20px 20px;
        }
        .director-payroll-page .payroll-board {
            grid-template-columns: 1fr;
            margin: 0 14px 16px;
            gap: 12px;
        }
        .director-payroll-page .payroll-panel {
            margin: 0 14px 14px;
        }
        .director-payroll-page .payroll-actions {
            justify-content: flex-start;
            margin-top: 16px;
        }
        .director-payroll-page .filter-row {
            flex-direction: column;
            gap: 10px;
        }
        .director-payroll-page .filter-group {
            min-width: 100%;
        }
    }
</style>

<div class="container-fluid director-payroll-page">
    <!-- Executive Header -->
    <section class="payroll-hero">
        <div class="row g-4 align-items-end">
            <div class="col-lg-7">
                <div class="payroll-eyebrow"><i class="fas fa-shield-alt"></i> Director Payroll Control</div>
                <h1 class="payroll-title">Payroll Governance</h1>
                <p class="payroll-subtitle">Approve staff pay, child-fee deductions, and payment status from one executive ledger.</p>
            </div>
            <div class="col-lg-5">
                <div class="payroll-actions">
                    <button class="payroll-action-btn ghost" onclick="PayrollManagerController.exportCsv()">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </button>
                    <button class="payroll-action-btn ghost" onclick="PayrollManagerController.printPayrollReport()">
                        <i class="fas fa-print me-1"></i> Print PDF
                    </button>
                    <button class="payroll-action-btn ghost" onclick="PayrollManagerController.refresh()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh
                    </button>
                    <button class="payroll-action-btn primary" onclick="PayrollManagerController.showBulkPayrollModal()">
                        <i class="fas fa-users-cog me-1"></i> Bulk Payroll
                    </button>
                    <button class="payroll-action-btn primary" onclick="PayrollManagerController.showProcessPayrollModal()">
                        <i class="fas fa-plus-circle me-1"></i> Single Payroll
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Director Payroll Board -->
    <div class="payroll-board">
        <div class="payroll-metric featured">
            <div class="metric-icon"><i class="fas fa-coins"></i></div>
            <div class="metric-label">This Month's Net Pay</div>
            <div class="metric-value" id="statThisMonthNet">KES --</div>
            <div class="metric-note">Total payroll exposure for the selected period.</div>
        </div>
        <div class="payroll-metric">
            <div class="metric-icon"><i class="fas fa-users"></i></div>
            <div class="metric-label">Total Staff</div>
            <div class="metric-value" id="statTotalStaff">--</div>
            <div class="metric-note">Eligible payroll staff</div>
        </div>
        <div class="payroll-metric">
            <div class="metric-icon"><i class="fas fa-child"></i></div>
            <div class="metric-label">Staff With Children</div>
            <div class="metric-value" id="statStaffWithChildren">--</div>
            <div class="metric-note">Fee deduction candidates</div>
        </div>
        <div class="payroll-metric">
            <div class="metric-icon"><i class="fas fa-graduation-cap"></i></div>
            <div class="metric-label">Children Fees Deducted</div>
            <div class="metric-value" id="statChildrenFees">KES --</div>
            <div class="metric-note">Offset against student balances</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="payroll-panel">
        <div class="filter-row">
            <div class="filter-group">
                <label class="form-label">Month</label>
                <select id="filterMonth" class="form-select" onchange="PayrollManagerController.applyFilters()">
                    <option value="">All Months</option>
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
                <label class="form-label">Year</label>
                <select id="filterYear" class="form-select" onchange="PayrollManagerController.applyFilters()">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="form-label">Status</label>
                <select id="filterStatus" class="form-select" onchange="PayrollManagerController.applyFilters()">
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
                       oninput="PayrollManagerController.applyFilters()">
            </div>
        </div>
    </div>

    <!-- Payroll Records Table -->
    <div class="payroll-panel">
        <div class="payroll-panel-header">
            <h5><i class="fas fa-list me-2" style="color: var(--payroll-gold)"></i>Payroll Records</h5>
            <span class="status-badge pending" id="payrollCount">0 records</span>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" id="payrollTable">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Period</th>
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
</div>

<!-- Process Payroll Modal -->
<div class="modal fade" id="processPayrollModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-money-check-alt me-2"></i>Process Staff Payroll</h5>
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
                            <select id="payrollStaffSelect" class="form-select" onchange="PayrollManagerController.onStaffSelected()">
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
                                    <p class="mb-1"><strong>Children in School:</strong> <span id="staffInfoChildrenCount" class="status-badge processing">0</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Children & Fee Deductions -->
                <div id="payrollStep2" class="d-none">
                    <hr style="border-color: var(--payroll-line); margin: 20px 0;">
                    <div class="step-header">
                        <div class="step-number">2</div>
                        <div class="step-title">Children Fee Deductions</div>
                        <span class="status-badge processing" id="childrenCountBadge">0 children</span>
                    </div>

                    <div style="background: rgba(201, 168, 76, 0.08); border: 1px solid rgba(201, 168, 76, 0.2); border-radius: 12px; padding: 14px 18px; margin-bottom: 16px;">
                        <i class="fas fa-info-circle me-2" style="color: var(--payroll-gold)"></i>
                        <span style="font-size: 0.88rem; color: var(--payroll-ink);">Configure how much to deduct from salary for each child's school fees.</span>
                    </div>

                    <div id="childrenFeesList"></div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div style="border: 1px solid rgba(58, 90, 140, 0.15); border-radius: 14px; padding: 18px; text-align: center; background: rgba(58, 90, 140, 0.04);">
                                <div style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #8895a7;">Total Fees Outstanding</div>
                                <div style="font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: var(--payroll-blue-soft); margin-top: 4px;" id="totalChildrenFees">KES 0.00</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div style="border: 1px solid rgba(26, 122, 76, 0.15); border-radius: 14px; padding: 18px; text-align: center; background: rgba(26, 122, 76, 0.04);">
                                <div style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: #8895a7;">Total to Deduct This Month</div>
                                <div style="font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: var(--payroll-success); margin-top: 4px;" id="totalDeductionAmount">KES 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Salary Calculation -->
                <div id="payrollStep3" class="d-none">
                    <hr style="border-color: var(--payroll-line); margin: 20px 0;">
                    <div class="step-header">
                        <div class="step-number">3</div>
                        <div class="step-title">Salary Breakdown</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <h6 style="color: var(--payroll-success); font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.82rem; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 12px;">
                                <i class="fas fa-arrow-up me-1"></i> Earnings
                            </h6>
                            <table class="table table-sm" style="font-size: 0.88rem;">
                                <tr>
                                    <td style="color: #6b7a8d;">Basic Salary</td>
                                    <td class="text-end" style="font-weight: 700;" id="calcBasicSalary">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">House Allowance</td>
                                    <td class="text-end">
                                        <input type="number" id="houseAllowance" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">Transport Allowance</td>
                                    <td class="text-end">
                                        <input type="number" id="transportAllowance" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">Other Allowances</td>
                                    <td class="text-end">
                                        <input type="number" id="otherAllowances" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
                                    </td>
                                </tr>
                                <tr style="background: rgba(26, 122, 76, 0.06);">
                                    <td style="font-weight: 800; color: var(--payroll-success);">Gross Salary</td>
                                    <td class="text-end" style="font-weight: 800; color: var(--payroll-success);" id="calcGrossSalary">0.00</td>
                                </tr>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <h6 style="color: var(--payroll-danger); font-family: 'DM Sans', sans-serif; font-weight: 700; font-size: 0.82rem; letter-spacing: 0.05em; text-transform: uppercase; margin-bottom: 12px;">
                                <i class="fas fa-arrow-down me-1"></i> Deductions
                            </h6>
                            <table class="table table-sm" style="font-size: 0.88rem;">
                                <tr>
                                    <td style="color: #6b7a8d;">NSSF</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcNSSF">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">NHIF</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcNHIF">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">PAYE Tax</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcPAYE">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">Housing Levy (1.5%)</td>
                                    <td class="text-end" style="font-weight: 600;" id="calcHousingLevy">0.00</td>
                                </tr>
                                <tr style="background: rgba(201, 168, 76, 0.08);">
                                    <td style="font-weight: 700; color: #9a7d2e;"><i class="fas fa-graduation-cap me-1"></i>Children Fees</td>
                                    <td class="text-end" style="font-weight: 700; color: #9a7d2e;" id="calcChildrenFees">0.00</td>
                                </tr>
                                <tr>
                                    <td style="color: #6b7a8d;">Other Deductions</td>
                                    <td class="text-end">
                                        <input type="number" id="otherDeductions" class="form-control form-control-sm text-end"
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()" style="border-radius: 8px;">
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
                <button type="button" class="btn" id="processPayrollBtn" onclick="PayrollManagerController.submitPayroll()" disabled
                        style="background: var(--payroll-gold); color: #0B1426; border: none; border-radius: 10px; font-weight: 700; padding: 10px 24px;">
                    <i class="fas fa-check-circle me-1"></i> Process Payroll
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
                <h5 class="modal-title"><i class="fas fa-users-cog me-2"></i>Prepare Bulk Payroll</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Month</label>
                        <select id="bulkPayrollMonth" class="form-select" onchange="PayrollManagerController.prepareBulkPayrollRows()">
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
                        <input type="number" id="bulkPayrollYear" class="form-control" value="<?= date('Y') ?>" onchange="PayrollManagerController.prepareBulkPayrollRows()">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="alert alert-info mb-0 w-100" style="font-size: 0.85rem;">
                            <i class="fas fa-info-circle me-1"></i>
                            Bulk payroll excludes child-fee deductions. Use Single Payroll when fee deductions need review.
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick="PayrollManagerController.toggleBulkStaffSelection(true)">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="PayrollManagerController.toggleBulkStaffSelection(false)">Clear</button>
                    </div>
                    <strong id="bulkPayrollSummary">0 selected · KES 0.00 net</strong>
                </div>

                <div class="table-responsive" style="max-height: 55vh; overflow: auto; border: 1px solid rgba(11,20,38,0.08); border-radius: 12px;">
                    <table class="table table-sm mb-0">
                        <thead style="position: sticky; top: 0; z-index: 1; background: #0B1426; color: #fff;">
                            <tr>
                                <th style="width: 42px;"></th>
                                <th>Staff</th>
                                <th>Position</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allowances</th>
                                <th class="text-end">Statutory Ded.</th>
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
                <button type="button" class="btn" onclick="PayrollManagerController.submitBulkPayroll()"
                        style="background: var(--payroll-gold); color: #0B1426; border: none; border-radius: 10px; font-weight: 700;">
                    <i class="fas fa-check-circle me-1"></i> Process Selected Payrolls
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Payslip Modal -->
<div class="modal fade" id="viewPayslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Detailed Payslip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Payslip content loaded here -->
            </div>
            <div class="modal-footer" style="border-top: 1px solid var(--payroll-line); padding: 16px 28px;">
                <button type="button" class="btn" onclick="PayrollManagerController.printPayslip()"
                        style="background: transparent; border: 1px solid var(--payroll-navy); color: var(--payroll-navy); border-radius: 10px; font-weight: 600;">
                    <i class="fas fa-print me-1"></i> Print
                </button>
                <button type="button" class="btn" onclick="PayrollManagerController.downloadPayslip()"
                        style="background: transparent; border: 1px solid var(--payroll-success); color: var(--payroll-success); border-radius: 10px; font-weight: 600;">
                    <i class="fas fa-download me-1"></i> Download PDF
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Payroll Confirmation Modal -->
<div class="modal fade" id="payrollConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
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
                <button type="button" class="btn text-white" id="payrollConfirmOk" style="background: #0d4f2a;">
                    <i class="bi bi-check-circle"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payroll Payment Mode Modal -->
<div class="modal fade" id="payrollPaymentModeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #0d4f2a, #198754); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-cash-coin"></i> Record Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Mode</label>
                    <div class="row g-2" id="paymentModeOptions">
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="paymentMode" id="modeBank" value="bank" checked>
                            <label class="btn btn-outline-success w-100 py-3" for="modeBank">
                                <i class="bi bi-bank"></i><br>Bank
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="paymentMode" id="modeCash" value="cash">
                            <label class="btn btn-outline-success w-100 py-3" for="modeCash">
                                <i class="bi bi-cash-stack"></i><br>Cash
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="paymentMode" id="modeMpesa" value="mpesa">
                            <label class="btn btn-outline-success w-100 py-3" for="modeMpesa">
                                <i class="bi bi-phone"></i><br>M-Pesa
                            </label>
                        </div>
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="paymentMode" id="modeAirtel" value="airtel_money">
                            <label class="btn btn-outline-success w-100 py-3" for="modeAirtel">
                                <i class="bi bi-phone"></i><br>Airtel Money
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Payment Reference (Optional)</label>
                    <input type="text" id="paymentReferenceInput" class="form-control" placeholder="e.g. TRX-12345 or Cheque No.">
                </div>
            </div>
            <div class="modal-footer border-0" style="background: #f5f5dc;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn text-white" id="payrollPaymentConfirmOk" style="background: #0d4f2a;">
                    <i class="bi bi-check-circle"></i> Mark as Paid
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>/js/pages/payroll_manager.js?v=<?= time() ?>"></script>
