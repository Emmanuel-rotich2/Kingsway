<?php
/**
 * Observation Schedule
 * Shows classroom observation records linked to the signed-in staff member.
 * JS controller: js/pages/observation_schedule.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-observation_schedule">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-eye me-2 text-primary"></i>Observation Schedule</h3>
    <small class="text-muted">Scheduled and completed sessions recorded in the school observation register</small>
    </div>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="osStatUpcoming">—</div>
          <div class="text-muted small">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="osStatCompleted">—</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="osStatThisTerm">—</div>
          <div class="text-muted small">Total Records</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Schedule Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-eye me-2 text-primary"></i>
      <span class="fw-semibold">Observation Sessions</span>
    </div>
    <div class="card-body p-0">
      <div id="osLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading schedule…</p>
      </div>
      <div id="osContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Learning Area</th>
                <th scope="col">Class</th>
                <th scope="col">Observer</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody id="osTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="osEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-eye-slash fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No observation records are linked to your staff account.</p>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/observation_schedule.js'); ?>
