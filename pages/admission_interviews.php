<?php
/**
 * Admission Interviews — PARTIAL
 * Schedule and record headteacher admission interviews.
 * JS controller: js/pages/admission_interviews.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-person-badge me-2 text-info"></i>Admission Interviews</h2>
      <small class="text-muted">Schedule and record headteacher admission interviews</small>
    </div>
    <button class="btn btn-primary" onclick="admissionInterviewsController.showScheduleModal()">
      <i class="bi bi-plus-circle me-1"></i> Schedule Interview
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="aiStatToday">—</div>
          <div class="text-muted small">Scheduled Today</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="aiStatPending">—</div>
          <div class="text-muted small">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="aiStatCompletedMonth">—</div>
          <div class="text-muted small">Completed This Month</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="aiStatRate">—</div>
          <div class="text-muted small">Admission Rate</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="aiTableBody">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- SCHEDULE INTERVIEW MODAL -->
<div class="modal fade" id="aiScheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Schedule Interview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Applicant <span class="text-danger">*</span></label>
            <select id="aiApplicantId" class="form-select">
              <option value="">— Select applicant —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Interview Date <span class="text-danger">*</span></label>
            <input type="date" id="aiInterviewDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Time <span class="text-danger">*</span></label>
            <input type="time" id="aiInterviewTime" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Interviewer <span class="text-danger">*</span></label>
            <select id="aiInterviewerId" class="form-select">
              <option value="">— Select staff member —</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Location / Room</label>
            <input type="text" id="aiLocation" class="form-control" placeholder="e.g. Head Teacher's Office">
          </div>
        </div>
        <div id="aiScheduleError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="admissionInterviewsController.saveSchedule()">Schedule</button>
      </div>
    </div>
  </div>
</div>

<!-- RECORD OUTCOME MODAL -->
<div class="modal fade" id="aiOutcomeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Interview Outcome</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="aiOutcomeInterviewId">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Outcome <span class="text-danger">*</span></label>
            <select id="aiOutcome" class="form-select">
              <option value="Recommended">Recommended</option>
              <option value="Not Recommended">Not Recommended</option>
              <option value="Conditional">Conditional</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Interview Notes</label>
            <textarea id="aiOutcomeNotes" class="form-control" rows="3" placeholder="Key observations from the interview…"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Next Step <span class="text-danger">*</span></label>
            <select id="aiNextStep" class="form-select">
              <option value="Proceed to Admission">Proceed to Admission</option>
              <option value="Waitlist">Waitlist</option>
              <option value="Decline">Decline</option>
            </select>
          </div>
        </div>
        <div id="aiOutcomeError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="admissionInterviewsController.saveOutcome()">Save Outcome</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>/js/pages/admission_interviews.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => admissionInterviewsController.init());</script>
