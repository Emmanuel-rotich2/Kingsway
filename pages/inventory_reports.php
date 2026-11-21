<?php
/* Inventory Reports — stock levels, requisition status, adjustment logs. */

?>

<div class="container-fluid px-4 py-4">

  <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-clipboard-data text-success me-2"></i>Inventory Reports</h4>
      <p class="text-muted small mb-0 mt-1">Stock levels, requisition status and stock adjustment logs.</p>
    </div>
    <button class="btn btn-outline-success btn-sm" data-kw-onclick="inventoryReportsController.load()"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="bg-white border rounded-3 overflow-hidden">
        <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Stock Levels</div>
        <div class="table-responsive" style="max-height: 520px;">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Item</th><th>Category</th><th>Qty</th><th>Unit</th></tr></thead>
            <tbody id="irStockBody"><tr><td colspan="4" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-7">
      <div class="row g-3 mb-3">
        <div class="col-12">
          <div class="bg-white border rounded-3 overflow-hidden">
            <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Requisitions by Status</div>
            <div class="p-3" id="irReqSummary">Loading…</div>
          </div>
        </div>
        <div class="col-12">
          <div class="bg-white border rounded-3 overflow-hidden">
            <div class="p-3 border-bottom fw-semibold small text-uppercase text-muted">Stock Adjustment Logs</div>
            <div class="table-responsive" style="max-height: 300px;">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Item</th><th>Qty</th><th>Unit Cost</th><th>Notes</th></tr></thead>
                <tbody id="irAdjBody"><tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Loading…</td></tr></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php asset_script($appBase, 'js/pages/inventory_reports.js'); ?>
