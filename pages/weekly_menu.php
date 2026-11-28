<?php
/**
 * Weekly Menu — the week's menu boards, one per day (boarders eat at
 * weekends too). Read-only and made for printing and pinning up in the
 * dining hall. Data: /api/catering/plans.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--kitchen container-fluid py-3" id="weeklyMenuPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title" id="wmTitle">Weekly menu</h1>
      <p class="ops-lede">The week's meals, day by day. Print it for the dining hall notice board.</p>
    </div>
    <div class="d-flex flex-wrap align-items-end gap-2 d-print-none">
      <div class="btn-group" role="group" aria-label="Change week">
        <button class="btn btn-outline-secondary" type="button" id="wmPrev" aria-label="Previous week"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button class="btn btn-outline-secondary" type="button" id="wmThis">This week</button>
        <button class="btn btn-outline-secondary" type="button" id="wmNext" aria-label="Next week"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
      </div>
      <div class="form-check form-switch mb-2 ms-2"><input class="form-check-input" type="checkbox" role="switch" id="wmWeekend" checked><label class="form-check-label" for="wmWeekend">Include the weekend</label></div>
      <button class="btn btn-outline-secondary" type="button" id="wmCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="wmPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>
  <div class="ops-board-week" id="wmDays" aria-live="polite"></div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/kitchen_common.js'); ?>
<?php asset_script($appBase, 'js/pages/weekly_menu.js'); ?>
