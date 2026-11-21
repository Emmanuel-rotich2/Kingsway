<?php
/**
 * Payroll Page
 * HTML structure only - all logic in js/pages/finance.js (payrollController)
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-warning text-white">
        <h2 class="mb-0">💰 Payroll Ledger</h2>
    </div>
    <div class="card-body">
        <!-- Tabs for different payroll actions -->
        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="processTab" data-bs-toggle="tab" data-bs-target="#processPanel" type="button" role="tab" disabled>
                    Payroll Processing
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="reportTab" data-bs-toggle="tab" data-bs-target="#reportPanel" type="button" role="tab">
                    Database Payroll Records
                </button>
            </li>
        </ul>

        <!-- Process Payroll Tab -->
        <div class="tab-content">
            <div class="tab-pane fade" id="processPanel" role="tabpanel">
                <form id="payrollForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Staff Member</label>
                            <select id="staffSelect" class="form-select" required>
                                <option value="">-- Select Staff --</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Pay Period</label>
                            <select id="payPeriodSelect" class="form-select" required>
                                <option value="">-- Select Period --</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" id="basicSalary" class="form-control" min="0" step="0.01" required readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Allowances</label>
                            <input type="number" id="allowances" class="form-control" min="0" step="0.01" value="0" data-kw-onchange="payrollController.calculateNetSalary()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Deductions</label>
                            <input type="number" id="deductions" class="form-control" min="0" step="0.01" value="0" data-kw-onchange="payrollController.calculateNetSalary()">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Net Salary</label>
                            <input type="number" id="netSalary" class="form-control" min="0" step="0.01" readonly>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" data-year-write>Process Payroll</button>
                </form>
            </div>

            <!-- Payroll Report Tab -->
            <div class="tab-pane fade show active" id="reportPanel" role="tabpanel">
                <div class="alert alert-info mb-3">
                    This ledger displays only payroll records returned from the database. Payroll preparation, approval, and disbursement are handled in the Finance payroll workflow.
                </div>
                <button class="btn btn-secondary mb-3" data-kw-onclick="payrollController.loadReport()">Load Report</button>
                <div id="reportContainer">
                    <p class="text-muted">Click Load Report to view payroll history</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php asset_script($appBase, 'js/pages/staff_access.js'); ?>
<?php asset_script($appBase, 'js/pages/payroll.js'); ?>
