/** Daily usage: issues to the kitchen and food thrown away (ledger rows of kind 'issue' and 'spoil'). */
(() => {
  'use strict';
  const root = document.getElementById('usagePage');
  if (!root) return;
  const O = window.OpsCommon;
  const F = window.FoodStore;
  const { esc, num, kes, date, data, api } = O;
  const $ = (id) => document.getElementById(id);
  let rows = [];

  const days = () => Math.max(1, Math.round((new Date(`${$('fcTo').value}T00:00:00`) - new Date(`${$('fcFrom').value}T00:00:00`)) / 86400000) + 1);

  async function load() {
    try {
      const res = data(await api('/catering/store-ledger', 'GET', null, { from: $('fcFrom').value, to: $('fcTo').value }));
      rows = (res.rows || []).filter((r) => r.movement === 'issue' || r.movement === 'spoil');
    } catch (e) { $('usItemBody').innerHTML = `<tr><td colspan="5" class="text-muted p-3">${esc(e.message || 'Usage could not be loaded.')}</td></tr>`; return; }
    render();
  }

  function byItem() {
    const m = new Map();
    rows.forEach((r) => {
      const x = m.get(r.item_id) || { item: r.item, unit: r.unit, issued: 0, thrown: 0, cost: 0, wasteCost: 0 };
      if (r.movement === 'issue') { x.issued += r.out; x.cost += r.value || 0; } else { x.thrown += r.out; x.wasteCost += r.value || 0; }
      m.set(r.item_id, x);
    });
    return [...m.values()].sort((a, b) => (b.cost + b.wasteCost) - (a.cost + a.wasteCost));
  }

  function render() {
    const items = byItem();
    const cost = items.reduce((s, x) => s + x.cost, 0);
    const waste = items.reduce((s, x) => s + x.wasteCost, 0);
    const n = days();
    $('usFacts').innerHTML = [
      ['', kes(cost), 'issued to the kitchen'],
      ['', kes(cost / n), 'a day, on average'],
      [waste ? 'is-stop' : '', kes(waste), 'thrown away'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
    $('usItemBody').innerHTML = items.length ? items.map((x) => `<tr><td class="fw-semibold">${esc(x.item)}</td>
        <td class="text-nowrap">${esc(num(x.issued))} ${esc(x.unit)}</td><td>${esc(num(Math.round((x.issued / n) * 10) / 10))}</td>
        <td>${x.thrown ? `<strong class="text-danger">${esc(num(x.thrown))} ${esc(x.unit)}</strong>` : ''}</td>
        <td class="text-nowrap">${esc(kes(x.cost + x.wasteCost))}</td></tr>`).join('')
      : '<tr><td colspan="5" class="text-muted p-3">No food left the store in these dates.</td></tr>';

    const byDay = new Map();
    rows.forEach((r) => { if (!byDay.has(r.date)) byDay.set(r.date, []); byDay.get(r.date).push(r); });
    $('usDayBody').innerHTML = byDay.size ? [...byDay].map(([d, list]) => `<tr class="table-active"><th scope="rowgroup" colspan="5">${esc(date(d))}
        <span class="small text-muted fw-normal ms-2">${esc(kes(list.reduce((s, r) => s + (r.value || 0), 0)))}</span></th></tr>`
      + list.map((r) => `<tr><td>${esc(r.item)}${r.movement === 'spoil' ? ' <span class="ops-tag is-stop">Thrown away</span>' : ''}</td>
          <td class="text-nowrap">${esc(num(r.out))} ${esc(r.unit)}</td><td>${esc(r.meal_type ? F.MEAL[r.meal_type] : '')}</td>
          <td>${esc(r.movement === 'spoil' ? r.notes || '' : r.issued_to || '')}</td><td class="text-nowrap">${r.value !== null ? esc(kes(r.value)) : ''}</td></tr>`).join('')).join('')
      : '<tr><td colspan="5" class="text-muted p-3">No food left the store in these dates.</td></tr>';
  }

  async function init() {
    await window.AuthContext?.ready?.();
    F.wire();
    $('fcTo').value = O.today();
    const d = new Date(); d.setDate(d.getDate() - 13); $('fcFrom').value = d.toISOString().slice(0, 10);
    ['fcFrom', 'fcTo'].forEach((id) => $(id).addEventListener('change', load));
    O.tabs($('usTabs'));
    F.onChange(load);
    $('usIssue').addEventListener('click', () => F.openIssue());
    $('usPrint').addEventListener('click', () => window.print());
    $('usCsv').addEventListener('click', () => O.exportCsv(`food_usage_${$('fcFrom').value}_${$('fcTo').value}.csv`, ['Date', 'Item', 'Kind', 'Quantity', 'Unit', 'Meal', 'Taken by', 'Reason', 'Cost', 'Recorded by'],
      rows.map((r) => [r.date, r.item, r.movement === 'spoil' ? 'Thrown away' : 'Issued', r.out, r.unit, r.meal_type ? F.MEAL[r.meal_type] : '', r.issued_to, r.movement === 'spoil' ? r.notes : '', r.value, r.recorded_by])));
    if (!O.canExport()) $('usCsv').hidden = true;
    window.APIRealtime?.register?.('food_consumption', { refresh: load }, ['inventory', 'catering']);
    try { await F.load(); } catch { /* the report still loads */ }
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
