<?php
/**
 * Student Fees Page (Per-student fee tracking)
 * HTML structure only - logic will be in js/pages/student_fees.js
 * Embedded in app_layout.php
 */
?>

<div class="card shadow-sm">
    <div class="card-header bg-gradient bg-primary text-white">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0"><i class="bi bi-credit-card"></i> Student Fee Accounts</h4>
                <small class="opacity-75">Balances, obligations and learner statements</small>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline-light btn-sm" id="awardScholarshipBtn" hidden>
                    <i class="bi bi-award"></i> Sponsor (Scholarship)
                </button>
                <button class="btn btn-outline-light btn-sm" id="waiveFeesBtn" hidden>
                    <i class="bi bi-shield-check"></i> Waive Off Fees
                </button>
                <button class="btn btn-outline-light btn-sm" id="printSelectedFeesBtn">
                    <i class="bi bi-printer"></i> Print Selected
                </button>
                <button class="btn btn-outline-light btn-sm" id="exportBtn">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-light border small">This is the learner account view. To enter or reconcile a payment, use <strong>Payment Register</strong>.</div>

        <!-- Search & Filter -->
        <div class="row mb-4">
            <div class="col-md-3">
                <input type="text" class="form-control" id="searchStudent" placeholder="Search student...">
            </div>
            <div class="col-md-2">
                <select class="form-select" id="classFilter">
                    <option value="">All Classes</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="statusFilter">
                    <option value="">All Status</option>
                    <option value="paid">Fully Paid</option>
                    <option value="partial">Partial Payment</option>
                    <option value="unpaid">Not Paid</option>
                    <option value="overpaid">Overpaid</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" id="termFilter">
                    <option value="">Current Term</option>
                    <option value="1">Term 1</option>
                    <option value="2">Term 2</option>
                    <option value="3">Term 3</option>
                </select>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Expected</h6>
                        <h3 class="text-primary mb-0" id="totalExpected">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Collected</h6>
                        <h3 class="text-success mb-0" id="totalCollected">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total Outstanding</h6>
                        <h3 class="text-warning mb-0" id="totalOutstanding">KES 0</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Collection Rate</h6>
                        <h3 class="text-info mb-0" id="collectionRate">0%</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Students Table -->
        <div class="table-responsive">
            <table class="table table-hover" id="feesTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="text-center"><input type="checkbox" id="selectAllFeeStudents" aria-label="Select all students"></th>
                        <th scope="col">Admission No</th>
                        <th scope="col">Student Name</th>
                        <th scope="col">Class</th>
                        <th scope="col">Expected (KES)</th>
                        <th scope="col">Paid (KES)</th>
                        <th scope="col">Balance (KES)</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Dynamic content -->
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <nav>
            <ul class="pagination justify-content-center" id="pagination">
                <!-- Dynamic pagination -->
            </ul>
        </nav>
    </div>
</div>

<!-- Student Fee Details Modal -->
<div class="modal fade" id="feeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title">Fee Details</h5>
                    <p class="mb-0"><strong>Student:</strong> <span id="studentName"></span> (<span id="admNo"></span>)
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Fee Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h6>Total Fee</h6>
                                <h4 id="modalTotalFee">KES 0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6>Total Paid</h6>
                                <h4 id="modalTotalPaid">KES 0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h6>Balance</h6>
                                <h4 id="modalBalance">KES 0</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fee Breakdown -->
                <h6 class="mb-2">Fee Breakdown</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Fee Type</th>
                            <th scope="col">Amount (KES)</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody id="feeBreakdownBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>

                <!-- Payment History -->
                <h6 class="mb-2">Payment History</h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Date</th>
                            <th scope="col">Receipt No</th>
                            <th scope="col">Amount (KES)</th>
                            <th scope="col">Method</th>
                            <th scope="col">Received By</th>
                        </tr>
                    </thead>
                    <tbody id="paymentHistoryBody">
                        <!-- Dynamic content -->
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-success" id="manageAssistanceBtn">
                    <i class="bi bi-award"></i> Sponsor (Scholarship)
                </button>
                <button type="button" class="btn btn-primary" id="printStatementBtn">
                    <i class="bi bi-printer"></i> Print Statement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scholarship award. A scholarship is a distinct sponsored-fee decision. -->
