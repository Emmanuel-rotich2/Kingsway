<?php
/**
 * Uniform Sales — every sale the uniform store made: counter sales at the
 * till, staff orders and parent orders, with what was bought, for which
 * learner, how it was paid and who served. A second view totals each
 * garment and size. Cancelled orders are listed but not counted.
 * Data: /api/inventory/uniform-store-sales (catalog_orders and their lines).
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--shop container-fluid py-3" id="uniformSalesPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Uniform sales</h1>
      <p class="ops-lede">Counter sales, staff orders and parent orders.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-outline-secondary" type="button" id="srCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="srPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div class="ops-panel mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-end d-print-none">
      <div><label class="form-label small" for="srFrom">From</label><input class="form-control" type="date" id="srFrom"></div>
      <div><label class="form-label small" for="srTo">To</label><input class="form-control" type="date" id="srTo"></div>
      <div><label class="form-label small" for="srSource">Sold through</label><select class="form-select" id="srSource"><option value="">Everywhere</option></select></div>
      <div><label class="form-label small" for="srPay">Payment</label><select class="form-select" id="srPay"><option value="">Any</option><option value="paid">Paid</option><option value="unpaid">Not paid yet</option></select></div>
      <div class="flex-grow-1" style="max-inline-size:18rem"><label class="form-label small" for="srSearch">Find</label><input class="form-control" type="search" id="srSearch" placeholder="Reference, buyer or learner"></div>
    </div>
    <ul class="ops-facts mt-3" id="srFacts" aria-live="polite"></ul>
  </div>

  <div class="ops-tabs d-print-none" role="tablist" id="srTabs">
    <button type="button" role="tab" id="srTabSales" aria-controls="srSales" aria-selected="true">Sales</button>
    <button type="button" role="tab" id="srTabItems" aria-controls="srItems" aria-selected="false" tabindex="-1">By garment and size</button>
  </div>
  <section class="ops-panel" id="srSales" role="tabpanel" aria-labelledby="srTabSales">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Sales">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">When</th><th scope="col">Sale</th><th scope="col">What was bought</th><th scope="col">Buyer</th><th scope="col">Total</th><th scope="col">Payment</th></tr></thead>
        <tbody id="srBody"><tr><td colspan="6" class="text-muted p-3">Loading sales…</td></tr></tbody>
      </table>
    </div>
  </section>
  <section class="ops-panel" id="srItems" role="tabpanel" aria-labelledby="srTabItems" hidden>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Sales by garment and size">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Garment</th><th scope="col">Size</th><th scope="col">Pieces sold</th><th scope="col">Takings</th></tr></thead>
        <tbody id="srItemBody"></tbody>
      </table>
    </div>
  </section>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/uniform_sales_records.js'); ?>
