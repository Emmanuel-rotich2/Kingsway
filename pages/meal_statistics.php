<?php
/* Meal Statistics — planned vs served allocations across a date range. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-pie-chart text-success me-2"></i>Meal Statistics</h4>
      <p class="text-muted small mb-0 mt-1">Meal allocations: planned versus served across days.</p>
    </div>
    <div class="d-flex gap-2 align-items-end flex-wrap">
      <div><label class="form-label small fw-semibold">From</label><input type="date" class="form-control form-control-sm" id="msFrom"></div>
      <div><label class="form-label small fw-semibold">To</label><input type="date" class="form-control form-control-sm" id="msTo"></div>
      <button class="btn btn-outline-success btn-sm" id="msExportBtn"><i class="bi bi-download me-1"></i>Export CSV</button>
      <button class="btn btn-outline-secondary btn-sm" id="msPrintBtn"><i class="bi bi-printer me-1"></i>Print / PDF</button>
    </div>
  </div>

  <div id="msStats" class="row g-3 mb-4"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Date</th><th>Meal Type</th><th>Planned Items</th><th>Allocated</th><th>Served</th><th>Serve Rate</th>
        </tr></thead>
        <tbody id="msBody"><tr><td colspan="6" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/meal_statistics.js'); ?>
