<?php
/* Stock Reports — inventory valuation by category + usage rates. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart text-success me-2"></i>Stock Reports</h4>
      <p class="text-muted small mb-0 mt-1">Inventory valuation by category and item usage rates.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="stockReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="border rounded-3 p-3 bg-white">
        <div class="text-muted small">Total Stock Value</div>
        <div class="fs-4 fw-bold" id="srGrandTotal">KES 0</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="border rounded-3 p-3 bg-white">
        <div class="text-muted small">Active Categories</div>
        <div class="fs-4 fw-bold" id="srCategoryCount">0</div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="border rounded-3 p-3 bg-white">
        <div class="text-muted small">Total Units On Hand</div>
        <div class="fs-4 fw-bold" id="srTotalQty">0</div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Valuation by Category</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Category</th><th>Items</th><th>Quantity</th><th>Value (KES)</th></tr></thead>
            <tbody id="srValBody"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Usage Rates (units issued out)</div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Year</th><th>Month</th><th>Item</th><th>Total Used</th></tr></thead>
            <tbody id="srUsageBody"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/stock_reports.js'); ?>
