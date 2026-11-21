<?php
/* Payment Records — read-only ledger of recorded fee payments. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-receipt text-success me-2"></i>Payment Records</h4>
      <p class="text-muted small mb-0 mt-1">All fee payments recorded in the system.</p>
    </div>
  </div>

  <div class="d-flex gap-2 mb-3 flex-wrap align-items-end">
    <div><label class="form-label small fw-semibold">From</label><input type="date" class="form-control form-control-sm" id="prFrom"></div>
    <div><label class="form-label small fw-semibold">To</label><input type="date" class="form-control form-control-sm" id="prTo"></div>
    <div><label class="form-label small fw-semibold">Method</label><select class="form-select form-select-sm" id="prMethod"><option value="">All methods</option><option>M-Pesa</option><option>Cash</option><option>Bank Transfer</option><option>Cheque</option><option>Other</option></select></div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="paymentRecordsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="bg-white border rounded-3 overflow-hidden">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr>
          <th>Date</th><th>Receipt</th><th>Adm No</th><th>Student</th><th>Amount (KES)</th><th>Method</th><th>Status</th><th>Reference</th>
        </tr></thead>
        <tbody id="prBody"><tr><td colspan="8" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>
    <div class="p-3 border-top d-flex justify-content-between align-items-center">
      <small class="text-muted" id="prInfo"></small>
      <div class="btn-group btn-group-sm" id="prPages"></div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/payment_records.js'); ?>
