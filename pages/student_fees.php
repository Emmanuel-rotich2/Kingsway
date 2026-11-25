<?php
/**
 * Student Fees Page (Per-student fee tracking)
 * HTML structure only - logic will be in js/pages/student_fees.js
 * Embedded in app_layout.php
 */
?>

<style>
/* Fee accounts: a bursar's ledger. One idea carries the page: the balance
   line (opening + fees − reductions − credits − paid = balance). Figures are
   tabular; colour only means something: owed, in credit, reduced. */
#feeAccountsPage {
    --fa-ink: #1f2a44;
    --fa-muted: #667085;
    --fa-rule: #e3e6eb;
    --fa-paper: #ffffff;
    --fa-owed: #a23b2a;
    --fa-credit: #2f7d5b;
    --fa-reduce: #4c5ba8;
    color: var(--fa-ink);
    background: var(--fa-paper);
    border: 1px solid var(--fa-rule);
    border-radius: 10px;
    padding: 1.25rem 1.25rem 1rem;
}
#feeAccountsPage .fl-num, #feeAccountsPage .fl-table td.num, #feeAccountsPage .fl-ledger strong { font-variant-numeric: tabular-nums; }
#feeAccountsPage .fl-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: .75rem; margin-bottom: 1rem; }
#feeAccountsPage .fl-title { font-size: 1.5rem; font-weight: 650; letter-spacing: -.01em; margin: 0; }
#feeAccountsPage .fl-sub { margin: .15rem 0 0; color: var(--fa-muted); }
#feeAccountsPage .fl-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
#feeAccountsPage .fl-filters { display: grid; grid-template-columns: minmax(12rem, 2fr) repeat(4, minmax(8rem, 1fr)); gap: .6rem; margin-bottom: 1.25rem; }
#feeAccountsPage .fl-field { display: flex; flex-direction: column; gap: .2rem; margin: 0; }
#feeAccountsPage .fl-field > span, #feeAccountsPage .fl-rows > span { font-size: .78rem; color: var(--fa-muted); }

/* The balance line */
#feeAccountsPage .fl-ledger { display: flex; flex-wrap: wrap; align-items: stretch; border-top: 2px solid var(--fa-ink); border-bottom: 1px solid var(--fa-rule); }
#feeAccountsPage .fl-step { flex: 1 1 8.5rem; padding: .75rem .9rem .8rem 0; position: relative; }
#feeAccountsPage .fl-step + .fl-step { padding-left: 1.4rem; }
#feeAccountsPage .fl-step + .fl-step::before { content: attr(data-op); position: absolute; left: .35rem; top: .7rem; color: var(--fa-muted); font-size: 1.05rem; }
#feeAccountsPage .fl-step small { display: block; font-size: .78rem; color: var(--fa-muted); }
#feeAccountsPage .fl-step strong { display: block; font-size: 1.05rem; font-weight: 600; margin-top: .1rem; white-space: nowrap; }
#feeAccountsPage .fl-step em { display: block; font-style: normal; font-size: .75rem; color: var(--fa-muted); margin-top: .1rem; }
#feeAccountsPage .fl-step.is-reduce strong { color: var(--fa-reduce); }
#feeAccountsPage .fl-step.is-result { flex: 1.4 1 14rem; background: #f6f7f9; padding-right: .9rem; }
#feeAccountsPage .fl-step.is-result strong { font-size: 1.45rem; font-weight: 700; }
#feeAccountsPage .is-owed { color: var(--fa-owed); }
#feeAccountsPage .is-credit { color: var(--fa-credit); }

/* Terms of the year, under the line */
#feeAccountsPage .fl-terms { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem; padding: .8rem 0 .2rem; }
#feeAccountsPage .fl-term { font-size: .85rem; }
#feeAccountsPage .fl-term header { display: flex; justify-content: space-between; font-weight: 600; }
#feeAccountsPage .fl-term .fl-bar { height: 4px; background: var(--fa-rule); border-radius: 2px; margin: .35rem 0; overflow: hidden; }
#feeAccountsPage .fl-term .fl-bar span { display: block; height: 100%; background: var(--fa-ink); }
#feeAccountsPage .fl-term p { margin: 0; color: var(--fa-muted); }
#feeAccountsPage .fl-term.is-selected header { color: var(--fa-reduce); }
#feeAccountsPage .fl-note { font-size: .8rem; color: var(--fa-muted); margin: .4rem 0 .9rem; }

