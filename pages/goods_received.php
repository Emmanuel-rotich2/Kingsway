<?php
/* Goods Received — track purchase orders awaiting/confirming delivery. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-box-seam text-warning me-2"></i>Goods Received</h4>
      <p class="text-muted small mb-0 mt-1">Purchase orders and their delivery status.</p>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3 flex-wrap align-items-end">
    <div><label class="form-label small fw-semibold">Status</label><select class="form-select form-select-sm" id="grStatus">
      <option value="">All statuses</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="ordered">Ordered</option>
      <option value="received">Received</option>
      <option value="cancelled">Cancelled</option>
    </select></div>
    <button class="btn btn-outline-warning btn-sm" data-kw-onclick="goodsReceivedController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Order No</th><th>Supplier</th><th>Order Date</th><th>Expected Delivery</th><th>Items</th><th>Total (KES)</th><th>Status</th><th></th>
        </tr></thead>
        <tbody id="grBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
      <small class="text-muted" id="grInfo"></small>
      <div class="btn-group btn-group-sm" id="grPages"></div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/goods_received.js'); ?>
