/**
 * Route board. Drivers: the route they drive (/transport/my-route).
 * Director / School Administrator: pick any route (/transport/all-routes,
 * /transport/route-board/{id}).
 */
(() => {
  'use strict';
  const root = document.getElementById('myRoutesPage');
  if (!root) return;
  const O = window.OpsCommon;
  const { esc, data, api, label, date, hm, num } = O;
  const $ = (id) => document.getElementById(id);
  const INCIDENT = { accident: 'Accident', late_pickup: 'Late pick-up', late_dropoff: 'Late drop-off', wrong_stop: 'Wrong stop', behavior: 'Behaviour', medical: 'Medical', vehicle_breakdown: 'Breakdown', other: 'Other' };
  let board = null;

  // Drivers see their own route; everyone else allowed here gets the overview.
  const isDriver = () => (window.AuthContext?.getRoles?.() || []).some((r) => String(r?.name || r).toLowerCase().includes('driver'));

  function state(text) {
    $('mrState').hidden = false;
    $('mrBody').hidden = true;
    $('mrState').textContent = text;
  }

  /** my-route rows come flat; route-board rows carry vehicles[]. */
  function normalise(r) {
    if (!r.vehicles) {
      r.vehicles = r.vehicle_id ? [{ vehicle_id: r.vehicle_id, registration_number: r.registration_number, capacity: r.vehicle_capacity, status: r.vehicle_status, direction: r.direction }] : [];
    }
    return r;
  }

  function render(r) {
    board = normalise(r);
    $('mrState').hidden = true;
    $('mrBody').hidden = false;
    const stops = r.stops || [];
    $('mrBlind').innerHTML = `<span class="ops-blind__code">${esc(r.code || '')}</span><span class="ops-blind__name">${esc(r.name || '')}</span>
      <span class="ops-blind__via">${esc([r.start_point, r.end_point].filter(Boolean).join(' to '))}${stops.length > 2 ? `, via ${esc(stops.slice(1, -1).slice(0, 3).map((s) => s.name).join(', '))}` : ''}</span>`;
    const facts = [
      [hm(r.morning_departure) || '—', 'Morning departure'],
      [hm(r.afternoon_departure) || '—', 'Afternoon departure'],
      [r.estimated_duration ? `${num(r.estimated_duration)} min` : '—', 'Usual journey'],
      [r.distance ? `${num(r.distance)} km` : '—', 'Distance'],
      [String(stops.length), stops.length === 1 ? 'Stop' : 'Stops'],
    ];
    if (r.passenger_count !== undefined) facts.push([num(r.passenger_count), 'Learners this month']);
    $('mrFacts').innerHTML = facts.map(([v, l]) => `<li><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
    renderStops();

    $('mrVehicles').innerHTML = r.vehicles.length ? r.vehicles.map((v) => `
      <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
        <span class="ops-plate" style="font-size:1.4rem">${esc(String(v.registration_number || '').toUpperCase())}</span>
        <div><div class="fw-semibold">${esc(v.direction === 'dropoff' ? 'Drop-off' : v.direction === 'pickup' ? 'Pick-up' : '')}${v.capacity ? `, ${esc(num(v.capacity))} seats` : ''}</div>
        <div class="small text-muted">${esc(v.driver_name || '')}${v.driver_phone ? `, ${esc(v.driver_phone)}` : ''}</div>
        ${v.status && v.status !== 'active' ? `<span class="ops-tag is-warn">${esc(label(v.status))}</span>` : ''}</div>
      </div>`).join('') : '<p class="ops-empty">No vehicle is on this route.</p>';

    const sched = r.schedules || [];
    $('mrSchedules').innerHTML = sched.length
      ? `<ul class="list-unstyled mb-0">${sched.map((s) => `<li class="d-flex justify-content-between py-1 border-bottom"><span>${esc(s.date ? date(s.date) : 'Every school day')}</span><strong>${esc(hm(s.pickup_time))}</strong></li>`).join('')}</ul>`
      : '<p class="ops-empty">The route runs on its usual times. No extra trips are scheduled.</p>';

    const inc = r.recent_incidents || [];
    $('mrIncidents').innerHTML = inc.length
      ? `<ul class="list-unstyled mb-0">${inc.map((i) => `<li class="py-1 border-bottom"><div class="d-flex justify-content-between gap-2"><strong>${esc(INCIDENT[i.incident_type] || label(i.incident_type))}</strong><span class="small text-muted">${esc(String(i.incident_datetime).slice(0, 16))}</span></div><div class="small">${esc(String(i.description || '').slice(0, 160))}</div>${Number(i.escalated) ? '<span class="ops-tag is-stop">Escalated</span>' : ''}</li>`).join('')}</ul>`
      : '<p class="ops-empty">None recorded.</p>';
  }

  function renderStops() {
    const r = board;
    const pm = root.querySelector('input[name="mrDir"]:checked')?.value === 'pm';
    const stops = [...(r.stops || [])].sort((a, b) => Number(a.sequence) - Number(b.sequence));
    if (!stops.length) { $('mrStops').innerHTML = '<li class="d-block ops-empty">No stops have been set for this route. The transport office adds them.</li>'; return; }
    const list = pm ? [{ name: 'School', meta: 'Leave school', t: hm(r.afternoon_departure) }, ...stops.reverse().map((s) => ({ name: s.name, meta: s.location, t: '' }))]
      : stops.map((s) => ({ name: s.name, meta: s.location, t: hm(s.arrival_time || s.departure_time) }));
    $('mrStops').innerHTML = list.map((s) => `<li><span class="ops-line__t">${esc(s.t || '')}</span><span class="ops-line__dot" aria-hidden="true"></span>
      <span><span class="ops-line__name">${esc(s.name)}</span>${s.meta && !/^-?\d+(\.\d+)?,\s*-?\d+(\.\d+)?$/.test(s.meta) ? `<span class="d-block ops-line__meta">${esc(s.meta)}</span>` : ''}</span></li>`).join('');
  }

  async function loadOverview() {
    $('mrTitle').textContent = 'Routes overview';
    $('mrLede').textContent = 'Every route: its stops and times, the vehicle and driver on it, and recent incidents.';
    $('mrPassengers').hidden = true;
    let routes = [];
    try { routes = data(await api('/transport/all-routes')) || []; } catch (e) { state(e.message || 'Routes could not be loaded.'); return; }
    if (!routes.length) { state('No routes have been set up yet.'); return; }
    $('mrPickWrap').hidden = false;
    $('mrPick').innerHTML = routes.map((r) => `<option value="${Number(r.id)}">${esc(r.code ? `${r.code}: ${r.name}` : r.name)}</option>`).join('');
    const wanted = new URLSearchParams(location.search).get('route_id');
    if (wanted && routes.some((r) => String(r.id) === wanted)) $('mrPick').value = wanted;
    $('mrPick').addEventListener('change', openBoard);
    await openBoard();
  }

  async function openBoard() {
    try { render(data(await api(`/transport/route-board/${Number($('mrPick').value)}`))); }
    catch (e) { state(e.message || 'The route could not be opened.'); }
  }

  async function loadDriver() {
    try {
      const r = data(await api('/transport/my-route'));
      if (!r || !r.id) { state('No route is assigned to your vehicle yet. Ask the transport office.'); return; }
      render(r);
    } catch (e) { state(e.message || 'Your route could not be loaded.'); }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    root.addEventListener('change', (e) => { if (e.target.name === 'mrDir' && board) renderStops(); });
    $('mrPrint').addEventListener('click', () => window.print());
    $('mrStopsCsv').addEventListener('click', () => {
      if (!board) return;
      O.exportCsv(`route_${String(board.code || board.id).replace(/\W+/g, '_')}_stops.csv`, ['Order', 'Stop', 'Arrival', 'Departure', 'Location'],
        (board.stops || []).map((s) => [s.sequence, s.name, hm(s.arrival_time), hm(s.departure_time), s.location]));
    });
    if (!O.canExport()) $('mrStopsCsv').hidden = true;
    if (isDriver()) await loadDriver(); else await loadOverview();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
