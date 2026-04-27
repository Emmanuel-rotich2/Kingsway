<?php
/**
 * Mentor Meetings — PARTIAL
 * Schedule and log mentor meetings.
 * JS controller: js/pages/mentor_meetings.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid py-4" id="page-mentor_meetings">

  <!-- Header -->
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
      <h3 class="mb-0"><i class="bi bi-people me-2 text-primary"></i>Mentor Meetings</h3>
      <small class="text-muted">Schedule, log, and review meetings with your mentor</small>
    </div>
    <button class="btn btn-primary btn-sm" id="mmScheduleBtn">
      <i class="bi bi-calendar-plus me-1"></i> Schedule Meeting
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="mmStatUpcoming">—</div>
          <div class="text-muted small">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="mmStatThisMonth">—</div>
          <div class="text-muted small">This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="mmStatTotal">—</div>
          <div class="text-muted small">Total Meetings</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Meetings Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom d-flex align-items-center py-2">
      <i class="bi bi-calendar-event me-2 text-primary"></i>
      <span class="fw-semibold">All Meetings</span>
    </div>
    <div class="card-body p-0">
      <div id="mmLoading" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="text-muted mt-2 mb-0">Loading meetings…</p>
      </div>
      <div id="mmContent" style="display:none;">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Date &amp; Time</th>
                <th>Type</th>
                <th>Location</th>
                <th>Agenda</th>
                <th>Status</th>
                <th>Notes</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="mmTableBody"></tbody>
          </table>
        </div>
      </div>
      <div id="mmEmpty" style="display:none;" class="text-center py-5">
        <i class="bi bi-calendar-x fs-1 text-muted"></i>
        <p class="text-muted mt-2 mb-0">No meetings scheduled yet.</p>
      </div>
    </div>
  </div>

</div>

<!-- Schedule Meeting Modal -->
<div class="modal fade" id="mmModal" tabindex="-1" aria-labelledby="mmModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow">
      <div class="modal-header border-bottom">
        <h5 class="modal-title" id="mmModalLabel"><i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule Meeting</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="mmMeetingId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
            <input type="date" class="form-control" id="mmMeetDate">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Time <span class="text-danger">*</span></label>
            <input type="time" class="form-control" id="mmMeetTime">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Location</label>
            <input type="text" class="form-control" id="mmLocation" placeholder="e.g. Staff Room, Room 12">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Meeting Type</label>
            <select class="form-select" id="mmMeetType">
              <option value="scheduled">Scheduled</option>
              <option value="informal">Informal</option>
              <option value="observation_debrief">Observation Debrief</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Agenda / Purpose</label>
            <textarea class="form-control" id="mmAgenda" rows="3" placeholder="What will you discuss?"></textarea>
          </div>
          <div class="col-12" id="mmNotesGroup" style="display:none;">
            <label class="form-label fw-semibold">Meeting Notes (after meeting)</label>
            <textarea class="form-control" id="mmNotes" rows="3" placeholder="Record outcomes and action items…"></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer border-top">
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="mmSaveBtn">
          <i class="bi bi-check-circle me-1"></i> Save
        </button>
      </div>
    </div>
  </div>
</div>
<script src="<?= $appBase ?>/js/pages/mentor_meetings.js?v=<?= time() ?>"></script>
