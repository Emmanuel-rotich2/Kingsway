<?php
/**
 * Food store forms shared by the food store pages (food_store, food_orders,
 * food_low_stock, food_stock_levels, food_consumption): add or edit an item,
 * receive a delivery, issue food to the kitchen, write off spoilt food, and
 * an item's bin card. Wired by js/pages/food_store_common.js.
 */
?>
<div class="modal fade" id="fsItemModal" tabindex="-1" aria-labelledby="fsItemTitle" aria-hidden="true">
  <div class="modal-dialog"><form class="modal-content" id="fsItemForm" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="fsItemTitle">Add a food item</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <input type="hidden" id="fsItemId">
      <div class="row g-2">
        <div class="col-12"><label class="form-label small" for="fsItemName">Item</label><input class="form-control" id="fsItemName" maxlength="255" required placeholder="For example, Maize flour (2 kg)"></div>
        <div class="col-7"><label class="form-label small" for="fsItemCat">Category</label><select class="form-select" id="fsItemCat" required></select></div>
        <div class="col-5"><label class="form-label small" for="fsItemUnit">Counted in</label><input class="form-control" id="fsItemUnit" maxlength="20" required placeholder="bags, kg, crates" list="fsUnitList"></div>
        <div class="col-6"><label class="form-label small" for="fsItemReorder">Order more at</label><input class="form-control" type="number" min="0" inputmode="numeric" id="fsItemReorder"></div>
        <div class="col-6"><label class="form-label small" for="fsItemCost">Cost of one (KES)</label><input class="form-control" type="number" min="0" step="0.01" inputmode="decimal" id="fsItemCost"></div>
        <div class="col-6"><label class="form-label small" for="fsItemLoc">Kept in</label><select class="form-select" id="fsItemLoc"></select></div>
        <div class="col-6"><label class="form-label small" for="fsItemSup">Usual supplier</label><select class="form-select" id="fsItemSup"></select></div>
        <div class="col-6" id="fsItemOpeningWrap"><label class="form-label small" for="fsItemOpening">In the store now</label><input class="form-control" type="number" min="0" inputmode="numeric" id="fsItemOpening"></div>
        <div class="col-12" id="fsItemActiveWrap"><div class="form-check mt-1"><input class="form-check-input" type="checkbox" id="fsItemActive" checked><label class="form-check-label" for="fsItemActive">The store still keeps this item</label></div></div>
      </div>
      <p class="small text-muted mt-2 mb-0" id="fsItemQtyNote" hidden>To change the quantity, record a delivery, an issue, food thrown away or a stock count. That keeps the bin card right.</p>
      <datalist id="fsUnitList"><option value="bags"><option value="kg"><option value="crates"><option value="cartons"><option value="litres"><option value="jerrycans"><option value="trays"><option value="packets"><option value="tins"></datalist>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Save item</button></div>
  </form></div>
</div>

<div class="modal fade" id="fsReceiveModal" tabindex="-1" aria-labelledby="fsReceiveTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg"><form class="modal-content" id="fsReceiveForm" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="fsReceiveTitle">Receive a delivery</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-3"><label class="form-label small" for="fsRcDate">Delivered on</label><input class="form-control" type="date" id="fsRcDate"></div>
        <div class="col-6 col-md-5"><label class="form-label small" for="fsRcSup">Supplier</label><select class="form-select" id="fsRcSup"></select></div>
        <div class="col-12 col-md-4"><label class="form-label small" for="fsRcDoc">Delivery note no.</label><input class="form-control" id="fsRcDoc" maxlength="60"></div>
      </div>
      <div class="ops-lines ops-lines--receive" id="fsRcLines"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-add-line="fsRcLines"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add another item</button>
      <label class="form-label small mt-3" for="fsRcNotes">Notes (short or damaged goods, for example)</label>
      <textarea class="form-control" id="fsRcNotes" rows="2" maxlength="500"></textarea>
    </div>
    <div class="modal-footer"><span class="me-auto small text-muted" id="fsRcTotal"></span><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Add to the store</button></div>
  </form></div>
</div>

