<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-exception_reports">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-exclamation-octagon me-2 text-danger"></i>Exception Reports</h3>
      <small class="text-muted">Fee credit adjustments awaiting review or recently dismissed</small>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-sm btn-outline-secondary" id="erRefresh">Refresh</button><button class="btn btn-sm btn-outline-success" id="erExport">Export CSV</button><button class="btn btn-sm btn-outline-secondary" id="erPrint">Print</button></div>
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
          <div class="text-muted small">Awaiting Review</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="erStatCritical">—</div>
          <div class="text-muted small">High Value</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold" id="erStatHigh" style="color:#e67e22;">—</div>
          <div class="text-muted small">Dismissed</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Severity Filter -->
  <ul class="nav nav-tabs mb-3" id="erSeverityTabs">
    <li class="nav-item">
      <button class="nav-link active" data-severity="all">All</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-severity="high">
        <span class="badge bg-danger me-1">!</span>High Value
      </button>
    </li>
    <li class="nav-item">
    </li>
    <li class="nav-item">
      <button class="nav-link" data-severity="medium">Medium</button>
    </li>
    <li class="nav-item">
      <button class="nav-link" data-severity="low">Low</button>
    </li>
  </ul>

  <div class="alert alert-danger d-none" id="erError" role="alert"></div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Reference</th>
              <th scope="col">Exception Type</th>
              <th scope="col">Description</th>
              <th scope="col">Amount</th>
              <th scope="col">Affected Party</th>
              <th scope="col">Detected</th>
              <th scope="col">Status / Actions</th>
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

<?php asset_script($appBase, 'js/pages/exception_reports.js'); ?>
