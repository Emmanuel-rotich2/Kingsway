<?php
/**
 * Daily Usage — food that left the store: issued to the kitchen (by meal)
 * and thrown away, per item and per day, with its cost. Feeds the catering
 * consumption review.
 * Data: /api/catering/store-ledger (issue, spoil).
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--store container-fluid py-3" id="usagePage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Daily usage</h1>
      <p class="ops-lede">Food issued to the kitchen and food thrown away.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <button class="btn btn-ops" type="button" id="usIssue" data-fs-manage hidden><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i>Issue to the kitchen</button>
      <button class="btn btn-outline-secondary" type="button" id="usCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="usPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>
  <div class="ops-panel mb-3">
    <div class="d-flex flex-wrap gap-3 align-items-end">
      <div><label class="form-label small" for="fcFrom">From</label><input class="form-control" type="date" id="fcFrom"></div>
      <div><label class="form-label small" for="fcTo">To</label><input class="form-control" type="date" id="fcTo"></div>
      <ul class="ops-facts ms-md-3" id="usFacts" aria-live="polite"></ul>
    </div>
  </div>

  <div class="ops-tabs d-print-none" role="tablist" id="usTabs">
    <button type="button" role="tab" id="usTabItems" aria-controls="usItems" aria-selected="true">By item</button>
    <button type="button" role="tab" id="usTabDays" aria-controls="usDays" aria-selected="false" tabindex="-1">Day by day</button>
  </div>
  <section class="ops-panel mb-3" id="usItems" role="tabpanel" aria-labelledby="usTabItems">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Usage by item">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Item</th><th scope="col">Issued</th><th scope="col">A day, on average</th><th scope="col">Thrown away</th><th scope="col">Cost</th></tr></thead>
        <tbody id="usItemBody"></tbody>
      </table>
    </div>
  </section>
  <section class="ops-panel mb-3" id="usDays" role="tabpanel" aria-labelledby="usTabDays" hidden>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Usage day by day">
      <table class="table align-middle mb-0">
        <thead><tr><th scope="col">Item</th><th scope="col">Quantity</th><th scope="col">For</th><th scope="col">Taken by or reason</th><th scope="col">Cost</th></tr></thead>
        <tbody id="usDayBody"></tbody>
      </table>
    </div>
  </section>

  <section class="ops-panel d-print-none" aria-labelledby="aiCatTitle">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div><h2 class="mb-1" id="aiCatTitle">Usage review</h2><p class="small text-muted mb-0">The assistant compares usage and waste with the menu for these dates. It does not decide on nutrition, food safety, menus or purchasing.</p></div>
      <button type="button" class="btn btn-outline-secondary" id="queueAiCateringReview"><i class="bi bi-stars me-1" aria-hidden="true"></i>Review these dates</button>
    </div>
    <div id="aiCateringReviews" class="row g-2 mt-2"></div>
  </section>
  <?php include __DIR__ . '/../components/modals/food_store_forms.php'; ?>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_store_common.js'); ?>
<?php asset_script($appBase, 'js/pages/food_consumption.js'); ?>
<?php asset_script($appBase, 'js/pages/ai_catering_review.js'); ?>
