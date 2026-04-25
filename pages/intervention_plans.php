<?php
/**
 * Intervention Plans — PARTIAL
 * CRUD for formal support/intervention plans per student.
 * JS controller: js/pages/intervention_plans.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-journal-plus me-2 text-primary"></i>Intervention Plans</h2>
      <small class="text-muted">Formal support plans with goals, timeline, and responsible staff</small>
    </div>
    <button class="btn btn-primary" onclick="interventionPlansController.showModal()">
      <i class="bi bi-plus-circle me-1"></i> Create Plan
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="ipStatTotal">—</div>
          <div class="text-muted small">Total Plans</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="ipStatActive">—</div>
          <div class="text-muted small">Active</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="ipStatCompleted">—</div>
          <div class="text-muted small">Completed</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="ipStatStudents">—</div>
          <div class="text-muted small">Students Supported</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div id="ipTableContainer">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
    </div>
  </div>

</div>

<!-- CREATE/EDIT MODAL -->
<div class="modal fade" id="ipModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ipModalTitle">Create Intervention Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ipEditId">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="ipStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Plan Type <span class="text-danger">*</span></label>
            <select id="ipPlanType" class="form-select">
              <option value="">— Select type —</option>
              <option value="Academic">Academic</option>
              <option value="Behavioural">Behavioural</option>
              <option value="Welfare">Welfare</option>
              <option value="Health">Health</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Goals <span class="text-danger">*</span></label>
            <textarea id="ipGoals" class="form-control" rows="3" placeholder="Describe the goals of this intervention plan…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
            <input type="date" id="ipStartDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Review Date <span class="text-danger">*</span></label>
            <input type="date" id="ipReviewDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Responsible Staff</label>
            <select id="ipResponsibleStaff" class="form-select">
              <option value="">— Select staff —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select id="ipStatus" class="form-select">
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="paused">Paused</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
        </div>
        <div id="ipError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="interventionPlansController.save()">Save Plan</button>
      </div>
    </div>
  </div>
</div>

<!-- VIEW DETAIL MODAL -->
<div class="modal fade" id="ipViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Intervention Plan Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="ipViewBody">
        <div class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/intervention_plans.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => interventionPlansController.init());</script>
