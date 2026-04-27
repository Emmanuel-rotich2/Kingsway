<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-assessment_overview">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
    <div>
      <h3 class="mb-1"><i class="bi bi-clipboard2-data me-2 text-primary"></i>Assessment Overview</h3>
      <p class="text-muted mb-0 small">All formative &amp; summative assessments — create, grade, and track completion.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <select class="form-select form-select-sm" id="aoClassFilter" style="width:auto;" onchange="assessmentOverviewCtrl.reload()">
        <option value="">All My Classes</option>
      </select>
      <select class="form-select form-select-sm" id="aoTermFilter" style="width:auto;" onchange="assessmentOverviewCtrl.reload()">
        <option value="">Current Term</option>
      </select>
      <button class="btn btn-primary btn-sm" onclick="assessmentOverviewCtrl.showCreateModal()">
        <i class="bi bi-plus-circle me-1"></i> Create Assessment
      </button>
    </div>
  </div>

  <!-- Stats row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="aoStatTotal">—</div>
          <div class="text-muted small">Total Assessments</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="aoStatPending">—</div>
          <div class="text-muted small">Pending Grading</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="aoStatGraded">—</div>
          <div class="text-muted small">Fully Graded</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="aoStatStudents">—</div>
          <div class="text-muted small">Students in My Classes</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Tabs -->
  <ul class="nav nav-tabs mb-3" id="aoTabs">
    <li class="nav-item">
      <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aoTabFormative">
        <i class="bi bi-pencil-square me-1"></i>Formative (CA)
        <span class="badge bg-warning text-dark ms-1" id="aoCaCount">0</span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aoTabSummative">
        <i class="bi bi-journal-check me-1"></i>Summative (Exams)
        <span class="badge bg-danger ms-1" id="aoExamCount">0</span>
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aoTabProgress">
        <i class="bi bi-graph-up me-1"></i>Grading Progress
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-bs-toggle="tab" data-bs-target="#aoTabLA">
        <i class="bi bi-bar-chart me-1"></i>By Learning Area
      </button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- ── FORMATIVE ASSESSMENTS ──────────────────────────────────────────── -->
    <div class="tab-pane fade show active" id="aoTabFormative">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Title</th><th>Type</th><th>Learning Area</th><th>Class</th>
                  <th>Date</th><th>Max</th><th class="text-center">Graded</th>
                  <th class="text-center">Avg %</th><th>Status</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="aoFormativeBody">
                <tr><td colspan="10" class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── SUMMATIVE (EXAMS) ──────────────────────────────────────────────── -->
    <div class="tab-pane fade" id="aoTabSummative">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Title</th><th>Type</th><th>Learning Area</th><th>Class</th>
                  <th>Exam Date</th><th>Max Marks</th>
                  <th class="text-center">Graded</th><th class="text-center">Avg %</th>
                  <th>Status</th><th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="aoSummativeBody">
                <tr><td colspan="10" class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── GRADING PROGRESS ───────────────────────────────────────────────── -->
    <div class="tab-pane fade" id="aoTabProgress">
      <div class="row g-3" id="aoProgressCards">
        <div class="col-12 text-center py-4">
          <div class="spinner-border text-primary"></div>
        </div>
      </div>
    </div>

    <!-- ── BY LEARNING AREA ───────────────────────────────────────────────── -->
    <div class="tab-pane fade" id="aoTabLA">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Learning Area</th><th>Class</th>
                  <th class="text-center">CA Count</th><th class="text-center">Exam Count</th>
                  <th class="text-center">CA Avg %</th><th class="text-center">Exam Avg %</th>
                  <th class="text-center">Overall Grade</th>
                </tr>
              </thead>
              <tbody id="aoLABody">
                <tr><td colspan="7" class="text-center py-4">
                  <div class="spinner-border spinner-border-sm text-primary"></div>
                </td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /.tab-content -->
</div>

<!-- ── CREATE ASSESSMENT MODAL ──────────────────────────────────────────── -->
<div class="modal fade" id="aoCreateModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Create Assessment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
            <input type="text" id="aoTitle" class="form-control" placeholder="e.g. Chapter 3 Quiz — Fractions">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Assessment Type <span class="text-danger">*</span></label>
            <select id="aoType" class="form-select">
              <option value="">— Select type —</option>
              <optgroup label="Formative (CA)" id="aoTypeFormative"></optgroup>
              <optgroup label="Summative (Exam)" id="aoTypeSummative"></optgroup>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
            <select id="aoClass" class="form-select" onchange="assessmentOverviewCtrl.onClassChange()">
              <option value="">— Select class —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Learning Area <span class="text-danger">*</span></label>
            <select id="aoLearningArea" class="form-select" onchange="assessmentOverviewCtrl.onLAChange()">
              <option value="">— Select learning area —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Strand</label>
            <select id="aoStrand" class="form-select">
              <option value="">— Select strand (optional) —</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" id="aoDate" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Max Marks <span class="text-danger">*</span></label>
            <input type="number" id="aoMaxMarks" class="form-control" value="20" min="1" max="100">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Term</label>
            <select id="aoTermModal" class="form-select">
              <option value="">Current Term</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Instructions / Notes</label>
            <textarea id="aoInstructions" class="form-control" rows="2" placeholder="Optional instructions for students…"></textarea>
          </div>
        </div>
        <div id="aoCreateError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="assessmentOverviewCtrl.saveAssessment()">
          <i class="bi bi-check-circle me-1"></i> Create Assessment
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── MARKS ENTRY MODAL ─────────────────────────────────────────────────── -->
<div class="modal fade" id="aoMarksModal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="aoMarksTitle">Enter Marks</h5>
          <small class="text-muted" id="aoMarksSubtitle"></small>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- Quick stats bar -->
        <div class="d-flex gap-4 mb-3 p-3 bg-light rounded">
          <div><span class="fw-semibold" id="aoMrkEntered">0</span><span class="text-muted small ms-1">entered</span></div>
          <div><span class="fw-semibold" id="aoMrkTotal">0</span><span class="text-muted small ms-1">students</span></div>
          <div><span class="fw-semibold text-primary" id="aoMrkAvg">—</span><span class="text-muted small ms-1">avg %</span></div>
          <div><span class="fw-semibold text-success" id="aoMrkEE">0</span><span class="text-muted small ms-1">EE</span></div>
          <div><span class="fw-semibold text-primary" id="aoMrkME">0</span><span class="text-muted small ms-1">ME</span></div>
          <div><span class="fw-semibold text-warning" id="aoMrkAE">0</span><span class="text-muted small ms-1">AE</span></div>
          <div><span class="fw-semibold text-danger" id="aoMrkBE">0</span><span class="text-muted small ms-1">BE</span></div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-dark">
              <tr>
                <th>#</th>
                <th>Student Name</th>
                <th>Adm No</th>
                <th style="width:130px;">Marks <small id="aoMrkMax" class="fw-normal">/ ?</small></th>
                <th class="text-center">%</th>
                <th class="text-center">CBC Grade</th>
                <th>Remarks</th>
              </tr>
            </thead>
            <tbody id="aoMarksBody"></tbody>
          </table>
        </div>
        <div id="aoMarksError" class="alert alert-danger d-none mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-success" onclick="assessmentOverviewCtrl.saveMarks()">
          <i class="bi bi-save me-1"></i> Save All Marks
        </button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/assessment_overview.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => assessmentOverviewCtrl.init());</script>
