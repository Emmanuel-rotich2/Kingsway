<?php
/* Food Low Stock — food items at or below reorder with attention flags. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-exclamation-diamond text-warning me-2"></i>Food Low Stock</h4>
      <p class="text-muted small mb-0 mt-1">Food items flagged as low, reorder or out of stock.</p>
    </div>
    <button class="btn btn-outline-warning btn-sm" data-kw-onclick="foodLowStockController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div id="flsStats" class="row g-3 mb-4"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Item</th><th>Category</th><th>Location</th><th>On Hand</th><th>Min</th><th>Reorder</th><th>Status</th><th>Expiry</th><th>Value (KES)</th>
        </tr></thead>
        <tbody id="flsBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/food_low_stock.js'); ?>
