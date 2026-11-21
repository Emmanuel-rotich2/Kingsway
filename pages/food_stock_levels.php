<?php
/* Food Stock Levels — current food/catering inventory stock positions. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-boxes text-success me-2"></i>Food Stock Levels</h4>
      <p class="text-muted small mb-0 mt-1">Current stock position of all food and catering items.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="foodStockLevelsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div id="fslStats" class="row g-3 mb-4"></div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Item</th><th>Category</th><th>Location</th><th>On Hand</th><th>Min</th><th>Reorder</th><th>Status</th><th>Expiry</th><th>Value (KES)</th>
        </tr></thead>
        <tbody id="fslBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/food_stock_levels.js'); ?>
