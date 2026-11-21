<?php
/* Uniform Sales Records — read-only list of uniform item sales. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-person-badge text-primary me-2"></i>Uniform Sales Records</h4>
      <p class="text-muted small mb-0 mt-1">Recorded uniform item sales and payment status.</p>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3 flex-wrap align-items-end">
    <div><label class="form-label small fw-semibold">Payment Status</label><select class="form-select form-select-sm" id="usrStatus"><option value="">All statuses</option><option>paid</option><option>pending</option><option>partially_paid</option></select></div>
    <div><label class="form-label small fw-semibold">From</label><input type="date" class="form-control form-control-sm" id="usrFrom"></div>
    <div><label class="form-label small fw-semibold">To</label><input type="date" class="form-control form-control-sm" id="usrTo"></div>
    <button class="btn btn-outline-primary btn-sm" data-kw-onclick="uniformSalesRecordsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Sale Date</th><th>Adm No</th><th>Student</th><th>Item</th><th>Size</th><th>Qty</th><th>Amount (KES)</th><th>Payment</th><th>Sold By</th>
        </tr></thead>
        <tbody id="usrBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
      <small class="text-muted" id="usrInfo"></small>
      <div class="btn-group btn-group-sm" id="usrPages"></div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/uniform_sales_records.js'); ?>
