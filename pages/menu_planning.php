<?php
/**
 * Menu Planning — the kitchen's week: which dishes are cooked at each meal,
 * for how many, and the list of dishes the kitchen makes. manage_menus opens
 * the same page on the Dishes tab.
 * Data: /api/catering/plans, /api/catering/dishes (KitchenService).
 * Planning: Cateress, Director, School Administrator.
 */
?>
<link rel="stylesheet" href="<?= htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8') ?>/css/pages/support_ops.css?v=<?= asset_version('css/pages/support_ops.css') ?>">
<div class="kw-ops kw-ops--kitchen container-fluid py-3" id="menuPlanPage">
  <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
      <h1 class="ops-title">Menu planning</h1>
      <p class="ops-lede">Plan each meal of the week from the kitchen's dishes, with how many servings to cook.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 d-print-none">
      <a class="btn btn-outline-secondary" href="home.php?route=todays_menu"><i class="bi bi-calendar2-day me-1" aria-hidden="true"></i>Today's menu</a>
      <button class="btn btn-outline-secondary" type="button" id="mpCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
      <button class="btn btn-outline-secondary" type="button" id="mpPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
    </div>
  </div>

  <div class="ops-tabs d-print-none" role="tablist" aria-label="Menu planning" id="mpTabs">
    <button type="button" role="tab" id="mpTabWeek" aria-controls="mpWeekPanel" aria-selected="true">Week plan</button>
    <button type="button" role="tab" id="mpTabDishes" aria-controls="mpDishPanel" aria-selected="false" tabindex="-1">Dishes</button>
  </div>

  <div role="tabpanel" id="mpWeekPanel" aria-labelledby="mpTabWeek">
    <div class="d-flex flex-wrap align-items-end gap-2 mb-3 d-print-none">
      <div class="btn-group" role="group" aria-label="Change week">
        <button class="btn btn-outline-secondary" type="button" id="mpPrev" aria-label="Previous week"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button class="btn btn-outline-secondary" type="button" id="mpThis">This week</button>
        <button class="btn btn-outline-secondary" type="button" id="mpNext" aria-label="Next week"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
      </div>
      <div><label class="form-label small" for="mpWeek">Week of</label><input class="form-control" type="date" id="mpWeek"></div>
      <button class="btn btn-outline-secondary" type="button" id="mpCopy" hidden><i class="bi bi-files me-1" aria-hidden="true"></i>Copy last week into empty meals</button>
    </div>
    <h2 class="h5 mb-2" id="mpRange" aria-live="polite"></h2>
    <div class="ops-week" id="mpGrid" aria-describedby="mpRange"></div>
    <p class="small text-muted mt-2 mb-0" id="mpHint"></p>
  </div>

  <div role="tabpanel" id="mpDishPanel" aria-labelledby="mpTabDishes" hidden>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 d-print-none">
      <div><label class="form-label small" for="mpDishQ">Find a dish</label><input class="form-control" type="search" id="mpDishQ"></div>
      <button class="btn btn-ops" type="button" id="mpDishAdd" hidden><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add a dish</button>
    </div>
    <div class="ops-panel p-0">
      <div class="table-responsive" tabindex="0" role="region" aria-label="Dishes">
        <table class="table align-middle mb-0">
          <thead><tr><th scope="col">Dish</th><th scope="col">Usually served at</th><th scope="col">Notes</th><th scope="col">In use</th><th scope="col" class="d-print-none"><span class="visually-hidden">Actions</span></th></tr></thead>
          <tbody id="mpDishBody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add or change a dish on a meal -->
  <div class="modal fade" id="mpPlanModal" tabindex="-1" aria-labelledby="mpPlanTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="mpPlanForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="mpPlanTitle">Plan a dish</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <p class="fw-semibold mb-2" id="mpPlanWhen"></p>
        <label class="form-label small" for="mpPlanDish">Dish</label>
        <select class="form-select mb-1" id="mpPlanDish" required></select>
        <button type="button" class="btn btn-link btn-sm px-0 mb-2" id="mpPlanNewDish">The dish is not listed: add it</button>
        <label class="form-label small" for="mpPlanServings">Servings to cook</label>
        <input class="form-control mb-2" type="number" min="0" max="5000" inputmode="numeric" id="mpPlanServings" required>
        <label class="form-label small" for="mpPlanNotes">Notes for the kitchen</label>
        <textarea class="form-control" id="mpPlanNotes" rows="2" maxlength="500"></textarea>
      </div>
      <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-outline-danger" id="mpPlanRemove" hidden>Remove from this meal</button>
        <span class="d-flex gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Save</button></span>
      </div>
    </form></div>
  </div>

  <!-- Add or edit a dish -->
  <div class="modal fade" id="mpDishModal" tabindex="-1" aria-labelledby="mpDishTitle" aria-hidden="true">
    <div class="modal-dialog"><form class="modal-content" id="mpDishForm" novalidate>
      <div class="modal-header"><h2 class="modal-title h5" id="mpDishTitle">Add a dish</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
      <div class="modal-body">
        <input type="hidden" id="mpDishId">
        <label class="form-label small" for="mpDishName">Name</label>
        <input class="form-control mb-2" id="mpDishName" maxlength="255" required placeholder="For example: Githeri with cabbage">
        <label class="form-label small" for="mpDishMeal">Usually served at</label>
        <select class="form-select mb-2" id="mpDishMeal"><option value="breakfast">Breakfast</option><option value="snack">Tea break</option><option value="lunch" selected>Lunch</option><option value="dinner">Supper</option></select>
        <label class="form-label small" for="mpDishDesc">Notes (ingredients, allergies)</label>
        <textarea class="form-control mb-2" id="mpDishDesc" rows="2" maxlength="1000"></textarea>
        <div class="form-check"><input class="form-check-input" type="checkbox" id="mpDishActive" checked><label class="form-check-label" for="mpDishActive">Still cooked (shown when planning)</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Save dish</button></div>
    </form></div>
  </div>
</div>
<?php asset_script($appBase, 'js/pages/support_ops_common.js'); ?>
<?php asset_script($appBase, 'js/pages/kitchen_common.js'); ?>
<?php asset_script($appBase, 'js/pages/menu_planning.js'); ?>
