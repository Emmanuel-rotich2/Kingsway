<?php
/* Transport Payments — track transport bill payments, print receipts. */
$appBase = rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($appBase === '.') $appBase = '';
?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-bus-front text-success me-2"></i>Transport Payments</h4>
      <p class="text-muted small mb-0 mt-1">Track transport bill payments and print receipts. Transport fees are set by the Director.</p>
    </div>
  </div>

  <div class="bg-white border rounded-3 p-3">
    <div class="d-flex gap-2 mb-3 flex-wrap">
      <select id="mtfMonth" class="form-select form-select-sm" style="width:170px">
        <option value="">All months</option>
      </select>
      <select id="mtfRoute" class="form-select form-select-sm" style="width:190px">
        <option value="">All routes</option>
      </select>
      <select id="mtfStatus" class="form-select form-select-sm" style="width:150px">
        <option value="">All statuses</option>
        <option value="paid">Paid</option>
        <option value="partial">Partial</option>
        <option value="pending">Pending</option>
      </select>
      <button class="btn btn-outline-success btn-sm" onclick="manageTransportFeeStructureController.loadBills()"><i class="bi bi-arrow-clockwise"></i></button>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-primary">
          <div class="card-body text-center">
            <h6 class="text-muted mb-2"><i class="bi bi-receipt"></i> Total Bills</h6>
            <h3 class="text-primary mb-0" id="mtfTotalBills">0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-success">
          <div class="card-body text-center">
            <h6 class="text-muted mb-2"><i class="bi bi-cash-check"></i> Total Collected</h6>
            <h3 class="text-success mb-0" id="mtfTotalCollected">KES 0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-warning">
          <div class="card-body text-center">
            <h6 class="text-muted mb-2"><i class="bi bi-clock-history"></i> Outstanding</h6>
            <h3 class="text-warning mb-0" id="mtfOutstanding">KES 0</h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-info">
          <div class="card-body text-center">
            <h6 class="text-muted mb-2"><i class="bi bi-check-circle"></i> Paid</h6>
            <h3 class="text-info mb-0" id="mtfPaidCount">0</h3>
          </div>
        </div>
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light"><tr>
          <th>Student</th><th>Admission No</th><th>Route</th><th>Month</th>
          <th class="text-end">Amount Due</th><th class="text-end">Amount Paid</th>
          <th class="text-end">Balance</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody id="mtfBody"><tr><td colspan="9" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
      </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3">
      <span class="text-muted" id="mtfPagInfo">Showing 0 of 0</span>
      <div id="mtfPagControls"></div>
    </div>
  </div>

</div>

<!-- Receipt Modal -->
<div class="modal fade" id="mtfReceiptModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title fw-bold">Transport Payment Receipt</h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body" id="mtfReceiptBody"></div>
    <div class="modal-footer">
      <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      <button class="btn btn-success btn-sm" onclick="manageTransportFeeStructureController.printReceipt()"><i class="bi bi-printer me-1"></i>Print Receipt</button>
    </div>
  </div></div>
</div>

<?php asset_script($appBase, 'js/pages/manage_transport_fee_structure.js'); ?>
