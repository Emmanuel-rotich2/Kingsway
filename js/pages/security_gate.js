/** Gate desk: visitors in and out, who is on site, exeats at the gate, gate log. */
(() => {
  'use strict';
  const root = document.getElementById('gateDesk');
  if (!root) return;
  const S = window.SecurityCommon;
  const { esc, api, data, time, PURPOSE, notify } = S;
  const $ = (id) => document.getElementById(id);
  const state = { learner: null, host: null, timer: null, refresh: null };
  const LONG_STAY_MIN = 180;

  async function loadSummary() {
    try {
      const s = data(await api('summary')) || {};
      $('gdOnSite').textContent = Number(s.on_site || 0);
      $('gdFigures').innerHTML = [
        [s.visitors_today, 'visitors today'],
        [s.denied_today, 'turned away'],
        [s.movements_today, 'gate log entries'],
        [s.exeats_out, 'learners out on exeat'],
      ].map(([n, l]) => `<li><strong>${Number(n || 0)}</strong>${l}</li>`).join('')
        + `<li><a href="home.php?route=school_security_incidents"><strong>${Number(s.open_incidents || 0)}</strong>open incidents</a></li>`;
    } catch (e) { /* the lists below show their own errors */ }
  }

  async function loadOnSite() {
    const list = $('gdOnSiteList');
    try {
      const rows = data(await api('visits', 'GET', null, { status: 'on_site' })) || [];
      list.innerHTML = rows.length ? rows.map((v) => {
        const long = Number(v.minutes_on_site) >= LONG_STAY_MIN;
        const who = v.student ? `${esc(v.student.full_name)} (${esc(v.student.class_name)})` : esc(v.host_name || '');
        const hrs = Math.floor(Number(v.minutes_on_site || 0) / 60), mins = Number(v.minutes_on_site || 0) % 60;
        return `<li class="${long ? 'is-long' : ''}">
          <span class="gate-time">${esc(time(v.checked_in_at))}</span>
          <span><span class="gate-who">${esc(v.visitor_name)}</span>${Number(v.people_count) > 1 ? ` <span class="gate-meta">+${Number(v.people_count) - 1}</span>` : ''}
            <span class="d-block gate-meta">${esc(PURPOSE[v.purpose] || v.purpose)}${who ? `, seeing ${who}` : ''}${v.badge_no ? `. Badge ${esc(v.badge_no)}` : ''}${v.vehicle_reg ? `. ${esc(v.vehicle_reg)}` : ''}. ${hrs ? `${hrs} h ` : ''}${mins} min on site</span></span>
          <span><button class="btn btn-outline-secondary btn-sm" type="button" data-out="${Number(v.id)}">Sign out</button></span></li>`;
      }).join('') : '<li class="gate-empty">Nobody is signed in.</li>';
    } catch (e) {
      list.innerHTML = `<li class="gate-empty text-danger">${esc(e.message || 'Visitors could not be loaded.')}</li>`;
    }
  }

  async function loadExeats() {
    const list = $('gdExeats');
    try {
      const rows = data(await api('exeats-today')) || [];
      list.innerHTML = rows.length ? rows.map((p) => {
        const st = p.student || {};
        const tag = p.gate_state === 'returned' ? '<span class="gate-tag is-clear">Back in school</span>'
          : p.gate_state === 'out' ? `<span class="gate-tag is-amber">Out${p.overdue ? ', overdue' : ''}</span>` : '<span class="gate-tag is-muted">May leave</span>';
        const btn = p.gate_state === 'may_leave' ? `<button class="btn btn-gate btn-sm" data-exeat="${Number(p.id)}" data-dir="out">Let out</button>`
          : p.gate_state === 'out' ? `<button class="btn btn-outline-secondary btn-sm" data-exeat="${Number(p.id)}" data-dir="in">Back in</button>` : '';
        return `<li><span class="gate-time">${esc(p.start_time ? String(p.start_time).slice(0, 5) : '')}</span>
          <span class="gate-learner">${st.photo_url ? `<img src="${esc(S.photo(st.photo_url))}" alt="">` : ''}<span><span class="gate-who">${esc(st.full_name || 'Learner')}</span>
            <span class="d-block gate-meta">${esc(st.admission_no || '')} ${esc(st.class_name || '')}, ${esc(st.boarding || '')}. ${esc(p.permission_type || 'Permission')}${p.expected_return ? `, due back ${esc(String(p.expected_return).slice(0, 16).replace('T', ' '))}` : ''}</span> ${tag}</span></span>
          <span>${btn}</span></li>`;
      }).join('') : '<li class="gate-empty">No approved exeats for today.</li>';
    } catch (e) {
      list.innerHTML = `<li class="gate-empty text-danger">${esc(e.message || 'Exeats could not be loaded.')}</li>`;
    }
  }

  async function loadMoves() {
    const body = $('gdMoves');
    try {
      const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
      const rows = data(await api('movements', 'GET', null, { from: today, to: today })) || [];
      body.innerHTML = rows.length ? rows.map((m) => `<tr><td>${esc(time(m.recorded_at))}</td><td>${m.direction === 'in' ? 'In' : 'Out'}</td><td>${esc(S.label(m.movement_type))}</td><td>${esc(m.vehicle_reg || '')}</td><td>${esc([m.person_name, m.description].filter(Boolean).join(': '))}</td><td>${esc(m.recorded_by_name || '')}</td></tr>`).join('')
        : '<tr><td colspan="6" class="text-muted">Nothing logged today.</td></tr>';
    } catch (e) {
      body.innerHTML = `<tr><td colspan="6" class="text-danger">${esc(e.message || 'The gate log could not be loaded.')}</td></tr>`;
    }
  }

  const refreshAll = () => Promise.all([loadSummary(), loadOnSite(), loadExeats(), loadMoves()]);

  // ---------------------------------------------------------- lookups
  function debounce(fn) { return (...a) => { clearTimeout(state.timer); state.timer = setTimeout(() => fn(...a), 300); }; }

  const searchLearner = debounce(async (q) => {
    const box = $('gdLearnerResults');
    if (q.trim().length < 2) { box.innerHTML = ''; return; }
    try {
      const rows = data(await api('learner-lookup', 'GET', null, { q })) || [];
      box.innerHTML = rows.map((l) => `<button type="button" class="list-group-item list-group-item-action" data-learner='${esc(JSON.stringify(l))}'>${esc(l.full_name)} <span class="text-muted">${esc(l.admission_no)} ${esc(l.class_name)}</span></button>`).join('') || '<div class="list-group-item text-muted">No learner found.</div>';
    } catch (e) { box.innerHTML = `<div class="list-group-item text-danger">${esc(e.message)}</div>`; }
  });

  const searchHost = debounce(async (q) => {
    const box = $('gdHostResults');
    state.host = null;
    if (q.trim().length < 2) { box.innerHTML = ''; return; }
    try {
      const rows = data(await api('host-lookup', 'GET', null, { q })) || [];
      box.innerHTML = rows.map((h) => `<button type="button" class="list-group-item list-group-item-action" data-host="${Number(h.staff_id)}" data-name="${esc(h.full_name)}">${esc(h.full_name)} <span class="text-muted">${esc(h.position || '')}</span></button>`).join('');
    } catch (e) { box.innerHTML = ''; }
  });

  function chooseLearner(l) {
    state.learner = l;
    $('gdLearnerResults').innerHTML = '';
    $('gdLearnerSearch').value = '';
    $('gdLearnerChosen').innerHTML = l ? `<div class="gate-learner">${l.photo_url ? `<img src="${esc(S.photo(l.photo_url))}" alt="Photo of ${esc(l.full_name)}">` : ''}<div><div class="gate-who">${esc(l.full_name)}</div><div class="gate-meta">${esc(l.admission_no)} ${esc(l.class_name)}, ${esc(l.boarding)}</div><button type="button" class="btn btn-link btn-sm px-0" id="gdLearnerClear">Change</button></div></div>` : '';
    $('gdLearnerClear')?.addEventListener('click', () => chooseLearner(null));
  }

  // ---------------------------------------------------------- actions
  function formPayload() {
    return {
      visitor_name: $('gdName').value.trim(),
      id_number: $('gdIdNo').value.trim(),
      phone: $('gdPhone').value.trim(),
      purpose: $('gdPurpose').value,
      organisation: $('gdOrg').value.trim(),
      student_id: state.learner?.id || null,
      host_staff_id: state.host?.id || null,
      host_note: state.host ? null : $('gdHostSearch').value.trim(),
      badge_no: $('gdBadge').value.trim(),
      vehicle_reg: $('gdVehicle').value.trim(),
      people_count: Number($('gdPeople').value) || 1,
      items_declared: $('gdItems').value.trim(),
    };
  }

  function resetForm() {
    $('gdForm').reset();
    chooseLearner(null);
    state.host = null;
    $('gdHostResults').innerHTML = '';
    $('gdName').focus();
  }

  async function signIn(deny = false, reason = '') {
    const p = formPayload();
    if (!p.visitor_name) { notify("Enter the visitor's name.", 'warning'); $('gdName').focus(); return false; }
    if (p.purpose === 'learner_pickup' && !p.student_id) { notify('Choose the learner being picked up.', 'warning'); return false; }
    if (deny) { p.deny = 1; p.denial_reason = reason; }
    try {
      await api('visits', 'POST', p);
      notify(deny ? `${p.visitor_name} recorded as turned away.` : `${p.visitor_name} signed in.`, deny ? 'warning' : 'success');
      resetForm();
      await refreshAll();
      return true;
    } catch (e) {
      notify(e.message || 'The visitor could not be signed in.', 'danger');
      return false;
    }
  }

  function bind() {
    $('gdForm').addEventListener('submit', (e) => { e.preventDefault(); signIn(false); });
    $('gdDeny').addEventListener('click', () => {
      if (!$('gdName').value.trim()) { notify("Enter the visitor's name first.", 'warning'); return; }
      $('gdDenyReason').value = '';
      bootstrap.Modal.getOrCreateInstance($('gdDenyModal')).show();
    });
    $('gdDenyConfirm').addEventListener('click', async () => {
      const reason = $('gdDenyReason').value.trim();
      if (!reason) { notify('Say why the visitor was turned away.', 'warning'); return; }
      if (await signIn(true, reason)) bootstrap.Modal.getInstance($('gdDenyModal'))?.hide();
    });
    $('gdPurpose').addEventListener('change', () => { $('gdLearnerSearch').placeholder = $('gdPurpose').value === 'learner_pickup' ? 'Learner being picked up (required)' : 'Learner name or admission number'; });
    $('gdLearnerSearch').addEventListener('input', (e) => searchLearner(e.target.value));
    $('gdHostSearch').addEventListener('input', (e) => searchHost(e.target.value));
    $('gdLearnerResults').addEventListener('click', (e) => { const b = e.target.closest('[data-learner]'); if (b) chooseLearner(JSON.parse(b.dataset.learner)); });
    $('gdHostResults').addEventListener('click', (e) => {
      const b = e.target.closest('[data-host]');
      if (!b) return;
      state.host = { id: Number(b.dataset.host), name: b.dataset.name };
      $('gdHostSearch').value = b.dataset.name;
      $('gdHostResults').innerHTML = '';
    });
    $('gdOnSiteList').addEventListener('click', async (e) => {
      const b = e.target.closest('[data-out]');
      if (!b) return;
      b.disabled = true;
      try { await api(`visits-checkout/${Number(b.dataset.out)}`, 'POST', {}); notify('Signed out.', 'success'); await refreshAll(); }
      catch (err) { b.disabled = false; notify(err.message || 'Could not sign out.', 'danger'); }
    });
    $('gdExeats').addEventListener('click', async (e) => {
      const b = e.target.closest('[data-exeat]');
      if (!b) return;
      const out = b.dataset.dir === 'out';
      if (out && !(await window.confirmAction('Let learner out', 'Check the learner\'s face against the photo and the person collecting them before letting them out.', { confirmText: 'Let out' }))) return;
      b.disabled = true;
      try { await api(`exeat-gate/${Number(b.dataset.exeat)}`, 'POST', { direction: b.dataset.dir }); notify(out ? 'Learner signed out.' : 'Learner back in school.', 'success'); await refreshAll(); }
      catch (err) { b.disabled = false; notify(err.message || 'Could not record this.', 'danger'); }
    });
    $('gdMoveForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const p = { direction: $('gdMoveDir').value, movement_type: $('gdMoveType').value, vehicle_reg: $('gdMoveReg').value.trim(), description: $('gdMoveNote').value.trim() };
      if (!p.vehicle_reg && !p.description) { notify('Enter the vehicle or what happened.', 'warning'); return; }
      try { await api('movements', 'POST', p); $('gdMoveForm').reset(); await Promise.all([loadMoves(), loadSummary()]); }
      catch (err) { notify(err.message || 'Could not log this.', 'danger'); }
    });
    S.wireReport($('gdReportIncident'), () => loadSummary());
  }

  async function init() {
    await window.AuthContext?.ready?.();
    bind();
    await refreshAll();
    // The desk stays current while it is open; paused when the tab is hidden.
    state.refresh = setInterval(() => { if (!document.hidden && document.body.contains(root)) refreshAll(); }, 60000);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
