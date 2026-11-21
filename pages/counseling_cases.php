<?php
/* Counseling Cases — counseling sessions and case management. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-person-badge text-primary me-2"></i>Counseling Cases</h4>
      <p class="text-muted small mb-0 mt-1">Counseling sessions and case management.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" data-kw-onclick="counselingCasesController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <input type="text" class="form-control form-control-sm" id="ccSearch" placeholder="Search case code, title, name…" style="max-width: 240px;">
    <select class="form-select form-select-sm" id="ccStatus" style="max-width: 160px;">
      <option value="">All statuses</option>
      <option>open</option><option>in_progress</option><option>resolved</option><option>closed</option>
    </select>
    <select class="form-select form-select-sm" id="ccCategory" style="max-width: 180px;">
      <option value="">All categories</option>
    </select>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Case</th><th>Counselee</th><th>Type</th><th>Priority</th><th>Session Date</th><th>Counselor</th><th>Status</th>
        </tr></thead>
        <tbody id="ccBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/counseling_cases.js'); ?>
