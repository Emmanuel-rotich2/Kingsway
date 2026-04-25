<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-audit_logs">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-journal-check me-2 text-primary"></i>Financial Audit Logs</h3>
      <small class="text-muted">Immutable transaction audit trail — all financial actions logged</small>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="auditLogsController.exportCSV()">
      <i class="bi bi-download me-1"></i> Export
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="alStatTotal">—</div>
          <div class="text-muted small">Total Log Entries</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="alStatToday">—</div>
          <div class="text-muted small">Today's Entries</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="alStatModified">—</div>
          <div class="text-muted small">Modifications</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="alStatDeleted">—</div>
          <div class="text-muted small">Deletions</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-3">
          <input type="text" id="alSearch" class="form-control form-control-sm" placeholder="Search reference, user, action…"
                 oninput="auditLogsController.filter()">
        </div>
        <div class="col-md-2">
          <select id="alAction" class="form-select form-select-sm" onchange="auditLogsController.filter()">
            <option value="">All Actions</option>
            <option value="CREATE">Create</option>
            <option value="UPDATE">Update</option>
            <option value="DELETE">Delete</option>
            <option value="APPROVE">Approve</option>
            <option value="REJECT">Reject</option>
          </select>
        </div>
        <div class="col-md-2">
          <input type="date" id="alDateFrom" class="form-control form-control-sm" onchange="auditLogsController.filter()">
        </div>
        <div class="col-md-2">
          <input type="date" id="alDateTo" class="form-control form-control-sm" onchange="auditLogsController.filter()">
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Timestamp</th>
              <th>User</th>
              <th>Action</th>
              <th>Entity</th>
              <th>Reference</th>
              <th>Amount</th>
              <th>Details</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody id="alTableBody">
            <tr>
              <td colspan="8" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading audit logs…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="p-3 border-top d-flex justify-content-between align-items-center">
        <span class="text-muted small" id="alPaginationInfo">—</span>
        <div class="btn-group btn-group-sm">
          <button class="btn btn-outline-secondary" id="alPrevBtn" onclick="auditLogsController.prevPage()">
            <i class="bi bi-chevron-left"></i>
          </button>
          <button class="btn btn-outline-secondary" id="alNextBtn" onclick="auditLogsController.nextPage()">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>js/pages/audit_logs.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => auditLogsController.init());</script>
