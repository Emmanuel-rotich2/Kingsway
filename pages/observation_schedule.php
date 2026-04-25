<?php
/**
 * Observation Schedule — PARTIAL
 * Planned classroom observation sessions.
 * JS controller: js/pages/observation_schedule.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-observation_schedule">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-eye me-2 text-primary"></i>Observation Schedule</h3>
      <small class="text-muted">Classroom observation sessions planned and completed</small>
    </div>
    <button class="btn btn-primary btn-sm" id="osScheduleBtn">
      <i class="bi bi-plus-circle me-1"></i> Schedule Observation
    </button>
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
          <div class="text-muted small">This Term</div>
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
                <th>Date</th>
                <th>Time</th>
                <th>Class</th>
                <th>Subject</th>
                <th>Observer</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="osTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="osEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-eye-slash fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No observations scheduled yet.</p>
      </div>
    </div>
  </div>

</div>

<!-- Schedule Observation Modal -->
<div class="modal fade" id="osModal" tabindex="-1" aria-labelledby="osModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom">
        <h5 class="modal-title" id="osModalLabel"><i class="bi bi-eye me-2 text-primary"></i>Schedule Observation</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="osObsDate">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Time</label>
            <input type="time" class="form-control" id="osObsTime">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Class <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="osClass" placeholder="e.g. Grade 4A">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="osSubject" placeholder="e.g. Mathematics">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Observer / Mentor</label>
            <input type="text" class="form-control" id="osObserver" placeholder="Name of observer">
          </div>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="osSaveBtn">
          <i class="bi bi-check-circle me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>
<script src="<?= $appBase ?>/js/pages/observation_schedule.js?v=<?= time() ?>"></script>
