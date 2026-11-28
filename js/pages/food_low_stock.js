/** Reorder list: what to buy, grouped by supplier (from /api/catering/store). */
(() => {
  'use strict';
  const root = document.getElementById('reorderPage');
  if (!root) return;
  const O = window.OpsCommon;
  const F = window.FoodStore;
  const { esc, num, kes } = O;
  const $ = (id) => document.getElementById(id);

  /** Enough for three weeks at the usual rate, and at least twice the reorder level. */
  const suggest = (i) => Math.max(0, Math.max(i.reorder_level * 2, Math.ceil(i.daily_use * 21)) - i.on_hand);
  const need = () => F.active().filter((i) => i.level !== 'ok' || (i.days_left !== null && i.days_left <= 7))
    .map((i) => ({ ...i, order: Math.max(suggest(i), 1) }))
    .sort((a, b) => ({ out: 0, low: 1, ok: 2 }[a.level] - { out: 0, low: 1, ok: 2 }[b.level]) || (a.days_left ?? 999) - (b.days_left ?? 999));

  function render() {
    const list = need();
    const cost = list.reduce((s, i) => s + i.order * i.unit_cost, 0);
    $('roFacts').innerHTML = [
      [list.some((i) => i.level === 'out') ? 'is-stop' : '', num(list.filter((i) => i.level === 'out').length), 'out of stock'],
      [list.length ? 'is-warn' : 'is-ok', num(list.length), 'items to order'],
      ['', kes(cost), 'estimated cost, at the last price'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
    if (!list.length) { $('roGroups').innerHTML = '<div class="ops-panel"><p class="ops-empty mb-0">Nothing needs ordering. Every item is above its reorder level and lasts more than a week.</p></div>'; return; }

    const groups = new Map();
    list.forEach((i) => { const k = i.supplier || ''; if (!groups.has(k)) groups.set(k, []); groups.get(k).push(i); });
    const m = F.state.canManage;
    $('roGroups').innerHTML = [...groups].sort(([a], [b]) => (a ? 0 : 1) - (b ? 0 : 1) || a.localeCompare(b)).map(([sup, rows]) => `
      <section class="ops-panel mb-3" aria-label="${esc(sup || 'No usual supplier')}">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2 mb-2">
          <h2 class="mb-0">${esc(sup || 'No usual supplier set')}</h2>
          <span class="small text-muted">${esc(kes(rows.reduce((s, i) => s + i.order * i.unit_cost, 0)))}</span>
        </div>
        <div class="table-responsive" tabindex="0" role="region" aria-label="${esc(sup || 'No usual supplier')}">
          <table class="table align-middle mb-0">
            <thead><tr><th scope="col">Item</th><th scope="col">In the store</th><th scope="col">Lasts</th><th scope="col">Order</th><th scope="col">Estimated cost</th><th scope="col" class="d-print-none"><span class="visually-hidden">Actions</span></th></tr></thead>
            <tbody>${rows.map((i) => `<tr>
              <td><span class="fw-bold">${esc(i.name)}</span> ${i.level === 'ok' ? '<span class="ops-tag is-warn">Runs out this week</span>' : F.levelTag(i)}<div class="small text-muted">${esc(i.category)}</div></td>
              <td>${F.gauge(i)}</td>
              <td>${F.lasts(i)}</td>
              <td class="fw-bold text-nowrap">${esc(num(i.order))} ${esc(i.unit)}</td>
              <td class="text-nowrap">${esc(kes(i.order * i.unit_cost))}</td>
              <td class="d-print-none text-end">${m ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-receive="${i.id}" data-qty="${i.order}">It has come</button>` : ''}</td></tr>`).join('')}</tbody>
          </table>
        </div>
      </section>`).join('');
  }

  async function init() {
    await window.AuthContext?.ready?.();
    F.wire();
    F.onChange(render);
    $('roGroups').addEventListener('click', (e) => {
      const b = e.target.closest('[data-receive]');
      if (b) F.openReceive([{ item_id: Number(b.dataset.receive), quantity: Number(b.dataset.qty) }]);
    });
    $('roPrint').addEventListener('click', () => window.print());
    $('roCsv').addEventListener('click', () => O.exportCsv(`food_reorder_${O.today()}.csv`, ['Supplier', 'Item', 'Category', 'In the store', 'Unit', 'Order more at', 'Days left', 'Order', 'Cost of one', 'Estimated cost'],
      need().map((i) => [i.supplier, i.name, i.category, i.on_hand, i.unit, i.reorder_level, i.days_left ?? '', i.order, i.unit_cost, i.order * i.unit_cost])));
    if (!O.canExport()) $('roCsv').hidden = true;
    window.APIRealtime?.register?.('food_low_stock', { refresh: async () => { await F.load(); render(); } }, ['inventory', 'catering']);
    try { await F.load(); render(); }
    catch (e) { $('roGroups').innerHTML = `<div class="ops-panel text-muted">${esc(e.message || 'The food store could not be loaded.')}</div>`; }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
