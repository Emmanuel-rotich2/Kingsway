<?php
/**
 * Student Sanctions — PARTIAL
 * Deputy Head Discipline view of active sanctions (detentions, suspensions, expulsions).
 * JS controller: js/pages/sanctions.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h2 class="mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Student Sanctions</h2>
      <small class="text-muted">Detentions · Suspensions · Expulsions · Community service</small>
    </div>
    <button class="btn btn-danger" onclick="sanctionsController.showLogModal()">
      <i class="bi bi-plus-circle me-1"></i> Log Sanction
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="saStatDetention">—</div>
          <div class="text-muted small">Active Detentions</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-orange" id="saStatSuspension" style="color:#e67e22;">—</div>
          <div class="text-muted small">Active Suspensions</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="saStatExpulsion">—</div>
          <div class="text-muted small">Expulsions This Year</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="saStatPending">—</div>
          <div class="text-muted small">Pending Review</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filter Tabs -->
  <ul class="nav nav-tabs mb-3" id="saTabs">
    <li class="nav-item">
      <button class="nav-link active" data-filter="all" onclick="sanctionsController.filterByType('all', this)">All</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-filter="Detention" onclick="sanctionsController.filterByType('Detention', this)">Detention</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-filter="Suspension" onclick="sanctionsController.filterByType('Suspension', this)">Suspension</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-filter="Expulsion" onclick="sanctionsController.filterByType('Expulsion', this)">Expulsion</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-filter="Community Service" onclick="sanctionsController.filterByType('Community Service', this)">Community Service</button>
    </li>
  </ul>

  <!-- Sanctions Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Student</th>
              <th>Class</th>
              <th>Sanction Type</th>
              <th>Reason</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Issued By</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="saTableBody">
            <tr>
              <td colspan="9" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading sanctions…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- LOG SANCTION MODAL -->
<div class="modal fade" id="saModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Log Sanction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label fw-semibold">Student <span class="text-danger">*</span></label>
            <select id="saStudentId" class="form-select">
              <option value="">— Select student —</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Sanction Type <span class="text-danger">*</span></label>
            <select id="saType" class="form-select">
              <option value="">— Select type —</option>
              <option value="Detention">Detention</option>
              <option value="Internal Suspension">Internal Suspension</option>
              <option value="External Suspension">External Suspension</option>
              <option value="Expulsion">Expulsion</option>
              <option value="Community Service">Community Service</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select id="saStatus" class="form-select">
              <option value="active">Active</option>
              <option value="pending_review">Pending Review</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
            <textarea id="saReason" class="form-control" rows="3" placeholder="Describe the reason for this sanction…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
            <input type="date" id="saStartDate" class="form-control">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">End Date</label>
            <input type="date" id="saEndDate" class="form-control">
          </div>
          <div class="col-12">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="saParentNotified">
              <label class="form-check-label" for="saParentNotified">Parent/Guardian has been notified</label>
            </div>
          </div>
        </div>
        <div id="saError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="sanctionsController.logSanction()">Log Sanction</button>
      </div>
    </div>
  </div>
</div>

<!-- LIFT SANCTION MODAL -->
<div class="modal fade" id="saLiftModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Lift Sanction</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="saLiftId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Reason for Lifting <span class="text-danger">*</span></label>
          <textarea id="saLiftReason" class="form-control" rows="3" placeholder="Explain why this sanction is being lifted…"></textarea>
        </div>
        <div id="saLiftError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="sanctionsController.confirmLift()">Confirm Lift</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= $appBase ?>js/pages/sanctions.js?v=<?= time() ?>"></script>
<script>document.addEventListener('DOMContentLoaded', () => sanctionsController.init());</script>
