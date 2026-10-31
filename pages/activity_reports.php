<?php
/* Activity Reports — co-curricular activity analytics and upcoming activities. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-graph-up text-success me-2"></i>Activity Reports</h4>
      <p class="text-muted small mb-0 mt-1">Co-curricular activity analytics.</p>
    </div>
    <div class="d-flex gap-2"><button class="btn btn-outline-secondary btn-sm" id="arExportBtn" type="button"><i class="bi bi-download me-1"></i>Export CSV</button><button class="btn btn-outline-secondary btn-sm" id="arPrintBtn" type="button"><i class="bi bi-printer me-1"></i>Print / PDF</button></div>
  </div>

  <div class="row g-3 mb-4" id="arKpis"></div>

  <div class="row g-4">
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Upcoming Activities</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Activity</th><th>Category</th><th>Start</th><th>Days Left</th><th>Participants</th>
            </tr></thead>
            <tbody id="arUpcoming"><tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Activity List</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
              <th>Title</th><th>Category</th><th>Status</th><th>Participants</th><th>Start</th>
            </tr></thead>
            <tbody id="arList"><tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>
<style>@media print { #arExportBtn, #arPrintBtn { display:none !important; } }</style>

<?php asset_script($appBase, 'js/pages/activity_reports.js'); ?>
