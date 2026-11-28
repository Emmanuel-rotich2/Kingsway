/**
 * Boarding roll call: one register for every dormitory roll call. The server
 * limits dormitories to the ones the user may mark, and allows saving for
 * today only. Learners on an approved exeat are marked away and locked.
 */
(() => {
  'use strict';
  const root = document.getElementById('rollCallPage');
  if (!root) return;
  const O = window.OpsCommon;
  const { esc, data, api, notify, today, num } = O;
  const $ = (id) => document.getElementById(id);
  const MARKS = [['present', 'In', 'btn-success', 'btn-outline-success'], ['absent', 'Absent', 'btn-danger', 'btn-outline-danger'], ['sick_bay', 'Sick bay', 'btn-warning', 'btn-outline-secondary']];
  const LABEL = { present: 'In', absent: 'Absent', sick_bay: 'Sick bay', permission: 'Away on exeat', unknown: 'Not counted' };
  let dorms = [];
  let sessions = [];
  let learners = [];
  let marks = new Map(); // student id -> status
  let saved = new Map();
  const presetCode = root.dataset.sessionCode || '';

  const rows = (r) => { const p = data(r); return Array.isArray(p) ? p : (Array.isArray(p?.data) ? p.data : []); };
  const name = (s) => `${s.first_name || ''} ${s.last_name || ''}`.trim() || s.student_name || '—';
  const away = (s) => Boolean(s.permission_id);
  const isToday = () => $('rcDate').value === today();

  function state(text) {
    $('rcState').hidden = !text;
    $('rcState').textContent = text || '';
    $('rcBody').hidden = Boolean(text);
  }

  function pickSession() {
    if (presetCode) {
      const s = sessions.find((x) => x.code === presetCode);
      if (s) return s.id;
    }
    const day = new Date().getDay();
    const hour = new Date().getHours();
    const code = (day === 0 || day === 6) && sessions.some((x) => x.code === 'WEEKEND_ROLL_CALL') ? 'WEEKEND_ROLL_CALL' : hour < 12 ? 'MORNING_ROLL_CALL' : 'NIGHT_ROLL_CALL';
    return (sessions.find((x) => x.code === code) || sessions[0])?.id;
  }

  async function loadRefs() {
    try {
      const [d, s] = await Promise.all([api('/attendance/dormitories'), api('/attendance/sessions', 'GET', null, { type: 'boarding' })]);
      dorms = rows(d);
      sessions = rows(s).filter((x) => !x.type || String(x.type).toLowerCase() === 'boarding');
    } catch (e) { state(e.message || 'Your dormitories could not be loaded.'); return false; }
    if (!dorms.length) { state('No dormitory is assigned to you. Ask the boarding master or the school office.'); return false; }
    if (!sessions.length) { state('No boarding roll call is set up. The school office sets these under attendance sessions.'); return false; }
    $('rcDorm').innerHTML = dorms.map((x) => `<option value="${Number(x.id)}">${esc(x.name)}</option>`).join('');
    $('rcSession').innerHTML = sessions.map((x) => `<option value="${Number(x.id)}">${esc(x.name)}${x.start_time ? ` (${esc(String(x.start_time).slice(0, 5))})` : ''}</option>`).join('');
    $('rcSession').value = String(pickSession());
    return true;
  }

  async function loadRoster() {
    state('Loading the register…');
    try {
      const p = data(await api('/attendance/dormitory-students', 'GET', null, { dormitory_id: $('rcDorm').value, session_id: $('rcSession').value, date: $('rcDate').value }));
      learners = Array.isArray(p?.students) ? p.students : [];
    } catch (e) { state(e.message || 'The register could not be loaded.'); return; }
    marks = new Map(); saved = new Map();
    learners.forEach((s) => {
      const st = away(s) ? 'permission' : (['present', 'absent', 'sick_bay', 'permission'].includes(String(s.current_status)) ? String(s.current_status) : 'unknown');
      marks.set(Number(s.id), st);
      saved.set(Number(s.id), st);
    });
    const sess = sessions.find((x) => String(x.id) === $('rcSession').value);
    const dorm = dorms.find((x) => String(x.id) === $('rcDorm').value);
    $('rcTitle').textContent = `${sess?.name || 'Roll call'}: ${dorm?.name || ''}`;
    if (!learners.length) { state('No learners are assigned to this dormitory.'); return; }
    state('');
    render();
  }

  function counts() {
    const c = { present: 0, absent: 0, sick_bay: 0, permission: 0, unknown: 0 };
    marks.forEach((v) => { c[v] = (c[v] || 0) + 1; });
    return c;
  }

  function renderCounts() {
    const c = counts();
    const expected = learners.length - c.permission;
    $('rcIn').textContent = num(c.present);
    $('rcOf').textContent = `of ${num(expected)} in`;
    $('rcFacts').innerHTML = [
      [c.unknown ? 'is-warn' : '', c.unknown, 'not counted yet'],
      [c.absent ? 'is-stop' : '', c.absent, 'absent'],
      ['', c.sick_bay, 'in the sick bay'],
      ['', c.permission, 'away on exeat'],
    ].map(([t, v, l]) => `<li class="${t}"><span class="ops-fact__v">${esc(num(v))}</span><span class="ops-fact__l">${esc(l)}</span></li>`).join('');
    const changed = [...marks].some(([k, v]) => saved.get(k) !== v);
    $('rcSave').disabled = !isToday() || c.unknown > 0 || !changed;
    $('rcAllIn').disabled = !isToday() || !c.unknown;
    $('rcNote').textContent = !isToday() ? 'This is an earlier roll call. It can be read but not changed.'
      : c.unknown ? `Count all ${c.unknown} remaining learner${c.unknown === 1 ? '' : 's'} before saving.`
        : changed ? 'Not saved yet.' : 'Saved.';
  }

  function render() {
    const q = $('rcSearch').value.trim().toLowerCase();
    const list = learners.filter((s) => !q || `${name(s)} ${s.admission_no || ''} ${s.bed_number || ''}`.toLowerCase().includes(q));
    const order = { unknown: 0, absent: 1, sick_bay: 2, present: 3, permission: 4 };
    $('rcList').innerHTML = list.sort((a, b) => (order[marks.get(Number(a.id))] - order[marks.get(Number(b.id))]) || name(a).localeCompare(name(b))).map((s) => {
      const st = marks.get(Number(s.id));
      const meta = [s.admission_no, s.class_name, s.bed_number ? `bed ${s.bed_number}` : ''].filter(Boolean).join(', ');
      return `<li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom" data-sid="${Number(s.id)}">
        <div class="me-auto"><div class="fw-bold">${esc(name(s))}</div><div class="small text-muted">${esc(meta)}</div></div>
        ${away(s) ? `<span class="ops-tag is-muted">${esc(s.permission_type || 'Away on exeat')}${s.permission_until ? ` until ${esc(String(s.permission_until).slice(0, 10))}` : ''}</span>`
          : `<span class="d-flex gap-2" role="group" aria-label="${esc(name(s))}">${MARKS.map(([v, l, on, off]) => `<button type="button" class="btn ${st === v ? on : off}" data-mark="${v}" aria-pressed="${st === v}" ${isToday() ? '' : 'disabled'}>${esc(l)}</button>`).join('')}</span>`}
      </li>`;
    }).join('') || '<li class="ops-empty">No learner matches that search.</li>';
    renderCounts();
  }

  async function save() {
    const attendance = learners.map((s) => ({ student_id: Number(s.id), status: away(s) ? 'permission' : marks.get(Number(s.id)) }));
    $('rcSave').disabled = true;
    try {
      await api('/attendance/mark-boarding', 'POST', { dormitory_id: Number($('rcDorm').value), session_id: Number($('rcSession').value), date: $('rcDate').value, attendance });
      marks.forEach((v, k) => saved.set(k, v));
      notify('Roll call saved.', 'success');
      renderCounts();
      loadAll();
    } catch (e) { notify(e.message || 'The roll call could not be saved.', 'danger'); renderCounts(); }
  }

  async function loadAll() {
    try {
      const list = rows(await api('/boarding/roll-call', 'GET', null, { date: $('rcDate').value }));
      $('rcAllBody').innerHTML = list.length ? list.map((r) => `<tr><td class="fw-semibold">${esc(r.dormitory_name)}</td><td>${esc(r.session_name)}</td>
        <td>${esc(num(r.total_students))}</td><td>${esc(num(r.present_count))}</td><td>${Number(r.absent_count) ? `<strong class="text-danger">${esc(num(r.absent_count))}</strong>` : '0'}</td>
        <td>${esc(num(r.permission_count))}</td><td>${esc(num(r.sick_bay_count))}</td></tr>`).join('')
        : '<tr><td colspan="7" class="text-muted p-3">No roll call has been saved for this day yet.</td></tr>';
    } catch (e) {
      $('rcAllBody').innerHTML = `<tr><td colspan="7" class="text-muted p-3">${esc(e.message || 'Counts could not be loaded.')}</td></tr>`;
    }
  }

  async function init() {
    await window.AuthContext?.ready?.();
    $('rcDate').value = today();
    $('rcDate').max = today();
    if (await loadRefs()) {
      ['rcDorm', 'rcSession'].forEach((id) => $(id).addEventListener('change', loadRoster));
      await loadRoster();
    }
    $('rcDate').addEventListener('change', () => { if (dorms.length) loadRoster(); loadAll(); });
    $('rcSearch').addEventListener('input', render);
    $('rcList').addEventListener('click', (e) => {
      const b = e.target.closest('[data-mark]');
      if (!b || !isToday()) return;
      const sid = Number(b.closest('[data-sid]').dataset.sid);
      marks.set(sid, b.dataset.mark);
      // Re-render only this row's buttons so the list does not jump while counting.
      b.parentElement.querySelectorAll('[data-mark]').forEach((x) => {
        const [v, , on, off] = MARKS.find((m) => m[0] === x.dataset.mark);
        const sel = v === b.dataset.mark;
        x.className = `btn ${sel ? on : off}`;
        x.setAttribute('aria-pressed', String(sel));
      });
      renderCounts();
    });
    $('rcAllIn').addEventListener('click', () => { marks.forEach((v, k) => { if (v === 'unknown') marks.set(k, 'present'); }); render(); });
    $('rcSave').addEventListener('click', save);
    $('rcPrint').addEventListener('click', () => window.print());
    $('rcCsv').addEventListener('click', () => O.exportCsv(`roll_call_${$('rcDate').value}.csv`, ['Learner', 'Admission no.', 'Class', 'Bed', 'Status'],
      learners.map((s) => [name(s), s.admission_no, s.class_name, s.bed_number, LABEL[marks.get(Number(s.id))] || ''])));
    if (!O.canExport()) $('rcCsv').hidden = true;
    window.addEventListener('beforeunload', (e) => { if ([...marks].some(([k, v]) => saved.get(k) !== v) && isToday()) e.preventDefault(); });
    window.APIRealtime?.register?.('boarding_roll_call', { refresh: () => loadAll() }, ['boarding', 'attendance']);
    await loadAll();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
