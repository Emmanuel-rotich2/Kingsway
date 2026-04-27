/**
 * Term Transition Controller
 * Wizard to close Term 1, roll over timetable, and activate Term 2.
 * API: /academic/terms, /schedules/timetable-get, /schedules/timetable-create
 */
const termTransitionController = {

  _step:       1,
  _currentTerm: null,
  _nextTerm:    null,
  _allTerms:    [],
  _timetableSlots: [],

  // ── Init ──────────────────────────────────────────────────────────────────
  init: async function () {
    if (!AuthContext.isAuthenticated()) {
      window.location.href = (window.APP_BASE || '') + '/index.php';
      return;
    }
    await Promise.all([this._loadTerms(), this._loadTimetableStats()]);
    this._renderStep1();
  },

  // ── Load data ─────────────────────────────────────────────────────────────
  _loadTerms: async function () {
    try {
      const r = await callAPI('/academic/terms-list', 'GET');
      this._allTerms = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      this._currentTerm = this._allTerms.find(t => (t.status || '').toLowerCase() === 'current');
      this._nextTerm    = this._allTerms.find(t => (t.status || '').toLowerCase() === 'upcoming' &&
                          Number(t.term_number) === Number(this._currentTerm?.term_number || 0) + 1);

      const badge = document.getElementById('ttCurrentTermBadge');
      if (badge && this._currentTerm) {
        badge.textContent = `Current: ${this._currentTerm.name} ${this._currentTerm.year || ''}`;
      }
      const closeBtn = document.getElementById('ttCloseTermBtn');
      if (closeBtn && this._currentTerm) {
        closeBtn.textContent = `Close ${this._currentTerm.name}`;
      }
      const nameEl = document.getElementById('ttCurrentTermName');
      if (nameEl && this._currentTerm) nameEl.textContent = this._currentTerm.name;

      // Pre-fill Term 2 dates if we have them
      if (this._nextTerm) {
        const s = document.getElementById('ttTerm2Start');
        const e = document.getElementById('ttTerm2End');
        if (s && this._nextTerm.start_date) s.value = this._nextTerm.start_date;
        if (e && this._nextTerm.end_date)   e.value = this._nextTerm.end_date;
      }
    } catch (e) {
      console.warn('Terms load failed:', e);
    }
  },

  _loadTimetableStats: async function () {
    try {
      const params = this._currentTerm
        ? new URLSearchParams({ term_id: this._currentTerm.id }).toString()
        : '';
      const r = await callAPI('/schedules/timetable-get' + (params ? '?' + params : ''), 'GET');
      this._timetableSlots = Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
      const classes  = [...new Set(this._timetableSlots.map(s => s.class_id))].length;
      const teachers = [...new Set(this._timetableSlots.map(s => s.teacher_id).filter(Boolean))].length;
      this._set('ttSlotCount',   this._timetableSlots.length);
      this._set('ttClassCount',  classes);
      this._set('ttTeacherCount',teachers);
    } catch (e) {
      console.warn('Timetable stats failed:', e);
    }
  },

  // ── Step navigation ───────────────────────────────────────────────────────
  goStep: function (step) {
    this._step = step;
    const stepIds = ['ttStep1','ttStep2','ttStep3','ttStep4','ttStep5','ttDone'];
    stepIds.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });
    const target = step === 'done' ? 'ttDone' : 'ttStep' + step;
    const el = document.getElementById(target);
    if (el) el.style.display = '';

    document.querySelectorAll('.tt-step').forEach(el => {
      const s = parseInt(el.dataset.step);
      el.classList.toggle('active', s === step);
      el.classList.toggle('done',   s < step);
    });

    if (step === 4) this._renderTerm2Setup();
    if (step === 5) this._renderActivateSummary();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  },

  // ── STEP 1: Review ────────────────────────────────────────────────────────
  _renderStep1: async function () {
    const statsEl     = document.getElementById('ttReviewStats');
    const completedEl = document.getElementById('ttCompletedList');
    const pendingEl   = document.getElementById('ttPendingList');

    // Stats
    const term = this._currentTerm;
    const slots = this._timetableSlots.length;
    if (statsEl) {
      statsEl.innerHTML = `
        <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold text-primary">${this._esc(term?.name || '—')}</div>
          <div class="text-muted small">Current Term</div>
        </div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold">${this._esc(term?.start_date || '—')}</div>
          <div class="text-muted small">Start Date</div>
        </div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold">${this._esc(term?.end_date || '—')}</div>
          <div class="text-muted small">End Date</div>
        </div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm text-center p-3">
          <div class="fs-2 fw-bold text-${slots > 0 ? 'success' : 'danger'}">${slots}</div>
          <div class="text-muted small">Timetable Slots</div>
        </div></div>`;
    }

    // Load counts for completed/pending checklist
    try {
      const [rLP, rAttn, rResults] = await Promise.allSettled([
        callAPI('/academic/lesson-plans-list?term_id=' + (term?.id || ''), 'GET'),
        callAPI('/attendance/summary?term_id=' + (term?.id || ''), 'GET'),
        callAPI('/academic/results-list?term_id=' + (term?.id || ''), 'GET'),
      ]);

      const lp      = this._extract(rLP);
      const approved = lp.filter(p => (p.status || '') === 'approved').length;
      const pending  = lp.filter(p => ['draft','submitted'].includes(p.status || '')).length;

      if (completedEl) completedEl.innerHTML = this._checkItem('Timetable configured', slots > 0) +
        this._checkItem(`Lesson plans approved (${approved})`, approved > 0) +
        this._checkItem('Next term dates set', !!this._nextTerm?.start_date);

      if (pendingEl) pendingEl.innerHTML = this._warnItem('Lesson plans pending review', pending > 0, pending + ' still in draft/submitted') +
        this._warnItem('Timetable empty', slots === 0, 'No class schedules found for this term') +
        this._warnItem('Next term not configured', !this._nextTerm, 'Term 2 has no start/end dates');
    } catch (e) {
      if (completedEl) completedEl.innerHTML = '<div class="alert alert-warning">Could not load full review data.</div>';
    }
  },

  _checkItem: (label, done) => `
    <div class="d-flex align-items-center gap-2 mb-2">
      <i class="bi bi-${done ? 'check-circle-fill text-success' : 'circle text-muted'}"></i>
      <span class="${done ? 'text-success' : 'text-muted'}">${label}</span>
    </div>`,

  _warnItem: (label, isIssue, detail = '') => isIssue ? `
    <div class="d-flex align-items-start gap-2 mb-2">
      <i class="bi bi-exclamation-triangle-fill text-warning mt-1"></i>
      <div><div class="fw-semibold">${label}</div>
        ${detail ? `<div class="small text-muted">${detail}</div>` : ''}</div>
    </div>` : '',

  // ── STEP 2: Close term ────────────────────────────────────────────────────
  closeTerm: async function () {
    const confirmed = document.getElementById('ttConfirmClose')?.checked;
    const errEl     = document.getElementById('ttCloseError');
    if (!confirmed) {
      if (errEl) { errEl.textContent = 'Please confirm that you have finalised all term data.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (!this._currentTerm) {
      if (errEl) { errEl.textContent = 'No current term found.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');
    const btn = document.getElementById('ttCloseTermBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> Closing…'; }

    try {
      await callAPI('/academic/terms-update/' + this._currentTerm.id, 'PUT', { status: 'completed' });
      this._currentTerm.status = 'completed';
      showNotification(`${this._currentTerm.name} closed successfully.`, 'success');
      this.goStep(3);
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Failed to close term.'; errEl.classList.remove('d-none'); }
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = `<i class="bi bi-lock me-1"></i> Close ${this._currentTerm?.name}`; }
    }
  },

  // ── STEP 3: Rollover timetable ────────────────────────────────────────────
  rolloverTimetable: async function () {
    if (!this._nextTerm) {
      showNotification('Term 2 must exist in the database before rolling over the timetable.', 'warning');
      return;
    }
    if (!this._timetableSlots.length) {
      showNotification('No timetable slots found in Term 1 to roll over.', 'warning');
      this.goStep(4);
      return;
    }
    const keepTeachers = document.getElementById('ttKeepTeachers')?.checked ?? true;
    const keepRooms    = document.getElementById('ttKeepRooms')?.checked ?? true;
    const errEl        = document.getElementById('ttRolloverError');
    if (errEl) errEl.classList.add('d-none');

    this._set('ttRolloverStatus', 'Working…');

    try {
      // Create new class_schedule entries for Term 2 from Term 1 entries
      let created = 0; let failed = 0;
      for (const slot of this._timetableSlots) {
        const newSlot = {
          class_id:        slot.class_id,
          day_of_week:     slot.day_of_week,
          start_time:      slot.start_time,
          end_time:        slot.end_time,
          subject_id:      slot.subject_id,
          teacher_id:      keepTeachers ? slot.teacher_id : null,
          room_id:         keepRooms    ? slot.room_id    : null,
          academic_year_id:slot.academic_year_id,
          term_id:         this._nextTerm.id,
          period_number:   slot.period_number,
          status:          'active',
        };
        try {
          await callAPI('/schedules/timetable-create', 'POST', newSlot);
          created++;
        } catch { failed++; }
      }

      this._set('ttRolloverStatus', `Done (${created} slots)`);
      showNotification(`Timetable rolled over: ${created} slots created${failed ? `, ${failed} failed` : ''}.`, 'success');
      this.goStep(4);
    } catch (e) {
      this._set('ttRolloverStatus', 'Failed');
      if (errEl) { errEl.textContent = e.message || 'Rollover failed.'; errEl.classList.remove('d-none'); }
    }
  },

  // ── STEP 4: Term 2 setup ──────────────────────────────────────────────────
  _renderTerm2Setup: function () {
    const cl = document.getElementById('ttSetupChecklist');
    if (!cl) return;
    const term2 = this._nextTerm;
    const checks = [
      { label: 'Term 2 dates configured',    done: !!(term2?.start_date && term2?.end_date) },
      { label: 'Timetable rolled over',       done: true }, // assumed since we just did it
      { label: 'Exam schedule (set after activation)', done: false },
      { label: 'Schemes of work due',         done: false },
    ];
    cl.innerHTML = checks.map(c => `
      <div class="col-md-6">
        <div class="d-flex align-items-center gap-2 p-2 border rounded ${c.done?'border-success bg-success bg-opacity-10':''}">
          <i class="bi bi-${c.done?'check-circle-fill text-success':'circle text-muted'}"></i>
          <span class="${c.done?'text-success':'text-muted'} small">${c.label}</span>
        </div>
      </div>`).join('');
  },

  saveTerm2Setup: async function () {
    const start = document.getElementById('ttTerm2Start')?.value;
    const end   = document.getElementById('ttTerm2End')?.value;
    const mbs   = document.getElementById('ttMidtermStart')?.value;
    const mbe   = document.getElementById('ttMidtermEnd')?.value;
    const errEl = document.getElementById('ttSetupError');

    if (!start || !end) {
      if (errEl) { errEl.textContent = 'Start and end dates are required.'; errEl.classList.remove('d-none'); }
      return;
    }
    if (errEl) errEl.classList.add('d-none');

    if (this._nextTerm) {
      try {
        await callAPI('/academic/terms-update/' + this._nextTerm.id, 'PUT', {
          start_date: start, end_date: end,
          midterm_break_start: mbs || null, midterm_break_end: mbe || null,
        });
        if (this._nextTerm) {
          this._nextTerm.start_date = start;
          this._nextTerm.end_date   = end;
        }
        showNotification('Term 2 dates saved.', 'success');
      } catch (e) {
        if (errEl) { errEl.textContent = e.message || 'Save failed.'; errEl.classList.remove('d-none'); }
        return;
      }
    }
    this.goStep(5);
  },

  // ── STEP 5: Activate ──────────────────────────────────────────────────────
  _renderActivateSummary: function () {
    const el = document.getElementById('ttActivateSummary');
    if (!el) return;
    const t = this._nextTerm;
    el.innerHTML = `
      <div class="col-md-3"><div class="card border-0 bg-success bg-opacity-10 text-center p-3">
        <div class="fw-bold fs-5">${this._esc(t?.name || '—')}</div><div class="small text-muted">Will Become Current</div></div></div>
      <div class="col-md-3"><div class="card border-0 bg-light text-center p-3">
        <div class="fw-bold">${this._esc(t?.start_date || '—')}</div><div class="small text-muted">Starts</div></div></div>
      <div class="col-md-3"><div class="card border-0 bg-light text-center p-3">
        <div class="fw-bold">${this._esc(t?.end_date || '—')}</div><div class="small text-muted">Ends</div></div></div>
      <div class="col-md-3"><div class="card border-0 bg-light text-center p-3">
        <div class="fw-bold">${this._timetableSlots.length}</div><div class="small text-muted">Timetable Slots</div></div></div>`;
  },

  activateTerm2: async function () {
    if (!this._nextTerm) {
      showNotification('Term 2 not found.', 'danger');
      return;
    }
    const btn   = document.getElementById('ttActivateBtn');
    const errEl = document.getElementById('ttActivateError');
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner-border spinner-border-sm me-1"></div> Activating…'; }
    if (errEl) errEl.classList.add('d-none');

    try {
      await callAPI('/academic/terms-update/' + this._nextTerm.id, 'PUT', { status: 'current' });
      showNotification('Term 2 is now active!', 'success');
      this.goStep('done');
    } catch (e) {
      if (errEl) { errEl.textContent = e.message || 'Activation failed.'; errEl.classList.remove('d-none'); }
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-play-fill me-1"></i> Activate Term 2 Now'; }
    }
  },

  // ── Utilities ─────────────────────────────────────────────────────────────
  _extract: function (settled) {
    if (settled.status !== 'fulfilled') return [];
    const r = settled.value;
    return Array.isArray(r?.data) ? r.data : (Array.isArray(r) ? r : []);
  },
  _set: (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; },
  _esc: s => { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; },
};

document.addEventListener('DOMContentLoaded', () => termTransitionController.init());
