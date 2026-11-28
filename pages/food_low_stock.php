<?php
/**
 * Reorder List — what to buy. Every food item that is out, at or below its
 * reorder level, or that will run out within a week at the usual rate, with
 * a suggested quantity (enough for three weeks, at least twice the reorder
 * level), grouped by supplier so it prints as a shopping list.
 * Data: /api/catering/store.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--store container-fluid py-3" id="reorderPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Reorder list</h1>
      <p class="ops-lede">Food that is out, running low, or will run out within a week. The quantity to order covers about three weeks.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <a class="btn btn-outline-secondary" href="home.php?route=purchase_orders"><i class="bi bi-receipt me-1" aria-hidden="true"></i>Purchase orders</a>
      <button class="btn btn-outline-secondary" type="button" id="roCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="roPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print the list</button>
    </div>
  </div>
  <div class="ops-panel mb-3"><ul class="ops-facts" id="roFacts" aria-live="polite"></ul></div>
  <div id="roGroups"><div class="ops-panel text-muted">Loading the store…</div></div>
  <?php include __DIR__ . '/../components/modals/food_store_forms.php'; ?>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_store_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_low_stock.js'); ?>
