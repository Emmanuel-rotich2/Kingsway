/**
 * Shared food store helpers and forms (components/modals/food_store_forms.php).
 * window.FoodStore: load(), state, gauge(), tags, and open*() for the forms.
 * Every write reloads the store and calls the page's onChange callbacks.
 */
(function () {
  'use strict';
  if (window.FoodStore) return;
  const O = window.OpsCommon;
  const { esc, data, api, notify, num, date, today } = O;
  const $ = (id) => document.getElementById(id);
  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));
  const MEAL = { breakfast: 'Breakfast', snack: 'Snack', lunch: 'Lunch', dinner: 'Supper' };
  const MOVE = { receive: 'Delivery', issue: 'Issued', spoil: 'Thrown away', count: 'Stock count' };
  const listeners = [];
  const state = { items: [], categories: [], locations: [], suppliers: [], canManage: false, loaded: false };

  async function load() {
    const res = data(await api('/catering/store', 'GET', null, { all: 1 }));
    state.items = res.items || [];
    state.categories = res.categories || [];
    state.locations = res.locations || [];
    state.suppliers = res.suppliers || [];
    state.canManage = Boolean(res.can_manage);
    state.loaded = true;
    document.querySelectorAll('[data-fs-manage]').forEach((el) => { el.hidden = !state.canManage; });
    return state;
  }
  const active = () => state.items.filter((i) => i.active);
  const byId = (id) => state.items.find((i) => Number(i.id) === Number(id));
  const changed = async () => { await load(); listeners.forEach((fn) => fn(state)); };
  const qty = (i, n = i.on_hand) => `${num(n)} ${i.unit || ''}`.trim();

  /** The shelf gauge: on hand against twice the reorder level; the tick marks the reorder level. */
  function gauge(i) {
    const full = Math.max(i.reorder_level * 2, 1);
    const pct = Math.min(100, Math.round((i.on_hand / full) * 100));
    const tone = i.level === 'out' ? 'is-out' : i.level === 'low' ? 'is-low' : '';
    const said = i.level === 'out' ? 'none left' : i.level === 'low' ? `at or below the reorder level of ${num(i.reorder_level)}` : `reorder level ${num(i.reorder_level)}`;
    return `<div class="ops-gauge ${tone}"><span class="ops-gauge__q">${esc(num(i.on_hand))} <small>${esc(i.unit || '')}</small></span>
      <span class="ops-gauge__bar" role="img" aria-label="${esc(`${qty(i)}, ${said}`)}"><span class="ops-gauge__fill" style="inline-size:${pct}%"></span>${i.reorder_level ? '<span class="ops-gauge__tick"></span>' : ''}</span></div>`;
  }
  function levelTag(i) {
    if (!i.active) return '<span class="ops-tag is-muted">No longer kept</span>';
    return i.level === 'out' ? '<span class="ops-tag is-stop">Out</span>' : i.level === 'low' ? '<span class="ops-tag is-warn">Order more</span>' : '<span class="ops-tag is-ok">Enough</span>';
  }
  function lasts(i) {
    if (i.on_hand <= 0) return '<span class="text-danger fw-bold">None left</span>';
    if (i.days_left === null || i.days_left === undefined) return '<span class="text-muted">Not used lately</span>';
    const tone = i.days_left <= 3 ? 'text-danger fw-bold' : i.days_left <= 7 ? 'fw-bold' : '';
    return `<span class="${tone}">${i.days_left === 0 ? 'Less than a day' : `About ${num(i.days_left)} day${i.days_left === 1 ? '' : 's'}`}</span>`;
  }
  function expiry(i) {
    if (i.expiry === 'none' || !i.expiry_date) return '';
    const d = O.daysUntil(i.expiry_date);
    if (i.expiry === 'expired') return `<span class="ops-tag is-stop">Expired ${esc(date(i.expiry_date))}</span>`;
    if (i.expiry === 'soon') return `<span class="ops-tag is-warn">Use by ${esc(date(i.expiry_date))}${d === 0 ? ', today' : ''}</span>`;
    return `<span class="small text-muted">Use by ${esc(date(i.expiry_date))}</span>`;
  }

  // ---------------------------------------------------------------- forms

  const opts = (list, value, label, blank) => (blank ? `<option value="">${esc(blank)}</option>` : '')
    + list.map((x) => `<option value="${Number(x.id)}" ${Number(x.id) === Number(value) ? 'selected' : ''}>${esc(x[label])}</option>`).join('');

  function openItem(item = null) {
    $('fsItemTitle').textContent = item ? `Edit ${item.name}` : 'Add a food item';
    $('fsItemId').value = item?.id || '';
    $('fsItemName').value = item?.name || '';
    $('fsItemCat').innerHTML = opts(state.categories, item?.category_id, 'name');
    $('fsItemUnit').value = item?.unit || '';
    $('fsItemReorder').value = item?.reorder_level ?? '';
    $('fsItemCost').value = item?.unit_cost ?? '';
    $('fsItemLoc').innerHTML = opts(state.locations, item?.location_id, 'location_name', 'Not set');
    $('fsItemSup').innerHTML = opts(state.suppliers, item?.supplier_id, 'name', 'Not set');
    $('fsItemOpening').value = '';
    $('fsItemOpeningWrap').hidden = Boolean(item);
    $('fsItemActiveWrap').hidden = !item;
    $('fsItemActive').checked = item ? item.active : true;
    $('fsItemQtyNote').hidden = !item;
    modal('fsItemModal').show();
  }

  /** A line editor: kind 'receive' (qty, cost, use-by) or 'out' (qty, with what is in the store). */
  function lineRow(kind, line = {}) {
    const items = active();
    const sel = `<select class="form-select" data-f="item_id" aria-label="Item"><option value="">Choose an item</option>${items.map((i) => `<option value="${i.id}" ${Number(i.id) === Number(line.item_id) ? 'selected' : ''}>${esc(i.name)}${kind === 'out' ? ` (${esc(qty(i))})` : ''}</option>`).join('')}</select>`;
    const i = byId(line.item_id);
    const q = `<input class="form-control" type="number" min="1" inputmode="numeric" data-f="quantity" aria-label="Quantity" value="${esc(line.quantity ?? '')}">`;
    const rm = '<button type="button" class="btn btn-outline-secondary" data-rm-line aria-label="Remove this item"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
    if (kind === 'receive') {
      return `<div class="ops-lines__row"><div><label class="form-label">Item</label>${sel}</div>
        <div><label class="form-label">Quantity</label>${q}</div>
        <div><label class="form-label">Cost of one</label><input class="form-control" type="number" min="0" step="0.01" inputmode="decimal" data-f="unit_cost" aria-label="Cost of one, KES" value="${esc(line.unit_cost ?? (i ? i.unit_cost : ''))}"></div>
        <div><label class="form-label">Use by</label><input class="form-control" type="date" data-f="expiry_date" aria-label="Use by"></div>
        <div><span class="form-label d-block">&nbsp;</span>${rm}</div></div>`;
    }
    return `<div class="ops-lines__row"><div><label class="form-label">Item</label>${sel}</div><div><label class="form-label">Quantity</label>${q}</div><div><span class="form-label d-block">&nbsp;</span>${rm}</div></div>`;
  }
  const kindOf = (id) => (id === 'fsRcLines' ? 'receive' : 'out');
  function setLines(id, lines) {
    $(id).innerHTML = (lines.length ? lines : [{}]).map((l) => lineRow(kindOf(id), l)).join('');
    total();
  }
  function readLines(id) {
    return [...$(id).querySelectorAll('.ops-lines__row')].map((r) => {
      const o = {};
      r.querySelectorAll('[data-f]').forEach((f) => { o[f.dataset.f] = f.value; });
      return o;
    }).filter((l) => l.item_id || l.quantity);
  }
  function total() {
    if (!$('fsRcTotal')) return;
    const sum = readLines('fsRcLines').reduce((s, l) => s + Number(l.quantity || 0) * Number(l.unit_cost || 0), 0);
    $('fsRcTotal').textContent = sum ? `Delivery value ${O.kes(sum)}` : '';
  }

  function openReceive(lines = []) {
    $('fsRcDate').value = today(); $('fsRcDate').max = today();
    $('fsRcSup').innerHTML = opts(state.suppliers, null, 'name', state.suppliers.length ? 'Choose the supplier' : 'No suppliers yet (add them under Food Suppliers)');
    $('fsRcDoc').value = ''; $('fsRcNotes').value = '';
    setLines('fsRcLines', lines);
    modal('fsReceiveModal').show();
  }
  function openIssue(lines = []) {
    $('fsIsDate').value = today(); $('fsIsDate').max = today();
    const h = new Date().getHours();
    $('fsIsMeal').value = h < 9 ? 'breakfast' : h < 14 ? 'lunch' : 'dinner';
    $('fsIsTo').value = ''; $('fsIsDoc').value = ''; $('fsIsNotes').value = '';
    setLines('fsIsLines', lines);
    modal('fsIssueModal').show();
  }
  function openSpoil(lines = []) {
    $('fsSpDate').value = today(); $('fsSpDate').max = today();
    $('fsSpReason').value = lines[0]?.reason || '';
    setLines('fsSpLines', lines);
    modal('fsSpoilModal').show();
  }

  async function submit(path, body, okText, modalId) {
    try {
      await api(path, 'POST', body);
      modal(modalId).hide();
      notify(okText, 'success');
      await changed();
    } catch (e) { notify(e.message || 'The food store could not be updated.', 'danger'); }
  }

  // -------------------------------------------------------------- bin card

  let cardItem = null;
  let cardRows = [];
  function openCard(item) {
    cardItem = item;
    $('fsCardName').textContent = item.name;
    $('fsCardFacts').innerHTML = [['Code', item.code], ['Counted in', item.unit], ['Order more at', num(item.reorder_level)], ['In the store', qty(item)], ['Kept in', item.location || 'Not set'], ['Last counted', item.last_count ? date(item.last_count) : 'Never']]
      .map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('');
    $('fsCardTo').value = today();
    $('fsCardFrom').value = shift(today(), -90);
    modal('fsCardModal').show();
    loadCard();
  }
  const shift = (d, n) => { const x = new Date(`${d}T00:00:00`); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
  function details(r) {
    const bits = [MOVE[r.movement] || r.movement];
    if (r.movement === 'receive') bits.push(r.supplier, r.document_no ? `note ${r.document_no}` : '', r.expiry_date ? `use by ${date(r.expiry_date)}` : '');
    if (r.movement === 'issue') bits.push(r.meal_type ? MEAL[r.meal_type] : '', r.issued_to ? `to ${r.issued_to}` : '', r.document_no ? `voucher ${r.document_no}` : '');
    if (r.movement !== 'issue' && r.notes) bits.push(r.notes);
    return bits.filter(Boolean).join(', ');
  }
  async function loadCard() {
    $('fsCardBody').innerHTML = '<tr><td colspan="5">Loading the bin card…</td></tr>';
    try {
      const res = data(await api('/catering/store-ledger', 'GET', null, { item_id: cardItem.id, from: $('fsCardFrom').value, to: $('fsCardTo').value }));
      cardRows = res.rows || [];
      $('fsCardBody').innerHTML = cardRows.length ? cardRows.map((r) => `<tr>
          <td class="text-nowrap">${esc(date(r.date))}</td>
          <td>${esc(details(r))}${r.recorded_by ? `<div class="ops-bincard__what">Recorded by ${esc(r.recorded_by)}</div>` : ''}</td>
          <td class="is-in">${r.in ? esc(num(r.in)) : ''}</td><td class="is-out">${r.out ? esc(num(r.out)) : ''}</td>
          <td class="is-bal">${r.balance === null ? '' : esc(num(r.balance))}</td></tr>`).join('')
        : '<tr><td colspan="5">Nothing came in or went out in these dates.</td></tr>';
    } catch (e) { $('fsCardBody').innerHTML = `<tr><td colspan="5">${esc(e.message || 'The bin card could not be loaded.')}</td></tr>`; }
  }

  // ---------------------------------------------------------------- wiring

  function wire() {
    if (!$('fsItemForm') || wire.done) return;
    wire.done = true;
    document.addEventListener('click', (e) => {
      const add = e.target.closest('[data-add-line]');
      if (add) { const box = $(add.dataset.addLine); box.insertAdjacentHTML('beforeend', lineRow(kindOf(box.id))); box.lastElementChild.querySelector('select')?.focus(); return; }
      const rm = e.target.closest('[data-rm-line]');
      if (rm) { const box = rm.closest('.ops-lines'); rm.closest('.ops-lines__row').remove(); if (!box.children.length) setLines(box.id, []); total(); }
    });
    $('fsRcLines').addEventListener('input', total);
    $('fsRcLines').addEventListener('change', (e) => {
      const sel = e.target.closest('[data-f="item_id"]');
      if (!sel) return;
      const i = byId(sel.value);
      const cost = sel.closest('.ops-lines__row').querySelector('[data-f="unit_cost"]');
      if (i && cost && !cost.value) cost.value = i.unit_cost || '';
      total();
    });

    $('fsItemForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (!$('fsItemName').value.trim()) { notify('Give the item a name.', 'warning'); $('fsItemName').focus(); return; }
      if (!$('fsItemUnit').value.trim()) { notify('Say how the item is counted.', 'warning'); $('fsItemUnit').focus(); return; }
      submit('/catering/store-item', {
        id: $('fsItemId').value || null, name: $('fsItemName').value.trim(), category_id: $('fsItemCat').value, unit: $('fsItemUnit').value.trim(),
        reorder_level: $('fsItemReorder').value || 0, unit_cost: $('fsItemCost').value || 0, location_id: $('fsItemLoc').value || null,
        supplier_id: $('fsItemSup').value || null, opening_quantity: $('fsItemOpening').value || 0, active: $('fsItemActive').checked,
      }, 'Item saved.', 'fsItemModal');
    });
    $('fsReceiveForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const lines = readLines('fsRcLines');
      if (!lines.length) { notify('Add the items that came.', 'warning'); return; }
      submit('/catering/store-receive', { date: $('fsRcDate').value, supplier_id: $('fsRcSup').value || null, document_no: $('fsRcDoc').value.trim(), notes: $('fsRcNotes').value.trim(), lines },
        `Delivery added to the store (${lines.length} item${lines.length === 1 ? '' : 's'}).`, 'fsReceiveModal');
    });
    $('fsIssueForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (!$('fsIsTo').value.trim()) { notify('Say who took the food.', 'warning'); $('fsIsTo').focus(); return; }
      const lines = readLines('fsIsLines');
      if (!lines.length) { notify('Add the food being issued.', 'warning'); return; }
      submit('/catering/store-issue', { date: $('fsIsDate').value, meal_type: $('fsIsMeal').value, issued_to: $('fsIsTo').value.trim(), document_no: $('fsIsDoc').value.trim(), notes: $('fsIsNotes').value.trim(), lines },
        'Food issued to the kitchen.', 'fsIssueModal');
    });
    $('fsSpoilForm').addEventListener('submit', (e) => {
      e.preventDefault();
      if (!$('fsSpReason').value.trim()) { notify('Say why the food was thrown away.', 'warning'); $('fsSpReason').focus(); return; }
      const lines = readLines('fsSpLines');
      if (!lines.length) { notify('Add the food that was thrown away.', 'warning'); return; }
      submit('/catering/store-spoil', { date: $('fsSpDate').value, reason: $('fsSpReason').value.trim(), lines }, 'Taken out of the store.', 'fsSpoilModal');
    });

    ['fsCardFrom', 'fsCardTo'].forEach((id) => $(id).addEventListener('change', loadCard));
    $('fsCardPrint').addEventListener('click', () => {
      document.body.classList.add('ops-print-card');
      window.addEventListener('afterprint', () => document.body.classList.remove('ops-print-card'), { once: true });
      window.print();
    });
    $('fsCardCsv').addEventListener('click', () => O.exportCsv(`bin_card_${(cardItem?.code || 'item').toLowerCase()}.csv`, ['Date', 'Details', 'Received', 'Issued', 'Balance', 'Recorded by'],
      cardRows.map((r) => [r.date, details(r), r.in || '', r.out || '', r.balance ?? '', r.recorded_by])));
    if (!O.canExport()) $('fsCardCsv').hidden = true;
  }

  window.FoodStore = {
    state, load, active, byId, qty, gauge, levelTag, lasts, expiry, MEAL, MOVE, details,
    openItem, openReceive, openIssue, openSpoil, openCard, wire,
    onChange: (fn) => listeners.push(fn),
  };
})();
