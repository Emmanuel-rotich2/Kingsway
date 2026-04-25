<?php
/**
 * Welfare Follow-ups — PARTIAL
 * Track welfare follow-up actions for at-risk students.
 * JS controller: js/pages/welfare_follow_ups.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-clipboard2-check me-2 text-success"></i>Welfare Follow-ups</h2>
      <small class="text-muted">Track and manage follow-up actions for at-risk students</small>
    </div>
    <button class="btn btn-primary" onclick="welfareFUController.showModal()">
      <i class="bi bi-plus-circle me-1"></i> Add Follow-up
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="wfStatTotal">—</div>
          <div class="text-muted small">Total</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="wfStatPending">—</div>
          <div class="text-muted small">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="wfStatCompleted">—</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="wfStatOverdue">—</div>
          <div class="text-muted small">Overdue</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="wfTableContainer">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="wfModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="wfModalTitle">Add Follow-up</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="wfEditId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="wfStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
            <select id="wfCategory" class="form-select">
              <option value="">— Select category —</option>
              <option value="Attendance">Attendance</option>
              <option value="Discipline">Discipline</option>
              <option value="Counseling">Counseling</option>
              <option value="Health">Health</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Follow-up Action <span class="text-danger">*</span></label>
            <textarea id="wfAction" class="form-control" rows="3" placeholder="Describe the follow-up action…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Due Date <span class="text-danger">*</span></label>
            <input type="date" id="wfDueDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Assigned To</label>
            <select id="wfAssignedTo" class="form-select">
              <option value="">— Select staff —</option>
            </select>
          </div>
        </div>
        <div id="wfError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="welfareFUController.save()">Save Follow-up</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/welfare_follow_ups.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => welfareFUController.init());</script>
