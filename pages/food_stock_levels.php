<?php
/**
 * Stock Count — the count sheet. For each food item, the book quantity and
 * a box for what was found on the shelf; the difference shows as you type.
 * Saving writes each difference to the bin card as a correction. Print a
 * blank sheet to count on paper first.
 * Data: /api/catering/store, POST /api/catering/store-count.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--store container-fluid py-3" id="stockCountPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Stock count</h1>
      <p class="ops-lede">Count what is on the shelves and enter it. Leave an item empty if you did not count it.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-outline-secondary" type="button" id="scBlank"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print a blank sheet</button>
      <button class="btn btn-outline-secondary" type="button" id="scPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
      <button class="btn btn-outline-secondary" type="button" id="scCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
    </div>
  </div>

  <div class="ops-panel mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-end">
      <div><label class="form-label small" for="scDate">Counted on</label><input class="form-control" type="date" id="scDate"></div>
      <div><label class="form-label small" for="scCat">Category</label><select class="form-select" id="scCat"><option value="">All food</option></select></div>
      <ul class="ops-facts ms-md-3" id="scFacts" aria-live="polite"></ul>
      <button class="btn btn-ops ms-auto d-print-none" type="button" id="scSave" data-fs-manage hidden disabled>Save the count</button>
    </div>
    <p class="small text-muted mt-2 mb-0" id="scNote"></p>
  </div>

  <div class="ops-panel">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Count sheet">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Item</th><th scope="col">Book says</th><th scope="col">Found</th><th scope="col">Difference</th><th scope="col" class="d-none d-md-table-cell">Note</th><th scope="col" class="d-none d-lg-table-cell">Last counted</th></tr></thead>
        <tbody id="scBody"><tr><td colspan="6" class="text-muted p-3">Loading the store…</td></tr></tbody>
      </table>
    </div>
  </div>
  <?php include __DIR__ . '/../components/modals/food_store_forms.php'; ?>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_store_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_stock_levels.js'); ?>
