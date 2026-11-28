<?php
/**
 * Today's Menu — the kitchen's day: the menu board for the chosen day, and
 * the kitchen record of each dish (servings planned, cooked and served).
 * Data: /api/catering/plans, POST /api/catering/plans-record/{id}.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--kitchen container-fluid py-3" id="todaysMenuPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Today's menu</h1>
      <p class="ops-lede">What the kitchen is cooking today, and how many were served.</p>
    </div>
    <div class="d-flex flex-wrap align-items-end gap-2 d-print-none">
      <div><label class="form-label small" for="tmDay">Day</label><input class="form-control" type="date" id="tmDay"></div>
      <a class="btn btn-outline-secondary" href="home.php?route=menu_planning"><i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Plan the week</a>
      <button class="btn btn-outline-secondary" type="button" id="tmCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="tmPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-5">
      <section class="ops-board" aria-labelledby="tmBoardTitle">
        <h2 id="tmBoardTitle">Menu</h2>
        <ol class="ops-line" id="tmBoard"></ol>
      </section>
    </div>
    <div class="col-lg-7">
      <section class="ops-panel" aria-labelledby="tmRecTitle">
        <h2 id="tmRecTitle">Kitchen record</h2>
        <ul class="ops-facts mb-3" id="tmFacts" aria-live="polite"></ul>
        <div class="table-responsive" tabindex="0" role="region" aria-label="Kitchen record">
          <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Meal</th><th scope="col">Dish</th><th scope="col">Planned</th><th scope="col">Cooked</th><th scope="col">Served</th><th scope="col">Status</th><th scope="col" class="d-print-none"><span class="visually-hidden">Record</span></th></tr></thead>
            <tbody id="tmBody"></tbody>
          </table>
        </div>
      </section>
    </div>
  </div>

  <div class="modal fade" id="tmRecModal" tabindex="-1" aria-labelledby="tmRecModalTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="tmRecForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="tmRecModalTitle">Record</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p class="fw-semibold mb-2" id="tmRecWhat"></p>
        <fieldset class="mb-2"><legend class="form-label small">What happened</legend>
          <div class="btn-group w-100" role="radiogroup">
            <input type="radio" class="btn-check" name="tmRecStatus" id="tmRsPrepared" value="prepared"><label class="btn btn-outline-secondary" for="tmRsPrepared">Cooked</label>
            <input type="radio" class="btn-check" name="tmRecStatus" id="tmRsServed" value="served"><label class="btn btn-outline-secondary" for="tmRsServed">Served</label>
            <input type="radio" class="btn-check" name="tmRecStatus" id="tmRsCancelled" value="cancelled"><label class="btn btn-outline-secondary" for="tmRsCancelled">Not cooked</label>
          </div>
        </fieldset>
        <div class="row g-2">
          <div class="col-6"><label class="form-label small" for="tmRecPrepared">Servings cooked</label><input class="form-control" type="number" min="0" inputmode="numeric" id="tmRecPrepared"></div>
          <div class="col-6"><label class="form-label small" for="tmRecServed">Servings served</label><input class="form-control" type="number" min="0" inputmode="numeric" id="tmRecServed"></div>
        </div>
        <label class="form-label small mt-2" for="tmRecNotes">Notes (left over, shortages, changes)</label>
        <textarea class="form-control" id="tmRecNotes" rows="2" maxlength="500"></textarea>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Save</button></div>
    </form></div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/kitchen_common.js'); ?>
<?php asset_script($appBase, 'js/pages/todays_menu.js'); ?>
