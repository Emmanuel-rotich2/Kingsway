<?php
/* Suspensions & Expulsions — disciplinary cases focused on suspensions and expulsions. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-person-x text-danger me-2"></i>Suspensions & Expulsions</h4>
      <p class="text-muted small mb-0 mt-1">Serious disciplinary cases — suspensions and expulsions.</p>
    </div>
    <button class="btn btn-outline-danger btn-sm" data-kw-onclick="suspensionsExpulsionsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <input type="text" class="form-control form-control-sm" id="seSearch" placeholder="Search student, adm no…" style="max-width: 220px;">
    <select class="form-select form-select-sm" id="seStatus" style="max-width: 160px;">
      <option value="">All statuses</option>
      <option>pending</option><option>approved</option><option>resolved</option><option>rejected</option>
    </select>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Student</th><th>Adm No</th><th>Class</th><th>Type</th><th>Severity</th><th>Date</th><th>Description</th><th>Status</th>
        </tr></thead>
        <tbody id="seBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/suspensions_expulsions.js'); ?>
