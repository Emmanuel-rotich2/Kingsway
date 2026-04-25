<?php
/**
 * Chronic Absenteeism — PARTIAL
 * Students with persistent attendance issues in the teacher's class.
 * JS controller: js/pages/chronic_absenteeism.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-chronic_absenteeism">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-calendar-x me-2 text-danger"></i>Chronic Absenteeism</h3>
      <small class="text-muted">Students with persistent attendance issues in your class</small>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <label class="fw-semibold small">Threshold:</label>
      <select class="form-select form-select-sm" id="caThreshold" style="width:auto;">
        <option value="70">Below 70% (Critical)</option>
        <option value="80" selected>Below 80% (Warning)</option>
        <option value="90">Below 90% (Monitor)</option>
      </select>
      <button class="btn btn-outline-primary btn-sm" id="caApplyBtn">Apply</button>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center border-start border-danger border-3">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="caStatCritical">—</div>
          <div class="text-muted small">Critical (&lt;70%)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center border-start border-warning border-3">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="caStatWarning">—</div>
          <div class="text-muted small">Warning (&lt;80%)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="caStatTotal">—</div>
          <div class="text-muted small">Total Flagged</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Students Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-people me-2 text-danger"></i>
      <span class="fw-semibold">Flagged Students</span>
    </div>
    <div class="card-body p-0">
      <div id="caLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading…</p>
      </div>
      <div id="caContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Student</th>
                <th>Class</th>
                <th>Attendance %</th>
                <th>Days Missed</th>
                <th>Last Absent</th>
                <th>Parent Notified</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="caTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="caEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-check-circle fs-1 text-success"></i>
        <p class="text-muted mt-2 mb-0">No students flagged at this threshold.</p>
      </div>
    </div>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/chronic_absenteeism.js?v=<?= time() ?>"></script>
