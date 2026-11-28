/** Food store: the items, their levels and the storekeeper's actions (FoodStoreService). */
(() => {
  'use strict';
  const root = document.getElementById('foodStorePage');
  if (!root) return;
  const O = window.OpsCommon;
  const F = window.FoodStore;
  const { esc, num, kes, notify } = O;
  const $ = (id) => document.getElementById(id);
  let show = '';

  function list() {
    const q = $('fsSearch').value.trim().toLowerCase();
    const cat = $('fsCat').value;
    return F.state.items.filter((i) => (show === 'inactive' ? !i.active : i.active)
      && (!q || `${i.name} ${i.code}`.toLowerCase().includes(q))
      && (!cat || String(i.category_id) === cat)
      && (show !== 'low' || i.level !== 'ok' || (i.days_left !== null && i.days_left <= 7))
      && (show !== 'expiry' || ['soon', 'expired'].includes(i.expiry)));
  }

  function render() {
    const items = F.active();
    const low = items.filter((i) => i.level === 'low').length;
    const out = items.filter((i) => i.level === 'out').length;
    const soon = items.filter((i) => ['soon', 'expired'].includes(i.expiry)).length;
    $('fsFacts').innerHTML = [
      ['', num(items.length), 'food items kept'],
      [out ? 'is-stop' : '', num(out), 'out of stock'],
      [low ? 'is-warn' : '', num(low), 'to order more'],
      [soon ? 'is-warn' : '', num(soon), 'to use soon or throw away'],
      ['', kes(items.reduce((s, i) => s + i.value, 0)), 'value of the stock'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');

    const rows = list();
    const m = F.state.canManage;
    $('fsBody').innerHTML = rows.length ? rows.map((i) => `<tr>
        <td><button type="button" class="btn btn-link p-0 fw-bold text-start text-decoration-none" data-card="${i.id}">${esc(i.name)}</button>
          <div class="small text-muted">${esc([i.category, i.code, i.location].filter(Boolean).join(', '))}</div></td>
        <td>${F.gauge(i)}</td>
        <td>${F.lasts(i)}<div class="small text-muted">${i.daily_use ? `uses about ${esc(num(i.daily_use))} a day` : ''}</div></td>
        <td>${F.expiry(i) || '<span class="text-muted">None</span>'}</td>
        <td class="text-nowrap">${esc(kes(i.value))}<div class="small text-muted">${esc(kes(i.unit_cost))} each</div></td>
        <td class="d-print-none text-end text-nowrap">
          ${m && i.active ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-issue="${i.id}">Issue</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-receive="${i.id}">Receive</button>` : ''}
          ${m ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-edit="${i.id}">Edit<span class="visually-hidden"> ${esc(i.name)}</span></button>` : ''}
        </td></tr>`).join('')
      : `<tr><td colspan="6" class="text-muted p-3">${F.state.items.length ? 'No item matches.' : (m ? 'The store has no food items yet. Use "Add an item" to start.' : 'The store has no food items yet.')}</td></tr>`;
  }

  async function reload() {
    try { await F.load(); }
    catch (e) { $('fsBody').innerHTML = `<tr><td colspan="6" class="text-muted p-3">${esc(e.message || 'The food store could not be loaded.')}</td></tr>`; return; }
    const cur = $('fsCat').value;
    $('fsCat').innerHTML = '<option value="">All food</option>' + F.state.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    $('fsCat').value = cur;
    render();
  }

  async function init() {
    await window.AuthContext?.ready?.();
    F.wire();
    F.onChange(render);
    $('fsSearch').addEventListener('input', render);
    $('fsCat').addEventListener('change', render);
    $('fsShow').addEventListener('click', (e) => {
      const b = e.target.closest('[data-show]');
      if (!b) return;
      show = b.dataset.show;
      $('fsShow').querySelectorAll('[data-show]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      render();
    });
    $('fsBody').addEventListener('click', (e) => {
      const b = e.target.closest('[data-card],[data-issue],[data-receive],[data-edit]');
      if (!b) return;
      const i = F.byId(b.dataset.card || b.dataset.issue || b.dataset.receive || b.dataset.edit);
      if (b.dataset.card) F.openCard(i);
      else if (b.dataset.issue) F.openIssue([{ item_id: i.id }]);
      else if (b.dataset.receive) F.openReceive([{ item_id: i.id }]);
      else F.openItem(i);
    });
    $('fsIssueBtn').addEventListener('click', () => F.openIssue());
    $('fsReceiveBtn').addEventListener('click', () => F.openReceive());
    $('fsSpoilBtn').addEventListener('click', () => F.openSpoil());
    $('fsAddBtn').addEventListener('click', () => F.openItem());
    $('fsPrint').addEventListener('click', () => window.print());
    $('fsCsv').addEventListener('click', () => O.exportCsv(`food_store_${O.today()}.csv`, ['Item', 'Code', 'Category', 'Kept in', 'In the store', 'Unit', 'Order more at', 'Status', 'Uses a day', 'Days left', 'Use by', 'Cost of one', 'Value'],
      list().map((i) => [i.name, i.code, i.category, i.location, i.on_hand, i.unit, i.reorder_level, i.level === 'out' ? 'Out' : i.level === 'low' ? 'Order more' : 'Enough', i.daily_use, i.days_left ?? '', i.expiry_date || '', i.unit_cost, i.value])));
    if (!O.canExport()) $('fsCsv').hidden = true;
    window.APIRealtime?.register?.('food_store', { refresh: reload }, ['inventory', 'catering']);
    await reload();
    if (!F.state.loaded) notify('The food store could not be loaded.', 'danger');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
