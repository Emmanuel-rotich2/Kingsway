<?php /* PARTIAL — no DOCTYPE/html/head/body */ ?>
<div class="container-fluid py-4" id="page-depreciation">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h3 class="mb-0"><i class="bi bi-graph-down me-2 text-secondary"></i>Asset Depreciation</h3>
      <small class="text-muted">Straight-line depreciation schedules for all fixed assets</small>
    </div>
    <button class="btn btn-sm btn-outline-secondary" onclick="depreciationController.exportCSV()">
      <i class="bi bi-download me-1"></i> Export
    </button>
  </div>

  <!-- Stats -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-primary" id="dpStatAssets">—</div>
          <div class="text-muted small">Total Assets</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-secondary" id="dpStatOriginal">—</div>
          <div class="text-muted small">Original Cost (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-success" id="dpStatCurrentVal">—</div>
          <div class="text-muted small">Current Book Value (KES)</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card border-0 shadow-sm text-center">
        <div class="card-body py-3">
          <div class="fs-2 fw-bold text-danger" id="dpStatTotalDep">—</div>
          <div class="text-muted small">Accumulated Depreciation (KES)</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-center">
        <div class="col-md-3">
          <select id="dpCategory" class="form-select form-select-sm" onchange="depreciationController.filter()">
            <option value="">All Categories</option>
          </select>
        </div>
        <div class="col-md-2">
          <select id="dpYear" class="form-select form-select-sm" onchange="depreciationController.filter()">
            <option value="">All Years</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Asset Code</th>
              <th>Asset Name</th>
              <th>Category</th>
              <th>Purchase Date</th>
              <th>Original Cost</th>
              <th>Dep. Rate</th>
              <th>Annual Dep.</th>
              <th>Book Value</th>
              <th>% Remaining</th>
            </tr>
          </thead>
          <tbody id="dpTableBody">
            <tr>
              <td colspan="9" class="text-center py-4">
                <div class="spinner-border spinner-border-sm text-primary"></div>
                <span class="ms-2 text-muted">Loading depreciation schedule…</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script src="<?= $appBase ?>js/pages/depreciation.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => depreciationController.init());</script>
