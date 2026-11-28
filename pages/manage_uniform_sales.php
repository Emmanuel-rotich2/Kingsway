<?php
/**
 * Uniform Stock — what the uniform store holds, garment by garment, as a
 * size run: one tile per size with the count on the shelf, what is held for
 * paid orders, and whether it needs restocking. Stock comes in only through
 * Stock Intake (which prints the unit labels) and goes out only through the
 * till or an order, so this page reads and links; it does not change stock.
 * Data: /api/inventory/uniform-store-stock (uniform_sizes, catalog units).
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--shop container-fluid py-3" id="uniformStockPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Uniform stock</h1>
      <p class="ops-lede">Every garment and size on the shelf. Restock through Stock Intake so each piece gets its label.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none" id="usLinks">
      <a class="btn btn-ops" href="home.php?route=uniform_stock_intake" data-store-only>Stock intake</a>
      <a class="btn btn-outline-secondary" href="home.php?route=uniform_point_of_sale" data-store-only>Point of sale</a>
      <button class="btn btn-outline-secondary" type="button" id="usCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="usPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div class="ops-panel mb-3"><ul class="ops-facts" id="usFacts" aria-live="polite"></ul></div>

  <div class="ops-panel">
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3 d-print-none">
      <div class="flex-grow-1" style="max-inline-size:20rem"><label class="form-label small" for="usSearch">Find</label><input class="form-control" type="search" id="usSearch" placeholder="Garment, colour or code"></div>
      <div><label class="form-label small" for="usCat">Collection</label><select class="form-select" id="usCat"><option value="">All</option></select></div>
      <div class="ops-chips" role="group" aria-label="Show" id="usShow">
        <button type="button" class="btn btn-outline-secondary" data-show="" aria-pressed="true">Everything</button>
        <button type="button" class="btn btn-outline-secondary" data-show="restock" aria-pressed="false">Needs restocking</button>
        <button type="button" class="btn btn-outline-secondary" data-show="unlabelled" aria-pressed="false">Not labelled</button>
      </div>
    </div>
    <div id="usList" aria-live="polite"><p class="ops-empty">Loading the stock…</p></div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/manage_uniform_sales.js'); ?>
