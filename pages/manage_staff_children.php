<?php
/**
 * Manage Staff Children Page
 * Link staff members to their children enrolled in the school
 * HTML structure only - all logic in js/pages/staff_children.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow">
    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
        <h2 class="mb-0">👨‍👩‍👧‍👦 Staff Children Management</h2>
        <button class="btn btn-light" onclick="staffChildrenController.showAddModal()">
            <i class="bi bi-plus-circle"></i> Link Child to Staff
        </button>
    </div>
    <div class="card-body">
        <!-- Info Alert -->
        <div class="alert alert-info mb-4">
            <strong><i class="bi bi-info-circle"></i> Staff Children Fee Deduction Policy:</strong>
            <ul class="mb-0 mt-2">
                <li><strong>1st Child:</strong> <span id="discount1stChild">Loading...</span>% discount on school fees
                </li>
                <li><strong>2nd Child:</strong> <span id="discount2ndChild">Loading...</span>% discount on school fees
                </li>
                <li><strong>3rd Child onwards:</strong> <span id="discount3rdChild">Loading...</span>% discount on
                    school fees</li>
                <li><strong>Maximum Deduction:</strong> <span id="maxDeductionPercent">Loading...</span>% of gross
                    salary</li>
            </ul>
        </div>

        <!-- Search and Filter -->
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" id="searchStaffChildren" class="form-control"
                    placeholder="Search by staff name, child name, admission number..."
                    onkeyup="staffChildrenController.search(this.value)">
            </div>
            <div class="col-md-3">
                <select id="departmentFilter" class="form-select"
                    onchange="staffChildrenController.filterByDepartment(this.value)">
                    <option value="">-- All Departments --</option>
                </select>
            </div>
            <div class="col-md-3">
                <select id="statusFilter" class="form-select"
                    onchange="staffChildrenController.filterByStatus(this.value)">
                    <option value="">-- All Status --</option>
                    <option value="active">Active</option>
                    <option value="graduated">Graduated</option>
                    <option value="withdrawn">Withdrawn</option>
                </select>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4" id="summaryCards">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body text-center">
                        <h3 id="totalStaffWithChildren">0</h3>
                        <small>Staff with Children</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <h3 id="totalChildren">0</h3>
                        <small>Total Children</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body text-center">
                        <h3 id="totalMonthlyDeductions">KES 0</h3>
                        <small>Monthly Deductions</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h3 id="totalDiscountsSaved">KES 0</h3>
                        <small>Total Discounts Applied</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Staff Children Table -->
        <div id="staffChildrenTableContainer">
            <p class="text-muted text-center"><i class="bi bi-hourglass-split"></i> Loading staff children records...
            </p>
        </div>
    </div>
</div>

<!-- Add/Edit Staff Child Modal -->
<div class="modal fade" id="staffChildModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="staffChildModalTitle">Link Child to Staff</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="staffChildForm">
                <div class="modal-body">
                    <input type="hidden" id="staffChildId">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Staff Member <span class="text-danger">*</span></label>
                                <select id="staffSelect" class="form-select" required>
                                    <option value="">-- Select Staff --</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Student (Child) <span class="text-danger">*</span></label>
                                <select id="studentSelect" class="form-select" required>
                                    <option value="">-- Select Student --</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Relationship <span class="text-danger">*</span></label>
                                <select id="relationshipSelect" class="form-select" required>
                                    <option value="">-- Select Relationship --</option>
                                    <option value="son">Son</option>
                                    <option value="daughter">Daughter</option>
                                    <option value="ward">Ward</option>
                                    <option value="stepchild">Stepchild</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fee Deduction Status</label>
                                <select id="deductionStatus" class="form-select">
                                    <option value="active">Active - Deduct from Salary</option>
                                    <option value="suspended">Suspended - No Deduction</option>
                                    <option value="exempt">Exempt - Full Scholarship</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="childNotes" class="form-control" rows="2"
                            placeholder="Any special arrangements or notes..."></textarea>
                    </div>

                    <!-- Preview Section -->
                    <div id="feePreviewSection" class="card bg-light mt-3" style="display: none;">
                        <div class="card-body">
                            <h6 class="card-title"><i class="bi bi-calculator"></i> Fee Deduction Preview</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted">Child Order</small>
                                    <p class="mb-0 fw-bold" id="previewChildOrder">-</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Discount Rate</small>
                                    <p class="mb-0 fw-bold text-success" id="previewDiscountRate">-</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Estimated Monthly Deduction</small>
                                    <p class="mb-0 fw-bold text-primary" id="previewMonthlyDeduction">-</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-save"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Staff Fee Deductions Detail Modal -->
<div class="modal fade" id="staffDeductionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-person-badge"></i> <span id="staffNameTitle">Staff Name</span> - Children Fee
                    Deductions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Month</label>
                        <select id="deductionMonth" class="form-select"
                            onchange="staffChildrenController.recalculateDeductions()">
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
                    <div class="col-md-4">
                        <label class="form-label">Year</label>
                        <input type="number" id="deductionYear" class="form-control" value="<?php echo date('Y'); ?>"
                            min="2020" max="2030" onchange="staffChildrenController.recalculateDeductions()">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-primary w-100" onclick="staffChildrenController.recalculateDeductions()">
                            <i class="bi bi-calculator"></i> Calculate Deductions
                        </button>
                    </div>
                </div>

                <!-- Deductions Table -->
                <div id="staffDeductionsContainer">
                    <p class="text-muted text-center">Select month and year to calculate deductions</p>
                </div>

                <!-- Summary -->
                <div class="card bg-light mt-3" id="deductionSummary" style="display: none;">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4 class="text-primary" id="summaryTotalFees">KES 0</h4>
                                <small>Total Fees</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-success" id="summaryTotalDiscount">KES 0</h4>
                                <small>Staff Discount</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-danger" id="summaryNetDeduction">KES 0</h4>
                                <small>Net Deduction</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning" id="summaryCapped">-</h4>
                                <small>Salary Cap Applied</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>