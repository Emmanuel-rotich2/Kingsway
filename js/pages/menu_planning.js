/** Menu planning: the kitchen's week plan and its list of dishes (KitchenService). */
(() => {
  'use strict';
  const root = document.getElementById('menuPlanPage');
  if (!root) return;
  const O = window.OpsCommon;
  const K = window.KitchenCommon;
  const { esc, data, api, notify, date } = O;
  const $ = (id) => document.getElementById(id);
  let week = K.monday();
  let plan = [];
  let dishList = [];
  let canPlan = false;
  let slot = null; // { date, meal, row? }
  let backToSlot = false; // reopen the meal after adding a dish from it

  const modal = (id) => bootstrap.Modal.getOrCreateInstance($(id));

  async function loadDishes() {
    const res = await K.dishes();
    dishList = res.dishes || [];
    canPlan = Boolean(res.can_plan);
    $('mpDishAdd').hidden = !canPlan;
    renderDishes();
  }

  async function loadWeek() {
    $('mpWeek').value = week;
    $('mpRange').textContent = `Week of ${date(week)} to ${date(K.addDays(week, 6))}`;
    try {
      const res = await K.plans(week, K.addDays(week, 6));
      plan = res.plans || [];
      canPlan = Boolean(res.can_plan);
      renderWeek();
    } catch (e) {
      $('mpGrid').innerHTML = `<p class="text-danger">${esc(e.message || 'The week could not be loaded.')}</p>`;
    }
  }

  function renderWeek() {
    $('mpCopy').hidden = !canPlan;
    const head = `<div class="ops-week__row ops-week__headrow" aria-hidden="true"><span></span>${K.MEALS.map(([, l]) => `<span class="ops-week__head">${esc(l)}</span>`).join('')}</div>`;
    const days = Array.from({ length: 7 }, (_, i) => K.addDays(week, i));
    $('mpGrid').innerHTML = head + days.map((d) => `
      <div class="ops-week__row${d === O.today() ? ' is-today' : ''}">
        <div class="ops-week__day">${esc(K.dayName(d))}<small>${esc(date(d))}</small></div>
        ${K.MEALS.map(([m, l]) => {
          const rows = plan.filter((p) => p.plan_date === d && p.meal_type === m);
          return `<div class="ops-slot" role="group" aria-label="${esc(`${K.dayName(d)} ${l}`)}"><span class="ops-slot__label">${esc(l)}</span>
            ${rows.map((p) => `<button type="button" class="ops-dish${p.status === 'cancelled' ? ' is-cancelled' : ''}" data-plan="${Number(p.id)}" ${canPlan ? '' : 'disabled'}>
              ${esc(p.menu_item || 'Dish not named')}<small>${esc(O.num(p.planned_servings))} servings${p.status !== 'planned' ? `, ${esc(K.STATUS[p.status]?.[0] || p.status)}` : ''}</small></button>`).join('')}
            ${canPlan ? `<button type="button" class="ops-add" data-add="${d}|${m}" aria-label="Add a dish to ${esc(`${K.dayName(d)} ${l}`)}">+ Add</button>` : (rows.length ? '' : '<span class="small text-muted">Nothing planned</span>')}
          </div>`;
        }).join('')}
      </div>`).join('');
    const empty = !plan.length;
    $('mpHint').textContent = empty && canPlan
      ? (dishList.length ? 'Nothing is planned for this week yet. Use "+ Add" on a meal, or copy last week.' : 'Start by adding the dishes the kitchen cooks on the Dishes tab.')
      : '';
  }

  function dishOptions(selected, meal) {
    const active = dishList.filter((d) => d.status === 'active' || Number(d.id) === Number(selected));
    const sorted = [...active].sort((a, b) => (a.meal_type === meal ? -1 : 0) - (b.meal_type === meal ? -1 : 0) || a.name.localeCompare(b.name));
    return '<option value="">Choose a dish</option>' + sorted.map((d) => `<option value="${Number(d.id)}" ${Number(d.id) === Number(selected) ? 'selected' : ''}>${esc(d.name)}</option>`).join('');
  }

  function openSlot(d, m, row = null) {
    const existing = Boolean(row?.id);
    slot = { date: d, meal: m, row: existing ? row : null };
    $('mpPlanTitle').textContent = existing ? 'Change the dish' : 'Add a dish';
    $('mpPlanWhen').textContent = `${K.dayName(d)} ${date(d)}, ${K.MEAL[m]}`;
    $('mpPlanDish').innerHTML = dishOptions(row?.menu_item_id, m);
    const sameMealBefore = plan.filter((p) => p.meal_type === m && p.planned_servings).map((p) => p.planned_servings);
    $('mpPlanServings').value = existing ? row.planned_servings : (sameMealBefore[sameMealBefore.length - 1] || '');
    $('mpPlanNotes').value = row?.notes || '';
    $('mpPlanRemove').hidden = !existing;
    modal('mpPlanModal').show();
  }

  function renderDishes() {
    const q = $('mpDishQ').value.trim().toLowerCase();
    const list = dishList.filter((d) => !q || d.name.toLowerCase().includes(q));
    $('mpDishBody').innerHTML = list.length ? list.map((d) => `<tr>
      <td class="fw-semibold">${esc(d.name)}</td><td>${esc(K.MEAL[d.meal_type] || d.meal_type)}</td><td class="small">${esc(d.description || '')}</td>
      <td>${d.status === 'active' ? '<span class="ops-tag is-ok">Yes</span>' : '<span class="ops-tag is-muted">No</span>'}</td>
      <td class="d-print-none text-end">${canPlan ? `<button class="btn btn-outline-secondary btn-sm" type="button" data-dish="${Number(d.id)}">Edit</button>` : ''}</td></tr>`).join('')
      : `<tr><td colspan="5" class="text-muted p-3">${dishList.length ? 'No dish matches that search.' : 'No dishes yet. Add the dishes the kitchen cooks, then plan the week from them.'}</td></tr>`;
  }

  function openDish(d = null) {
    $('mpDishTitle').textContent = d ? 'Edit dish' : 'Add a dish';
    $('mpDishId').value = d?.id || '';
    $('mpDishName').value = d?.name || '';
    $('mpDishMeal').value = d?.meal_type || slot?.meal || 'lunch';
    $('mpDishDesc').value = d?.description || '';
    $('mpDishActive').checked = d ? d.status === 'active' : true;
    modal('mpDishModal').show();
  }

  function wire() {
    O.tabs($('mpTabs'));
    if (/route=manage_menus/.test(location.search)) $('mpTabDishes').click();
    $('mpPrev').addEventListener('click', () => { week = K.addDays(week, -7); loadWeek(); });
    $('mpNext').addEventListener('click', () => { week = K.addDays(week, 7); loadWeek(); });
    $('mpThis').addEventListener('click', () => { week = K.monday(); loadWeek(); });
    $('mpWeek').addEventListener('change', () => { if ($('mpWeek').value) { week = K.monday($('mpWeek').value); loadWeek(); } });
    $('mpGrid').addEventListener('click', (e) => {
      const add = e.target.closest('[data-add]');
      if (add) { const [d, m] = add.dataset.add.split('|'); openSlot(d, m); return; }
      const p = e.target.closest('[data-plan]');
      if (p) { const row = plan.find((x) => Number(x.id) === Number(p.dataset.plan)); if (row) openSlot(row.plan_date, row.meal_type, row); }
    });
    $('mpPlanForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!$('mpPlanDish').value) { notify('Choose a dish.', 'warning'); return; }
      try {
        await api('/catering/plans', 'POST', { id: slot.row?.id || null, plan_date: slot.date, meal_type: slot.meal, menu_item_id: $('mpPlanDish').value, planned_servings: $('mpPlanServings').value || 0, notes: $('mpPlanNotes').value.trim() });
        modal('mpPlanModal').hide();
        notify('Menu saved.', 'success');
        loadWeek();
      } catch (err) { notify(err.message || 'The menu could not be saved.', 'danger'); }
    });
    $('mpPlanRemove').addEventListener('click', async () => {
      if (!slot?.row) return;
      try {
        await api(`/catering/plans-remove/${Number(slot.row.id)}`, 'POST', {});
        modal('mpPlanModal').hide();
        notify('Dish removed from the meal.', 'success');
        loadWeek();
      } catch (err) { notify(err.message || 'The dish could not be removed.', 'danger'); }
    });
    $('mpPlanNewDish').addEventListener('click', () => { modal('mpPlanModal').hide(); backToSlot = true; openDish(); });
    $('mpCopy').addEventListener('click', async () => {
      try {
        const res = data(await api('/catering/plans-copy-week', 'POST', { from: K.addDays(week, -7), to: week }));
        notify(res.copied ? `${res.copied} dish${res.copied === 1 ? '' : 'es'} copied from last week.` : 'Nothing to copy: last week is empty or these meals are already planned.', res.copied ? 'success' : 'info');
        loadWeek();
      } catch (err) { notify(err.message || 'Last week could not be copied.', 'danger'); }
    });

    $('mpDishQ').addEventListener('input', renderDishes);
    $('mpDishAdd').addEventListener('click', () => { backToSlot = false; openDish(); });
    $('mpDishBody').addEventListener('click', (e) => { const b = e.target.closest('[data-dish]'); if (b) openDish(dishList.find((d) => Number(d.id) === Number(b.dataset.dish))); });
    $('mpDishForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!$('mpDishName').value.trim()) { notify('Give the dish a name.', 'warning'); return; }
      try {
        const saved = data(await api('/catering/dishes', 'POST', { id: $('mpDishId').value || null, name: $('mpDishName').value.trim(), meal_type: $('mpDishMeal').value, description: $('mpDishDesc').value.trim(), status: $('mpDishActive').checked ? 'active' : 'inactive' }));
        modal('mpDishModal').hide();
        notify('Dish saved.', 'success');
        await loadDishes();
        renderWeek();
        const reopen = backToSlot; backToSlot = false;
        if (reopen && slot) openSlot(slot.date, slot.meal, slot.row ? { ...slot.row, menu_item_id: saved.id } : { menu_item_id: saved.id, planned_servings: '', notes: '' });
      } catch (err) { notify(err.message || 'The dish could not be saved.', 'danger'); }
    });

    $('mpPrint').addEventListener('click', () => window.print());
    $('mpCsv').addEventListener('click', () => {
      if ($('mpTabDishes').getAttribute('aria-selected') === 'true') {
        O.exportCsv('kitchen_dishes.csv', ['Dish', 'Usually served at', 'Notes', 'In use'], dishList.map((d) => [d.name, K.MEAL[d.meal_type], d.description, d.status === 'active' ? 'Yes' : 'No']));
      } else {
        O.exportCsv(`menu_week_${week}.csv`, ['Date', 'Day', 'Meal', 'Dish', 'Servings', 'Status', 'Notes'],
          plan.map((p) => [p.plan_date, K.dayName(p.plan_date), K.MEAL[p.meal_type], p.menu_item, p.planned_servings, K.STATUS[p.status]?.[0] || p.status, p.notes]));
      }
    });
    if (!O.canExport()) $('mpCsv').hidden = true;
  }

  async function init() {
    await window.AuthContext?.ready?.();
    wire();
    try { await loadDishes(); } catch (e) { notify(e.message || 'The dishes could not be loaded.', 'danger'); }
    await loadWeek();
    window.APIRealtime?.register?.('menu_planning', { refresh: () => loadWeek() }, ['catering']);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
