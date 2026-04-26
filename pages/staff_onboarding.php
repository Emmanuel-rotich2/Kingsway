<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-staff-onboarding">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-person-check-fill me-2 text-success"></i>Staff Onboarding</h3>
      <small class="text-muted">Track new hire onboarding, document collection, probation reviews, and clearance</small>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" onclick="staffOnboardingController.showPendingPanel()">
        <i class="bi bi-exclamation-circle me-1"></i>Pending Actions
      </button>
      <button class="btn btn-sm btn-success" id="newOnboardingBtn" style="display:none"
              onclick="staffOnboardingController.showInitiateModal()">
        <i class="bi bi-plus-circle me-1"></i>Initiate Onboarding
      </button>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary"   id="obStatTotal">—</div>
          <div class="text-muted small">Total</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning"   id="obStatInProgress">—</div>
          <div class="text-muted small">In Progress</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success"   id="obStatCompleted">—</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger"    id="obStatOverdue">—</div>
          <div class="text-muted small">Have Overdue</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-2">
      <div class="card border-0 shadow-sm text-center h-100">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="obStatPending">—</div>
          <div class="text-muted small">Not Started</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters + View toggle -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-3">
          <input type="text" class="form-control form-control-sm" id="obSearch"
                 placeholder="Search staff name, number…">
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="obStatusFilter">
            <option value="">All Statuses</option>
            <option value="pending">Not Started</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
            <option value="extended">Extended</option>
            <option value="terminated">Terminated</option>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="obDeptFilter">
            <option value="">All Departments</option>
          </select>
        </div>
        <div class="col-md-1">
          <button class="btn btn-sm btn-outline-secondary w-100" onclick="staffOnboardingController.clearFilters()">Clear</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Onboarding Cards Grid -->
  <div id="obCardGrid" class="row g-3">
    <div class="col-12 text-center py-5">
      <div class="spinner-border text-success"></div>
      <div class="text-muted mt-2">Loading onboarding records…</div>
    </div>
  </div>
</div>

<!-- ============================================================
     INITIATE ONBOARDING MODAL
     ============================================================ -->
<div class="modal fade" id="initiateOnboardModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Initiate Onboarding</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info small">
          <i class="bi bi-info-circle me-2"></i>
          This will auto-generate all standard onboarding tasks and a probationary contract
          based on the staff member's category. No tasks are created manually.
        </div>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Staff Member <span class="text-danger">*</span></label>
            <select class="form-select" id="ob_staff_id" required></select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Joining / Start Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="ob_start_date" required>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Probation Period</label>
            <select class="form-select" id="ob_probation_months">
              <option value="1">1 Month (Intern / Casual)</option>
              <option value="3" selected>3 Months (Standard)</option>
              <option value="6">6 Months (Management)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Contract Type</label>
            <select class="form-select" id="ob_contract_type">
              <option value="probation">Probation → Permanent</option>
              <option value="contract">Fixed-Term Contract</option>
              <option value="internship">Internship / TP</option>
              <option value="temporary">Temporary</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Mentor / Buddy</label>
            <select class="form-select" id="ob_mentor_id">
              <option value="">— No mentor assigned —</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea class="form-control" id="ob_notes" rows="2" placeholder="Any special onboarding instructions…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" onclick="staffOnboardingController.initiateOnboarding()">
          <i class="bi bi-rocket-takeoff me-1"></i> Start Onboarding
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     ONBOARDING DETAIL OFFCANVAS (Task board + Documents + Reviews)
     ============================================================ -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="obDetailOffcanvas" style="width:680px">
  <div class="offcanvas-header bg-success text-white">
    <div>
      <h5 class="offcanvas-title mb-0" id="obDetailName">—</h5>
      <small id="obDetailMeta" class="opacity-75"></small>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">

    <!-- Progress bar -->
    <div class="px-4 py-3 border-bottom bg-light">
      <div class="d-flex justify-content-between small mb-1">
        <span class="fw-semibold">Overall Progress</span>
        <span id="obDetailPct" class="text-success fw-bold">0%</span>
      </div>
      <div class="progress" style="height:10px">
        <div class="progress-bar bg-success" id="obDetailProgressBar" style="width:0%"></div>
      </div>
      <div class="row g-2 mt-2 small text-muted">
        <div class="col-auto"><i class="bi bi-check-circle text-success me-1"></i><span id="obDoneCount">0</span> done</div>
        <div class="col-auto"><i class="bi bi-hourglass text-warning me-1"></i><span id="obPendingCount">0</span> pending</div>
        <div class="col-auto"><i class="bi bi-exclamation-circle text-danger me-1"></i><span id="obOverdueCount">0</span> overdue</div>
        <div class="col-auto ms-auto"><i class="bi bi-calendar3 me-1"></i><span id="obDaysLeft">—</span> days left</div>
      </div>
    </div>

    <!-- Tab nav -->
    <ul class="nav nav-tabs px-3 pt-2" id="obDetailTabs">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#obTabTasks">
        <i class="bi bi-list-check me-1"></i>Tasks
      </a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#obTabDocs">
        <i class="bi bi-folder2 me-1"></i>Documents
      </a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#obTabReviews">
        <i class="bi bi-star me-1"></i>Probation Reviews
      </a></li>
    </ul>

    <div class="tab-content px-3 pb-4 pt-2">

      <!-- Tasks tab -->
      <div class="tab-pane fade show active" id="obTabTasks">
        <div id="obTasksList"></div>
      </div>

      <!-- Documents tab -->
      <div class="tab-pane fade" id="obTabDocs">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Documents Collected</h6>
          <button class="btn btn-sm btn-outline-success" onclick="staffOnboardingController.showDocModal()">
            <i class="bi bi-plus me-1"></i>Record Document
          </button>
        </div>
        <div id="obDocsList"></div>
      </div>

      <!-- Reviews tab -->
      <div class="tab-pane fade" id="obTabReviews">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Probation Reviews</h6>
          <button class="btn btn-sm btn-outline-primary" onclick="staffOnboardingController.showReviewModal()">
            <i class="bi bi-plus me-1"></i>Add Review
          </button>
        </div>
        <div id="obReviewsList"></div>
      </div>

    </div>
  </div>
