/** Weekly menu: one menu board per day, read-only and printable. */
(() => {
  'use strict';
  const root = document.getElementById('weeklyMenuPage');
  if (!root) return;
  const O = window.OpsCommon;
  const K = window.KitchenCommon;
  const { esc, date } = O;
  const $ = (id) => document.getElementById(id);
  let week = K.monday();
  let rows = [];

  function render() {
    const days = Array.from({ length: $('wmWeekend').checked ? 7 : 5 }, (_, i) => K.addDays(week, i));
    $('wmTitle').textContent = `Weekly menu: ${date(week)} to ${date(days[days.length - 1])}`;
    $('wmDays').innerHTML = days.map((d) => {
      const live = rows.filter((r) => r.plan_date === d && r.status !== 'cancelled');
      const meals = K.MEALS.filter(([m]) => live.some((r) => r.meal_type === m));
      return `<section class="ops-board${d === O.today() ? ' is-today' : ''}" aria-label="${esc(K.dayName(d))}">
        <h2>${esc(K.dayName(d))} <span class="fw-normal" style="font-size:0.9rem">${esc(date(d))}</span></h2>
        ${meals.length ? `<ol class="ops-line">${meals.map(([m, l]) => `<li><span class="ops-line__t">${esc(l)}</span><span class="ops-line__dot" aria-hidden="true"></span>
          <span>${live.filter((r) => r.meal_type === m).map((r) => `<span class="ops-line__name d-block">${esc(r.menu_item || 'Dish')}</span>`).join('')}</span></li>`).join('')}</ol>`
        : '<p class="mb-0">Not planned yet.</p>'}
      </section>`;
    }).join('');
  }

  async function load() {
    try { rows = (await K.plans(week, K.addDays(week, 6))).plans || []; render(); }
    catch (e) { $('wmDays').innerHTML = `<p class="text-danger">${esc(e.message || 'The menu could not be loaded.')}</p>`; }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    $('wmPrev').addEventListener('click', () => { week = K.addDays(week, -7); load(); });
    $('wmNext').addEventListener('click', () => { week = K.addDays(week, 7); load(); });
    $('wmThis').addEventListener('click', () => { week = K.monday(); load(); });
    $('wmWeekend').addEventListener('change', render);
    $('wmPrint').addEventListener('click', () => window.print());
    $('wmCsv').addEventListener('click', () => O.exportCsv(`weekly_menu_${week}.csv`, ['Date', 'Day', 'Meal', 'Dish'],
      rows.filter((r) => r.status !== 'cancelled').map((r) => [r.plan_date, K.dayName(r.plan_date), K.MEAL[r.meal_type], r.menu_item])));
    if (!O.canExport()) $('wmCsv').hidden = true;
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
