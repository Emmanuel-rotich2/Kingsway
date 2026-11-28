/** Uniform stock: size runs per garment (from /api/inventory/uniform-store-stock). */
(() => {
  'use strict';
  const root = document.getElementById('uniformStockPage');
  if (!root) return;
  const O = window.OpsCommon;
  const { esc, num, kes, data, api, date } = O;
  const $ = (id) => document.getElementById(id);
  let sizes = [];
  let show = '';
  const label = (s) => String(s || '').replace(/^Clothing size\s+/i, '').replace(/^Shoe size\s+/i, '');
  const coll = (c) => O.label(String(c || '').replace(/-/g, ' '));

  function garments() {
    const q = $('usSearch').value.trim().toLowerCase();
    const map = new Map();
    sizes.filter((s) => (!$('usCat').value || s.category === $('usCat').value)
      && (!q || `${s.product} ${s.colour} ${s.code}`.toLowerCase().includes(q)))
      .forEach((s) => { const k = `${s.item_id}`; if (!map.has(k)) map.set(k, []); map.get(k).push(s); });
    return [...map.values()]
      .filter((g) => show !== 'restock' || g.some((s) => s.level !== 'ok'))
      .filter((g) => show !== 'unlabelled' || g.some((s) => s.unlabelled > 0))
      .sort((a, b) => a[0].product.localeCompare(b[0].product) || a[0].colour.localeCompare(b[0].colour));
  }

  function render() {
    const onShelf = sizes.reduce((t, s) => t + s.on_shelf, 0);
    const out = sizes.filter((s) => s.level === 'out' && s.on_sale).length;
    const low = sizes.filter((s) => s.level === 'low' && s.on_sale).length;
    const unl = sizes.reduce((t, s) => t + s.unlabelled, 0);
    const held = sizes.reduce((t, s) => t + s.held, 0);
    $('usFacts').innerHTML = [
      ['', num(onShelf), 'pieces on the shelf'],
      [held ? 'is-warn' : '', num(held), 'held for paid orders'],
      [out ? 'is-stop' : '', num(out), 'sizes sold out'],
      [low ? 'is-warn' : '', num(low), 'sizes running low'],
      [unl ? 'is-warn' : '', num(unl), 'pieces without a label'],
      ['', kes(sizes.reduce((t, s) => t + s.value, 0)), 'value at selling price'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');

    const list = garments();
    $('usList').innerHTML = list.length ? list.map((g) => {
      const f = g[0];
      const prices = [...new Set(g.map((s) => s.price))].sort((a, b) => a - b);
      const unl = g.reduce((t, s) => t + s.unlabelled, 0);
      const sold = g.reduce((t, s) => t + s.sold, 0);
      return `<article class="ops-garment">
        <div>
          <h2 class="ops-garment__name">${/^#[0-9a-f]{3,8}$/i.test(f.swatch || '') ? `<span class="ops-swatch" style="background:${esc(f.swatch)}" aria-hidden="true"></span>` : ''}${esc(f.product)}${f.colour ? `, ${esc(f.colour)}` : ''}</h2>
          <div class="ops-garment__meta">${esc([coll(f.category), f.code].filter(Boolean).join(', '))}</div>
          <div class="ops-garment__meta">${esc(prices.length > 1 ? `${kes(prices[0])} to ${kes(prices[prices.length - 1])}` : kes(prices[0]))}, ${esc(num(sold))} sold</div>
          ${!f.on_sale ? '<span class="ops-tag is-muted mt-1">Not on sale</span>' : ''}
          ${unl ? `<div class="small mt-1"><span class="ops-tag is-warn">${esc(num(unl))} without a label</span></div>` : ''}
        </div>
        <ul class="ops-sizes" aria-label="${esc(`${f.product} ${f.colour} by size`)}">${g.map((s) => `<li class="ops-size ${s.level === 'out' ? 'is-out' : s.level === 'low' ? 'is-low' : ''}"
            title="${esc(`${label(s.size)}: ${s.on_shelf} on the shelf, ${s.held} held, restock at ${s.reorder_level}`)}">
            <span class="ops-size__label">${esc(label(s.size))}</span>
            <span class="ops-size__n">${esc(num(s.free))}</span>
            <span class="ops-size__held">${s.held ? `+${esc(num(s.held))} held` : s.level === 'out' ? 'sold out' : s.level === 'low' ? 'restock' : 'free'}</span></li>`).join('')}</ul>
      </article>`;
    }).join('') : `<p class="ops-empty">${sizes.length ? 'Nothing matches.' : 'The uniform store has no sizes set up yet. Add products and sizes under Manage Products.'}</p>`;
  }

  async function load() {
    try { sizes = data(await api('/inventory/uniform-store-stock')).sizes || []; }
    catch (e) { $('usList').innerHTML = `<p class="ops-empty">${esc(e.message || 'The stock could not be loaded.')}</p>`; return; }
    const cur = $('usCat').value;
    const cats = [...new Set(sizes.map((s) => s.category).filter(Boolean))].sort();
    $('usCat').innerHTML = '<option value="">All</option>' + cats.map((c) => `<option value="${esc(c)}">${esc(coll(c))}</option>`).join('');
    $('usCat').value = cats.includes(cur) ? cur : '';
    render();
  }

  async function init() {
    await window.AuthContext?.ready?.();
    const roles = (window.AuthContext?.getRoles?.() || []).map((r) => String(r).toLowerCase());
    if (!roles.some((r) => r.includes('uniform') || r.includes('store manager'))) root.querySelectorAll('[data-store-only]').forEach((a) => { a.hidden = true; });
    $('usSearch').addEventListener('input', render);
    $('usCat').addEventListener('change', render);
    $('usShow').addEventListener('click', (e) => {
      const b = e.target.closest('[data-show]');
      if (!b) return;
      show = b.dataset.show;
      $('usShow').querySelectorAll('[data-show]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
      render();
    });
    $('usPrint').addEventListener('click', () => window.print());
    $('usCsv').addEventListener('click', () => O.exportCsv(`uniform_stock_${O.today()}.csv`, ['Garment', 'Colour', 'Collection', 'Code', 'Size', 'On the shelf', 'Held for orders', 'Free to sell', 'Restock at', 'Sold', 'Price', 'Value', 'Labelled', 'Without a label', 'Last restocked'],
      garments().flat().map((s) => [s.product, s.colour, coll(s.category), s.code, label(s.size), s.on_shelf, s.held, s.free, s.reorder_level, s.sold, s.price, s.value, s.labelled, s.unlabelled, s.last_restocked ? date(s.last_restocked) : ''])));
    if (!O.canExport()) $('usCsv').hidden = true;
    window.APIRealtime?.register?.('manage_uniform_sales', { refresh: load }, ['inventory', 'commerce']);
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
