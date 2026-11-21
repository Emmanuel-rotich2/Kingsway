<?php
/* Low Stock Alerts — items at or below their reorder level. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Low Stock Alerts</h4>
      <p class="text-muted small mb-0 mt-1">Items whose on-hand quantity has dropped to or below the reorder level.</p>
    </div>
    <div class="d-flex gap-2">
      <input type="text" class="form-control form-control-sm" id="lsaSearch" placeholder="Search item…" style="width: 220px;">
      <button class="btn btn-outline-danger btn-sm" data-kw-onclick="lowStockAlertsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
    </div>
  </div>

  <div id="lsaStats" class="row g-3 mb-4"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Item</th><th>Category</th><th>Location</th><th>On Hand</th><th>Reorder Level</th><th>Shortage</th><th>Unit</th><th>Unit Cost</th>
        </tr></thead>
        <tbody id="lsaBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/low_stock_alerts.js'); ?>
