<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-student-timeline">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Student Full Record</h3>
      <small class="text-muted">Complete academic, finance, attendance and conduct history from enrollment to today</small>
    </div>
    <button class="btn btn-sm btn-outline-primary" onclick="studentTimelineController.exportPDF()" id="exportBtn" style="display:none">
      <i class="bi bi-file-earmark-pdf me-1"></i> Export Report
    </button>
  </div>

  <!-- Student Search / Selector -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-5">
          <label class="form-label fw-semibold">Search Student</label>
          <input type="text" class="form-control" id="tlStudentSearch"
                 placeholder="Name, Admission No, NEMIS No…">
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Select Student</label>
          <select class="form-select" id="tlStudentSelect">
            <option value="">— Search and select a student —</option>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-primary w-100" onclick="studentTimelineController.load()">
            <i class="bi bi-search me-1"></i> Load
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Content (hidden until student loaded) -->
  <div id="tlContent" style="display:none">

    <!-- Student Bio Card -->
    <div class="card border-0 shadow-sm mb-4" style="border-left:5px solid #0d6efd !important">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-auto">
            <img id="tlPhoto" src="" alt="Photo"
                 class="rounded-circle border border-2"
                 style="width:80px;height:80px;object-fit:cover"
                 onerror="this.src='<?= $appBase ?>/assets/images/avatar.png'">
          </div>
          <div class="col">
            <h4 class="mb-1" id="tlStudentName">—</h4>
            <div class="row g-2 small text-muted">
              <div class="col-auto"><i class="bi bi-hash"></i> Adm: <strong id="tlAdmNo">—</strong></div>
              <div class="col-auto"><i class="bi bi-calendar"></i> DOB: <strong id="tlDob">—</strong></div>
              <div class="col-auto"><i class="bi bi-gender-ambiguous"></i> <strong id="tlGender">—</strong></div>
              <div class="col-auto"><i class="bi bi-book"></i> Class: <strong id="tlClass">—</strong></div>
              <div class="col-auto"><i class="bi bi-calendar-check"></i> Joined: <strong id="tlAdmDate">—</strong></div>
              <div class="col-auto"><i class="bi bi-person-badge"></i> Type: <strong id="tlStudentType">—</strong></div>
            </div>
          </div>
          <div class="col-md-auto text-end">
            <div class="mb-1"><span class="badge fs-6" id="tlStatusBadge">Active</span></div>
            <small class="text-muted" id="tlSponsor"></small>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary KPIs -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-primary" id="tlYearsEnrolled">—</div>
            <div class="text-muted small">Years Enrolled</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-success" id="tlTotalPaid">—</div>
            <div class="text-muted small">Total Fees Paid</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-danger" id="tlBalance">—</div>
            <div class="text-muted small">Current Balance</div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center">
          <div class="card-body py-3">
            <div class="fs-2 fw-bold text-warning" id="tlDisciplineCount">—</div>
            <div class="text-muted small">Discipline Cases</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" id="tlTabs">
      <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tlTabAcademic">
        <i class="bi bi-bar-chart-line me-1"></i>Academic
      </a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tlTabFinance">
        <i class="bi bi-cash-coin me-1"></i>Finance
      </a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tlTabAttendance">
        <i class="bi bi-calendar3 me-1"></i>Attendance
      </a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tlTabDiscipline">
        <i class="bi bi-shield-exclamation me-1"></i>Conduct
      </a></li>
      <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tlTabTransfers">
        <i class="bi bi-arrow-right-circle me-1"></i>Transfers
      </a></li>
    </ul>

    <div class="tab-content">

      <!-- Academic Tab -->
      <div class="tab-pane fade show active" id="tlTabAcademic">
        <div id="tlAcademicContent">
          <!-- Rendered by JS: year cards with term-level subject scores accordion -->
        </div>
      </div>

      <!-- Finance Tab -->
      <div class="tab-pane fade" id="tlTabFinance">
        <div class="row g-3 mb-3">
          <div class="col-md-8">
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-receipt me-2"></i>Payment History
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light"><tr>
                      <th>Date</th><th>Year</th><th>Term</th>
                      <th class="text-end">Amount</th><th>Method</th><th>Receipt</th><th>Status</th>
                    </tr></thead>
                    <tbody id="tlPaymentsBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-3">
              <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-credit-card me-2 text-success"></i>Fee Credits / Overpayments
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead class="table-light"><tr>
                      <th>Ref</th><th>Amount</th><th>Remaining</th><th>Status</th>
                    </tr></thead>
                    <tbody id="tlCreditsBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
            <div class="card border-0 shadow-sm">
              <div class="card-header bg-transparent fw-semibold">
                <i class="bi bi-list-check me-2 text-warning"></i>Outstanding Obligations
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead class="table-light"><tr>
                      <th>Year</th><th>Term</th><th>Fee</th><th class="text-end">Balance</th>
                    </tr></thead>
                    <tbody id="tlObligationsBody"></tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendance Tab -->
      <div class="tab-pane fade" id="tlTabAttendance">
        <div class="table-responsive">
          <table class="table table-hover border-0 shadow-sm">
            <thead class="table-light"><tr>
              <th>Year</th><th>Term</th><th class="text-center">Present</th>
              <th class="text-center">Absent</th><th class="text-center">Late</th>
              <th class="text-center">Total</th><th class="text-end">%</th>
            </tr></thead>
            <tbody id="tlAttendanceBody"></tbody>
          </table>
        </div>
      </div>

      <!-- Discipline Tab -->
      <div class="tab-pane fade" id="tlTabDiscipline">
        <div id="tlDisciplineContent"></div>
      </div>

      <!-- Transfers Tab -->
      <div class="tab-pane fade" id="tlTabTransfers">
        <div id="tlTransfersContent"></div>
        <div class="mt-3" id="tlTransferActions"></div>
      </div>

    </div><!-- /tab-content -->
  </div><!-- /tlContent -->

  <!-- Empty state -->
  <div id="tlEmpty" class="text-center py-5 text-muted">
    <i class="bi bi-person-lines-fill fs-1 mb-3 d-block text-secondary"></i>
    <p>Search for a student above to view their complete record.</p>
  </div>
</div>

<!-- Transfer Request Modal -->
<div class="modal fade" id="transferModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="bi bi-arrow-right-circle me-2"></i>Initiate Transfer</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="tr_student_id">
        <div class="alert alert-warning small">
          <i class="bi bi-exclamation-triangle me-2"></i>
          The system will check for outstanding fees before proceeding.
          All fee balances must be cleared or formally waived.
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Transfer Type</label>
          <select class="form-select" id="tr_type">
            <option value="inter_school">Inter-school (leaving for another school)</option>
            <option value="withdrawal">Withdrawal</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Destination School</label>
          <input type="text" class="form-control" id="tr_destination" placeholder="Name of receiving school">
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Reason</label>
          <textarea class="form-control" id="tr_reason" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-warning" onclick="studentTimelineController.submitTransfer()">Submit Request</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/student_timeline.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => studentTimelineController.init());</script>