<div class="modal fade" id="fsIssueModal" tabindex="-1" aria-labelledby="fsIssueTitle" aria-hidden="true">
  <div class="modal-dialog"><form class="modal-content" id="fsIssueForm" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="fsIssueTitle">Issue food to the kitchen</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3">
        <div class="col-6"><label class="form-label small" for="fsIsDate">Date</label><input class="form-control" type="date" id="fsIsDate"></div>
        <div class="col-6"><label class="form-label small" for="fsIsMeal">For</label><select class="form-select" id="fsIsMeal"><option value="">The whole day</option><option value="breakfast">Breakfast</option><option value="snack">Snack</option><option value="lunch">Lunch</option><option value="dinner">Supper</option></select></div>
        <div class="col-7"><label class="form-label small" for="fsIsTo">Taken by</label><input class="form-control" id="fsIsTo" maxlength="120" required placeholder="Name of the cook"></div>
        <div class="col-5"><label class="form-label small" for="fsIsDoc">Voucher no.</label><input class="form-control" id="fsIsDoc" maxlength="60"></div>
      </div>
      <div class="ops-lines" id="fsIsLines"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-add-line="fsIsLines"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add another item</button>
      <label class="form-label small mt-3" for="fsIsNotes">Notes</label>
      <textarea class="form-control" id="fsIsNotes" rows="2" maxlength="500"></textarea>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-ops">Issue</button></div>
  </form></div>
</div>

<div class="modal fade" id="fsSpoilModal" tabindex="-1" aria-labelledby="fsSpoilTitle" aria-hidden="true">
  <div class="modal-dialog"><form class="modal-content" id="fsSpoilForm" novalidate>
    <div class="modal-header"><h2 class="modal-title h5" id="fsSpoilTitle">Record food thrown away</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <div class="row g-2 mb-3">
        <div class="col-5"><label class="form-label small" for="fsSpDate">Date</label><input class="form-control" type="date" id="fsSpDate"></div>
        <div class="col-7"><label class="form-label small" for="fsSpReason">Why</label><input class="form-control" id="fsSpReason" maxlength="500" required list="fsSpReasons" placeholder="Expired, spoilt, weevils"></div>
      </div>
      <datalist id="fsSpReasons"><option value="Past its use-by date"><option value="Spoilt (rotten or mouldy)"><option value="Weevils or pests"><option value="Damaged packaging"><option value="Rats"></datalist>
      <div class="ops-lines" id="fsSpLines"></div>
      <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-add-line="fsSpLines"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Add another item</button>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Take out of the store</button></div>
  </form></div>
</div>

<div class="modal fade" id="fsCardModal" tabindex="-1" aria-labelledby="fsCardName" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0">
      <div class="d-flex flex-wrap gap-2 align-items-end d-print-none">
        <div><label class="form-label small mb-0" for="fsCardFrom">From</label><input class="form-control form-control-sm" type="date" id="fsCardFrom"></div>
        <div><label class="form-label small mb-0" for="fsCardTo">To</label><input class="form-control form-control-sm" type="date" id="fsCardTo"></div>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="fsCardCsv"><i class="bi bi-download me-1" aria-hidden="true"></i>CSV</button>
        <button class="btn btn-outline-secondary btn-sm" type="button" id="fsCardPrint"><i class="bi bi-printer me-1" aria-hidden="true"></i>Print</button>
      </div>
      <button type="button" class="btn-close d-print-none" data-bs-dismiss="modal" aria-label="Close"></button>
    </div>
    <div class="modal-body">
      <section class="ops-bincard" aria-labelledby="fsCardName">
        <div class="ops-bincard__head">
          <h2 class="ops-bincard__name" id="fsCardName">Bin card</h2>
          <dl id="fsCardFacts"></dl>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead><tr><th scope="col">Date</th><th scope="col">Details</th><th scope="col" class="is-in">Received</th><th scope="col" class="is-out">Issued</th><th scope="col" class="is-bal">Balance</th></tr></thead>
            <tbody id="fsCardBody"></tbody>
          </table>
        </div>
      </section>
    </div>
  </div></div>
</div>
