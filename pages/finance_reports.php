<?php
/**
 * Finance Reports Page
 * HTML structure only - logic will be in js/pages/finance_reports.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-secondary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-chart-line"></i> Financial Reports</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="exportReportBtn">
                    <i class="bi bi-download"></i> Export Report
                </button>
                <button class="btn btn-outline-light btn-sm" id="printReportBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Report Type Selection -->
        <div class="row mb-4">
            <div class="col-md-3">
                <select class="form-select" id="reportType">
                    <option value="income_statement">Income Statement</option>
                    <option value="balance_sheet">Balance Sheet</option>
                    <option value="cash_flow">Cash Flow Statement</option>
                    <option value="fee_collection">Fee Collection Report</option>
                    <option value="expense_summary">Expense Summary</option>
                    <option value="student_accounts">Student Account Status</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="periodType">
                    <option value="term">By Term</option>
                    <option value="month">By Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="startDate">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" id="endDate">
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary w-100" id="generateReportBtn">Generate Report</button>
            </div>
        </div>

        <!-- Financial Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Income</h6>
                        <h3 class="text-success mb-0" id="totalIncome">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expenses</h6>
                        <h3 class="text-danger mb-0" id="totalExpenses">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Net Profit/Loss</h6>
                        <h3 class="text-primary mb-0" id="netProfit">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Outstanding Fees</h6>
                        <h3 class="text-info mb-0" id="outstandingFees">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Content Area -->
        <div id="reportContent">
            <!-- Chart Area -->
            <div class="card mb-4">
                <div class="card-body">
                    <canvas id="financeChart" height="80"></canvas>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-bordered" id="reportTable">
                    <thead class="table-light">
                        <tr id="reportTableHeader">
                            <!-- Dynamic headers -->
                        </tr>
                    </thead>
                    <tbody id="reportTableBody">
                        <!-- Dynamic content -->
                    </tbody>
                    <tfoot class="table-light">
                        <tr id="reportTableFooter">
                            <!-- Dynamic totals -->
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div id="emptyState" class="text-center py-5" style="display: none;">
            <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No Report Generated</h5>
            <p class="text-muted">Select report type and date range, then click "Generate Report"</p>
        </div>
    </div>
</div>

<script>
    // Initialize finance reports when page loads
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement financeReportsController in js/pages/finance_reports.js
        console.log('Finance Reports page loaded');
    });
</script>