<?php
/**
 * Deliveries — the food that came into the store: each delivery with its
 * supplier, delivery note, items, cost and use-by dates. Record a new one
 * from here. Purchase orders stay on the Purchase Orders page.
 * Data: /api/catering/store-ledger?movement=receive, POST store-receive.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--store container-fluid py-3" id="deliveriesPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Deliveries</h1>
      <p class="ops-lede">Food received into the store, by delivery note.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-ops" type="button" id="dlNew" data-fs-manage hidden><i class="bi bi-truck me-1" aria-hidden="true"></i>Receive a delivery</button>
      <a class="btn btn-outline-secondary" href="home.php?route=purchase_orders"><i class="bi bi-receipt me-1" aria-hidden="true"></i>Purchase orders</a>
    </div>
  </div>
  <div class="ops-panel mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-end">
      <div><label class="form-label small" for="dlFrom">From</label><input class="form-control" type="date" id="dlFrom"></div>
      <div><label class="form-label small" for="dlTo">To</label><input class="form-control" type="date" id="dlTo"></div>
      <div><label class="form-label small" for="dlSup">Supplier</label><select class="form-select" id="dlSup"><option value="">All suppliers</option></select></div>
      <ul class="ops-facts ms-md-3" id="dlFacts" aria-live="polite"></ul>
      <div class="ms-auto d-flex gap-2 d-print-none">
        <button class="btn btn-outline-secondary" type="button" id="dlCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
        <button class="btn btn-outline-secondary" type="button" id="dlPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
      </div>
    </div>
  </div>
  <div class="ops-panel">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Deliveries">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Item</th><th scope="col">Quantity</th><th scope="col">Cost of one</th><th scope="col">Value</th><th scope="col">Use by</th></tr></thead>
        <tbody id="dlBody"><tr><td colspan="5" class="text-muted p-3">Loading deliveries…</td></tr></tbody>
      </table>
    </div>
  </div>
  <?php include __DIR__ . '/../components/modals/food_store_forms.php'; ?>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_store_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_orders.js'); ?>
