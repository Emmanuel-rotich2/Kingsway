<?php
/* Health Reports — school health overview and sick bay activity. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-heart-pulse text-danger me-2"></i>Health Reports</h4>
      <p class="text-muted small mb-0 mt-1">School health overview and sick bay activity.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-success btn-sm" id="hrExport" type="button"><i class="bi bi-download me-1"></i>Export CSV</button>
      <button class="btn btn-outline-secondary btn-sm" id="hrPrint" type="button"><i class="bi bi-printer me-1"></i>Print / PDF</button>
      <button class="btn btn-outline-danger btn-sm" id="hrRefresh" type="button" onclick="healthReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
  </div>

  <div class="row g-3 mb-4" id="hrKpis"></div>
  <div class="alert alert-danger d-none" id="hrError" role="alert"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Recent Sick Bay Visits</div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Adm No</th><th>Student</th><th>Class</th><th>Complaint</th><th>Visit Time</th><th>Referred</th><th>Action</th>
        </tr></thead>
        <tbody id="hrBody"><tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/health_reports.js'); ?>