/* Learner rows */
#feeAccountsPage .fl-table { font-size: .875rem; margin-bottom: 0; }
#feeAccountsPage .fl-table thead th { font-size: .78rem; font-weight: 600; color: var(--fa-muted); border-bottom: 1px solid var(--fa-ink); white-space: nowrap; background: transparent; }
#feeAccountsPage .fl-table td.num, #feeAccountsPage .fl-table th.num { text-align: right; white-space: nowrap; }
#feeAccountsPage .fl-table td.dim { color: #b0b6c0; }
#feeAccountsPage .fl-who strong { display: block; font-weight: 600; }
#feeAccountsPage .fl-who span { font-size: .78rem; color: var(--fa-muted); }
#feeAccountsPage .fl-state { font-size: .78rem; font-weight: 600; white-space: nowrap; }
#feeAccountsPage .fl-toggle { border: 0; background: none; color: var(--fa-muted); padding: 0 .25rem; }
#feeAccountsPage .fl-toggle:focus-visible, #feeAccountsPage .btn:focus-visible { outline: 2px solid var(--fa-reduce); outline-offset: 2px; }
#feeAccountsPage tr.fl-detail td { background: #f6f7f9; font-size: .8rem; }
#feeAccountsPage .fl-detail-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .5rem 1.5rem; padding: .25rem 0; }
#feeAccountsPage .fl-detail-grid b { display: block; }
#feeAccountsPage .fl-foot { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .75rem; border-top: 1px solid var(--fa-rule); margin-top: .75rem; padding-top: .75rem; }
#feeAccountsPage .fl-rows { display: flex; align-items: center; gap: .4rem; margin: 0; }
@media (max-width: 768px) {
    #feeAccountsPage { padding: 1rem; }
    #feeAccountsPage .fl-filters { grid-template-columns: 1fr 1fr; }
    #feeAccountsPage .fl-field-search { grid-column: 1 / -1; }
    #feeAccountsPage .fl-step { flex-basis: 45%; }
}
@media print {
    #feeAccountsPage { border: 0; padding: 0; }
    @page { size: A4 landscape; margin: 10mm; }
}
</style>

<section class="fl" id="feeAccountsPage">
<div class="card border-success-subtle bg-light mb-3" id="aiFeeArrearsPanel"><div class="card-body py-2"><div class="d-flex justify-content-between align-items-center gap-2"><div><strong><i class="bi bi-stars text-success me-1"></i>Fee arrears assistant</strong><div class="small text-muted">Aggregate aging and collection guidance only; it never names learners and never records payments or waivers.</div></div><button type="button" class="btn btn-outline-success btn-sm" id="queueAiArrearsReview"><i class="bi bi-stars me-1"></i>Review arrears with assistant</button></div><div id="aiFeeArrearsDrafts" class="row g-2 mt-2"></div></div></div>
    <header class="fl-head">
        <div>
            <h1 class="fl-title">Fee accounts</h1>
            <p class="fl-sub" id="faPeriod">Loading the year…</p>
        </div>
        <div class="fl-actions d-print-none">
            <button class="btn btn-sm btn-outline-secondary" id="awardScholarshipBtn" hidden><i class="bi bi-award"></i> Sponsor (Scholarship)</button>
            <button class="btn btn-sm btn-outline-secondary" id="waiveFeesBtn" hidden><i class="bi bi-shield-check"></i> Waive Off Fees</button>
            <button class="btn btn-sm btn-outline-secondary" id="printSelectedFeesBtn"><i class="bi bi-printer"></i> Print selected</button>
            <button class="btn btn-sm btn-outline-secondary" id="exportBtn"><i class="bi bi-download"></i> Export CSV</button>
        </div>
    </header>

    <div class="fl-filters d-print-none">
        <label class="fl-field fl-field-search">
            <span>Search</span>
            <input type="search" class="form-control form-control-sm" id="searchStudent" placeholder="Name or admission number">
        </label>
        <label class="fl-field"><span>Academic year</span><select class="form-select form-select-sm" id="yearFilter"><option value="">Loading…</option></select></label>
        <label class="fl-field"><span>Term</span><select class="form-select form-select-sm" id="termFilter"><option value="">Whole year</option></select></label>
        <label class="fl-field"><span>Class</span><select class="form-select form-select-sm" id="classFilter"><option value="">All classes</option></select></label>
        <label class="fl-field"><span>Account</span>
            <select class="form-select form-select-sm" id="statusFilter">
                <option value="">All accounts</option>
                <option value="pending">Not yet paid</option>
                <option value="partial">Part paid</option>
                <option value="paid">Cleared</option>
                <option value="credit">In credit</option>
            </select>
        </label>
    </div>

    <!-- The school's position for the selection, read left to right as the
         bursar's balance: opening balance + fees − reductions − credits − paid. -->
    <div class="fl-ledger" id="faLedger" aria-live="polite"></div>
    <div class="fl-terms" id="faTerms"></div>

    <p class="fl-note d-print-none">Payments are entered and reconciled in Payment Register.</p>

