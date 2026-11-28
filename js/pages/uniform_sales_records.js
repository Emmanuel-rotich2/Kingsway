/** Uniform sales: every order and counter sale (from /api/inventory/uniform-store-sales). */
(() => {
  'use strict';
  const root = document.getElementById('uniformSalesPage');
  if (!root) return;
  const O = window.OpsCommon;
  const { esc, num, kes, data, api, date } = O;
  const $ = (id) => document.getElementById(id);
  let orders = [];
  let tab = 'srSales';
  const STATUS = { pending_payment: ['Waiting for payment', 'is-warn'], paid: ['Paid, to pack', 'is-warn'], preparing: ['Being packed', 'is-warn'], ready: ['Ready to collect', 'is-ok'], fulfilled: ['Collected', 'is-ok'], dispatched: ['Collected', 'is-ok'], cancelled: ['Cancelled', 'is-muted'] };
  const paid = (o) => o.payment === 'paid';
  const live = (o) => o.status !== 'cancelled';
  const time = (t) => String(t || '').slice(11, 16);
  const line = (l) => `${l.quantity} × ${l.product}${l.colour && l.colour !== 'Standard' ? `, ${l.colour}` : ''}, ${String(l.size || '').replace(/^(Clothing|Shoe) size\s+/i, '')}`;

  function shown() {
    const q = $('srSearch').value.trim().toLowerCase();
    return orders.filter((o) => (!$('srSource').value || o.source === $('srSource').value)
      && (!$('srPay').value || ($('srPay').value === 'paid' ? paid(o) : !paid(o)))
      && (!q || `${o.reference} ${o.buyer} ${o.learner} ${o.admission_no}`.toLowerCase().includes(q)));
  }

  function byItem(list) {
    const m = new Map();
    list.filter(live).forEach((o) => o.lines.forEach((l) => {
      const k = `${l.product}|${l.colour}|${l.size}`;
      const x = m.get(k) || { product: l.product, colour: l.colour, size: String(l.size || '').replace(/^(Clothing|Shoe) size\s+/i, ''), pieces: 0, takings: 0 };
      x.pieces += l.quantity; x.takings += l.total;
      m.set(k, x);
    }));
    return [...m.values()].sort((a, b) => a.product.localeCompare(b.product) || b.pieces - a.pieces);
  }

  function render() {
    const list = shown();
    const counted = list.filter(live);
    const pieces = counted.reduce((t, o) => t + o.lines.reduce((s, l) => s + l.quantity, 0), 0);
    const takings = counted.filter(paid).reduce((t, o) => t + o.total, 0);
    const owed = counted.filter((o) => !paid(o)).reduce((t, o) => t + o.total, 0);
    const disc = counted.reduce((t, o) => t + o.discount, 0);
    $('srFacts').innerHTML = [
      ['', num(counted.length), counted.length === 1 ? 'sale' : 'sales'],
      ['', num(pieces), 'pieces sold'],
      ['is-ok', kes(takings), 'paid'],
      [owed ? 'is-warn' : '', kes(owed), 'not paid yet'],
      ['', kes(disc), 'given in offers'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');

    $('srBody').innerHTML = list.length ? list.map((o) => {
      const [st, tone] = STATUS[o.status] || [O.label(o.status), 'is-muted'];
      return `<tr${live(o) ? '' : ' class="text-muted"'}>
        <td class="text-nowrap">${esc(date(o.at))}<div class="small text-muted">${esc(time(o.at))}</div></td>
        <td><span class="fw-semibold">${esc(o.reference)}</span><div class="small text-muted">${esc(o.source)}${o.served_by ? `, served by ${esc(o.served_by)}` : ''}</div></td>
        <td><ul class="ops-sale-lines">${o.lines.map((l) => `<li>${esc(line(l))}</li>`).join('')}</ul></td>
        <td>${esc(o.buyer)}${o.learner ? `<div class="small text-muted">For ${esc(o.learner)}${o.admission_no ? `, ${esc(o.admission_no)}` : ''}</div>` : ''}</td>
        <td class="text-nowrap fw-semibold">${esc(kes(o.total))}${o.discount ? `<div class="small text-muted fw-normal">${esc(kes(o.discount))} off</div>` : ''}</td>
        <td><span class="ops-tag ${paid(o) ? 'is-ok' : 'is-warn'}">${paid(o) ? 'Paid' : 'Not paid'}</span><div class="small mt-1"><span class="ops-tag ${tone}">${esc(st)}</span></div></td></tr>`;
    }).join('') : `<tr><td colspan="6" class="text-muted p-3">${orders.length ? 'No sale matches.' : 'No sales in these dates.'}</td></tr>`;

    const items = byItem(list);
    $('srItemBody').innerHTML = items.length ? items.map((x) => `<tr><td class="fw-semibold">${esc(x.product)}${x.colour && x.colour !== 'Standard' ? `, ${esc(x.colour)}` : ''}</td>
        <td>${esc(x.size)}</td><td>${esc(num(x.pieces))}</td><td>${esc(kes(x.takings))}</td></tr>`).join('')
      : '<tr><td colspan="4" class="text-muted p-3">Nothing sold in these dates.</td></tr>';
  }

  async function load() {
    $('srBody').innerHTML = '<tr><td colspan="6" class="text-muted p-3">Loading sales…</td></tr>';
    try { orders = data(await api('/inventory/uniform-store-sales', 'GET', null, { from: $('srFrom').value, to: $('srTo').value })).orders || []; }
    catch (e) { orders = []; $('srBody').innerHTML = `<tr><td colspan="6" class="text-muted p-3">${esc(e.message || 'Sales could not be loaded.')}</td></tr>`; return; }
    const cur = $('srSource').value;
    const src = [...new Set(orders.map((o) => o.source))].sort();
    $('srSource').innerHTML = '<option value="">Everywhere</option>' + src.map((s) => `<option>${esc(s)}</option>`).join('');
    $('srSource').value = src.includes(cur) ? cur : '';
    render();
  }

  async function init() {
    await window.AuthContext?.ready?.();
    const t = O.today();
    $('srTo').value = t;
    $('srFrom').value = `${t.slice(0, 8)}01`;
    ['srFrom', 'srTo'].forEach((id) => $(id).addEventListener('change', load));
    ['srSource', 'srPay'].forEach((id) => $(id).addEventListener('change', render));
    $('srSearch').addEventListener('input', render);
    O.tabs($('srTabs'), (id) => { tab = id; });
    $('srPrint').addEventListener('click', () => window.print());
    $('srCsv').addEventListener('click', () => {
      const span = `${$('srFrom').value}_${$('srTo').value}`;
      if (tab === 'srItems') {
        O.exportCsv(`uniform_sales_by_size_${span}.csv`, ['Garment', 'Colour', 'Size', 'Pieces sold', 'Takings'], byItem(shown()).map((x) => [x.product, x.colour, x.size, x.pieces, x.takings]));
      } else {
        O.exportCsv(`uniform_sales_${span}.csv`, ['Date', 'Time', 'Reference', 'Sold through', 'Buyer', 'Learner', 'Admission no.', 'Items', 'Subtotal', 'Offer', 'Total', 'Payment', 'Status', 'Served by'],
          shown().map((o) => [String(o.at).slice(0, 10), time(o.at), o.reference, o.source, o.buyer, o.learner, o.admission_no, o.lines.map(line).join('; '), o.subtotal, o.discount, o.total, paid(o) ? 'Paid' : 'Not paid', (STATUS[o.status] || [o.status])[0], o.served_by]));
      }
    });
    if (!O.canExport()) $('srCsv').hidden = true;
    window.APIRealtime?.register?.('uniform_sales_records', { refresh: load }, ['inventory', 'commerce']);
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
