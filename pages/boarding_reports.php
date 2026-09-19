<?php
/* Boarding Reports — boarding analytics and dormitory occupancy summary. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line text-success me-2"></i>Boarding Reports</h4>
      <p class="text-muted small mb-0 mt-1">Boarding KPIs and dormitory occupancy summary.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" onclick="boardingReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4" id="brKpis"></div>

  <div class="card border-success-subtle bg-light mb-4">
    <div class="card-body py-2"><div class="d-flex justify-content-between align-items-center gap-2"><div><strong><i class="bi bi-stars text-success me-1"></i>Boarding operations assistant</strong><div class="small text-muted">Summarizes occupancy and roll-call signals for authorized staff. It does not change boarding records.</div></div><button type="button" class="btn btn-outline-success btn-sm" id="queueAiBoardingSummary"><i class="bi bi-stars me-1"></i>Review today’s exceptions</button></div><div id="aiBoardingSummaries" class="row g-2 mt-2"></div></div>
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
