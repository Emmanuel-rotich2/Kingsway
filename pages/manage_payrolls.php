<?php
/**
 * Manage Payrolls Page
 * Enhanced with staff children fee deduction during payroll processing
 * All logic in js/pages/payroll_manager.js
 */
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-money-check-alt text-success"></i> Payroll Management</h2>
            <p class="text-muted mb-0">Process staff payroll with automatic children fee deductions</p>
        </div>
        <div>
            <button class="btn btn-primary" onclick="PayrollManagerController.showProcessPayrollModal()">
                <i class="fas fa-plus-circle me-1"></i> Process New Payroll
            </button>
            <button class="btn btn-outline-secondary" onclick="PayrollManagerController.refresh()">
                <i class="fas fa-sync-alt me-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Total Staff</h6>
                            <h3 class="mb-0" id="statTotalStaff">--</h3>
                        </div>
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">Staff with Children</h6>
                            <h3 class="mb-0" id="statStaffWithChildren">--</h3>
                        </div>
                        <i class="fas fa-child fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2 text-white-50">This Month's Net Pay</h6>
                            <h3 class="mb-0" id="statThisMonthNet">KES --</h3>
                        </div>
                        <i class="fas fa-wallet fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-subtitle mb-2 text-dark-50">Children Fees Deducted</h6>
                            <h3 class="mb-0" id="statChildrenFees">KES --</h3>
                        </div>
                        <i class="fas fa-graduation-cap fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
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
                <div class="col-md-3">
                    <label class="form-label">Year</label>
                    <select id="filterYear" class="form-select" onchange="PayrollManagerController.applyFilters()">
                        <option value="">All Years</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select id="filterStatus" class="form-select" onchange="PayrollManagerController.applyFilters()">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="paid">Paid</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search Staff</label>
                    <input type="text" id="searchStaff" class="form-control" placeholder="Name or ID..."
                           oninput="PayrollManagerController.applyFilters()">
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Records Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Payroll Records</h5>
            <span class="badge bg-secondary" id="payrollCount">0 records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-dark">
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
                            <td colspan="10" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="text-muted mb-0 mt-2">Loading payroll records...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
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
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-money-check-alt me-2"></i>Process Staff Payroll</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Select Staff & Period -->
                <div id="payrollStep1">
                    <h6 class="text-primary mb-3"><i class="fas fa-1 me-2"></i>Step 1: Select Staff Member & Pay Period</h6>
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
                        
                        <!-- Staff Info Card (shows after selection) -->
                        <div id="staffInfoCard" class="card bg-light d-none mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Staff Details</h6>
                                        <p class="mb-1"><strong>Name:</strong> <span id="staffInfoName">-</span></p>
                                        <p class="mb-1"><strong>Position:</strong> <span id="staffInfoPosition">-</span></p>
                                        <p class="mb-1"><strong>Department:</strong> <span id="staffInfoDept">-</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-muted">Salary Info</h6>
                                        <p class="mb-1"><strong>Basic Salary:</strong> <span id="staffInfoSalary" class="text-success">KES
                                                0.00</span></p>
                                        <p class="mb-1"><strong>Children in School:</strong> <span id="staffInfoChildrenCount"
                                                class="badge bg-info">0</span></p>
                                    </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Children & Fee Deductions (shows if staff has children) -->
                <div id="payrollStep2" class="d-none">
                    <hr>
                    <h6 class="text-primary mb-3">
                        <i class="fas fa-2 me-2"></i>Step 2: Children Fee Deductions
                        <span class="badge bg-info ms-2" id="childrenCountBadge">0 children</span>
                    </h6>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This staff member has children enrolled in the school. Configure how much to deduct from salary for each child's school fees.
                    </div>
                    
                    <div id="childrenFeesList">
                        <!-- Children and fees will be loaded here -->
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <div class="card border-primary">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Total Children Fees Outstanding</h6>
                                    <h4 class="text-primary mb-0" id="totalChildrenFees">KES 0.00</h4>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-body text-center">
                                    <h6 class="text-muted">Total to Deduct This Month</h6>
                                    <h4 class="text-success mb-0" id="totalDeductionAmount">KES 0.00</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Salary Calculation -->
                <div id="payrollStep3" class="d-none">
                    <hr>
                    <h6 class="text-primary mb-3"><i class="fas fa-3 me-2"></i>Step 3: Salary Breakdown</h6>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-success">Earnings</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td>Basic Salary</td>
                                    <td class="text-end" id="calcBasicSalary">0.00</td>
                                </tr>
                                <tr>
                                    <td>House Allowance</td>
                                    <td class="text-end">
                                        <input type="number" id="houseAllowance" class="form-control form-control-sm text-end" 
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Transport Allowance</td>
                                    <td class="text-end">
                                        <input type="number" id="transportAllowance" class="form-control form-control-sm text-end" 
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Other Allowances</td>
                                    <td class="text-end">
                                        <input type="number" id="otherAllowances" class="form-control form-control-sm text-end" 
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()">
                                    </td>
                                </tr>
                                <tr class="table-success fw-bold">
                                    <td>Gross Salary</td>
                                    <td class="text-end" id="calcGrossSalary">0.00</td>
                                </tr>
                            </table>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="text-danger">Deductions</h6>
                            <table class="table table-sm">
                                <tr>
                                    <td>NSSF</td>
                                    <td class="text-end" id="calcNSSF">0.00</td>
                                </tr>
                                <tr>
                                    <td>NHIF</td>
                                    <td class="text-end" id="calcNHIF">0.00</td>
                                </tr>
                                <tr>
                                    <td>PAYE Tax</td>
                                    <td class="text-end" id="calcPAYE">0.00</td>
                                </tr>
                                <tr>
                                    <td>Housing Levy (1.5%)</td>
                                    <td class="text-end" id="calcHousingLevy">0.00</td>
                                </tr>
                                <tr class="table-warning">
                                    <td><i class="fas fa-graduation-cap me-1"></i>Children School Fees</td>
                                    <td class="text-end" id="calcChildrenFees">0.00</td>
                                </tr>
                                <tr>
                                    <td>Other Deductions</td>
                                    <td class="text-end">
                                        <input type="number" id="otherDeductions" class="form-control form-control-sm text-end" 
                                               value="0" step="0.01" onchange="PayrollManagerController.recalculatePayroll()">
                                    </td>
                                </tr>
                                <tr class="table-danger fw-bold">
                                    <td>Total Deductions</td>
                                    <td class="text-end" id="calcTotalDeductions">0.00</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Net Pay -->
                    <div class="card bg-success text-white mt-3">
                        <div class="card-body text-center">
                            <h5 class="mb-1">NET PAY</h5>
                            <h2 class="mb-0" id="calcNetPay">KES 0.00</h2>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="processPayrollBtn" onclick="PayrollManagerController.submitPayroll()" disabled>
                    <i class="fas fa-check-circle me-1"></i> Process Payroll
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Payslip Modal -->
<div class="modal fade" id="viewPayslipModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="fas fa-file-invoice me-2"></i>Detailed Payslip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="payslipContent">
                <!-- Payslip content will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-primary" onclick="PayrollManagerController.printPayslip()">
                    <i class="fas fa-print me-1"></i> Print
                </button>
                <button type="button" class="btn btn-outline-success" onclick="PayrollManagerController.downloadPayslip()">
                    <i class="fas fa-download me-1"></i> Download PDF
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="js/pages/payroll_manager.js"></script>