<div class="modal fade" id="studentAssistanceModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <div>
                <h5 class="modal-title"><i class="bi bi-award me-2"></i>Sponsor Learner — Scholarship</h5>
                    <small id="assistanceStudentLabel"></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">This scholarship decision applies to the selected learner(s) for the selected academic year. It is separate from a fee waiver and records the scholarship programme and coverage.</div>
                <form id="studentAssistanceForm">
                    <input type="hidden" id="assistanceStudentId">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Academic year *</label><select class="form-select" id="assistanceYear" required></select></div>
                        <div class="col-md-6"><label class="form-label">Programme *</label><select class="form-select" id="assistanceProgram" required></select></div>
                        <div class="col-md-6"><label class="form-label">Coverage</label><select class="form-select" id="assistanceCoverage"><option value="full">Full sponsorship (100%)</option><option value="percentage">Percentage</option><option value="fixed_amount">Fixed amount per obligation</option></select></div>
                        <div class="col-md-6" id="assistancePercentageWrap"><label class="form-label">Percentage covered *</label><input type="number" class="form-control" id="assistancePercentage" min="0" max="100" step="0.01"></div>
                        <div class="col-md-6 d-none" id="assistanceAmountWrap"><label class="form-label">Amount covered per obligation (KES) *</label><input type="number" class="form-control" id="assistanceAmount" min="0" step="0.01"></div>
                        <div class="col-12"><label class="form-label">Reason / approval note *</label><textarea class="form-control" id="assistanceReason" rows="2" required placeholder="For example: approved hardship scholarship for 2026"></textarea></div>
                        <div class="col-12"><label class="form-label">Additional notes</label><textarea class="form-control" id="assistanceNotes" rows="2"></textarea></div>
                    </div>
                </form>
                <hr>
                <h6>Existing awards for this learner</h6>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Year</th><th>Programme</th><th>Coverage</th><th>Status</th><th></th></tr></thead><tbody id="studentAssistanceAwardsBody"></tbody></table></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-success" id="saveAssistanceBtn">Sponsor (Scholarship)</button></div>
        </div>
    </div>
</div>

<!-- Fee waiver modal. A waiver clears all or part of an outstanding balance. -->
<div class="modal fade" id="studentWaiverModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <div><h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Waive Off School Fees</h5><small id="waiverStudentLabel"></small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small">A waiver is a fee-relief decision that clears all or part of the selected learner(s)’ outstanding fee balance. It is not a scholarship.</div>
                <form id="studentWaiverForm">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Academic year</label><select class="form-select" id="waiverYear" required></select></div>
                        <div class="col-md-6"><label class="form-label">Waiver amount</label><select class="form-select" id="waiverScope"><option value="full">Clear outstanding balance</option><option value="amount">Specific amount</option></select></div>
                        <div class="col-md-6 d-none" id="waiverAmountWrap"><label class="form-label">Amount per learner (KES)</label><input type="number" class="form-control" id="waiverAmount" min="0" step="0.01"></div>
                        <div class="col-12"><label class="form-label">Reason / approval note *</label><textarea class="form-control" id="waiverReason" rows="2" required placeholder="Explain why the fee balance is being waived"></textarea></div>
                        <div class="col-12"><label class="form-label">Additional notes</label><textarea class="form-control" id="waiverNotes" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" id="saveWaiverBtn">Waive Off Fees</button></div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="studentId">
                    <div class="mb-3">
                        <label class="form-label">Student*</label>
                        <select class="form-select" id="paymentStudent" required></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (KES)*</label>
                        <input type="number" class="form-control" id="amount" required min="1" step="0.01">
                        <small class="text-muted">Outstanding: <span id="outstandingAmount">KES 0</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method*</label>
                        <select class="form-select" id="paymentMethod" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    <div class="mb-3" id="referenceDiv">
                        <label class="form-label">Transaction Reference</label>
                        <input type="text" class="form-control" id="reference" placeholder="e.g., M-Pesa code">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Date*</label>
                        <input type="date" class="form-control" id="paymentDate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="savePaymentBtn">Save Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- Student Billing History Modal -->
<div class="modal fade" id="studentBillingHistoryModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Full Billing History — <span id="historyStudentName"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="billingHistoryContent">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" onclick="StudentFeesController.printFeeStatement()"><i class="bi bi-printer me-1"></i>Print Statement</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/student_fees.js'); ?>
