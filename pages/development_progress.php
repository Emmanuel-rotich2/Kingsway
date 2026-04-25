<?php
/**
 * Development Progress — PARTIAL
 * Professional development milestones tracker.
 * JS controller: js/pages/development_progress.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-development_progress">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Development Progress</h3>
      <small class="text-muted">Professional development milestones and placement timeline</small>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="dpStatCompleted">—</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="dpStatInProgress">—</div>
          <div class="text-muted small">In Progress</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="dpStatPending">—</div>
          <div class="text-muted small">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="dpStatWeeksLeft">—</div>
          <div class="text-muted small">Weeks Remaining</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Overall Progress Bar -->
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
      <div class="d-flex justify-content-between mb-2">
        <span class="fw-semibold">Overall Placement Progress</span>
        <span id="dpOverallPct" class="text-muted small fw-semibold">0%</span>
      </div>
      <div class="progress" style="height:12px;">
        <div class="progress-bar bg-primary" id="dpProgressBar" role="progressbar" style="width:0%"></div>
      </div>
    </div>
  </div>

  <!-- Milestones Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-flag me-2 text-primary"></i>
      <span class="fw-semibold">Milestones</span>
    </div>
    <div class="card-body p-0">
      <div id="dpLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading milestones…</p>
      </div>
      <div id="dpContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Milestone</th>
                <th>Category</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="dpTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="dpEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-flag fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No milestones defined for your placement.</p>
      </div>
    </div>
  </div>

</div>
<script src="<?= $appBase ?>/js/pages/development_progress.js?v=<?= time() ?>"></script>
