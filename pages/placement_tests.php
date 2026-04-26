<?php
/**
 * Placement Tests — PARTIAL
 * Schedule and record placement/entry tests for new student admissions.
 * JS controller: js/pages/placement_tests.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-file-earmark-text me-2 text-success"></i>Placement Tests</h2>
      <small class="text-muted">Schedule and record entry/placement assessments for new admissions</small>
    </div>
    <button class="btn btn-primary" onclick="placementTestsController.showScheduleModal()">
      <i class="bi bi-plus-circle me-1"></i> Schedule Test
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="ptStatPending">—</div>
          <div class="text-muted small">Pending Tests</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="ptStatMonth">—</div>
          <div class="text-muted small">Tests This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="ptStatPassRate">—</div>
          <div class="text-muted small">Pass Rate</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="ptStatAwaiting">—</div>
          <div class="text-muted small">Awaiting Placement</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter tabs -->
  <ul class="nav nav-tabs mb-3" id="ptTabs">
    <li class="nav-item">
      <button class="nav-link active" data-status="pending"
              onclick="placementTestsController.switchTab(this, 'pending')">Pending</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="completed"
              onclick="placementTestsController.switchTab(this, 'completed')">Completed</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-status="all"
              onclick="placementTestsController.switchTab(this, 'all')">All</button>
    </li>
  </ul>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="ptTableBody">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- SCHEDULE TEST MODAL -->
<div class="modal fade" id="ptScheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Schedule Placement Test</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Applicant <span class="text-danger">*</span></label>
            <select id="ptApplicantId" class="form-select">
              <option value="">— Select applicant —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Class Applying For <span class="text-danger">*</span></label>
            <input type="text" id="ptAppliedClass" class="form-control" placeholder="e.g. Grade 4">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Test Date <span class="text-danger">*</span></label>
            <input type="date" id="ptTestDate" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Test Type <span class="text-danger">*</span></label>
            <select id="ptTestType" class="form-select">
              <option value="Written">Written</option>
              <option value="Oral">Oral</option>
              <option value="Both">Both (Written + Oral)</option>
            </select>
          </div>
        </div>
        <div id="ptScheduleError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="placementTestsController.saveSchedule()">Schedule Test</button>
      </div>
    </div>
  </div>
</div>

<!-- ENTER SCORE MODAL -->
<div class="modal fade" id="ptScoreModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Enter Test Score</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ptScoreTestId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold">Score <span class="text-danger">*</span></label>
            <input type="number" id="ptScore" class="form-control" min="0" oninput="placementTestsController.computeResult()">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Max Score</label>
            <input type="number" id="ptMaxScore" class="form-control" value="100" oninput="placementTestsController.computeResult()">
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Pass Mark</label>
            <input type="number" id="ptPassMark" class="form-control" value="50" oninput="placementTestsController.computeResult()">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Result</label>
            <input type="text" id="ptResult" class="form-control" readonly placeholder="Auto-computed">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Class Recommended</label>
            <input type="text" id="ptClassRecommended" class="form-control" placeholder="e.g. Grade 4">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Notes</label>
            <textarea id="ptScoreNotes" class="form-control" rows="2" placeholder="Examiner observations…"></textarea>
          </div>
        </div>
        <div id="ptScoreError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="placementTestsController.saveScore()">Save Score</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/placement_tests.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => placementTestsController.init());</script>
