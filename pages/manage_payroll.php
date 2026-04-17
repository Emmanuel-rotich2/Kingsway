<?php
/**
 * Manage Payroll Page
 * UI for payroll processing and payslip management.
 * Logic handled by js/pages/payroll.js (payrollController).
 * Permission-gated via data-permission attributes + RBAC middleware.
 */
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1"><i class="bi bi-cash-coin me-2 text-success"></i>Payroll Management</h2>
        <p class="text-muted mb-0">Process staff payroll and manage payslips</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm" onclick="payrollController.loadReport()">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <button class="btn btn-outline-success btn-sm"
                data-permission="finance_export"
                onclick="payrollController.exportPayroll()">
            <i class="bi bi-download"></i> Export
        </button>
    </div>
</div>

<!-- Process Payroll Card (Finance/Admin only) -->
<div class="card mb-4 border-0 shadow-sm" data-permission="finance_approve">
    <div class="card-header bg-success text-white">
        <h6 class="mb-0"><i class="bi bi-play-circle me-2"></i>Process Payroll</h6>
    </div>
    <div class="card-body">
        <form id="payrollForm">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Staff Member *</label>
                    <select class="form-select" id="staffSelect" required>
                        <option value="">-- Select Staff --</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Pay Period *</label>
                    <select class="form-select" id="payPeriodSelect" required>
                        <option value="">-- Select Period --</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Basic Salary (KES)</label>
                    <input type="number" class="form-control" id="basicSalary"
                           placeholder="0.00" min="0" step="0.01"
                           oninput="payrollController.calculateNetSalary()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Allowances (KES)</label>
                    <input type="number" class="form-control" id="allowances"
                           placeholder="0.00" min="0" step="0.01" value="0"
                           oninput="payrollController.calculateNetSalary()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Deductions (KES)</label>
                    <input type="number" class="form-control" id="deductions"
                           placeholder="0.00" min="0" step="0.01" value="0"
                           oninput="payrollController.calculateNetSalary()">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div class="w-100">
                        <label class="form-label">Net Pay</label>
                        <input type="number" class="form-control bg-light fw-bold"
                               id="netSalary" readonly placeholder="0.00">
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-1"></i> Process Payroll
                </button>
                <button type="button" class="btn btn-outline-secondary ms-2" onclick="document.getElementById('payrollForm').reset()">
                    <i class="bi bi-x-circle me-1"></i> Clear
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Payroll Records -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-table me-2"></i>Payroll Records</h6>
        <div class="d-flex gap-2">
            <input type="month" class="form-control form-control-sm" id="filterMonth"
                   style="width:180px"
                   onchange="payrollController.loadReport()">
            <select class="form-select form-select-sm" id="filterStatus"
                    style="width:140px"
                    onchange="payrollController.loadReport()">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="paid">Paid</option>
            </select>
        </div>
    </div>
    <div class="card-body" id="reportContainer">
        <div class="text-center text-muted py-5">
            <i class="bi bi-hourglass-split display-5 mb-3"></i>
            <p>Loading payroll records...</p>
        </div>
    </div>
</div>

<!-- Payslip Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-receipt me-2"></i>Payslip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payslipContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-success"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" onclick="payrollController.printPayslip()">
                    <i class="bi bi-printer me-1"></i> Print
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= $appBase ?>js/pages/payroll.js?v=<?= time() ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof payrollController !== 'undefined') {
            payrollController.init();
        }
    });
</script>