</div>

<!-- ============================================================
     DOCUMENT COLLECTION MODAL
     ============================================================ -->
<div class="modal fade" id="obDocModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i>Record Document</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="doc_onboarding_id">
        <input type="hidden" id="doc_staff_id">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
            <select class="form-select" id="doc_type" required>
              <option value="">Select type…</option>
              <option value="national_id">National ID / Passport</option>
              <option value="kra_pin">KRA PIN Certificate</option>
              <option value="nssf">NSSF Membership Card</option>
              <option value="nhif">NHIF / SHIF Card</option>
              <option value="certificates">Academic Certificates</option>
              <option value="tsc_certificate">TSC Certificate</option>
              <option value="recommendation_letters">Recommendation Letters</option>
              <option value="bank_details">Bank Account Details</option>
              <option value="passport_photos">Passport Photos</option>
              <option value="contract_signed">Signed Contract</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Document Name / Description</label>
            <input type="text" class="form-control" id="doc_name" placeholder="e.g. National ID No. 12345678">
          </div>
          <div class="col-6">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="doc_original_seen">
              <label class="form-check-label fw-semibold" for="doc_original_seen">Original Verified</label>
            </div>
          </div>
          <div class="col-6">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" id="doc_copy_filed">
              <label class="form-check-label fw-semibold" for="doc_copy_filed">Copy Filed in HR</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <input type="text" class="form-control" id="doc_notes" placeholder="Optional notes…">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" onclick="staffOnboardingController.saveDocument()">Save Document</button>
      </div>
    </div>
  </div>
</div>

<!-- ============================================================
     PROBATION REVIEW MODAL
     ============================================================ -->
<div class="modal fade" id="obReviewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="bi bi-clipboard2-check me-2"></i>Probation Review</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rev_onboarding_id">
        <input type="hidden" id="rev_staff_id">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label fw-semibold">Review Month</label>
            <select class="form-select" id="rev_month">
              <option value="1">Month 1 Review</option>
              <option value="2">Month 2 Review</option>
              <option value="3">Month 3 (Final) Review</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold">Review Date</label>
            <input type="date" class="form-control" id="rev_date">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Overall Rating</label>
            <select class="form-select" id="rev_rating">
              <option value="excellent">Excellent</option>
              <option value="good">Good</option>
              <option value="satisfactory" selected>Satisfactory</option>
              <option value="needs_improvement">Needs Improvement</option>
              <option value="unsatisfactory">Unsatisfactory</option>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label fw-semibold">Attendance Score (%)</label>
            <input type="number" class="form-control" id="rev_attendance" min="0" max="100" step="1">
          </div>
          <div class="col-4">
            <label class="form-label fw-semibold">Performance Score (%)</label>
            <input type="number" class="form-control" id="rev_performance" min="0" max="100" step="1">
          </div>
          <div class="col-4">
            <label class="form-label fw-semibold">Conduct Score (%)</label>
            <input type="number" class="form-control" id="rev_conduct" min="0" max="100" step="1">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Strengths Observed</label>
            <textarea class="form-control" id="rev_strengths" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Areas to Improve</label>
            <textarea class="form-control" id="rev_areas" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Outcome <span class="text-danger">*</span></label>
            <select class="form-select" id="rev_outcome" required onchange="staffOnboardingController.onOutcomeChange()">
              <option value="continue">Continue Probation</option>
              <option value="extend_probation">Extend Probation</option>
              <option value="confirm_permanent">Confirm Permanent Employment ✓</option>
              <option value="terminate">Terminate Employment ✗</option>
            </select>
          </div>
          <div class="col-12" id="revExtendMonthsRow" style="display:none">
            <label class="form-label fw-semibold">Extend by (months)</label>
            <select class="form-select" id="rev_extend_months">
              <option value="1">1 Month</option>
              <option value="3" selected>3 Months</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes / Comments</label>
            <textarea class="form-control" id="rev_notes" rows="2"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" onclick="staffOnboardingController.saveReview()">Save Review</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/staff_onboarding.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => staffOnboardingController.init());</script>
