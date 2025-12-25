<?php
/**
 * Financial Reports Page
 * HTML structure only - logic will be in js/pages/financial_reports.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-success text-white">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-chart-pie"></i> Financial Reports</h4>
            <div class="btn-group">
                <button class="btn btn-light btn-sm" id="generateReportBtn">
                    <i class="bi bi-bar-chart"></i> Generate Report
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportPDFBtn">
                    <i class="bi bi-file-pdf"></i> Export PDF
                </button>
                <button class="btn btn-outline-light btn-sm" id="printBtn">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Report Configuration -->
        <div class="row mb-4">
            <div class="col-md-3">
                <label class="form-label">Report Type</label>
                <select class="form-select" id="reportType">
                    <option value="income_statement">Income Statement</option>
                    <option value="balance_sheet">Balance Sheet</option>
                    <option value="cash_flow">Cash Flow Statement</option>
                    <option value="fee_analysis">Fee Collection Analysis</option>
                    <option value="expense_breakdown">Expense Breakdown</option>
                    <option value="budget_variance">Budget vs Actual</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Period</label>
                <select class="form-select" id="period">
                    <option value="current_term">Current Term</option>
                    <option value="previous_term">Previous Term</option>
                    <option value="current_year">Current Year</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Start Date</label>
                <input type="date" class="form-control" id="startDate">
            </div>
            <div class="col-md-2">
                <label class="form-label">End Date</label>
                <input type="date" class="form-control" id="endDate">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary w-100" id="loadReportBtn">Load Report</button>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Revenue</h6>
                        <h3 class="text-success mb-0" id="totalRevenue">KES 0</h3>
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
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Outstanding</h6>
                        <h3 class="text-warning mb-0" id="outstanding">KES 0</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Revenue vs Expenses Trend</h5>
                        <canvas id="trendChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Expense Distribution</h5>
                        <canvas id="expensePieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Table -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title" id="reportTitle">Financial Report</h5>
                <div class="table-responsive">
                    <table class="table table-bordered" id="financialReportTable">
                        <thead class="table-light">
                            <tr id="reportHeaders">
                                <!-- Dynamic headers -->
                            </tr>
                        </thead>
                        <tbody id="reportBody">
                            <!-- Dynamic content -->
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr id="reportTotals">
                                <!-- Dynamic totals -->
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // TODO: Implement financialReportsController in js/pages/financial_reports.js
        console.log('Financial Reports page loaded');
    });
</script>