<?php
/* Welfare Records — student welfare cases. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-heart text-danger me-2"></i>Welfare Records</h4>
      <p class="text-muted small mb-0 mt-1">Student welfare cases with filters.</p>
    </div>
    <button class="btn btn-outline-danger btn-sm" data-kw-onclick="welfareRecordsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="d-flex gap-2 mb-3 align-items-center flex-wrap">
    <input type="text" class="form-control form-control-sm" id="wrSearch" placeholder="Search student, case title…" style="max-width: 220px;">
    <select class="form-select form-select-sm" id="wrCategory" style="max-width: 160px;"></select>
    <select class="form-select form-select-sm" id="wrPriority" style="max-width: 140px;"></select>
    <select class="form-select form-select-sm" id="wrStatus" style="max-width: 150px;">
      <option value="">All statuses</option>
      <option>open</option><option>in_progress</option><option>resolved</option><option>closed</option>
    </select>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Case</th><th>Student</th><th>Category</th><th>Priority</th><th>Assigned To</th><th>Follow-up</th><th>Status</th>
        </tr></thead>
        <tbody id="wrBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/welfare_records.js'); ?>
