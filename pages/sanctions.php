<?php
/**
 * Student Discipline — Deputy Head Discipline incident register.
 * JS controller: js/pages/sanctions.js
 */
/* PARTIAL — no DOCTYPE/html/head/body */
?>
<div class="container-fluid mt-3">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-0"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Student Discipline Cases</h2>
        <small class="text-muted">Record and resolve learner discipline incidents</small>
    </div>
    <button class="btn btn-danger" onclick="sanctionsController.showLogModal()">
      <i class="bi bi-plus-circle me-1"></i> Record Case
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
      <div class="fs-2 fw-bold text-primary" id="saStatTotal">—</div>
          <div class="text-muted small">Total Cases</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="saStatPending">—</div>
          <div class="text-muted small">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="saStatResolved">—</div>
          <div class="text-muted small">Resolved</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="saStatEscalated">—</div>
          <div class="text-muted small">Escalated</div>
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
              <th scope="col">Student</th>
              <th scope="col">Class</th>
              <th scope="col">Incident Type</th>
              <th scope="col">Severity</th>
              <th scope="col">Description</th>
              <th scope="col">Incident Date</th>
              <th scope="col">Status</th>
              <th scope="col">Action Taken</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="saTableBody">
            <tr>
              <td colspan="9" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading discipline cases…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- RECORD DISCIPLINE CASE MODAL -->
<div class="modal fade" id="saModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Record Discipline Case</h5>
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
            <label class="form-label fw-semibold">Incident Type <span class="text-danger">*</span></label>
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
            <label class="form-label fw-semibold">Severity <span class="text-danger">*</span></label>
            <select id="saSeverity" class="form-select">
              <option value="">— Select severity —</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
            <textarea id="saDescription" class="form-control" rows="3" placeholder="Describe the incident…"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Incident Date <span class="text-danger">*</span></label>
            <input type="date" id="saIncidentDate" class="form-control">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Action Taken</label>
            <textarea id="saActionTaken" class="form-control" rows="2" placeholder="Optional follow-up or action taken"></textarea>
          </div>
        </div>
        <div id="saError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="sanctionsController.logSanction()">Record Case</button>
      </div>
    </div>
  </div>
</div>

<!-- RESOLVE DISCIPLINE CASE MODAL -->
<div class="modal fade" id="saLiftModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Resolve Discipline Case</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="saLiftId">
        <div class="mb-3">
          <label class="form-label fw-semibold">Resolution / Action Taken <span class="text-danger">*</span></label>
          <textarea id="saLiftReason" class="form-control" rows="3" placeholder="Record the resolution or action taken…"></textarea>
        </div>
        <div id="saLiftError" class="alert alert-danger d-none"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" onclick="sanctionsController.confirmLift()">Resolve Case</button>
      </div>
    </div>
  </div>
</div>

<?php asset_script($appBase, 'js/pages/sanctions.js'); ?>