<!-- Students Table -->
        <div class="table-responsive">
            <table class="table fl-table align-middle" id="feesTable">
                <thead>
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

        <div class="fl-foot">
            <div id="pagination" class="d-flex justify-content-between align-items-center flex-wrap gap-2"></div>
            <select class="d-none" id="perPageFilter" aria-hidden="true" tabindex="-1">
                <option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="250">250</option>
            </select>
        </div>
</section>

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
                <div class="alert alert-info small">This is a school sponsorship decision, separate from a fee waiver. Select exactly how long it applies. It may be terminated prospectively by an authorised finance user; an approved fee waiver is append-only and cannot be revoked.</div>
                <form id="studentAssistanceForm">
                    <input type="hidden" id="assistanceStudentId">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Academic year *</label><select class="form-select" id="assistanceYear" required></select></div>
                        <div class="col-md-6"><label class="form-label">Programme *</label><select class="form-select" id="assistanceProgram" required></select></div>
                        <div class="col-md-4"><label class="form-label">Sponsorship period *</label><select class="form-select" id="assistancePeriodType" required><option value="academic_year">Whole academic year</option><option value="term">One term</option><option value="custom">Custom dates</option></select></div>
                        <div class="col-md-4" id="assistanceTermWrap"><label class="form-label">Term *</label><select class="form-select" id="assistanceTerm"></select></div>
                        <div class="col-md-2 d-none" id="assistanceStartsWrap"><label class="form-label">Starts</label><input type="date" class="form-control" id="assistanceStartsOn"></div>
                        <div class="col-md-2 d-none" id="assistanceEndsWrap"><label class="form-label">Ends</label><input type="date" class="form-control" id="assistanceEndsOn"></div>
                        <div class="col-md-6"><label class="form-label">Coverage</label><select class="form-select" id="assistanceCoverage"><option value="full">Full sponsorship (100%)</option><option value="percentage">Percentage</option><option value="fixed_amount">Fixed amount per obligation</option></select></div>
                        <div class="col-md-6" id="assistancePercentageWrap"><label class="form-label">Percentage covered *</label><input type="number" class="form-control" id="assistancePercentage" min="0" max="100" step="0.01"></div>
                        <div class="col-md-6 d-none" id="assistanceAmountWrap"><label class="form-label">Amount covered per obligation (KES) *</label><input type="number" class="form-control" id="assistanceAmount" min="0" step="0.01"></div>
                        <div class="col-12"><label class="form-label">Reason / approval note *</label><textarea class="form-control" id="assistanceReason" rows="2" required placeholder="For example: approved hardship scholarship for 2026"></textarea></div>
                        <div class="col-12"><label class="form-label">Additional notes</label><textarea class="form-control" id="assistanceNotes" rows="2"></textarea></div>
                    </div>
                </form>
                <hr>
                <h6>Existing awards for this learner</h6>
                <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Year</th><th>Programme</th><th>Coverage</th><th>Period</th><th>Status</th><th></th></tr></thead><tbody id="studentAssistanceAwardsBody"></tbody></table></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-success" id="saveAssistanceBtn" data-year-write>Sponsor (Scholarship)</button></div>
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
                <div class="alert alert-warning small">A waiver is a permanent, append-only school fee-relief decision. It clears all or part of the selected obligation and cannot be cancelled after approval; record a new correcting decision if required.</div>
                <form id="studentWaiverForm">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Academic year</label><select class="form-select" id="waiverYear" required></select></div>
                        <div class="col-md-6"><label class="form-label">One-time relief</label><select class="form-select" id="waiverScope"><option value="full">Clear outstanding balance</option><option value="amount">Specific KES amount</option><option value="percentage">Percentage of current balance</option></select></div>
                        <div class="col-md-6 d-none" id="waiverAmountWrap"><label class="form-label" id="waiverAmountLabel">Amount per learner (KES)</label><input type="number" class="form-control" id="waiverAmount" min="0" step="0.01"></div>
                        <div class="col-12"><label class="form-label">Reason / approval note *</label><textarea class="form-control" id="waiverReason" rows="2" required placeholder="Explain why the fee balance is being waived"></textarea></div>
                        <div class="col-12"><label class="form-label">Additional notes</label><textarea class="form-control" id="waiverNotes" rows="2"></textarea></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-warning" id="saveWaiverBtn" data-year-write>Waive Off Fees</button></div>
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
        <button type="button" class="btn btn-outline-secondary" data-kw-onclick="StudentFeesController.printFeeStatement()"><i class="bi bi-printer me-1"></i>Print Statement</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Prompt Parent Modal (STK Push) -->
