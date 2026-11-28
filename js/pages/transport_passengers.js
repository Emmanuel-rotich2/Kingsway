/**
 * Today's passengers: the driver's run sheet. Learners are grouped by stop in
 * route order; one tap records each learner. The server scopes the manifest
 * and the writes to the driver's assigned routes.
 *
 * The manifest is read from a read model that refreshes shortly after a
 * write, so a mark is applied to the row locally once the server accepts it.
 */
(() => {
  'use strict';
  const root = document.getElementById('runSheetPage');
  if (!root) return;
  const O = window.OpsCommon;
  const { esc, data, api, notify, today, hm } = O;
  const $ = (id) => document.getElementById(id);
  const STATUS = { picked_up: ['On the bus', 'is-ok'], dropped_off: ['Dropped off', 'is-ok'], absent: ['Absent', 'is-stop'], not_riding: ['Not riding', 'is-muted'], excused: ['Excused', 'is-muted'], pending: ['Not yet', 'is-warn'] };
  let manifest = { routes: [], students: [] };
  /** Marks made here, kept until the read model has caught up (key: sid|date|trip). */
  const recent = new Map();

  const trip = () => $('rsTrip').value;
  const evening = () => trip() === 'evening_dropoff';
  /** The two choices for this trip: [done, missed]. */
  const choices = () => (evening() ? [['dropped_off', 'Dropped off'], ['not_riding', 'Not riding']] : [['picked_up', 'On the bus'], ['absent', 'Absent']]);
  const name = (s) => s.full_name || `${s.first_name || ''} ${s.last_name || ''}`.trim();
  const status = (s) => s.attendance_status || 'pending';

  function visible() {
    const q = $('rsSearch').value.trim().toLowerCase();
    const route = $('rsRoute').value;
    return manifest.students.filter((s) => (!route || String(s.route_id) === route)
      && (!q || `${name(s)} ${s.admission_no || ''}`.toLowerCase().includes(q)));
  }

  function renderFacts() {
    const list = visible();
    const done = list.filter((s) => ['picked_up', 'dropped_off'].includes(status(s))).length;
    const missed = list.filter((s) => ['absent', 'not_riding', 'excused'].includes(status(s))).length;
    const left = list.length - done - missed;
    $('rsFacts').innerHTML = [
      ['is-ok', `${done} of ${list.length}`, evening() ? 'dropped off' : 'on the bus'],
      [left ? 'is-warn' : '', String(left), 'still to mark'],
      ['', String(missed), evening() ? 'not riding' : 'absent'],
      ['', manifest.routes.map((r) => [r.code || r.name, r.vehicle_registration].filter(Boolean).join(', ')).join('; ') || '—', 'Route and vehicle'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(v)}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
  }

  function row(s) {
    const [label, tone] = STATUS[status(s)] || [status(s), 'is-muted'];
    const [[a, aText], [b, bText]] = choices();
    const phone = String(s.guardian_phone || '').trim();
    return `<li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom" data-sid="${Number(s.student_id)}">
      <div class="me-auto">
        <div class="fw-bold">${esc(name(s))}</div>
        <div class="small text-muted">${esc([s.admission_no, [s.class_name, s.stream_name].filter(Boolean).join(' ')].filter(Boolean).join(', '))}${phone ? `, <a href="tel:${esc(phone.replace(/[^\d+]/g, ''))}">${esc(phone)}</a>` : ''}</div>
      </div>
      <span class="ops-tag ${tone}${status(s) === 'pending' ? '' : ' d-none d-print-inline-block'}">${esc(label)}</span>
      <span class="d-flex gap-2 d-print-none">
        <button class="btn ${status(s) === a ? 'btn-success' : 'btn-outline-success'}" type="button" data-mark="${a}" aria-pressed="${status(s) === a}">${esc(aText)}</button>
        <button class="btn ${status(s) === b ? 'btn-secondary' : 'btn-outline-secondary'}" type="button" data-mark="${b}" aria-pressed="${status(s) === b}">${esc(bText)}</button>
      </span>
    </li>`;
  }

  function render() {
    renderFacts();
    const list = visible();
    if (!manifest.students.length) { $('rsState').hidden = false; $('rsState').textContent = 'No passengers are booked on your route for this day.'; $('rsStops').innerHTML = ''; return; }
    $('rsState').hidden = true;
    if (!list.length) { $('rsStops').innerHTML = '<p class="ops-empty">No learner matches that search.</p>'; return; }
    // Group by stop, in route order; the evening run goes in reverse.
    const groups = new Map();
    list.forEach((s) => {
      const key = `${s.route_id}|${s.stop_sequence ?? 9999}|${s.stop_name || ''}`;
      if (!groups.has(key)) groups.set(key, { name: s.stop_name || 'Stop not set', seq: Number(s.stop_sequence ?? 9999), time: hm(s.arrival_time || s.departure_time), kids: [] });
      groups.get(key).kids.push(s);
    });
    const ordered = [...groups.values()].sort((x, y) => (evening() ? y.seq - x.seq : x.seq - y.seq));
    $('rsStops').innerHTML = ordered.map((g) => {
      const done = g.kids.filter((s) => status(s) !== 'pending').length;
      return `<section class="ops-panel" aria-label="${esc(g.name)}">
        <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
          <h2 class="mb-0">${esc(g.name)}${!evening() && g.time ? ` <span class="text-muted fw-normal">${esc(g.time)}</span>` : ''}</h2>
          <span class="small ${done === g.kids.length ? 'text-success' : 'text-muted'}">${done} of ${g.kids.length} marked</span>
        </div>
        <ul class="list-unstyled mb-0">${g.kids.map(row).join('')}</ul>
      </section>`;
    }).join('');
  }

  async function load() {
    $('rsState').hidden = false;
    $('rsState').textContent = "Loading today's passengers…";
    try {
      manifest = data(await api('/transport/driver-manifest', 'GET', null, { date: $('rsDate').value || today(), trip_session: trip() })) || { routes: [], students: [] };
      manifest.routes ||= []; manifest.students ||= [];
      const now = Date.now();
      manifest.students.forEach((s) => {
        const k = `${s.student_id}|${$('rsDate').value}|${trip()}`;
        const r = recent.get(k);
        if (r && now - r.at < 120000) s.attendance_status = r.status; else recent.delete(k);
      });
      const multi = manifest.routes.length > 1;
      $('rsRouteWrap').hidden = !multi;
      if (multi) {
        const keep = $('rsRoute').value;
        $('rsRoute').innerHTML = '<option value="">All my routes</option>' + manifest.routes.map((r) => `<option value="${Number(r.id)}">${esc(r.code ? `${r.code}: ${r.name}` : r.name)}</option>`).join('');
        $('rsRoute').value = keep;
      } else { $('rsRoute').innerHTML = '<option value=""></option>'; }
      $('rsPrStudent').innerHTML = '<option value="">Not about one learner</option>' + manifest.students.map((s) => `<option value="${Number(s.student_id)}" data-route="${Number(s.route_id)}">${esc(name(s))} (${esc(s.admission_no || '')})</option>`).join('');
      render();
    } catch (e) {
      manifest = { routes: [], students: [] };
      $('rsStops').innerHTML = '';
      renderFacts();
      $('rsState').hidden = false;
      $('rsState').textContent = [401, 403].includes(Number(e?.code)) ? 'Only drivers and the transport office can open the passenger list.' : (e.message || 'The passenger list could not be loaded.');
    }
  }

  async function mark(sid, next, btn) {
    const s = manifest.students.find((x) => Number(x.student_id) === Number(sid));
    if (!s) return;
    const value = status(s) === next ? 'pending' : next; // tap again to undo
    btn.disabled = true;
    try {
      await api('/transport/attendance', 'POST', { date: $('rsDate').value || today(), route_id: Number(s.route_id), trip_session: trip(), status: value, present_student_ids: [Number(s.student_id)] });
      s.attendance_status = value === 'pending' ? null : value;
      recent.set(`${s.student_id}|${$('rsDate').value}|${trip()}`, { status: s.attendance_status, at: Date.now() });
      render();
      root.querySelector(`[data-sid="${Number(sid)}"] [data-mark="${next}"]`)?.focus();
    } catch (e) {
      notify(e.message || 'This could not be saved. Try again.', 'danger');
      btn.disabled = false;
    }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    $('rsDate').value = today();
    $('rsTrip').value = new Date().getHours() >= 12 ? 'evening_dropoff' : 'morning_pickup';
    ['rsTrip', 'rsDate'].forEach((id) => $(id).addEventListener('change', load));
    $('rsRoute').addEventListener('change', render);
    $('rsSearch').addEventListener('input', render);
    $('rsStops').addEventListener('click', (e) => {
      const b = e.target.closest('[data-mark]');
      if (b) mark(b.closest('[data-sid]').dataset.sid, b.dataset.mark, b);
    });
    $('rsPrint').addEventListener('click', () => window.print());
    $('rsCsv').addEventListener('click', () => O.exportCsv(`passengers_${$('rsDate').value}_${trip()}.csv`,
      ['Stop', 'Learner', 'Admission no.', 'Class', 'Guardian phone', 'Status'],
      visible().map((s) => [s.stop_name, name(s), s.admission_no, [s.class_name, s.stream_name].filter(Boolean).join(' '), s.guardian_phone, (STATUS[status(s)] || [status(s)])[0]])));
    if (!O.canExport()) $('rsCsv').hidden = true;

    $('rsProblemBtn').addEventListener('click', () => { $('rsProblemForm').reset(); bootstrap.Modal.getOrCreateInstance($('rsProblemModal')).show(); });
    $('rsProblemForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const desc = $('rsPrDesc').value.trim();
      if (desc.length < 5) { notify('Describe what happened.', 'warning'); return; }
      const opt = $('rsPrStudent').selectedOptions[0];
      try {
        await api('/transport/my-problem', 'POST', {
          incident_type: $('rsPrType').value, description: desc, action_taken: $('rsPrAction').value.trim(),
          urgent: $('rsPrUrgent').checked, student_id: $('rsPrStudent').value || null, route_id: opt?.dataset.route || null,
        });
        bootstrap.Modal.getInstance($('rsProblemModal'))?.hide();
        notify('Problem reported to the transport office.', 'success');
      } catch (err) { notify(err.message || 'The problem could not be reported.', 'danger'); }
    });
    window.APIRealtime?.register?.('transport_passengers', { refresh: () => load() }, ['transport']);
    await load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
