<?php
/* Boarding Reports — boarding analytics and dormitory occupancy summary. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-success me-2"></i>Boarding Reports</h4>
      <p class="text-muted small mb-0 mt-1">Boarding KPIs and dormitory occupancy summary.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-success btn-sm" data-kw-onclick="boardingReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
      <button class="btn btn-outline-success btn-sm" data-kw-onclick="boardingReportsController.exportCSV()"><i class="bi bi-download me-1"></i>Export CSV</button>
      <button class="btn btn-outline-secondary btn-sm" id="brPrint" type="button"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
  </div>

  <div class="row g-3 mb-4" id="brKpis"></div>
  <div class="alert alert-danger d-none" id="brError" role="alert"></div>

  <div class="card border-success-subtle bg-light mb-4">
    <div class="card-body py-2"><div class="d-flex justify-content-between align-items-center gap-2 flex-wrap"><div><strong><i class="bi bi-stars text-success me-1"></i>Boarding operations assistant</strong><div class="small text-muted">Reviews roll-call counts for the selected dates and current occupancy. It does not change boarding records.</div></div><div class="d-flex gap-2 flex-wrap align-items-end"><label class="small">From <input type="date" class="form-control form-control-sm" id="aiBoardingDateFrom" value="<?= htmlspecialchars(date('Y-m-d', strtotime('-29 days')), ENT_QUOTES, 'UTF-8') ?>"></label><label class="small">To <input type="date" class="form-control form-control-sm" id="aiBoardingDateTo" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"></label><button type="button" class="btn btn-outline-success btn-sm" id="queueAiBoardingSummary"><i class="bi bi-stars me-1"></i>Review roll-call</button><button type="button" class="btn btn-outline-secondary btn-sm" id="refreshAiBoardingSummary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh reviews</button></div></div><div id="aiBoardingSummaries" class="row g-2 mt-2"></div></div>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Dormitory Occupancy</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Dormitory</th><th>Code</th><th>Gender</th><th>House Parent</th><th>Capacity</th><th>Occupied</th><th>Available</th><th>Occupancy</th>
        </tr></thead>
        <tbody id="brBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/boarding_reports.js'); ?>
<?php asset_script($appBase, 'js/pages/ai_boarding_summary.js'); ?>
