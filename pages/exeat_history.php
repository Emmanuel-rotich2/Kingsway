<?php
/* Exeat History — all leave requests with status filter. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-clock-history text-info me-2"></i>Exeat History</h4>
      <p class="text-muted small mb-0 mt-1">All leave requests recorded for boarders.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <select class="form-select form-select-sm" id="ehStatus" style="width: 170px;">
        <option value="">All statuses</option>
        <option>pending</option><option>approved</option><option>rejected</option><option>checked_out</option><option>checked_in</option>
      </select>
      <button class="btn btn-outline-info btn-sm" data-kw-onclick="exeatHistoryController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Type</th><th>Start</th><th>Return</th><th>Reason</th><th>Status</th>
        </tr></thead>
        <tbody id="ehBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/exeat_history.js'); ?>
