<?php
/**
 * Detailed Payslip Viewer Page
 * Shows comprehensive payslip with all deductions including staff children fees
 * HTML structure only - all logic in js/pages/detailed_payslip.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h2 class="mb-0">📄 Detailed Payslip</h2>
        <div>
            <button class="btn btn-light" onclick="detailedPayslipController.downloadPayslip()">
                <i class="bi bi-download"></i> Download PDF
            </button>
            <button class="btn btn-outline-light" onclick="detailedPayslipController.printPayslip()">
                <i class="bi bi-printer"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body">
        <!-- Selection Form -->
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Staff Member</label>
                <select id="staffSelect" class="form-select" onchange="detailedPayslipController.onStaffChange()">
                    <option value="">-- Select Staff --</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Month</label>
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
            <div class="col-md-3">
                <label class="form-label">Year</label>
                <input type="number" id="payrollYear" class="form-control" value="<?php echo date('Y'); ?>" min="2020"
                    max="2030">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-success w-100" onclick="detailedPayslipController.generatePayslip()">
                    <i class="bi bi-file-earmark-text"></i> Generate
                </button>
            </div>
        </div>

        <!-- Payslip Container -->
        <div id="payslipContainer">
            <div class="text-center text-muted py-5">
                <i class="bi bi-file-earmark-text" style="font-size: 4rem;"></i>
                <p class="mt-3">Select a staff member and click "Generate" to view payslip</p>
            </div>
        </div>
    </div>
</div>

<!-- Payslip Template (Hidden, used for rendering) -->
<template id="payslipTemplate">
    <div class="payslip-document" id="payslipDocument">
        <!-- Company Header -->
        <div class="payslip-header text-center border-bottom pb-3 mb-4">
            <h3 class="mb-1">KINGSWAY ACADEMY</h3>
            <p class="mb-0 text-muted">P.O. Box 12345, Nairobi, Kenya</p>
            <p class="mb-0 text-muted">Tel: +254 700 000 000 | Email: info@kingswayacademy.ac.ke</p>
            <h4 class="mt-3 mb-0">PAYSLIP</h4>
            <p class="text-muted" id="payslipPeriod">Month Year</p>
        </div>

        <!-- Employee Details -->
        <div class="row mb-4">
            <div class="col-md-6">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="40%">Employee Name:</td>
                        <td class="fw-bold" id="employeeName">-</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Employee ID:</td>
                        <td id="employeeId">-</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Department:</td>
                        <td id="employeeDepartment">-</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Designation:</td>
                        <td id="employeeDesignation">-</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm">
                    <tr>
                        <td class="text-muted" width="40%">KRA PIN:</td>
                        <td id="employeeKraPin">-</td>
                    </tr>
                    <tr>
                        <td class="text-muted">NSSF No:</td>
                        <td id="employeeNssf">-</td>
                    </tr>
                    <tr>
                        <td class="text-muted">NHIF No:</td>
                        <td id="employeeNhif">-</td>
                    </tr>
                    <tr>
                        <td class="text-muted">Bank Account:</td>
                        <td id="employeeBankAccount">-</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Earnings and Deductions -->
        <div class="row">
            <!-- Earnings Column -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <strong><i class="bi bi-plus-circle"></i> EARNINGS</strong>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <tbody id="earningsTable">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot class="table-success">
                                <tr>
                                    <td class="fw-bold">GROSS EARNINGS</td>
                                    <td class="fw-bold text-end" id="grossEarnings">KES 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Deductions Column -->
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-danger text-white">
                        <strong><i class="bi bi-dash-circle"></i> DEDUCTIONS</strong>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <tbody id="deductionsTable">
                                <!-- Populated dynamically -->
                            </tbody>
                            <tfoot class="table-danger">
                                <tr>
                                    <td class="fw-bold">TOTAL DEDUCTIONS</td>
                                    <td class="fw-bold text-end" id="totalDeductions">KES 0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statutory Deductions Breakdown -->
        <div class="card mb-3">
            <div class="card-header bg-secondary text-white">
                <strong><i class="bi bi-bank"></i> STATUTORY DEDUCTIONS BREAKDOWN</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center">
                        <small class="text-muted">PAYE</small>
                        <h5 id="payeAmount">KES 0.00</h5>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">NSSF</small>
                        <h5 id="nssfAmount">KES 0.00</h5>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">NHIF</small>
                        <h5 id="nhifAmount">KES 0.00</h5>
                    </div>
                    <div class="col-md-3 text-center">
                        <small class="text-muted">Housing Levy</small>
                        <h5 id="housingLevyAmount">KES 0.00</h5>
                    </div>
                </div>
            </div>
        </div>

        <!-- Children Fee Deductions Section -->
        <div class="card mb-3" id="childrenFeeSection" style="display: none;">
            <div class="card-header bg-info text-white">
                <strong><i class="bi bi-people"></i> STAFF CHILDREN FEE DEDUCTIONS</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Child Name</th>
                            <th>Class</th>
                            <th>Term Fees</th>
                            <th>Discount</th>
                            <th class="text-end">Deduction</th>
                        </tr>
                    </thead>
                    <tbody id="childrenFeeTable">
                        <!-- Populated dynamically -->
                    </tbody>
                    <tfoot class="table-info">
                        <tr>
                            <td colspan="4" class="fw-bold">TOTAL CHILDREN FEE DEDUCTIONS</td>
                            <td class="fw-bold text-end" id="totalChildrenFees">KES 0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Other Deductions Section -->
        <div class="card mb-3" id="otherDeductionsSection" style="display: none;">
            <div class="card-header bg-warning text-dark">
                <strong><i class="bi bi-list-check"></i> OTHER DEDUCTIONS</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Description</th>
                            <th>Reference</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="otherDeductionsTable">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Net Pay Summary -->
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <small>Gross Earnings</small>
                        <h5 id="summaryGross">KES 0.00</h5>
                    </div>
                    <div class="col-md-1 text-center">
                        <h3>−</h3>
                    </div>
                    <div class="col-md-3">
                        <small>Total Deductions</small>
                        <h5 id="summaryDeductions">KES 0.00</h5>
                    </div>
                    <div class="col-md-1 text-center">
                        <h3>=</h3>
                    </div>
                    <div class="col-md-3 text-end">
                        <small>NET PAY</small>
                        <h3 class="mb-0" id="netPay">KES 0.00</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-4 pt-3 border-top">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><small class="text-muted">Generated on: <span id="generatedDate">-</span></small>
                    </p>
                    <p class="mb-0"><small class="text-muted">Reference: <span id="payslipReference">-</span></small>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-1"><small>This is a computer-generated payslip.</small></p>
                    <p class="mb-0"><small>No signature is required.</small></p>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
    @media print {

        .card-header.bg-success,
        .btn,
        .form-control,
        .form-select,
        #staffSelect,
        #payrollMonth,
        #payrollYear,
        .row.mb-4:first-child {
            display: none !important;
        }

        .payslip-document {
            padding: 20px;
            border: 1px solid #000;
        }

        .card {
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
    }

    .payslip-document {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
        background: white;
    }

    #payslipContainer .table td,
    #payslipContainer .table th {
        padding: 0.5rem;
    }
</style>