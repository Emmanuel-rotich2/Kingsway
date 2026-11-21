<?php
/* Welfare Summary — welfare case summary and analytics. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-heart-pulse text-danger me-2"></i>Welfare Summary</h4>
      <p class="text-muted small mb-0 mt-1">Student welfare case analytics by category, priority and status.</p>
    </div>
    <button class="btn btn-outline-danger btn-sm" data-kw-onclick="welfareSummaryController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4" id="wsKpis"></div>

  <div class="row g-4">
    <div class="col-lg-4">
      <div class="bg-white border rounded-3 overflow-hidden h-100">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">By Category</div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Category</th><th>Count</th><th>Bar</th></tr></thead>
          <tbody id="wsCat"><tr><td colspan="3" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
        </table></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="bg-white border rounded-3 overflow-hidden h-100">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">By Priority</div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Priority</th><th>Count</th><th>Bar</th></tr></thead>
          <tbody id="wsPrio"><tr><td colspan="3" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
        </table></div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="bg-white border rounded-3 overflow-hidden h-100">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">By Status</div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
          <thead class="table-light"><tr><th>Status</th><th>Count</th><th>Bar</th></tr></thead>
          <tbody id="wsStatus"><tr><td colspan="3" class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm"></div></td></tr></tbody>
        </table></div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/welfare_summary.js'); ?>
