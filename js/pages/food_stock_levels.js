/** Stock count: the count sheet; differences go to the bin card (FoodStoreService::count). */
(() => {
  'use strict';
  const root = document.getElementById('stockCountPage');
  if (!root) return;
  const O = window.OpsCommon;
  const F = window.FoodStore;
  const { esc, num, kes, notify, date } = O;
  const $ = (id) => document.getElementById(id);
  const found = new Map(); // item id -> { counted, note }

  const items = () => F.active().filter((i) => !$('scCat').value || String(i.category_id) === $('scCat').value)
    .sort((a, b) => (a.location || '').localeCompare(b.location || '') || a.name.localeCompare(b.name));

  function diffCell(i) {
    const f = found.get(i.id);
    if (!f || f.counted === '') return '';
    const d = Number(f.counted) - i.on_hand;
    if (!d) return '<span class="ops-diff">Correct</span>';
    return `<span class="ops-diff ${d < 0 ? 'is-short' : 'is-over'}">${d < 0 ? 'Short' : 'Extra'} ${esc(num(Math.abs(d)))}</span><div class="small text-muted">${esc(kes(Math.abs(d) * i.unit_cost))}</div>`;
  }

  function render() {
    const m = F.state.canManage;
    $('scBody').innerHTML = items().map((i) => {
      const f = found.get(i.id) || { counted: '', note: '' };
      return `<tr data-id="${i.id}"><td class="fw-semibold">${esc(i.name)}<div class="small text-muted fw-normal">${esc([i.code, i.location].filter(Boolean).join(', '))}</div></td>
        <td class="text-nowrap">${esc(F.qty(i))}</td>
        <td><input class="form-control ops-count-input" type="number" min="0" inputmode="numeric" data-f="counted" value="${esc(f.counted)}" aria-label="Found, ${esc(i.name)} (${esc(i.unit)})" ${m ? '' : 'disabled'}></td>
        <td data-diff>${diffCell(i)}</td>
        <td class="d-none d-md-table-cell"><input class="form-control form-control-sm" data-f="note" maxlength="200" value="${esc(f.note)}" aria-label="Note, ${esc(i.name)}" ${m ? '' : 'disabled'}></td>
        <td class="small text-muted text-nowrap d-none d-lg-table-cell">${i.last_count ? esc(date(i.last_count)) : 'Never'}</td></tr>`;
    }).join('') || '<tr><td colspan="6" class="text-muted p-3">The store has no food items in this category.</td></tr>';
    summary();
  }

  function summary() {
    const list = items();
    const counted = list.filter((i) => (found.get(i.id)?.counted ?? '') !== '');
    const off = counted.filter((i) => Number(found.get(i.id).counted) !== i.on_hand);
    const loss = off.reduce((s, i) => s + Math.min(0, Number(found.get(i.id).counted) - i.on_hand) * i.unit_cost, 0);
    $('scFacts').innerHTML = [
      ['', `${num(counted.length)} of ${num(list.length)}`, 'counted'],
      [off.length ? 'is-warn' : '', num(off.length), 'differ from the book'],
      [loss ? 'is-stop' : '', kes(-loss), 'short, at cost'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
    $('scSave').disabled = !counted.length;
    $('scNote').textContent = counted.length ? `Saving corrects the book for ${off.length} item${off.length === 1 ? '' : 's'} and marks ${counted.length} as counted on ${date($('scDate').value)}.` : '';
  }

  async function save() {
    const counts = [...found].filter(([, f]) => f.counted !== '').map(([id, f]) => ({ item_id: id, counted: Number(f.counted), note: f.note }));
    $('scSave').disabled = true;
    try {
      const res = O.data(await O.api('/catering/store-count', 'POST', { date: $('scDate').value, counts }));
      found.clear();
      notify(`Count saved: ${res.counted} counted, ${res.corrected} corrected.`, 'success');
      await F.load();
      render();
    } catch (e) { notify(e.message || 'The count could not be saved.', 'danger'); summary(); }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    F.wire();
    $('scDate').value = O.today(); $('scDate').max = O.today();
    try { await F.load(); } catch (e) { $('scBody').innerHTML = `<tr><td colspan="6" class="text-muted p-3">${esc(e.message || 'The food store could not be loaded.')}</td></tr>`; return; }
    $('scCat').innerHTML += F.state.categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    $('scCat').addEventListener('change', render);
    $('scDate').addEventListener('change', summary);
    $('scBody').addEventListener('input', (e) => {
      const tr = e.target.closest('[data-id]');
      if (!tr) return;
      const id = Number(tr.dataset.id);
      const f = found.get(id) || { counted: '', note: '' };
      f[e.target.dataset.f] = e.target.value;
      found.set(id, f);
      tr.querySelector('[data-diff]').innerHTML = diffCell(F.byId(id));
      summary();
    });
    $('scSave').addEventListener('click', save);
    $('scPrint').addEventListener('click', () => window.print());
    $('scBlank').addEventListener('click', () => {
      root.classList.add('is-blank-sheet');
      window.addEventListener('afterprint', () => root.classList.remove('is-blank-sheet'), { once: true });
      window.print();
    });
    $('scCsv').addEventListener('click', () => O.exportCsv(`stock_count_${$('scDate').value}.csv`, ['Item', 'Code', 'Kept in', 'Unit', 'Book says', 'Found', 'Difference', 'Note'],
      items().map((i) => { const f = found.get(i.id); const c = f && f.counted !== '' ? Number(f.counted) : ''; return [i.name, i.code, i.location, i.unit, i.on_hand, c, c === '' ? '' : c - i.on_hand, f?.note || '']; })));
    if (!O.canExport()) $('scCsv').hidden = true;
    window.addEventListener('beforeunload', (e) => { if ([...found.values()].some((f) => f.counted !== '')) e.preventDefault(); });
    render();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
