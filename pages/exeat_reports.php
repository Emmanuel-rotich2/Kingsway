<?php
/* Exeat Reports — leave request summary and status breakdown. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-sign-turn-right text-primary me-2"></i>Exeat Reports</h4>
      <p class="text-muted small mb-0 mt-1">Leave request summary by status and dormitory.</p>
    </div>
    <button class="btn btn-outline-primary btn-sm" data-kw-onclick="exeatReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4" id="xrKpis"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Leave Requests by Status</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Status</th><th>Count</th><th>Share</th>
        </tr></thead>
        <tbody id="xrStatusBody"><tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden mt-4">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Recent Leave Requests</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Dormitory</th><th>Type</th><th>Start</th><th>Return</th><th>Status</th>
        </tr></thead>
        <tbody id="xrBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/exeat_reports.js'); ?>
