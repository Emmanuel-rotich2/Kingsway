<?php
/* Food Orders — food/catering supplier purchase order tracking. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-basket text-success me-2"></i>Food Orders</h4>
      <p class="text-muted small mb-0 mt-1">Purchase orders raised with food and catering suppliers.</p>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3 flex-wrap align-items-end">
    <div><label class="form-label small fw-semibold">Search</label><input type="text" class="form-control form-control-sm" id="foSearch" placeholder="Order no / supplier…"></div>
    <div><label class="form-label small fw-semibold">Status</label><select class="form-select form-select-sm" id="foStatus">
      <option value="">All statuses</option>
      <option>draft</option><option>pending</option><option>approved</option><option>ordered</option><option>received</option><option>cancelled</option>
    </select></div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="foodOrdersController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Order No</th><th>Supplier</th><th>Contact</th><th>Order Date</th><th>Expected Delivery</th><th>Items</th><th>Total (KES)</th><th>Status</th>
        </tr></thead>
        <tbody id="foBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
      <small class="text-muted" id="foInfo"></small>
      <div class="btn-group btn-group-sm" id="foPages"></div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/food_orders.js'); ?>
