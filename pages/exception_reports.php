<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-exception_reports">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Exception Reports</h3>
      <small class="text-muted">Flagged transactions · Unmatched payments · Policy anomalies</small>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="exceptionReportsController.exportCSV()">
      <i class="bi bi-download me-1"></i> Export CSV
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-warning" id="erStatTotal">—</div>
          <div class="text-muted small">Total Exceptions</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-info" id="erStatUnmatched">—</div>
          <div class="text-muted small">Unmatched Payments</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="erStatCritical">—</div>
          <div class="text-muted small">Critical</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold" id="erStatHigh" style="color:#e67e22;">—</div>
          <div class="text-muted small">High Severity</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Severity Filter -->
  <ul class="nav nav-tabs mb-3" id="erSeverityTabs">
    <li class="nav-item">
      <button class="nav-link active" onclick="exceptionReportsController.filterBySeverity('all',this)">All</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="exceptionReportsController.filterBySeverity('critical',this)">
        <span class="badge bg-danger me-1">!</span>Critical
      </button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="exceptionReportsController.filterBySeverity('high',this)">High</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="exceptionReportsController.filterBySeverity('medium',this)">Medium</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" onclick="exceptionReportsController.filterBySeverity('low',this)">Low</button>
    </li>
  </ul>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Reference</th>
              <th>Exception Type</th>
              <th>Description</th>
              <th>Amount</th>
              <th>Affected Party</th>
              <th>Detected</th>
              <th>Status / Actions</th>
            </tr>
          </thead>
          <tbody id="erTableBody">
            <tr>
              <td colspan="7" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading exceptions…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>/js/pages/exception_reports.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => exceptionReportsController.init());</script>
