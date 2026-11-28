/** Shared helpers for the kitchen pages (menu planning, today's menu, weekly menu). */
(function () {
  'use strict';
  if (window.KitchenCommon) return;
  const O = window.OpsCommon;
  const MEALS = [['breakfast', 'Breakfast'], ['snack', 'Tea break'], ['lunch', 'Lunch'], ['dinner', 'Supper']];
  const MEAL = Object.fromEntries(MEALS);
  const STATUS = { planned: ['Planned', 'is-muted'], prepared: ['Cooked', 'is-warn'], served: ['Served', 'is-ok'], cancelled: ['Cancelled', 'is-stop'] };
  const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
  const addDays = (ymd, n) => { const d = new Date(`${ymd}T12:00:00`); d.setDate(d.getDate() + n); return iso(d); };
  const monday = (ymd = O.today()) => { const d = new Date(`${ymd}T12:00:00`); d.setDate(d.getDate() - ((d.getDay() + 6) % 7)); return iso(d); };
  const dayName = (ymd, style = 'long') => new Date(`${ymd}T12:00:00`).toLocaleDateString('en-KE', { weekday: style });
  const plans = async (from, to) => O.data(await O.api('/catering/plans', 'GET', null, { from, to })) || { plans: [] };
  const dishes = async (active = false) => O.data(await O.api('/catering/dishes', 'GET', null, active ? { active: 1 } : {})) || { dishes: [] };
  window.KitchenCommon = { MEALS, MEAL, STATUS, iso, addDays, monday, dayName, plans, dishes };
})();
