<?php
/**
 * Food Store — what the store holds. Each food item with its level against
 * the reorder mark, how long it lasts at the usual rate, its use-by date and
 * value. The storekeeper receives deliveries, issues food to the kitchen,
 * writes off spoilt food and opens each item's bin card from here.
 * Data: /api/catering/store (+ store-item, store-receive, store-issue,
 * store-spoil, store-ledger). Food categories only.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--store container-fluid py-3" id="foodStorePage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Food store</h1>
      <p class="ops-lede">What the store holds today. Open an item to see its bin card.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-ops" type="button" id="fsIssueBtn" data-fs-manage hidden><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Issue to the kitchen</button>
      <button class="btn btn-outline-secondary" type="button" id="fsReceiveBtn" data-fs-manage hidden><i class="bi bi-truck me-1" aria-hidden="true"></i>Receive a delivery</button>
      <button class="btn btn-outline-secondary" type="button" id="fsSpoilBtn" data-fs-manage hidden>Food thrown away</button>
      <button class="btn btn-outline-secondary" type="button" id="fsAddBtn" data-fs-manage hidden><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add an item</button>
    </div>
  </div>

  <div class="ops-panel mb-3">
    <ul class="ops-facts" id="fsFacts" aria-live="polite"></ul>
  </div>

  <div class="ops-panel">
    <div class="d-flex flex-wrap gap-2 align-items-end mb-3 d-print-none">
      <div class="flex-grow-1" style="max-inline-size:20rem"><label class="form-label small" for="fsSearch">Find</label><input class="form-control" type="search" id="fsSearch" placeholder="Item or code"></div>
      <div><label class="form-label small" for="fsCat">Category</label><select class="form-select" id="fsCat"><option value="">All food</option></select></div>
      <div class="ops-chips" role="group" aria-label="Show" id="fsShow">
        <button type="button" class="btn btn-outline-secondary" data-show="" aria-pressed="true">Everything</button>
        <button type="button" class="btn btn-outline-secondary" data-show="low" aria-pressed="false">Order more</button>
        <button type="button" class="btn btn-outline-secondary" data-show="expiry" aria-pressed="false">Use soon</button>
        <button type="button" class="btn btn-outline-secondary" data-show="inactive" aria-pressed="false">No longer kept</button>
      </div>
      <div class="ms-auto d-flex gap-2">
        <button class="btn btn-outline-secondary" type="button" id="fsCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
        <button class="btn btn-outline-secondary" type="button" id="fsPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
      </div>
    </div>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Food items">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Item</th><th scope="col">In the store</th><th scope="col">Lasts</th><th scope="col">Use by</th><th scope="col">Value</th><th scope="col" class="d-print-none"><span class="visually-hidden">Actions</span></th></tr></thead>
        <tbody id="fsBody"><tr><td colspan="6" class="text-muted p-3">Loading the store…</td></tr></tbody>
      </table>
    </div>
  </div>
  <?php include __DIR__ . '/../components/modals/food_store_forms.php'; ?>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_store_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_store.js'); ?>
