/** Today's menu: the day's menu board and the kitchen record (KitchenService). */
(() => {
  'use strict';
  const root = document.getElementById('todaysMenuPage');
  if (!root) return;
  const O = window.OpsCommon;
  const K = window.KitchenCommon;
  const { esc, api, data, notify, num, date } = O;
  const $ = (id) => document.getElementById(id);
  let rows = [];
  let canPlan = false;
  let current = null;

  async function load() {
    const day = $('tmDay').value || O.today();
    $('tmBoardTitle').textContent = day === O.today() ? `Menu for today, ${K.dayName(day)}` : `Menu for ${K.dayName(day)} ${date(day)}`;
    try {
      const res = await K.plans(day, day);
      rows = res.plans || [];
      canPlan = Boolean(res.can_plan);
      render();
    } catch (e) {
      $('tmBoard').innerHTML = `<li class="d-block">${esc(e.message || 'The menu could not be loaded.')}</li>`;
      $('tmBody').innerHTML = '';
    }
  }

  function render() {
    const live = rows.filter((r) => r.status !== 'cancelled');
    $('tmBoard').innerHTML = K.MEALS.filter(([m]) => live.some((r) => r.meal_type === m)).map(([m, l]) => {
      const dishes = live.filter((r) => r.meal_type === m);
      return `<li><span class="ops-line__t">${esc(l)}</span><span class="ops-line__dot" aria-hidden="true"></span>
        <span>${dishes.map((d) => `<span class="ops-line__name d-block">${esc(d.menu_item || 'Dish')}</span>${d.description ? `<span class="ops-line__meta d-block">${esc(d.description)}</span>` : ''}`).join('')}</span></li>`;
    }).join('') || '<li class="d-block">Nothing is on the menu for this day yet.</li>';

    const planned = live.reduce((s, r) => s + r.planned_servings, 0);
    const served = live.reduce((s, r) => s + r.actual_servings, 0);
    const cooked = live.reduce((s, r) => s + r.prepared_quantity, 0);
    const pending = live.filter((r) => r.status !== 'served').length;
    $('tmFacts').innerHTML = [
      ['', num(planned), 'servings planned'],
      ['', num(cooked), 'cooked'],
      ['is-ok', num(served), 'served'],
      [pending ? 'is-warn' : '', String(pending), pending === 1 ? 'dish still to record' : 'dishes still to record'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');

    $('tmBody').innerHTML = rows.length ? rows.map((r) => {
      const [st, tone] = K.STATUS[r.status] || [r.status, 'is-muted'];
      return `<tr><td>${esc(K.MEAL[r.meal_type] || r.meal_type)}</td><td class="fw-semibold">${esc(r.menu_item || 'Dish')}${r.notes ? `<div class="small text-muted fw-normal">${esc(r.notes)}</div>` : ''}</td>
        <td>${esc(num(r.planned_servings))}</td><td>${r.prepared_quantity ? esc(num(r.prepared_quantity)) : ''}</td><td>${r.actual_servings ? esc(num(r.actual_servings)) : ''}</td>
        <td><span class="ops-tag ${tone}">${esc(st)}</span></td>
        <td class="d-print-none text-end">${canPlan ? `<button class="btn btn-sm ${r.status === 'served' ? 'btn-outline-secondary' : 'btn-ops'}" type="button" data-rec="${Number(r.id)}">${r.status === 'planned' ? 'Record' : 'Change'}</button>` : ''}</td></tr>`;
    }).join('') : `<tr><td colspan="7" class="text-muted p-3">No dishes are planned for this day.${canPlan ? ' Plan them on <a href="home.php?route=menu_planning">Menu planning</a>.' : ''}</td></tr>`;
  }

  function openRecord(r) {
    current = r;
    $('tmRecModalTitle').textContent = `Record: ${r.menu_item || 'dish'}`;
    $('tmRecWhat').textContent = `${K.MEAL[r.meal_type]}, ${num(r.planned_servings)} servings planned`;
    const next = r.status === 'planned' ? 'prepared' : r.status === 'prepared' ? 'served' : r.status;
    root.querySelector(`input[name="tmRecStatus"][value="${next}"]`).checked = true;
    $('tmRecPrepared').value = r.prepared_quantity || r.planned_servings || '';
    $('tmRecServed').value = r.actual_servings || '';
    $('tmRecNotes').value = r.notes || '';
    bootstrap.Modal.getOrCreateInstance($('tmRecModal')).show();
  }

  async function init() {
    await window.AuthContext?.ready?.();
    $('tmDay').value = O.today();
    $('tmDay').addEventListener('change', load);
    $('tmBody').addEventListener('click', (e) => { const b = e.target.closest('[data-rec]'); if (b) openRecord(rows.find((r) => Number(r.id) === Number(b.dataset.rec))); });
    $('tmRecForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const status = root.querySelector('input[name="tmRecStatus"]:checked')?.value;
      if (status === 'served' && !Number($('tmRecServed').value)) { notify('Enter how many servings were served.', 'warning'); $('tmRecServed').focus(); return; }
      try {
        data(await api(`/catering/plans-record/${Number(current.id)}`, 'POST', { status, prepared_quantity: $('tmRecPrepared').value, actual_servings: $('tmRecServed').value, notes: $('tmRecNotes').value.trim() }));
        bootstrap.Modal.getInstance($('tmRecModal'))?.hide();
        notify('Kitchen record saved.', 'success');
        load();
      } catch (err) { notify(err.message || 'The record could not be saved.', 'danger'); }
    });
    $('tmPrint').addEventListener('click', () => window.print());
    $('tmCsv').addEventListener('click', () => O.exportCsv(`kitchen_record_${$('tmDay').value}.csv`, ['Date', 'Meal', 'Dish', 'Planned', 'Cooked', 'Served', 'Status', 'Notes'],
      rows.map((r) => [r.plan_date, K.MEAL[r.meal_type], r.menu_item, r.planned_servings, r.prepared_quantity, r.actual_servings, K.STATUS[r.status]?.[0] || r.status, r.notes])));
    if (!O.canExport()) $('tmCsv').hidden = true;
    window.APIRealtime?.register?.('todays_menu', { refresh: () => load() }, ['catering']);
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
