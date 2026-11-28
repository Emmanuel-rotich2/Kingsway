/** Deliveries: food received into the store, grouped by delivery (ledger rows of kind 'receive'). */
(() => {
  'use strict';
  const root = document.getElementById('deliveriesPage');
  if (!root) return;
  const O = window.OpsCommon;
  const F = window.FoodStore;
  const { esc, num, kes, date, data, api } = O;
  const $ = (id) => document.getElementById(id);
  let rows = [];

  const shown = () => rows.filter((r) => !$('dlSup').value || r.supplier === $('dlSup').value);

  async function load() {
    $('dlBody').innerHTML = '<tr><td colspan="5" class="text-muted p-3">Loading deliveries…</td></tr>';
    try {
      rows = (data(await api('/catering/store-ledger', 'GET', null, { movement: 'receive', from: $('dlFrom').value, to: $('dlTo').value })).rows) || [];
    } catch (e) { $('dlBody').innerHTML = `<tr><td colspan="5" class="text-muted p-3">${esc(e.message || 'Deliveries could not be loaded.')}</td></tr>`; return; }
    const cur = $('dlSup').value;
    const sups = [...new Set(rows.map((r) => r.supplier).filter(Boolean))].sort();
    $('dlSup').innerHTML = '<option value="">All suppliers</option>' + sups.map((s) => `<option>${esc(s)}</option>`).join('');
    $('dlSup').value = sups.includes(cur) ? cur : '';
    render();
  }

  function render() {
    const list = shown();
    const groups = new Map();
    list.forEach((r) => { const k = `${r.date}|${r.supplier}|${r.document_no || ''}`; if (!groups.has(k)) groups.set(k, []); groups.get(k).push(r); });
    const value = list.reduce((s, r) => s + (r.value || 0), 0);
    $('dlFacts').innerHTML = [[num(groups.size), groups.size === 1 ? 'delivery' : 'deliveries'], [kes(value), 'received, at cost']]
      .map(([v, l]) => `<li><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
    $('dlBody').innerHTML = groups.size ? [...groups.values()].map((g) => {
      const h = g[0];
      const head = [date(h.date), h.supplier || 'Supplier not recorded', h.document_no ? `delivery note ${h.document_no}` : 'no delivery note'].join(', ');
      return `<tr class="table-active"><th scope="rowgroup" colspan="5"><span class="fw-bold">${esc(head)}</span>
          <span class="small text-muted fw-normal ms-2">${esc(kes(g.reduce((s, r) => s + (r.value || 0), 0)))}${h.recorded_by ? `, received by ${esc(h.recorded_by)}` : ''}</span>
          ${h.notes ? `<div class="small fw-normal">${esc(h.notes)}</div>` : ''}</th></tr>`
        + g.map((r) => `<tr><td>${esc(r.item)}</td><td class="text-nowrap">${esc(num(r.in))} ${esc(r.unit)}</td><td>${r.unit_cost !== null ? esc(kes(r.unit_cost)) : ''}</td>
          <td>${r.value !== null ? esc(kes(r.value)) : ''}</td><td>${r.expiry_date ? esc(date(r.expiry_date)) : ''}</td></tr>`).join('');
    }).join('') : '<tr><td colspan="5" class="text-muted p-3">No deliveries in these dates.</td></tr>';
  }

  async function init() {
    await window.AuthContext?.ready?.();
    F.wire();
    $('dlTo').value = O.today();
    const d = new Date(); d.setDate(d.getDate() - 30); $('dlFrom').value = d.toISOString().slice(0, 10);
    ['dlFrom', 'dlTo'].forEach((id) => $(id).addEventListener('change', load));
    $('dlSup').addEventListener('change', render);
    F.onChange(load);
    $('dlNew').addEventListener('click', () => F.openReceive());
    $('dlPrint').addEventListener('click', () => window.print());
    $('dlCsv').addEventListener('click', () => O.exportCsv(`food_deliveries_${$('dlFrom').value}_${$('dlTo').value}.csv`, ['Date', 'Supplier', 'Delivery note', 'Item', 'Quantity', 'Unit', 'Cost of one', 'Value', 'Use by', 'Received by', 'Notes'],
      shown().map((r) => [r.date, r.supplier, r.document_no, r.item, r.in, r.unit, r.unit_cost, r.value, r.expiry_date, r.recorded_by, r.notes])));
    if (!O.canExport()) $('dlCsv').hidden = true;
    window.APIRealtime?.register?.('food_orders', { refresh: load }, ['inventory', 'catering']);
    try { await F.load(); } catch { /* the list still loads; the form needs the store */ }
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