<div class="modal fade" id="promptParentModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-phone me-2"></i>Prompt Parent for Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="promptParentForm">
          <input type="hidden" id="promptStudentId">
          <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label fw-bold">Student</label><input type="text" class="form-control" id="promptStudentName" readonly></div>
            <div class="col-md-6"><label class="form-label fw-bold">Admission No</label><input type="text" class="form-control" id="promptAdmNo" readonly></div>
            <div class="col-md-6"><label class="form-label fw-bold">Class</label><input type="text" class="form-control" id="promptClass" readonly></div>
            <div class="col-md-6"><label class="form-label fw-bold">Balance Due</label><input type="text" class="form-control" id="promptBalance" readonly></div>
          </div>
          <hr>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Parent/Guardian Name *</label>
              <input type="text" class="form-control" id="promptParentName" required placeholder="Enter parent/guardian name">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone Number *</label>
              <input type="tel" class="form-control" id="promptPhone" required placeholder="2547XXXXXXXX" pattern="254[0-9]{9}">
              <small class="text-muted">Format: 2547XXXXXXXX</small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Amount (KES) *</label>
              <input type="number" class="form-control" id="promptAmount" required min="1" step="0.01">
              <small class="text-muted">Outstanding: <span id="promptOutstanding">KES 0</span></small>
            </div>
            <div class="col-md-6">
              <label class="form-label">Payment Purpose</label>
              <select class="form-select" id="promptPurpose">
                <option value="fees">School Fees</option>
                <option value="uniforms">Uniforms</option>
                <option value="transport">Transport</option>
                
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea class="form-control" id="promptDescription" rows="2" placeholder="Optional description for the payment prompt"></textarea>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="sendStkPushBtn"><i class="bi bi-send me-1"></i>Send STK Push</button>
      </div>
    </div>
  </div>
</div>

<!-- Payment Trends Modal -->
<div class="modal fade" id="paymentTrendsModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="bi bi-graph-up me-2"></i>Payment Trends</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="paymentTrendsContent">
        <div class="text-center py-4"><div class="spinner-border text-light"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="exportTrendsBtn"><i class="bi bi-download me-1"></i>Export</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase ?? '', 'js/pages/student_fees.js'); ?>
<?php asset_script($appBase ?? '', 'js/pages/ai_fee_arrears.js'); ?>
