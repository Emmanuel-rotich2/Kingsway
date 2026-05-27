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
        <h2 class="mb-0"><i class="bi bi-file-earmark-text"></i> Detailed Payslip</h2>
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
        <!-- School Header -->
        <div class="payslip-header mb-4">
            <div class="payslip-header-glow"></div>
            <div class="payslip-header-main">
                <div class="payslip-logo-wrap">
                    <img src="<?= $appBase ?>/images/kings%20logo.png" alt="Kingsway Preparatory School Logo" class="payslip-school-logo">
                </div>
                <div class="payslip-school-info">
                    <div class="payslip-school-kicker">Official Staff Payroll Statement</div>
                    <h3 class="school-name mb-1">KINGSWAY PREPARATORY SCHOOL</h3>
                    <div class="school-contact-grid">
                        <span><i class="bi bi-geo-alt-fill"></i> P.O BOX 203-20203, Londiani, Kenya</span>
                        <span><i class="bi bi-signpost-2-fill"></i> Londiani, Kericho County</span>
                        <span><i class="bi bi-telephone-fill"></i> +254 720 113 030 / +254 720 113 031</span>
                        <span><i class="bi bi-envelope-fill"></i> info@kingswaypreparatoryschool.sc.ke</span>
                    </div>
                </div>
                <div class="payslip-title-card">
                    <div class="payslip-title">PAYSLIP</div>
                    <div class="payslip-period" id="payslipPeriod">Month Year</div>
                </div>
            </div>
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
                    <div class="card-header payslip-section-header earnings-header text-white">
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
                    <div class="card-header payslip-section-header deductions-header text-white">
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
            <div class="card-header payslip-section-header statutory-header text-white">
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

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border: none; border-radius: 12px; overflow: hidden;">
            <div class="modal-header border-0" style="background: linear-gradient(135deg, #0d4f2a, #198754); color: white;">
                <h5 class="modal-title">
                    <i class="bi bi-question-circle-fill" id="confirmModalIcon"></i>
                    <span id="confirmModalTitle">Confirm</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-4">
                <p id="confirmModalMessage" class="mb-0" style="font-size: 1rem;"></p>
            </div>
            <div class="modal-footer border-0" style="background: #f5f5dc;">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Cancel
                </button>
                <button type="button" class="btn text-white" id="confirmModalOk" style="background: #0d4f2a;">
                    <i class="bi bi-check-circle"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* School Colors */
    :root {
        --kps-green: #0d4f2a;
        --kps-green-light: #198754;
        --kps-gold: #f9c80e;
        --kps-cream: #f5f5dc;
    }

    /* Payslip Header */
    .payslip-header {
        position: relative;
        overflow: hidden;
        background:
            radial-gradient(circle at top left, rgba(249, 200, 14, 0.28), transparent 32%),
            linear-gradient(135deg, #06351c 0%, #0d4f2a 52%, #198754 100%);
        color: white;
        border-radius: 20px;
        padding: 1.25rem;
        margin: -10px -10px 1.5rem -10px;
        box-shadow: 0 18px 40px rgba(13, 79, 42, 0.28);
        border: 1px solid rgba(249, 200, 14, 0.35);
    }

    .payslip-header::after {
        content: "";
        position: absolute;
        right: -60px;
        top: -70px;
        width: 220px;
        height: 220px;
        border-radius: 50%;
        background: rgba(245, 245, 220, 0.12);
    }

    .payslip-header-glow {
        position: absolute;
        inset: auto 0 0 0;
        height: 8px;
        background: linear-gradient(90deg, #f9c80e, #f5f5dc, #f9c80e);
    }

    .payslip-header-main {
        position: relative;
        z-index: 1;
        display: grid;
        grid-template-columns: 96px 1fr 160px;
        align-items: center;
        gap: 1.2rem;
    }

    .payslip-logo-wrap {
        width: 88px;
        height: 88px;
        border-radius: 24px;
        background: #f5f5dc;
        border: 3px solid #f9c80e;
        box-shadow: 0 12px 26px rgba(0, 0, 0, 0.22);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px;
    }

    .payslip-school-logo {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .payslip-school-kicker {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        background: rgba(245, 245, 220, 0.16);
        border: 1px solid rgba(249, 200, 14, 0.32);
        color: #f5f5dc;
        padding: 0.25rem 0.65rem;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.35rem;
    }

    .school-name {
        font-family: 'Georgia', 'Times New Roman', serif;
        font-weight: 900;
        font-size: 1.45rem;
        letter-spacing: 2px;
        color: #f9c80e;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
    }

    .school-contact-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem 1rem;
        color: #f5f5dc;
        font-size: 0.76rem;
        line-height: 1.35;
    }

    .school-contact-grid i {
        color: #f9c80e;
        margin-right: 0.35rem;
    }

    .payslip-title-card {
        background: rgba(245, 245, 220, 0.95);
        color: #0d4f2a;
        border-radius: 18px;
        border: 2px solid #f9c80e;
        padding: 0.9rem 0.75rem;
        text-align: center;
        box-shadow: inset 0 0 0 1px rgba(13, 79, 42, 0.08), 0 10px 24px rgba(0, 0, 0, 0.2);
    }

    .payslip-title {
        font-weight: 900;
        letter-spacing: 3px;
        font-size: 1.15rem;
        line-height: 1;
    }

    .payslip-period {
        display: block;
        margin-top: 0.45rem;
        font-size: 0.86rem;
        font-weight: 700;
        color: #198754;
    }

    @media (max-width: 768px) {
        .payslip-header-main {
            grid-template-columns: 1fr;
            text-align: center;
        }

        .payslip-logo-wrap {
            margin: 0 auto;
        }

        .school-contact-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Print styles */
    @media print {
        .card-header.bg-success,
        .btn,
        .form-control,
        .form-select,
        #staffSelect,
        #payrollMonth,
        #payrollYear,
        .row.mb-4:first-child,
        .modal {
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

        .payslip-header {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }

    .payslip-document {
        max-width: 900px;
        margin: 0 auto;
        padding: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    }

    #payslipContainer .table td,
    #payslipContainer .table th {
        padding: 0.5rem;
    }

    /* Modern branded section styling */
    #payslipContainer .card {
        border: 1px solid rgba(13, 79, 42, 0.12);
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 8px 22px rgba(13, 79, 42, 0.08);
    }

    .payslip-section-header {
        border: 0;
        letter-spacing: 0.04em;
    }

    .earnings-header {
        background: linear-gradient(135deg, #0d4f2a, #198754) !important;
    }

    .deductions-header {
        background: linear-gradient(135deg, #7a1f1f, #dc3545) !important;
    }

    .statutory-header {
        background: linear-gradient(135deg, #2f3b2f, #6c757d) !important;
    }

    #payslipContainer .table thead th {
        background: #f5f5dc;
        color: #0d4f2a;
        border-color: rgba(13, 79, 42, 0.15);
        font-size: 0.78rem;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    #payslipContainer .alert-success {
        background: linear-gradient(135deg, rgba(13, 79, 42, 0.08), rgba(249, 200, 14, 0.12));
        border: 1px solid rgba(13, 79, 42, 0.18);
        color: #0d4f2a;
        border-radius: 14px;
    }


</style><script src="<?= $appBase ?>/js/pages/detailed_payslip.js?v=<?= time() ?>"></script>
